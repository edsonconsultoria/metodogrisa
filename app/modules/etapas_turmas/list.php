<?php
// app/modules/etapas_turmas/list.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';
require_once __DIR__ . '/../../helpers/pagination.php';
require_once __DIR__ . '/../../helpers/bulk_actions.php';
require_once __DIR__ . '/../../helpers/list_state.php';

$tableName = '104_etapas_turmas';
$engine = new CrudEngine($pdo, $tableName);

$engine->setLookups([
    'etapa' => ['table' => '104_etapas', 'label' => 'nome', 'extra_fields' => ['dia_1', 'dia_2']],
    'turma' => ['table' => '104_turmas', 'label' => 'nome']
]);

$hiddenCols = ['link_zoom_dia_1', 'link_zoom_dia_2', 'link_ead'];

$page = (int) ($_GET['page'] ?? 1);
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? '';
$dir = strtoupper($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
$turmaFilter = (int) ($_GET['turma'] ?? 0);

// Busca turmas para o filtro
$allTurmas = $pdo->query("SELECT id, nome FROM 104_turmas ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

$filters = [];
if ($turmaFilter > 0) {
    $filters['turma'] = $turmaFilter;
}

$orderBy = $sort !== '' ? "$sort $dir" : "";

// Mapeamento de ordenação para campos de lookups (JOINs)
$joins = [
    'e' => ['table' => '104_etapas', 'on' => 't.etapa = e.id']
];

if ($sort === 'etapa_dia_1')
    $orderBy = "e.dia_1 $dir";
if ($sort === 'etapa_dia_2')
    $orderBy = "e.dia_2 $dir";

$result = $engine->getList($page, 20, $search, $filters, $orderBy, $joins);
$data = $result['data'];
$totalPages = $result['totalPages'];
$schema = $engine->getSchema();
$primaryKey = $engine->getPrimaryKey();

$baseHref = BASE_URL . 'index.php';

$formatPtBrDate = function ($date) {
    if (!$date || $date === '0000-00-00')
        return '—';
    return date('d/m/Y', strtotime($date));
};
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <?php echo get_table_label($tableName); ?>
    </h4>
    <div class="d-flex gap-2 align-items-center">
        <form method="get" class="d-flex gap-1 align-items-center">
            <input type="hidden" name="m" value="etapas_turmas">
            <input type="hidden" name="a" value="list">
            <select name="turma" class="form-select form-select-sm" style="width: 180px;" onchange="this.form.submit()">
                <option value="">Todas as Turmas</option>
                <?php foreach ($allTurmas as $t): ?>
                    <option value="<?php echo $t['id']; ?>" <?php echo $turmaFilter === (int) $t['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($t['nome']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Buscar..."
                value="<?php echo htmlspecialchars($search); ?>" style="width: 200px;">
            <button type="submit" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-search"></i>
            </button>
            <?php if ($search): ?>
                <a href="<?php echo $baseHref; ?>?m=etapas_turmas&a=list" class="btn btn-outline-danger btn-sm"
                    title="Limpar busca">
                    <i class="bi bi-x-lg"></i>
                </a>
            <?php endif; ?>
        </form>
        <?php render_bulk_buttons(); ?>
        <a href="<?php echo $baseHref; ?>?m=etapas_turmas&a=form" class="btn btn-primary btn-sm">
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
                        <th style="width: 100px;">Ações</th>
                        <?php
                        $vCols = 0;
                        foreach ($schema as $col => $info):
                            if (in_array($col, $hiddenCols))
                                continue;
                            if ($vCols > 6)
                                break;
                            $vCols++;
                            $newDir = ($sort === $col && $dir === 'ASC') ? 'DESC' : 'ASC';
                            $sortUrl = $baseHref . '?' . http_build_query(array_merge($_GET, ['sort' => $col, 'dir' => $newDir, 'page' => 1]));
                            ?>
                            <th>
                                <a href="<?php echo $sortUrl; ?>"
                                    class="text-decoration-none text-dark d-flex align-items-center gap-1">
                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $col))); ?>
                                    <?php if ($sort === $col): ?>
                                        <i
                                            class="bi bi-sort-<?php echo strtolower($dir) === 'asc' ? 'up' : 'down'; ?> text-primary"></i>
                                    <?php else: ?>
                                        <i class="bi bi-hash text-muted small opacity-50"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                        <?php endforeach; ?>
                        <th>
                            <a href="<?php echo $baseHref . '?' . http_build_query(array_merge($_GET, ['sort' => 'etapa_dia_1', 'dir' => ($sort === 'etapa_dia_1' && $dir === 'ASC' ? 'DESC' : 'ASC'), 'page' => 1])); ?>"
                                class="text-decoration-none text-dark d-flex align-items-center gap-1">
                                Dia 1
                                <?php if ($sort === 'etapa_dia_1'): ?><i
                                        class="bi bi-sort-<?php echo strtolower($dir) === 'asc' ? 'up' : 'down'; ?> text-primary"></i><?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?php echo $baseHref . '?' . http_build_query(array_merge($_GET, ['sort' => 'etapa_dia_2', 'dir' => ($sort === 'etapa_dia_2' && $dir === 'ASC' ? 'DESC' : 'ASC'), 'page' => 1])); ?>"
                                class="text-decoration-none text-dark d-flex align-items-center gap-1">
                                Dia 2
                                <?php if ($sort === 'etapa_dia_2'): ?><i
                                        class="bi bi-sort-<?php echo strtolower($dir) === 'asc' ? 'up' : 'down'; ?> text-primary"></i><?php endif; ?>
                            </a>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data)): ?>
                        <tr>
                            <td colspan="<?php echo min(count($schema) - count($hiddenCols) + 3, 10); ?>"
                                class="text-center py-4 text-muted">
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
                                <td class="text-start">
                                    <div class="d-flex gap-1 align-items-center">
                                        <a href="<?php echo list_state_action_url('etapas_turmas', 'view', ['id' => $row[$primaryKey]]); ?>"
                                            class="action-btn action-btn--ver" title="Visualizar" data-bs-toggle="tooltip"><i
                                                class="bi bi-eye"></i></a>

                                        <?php $canEdit = true;
                                        if ($canEdit): ?>
                                            <a href="<?php echo list_state_action_url('etapas_turmas', 'form', ['id' => $row[$primaryKey]]); ?>"
                                                class="action-btn action-btn--editar" title="Editar" data-bs-toggle="tooltip"><i
                                                    class="bi bi-pencil"></i></a>

                                            <div class="dropend">
                                                <a href="javascript:void(0)" class="action-btn action-btn--mais"
                                                    data-bs-toggle="dropdown" aria-expanded="false"
                                                    title="Mais ações"><i class="bi bi-three-dots-vertical"></i></a>
                                                <ul class="dropdown-menu action-kebab-menu">
                                                    <li><a class="dropdown-item"
                                                            href="<?php echo list_state_action_url('etapas_turmas', 'copy', ['id' => $row[$primaryKey]]); ?>"><i
                                                                class="bi bi-files text-secondary"></i> Copiar e criar
                                                            nova etapa</a></li>
                                                    <li><a class="dropdown-item"
                                                            href="<?php echo list_state_action_url('etapas_turmas', 'delete', ['id' => $row[$primaryKey]]); ?>"
                                                            onclick="return confirm('Tem certeza que deseja excluir esta etapa da turma?')"><i
                                                                class="bi bi-trash text-danger"></i> Excluir</a></li>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <?php
                                $vCols = 0;
                                foreach ($schema as $col => $info):
                                    if (in_array($col, $hiddenCols))
                                        continue;
                                    if ($vCols > 6)
                                        break;
                                    $vCols++;

                                    if (isset($row[$col . '_label'])) {
                                        $val = $row[$col . '_label'];
                                    } else {
                                        $val = $row[$col];
                                        if ($info['type'] === 'date') {
                                            $val = $formatPtBrDate((string) $val);
                                        }
                                    }

                                    if (strlen((string) $val) > 50) {
                                        $val = substr((string) $val, 0, 47) . '...';
                                    }
                                    ?>
                                    <td>
                                        <?php echo htmlspecialchars((string) $val); ?>
                                    </td>
                                <?php endforeach; ?>
                                <td><?php echo $formatPtBrDate($row['etapa_dia_1'] ?? null); ?></td>
                                <td><?php echo $formatPtBrDate($row['etapa_dia_2'] ?? null); ?></td>
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
        <?php echo Pagination::render($page, $totalPages, $baseHref, ['m' => 'etapas_turmas', 'a' => 'list', 'search' => $search, 'sort' => $sort, 'dir' => $dir, 'turma' => $turmaFilter]); ?>
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
render_bulk_modal('etapas_turmas', $tableName, $schema, $engine->getLookupsOptions());

// Render JavaScript for handling bulk actions
render_bulk_js('etapas_turmas', $tableName);
?>