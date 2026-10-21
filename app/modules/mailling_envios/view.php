<?php
// app/modules/mailling_envios/view.php

require_once __DIR__ . '/../../config/config.php';

if (!current_user_is_at_least_admin()) {
    echo '<div class="alert alert-danger mt-3">Acesso negado. Apenas administradores podem acessar este módulo.</div>';
    include __DIR__ . '/../../templates/footer.php';
    exit;
}

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';

$tableName = '101_mailling_envios';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$engine = new CrudEngine($pdo, $tableName);

$record = $engine->getRecord($id);

if (!$record) {
    echo '<div class="alert alert-warning">Registro não encontrado.</div>';
    return;
}

$formatPtBrDate = function ($date) {
    if (!$date || $date === '0000-00-00 00:00:00')
        return '—';
    return date('d/m/Y H:i', strtotime($date));
};
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Visualizar Log de Envio #<?php echo htmlspecialchars($id); ?></h4>
    <div>
        <a href="<?php echo BASE_URL; ?>index.php?m=mailling_envios&a=list"
            class="btn btn-outline-secondary btn-sm">Voltar</a>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3 text-muted">E-mail</dt>
            <dd class="col-sm-9"><?php echo htmlspecialchars($record['email']); ?></dd>

            <dt class="col-sm-3 text-muted">Enviado em</dt>
            <dd class="col-sm-9"><?php echo $formatPtBrDate($record['enviado']); ?></dd>

            <dt class="col-sm-3 text-muted">Assunto/Título</dt>
            <dd class="col-sm-9"><?php echo htmlspecialchars($record['titulo'] ?? '—'); ?></dd>

            <hr class="my-3">

            <dt class="col-sm-12 text-muted mb-2">Mensagem Enviada</dt>
            <dd class="col-sm-12">
                <div class="p-3 border rounded bg-light min-vh-50">
                    <?php echo $record['mensagem']; // Renderiza HTML se houver ?>
                </div>
            </dd>
        </dl>
    </div>
</div>