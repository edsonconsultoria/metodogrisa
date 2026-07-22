<?php
// app/modules/matriculas/list.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';
require_once __DIR__ . '/../../helpers/pagination.php';
require_once __DIR__ . '/../../helpers/bulk_actions.php';

$tableName = '104_matriculas';
$engine = new CrudEngine($pdo, $tableName);

$page = (int) ($_GET['page'] ?? 1);
$search = $_GET['search'] ?? '';

$result = $engine->getList($page, 20, $search);
$data = $result['data'];
$totalPages = $result['totalPages'];
$schema = $engine->getSchema();
$primaryKey = $engine->getPrimaryKey();

// Resolve names
if (!empty($data)) {
    // Resolve Turma
    $turmaIds = array_unique(array_filter(array_column($data, 'turma')));
    if (!empty($turmaIds)) {
        $placeholders = implode(',', array_fill(0, count($turmaIds), '?'));
        $stmtT = $pdo->prepare("SELECT id, nome FROM 104_turmas WHERE id IN ($placeholders)");
        $stmtT->execute(array_values($turmaIds));
        $turmas = $stmtT->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    // Resolve Aluno (104_alunos.id -> 101_pessoas.nome)
    $alunoIds = array_unique(array_filter(array_column($data, 'aluno')));
    if (!empty($alunoIds)) {
        $placeholders = implode(',', array_fill(0, count($alunoIds), '?'));
        $sqlA = "SELECT a.id, p.nome 
                 FROM 104_alunos a 
                 JOIN 101_pessoas p ON a.pessoa = p.id 
                 WHERE a.id IN ($placeholders)";
        $stmtA = $pdo->prepare($sqlA);
        $stmtA->execute(array_values($alunoIds));
        $alunos = $stmtA->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    foreach ($data as &$row) {
        $row['turma_label'] = $turmas[$row['turma']] ?? $row['turma'];
        $row['aluno_label'] = $alunos[$row['aluno']] ?? $row['aluno'];
    }
    unset($row);
}

$baseHref = BASE_URL . 'index.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <?php echo get_table_label($tableName); ?>
    </h4>
    <div class="d-flex gap-2 align-items-center">
        <form method="get" class="d-flex gap-1 align-items-center">
            <input type="hidden" name="m" value="matriculas">
            <input type="hidden" name="a" value="list">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Buscar..."
                value="<?php echo htmlspecialchars($search); ?>" style="width: 200px;">
            <button type="submit" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-search"></i>
            </button>
            <?php if ($search): ?>
                <a href="<?php echo $baseHref; ?>?m=matriculas&a=list" class="btn btn-outline-danger btn-sm"
                    title="Limpar busca">
                    <i class="bi bi-x-lg"></i>
                </a>
            <?php endif; ?>
        </form>
        <?php render_bulk_buttons(); ?>
        <a href="<?php echo $baseHref; ?>?m=matriculas&a=form" class="btn btn-primary btn-sm">
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
                        foreach ($schema as $col => $info):
                            if ($col === 'observacao')
                                continue;
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
                            <td colspan="<?php echo count($schema); ?>" class="text-center py-4 text-muted">
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
                                        <a href="<?php echo $baseHref; ?>?m=matriculas&a=view&id=<?php echo $row[$primaryKey]; ?>"
                                            class="action-btn action-btn--ver" title="Visualizar" data-bs-toggle="tooltip"><i
                                                class="bi bi-eye"></i></a>
                                        <a href="<?php echo $baseHref; ?>?m=matriculas&a=form&id=<?php echo $row[$primaryKey]; ?>"
                                            class="action-btn action-btn--editar" title="Editar" data-bs-toggle="tooltip"><i
                                                class="bi bi-pencil"></i></a>
                                        <div class="dropdown">
                                            <a href="javascript:void(0)" class="action-btn action-btn--mais"
                                                data-bs-toggle="dropdown" data-bs-strategy="fixed" aria-expanded="false"
                                                title="Mais ações"><i class="bi bi-three-dots-vertical"></i></a>
                                            <ul class="dropdown-menu dropdown-menu-end action-kebab-menu">
                                                <li><a class="dropdown-item"
                                                        href="<?php echo $baseHref; ?>?m=matriculas&a=delete&id=<?php echo $row[$primaryKey]; ?>"
                                                        onclick="return confirm('Tem certeza que deseja excluir este registro?')"><i
                                                            class="bi bi-trash text-danger"></i> Excluir</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                                <?php
                                foreach ($schema as $col => $info):
                                    if ($col === 'observacao')
                                        continue;
                                    $val = isset($row[$col . '_label']) ? $row[$col . '_label'] : $row[$col];
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
        <?php echo Pagination::render($page, $totalPages, $baseHref, ['m' => 'matriculas', 'a' => 'list', 'search' => $search]); ?>
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
render_bulk_modal('matriculas', $tableName, $schema, $engine->getLookupsOptions());

// Render JavaScript for handling bulk actions
render_bulk_js('matriculas');
?>