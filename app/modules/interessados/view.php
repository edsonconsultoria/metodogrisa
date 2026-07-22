<?php
// app/modules/interessados/view.php

require_once __DIR__ . '/../../config/config.php';

if (!current_user_is_at_least_admin()) {
    echo '<div class="alert alert-danger mt-3">Acesso negado. Apenas administradores podem acessar este módulo.</div>';
    include __DIR__ . '/../../templates/footer.php';
    exit;
}
require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';

$tableName = '101_interessados';
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
    <h4 class="mb-0">Visualizar <?php echo get_table_label($tableName); ?> #<?php echo htmlspecialchars($id); ?></h4>
    <div>
        <a href="<?php echo BASE_URL; ?>index.php?m=interessados&a=form&id=<?php echo $id; ?>"
            class="btn btn-primary btn-sm">Editar</a>
        <a href="<?php echo BASE_URL; ?>index.php?m=interessados&a=list"
            class="btn btn-outline-secondary btn-sm">Voltar</a>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3 text-muted">Nome</dt>
            <dd class="col-sm-9"><?php echo htmlspecialchars($record['nome']); ?></dd>

            <dt class="col-sm-3 text-muted">Telefone</dt>
            <dd class="col-sm-9"><?php echo htmlspecialchars($record['telefone']); ?></dd>

            <dt class="col-sm-3 text-muted">E-mail</dt>
            <dd class="col-sm-9"><?php echo htmlspecialchars($record['email']); ?></dd>

            <dt class="col-sm-3 text-muted">Origem</dt>
            <dd class="col-sm-9"><?php echo htmlspecialchars($record['origem'] ?? '—'); ?></dd>

            <dt class="col-sm-3 text-muted">Cadastramento</dt>
            <dd class="col-sm-9"><?php echo $formatPtBrDate($record['cadastramento']); ?></dd>

            <hr class="my-3">

            <dt class="col-sm-12 text-muted mb-2">Notas</dt>
            <dd class="col-sm-12">
                <div class="p-3 border rounded bg-light min-vh-25">
                    <?php echo nl2br(htmlspecialchars($record['notas'] ?? '')); ?>
                </div>
            </dd>
        </dl>
    </div>
</div>