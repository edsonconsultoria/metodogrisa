<?php
// app/modules/turmas_disciplinas/list.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';
require_once __DIR__ . '/../../helpers/pagination.php';
require_once __DIR__ . '/../../helpers/bulk_actions.php';

$tableName = '104_turmas_disciplinas';
$engine = new CrudEngine($pdo, $tableName);

$engine->setLookups([
    'turma' => ['table' => '104_turmas', 'label' => 'nome'],
    'disciplina' => ['table' => '104_disciplinas', 'label' => 'nome'],
    'professor' => ['table' => '104_professores', 'label' => 'apelido', 'extra_fields' => ['titulacao']]
]);

$page = (int) ($_GET['page'] ?? 1);
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'id';
$dir = strtoupper($_GET['dir'] ?? 'DESC') === 'DESC' ? 'DESC' : 'ASC';
$turma_filter = $_GET['turma'] ?? '';
$professor_filter = $_GET['professor'] ?? '';

$filters = [];
if ($turma_filter !== '') {
    $filters['turma'] = $turma_filter;
}

$joins = [
    'tur' => ['table' => '104_turmas', 'on' => 't.turma = tur.id'],
    'dis' => ['table' => '104_disciplinas', 'on' => 't.disciplina = dis.id'],
    'pro' => ['table' => '104_professores', 'on' => 't.professor = pro.id'],
    'pes' => ['table' => '101_pessoas', 'on' => 'pro.pessoa = pes.id'],
    'pro_dis' => ['table' => '104_professores', 'on' => 'dis.professor = pro_dis.id'],
    'pes_dis' => ['table' => '101_pessoas', 'on' => 'pro_dis.pessoa = pes_dis.id']
];

$custom_wheres = [];

if ($search !== '') {
    $searchEscaped = $pdo->quote("%$search%");
    $custom_wheres[] = "(
        tur.nome LIKE $searchEscaped OR 
        dis.nome LIKE $searchEscaped OR 
        pro.apelido LIKE $searchEscaped OR
        pes.nome LIKE $searchEscaped OR
        pro_dis.apelido LIKE $searchEscaped OR
        pes_dis.nome LIKE $searchEscaped OR
        t.carga_horaria LIKE $searchEscaped
    )";
}

if ($professor_filter !== '') {
    $prof_id = (int) $professor_filter;
    $custom_wheres[] = "(t.professor = $prof_id OR dis.professor = $prof_id)";
}

if (!empty($custom_wheres)) {
    $filters['_custom_where'] = implode(' AND ', $custom_wheres);
}

$engineSearch = ''; // Usamos apenas a busca customizada se houver algo
$orderBy = "$sort $dir";
$result = $engine->getList($page, 20, $engineSearch, $filters, $orderBy, $joins);
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

// Fetch turmas for filter
$stmtTurmas = $pdo->query("SELECT id, nome FROM 104_turmas ORDER BY nome DESC");
$turmasList = $stmtTurmas->fetchAll(PDO::FETCH_ASSOC);

// Fetch professores for filter
$stmtProfessores = $pdo->query("SELECT id, apelido FROM 104_professores ORDER BY apelido ASC");
$professoresList = $stmtProfessores->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <?php echo get_table_label($tableName); ?>
    </h4>
    <div class="d-flex gap-2 align-items-center">
        <form method="get" class="d-flex gap-1 align-items-center">
            <input type="hidden" name="m" value="turmas_disciplinas">
            <input type="hidden" name="a" value="list">

            <select name="turma" class="form-select form-select-sm" style="width: 150px;" onchange="this.form.submit()">
                <option value="">Todas as Turmas</option>
                <?php foreach ($turmasList as $t): ?>
                    <option value="<?php echo $t['id']; ?>" <?php echo $turma_filter == $t['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($t['nome']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

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
            <?php if ($search || $turma_filter || $professor_filter): ?>
                <a href="<?php echo $baseHref; ?>?m=turmas_disciplinas&a=list" class="btn btn-outline-danger btn-sm"
                    title="Limpar busca">
                    <i class="bi bi-x-lg"></i>
                </a>
            <?php endif; ?>
        </form>
        <?php render_bulk_buttons(); ?>
        <a href="<?php echo list_state_action_url('turmas_disciplinas', 'form'); ?>" class="btn btn-primary btn-sm">
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
                            <a href="<?php echo $baseHref; ?>?m=turmas_disciplinas&a=list&sort=turma&dir=<?php echo ($sort == 'turma' && $dir == 'ASC') ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>&turma=<?php echo $turma_filter; ?>&professor=<?php echo $professor_filter; ?>"
                                class="text-decoration-none text-dark">
                                Turma <?php if ($sort == 'turma')
                                    echo $dir == 'ASC' ? '↑' : '↓'; ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?php echo $baseHref; ?>?m=turmas_disciplinas&a=list&sort=disciplina&dir=<?php echo ($sort == 'disciplina' && $dir == 'ASC') ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>&turma=<?php echo $turma_filter; ?>&professor=<?php echo $professor_filter; ?>"
                                class="text-decoration-none text-dark">
                                Disciplina <?php if ($sort == 'disciplina')
                                    echo $dir == 'ASC' ? '↑' : '↓'; ?>
                            </a>
                        </th>
                        <th style="width: 120px;">Carga Horária</th>
                        <th>
                            <a href="<?php echo $baseHref; ?>?m=turmas_disciplinas&a=list&sort=professor&dir=<?php echo ($sort == 'professor' && $dir == 'ASC') ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>&turma=<?php echo $turma_filter; ?>&professor=<?php echo $professor_filter; ?>"
                                class="text-decoration-none text-dark">
                                Professor <?php if ($sort == 'professor')
                                    echo $dir == 'ASC' ? '↑' : '↓'; ?>
                            </a>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
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
                                        <a href="<?php echo list_state_action_url('turmas_disciplinas', 'view', ['id' => $row[$primaryKey]]); ?>"
                                            class="action-btn action-btn--ver" title="Visualizar" data-bs-toggle="tooltip"><i
                                                class="bi bi-eye"></i></a>
                                        <a href="<?php echo list_state_action_url('turmas_disciplinas', 'form', ['id' => $row[$primaryKey]]); ?>"
                                            class="action-btn action-btn--editar" title="Editar" data-bs-toggle="tooltip"><i
                                                class="bi bi-pencil"></i></a>
                                        <div class="dropend">
                                            <a href="javascript:void(0)" class="action-btn action-btn--mais"
                                                data-bs-toggle="dropdown" aria-expanded="false"
                                                title="Mais ações"><i class="bi bi-three-dots-vertical"></i></a>
                                            <ul class="dropdown-menu action-kebab-menu">
                                                <li><a class="dropdown-item"
                                                        href="<?php echo list_state_action_url('turmas_disciplinas', 'delete', ['id' => $row[$primaryKey]]); ?>"
                                                        onclick="return confirm('Tem certeza que deseja excluir este registro?')"><i
                                                            class="bi bi-trash text-danger"></i> Excluir</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo $row[$primaryKey]; ?></td>
                                <td><?php echo htmlspecialchars($row['turma_label'] ?? $row['turma']); ?></td>
                                <td><?php echo htmlspecialchars($row['disciplina_label'] ?? $row['disciplina']); ?></td>
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
            'm' => 'turmas_disciplinas',
            'a' => 'list',
            'search' => $search,
            'turma' => $turma_filter,
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
render_bulk_modal('turmas_disciplinas', $tableName, $schema, $engine->getLookupsOptions());

// Render JavaScript for handling bulk actions
render_bulk_js('turmas_disciplinas');
?>