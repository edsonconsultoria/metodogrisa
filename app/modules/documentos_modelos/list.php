<?php
// app/modules/documentos_modelos/list.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';
require_once __DIR__ . '/../../helpers/pagination.php';
require_once __DIR__ . '/../../helpers/bulk_actions.php';

$tableName = '104_documentos_modelos';
$engine = new CrudEngine($pdo, $tableName);

$page = (int) ($_GET['page'] ?? 1);
$search = $_GET['search'] ?? '';

$result = $engine->getList($page, 20, $search);
$data = $result['data'];
$totalPages = $result['totalPages'];
$schema = $engine->getSchema();
$primaryKey = $engine->getPrimaryKey();

$baseHref = BASE_URL . 'index.php';

$formatPtBrDateTime = function ($datetime) {
    if (!$datetime || $datetime === '0000-00-00 00:00:00')
        return '—';
    return date('d/m/Y H:i', strtotime($datetime));
};
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        Modelos de Documentos
    </h4>
    <div class="d-flex gap-2 align-items-center">
        <form method="get" class="d-flex gap-1 align-items-center">
            <input type="hidden" name="m" value="documentos_modelos">
            <input type="hidden" name="a" value="list">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Buscar..."
                value="<?php echo htmlspecialchars($search); ?>" style="width: 200px;">
            <button type="submit" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-search"></i>
            </button>
            <?php if ($search): ?>
                <a href="<?php echo $baseHref; ?>?m=documentos_modelos&a=list" class="btn btn-outline-danger btn-sm"
                    title="Limpar busca">
                    <i class="bi bi-x-lg"></i>
                </a>
            <?php endif; ?>
        </form>
        <?php if (function_exists('render_bulk_buttons'))
            render_bulk_buttons(); ?>
        <a href="<?php echo $baseHref; ?>?m=documentos_modelos&a=form" class="btn btn-primary btn-sm">
            + Novo Modelo
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
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Descrição</th>
                        <th>Arquivo Modelo</th>
                        <th>Status</th>
                        <th>Criado em</th>
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
                                <td class="text-start">
                                    <div class="d-flex gap-1 align-items-center">
                                        <a href="<?php echo $baseHref; ?>?m=documentos_modelos&a=view&id=<?php echo $row[$primaryKey]; ?>"
                                            class="action-btn action-btn--ver" title="Visualizar" data-bs-toggle="tooltip"><i
                                                class="bi bi-eye"></i></a>
                                        <a href="<?php echo $baseHref; ?>?m=documentos_modelos&a=form&id=<?php echo $row[$primaryKey]; ?>"
                                            class="action-btn action-btn--editar" title="Editar" data-bs-toggle="tooltip"><i
                                                class="bi bi-pencil"></i></a>
                                        <div class="dropdown">
                                            <a href="javascript:void(0)" class="action-btn action-btn--mais"
                                                data-bs-toggle="dropdown" data-bs-strategy="fixed" aria-expanded="false"
                                                title="Mais ações"><i class="bi bi-three-dots-vertical"></i></a>
                                            <ul class="dropdown-menu dropdown-menu-end action-kebab-menu">
                                                <li><a class="dropdown-item"
                                                        href="<?php echo $baseHref; ?>?m=documentos_modelos&a=form_copy&id=<?php echo $row[$primaryKey]; ?>"><i
                                                            class="bi bi-copy text-secondary"></i> Copiar</a></li>
                                                <li><a class="dropdown-item"
                                                        href="<?php echo $baseHref; ?>?m=documentos_modelos&a=delete&id=<?php echo $row[$primaryKey]; ?>"
                                                        onclick="return confirm('Tem certeza que deseja excluir este modelo?')"><i
                                                            class="bi bi-trash text-danger"></i> Excluir</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php echo $row['id']; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($row['nome']); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars(substr($row['descricao'], 0, 50) . (strlen($row['descricao']) > 50 ? '...' : '')); ?>
                                </td>
                                <td>
                                    <?php if (!empty($row['arquivo_template'])): ?>
                                        <a href="<?php echo UPLOADS_URL . '/' . $row['arquivo_template']; ?>"
                                            class="btn btn-outline-primary btn-xs" target="_blank" title="Baixar Modelo DOCX"
                                            data-bs-toggle="tooltip">
                                            <i class="bi bi-file-earmark-word"></i> Baixar
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">Sem template</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $row['ativo'] ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo $row['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $formatPtBrDateTime($row['created_at']); ?>
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
        <?php echo Pagination::render($page, $totalPages, $baseHref, ['m' => 'documentos_modelos', 'a' => 'list', 'search' => $search]); ?>
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
if (function_exists('render_bulk_modal')) {
    render_bulk_modal('documentos_modelos', $tableName, $schema, $engine->getLookupsOptions());
}
if (function_exists('render_bulk_js')) {
    render_bulk_js('documentos_modelos');
}
?>