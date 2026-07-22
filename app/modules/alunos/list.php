<?php
// Version 1.0.1 - Cleaned up removed columns: foto.
// prevenção a erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// app/modules/alunos/list.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';
require_once __DIR__ . '/../../helpers/pagination.php';
require_once __DIR__ . '/../../helpers/bulk_actions.php';
require_once __DIR__ . '/../../helpers/list_state.php';

$tableName = '104_alunos';
$engine = new CrudEngine($pdo, $tableName);

$engine->setLookups([
    'turma' => ['table' => '104_turmas', 'label' => 'nome']
]);

$page = (int) ($_GET['page'] ?? 1);
$perPage = 20;
$search = trim($_GET['search'] ?? '');
$filtroTurma = $_GET['filtro_turma'] ?? '';
$sort = $_GET['sort'] ?? 'data_alteracao';
$order = isset($_GET['order']) && strtolower($_GET['order']) === 'asc' ? 'ASC' : 'DESC';


$sortMapping = [
    'id' => 'a.id',
    'turma' => 't.nome',
    'aluno' => 'p.nome',
    'cert_mg' => 'a.emitido_cert_metodo_grisa',
    'cert_uni' => 'a.emitido_cert_universidade',
    'obs' => 'a.obs',
    'data_inclusao' => 'a.data_inclusao',
    'data_alteracao' => 'a.data_alteracao'
];
$orderBySql = $sortMapping[$sort] ?? 'a.id'; // Default to a.id since p.nome might not be joined correctly in some edge cases
$orderBySql .= " $order";


// Fetch turmas for the filter
$stmtTurmas = $pdo->query("SELECT id, nome FROM 104_turmas ORDER BY id DESC");
$turmasOptionsArr = $stmtTurmas->fetchAll(PDO::FETCH_ASSOC);



// Contagem total para paginação
$sqlWhereParts = [];
$params = [];
if ($search !== '') {
    $sqlWhereParts[] = "(p.nome LIKE :s OR t.nome LIKE :s2)";
    $params[':s'] = '%' . $search . '%';
    $params[':s2'] = '%' . $search . '%';
}
if ($filtroTurma !== '') {
    $sqlWhereParts[] = "a.turma = :ft";
    $params[':ft'] = $filtroTurma;
}

$sqlWhere = !empty($sqlWhereParts) ? 'WHERE ' . implode(' AND ', $sqlWhereParts) : '';


$stmtCount = $pdo->prepare("
    SELECT COUNT(*) FROM 104_alunos a
    LEFT JOIN 101_pessoas p ON p.id = a.aluno
    LEFT JOIN 104_turmas  t ON t.id = a.turma
    $sqlWhere
");
$stmtCount->execute($params);
$total = (int) $stmtCount->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));
$page = max(1, min($page, $totalPages));
$offset = ($page - 1) * $perPage;

// Dados paginados e ordenados por nome
$stmtData = $pdo->prepare("
    SELECT a.id, a.turma, a.aluno, a.obs, a.emitido_cert_metodo_grisa, a.emitido_cert_universidade,
           a.diploma, a.rg_carteira_conselho, a.cpf, a.cer_nascimento_casamento,
           a.comprovante_residencia, a.tcc, a.diarios_classe,
           a.data_inclusao, a.data_alteracao,
           p.nome AS aluno_label, p.fone_1 AS _fone_1, p.email AS _email,
           t.nome AS turma_label
    FROM 104_alunos a
    LEFT JOIN 101_pessoas p ON p.id = a.aluno
    LEFT JOIN 104_turmas  t ON t.id = a.turma

    $sqlWhere
    ORDER BY $orderBySql
    LIMIT :limit OFFSET :offset
");
$stmtParams = $params;
$stmtParams[':limit'] = $perPage;
$stmtParams[':offset'] = $offset;
$stmtData->execute($stmtParams);
$data = $stmtData->fetchAll(PDO::FETCH_ASSOC);

$schema = $engine->getSchema();
$primaryKey = $engine->getPrimaryKey();


$baseHref = BASE_URL . 'index.php';

$formatPtBrDate = function ($date) {
    if (!$date || $date === '0000-00-00')
        return '—';
    return date('d/m/Y', strtotime($date));
};

$renderSortLink = function ($key, $label) use ($sort, $order, $baseHref, $search, $filtroTurma) {
    $isCurrent = $sort === $key;
    $nextOrder = ($isCurrent && $order === 'ASC') ? 'desc' : 'asc';
    $params = [
        'm' => 'alunos',
        'a' => 'list',
        'search' => $search,
        'filtro_turma' => $filtroTurma,
        'sort' => $key,
        'order' => $nextOrder
    ];
    $url = $baseHref . '?' . http_build_query($params);
    $icon = '';
    if ($isCurrent) {
        $icon = $order === 'ASC' ? ' <i class="bi bi-sort-alpha-down"></i>' : ' <i class="bi bi-sort-alpha-up-alt"></i>';
    }
    return '<a href="' . $url . '" class="text-decoration-none text-dark">' . $label . $icon . '</a>';
};
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <?php echo get_table_label($tableName); ?>
    </h4>
    <div class="d-flex gap-2 align-items-center">
        <form method="get" class="d-flex gap-1 align-items-center">
            <input type="hidden" name="m" value="alunos">
            <input type="hidden" name="a" value="list">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Buscar..."
                value="<?php echo htmlspecialchars($search); ?>" style="width: 180px;">

            <select name="filtro_turma" class="form-select form-select-sm" style="width: 180px;"
                onchange="this.form.submit()">
                <option value="">Todas as Turmas</option>
                <?php foreach ($turmasOptionsArr as $t): ?>
                    <option value="<?php echo $t['id']; ?>" <?php echo $filtroTurma == $t['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($t['nome']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-search"></i>
            </button>
            <?php if ($search || $filtroTurma): ?>
                <a href="<?php echo $baseHref; ?>?m=alunos&a=list" class="btn btn-outline-danger btn-sm"
                    title="Limpar busca">
                    <i class="bi bi-x-lg"></i>
                </a>
            <?php endif; ?>
        </form>
        <?php render_bulk_buttons(); ?>
        <a href="<?php echo $baseHref; ?>?m=alunos&a=form" class="btn btn-primary btn-sm">
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
                        <th><?php echo $renderSortLink('id', 'ID'); ?></th>
                        <th><?php echo $renderSortLink('turma', 'Turma'); ?></th>
                        <th><?php echo $renderSortLink('aluno', 'Aluno'); ?></th>
                        <th class="text-center"><?php echo $renderSortLink('cert_mg', 'Cert MG'); ?></th>
                        <th class="text-center"><?php echo $renderSortLink('cert_uni', 'Cert Uni'); ?></th>
                        <th><?php echo $renderSortLink('obs', 'Obs'); ?></th>
                        <th>Contato</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">
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
                                        <a href="<?php echo list_state_action_url('alunos', 'view', ['id' => $row[$primaryKey]]); ?>"
                                            class="action-btn action-btn--ver" title="Visualizar" data-bs-toggle="tooltip"><i
                                                class="bi bi-eye"></i></a>
                                        <a href="<?php echo list_state_action_url('alunos', 'form', ['id' => $row[$primaryKey]]); ?>"
                                            class="action-btn action-btn--editar" title="Editar" data-bs-toggle="tooltip"><i
                                                class="bi bi-pencil"></i></a>
                                        <div class="dropdown">
                                            <a href="javascript:void(0)" class="action-btn action-btn--mais"
                                                data-bs-toggle="dropdown" data-bs-strategy="fixed" aria-expanded="false"
                                                title="Mais ações"><i class="bi bi-three-dots-vertical"></i></a>
                                            <ul class="dropdown-menu dropdown-menu-end action-kebab-menu">
                                                <li><a class="dropdown-item" href="javascript:void(0)"
                                                        onclick="sendDocs(<?php echo $row[$primaryKey]; ?>, this)"><i
                                                            class="bi bi-envelope text-primary"></i> Enviar documentos
                                                        faculdade</a></li>
                                                <li><a class="dropdown-item"
                                                        href="<?php echo list_state_action_url('alunos', 'delete', ['id' => $row[$primaryKey]]); ?>"
                                                        onclick="return confirm('Tem certeza que deseja excluir este registro?')"><i
                                                            class="bi bi-trash text-danger"></i> Excluir</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="me-2 rounded-circle bg-light border text-secondary d-flex align-items-center justify-content-center shadow-sm"
                                            style="width: 32px; height: 32px; flex-shrink: 0; font-size: 1rem;">
                                            <i class="bi bi-person"></i>
                                        </div>
                                        <div><?php echo htmlspecialchars((string) ($row[$primaryKey] ?? '')); ?></div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($row['turma_label'] ?? $row['turma'] ?? '—'); ?></td>
                                <td class="fw-semibold"><?php echo htmlspecialchars($row['aluno_label'] ?? '—'); ?></td>
                                <td class="text-center">
                                    <div class="cert-toggle-wrapper" style="cursor: pointer;"
                                        onclick="toggleCert(<?php echo $row[$primaryKey]; ?>, 'emitido_cert_metodo_grisa', this)"
                                        data-status="<?php echo ($row['emitido_cert_metodo_grisa'] ?? '') === '1' ? '1' : '0'; ?>">
                                        <?php if (($row['emitido_cert_metodo_grisa'] ?? '') === '1'): ?>
                                            <i class="bi bi-check-lg fw-bold text-success" title="Sim"></i>
                                        <?php else: ?>
                                            <i class="bi bi-x-lg fw-bold text-danger" title="Não"></i>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="cert-toggle-wrapper" style="cursor: pointer;"
                                        onclick="toggleCert(<?php echo $row[$primaryKey]; ?>, 'emitido_cert_universidade', this)"
                                        data-status="<?php echo ($row['emitido_cert_universidade'] ?? '') === '1' ? '1' : '0'; ?>">
                                        <?php if (($row['emitido_cert_universidade'] ?? '') === '1'): ?>
                                            <i class="bi bi-check-lg fw-bold text-success" title="Sim"></i>
                                        <?php else: ?>
                                            <i class="bi bi-x-lg fw-bold text-danger" title="Não"></i>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="small text-muted">
                                    <?php echo htmlspecialchars(substr((string) ($row['obs'] ?? ''), 0, 60)); ?>
                                </td>
                                <td class="small">
                                    <?php if (!empty($row['_fone_1'])):
                                        $cleanPhone = preg_replace('/\D/', '', $row['_fone_1']);
                                        if (strlen($cleanPhone) === 11 || strlen($cleanPhone) === 10)
                                            $cleanPhone = '55' . $cleanPhone;
                                        ?>
                                        <div>
                                            <a href="https://wa.me/<?php echo $cleanPhone; ?>" target="_blank"
                                                class="text-decoration-none text-success">
                                                <i class="bi bi-whatsapp me-1"></i><?php echo htmlspecialchars($row['_fone_1']); ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($row['_email'])): ?>
                                        <div>
                                            <a href="mailto:<?php echo htmlspecialchars($row['_email']); ?>"
                                                class="text-decoration-none text-primary">
                                                <i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($row['_email']); ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (empty($row['_fone_1']) && empty($row['_email'])): ?>
                                        <span class="text-muted">—</span>
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
$_total = $total;
$_perPage = 20;
$_from = ($page - 1) * $_perPage + 1;
$_to = min($page * $_perPage, $_total);

$_qBase = [
    'm' => 'alunos',
    'a' => 'list',
    'search' => $search,
    'filtro_turma' => $filtroTurma,
    'sort' => $sort,
    'order' => strtolower($order)
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
<?php if ($_total > 0): ?>
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
<?php endif; ?>


<script>
    function toggleCert(id, field, element) {
        const formData = new FormData();
        formData.append('id', id);
        formData.append('field', field);

        // Feedback visual imediato (opcional, mas bom)
        element.style.opacity = '0.5';
        element.style.pointerEvents = 'none';

        fetch('<?php echo $baseHref; ?>?m=alunos&a=ajax_toggle', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const isCheck = data.newValue === '1';
                    element.setAttribute('data-status', data.newValue);
                    element.innerHTML = isCheck
                        ? '<i class="bi bi-check-lg fw-bold text-success" title="Sim"></i>'
                        : '<i class="bi bi-x-lg fw-bold text-danger" title="Não"></i>';
                } else {
                    alert('Erro: ' + (data.error || 'Erro desconhecido'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Erro ao processar a requisição.');
            })
            .finally(() => {
                element.style.opacity = '1';
                element.style.pointerEvents = 'auto';
            });
    }

    function sendDocs(id, element) {
        if (!confirm('Deseja enviar a documentação deste aluno para a faculdade?')) return;

        const originalHtml = element.innerHTML;
        element.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        element.style.pointerEvents = 'none';

        fetch('<?php echo $baseHref; ?>?m=alunos&a=ajax_send_docs&id=' + id + '&csrf_token=<?php echo urlencode(csrf_token()); ?>', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(async response => {
                const text = await response.text();
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        alert('E-mail enviado com sucesso!');
                    } else {
                        alert('Erro ao enviar e-mail: ' + (data.message || 'Erro desconhecido.'));
                    }
                } catch (e) {
                    console.error('Invalid JSON response:', text);
                    alert('Erro na requisição ao servidor. O servidor retornou uma resposta inválida. Verifique o console ou contate o suporte.');
                    // For debugging, we can show the start of the response
                    if (text.trim().length > 0) {
                        const debugWin = window.open('', '_blank');
                        debugWin.document.write('<h4>Resposta do Servidor:</h4><pre>' + text.replace(/</g, '&lt;') + '</pre>');
                    }
                }
            })
            .catch(error => {
                console.error('Network Error:', error);
                alert('Erro de conexão ou rede.');
            })
            .finally(() => {
                element.innerHTML = originalHtml;
                element.style.pointerEvents = 'auto';
            });
    }


    document.addEventListener('DOMContentLoaded', function () {

        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    });
</script>

<?php
// Render dynamic modal for bulk update
render_bulk_modal('alunos', $tableName, $schema, $engine->getLookupsOptions());

// Render JavaScript for handling bulk actions
render_bulk_js('alunos');
?>