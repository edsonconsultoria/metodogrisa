<?php
// app/modules/disciplinas/list.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';
require_once __DIR__ . '/../../helpers/pagination.php';
require_once __DIR__ . '/../../helpers/bulk_actions.php';

$tableName = '104_disciplinas';
$engine = new CrudEngine($pdo, $tableName);

$engine->setLookups([
    'professor' => ['table' => '104_professores', 'label' => 'apelido', 'extra_fields' => ['titulacao']]
]);

$page = (int) ($_GET['page'] ?? 1);
$search = $_GET['search'] ?? '';
$professor_filter = $_GET['professor'] ?? '';

$joins = [
    'pro' => ['table' => '104_professores', 'on' => 't.professor = pro.id'],
    'pes' => ['table' => '101_pessoas', 'on' => 'pro.pessoa = pes.id']
];

$filters = [];
if ($professor_filter !== '') {
    $filters['professor'] = $professor_filter;
}

$engineSearch = $search;
if ($search !== '') {
    $searchEscaped = $pdo->quote("%$search%");
    $filters['_custom_where'] = "(
        t.nome LIKE $searchEscaped OR 
        pro.apelido LIKE $searchEscaped OR 
        pes.nome LIKE $searchEscaped OR
        t.carga_horaria LIKE $searchEscaped
    )";
    $engineSearch = '';
}

// Sorting logic
$allowedSort = [
    'id' => 'id',
    'nome' => 'nome',
    'carga_horaria' => 'carga_horaria',
    'professor' => 'professor'
];

$sort = $_GET['sort'] ?? 'id';
$dir = strtolower($_GET['dir'] ?? 'asc') === 'asc' ? 'asc' : 'desc';
$orderField = $allowedSort[$sort] ?? 'id';
$orderDirection = strtoupper($dir);
$orderBy = "`$orderField` $orderDirection";

$result = $engine->getList($page, 20, $engineSearch, $filters, $orderBy, $joins);
$data = $result['data'];
$totalPages = $result['totalPages'];
$schema = $engine->getSchema();
$primaryKey = $engine->getPrimaryKey();

$baseHref = BASE_URL . 'index.php';

$buildSortUrl = function (string $field) use ($baseHref, $search, $sort, $dir) {
    $newDir = ($sort === $field && $dir === 'asc') ? 'desc' : 'asc';
    $params = [
        'm' => 'disciplinas',
        'a' => 'list',
        'sort' => $field,
        'dir' => $newDir,
    ];
    if ($search !== '') {
        $params['search'] = $search;
    }
    return $baseHref . '?' . http_build_query($params);
};

$renderSortIcon = function (string $field) use ($sort, $dir) {
    if ($sort !== $field)
        return '';
    return $dir === 'asc' ? ' ▲' : ' ▼';
};

$formatPtBrDate = function ($date) {
    if (!$date || $date === '0000-00-00')
        return '—';
    return date('d/m/Y', strtotime($date));
};

// Fetch professores for filter
$stmtProfessores = $pdo->query("SELECT id, apelido FROM 104_professores ORDER BY apelido ASC");
$professoresList = $stmtProfessores->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    .sortable-link {
        text-decoration: none;
        color: inherit;
        font-weight: bold;
    }

    .sortable-link:hover {
        text-decoration: underline;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <?php echo get_table_label($tableName); ?>
    </h4>
    <div class="d-flex gap-2 align-items-center">
        <form method="get" class="d-flex gap-1 align-items-center">
            <input type="hidden" name="m" value="disciplinas">
            <input type="hidden" name="a" value="list">

            <select name="professor" class="form-select form-select-sm" style="width: 150px;"
                onchange="this.form.submit()">
                <option value="">Todos os Professores</option>
                <?php foreach ($professoresList as $p): ?>
                    <option value="<?php echo $p['id']; ?>" <?php echo $professor_filter == $p['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($p['apelido']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="text" name="search" class="form-control form-control-sm" placeholder="Buscar..."
                value="<?php echo htmlspecialchars($search); ?>" style="width: 200px;">
            <button type="submit" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-search"></i>
            </button>
            <?php if ($search || $professor_filter): ?>
                <a href="<?php echo $baseHref; ?>?m=disciplinas&a=list" class="btn btn-outline-danger btn-sm"
                    title="Limpar busca">
                    <i class="bi bi-x-lg"></i>
                </a>
            <?php endif; ?>
        </form>
        <?php render_bulk_buttons(); ?>
        <a href="<?php echo $baseHref; ?>?m=disciplinas&a=form" class="btn btn-primary btn-sm">
            + Novo(a)
            <?php echo get_table_label($tableName); ?>
        </a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 40px;" class="text-center">
                            <input type="checkbox" class="form-check-input" id="bulk-select-all">
                        </th>
                        <th style="width: 100px;" class="text-center">Ações</th>
                        <th style="width: 60px;">ID</th>
                        <th>
                            <a href="<?php echo $buildSortUrl('nome'); ?>" class="sortable-link">
                                Nome <?php echo $renderSortIcon('nome'); ?>
                            </a>
                        </th>
                        <th style="width: 150px;">
                            <a href="<?php echo $buildSortUrl('carga_horaria'); ?>" class="sortable-link">
                                Carga Horária <?php echo $renderSortIcon('carga_horaria'); ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?php echo $buildSortUrl('professor'); ?>" class="sortable-link">
                                Professor <?php echo $renderSortIcon('professor'); ?>
                            </a>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                Nenhum registro encontrado.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($data as $row): ?>
                            <tr>
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input bulk-select-item"
                                        value="<?php echo htmlspecialchars($row[$primaryKey]); ?>">
                                </td>
                                <td class="text-center">
                                    <div class="d-flex gap-1 align-items-center justify-content-center">
                                        <a href="<?php echo $baseHref; ?>?m=disciplinas&a=view&id=<?php echo $row[$primaryKey]; ?>"
                                            class="action-btn action-btn--ver" title="Visualizar" data-bs-toggle="tooltip"><i
                                                class="bi bi-eye"></i></a>
                                        <a href="<?php echo $baseHref; ?>?m=disciplinas&a=form&id=<?php echo $row[$primaryKey]; ?>"
                                            class="action-btn action-btn--editar" title="Editar" data-bs-toggle="tooltip"><i
                                                class="bi bi-pencil"></i></a>
                                        <div class="dropend">
                                            <a href="javascript:void(0)" class="action-btn action-btn--mais"
                                                data-bs-toggle="dropdown" aria-expanded="false"
                                                title="Mais ações"><i class="bi bi-three-dots-vertical"></i></a>
                                            <ul class="dropdown-menu action-kebab-menu">
                                                <li><a class="dropdown-item"
                                                        href="<?php echo $baseHref; ?>?m=disciplinas&a=delete&id=<?php echo $row[$primaryKey]; ?>"
                                                        onclick="return confirm('Tem certeza que deseja excluir este registro?')"><i
                                                            class="bi bi-trash text-danger"></i> Excluir</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo $row[$primaryKey]; ?></td>
                                <td><?php echo htmlspecialchars($row['nome']); ?></td>
                                <td><?php echo htmlspecialchars($row['carga_horaria']); ?> h</td>
                                <td>
                                    <?php echo htmlspecialchars($row['professor_label'] ?? $row['professor']); ?>
                                    <?php if (!empty($row['professor_titulacao'])): ?>
                                        <small
                                            class="text-muted d-block"><?php echo htmlspecialchars($row['professor_titulacao']); ?></small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($totalPages > 1): ?>
    <div class="mt-3">
        <?php echo Pagination::render($page, $totalPages, $baseHref, [
            'm' => 'disciplinas',
            'a' => 'list',
            'search' => $search,
            'professor' => $professor_filter,
            'sort' => $sort,
            'dir' => $dir
        ]); ?>
    </div>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    });
</script>
<?php
// Render dynamic modal for bulk update
render_bulk_modal('disciplinas', $tableName, $schema, $engine->getLookupsOptions());

// Render JavaScript for handling bulk actions
render_bulk_js('disciplinas');
?>