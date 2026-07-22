<?php
// app/modules/documentos_gerados/form.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/list_state.php';

$tableName = '104_documentos_gerados';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$engine = new CrudEngine($pdo, $tableName);

$record = $id ? $engine->getRecord($id) : null;

// Pré-selecionar modelo se passado via GET (útil para modais)
if (!$id && isset($_GET['modelo_id'])) {
    $record['modelo_id'] = (int) $_GET['modelo_id'];
}
$baseHref = BASE_URL . 'index.php';
$returnUrl = list_state_return_destination('documentos_gerados', 'list');
$returnParam = $_REQUEST['return'] ?? '';

// Carregar opções para os selects
$modelos = $pdo->query("SELECT id, nome FROM 104_documentos_modelos ORDER BY nome ASC")->fetchAll();
$pessoas = [];

// Carregar pessoa se passado via GET ou se for edição
$lookupPessoaId = $id ? ($record['pessoa_id'] ?? 0) : ($_GET['pessoa_id'] ?? 0);

if ($lookupPessoaId) {
    $stmt = $pdo->prepare("SELECT id, nome FROM 101_pessoas WHERE id = ?");
    $stmt->execute([$lookupPessoaId]);
    $p = $stmt->fetch();
    if ($p) {
        $pessoas[] = $p;
        if (!$id) {
            $record['pessoa_id'] = $p['id'];
        }
    }
}
$isModal = isset($_GET['modal']) && $_GET['modal'] == '1';
?>

<!-- Assets: jQuery e Select2 -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<?php if (!$isModal): ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><?php echo ($id ? 'Editar' : 'Novo') . ' Documento Gerado'; ?></h4>
        <a href="<?php echo $returnUrl; ?>" class="btn btn-outline-secondary btn-sm">Voltar</a>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="<?php echo $baseHref; ?>?m=documentos_gerados&a=save" method="post">
            <input type="hidden" name="return" value="<?php echo htmlspecialchars($returnParam); ?>">
            <?php if ($id): ?>
                <input type="hidden" name="id" value="<?php echo $id; ?>">
            <?php endif; ?>
            <?php if ($isModal): ?>
                <input type="hidden" name="modal" value="1">
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Modelo <span class="text-danger">*</span></label>
                    <select name="modelo_id" class="form-select select2-field" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($modelos as $m): ?>
                            <option value="<?php echo $m['id']; ?>" <?php echo (($record['modelo_id'] ?? '') == $m['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($m['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Pessoa <span class="text-danger">*</span></label>
                    <select name="pessoa_id" class="form-select select2-field" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($pessoas as $p): ?>
                            <option value="<?php echo $p['id']; ?>" selected>
                                <?php echo htmlspecialchars($p['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-12">
                    <label class="form-label">Título</label>
                    <input type="text" name="titulo" class="form-control"
                        value="<?php echo htmlspecialchars($record['titulo'] ?? ''); ?>"
                        placeholder="Deixe em branco para gerar automaticamente">
                    <div class="form-text small">Se vazio, será gerado: Modelo_Aluno_DataHora</div>
                </div>

                <div class="col-md-12">
                    <label class="form-label">Arquivo DOCX</label>
                    <input type="text" name="arquivo_docx" class="form-control"
                        value="<?php echo htmlspecialchars($record['arquivo_docx'] ?? ''); ?>"
                        placeholder="Deixe em branco para preenchimento automático">
                </div>
            </div>

            <div class="mt-4 pt-3 border-top">
                <button type="submit" class="btn btn-primary">Salvar Registro</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof jQuery !== 'undefined' && typeof jQuery.fn.select2 !== 'undefined') {
            jQuery('.select2-field').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Selecione uma opção'
            });

            jQuery('select[name="pessoa_id"]').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Buscar pessoa...',
                minimumInputLength: 2,
                ajax: {
                    url: '<?php echo BASE_URL; ?>app/modules/pessoas/ajax_search.php',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            q: params.term
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data.results
                        };
                    },
                    cache: true
                }
            });
        }
    });
</script>