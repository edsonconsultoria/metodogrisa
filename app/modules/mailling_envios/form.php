<?php
// app/modules/mailling_envios/form.php

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

$schema = $engine->getSchema();
$primaryKey = $engine->getPrimaryKey();

$record = $id ? $engine->getRecord($id) : null;
$baseHref = BASE_URL . 'index.php';
?>

<!-- Summernote Assets -->
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <?php echo ($id ? 'Editar' : 'Novo') . ' Log de Envio'; ?>
    </h4>
    <a href="<?php echo $baseHref; ?>?m=mailling_envios&a=list" class="btn btn-outline-secondary btn-sm">Voltar</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="<?php echo $baseHref; ?>?m=mailling_envios&a=save" method="post">
            <?php if ($id): ?>
                <input type="hidden" name="<?php echo $primaryKey; ?>" value="<?php echo htmlspecialchars($id); ?>">
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">E-mail <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control"
                        value="<?php echo htmlspecialchars($record['email'] ?? ''); ?>" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Data de Envio <span class="text-danger">*</span></label>
                    <input type="datetime-local" name="enviado" class="form-control"
                        value="<?php echo $record ? date('Y-m-d\TH:i', strtotime($record['enviado'])) : date('Y-m-d\TH:i'); ?>"
                        required>
                </div>

                <div class="col-md-12">
                    <label class="form-label">Assunto/Título <span class="text-danger">*</span></label>
                    <input type="text" name="titulo" class="form-control"
                        value="<?php echo htmlspecialchars($record['titulo'] ?? ''); ?>" required>
                </div>

                <div class="col-md-12">
                    <label class="form-label">Mensagem <span class="text-danger">*</span></label>
                    <textarea name="mensagem" id="summernote-mensagem" class="form-control"
                        required><?php echo htmlspecialchars($record['mensagem'] ?? ''); ?></textarea>
                </div>
            </div>

            <div class="mt-4 pt-3 border-top">
                <button type="submit" class="btn btn-primary">Salvar Log</button>
                <a href="<?php echo $baseHref; ?>?m=mailling_envios&a=list"
                    class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof jQuery !== 'undefined' && typeof jQuery.fn.summernote !== 'undefined') {
            jQuery('#summernote-mensagem').summernote({
                placeholder: 'Conteúdo do e-mail...',
                tabsize: 2,
                height: 400,
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'underline', 'clear']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['table', ['table']],
                    ['insert', ['link', 'picture', 'video']],
                    ['view', ['fullscreen', 'codeview', 'help']]
                ],
                lang: 'pt-BR'
            });
        }
    });
</script>