<?php
// app/modules/101_mailling/view.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';

$tableName = '101_mailling';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$engine = new CrudEngine($pdo, $tableName);

$engine->setLookups([
    'destinatarios' => ['table' => '101_mailling_lista', 'label' => 'nome_da_lista']
]);

$record = $engine->getRecord($id);
$schema = $engine->getSchema();

if (!$record) {
    echo '<div class="alert alert-warning">Registro não encontrado.</div>';
    return;
}

$formatPtBrDate = function ($date) {
    if (!$date || $date === '0000-00-00')
        return '—';
    return date('d/m/Y', strtotime($date));
};
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Visualizar
        <?php echo get_table_label($tableName); ?> #
        <?php echo htmlspecialchars($id); ?>
    </h4>
    <div>
        <a href="<?php echo BASE_URL; ?>index.php?m=<?php echo $tableName; ?>&a=form&id=<?php echo $id; ?>"
            class="btn btn-primary btn-sm">Editar</a>
        <a href="<?php echo BASE_URL; ?>index.php?m=<?php echo $tableName; ?>&a=list"
            class="btn btn-outline-secondary btn-sm">Voltar</a>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <dl class="row mb-0">
            <!-- Data -->
            <dt class="col-sm-3 text-muted">Data</dt>
            <dd class="col-sm-9">
                <?php echo $formatPtBrDate($record['data']); ?>
            </dd>

            <!-- Destinatários -->
            <dt class="col-sm-3 text-muted">Lista de Destinatários</dt>
            <dd class="col-sm-9">
                <?php echo htmlspecialchars($record['destinatarios_label'] ?? $record['destinatarios']); ?>
            </dd>

            <!-- Assunto -->
            <dt class="col-sm-3 text-muted">Assunto</dt>
            <dd class="col-sm-9 font-weight-bold">
                <?php echo htmlspecialchars($record['assunto']); ?>
            </dd>

            <hr class="my-3">

            <!-- Mensagem -->
            <dt class="col-sm-12 text-muted mb-2">Conteúdo da Mensagem</dt>
            <dd class="col-sm-12">
                <div class="p-3 border rounded bg-light">
                    <?php echo $record['mensagem']; ?>
                </div>
            </dd>

            <hr class="my-3">

            <!-- Anexos -->
            <dt class="col-sm-3 text-muted">Anexos</dt>
            <dd class="col-sm-9">
                <?php if (empty($record['anexo'])): ?>
                    <span class="text-muted small"><em>Nenhum anexo disponível.</em></span>
                <?php else: ?>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach (explode(',', $record['anexo']) as $file): ?>
                            <?php
                            $fileName = basename($file);
                            $fileUrl = UPLOADS_URL . '/' . $file;
                            ?>
                            <a href="<?php echo htmlspecialchars($fileUrl); ?>" target="_blank"
                                class="btn btn-outline-info btn-sm">
                                <i class="bi bi-paperclip"></i>
                                <?php echo htmlspecialchars($fileName); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </dd>
        </dl>
    </div>
</div>