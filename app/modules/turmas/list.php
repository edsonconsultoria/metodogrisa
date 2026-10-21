<?php
// prevenção a erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// app/modules/turmas/list.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';
require_once __DIR__ . '/../../helpers/pagination.php';
require_once __DIR__ . '/../../helpers/bulk_actions.php';

$tableName = '104_turmas';
$engine = new CrudEngine($pdo, $tableName);

$engine->setLookups([
    'faculdade' => ['table' => '104_faculdades', 'label' => 'nome']
]);

$page = (int) ($_GET['page'] ?? 1);
$search = $_GET['search'] ?? '';

$result = $engine->getList($page, 20, $search, [], 'inicio DESC');
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
            <input type="hidden" name="m" value="turmas">
            <input type="hidden" name="a" value="list">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Buscar..."
                value="<?php echo htmlspecialchars($search); ?>" style="width: 200px;">
            <button type="submit" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-search"></i>
            </button>
            <?php if ($search): ?>
                <a href="<?php echo $baseHref; ?>?m=turmas&a=list" class="btn btn-outline-danger btn-sm"
                    title="Limpar busca">
                    <i class="bi bi-x-lg"></i>
                </a>
            <?php endif; ?>
        </form>
        <?php render_bulk_buttons(); ?>
        <a href="<?php echo $baseHref; ?>?m=turmas&a=form" class="btn btn-primary btn-sm">
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
                        <th style="width: 130px;">Ações</th>
                        <th>Alunos</th>
                        <th>Disciplinas</th>
                        <?php
                        $vCols = 0;
                        foreach ($schema as $col => $info):
                            if ($vCols > 6)
                                break;
                            $vCols++;
                            ?>
                            <th>
                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $col))); ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data)): ?>
                        <tr>
                            <td colspan="<?php echo min(count($schema) + 3, 10); ?>" class="text-center py-4 text-muted">
                                Nenhum registro encontrado.
                            </td>
                        </tr>
                    <?php else:
                        // Fetch counts for the current page
                        $ids = array_column($data, $primaryKey);
                        $alunoCounts = [];
                        $disciplinaCounts = [];
                        if (!empty($ids)) {
                            $placeholders = implode(',', array_fill(0, count($ids), '?'));

                            // Alunos Counts
                            $stmtAlunos = $pdo->prepare("SELECT turma, COUNT(*) as total FROM 104_alunos WHERE turma IN ($placeholders) GROUP BY turma");
                            $stmtAlunos->execute($ids);
                            $alunoCounts = $stmtAlunos->fetchAll(PDO::FETCH_KEY_PAIR);

                            // Disciplinas Counts
                            $stmtDisciplinas = $pdo->prepare("SELECT turma, COUNT(*) as total FROM 104_turmas_disciplinas WHERE turma IN ($placeholders) GROUP BY turma");
                            $stmtDisciplinas->execute($ids);
                            $disciplinaCounts = $stmtDisciplinas->fetchAll(PDO::FETCH_KEY_PAIR);
                        }
                        ?>
                        <?php foreach ($data as $row):
                            $alunoCount = $alunoCounts[$row[$primaryKey]] ?? 0;
                            $disciplinaCount = $disciplinaCounts[$row[$primaryKey]] ?? 0;
                            ?>
                            <tr>
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input bulk-select-item"
                                        value="<?php echo htmlspecialchars($row[$primaryKey]); ?>">
                                </td>
                                <td class="text-start">
                                    <div class="d-flex gap-1 align-items-center">
                                        <a href="<?php echo $baseHref; ?>?m=turmas&a=view&id=<?php echo $row[$primaryKey]; ?>"
                                            class="action-btn action-btn--ver" title="Visualizar" data-bs-toggle="tooltip"><i
                                                class="bi bi-eye"></i></a>
                                        <a href="<?php echo $baseHref; ?>?m=turmas&a=form&id=<?php echo $row[$primaryKey]; ?>"
                                            class="action-btn action-btn--editar" title="Editar" data-bs-toggle="tooltip"><i
                                                class="bi bi-pencil"></i></a>
                                        <div class="dropend">
                                            <a href="javascript:void(0)" class="action-btn action-btn--mais"
                                                data-bs-toggle="dropdown" aria-expanded="false"
                                                title="Mais ações"><i class="bi bi-three-dots-vertical"></i></a>
                                            <ul class="dropdown-menu action-kebab-menu">
                                                <?php if (!empty($row['id_moodle'])): ?>
                                                    <li><button type="button" class="dropdown-item"
                                                            onclick="copyEadLink(<?php echo (int) $row['id_moodle']; ?>, this)"><i
                                                                class="bi bi-clipboard text-secondary"></i> Copiar link
                                                            EAD</button></li>
                                                <?php endif; ?>
                                                <li><a class="dropdown-item"
                                                        href="<?php echo $baseHref; ?>?m=turmas&a=delete&id=<?php echo $row[$primaryKey]; ?>"
                                                        onclick="return confirm('Tem certeza que deseja excluir este registro?')"><i
                                                            class="bi bi-trash text-danger"></i> Excluir</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary rounded-pill"><?php echo $alunoCount; ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-info rounded-pill"><?php echo $disciplinaCount; ?></span>
                                </td>
                                <?php
                                $vCols = 0;
                                foreach ($schema as $col => $info):
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
        <?php echo Pagination::render($page, $totalPages, $baseHref, ['m' => 'turmas', 'a' => 'list', 'search' => $search]); ?>
    </div>
<?php endif; ?>

<!-- Toast feedback for copy -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 9999">
    <div id="copyToast" class="toast align-items-center text-white bg-success border-0" role="alert"
        aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body">
                <i class="bi bi-check-circle me-1"></i> Link EAD copiado!
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                aria-label="Close"></button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    });

    function copyEadLink(moodleId, btn) {
        var url = 'https://eadmetodogrisa.com.br/course/view.php?id=' + moodleId;
        navigator.clipboard.writeText(url).then(function () {
            // Show toast
            var toastEl = document.getElementById('copyToast');
            var toast = new bootstrap.Toast(toastEl, { delay: 2000 });
            toast.show();
            // Briefly change icon
            var icon = btn.querySelector('i');
            icon.className = 'bi bi-clipboard-check';
            setTimeout(function () { icon.className = 'bi bi-clipboard'; }, 2000);
        }).catch(function () {
            // Fallback for older browsers
            var el = document.createElement('textarea');
            el.value = url;
            document.body.appendChild(el);
            el.select();
            document.execCommand('copy');
            document.body.removeChild(el);
            alert('Link copiado: ' + url);
        });
    }
</script>

<?php
// Render dynamic modal for bulk update
render_bulk_modal('turmas', $tableName, $schema, $engine->getLookupsOptions());

// Render JavaScript for handling bulk actions
render_bulk_js('turmas');
?>