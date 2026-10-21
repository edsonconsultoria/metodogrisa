<?php
// app/core/CrudEngine.php

class CrudEngine
{
    private $pdo;
    private $table;
    private $schema = [];
    private $primaryKey = 'id';
    private $lookups = [];

    public function __construct(PDO $pdo, string $table)
    {
        $this->pdo = $pdo;
        $this->table = preg_replace('/[^a-z0-9_]/i', '', $table);
        $this->loadSchema();
    }

    public function setLookups(array $lookups)
    {
        $this->lookups = $lookups;
    }

    public function addVirtualColumn($name, $expression, $searchable = false)
    {
        $this->schema[$name] = [
            'type' => 'virtual',
            'expression' => $expression,
            'searchable' => (bool) $searchable,
            'null' => true,
            'key' => '',
            'default' => null,
            'extra' => ''
        ];
    }

    private function loadSchema()
    {
        $stmt = $this->pdo->prepare("DESCRIBE `{$this->table}`");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($columns as $col) {
            $this->schema[$col['Field']] = [
                'type' => $this->parseType($col['Type']),
                'null' => $col['Null'] === 'YES',
                'key' => $col['Key'],
                'default' => $col['Default'],
                'extra' => $col['Extra']
            ];
            if ($col['Key'] === 'PRI') {
                $this->primaryKey = $col['Field'];
            }
        }
    }

    private function parseType($type)
    {
        if (str_contains($type, 'int'))
            return 'number';
        if (str_contains($type, 'decimal') || str_contains($type, 'float'))
            return 'decimal';
        if (str_contains($type, 'date'))
            return 'date';
        if (str_contains($type, 'time'))
            return 'time';
        if (str_contains($type, 'text'))
            return 'textarea';
        return 'text';
    }

    public function getList(int $page = 1, int $limit = 20, string $search = '', array $filters = [], string $orderBy = '', array $joins = [])
    {
        $offset = ($page - 1) * $limit;
        $where = [];
        $params = [];

        $joinClause = '';
        if (!empty($joins)) {
            foreach ($joins as $alias => $config) {
                $target = $config['table'];
                $on = $config['on'];
                $joinClause .= " LEFT JOIN `{$target}` AS `{$alias}` ON {$on} ";
            }
        }

        if ($search !== '') {
            $searchParts = [];
            $index = 0;
            foreach ($this->schema as $col => $info) {
                if (isset($info['type']) && $info['type'] === 'virtual') {
                    if (!empty($info['searchable'])) {
                        $placeholder = ":search_" . $index;
                        $searchParts[] = "({$info['expression']}) LIKE $placeholder";
                        $params[$placeholder] = "%$search%";
                        $index++;
                    }
                    continue;
                }
                $placeholder = ":search_" . $index;
                $searchParts[] = "`t`.`$col` LIKE $placeholder";
                $params[$placeholder] = "%$search%";
                $index++;
            }
            if (!empty($searchParts)) {
                $where[] = '(' . implode(' OR ', $searchParts) . ')';
            }
        }

        foreach ($filters as $col => $val) {
            if ($col === '_custom_where' && $val !== '') {
                $where[] = $val;
                continue;
            }
            if (isset($this->schema[$col]) && $val !== '' && (!isset($this->schema[$col]['type']) || $this->schema[$col]['type'] !== 'virtual')) {
                $where[] = "`t`.`$col` = :filter_$col";
                $params[":filter_$col"] = $val;
            }
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sqlCount = "SELECT COUNT(*) FROM `{$this->table}` AS `t` $joinClause $whereClause";
        $stmtCount = $this->pdo->prepare($sqlCount);
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        $orderClause = "`t`.`{$this->primaryKey}` DESC";
        if ($orderBy !== '') {
            $segments = array_filter(array_map('trim', explode(',', $orderBy)));
            $parsedParts = [];
            foreach ($segments as $segment) {
                $tokens = preg_split('/\s+/', trim($segment));
                $col = preg_replace('/[^a-z0-9_.]/i', '', $tokens[0] ?? '');
                $dir = isset($tokens[1]) && strtoupper($tokens[1]) === 'DESC' ? 'DESC' : 'ASC';
                if ($col === '')
                    continue;
                if (str_contains($col, '.')) {
                    $parsedParts[] = "$col $dir";
                } elseif (isset($this->schema[$col])) {
                    if (isset($this->schema[$col]['type']) && $this->schema[$col]['type'] === 'virtual') {
                        $parsedParts[] = "`$col` $dir";
                    } else {
                        $parsedParts[] = "`t`.`$col` $dir";
                    }
                }
            }
            if (!empty($parsedParts)) {
                $orderClause = implode(', ', $parsedParts);
            }
        }
        $selectCols = ["`t`.*"];
        foreach ($this->schema as $col => $info) {
            if (isset($info['type']) && $info['type'] === 'virtual') {
                $selectCols[] = "({$info['expression']}) AS `{$col}`";
            }
        }
        $selectClause = implode(', ', $selectCols);

        $sql = "SELECT $selectClause FROM `{$this->table}` AS `t` $joinClause $whereClause ORDER BY $orderClause LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll();

        // Resolve lookups
        if (!empty($this->lookups) && !empty($data)) {
            foreach ($this->lookups as $col => $lookup) {
                if (!isset($this->schema[$col]))
                    continue;

                $targetTable = $lookup['table'];
                $targetId = $lookup['id'] ?? 'id';
                $targetLabel = $lookup['label'] ?? $lookup['column'] ?? 'nome';
                $extraFields = $lookup['extra_fields'] ?? [];

                $ids = array_unique(array_filter(array_column($data, $col)));
                if (empty($ids))
                    continue;

                $fieldsToFetch = array_unique(array_merge([$targetId, $targetLabel], $extraFields));
                $fieldsStr = implode('`, `', $fieldsToFetch);

                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $lStmt = $this->pdo->prepare("SELECT `{$fieldsStr}` FROM `{$targetTable}` WHERE `{$targetId}` IN ($placeholders)");
                $lStmt->execute(array_values($ids));
                $lookupsData = $lStmt->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);

                foreach ($data as &$row) {
                    if (isset($lookupsData[$row[$col]])) {
                        $row[$col . '_label'] = $lookupsData[$row[$col]][$targetLabel] ?? $row[$col];
                        foreach ($extraFields as $f) {
                            $row[$col . '_' . $f] = $lookupsData[$row[$col]][$f] ?? null;
                        }
                    }
                }
                unset($row);
            }
        }

        return [
            'data' => $data,
            'total' => $total,
            'totalPages' => ceil($total / $limit)
        ];
    }

    public function getRecord($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?");
        $stmt->execute([$id]);
        $record = $stmt->fetch();

        if ($record && !empty($this->lookups)) {
            foreach ($this->lookups as $col => $lookup) {
                if (isset($record[$col]) && $record[$col] !== null) {
                    $targetTable = $lookup['table'];
                    $targetId = $lookup['id'] ?? 'id';
                    $targetLabel = $lookup['label'] ?? $lookup['column'] ?? 'nome';
                    $extraFields = $lookup['extra_fields'] ?? [];

                    $fieldsToFetch = array_unique(array_merge([$targetId, $targetLabel], $extraFields));
                    $fieldsStr = implode('`, `', $fieldsToFetch);

                    $lStmt = $this->pdo->prepare("SELECT `{$fieldsStr}` FROM `{$targetTable}` WHERE `{$targetId}` = ?");
                    $lStmt->execute([$record[$col]]);
                    $lData = $lStmt->fetch(PDO::FETCH_ASSOC);

                    if ($lData !== false) {
                        $record[$col . '_label'] = $lData[$targetLabel];
                        foreach ($extraFields as $f) {
                            $record[$col . '_' . $f] = $lData[$f];
                        }
                    }
                }
            }
        }
        return $record;
    }

    public function save(array $data, $id = null)
    {
        $fields = [];
        $params = [];

        foreach ($data as $key => $val) {
            if (isset($this->schema[$key]) && $key !== $this->primaryKey) {
                $fields[] = "`$key` = :$key";
                $params[":$key"] = $val === '' ? null : $val;
            }
        }

        if ($id) {
            $sql = "UPDATE `{$this->table}` SET " . implode(', ', $fields) . " WHERE `{$this->primaryKey}` = :id";
            $params[':id'] = $id;
        } else {
            $cols = array_keys($this->schema);
            $cols = array_filter($cols, function ($c) {
                return $c !== $this->primaryKey || !str_contains($this->schema[$c]['extra'], 'auto_increment');
            });

            $placeholders = array_map(function ($c) {
                return ":$c";
            }, $cols);
            $sql = "INSERT INTO `{$this->table}` (`" . implode('`, `', $cols) . "`) VALUES (" . implode(', ', $placeholders) . ")";

            $params = [];
            foreach ($cols as $col) {
                $params[":$col"] = $data[$col] ?? $this->schema[$col]['default'];
                if ($params[":$col"] === '' && $this->schema[$col]['null'])
                    $params[":$col"] = null;
            }
        }

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete($id)
    {
        if ($this->table === '104_turmas_disciplinas') {
            $record = $this->getRecord($id);
            if ($record) {
                $turma = (int) $record['turma'];
                $disciplina = (int) $record['disciplina'];

                // Excluir notas vinculadas a esta turma e disciplina
                $stmtNotes = $this->pdo->prepare("DELETE FROM `104_turmas_notas` WHERE `turma` = ? AND `disciplina` = ?");
                $stmtNotes->execute([$turma, $disciplina]);

                // Registrar log do evento de cascata
                log_event($this->pdo, '104_turmas_notas', 'EXCLUSAO', "Exclusão em cascata: notas da turma $turma e disciplina $disciplina apagadas devido à exclusão da disciplina da turma.");
            }
        }

        $stmt = $this->pdo->prepare("DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?");
        return $stmt->execute([$id]);
    }

    public function getSchema()
    {
        return $this->schema;
    }

    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }

    public function getLookupsOptions()
    {
        $options = [];
        foreach ($this->lookups as $field => $config) {
            $table = $config['table'];
            $label = $config['label'] ?? $config['column'] ?? 'nome';
            $sql = "SELECT `id`, `$label` as label FROM `$table` ORDER BY `$label`";
            try {
                $stmt = $this->pdo->query($sql);
                $options[$field] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            } catch (Exception $e) {
                $options[$field] = [];
            }
        }
        return $options;
    }
}
