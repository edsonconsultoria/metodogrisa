<?php
// app/modules/mailling_lista/view.php

require_once __DIR__ . '/../../config/config.php';

if (!current_user_is_at_least_admin()) {
    echo '<div class="alert alert-danger mt-3">Acesso negado. Apenas administradores podem acessar este módulo.</div>';
    include __DIR__ . '/../../templates/footer.php';
    exit;
}
require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';

$tableName = '101_mailling_lista';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$engine = new CrudEngine($pdo, $tableName);

$record = $engine->getRecord($id);

if (!$record) {
    echo '<div class="alert alert-warning">Lista não encontrada.</div>';
    return;
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Visualizar Lista de Mailing #<?php echo htmlspecialchars($id); ?></h4>
    <div>
        <a href="<?php echo BASE_URL; ?>index.php?m=mailling_lista&a=list"
            class="btn btn-outline-secondary btn-sm">Voltar</a>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3 text-muted">Nome da Lista</dt>
            <dd class="col-sm-9"><?php echo htmlspecialchars($record['nome_da_lista']); ?></dd>

            <hr class="my-3">

            <dt class="col-sm-12 text-muted mb-2">Comando SQL</dt>
            <dd class="col-sm-12">
                <div class="p-3 border rounded bg-light font-monospace" style="white-space: pre-wrap;">
                    <?php echo htmlspecialchars($record['sql']); ?></div>
            </dd>
        </dl>
    </div>
</div>