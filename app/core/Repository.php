<?php
// app/core/Repository.php

abstract class Repository
{
    protected $pdo;
    protected $table;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Busca um registro pelo ID
     */
    public function find(int $id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `{$this->table}` WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Busca todos os registros com filtros opcionais
     */
    public function all(array $filters = [], string $orderBy = 'id DESC')
    {
        $where = '';
        $params = [];

        if (!empty($filters)) {
            $conditions = [];
            foreach ($filters as $column => $value) {
                $conditions[] = "`{$column}` = ?";
                $params[] = $value;
            }
            $where = ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql = "SELECT * FROM `{$this->table}`{$where} ORDER BY {$orderBy}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    /**
     * Exclui um registro pelo ID
     */
    public function delete(int $id)
    {
        $stmt = $this->pdo->prepare("DELETE FROM `{$this->table}` WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
