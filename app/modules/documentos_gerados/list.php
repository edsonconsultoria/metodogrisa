<?php
// app/modules/documentos_gerados/list.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/list_state.php';
require_once __DIR__ . '/../../helpers/bulk_actions.php';

$tableName = '104_documentos_gerados';
$engine = new CrudEngine($pdo, $tableName);

$engine->setLookups([
    'modelo_id' => ['table' => '104_documentos_modelos', 'label' => 'nome'],
    'pessoa_id' => ['table' => '101_pessoas', 'label' => 'nome'],
    'criado_por' => ['table' => '101_pessoas', 'label' => 'nome']
]);

$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$search = $_GET['search'] ?? '';
$limit = 20;

// Sorting logic
$allowedSort = [
    'id' => 'id',
    'titulo' => 'titulo',
    'modelo' => 'modelo_id',
    'pessoa' => 'pessoa_id',
    'data' => 'created_at'
];

$sort = $_GET['sort'] ?? 'id';
$dir = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
$orderField = $allowedSort[$sort] ?? 'id';
$orderDirection = strtoupper($dir);
$orderBy = "`$orderField` $orderDirection";

$filters = [];
if ($search !== '') {
    $searchEscaped = '%' . $search . '%';
    $filters['_custom_where'] = "(
        `titulo` LIKE " . $pdo->quote($searchEscaped) . " 
        OR `pessoa_id` IN (SELECT `id` FROM `101_pessoas` WHERE `nome` LIKE " . $pdo->quote($searchEscaped) . ")
    )";
}

$result = $engine->getList($page, $limit, '', $filters, $orderBy);
$records = $result['data'];
$total = $result['total'];
$totalPages = ceil($total / $limit);
$schema = $engine->getSchema();

$baseHref = BASE_URL . 'index.php';

$buildSortUrl = function (string $field) use ($baseHref, $search, $sort, $dir) {
    $newDir = ($sort === $field && $dir === 'asc') ? 'desc' : 'asc';
    $params = [
        'm' => 'documentos_gerados',
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

$formatPtBrDateTime = function ($datetime) {
    if (!$datetime || $datetime === '0000-00-00 00:00:00' || strtotime($datetime) === false)
        return '—';
    return date('d/m/Y H:i', strtotime($datetime));
};
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

    #doc-copy-toast {
        position: fixed;
        bottom: 1.5rem;
        right: 1.5rem;
        z-index: 1090;
        min-width: 320px;
    }
</style>

<!-- Toast de feedback da cópia -->
<div id="doc-copy-toast" class="toast align-items-center border-0 text-bg-success" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="6000">
    <div class="d-flex">
        <div class="toast-body" id="doc-copy-toast-body">
            Cópia criada com sucesso!
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Documentos Gerados</h4>
    <div class="d-flex gap-2">
        <?php render_bulk_buttons(); ?>
        <button type="button" class="btn btn-outline-success btn-sm d-none" id="btn-bulk-download">
            <i class="bi bi-download"></i> Baixar selecionados (.zip)
        </button>
        <a href="<?php echo list_state_action_url('documentos_gerados', 'gerar_lote'); ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-file-earmark-medical"></i> Gerar em Lote
        </a>
        <a href="<?php echo list_state_action_url('documentos_gerados', 'form'); ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg"></i> Novo Documento
        </a>
    </div>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'lote_success'): ?>
    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        <strong>Sucesso!</strong> Foram gerados com sucesso <strong><?php echo (int)($_GET['count'] ?? 0); ?></strong> documentos em lote para a turma.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'success'): ?>
    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        <strong>Sucesso!</strong> Registro salvo.
        <?php if (isset($_GET['msg']) && strpos($_SERVER['QUERY_STRING'] ?? '', 'error_gen') !== false): ?>
            <br><span class="text-danger">Atenção: O arquivo físico não pôde ser gerado. Verifique se o template é um arquivo
                .docx válido.</span>
        <?php endif; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'error_gen'): ?>
    <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
        <strong>Atenção!</strong> O registro foi salvo, mas o <b>arquivo .docx não foi gerado</b>. Isso acontece quando o
        template é um arquivo .doc antigo ou está corrompido. Converta o modelo para .docx.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="get" action="<?php echo $baseHref; ?>" class="row g-2">
            <input type="hidden" name="m" value="documentos_gerados">
            <input type="hidden" name="a" value="list">
            <div class="col-md-10">
                <input type="text" name="search" class="form-control" placeholder="Buscar por título ou pessoa..."
                    value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> Buscar
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th width="40" class="text-center">
                        <input type="checkbox" class="form-check-input" id="bulk-select-all">
                    </th>
                    <th width="120">Ações</th>
                    <th width="80">
                        <a href="<?php echo $buildSortUrl('id'); ?>" class="sortable-link">
                            ID<?php echo $renderSortIcon('id'); ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?php echo $buildSortUrl('titulo'); ?>" class="sortable-link">
                            Título<?php echo $renderSortIcon('titulo'); ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?php echo $buildSortUrl('modelo'); ?>" class="sortable-link">
                            Modelo<?php echo $renderSortIcon('modelo'); ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?php echo $buildSortUrl('pessoa'); ?>" class="sortable-link">
                            Pessoa<?php echo $renderSortIcon('pessoa'); ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?php echo $buildSortUrl('data'); ?>" class="sortable-link">
                            Gerado em<?php echo $renderSortIcon('data'); ?>
                        </a>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">Nenhum registro encontrado.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($records as $row): ?>
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input bulk-select-item"
                                    value="<?php echo (int) $row['id']; ?>">
                            </td>
                            <td>
                                <div class="d-flex gap-1 align-items-center">
                                    <a href="<?php echo list_state_action_url('documentos_gerados', 'view', ['id' => $row['id']]); ?>"
                                        class="btn btn-link btn-sm p-0 m-0" title="Visualizar">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="<?php echo UPLOADS_URL . '/' . $row['arquivo_docx']; ?>" target="_blank"
                                        class="btn btn-link btn-sm p-0 m-0 ms-2 text-primary" title="Baixar DOCX">
                                        <i class="bi bi-download"></i>
                                    </a>
                                    <div class="dropdown d-inline-block ms-2">
                                        <a href="javascript:void(0)" class="btn btn-link btn-sm p-0 m-0 text-secondary"
                                            data-bs-toggle="dropdown" data-bs-strategy="fixed" aria-expanded="false"
                                            title="Mais ações"><i class="bi bi-three-dots-vertical"></i></a>
                                        <ul class="dropdown-menu dropdown-menu-end action-kebab-menu">
                                            <li><button type="button" class="dropdown-item btn-copiar-doc"
                                                    data-id="<?php echo (int) $row['id']; ?>"
                                                    data-titulo="<?php echo htmlspecialchars($row['titulo'], ENT_QUOTES); ?>"><i
                                                        class="bi bi-files text-success"></i> Copiar com modelo
                                                    atualizado</button></li>
                                            <li><a class="dropdown-item"
                                                    href="<?php echo list_state_action_url('documentos_gerados', 'delete', ['id' => $row['id']]); ?>"
                                                    onclick="return confirm('Excluir este registro permanentemente?')"><i
                                                            class="bi bi-trash text-danger"></i> Excluir</a></li>
                                        </ul>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php echo $row['id']; ?>
                            </td>
                            <td class="fw-bold">
                                <?php echo htmlspecialchars($row['titulo']); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($row['modelo_id_label'] ?? $row['modelo_id']); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($row['pessoa_id_label'] ?? $row['pessoa_id']); ?>
                            </td>
                            <td class="small">
                                <?php echo $formatPtBrDateTime($row['created_at']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card-footer bg-white border-top-0">
        <?php
        $_total = $total;
        $_perPage = $limit;
        $_from = ($_total > 0) ? ($page - 1) * $_perPage + 1 : 0;
        $_to = min($page * $_perPage, $_total);

        $_qBase = [
            'm' => 'documentos_gerados',
            'a' => 'list',
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir,
        ];
        $_pageUrl = function (int $p) use ($baseHref, $_qBase): string {
            return $baseHref . '?' . http_build_query(array_merge($_qBase, ['page' => $p]));
        };

        // Pagination window logic
        $_win = 2;
        $_start = max(1, $page - $_win);
        $_end = min($totalPages, $page + $_win);
        ?>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <!-- Left: Pagination -->
            <nav aria-label="Paginação">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo $_pageUrl(1); ?>" title="Primeira">&laquo;&laquo;</a>
                    </li>
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo $_pageUrl(max(1, $page - 1)); ?>">&laquo;</a>
                    </li>

                    <?php if ($_start > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?php echo $_pageUrl(1); ?>">1</a>
                        </li>
                        <?php if ($_start > 2): ?>
                            <li class="page-item disabled"><span class="page-link">…</span></li>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($p = $_start; $p <= $_end; $p++): ?>
                        <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                            <a class="page-link fw-bold" href="<?php echo $_pageUrl($p); ?>"><?php echo $p; ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($_end < $totalPages): ?>
                        <?php if ($_end < $totalPages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">…</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="<?php echo $_pageUrl($totalPages); ?>"><?php echo $totalPages; ?></a>
                        </li>
                    <?php endif; ?>

                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo $_pageUrl(min($totalPages, $page + 1)); ?>">&raquo;</a>
                    </li>
                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo $_pageUrl($totalPages); ?>"
                            title="Última">&raquo;&raquo;</a>
                    </li>
                </ul>
            </nav>

            <!-- Right: Record Summary -->
            <div class="text-muted small text-end">
                <div>Exibindo <strong><?php echo $_from; ?>–<?php echo $_to; ?></strong> de
                    <strong><?php echo $_total; ?></strong> registros
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<?php
render_bulk_modal('documentos_gerados', $tableName, $schema, $engine->getLookupsOptions());
render_bulk_js('documentos_gerados', $tableName);
?>

<script>
(function () {
    'use strict';

    const copyUrl  = '<?php echo BASE_URL; ?>app/modules/documentos_gerados/ajax_copy.php';
    const toastEl  = document.getElementById('doc-copy-toast');
    const toastBody = document.getElementById('doc-copy-toast-body');
    const bsToast  = toastEl ? new bootstrap.Toast(toastEl) : null;

    function currentListUrl(extraParams) {
        const p = new URLSearchParams(window.location.search);
        p.set('m', 'documentos_gerados');
        p.set('a', 'list');
        Object.entries(extraParams || {}).forEach(function ([k, v]) { p.set(k, v); });
        return window.location.pathname + '?' + p.toString();
    }

    function showToast(html, isError) {
        if (!bsToast) return;
        toastEl.className = 'toast align-items-center border-0 text-bg-' + (isError ? 'danger' : 'success');
        toastBody.innerHTML = html;
        bsToast.show();
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-copiar-doc');
        if (!btn) return;

        const id     = btn.dataset.id;
        const titulo = btn.dataset.titulo;

        if (!confirm('Criar uma nova cópia do documento "' + titulo + '" usando o modelo atualizado?')) {
            return;
        }

        btn.disabled = true;
        const icon = btn.querySelector('i');
        if (icon) { icon.className = 'bi bi-hourglass-split'; }

        const body = new URLSearchParams({ id });

        fetch(copyUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            credentials: 'same-origin'
        })
        .then(function (res) {
            return res.text().then(function (text) {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error('Resposta inválida do servidor: ' + text.substring(0, 120));
                }
            });
        })
        .then(function (data) {
            btn.disabled = false;
            if (icon) { icon.className = 'bi bi-files'; }

            if (!data.success) {
                showToast('<strong>Erro:</strong> ' + (data.message || 'Falha ao copiar.'), true);
                return;
            }

            let html = '<strong>Cópia criada!</strong> ';
            if (data.arquivo_gerado) {
                html += '<a href="' + data.download_url + '" target="_blank" class="text-white fw-bold">'
                      + '<i class="bi bi-download"></i> Baixar DOCX</a>';
            } else if (data.aviso) {
                html += '<small>' + data.aviso + '</small>';
            }

            showToast(html, false);

            // Recarrega mantendo filtro, ordenação e página atuais
            const reloadUrl = currentListUrl({ copied: data.id });
            toastEl.addEventListener('hidden.bs.toast', function reload() {
                toastEl.removeEventListener('hidden.bs.toast', reload);
                window.location.href = reloadUrl;
            }, { once: true });
            // Fallback: recarrega mesmo se o toast não disparar o evento
            setTimeout(function () { window.location.href = reloadUrl; }, 3500);
        })
        .catch(function (err) {
            btn.disabled = false;
            if (icon) { icon.className = 'bi bi-files'; }
            showToast('<strong>Erro:</strong> ' + (err.message || 'Falha na requisição.'), true);
        });
    });

    // --- Lógica para Baixar em Lote (.zip) ---
    const btnBulkDownload = document.getElementById('btn-bulk-download');

    function updateDownloadButton() {
        if (!btnBulkDownload) return;
        const checkedCount = Array.from(document.querySelectorAll('.bulk-select-item:checked')).length;
        if (checkedCount > 0) {
            btnBulkDownload.classList.remove('d-none');
        } else {
            btnBulkDownload.classList.add('d-none');
        }
    }

    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('bulk-select-item') || e.target.id === 'bulk-select-all') {
            setTimeout(updateDownloadButton, 50);
        }
    });

    if (btnBulkDownload) {
        btnBulkDownload.addEventListener('click', function () {
            const selectedIds = Array.from(document.querySelectorAll('.bulk-select-item:checked')).map(cb => cb.value);
            if (selectedIds.length === 0) {
                alert('Por favor, selecione pelo menos um documento.');
                return;
            }

            // Criar um formulário oculto para fazer a submissão POST e forçar o download direto
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?php echo BASE_URL; ?>index.php?m=documentos_gerados&a=baixar_lote';
            form.style.display = 'none';

            selectedIds.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = id;
                form.appendChild(input);
            });

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        });
    }
}());
</script>