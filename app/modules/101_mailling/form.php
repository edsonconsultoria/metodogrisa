<?php
// app/modules/101_mailling/form.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';

$tableName = '101_mailling';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$engine = new CrudEngine($pdo, $tableName);

// Lookups para dropdowns
$stmt = $pdo->query("SELECT id, nome_da_lista FROM 101_mailling_lista ORDER BY nome_da_lista");
$destinatariosOptions = $stmt->fetchAll();

$schema = $engine->getSchema();
$primaryKey = $engine->getPrimaryKey();

$record = $id ? $engine->getRecord($id) : null;
$baseHref = BASE_URL . 'index.php';
?>

<!-- Assets para bibliotecas externas -->
<!-- Select2 -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- Summernote -->
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <?php echo ($id ? 'Editar' : 'Novo') . ' ' . get_table_label($tableName); ?>
    </h4>
    <a href="<?php echo $baseHref; ?>?m=<?php echo $tableName; ?>&a=list"
        class="btn btn-outline-secondary btn-sm">Voltar</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="<?php echo $baseHref; ?>?m=<?php echo $tableName; ?>&a=save" method="post"
            enctype="multipart/form-data">
            <?php if ($id): ?>
                <input type="hidden" name="<?php echo $primaryKey; ?>" value="<?php echo htmlspecialchars($id); ?>">
            <?php endif; ?>

            <div class="row g-3">
                <!-- Data -->
                <div class="col-md-4">
                    <label class="form-label">Data <span class="text-danger">*</span></label>
                    <input type="date" name="data" class="form-control"
                        value="<?php echo htmlspecialchars($record['data'] ?? date('Y-m-d')); ?>" required>
                </div>

                <!-- Destinatários -->
                <div class="col-md-8">
                    <label class="form-label">Lista de Destinatários <span class="text-danger">*</span></label>
                    <select name="destinatarios" class="form-select select2-field" required>
                        <option value="">Selecione a lista...</option>
                        <?php foreach ($destinatariosOptions as $opt): ?>
                            <option value="<?php echo $opt['id']; ?>" <?php echo ((string) ($record['destinatarios'] ?? '') === (string) $opt['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($opt['nome_da_lista']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Assunto -->
                <div class="col-md-12">
                    <label class="form-label">Assunto <span class="text-danger">*</span></label>
                    <input type="text" name="assunto" class="form-control"
                        value="<?php echo htmlspecialchars($record['assunto'] ?? ''); ?>" required>
                </div>

                <!-- Mensagem (HTML Editor) -->
                <div class="col-md-12">
                    <label class="form-label">Mensagem <span class="text-danger">*</span></label>
                    <textarea name="mensagem" id="summernote-mensagem" class="form-control"
                        required><?php echo htmlspecialchars($record['mensagem'] ?? ''); ?></textarea>
                </div>

                <!-- Anexo (Multiple Upload) -->
                <div class="col-md-12">
                    <label class="form-label">Anexos</label>
                    <input type="file" name="anexo[]" class="form-control" multiple>
                    <div class="form-text">Você pode selecionar vários arquivos de uma vez.</div>
                    <?php if (!empty($record['anexo'])): ?>
                        <div class="mt-2 small">
                            <strong>Arquivos atuais:</strong>
                            <ul class="mb-0">
                                <?php foreach (explode(',', $record['anexo']) as $file): ?>
                                    <li>
                                        <?php echo htmlspecialchars(basename($file)); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mt-4 pt-3 border-top">
                <button type="submit" class="btn btn-primary">Salvar
                    <?php echo get_table_label($tableName); ?>
                </button>
                <a href="<?php echo $baseHref; ?>?m=<?php echo $tableName; ?>&a=list"
                    class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Inicializa Select2
        if (typeof jQuery !== 'undefined' && typeof jQuery.fn.select2 !== 'undefined') {
            jQuery('.select2-field').select2({
                theme: 'bootstrap-5',
                width: '100%'
            });
        }

        // Inicializa Summernote
        if (typeof jQuery !== 'undefined' && typeof jQuery.fn.summernote !== 'undefined') {
            jQuery('#summernote-mensagem').summernote({
                placeholder: 'Escreva sua mensagem aqui...',
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