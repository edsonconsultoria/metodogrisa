<?php
// app/modules/etapas_cronogramas/list.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';
require_once __DIR__ . '/../../helpers/pagination.php';
require_once __DIR__ . '/../../helpers/bulk_actions.php';

$tableName = '104_cronograma';
$engine = new CrudEngine($pdo, $tableName);
$current_user = current_user();
$perfil_usuario = $current_user['perfil'] ?? 'OPERACIONAL';
$user_id = $current_user['id'] ?? 0;
$canEdit = current_user_is_at_least_admin() || current_user_is_professor();

$engine->setLookups([
    'etapa' => ['table' => '104_etapas', 'label' => 'nome'],
    'turma' => ['table' => '104_turmas', 'label' => 'nome'],
    'professor_1' => ['table' => '104_professores', 'label' => 'apelido'],
    'professor_2' => ['table' => '104_professores', 'label' => 'apelido'],
    'professor_3' => ['table' => '104_professores', 'label' => 'apelido'],
    'professor_4' => ['table' => '104_professores', 'label' => 'apelido']
]);

$page = (int) ($_GET['page'] ?? 1);
$search = $_GET['search'] ?? '';
$filtroTurma = (int) ($_GET['filtro_turma'] ?? 0);
$filtroEtapa = (int) ($_GET['filtro_etapa'] ?? 0);
$filtroProfessor = (int) ($_GET['filtro_professor'] ?? 0);
$filtroSemVideo = ($_GET['filtro_sem_video'] ?? '') === '1' ? 1 : -1; // -1 = todos, 1 = apenas sem vídeo

// Ordenação
$sortableColumns = [
    'etapa' => 'etapa_label',
    'turma' => 'turma_label',
    'data' => 'data',
    'inicio' => 'inicio',
    'aula' => 'aula',
];
$sortParam = $_GET['sort'] ?? '';
$dirParam = strtoupper($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
$orderBy = isset($sortableColumns[$sortParam])
    ? $sortableColumns[$sortParam] . ' ' . $dirParam . ', data DESC, inicio DESC'
    : 'data DESC, inicio DESC';

// Opções para os selects com filtros de perfil
if (current_user_is_aluno()) {
    $turmasOpts = $pdo->prepare("SELECT id, nome FROM 104_turmas WHERE id IN (SELECT turma FROM 104_alunos WHERE aluno = :aluno_id AND (status = 'Ativo' OR status = '' OR status IS NULL)) ORDER BY nome DESC");
    $turmasOpts->execute([':aluno_id' => $user_id]);
    $turmasOpts = $turmasOpts->fetchAll(PDO::FETCH_ASSOC);

    $etapasOpts = $pdo->prepare("SELECT DISTINCT e.id, e.nome FROM 104_etapas e JOIN 104_cronograma c ON c.etapa = e.id WHERE c.turma IN (SELECT turma FROM 104_alunos WHERE aluno = :aluno_id AND (status = 'Ativo' OR status = '' OR status IS NULL)) ORDER BY e.nome ASC");
    $etapasOpts->execute([':aluno_id' => $user_id]);
    $etapasOpts = $etapasOpts->fetchAll(PDO::FETCH_ASSOC);

    $professoresOpts = $pdo->prepare("SELECT DISTINCT p.id, p.apelido FROM 104_professores p JOIN 104_cronograma c ON (c.professor_1 = p.id OR c.professor_2 = p.id OR c.professor_3 = p.id OR c.professor_4 = p.id) WHERE c.turma IN (SELECT turma FROM 104_alunos WHERE aluno = :aluno_id AND (status = 'Ativo' OR status = '' OR status IS NULL)) ORDER BY p.apelido ASC");
    $professoresOpts->execute([':aluno_id' => $user_id]);
    $professoresOpts = $professoresOpts->fetchAll(PDO::FETCH_ASSOC);
} else {
    $turmasOpts = $pdo->query("SELECT id, nome FROM 104_turmas ORDER BY nome DESC")->fetchAll(PDO::FETCH_ASSOC);
    $etapasOpts = $pdo->query("SELECT id, nome FROM 104_etapas ORDER BY dia_1 DESC, nome ASC")->fetchAll(PDO::FETCH_ASSOC);
    $professoresOpts = $pdo->query("SELECT id, apelido FROM 104_professores ORDER BY apelido ASC")->fetchAll(PDO::FETCH_ASSOC);
}

// Montar filtros para o CrudEngine
$filters = [];
if ($filtroTurma > 0)
    $filters['turma'] = $filtroTurma;
if ($filtroEtapa > 0)
    $filters['etapa'] = $filtroEtapa;

$customWheres = [];
if ($filtroProfessor > 0) {
    $customWheres[] = "(professor_1 = {$filtroProfessor} OR professor_2 = {$filtroProfessor} OR professor_3 = {$filtroProfessor} OR professor_4 = {$filtroProfessor})";
}
if ($filtroSemVideo === 1) {
    // Pendentes de vídeo: sem_video não marcado E link vazio
    $customWheres[] = "(sem_video != '1' OR sem_video IS NULL) AND (link_para_aula = '' OR link_para_aula IS NULL)";
}

if (current_user_is_aluno()) {
    $customWheres[] = "turma IN (SELECT turma FROM 104_alunos WHERE aluno = $user_id AND (status = 'Ativo' OR status = '' OR status IS NULL))";
}

if (!empty($customWheres)) {
    $filters['_custom_where'] = implode(' AND ', $customWheres);
}

$result = $engine->getList($page, 20, $search, $filters, $orderBy);
$data = $result['data'];
$totalPages = $result['totalPages'];
$schema = $engine->getSchema();
$primaryKey = $engine->getPrimaryKey();

$baseHref = BASE_URL . 'index.php';
$returnState = http_build_query([
    'm' => 'etapas_cronogramas',
    'a' => 'list',
    'search' => $search,
    'filtro_turma' => $filtroTurma ?: '',
    'filtro_etapa' => $filtroEtapa ?: '',
    'filtro_professor' => $filtroProfessor ?: '',
    'filtro_sem_video' => $filtroSemVideo === 1 ? '1' : '',
    'sort' => $sortParam,
    'dir' => $dirParam,
    'page' => $page
]);

$formatPtBrDate = function ($date) {
    if (!$date || $date === '0000-00-00')
        return '—';
    return date('d/m/Y', strtotime($date));
};

// Helper to build a sortable th link
function sortTh(string $label, string $col, string $currentSort, string $currentDir, string $baseHref, array $qBase): string
{
    $isActive = $currentSort === $col;
    $nextDir = ($isActive && $currentDir === 'ASC') ? 'DESC' : 'ASC';
    $icon = $isActive ? ($currentDir === 'ASC' ? ' ↑' : ' ↓') : ' ↕';
    $url = $baseHref . '?' . http_build_query(array_merge($qBase, ['sort' => $col, 'dir' => $nextDir, 'page' => 1]));
    return '<th><a href="' . $url . '" class="text-decoration-none text-dark">' . htmlspecialchars($label) . '<small class="text-muted">' . $icon . '</small></a></th>';
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <?php echo get_table_label($tableName); ?>
    </h4>
    <div class="d-flex gap-2 align-items-center">
        <form method="get" class="d-flex gap-1 align-items-center flex-wrap">
            <input type="hidden" name="m" value="etapas_cronogramas">
            <input type="hidden" name="a" value="list">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Buscar..."
                value="<?php echo htmlspecialchars($search); ?>" style="width: 160px;">
            <select name="filtro_turma" class="form-select form-select-sm" style="width: 160px;">
                <option value="0">Todas as turmas</option>
                <?php foreach ($turmasOpts as $t): ?>
                    <option value="<?php echo $t['id']; ?>" <?php echo $filtroTurma == $t['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($t['nome']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="filtro_etapa" class="form-select form-select-sm" style="width: 160px;">
                <option value="0">Todas as etapas</option>
                <?php foreach ($etapasOpts as $e): ?>
                    <option value="<?php echo $e['id']; ?>" <?php echo $filtroEtapa == $e['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($e['nome']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="filtro_professor" class="form-select form-select-sm" style="width: 160px;">
                <option value="0">Todos os professores</option>
                <?php foreach ($professoresOpts as $p): ?>
                    <option value="<?php echo $p['id']; ?>" <?php echo $filtroProfessor == $p['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($p['apelido']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-check form-check-inline ms-1 mb-0 d-flex align-items-center" style="height:31px;">
                <input class="form-check-input me-1" type="checkbox" name="filtro_sem_video" id="filtro_sem_video"
                    value="1" <?php echo $filtroSemVideo === 1 ? 'checked' : ''; ?>>
                <label class="form-check-label small" for="filtro_sem_video">Pendente de vídeo</label>
            </div>
            <button type="submit" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-search"></i>
            </button>
            <?php if ($search || $filtroTurma || $filtroEtapa || $filtroProfessor || $filtroSemVideo === 1): ?>
                <a href="<?php echo $baseHref; ?>?m=etapas_cronogramas&a=list" class="btn btn-outline-danger btn-sm"
                    title="Limpar filtros">
                    <i class="bi bi-x-lg"></i>
                </a>
            <?php endif; ?>
        </form>
        <?php render_bulk_buttons(); ?>
        <?php if ($canEdit): ?>
            <a href="<?php echo $baseHref; ?>?m=etapas_cronogramas&a=form&return=<?php echo urlencode($returnState); ?>"
                class="btn btn-primary btn-sm">
                + Novo(a)
                <?php echo get_table_label($tableName); ?>
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <?php
                $_qSortBase = [
                    'm' => 'etapas_cronogramas',
                    'a' => 'list',
                    'search' => $search,
                    'filtro_turma' => $filtroTurma ?: '',
                    'filtro_etapa' => $filtroEtapa ?: '',
                    'filtro_professor' => $filtroProfessor ?: '',
                    'filtro_sem_video' => $filtroSemVideo === 1 ? '1' : '',
                ];
                ?>
                <thead class="table-light">
                    <tr>
                        <th style="width: 40px;" class="text-center">
                            <input type="checkbox" class="form-check-input" id="bulk-select-all">
                        </th>
                        <th style="width: 130px;">Ações</th>
                        <?php echo sortTh('Etapa', 'etapa', $sortParam, $dirParam, $baseHref, $_qSortBase); ?>
                        <?php echo sortTh('Turma', 'turma', $sortParam, $dirParam, $baseHref, $_qSortBase); ?>
                        <?php echo sortTh('Data', 'data', $sortParam, $dirParam, $baseHref, $_qSortBase); ?>
                        <?php echo sortTh('Início', 'inicio', $sortParam, $dirParam, $baseHref, $_qSortBase); ?>
                        <th>Término</th>
                        <?php echo sortTh('Aula', 'aula', $sortParam, $dirParam, $baseHref, $_qSortBase); ?>
                        <th>Professores</th>
                        <th class="text-center" title="Sem Vídeo (Privacidade)"><i class="bi bi-shield-lock"></i></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
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
                                        <a href="<?php echo $baseHref; ?>?m=etapas_cronogramas&a=view&id=<?php echo $row[$primaryKey]; ?>&return=<?php echo urlencode($returnState); ?>"
                                            class="action-btn action-btn--ver" title="Visualizar" data-bs-toggle="tooltip"><i
                                                class="bi bi-eye"></i></a>
                                        <?php if ($canEdit): ?>
                                            <a href="<?php echo $baseHref; ?>?m=etapas_cronogramas&a=form&id=<?php echo $row[$primaryKey]; ?>&return=<?php echo urlencode($returnState); ?>"
                                                class="action-btn action-btn--editar" title="Editar" data-bs-toggle="tooltip"><i
                                                    class="bi bi-pencil"></i></a>
                                            <div class="dropdown">
                                                <a href="javascript:void(0)" class="action-btn action-btn--mais"
                                                    data-bs-toggle="dropdown" data-bs-strategy="fixed" aria-expanded="false"
                                                    title="Mais ações"><i class="bi bi-three-dots-vertical"></i></a>
                                                <ul class="dropdown-menu dropdown-menu-end action-kebab-menu">
                                                    <li><a class="dropdown-item"
                                                            href="<?php echo $baseHref; ?>?m=etapas_cronogramas&a=form&dup_from=<?php echo $row[$primaryKey]; ?>&return=<?php echo urlencode($returnState); ?>"><i
                                                                class="bi bi-copy text-secondary"></i> Duplicar
                                                            registro</a></li>
                                                    <li><a class="dropdown-item"
                                                            href="<?php echo $baseHref; ?>?m=etapas_cronogramas&a=delete&id=<?php echo $row[$primaryKey]; ?>&return=<?php echo urlencode($returnState); ?>"
                                                            onclick="return confirm('Tem certeza que deseja excluir este registro?')"><i
                                                                class="bi bi-trash text-danger"></i> Excluir</a></li>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($row['etapa_label'] ?? $row['etapa']); ?></td>
                                <td><?php echo htmlspecialchars($row['turma_label'] ?? $row['turma']); ?></td>
                                <td><?php echo $formatPtBrDate($row['data']); ?></td>
                                <td><?php echo substr((string) $row['inicio'], 0, 5); ?></td>
                                <td><?php echo substr((string) $row['termino'], 0, 5); ?></td>
                                <td>
                                    <?php
                                    $aulaTexto = trim((string) ($row['aula'] ?? ''));
                                    if ($aulaTexto === '') {
                                        $aulaTexto = '—';
                                    }
                                    $aulaCurta = mb_strlen($aulaTexto) > 40
                                        ? mb_substr($aulaTexto, 0, 40) . '...'
                                        : $aulaTexto;
                                    echo htmlspecialchars($aulaCurta, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $profs = [];
                                    if (!empty($row['professor_1_label']))
                                        $profs[] = $row['professor_1_label'];
                                    if (!empty($row['professor_2_label']))
                                        $profs[] = $row['professor_2_label'];
                                    if (!empty($row['professor_3_label']))
                                        $profs[] = $row['professor_3_label'];
                                    if (!empty($row['professor_4_label']))
                                        $profs[] = $row['professor_4_label'];
                                    echo htmlspecialchars(implode(', ', $profs));
                                    ?>
                                </td>
                                <td class="text-center">
                                    <?php if (($row['sem_video'] ?? '0') == '1'): ?>
                                        <span class="badge bg-warning text-dark" title="Sem vídeo por privacidade">
                                            <i class="bi bi-shield-lock"></i>
                                        </span>
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

<?php
$_total = $result['total'];
$_perPage = 20;
$_from = ($page - 1) * $_perPage + 1;
$_to = min($page * $_perPage, $_total);

$_qBase = [
    'm' => 'etapas_cronogramas',
    'a' => 'list',
    'search' => $search,
    'filtro_turma' => $filtroTurma ?: '',
    'filtro_etapa' => $filtroEtapa ?: '',
    'filtro_professor' => $filtroProfessor ?: '',
    'filtro_sem_video' => $filtroSemVideo === 1 ? '1' : '',
    'sort' => $sortParam,
    'dir' => $dirParam,
];
$_pageUrl = function (int $p) use ($baseHref, $_qBase): string {
    return $baseHref . '?' . http_build_query(array_merge($_qBase, ['page' => $p]));
};

$_win = 2;
$_start = max(1, $page - $_win);
$_end = min($totalPages, $page + $_win);
if ($_end - $_start < $_win * 2) {
    $_start = max(1, $_end - $_win * 2);
    $_end = min($totalPages, $_start + $_win * 2);
}
?>
<div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
    <nav aria-label="Paginação">
        <ul class="pagination pagination-sm mb-0">
            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?php echo $_pageUrl(1); ?>" title="Primeira">&laquo;&laquo;</a>
            </li>
            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?php echo $_pageUrl(max(1, $page - 1)); ?>">&laquo;</a>
            </li>

            <?php if ($_start > 1): ?>
                <li class="page-item <?php echo $page === 1 ? 'active' : ''; ?>">
                    <a class="page-link link-bold" href="<?php echo $_pageUrl(1); ?>">1</a>
                </li>
                <?php if ($_start > 2): ?>
                    <li class="page-item disabled"><span class="page-link">…</span></li>
                <?php endif; ?>
            <?php endif; ?>

            <?php for ($p = $_start; $p <= $_end; $p++): ?>
                <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                    <a class="page-link link-bold" href="<?php echo $_pageUrl($p); ?>"><?php echo $p; ?></a>
                </li>
            <?php endfor; ?>

            <?php if ($_end < $totalPages): ?>
                <?php if ($_end < $totalPages - 1): ?>
                    <li class="page-item disabled"><span class="page-link">…</span></li>
                <?php endif; ?>
                <li class="page-item <?php echo $page === $totalPages ? 'active' : ''; ?>">
                    <a class="page-link link-bold"
                        href="<?php echo $_pageUrl($totalPages); ?>"><?php echo $totalPages; ?></a>
                </li>
            <?php endif; ?>

            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?php echo $_pageUrl(min($totalPages, $page + 1)); ?>">&raquo;</a>
            </li>
            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?php echo $_pageUrl($totalPages); ?>" title="Última">&raquo;&raquo;</a>
            </li>
        </ul>
    </nav>
    <div class="text-muted small">
        Exibindo <strong><?php echo $_from; ?>–<?php echo $_to; ?></strong>
        de <strong><?php echo $_total; ?></strong> registros
    </div>
</div>


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
render_bulk_modal('etapas_cronogramas', $tableName, $schema, $engine->getLookupsOptions());

// Render JavaScript for handling bulk actions
render_bulk_js('etapas_cronogramas', $tableName);
?>