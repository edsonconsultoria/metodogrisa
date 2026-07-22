<?php
// app/modules/generic/list.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/pagination.php';

$tableName = $_TABLE ?? $_GET['m'] ?? '';
if (!$tableName) {
    echo '<div class="alert alert-danger">Tabela não especificada.</div>';
    return;
}

$engine = new CrudEngine($pdo, $tableName);
if (isset($_LOOKUPS) && is_array($_LOOKUPS)) {
    $engine->setLookups($_LOOKUPS);
}

if (isset($_VIRTUAL_COLUMNS) && is_array($_VIRTUAL_COLUMNS)) {
    foreach ($_VIRTUAL_COLUMNS as $name => $def) {
        if (is_array($def)) {
            // Formato estendido: ['expression' => '...', 'searchable' => true]
            $engine->addVirtualColumn($name, $def['expression'], $def['searchable'] ?? false);
        } else {
            // Formato simples: string com a expressão SQL
            $engine->addVirtualColumn($name, $def);
        }
    }
}

if (strpos($tableName, '102_') === 0 && !current_user_is_at_least_admin()) {
    echo '<div class="alert alert-danger mt-3">Acesso negado. Apenas administradores podem acessar os dados financeiros.</div>';
    include __DIR__ . '/../../templates/footer.php';
    exit;
}

$page = (int) ($_GET['page'] ?? 1);
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? '';
$order = $_GET['order'] ?? 'ASC';

$orderBy = '';
if ($sort) {
    $orderBy = "$sort $order";
}

// Coletar filtros de select extras definidos pelo módulo
$activeSelectFilters = [];
if (!empty($_SELECT_FILTERS) && is_array($_SELECT_FILTERS)) {
    foreach ($_SELECT_FILTERS as $sf) {
        $val = isset($_GET[$sf['name']]) ? trim($_GET[$sf['name']]) : '';
        if ($val !== '') {
            $activeSelectFilters[$sf['column']] = $val;
        }
    }
}

$result = $engine->getList($page, 20, $search, $activeSelectFilters, $orderBy);
$data = $result['data'];
$totalPages = $result['totalPages'];
$schema = $engine->getSchema();
$primaryKey = $engine->getPrimaryKey();

$baseHref = BASE_URL . 'index.php';
$queryParams = ['m' => $tableName, 'a' => 'list'];
if ($search)
    $queryParams['search'] = $search;
if ($sort) {
    $queryParams['sort'] = $sort;
    $queryParams['order'] = $order;
}
// Preservar filtros de select na paginação e nos links de ordenação
if (!empty($_SELECT_FILTERS) && is_array($_SELECT_FILTERS)) {
    foreach ($_SELECT_FILTERS as $sf) {
        $val = isset($_GET[$sf['name']]) ? trim($_GET[$sf['name']]) : '';
        if ($val !== '') {
            $queryParams[$sf['name']] = $val;
        }
    }
}

$formatPtBrDate = function ($date) {
    if (!$date || $date === '0000-00-00')
        return '—';
    return date('d/m/Y', strtotime($date));
};
?>

<div class="row align-items-center mb-3 g-3">
    <div class="col-12 col-md-auto">
        <h4 class="mb-0"><?php echo get_table_label($tableName); ?></h4>
    </div>
    <div class="col-12 col-md text-md-end">
        <?php $newUrl = $baseHref . '?' . http_build_query(array_merge($_GET, ['a' => 'form'])); ?>
        <a href="<?php echo $newUrl; ?>"
            class="btn btn-primary btn-sm w-100 w-md-auto">
            + Novo(a) <?php echo get_table_label($tableName); ?>
        </a>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <form action="<?php echo $baseHref; ?>" method="get" class="row g-2">
            <input type="hidden" name="m" value="<?php echo htmlspecialchars($tableName); ?>">
            <input type="hidden" name="a" value="list">
            <?php if ($sort): ?>
                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                <input type="hidden" name="order" value="<?php echo htmlspecialchars($order); ?>">
            <?php endif; ?>
            <?php
            $hasSelects    = !empty($_SELECT_FILTERS) && is_array($_SELECT_FILTERS);
            $numSelects    = $hasSelects ? count($_SELECT_FILTERS) : 0;
            $inputColClass = $numSelects > 0 ? 'col-12 col-md' : 'col-12 col-md-10';
            ?>
            <div class="<?php echo $inputColClass; ?>">
                <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>"
                    placeholder="Buscar em todos os campos...">
            </div>
            <?php if ($hasSelects): ?>
                <?php foreach ($_SELECT_FILTERS as $sf): ?>
                    <?php $sfVal = isset($_GET[$sf['name']]) ? $_GET[$sf['name']] : ''; ?>
                    <div class="col-12 col-md-3">
                        <select name="<?php echo htmlspecialchars($sf['name']); ?>" class="form-select">
                            <option value=""><?php echo htmlspecialchars($sf['label']); ?></option>
                            <?php foreach ($sf['options'] as $opt): ?>
                                <option value="<?php echo htmlspecialchars($opt['value']); ?>"
                                    <?php echo ($sfVal == $opt['value']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($opt['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <div class="col-12 col-md-2">
                <button type="submit" class="btn btn-secondary w-100">Buscar</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <?php $bulkActionUrl = $baseHref . '?' . http_build_query(array_merge($_GET, ['a' => 'bulk'])); ?>
            <form id="bulkActionForm" method="post" action="<?php echo $bulkActionUrl; ?>">
                <table class="table table-striped table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <?php if (isset($_BULK_ACTIONS)): ?>
                            <th style="width: 40px;"><input type="checkbox" class="form-check-input" id="selectAll"></th>
                        <?php endif; ?>
                        <th style="width: 120px;" class="text-start">Ações</th>
                        <?php
                        $columnsToDisplay = isset($_LIST_COLUMNS) ? array_intersect_key($schema, array_flip($_LIST_COLUMNS)) : $schema;
                        if (isset($_LIST_COLUMNS)) {
                            // Garantir a ordem definida em $_LIST_COLUMNS
                            $ordered = [];
                            foreach ($_LIST_COLUMNS as $c) {
                                if (isset($schema[$c])) $ordered[$c] = $schema[$c];
                            }
                            $columnsToDisplay = $ordered;
                        }

                        $maxCols     = isset($_MAX_COLUMNS) ? (int) $_MAX_COLUMNS : 6;
                        $visibleCols = 0;
                        foreach ($columnsToDisplay as $col => $info):
                            if ($visibleCols >= $maxCols)
                                break;
                            $visibleCols++;
                            ?>
                                <?php
                                $label = $_LABELS[$col] ?? ucfirst($col);
                                $label = str_replace('_', ' ', $label);
                                
                                // Determinar próxima ordem
                                $nextOrder = ($sort === $col && $order === 'ASC') ? 'DESC' : 'ASC';
                                $sortIcon = '';
                                if ($sort === $col) {
                                    $sortIcon = $order === 'ASC' ? ' <i class="bi bi-sort-up"></i>' : ' <i class="bi bi-sort-down"></i>';
                                }
                                
                                $sortParams = $_GET;
                                unset($sortParams['page']);
                                $sortUrl = $baseHref . '?' . http_build_query(array_merge($sortParams, ['sort' => $col, 'order' => $nextOrder]));
                                ?>
                                <th>
                                    <a href="<?php echo $sortUrl; ?>" class="text-decoration-none text-dark">
                                        <?php echo htmlspecialchars($label); ?><?php echo $sortIcon; ?>
                                    </a>
                                </th>
                            <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                        <?php if (empty($data)): ?>
                            <tr>
                                <td colspan="<?php echo $visibleCols + (isset($_BULK_ACTIONS) ? 2 : 1); ?>"
                                    class="text-center py-4 text-muted">Nenhum registro encontrado.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($data as $row): ?>
                                <tr>
                                    <?php if (isset($_BULK_ACTIONS)): ?>
                                        <td><input type="checkbox" name="ids[]" value="<?php echo $row[$primaryKey]; ?>"
                                                class="form-check-input row-checkbox"></td>
                                    <?php endif; ?>
                                    <td class="text-start">
                                    <?php
                                    $rowId = $row[$primaryKey];
                                    $baseParams = $_GET;
                                    unset($baseParams['id']); // Prevenção
                                    
                                    $viewParams = array_merge($baseParams, ['a' => 'view', 'id' => $rowId]);
                                    $editParams = array_merge($baseParams, ['a' => 'form', 'id' => $rowId]);
                                    $deleteParams = array_merge($baseParams, ['a' => 'delete', 'id' => $rowId]);
                                    
                                    $viewUrl = $baseHref . '?' . http_build_query($viewParams);
                                    $editUrl = $baseHref . '?' . http_build_query($editParams);
                                    $deleteUrl = $baseHref . '?' . http_build_query($deleteParams);
                                    
                                    $returnUrl = $baseHref . '?' . http_build_query($_GET);
                                    ?>
                                    <div class="d-flex gap-1 align-items-center">
                                        <a href="<?php echo $viewUrl; ?>"
                                            class="action-btn action-btn--ver" title="Visualizar" data-bs-toggle="tooltip"><i
                                                class="bi bi-eye"></i></a>
                                        <a href="<?php echo $editUrl; ?>"
                                            class="action-btn action-btn--editar" title="Editar" data-bs-toggle="tooltip"><i
                                                class="bi bi-pencil"></i></a>
                                        <div class="dropdown">
                                            <a href="javascript:void(0)" class="action-btn action-btn--mais"
                                                data-bs-toggle="dropdown" data-bs-strategy="fixed" aria-expanded="false"
                                                title="Mais ações"><i class="bi bi-three-dots-vertical"></i></a>
                                            <ul class="dropdown-menu dropdown-menu-end action-kebab-menu">
                                                <li><a class="dropdown-item"
                                                        href="<?php echo $deleteUrl; ?>&return=<?php echo urlencode($returnUrl); ?>"
                                                        onclick="return confirm('Tem certeza que deseja excluir este registro?')"><i
                                                            class="bi bi-trash text-danger"></i> Excluir</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                                <?php
                                $vCols = 0;
                                foreach ($columnsToDisplay as $col => $info):
                                    if ($vCols >= $maxCols)
                                        break;
                                    $vCols++;

                                    if (isset($row[$col . '_label'])) {
                                        $val = $row[$col . '_label'];
                                    } else {
                                        $val = $row[$col];
                                        if ($info['type'] === 'date' || strpos($col, 'data') !== false) {
                                            $val = $formatPtBrDate((string) $val);
                                        }
                                    }

                                    if ($info['type'] === 'textarea') {
                                        $val = strip_tags((string) $val);
                                        if (strlen($val) > 50) {
                                            $val = substr($val, 0, 47) . '...';
                                        }
                                    }
                                    ?>
                                    <td><?php echo htmlspecialchars((string) $val); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if (isset($_BULK_ACTIONS)): ?>
                <!-- Floating Bulk Toolbar -->
                <div id="bulkToolbar" class="bg-dark text-white p-3 rounded shadow position-fixed bottom-0 start-50 translate-middle-x mb-4 d-none" style="z-index: 1050; min-width: 300px;">
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="me-3"><span id="selectedCount">0</span> itens selecionados</span>
                        <div class="btn-group btn-group-sm">
                            <button type="submit" name="action" value="delete" class="btn btn-danger" onclick="return confirm('Excluir todos os itens selecionados?')">
                                <i class="bi bi-trash me-1"></i> Excluir
                            </button>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bulkUpdateModal">
                                <i class="bi bi-pencil-square me-1"></i> Atualizar
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>
<?php if ($totalPages > 1): ?>
    <div class="card-footer bg-white">
        <?php echo Pagination::render($page, $totalPages, $baseHref, $queryParams); ?>
    </div>
<?php endif; ?>
</div>

<?php if (isset($_BULK_ACTIONS)): ?>
<!-- Modal de Atualização em Massa -->
<div class="modal fade" id="bulkUpdateModal" tabindex="-1">
<div class="modal-dialog">
    <form action="index.php?m=<?php echo $tableName; ?>&a=bulk" method="post">
        <!-- Os IDs selecionados serão copiados para aqui via JS -->
        <div id="bulkUpdateIds"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Atualizar Selecionados</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Campo para atualizar</label>
                    <select name="field" class="form-select" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($schema as $col => $info): 
                            if ($col === $primaryKey || (isset($info['type']) && $info['type'] === 'virtual')) continue;
                            ?>
                            <option value="<?php echo $col; ?>"><?php echo htmlspecialchars(ucfirst($col)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Novo Valor</label>
                    <input type="text" name="value" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" name="action" value="update" class="btn btn-primary">Salvar Alterações</button>
            </div>
        </div>
    </form>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectAll = document.getElementById('selectAll');
    const rowCheckboxes = document.querySelectorAll('.row-checkbox');
    const bulkToolbar = document.getElementById('bulkToolbar');
    const selectedCount = document.getElementById('selectedCount');
    const bulkUpdateIds = document.getElementById('bulkUpdateIds');

    function updateToolbar() {
        const checked = document.querySelectorAll('.row-checkbox:checked');
        selectedCount.textContent = checked.length;
        
        if (checked.length > 0) {
            bulkToolbar.classList.remove('d-none');
        } else {
            bulkToolbar.classList.add('d-none');
        }

        // Atualiza os IDs no modal de update
        if (bulkUpdateIds) {
            bulkUpdateIds.innerHTML = '';
            checked.forEach(cb => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = cb.value;
                bulkUpdateIds.appendChild(input);
            });
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            rowCheckboxes.forEach(cb => cb.checked = selectAll.checked);
            updateToolbar();
        });
    }

    rowCheckboxes.forEach(cb => {
        cb.addEventListener('change', updateToolbar);
    });

    // Tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })
});
</script>
<?php else: ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })
});
</script>
<?php endif; ?>