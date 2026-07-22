<?php
// app/modules/documentos_gerados/gerar_lote.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/list_state.php';

$baseHref = BASE_URL . 'index.php';
$returnUrl = list_state_return_destination('documentos_gerados', 'list');
$returnParam = $_REQUEST['return'] ?? '';

// Carregar modelos de documentos disponíveis
$modelos = $pdo->query("SELECT id, nome FROM 104_documentos_modelos ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

// Carregar turmas disponíveis
$turmas = $pdo->query("SELECT id, nome FROM 104_turmas ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Assets: jQuery e Select2 -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Gerar Documentos em Lote</h4>
    <a href="<?php echo $returnUrl; ?>" class="btn btn-outline-secondary btn-sm">Voltar</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <!-- Alerta de feedback local -->
        <div id="feedback-alert" class="alert alert-danger d-none" role="alert">
            <span id="feedback-message"></span>
        </div>

        <form id="form-gerar-lote" method="post" novalidate>
            <input type="hidden" name="return" value="<?php echo htmlspecialchars($returnParam); ?>">

            <div class="row g-3">
                <!-- Modelo de Documento -->
                <div class="col-md-6">
                    <label class="form-label fw-bold">Modelo de Documento <span class="text-danger">*</span></label>
                    <select name="modelo_id" class="form-select select2-field" required>
                        <option value="">Selecione o Modelo...</option>
                        <?php foreach ($modelos as $m): ?>
                            <option value="<?php echo $m['id']; ?>">
                                <?php echo htmlspecialchars($m['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">Por favor, selecione um modelo de documento.</div>
                </div>

                <!-- Turma -->
                <div class="col-md-6">
                    <label class="form-label fw-bold">Turma <span class="text-danger">*</span></label>
                    <select name="turma_id" class="form-select select2-field" required>
                        <option value="">Selecione a Turma...</option>
                        <?php foreach ($turmas as $t): ?>
                            <option value="<?php echo $t['id']; ?>">
                                <?php echo htmlspecialchars($t['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">Por favor, selecione uma turma.</div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-top d-flex align-items-center gap-2">
                <button type="submit" class="btn btn-primary" id="btn-submit">
                    <span id="btn-text"><i class="bi bi-gear-fill"></i> Confirmar Geração</span>
                    <span id="btn-spinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                </button>
                <a href="<?php echo $returnUrl; ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Inicializar Select2 nos campos
        if (typeof jQuery !== 'undefined' && typeof jQuery.fn.select2 !== 'undefined') {
            jQuery('.select2-field').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Selecione uma opção',
                allowClear: true
            });
        }

        const form = document.getElementById('form-gerar-lote');
        const btnSubmit = document.getElementById('btn-submit');
        const btnText = document.getElementById('btn-text');
        const btnSpinner = document.getElementById('btn-spinner');
        const alertBox = document.getElementById('feedback-alert');
        const alertMsg = document.getElementById('feedback-message');

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            alertBox.classList.add('d-none');

            // Validação simples dos selects
            const modeloId = form.modelo_id.value;
            const turmaId = form.turma_id.value;

            if (!modeloId || !turmaId) {
                alertBox.classList.remove('d-none');
                alertMsg.innerHTML = '<strong>Atenção:</strong> Por favor, selecione tanto o modelo quanto a turma antes de confirmar.';
                return;
            }

            if (!confirm('Deseja realmente gerar os documentos de todos os alunos matriculados nesta turma? O processamento pode levar alguns segundos.')) {
                return;
            }

            // Desabilitar botões e exibir loading spinner
            btnSubmit.disabled = true;
            btnText.classList.add('d-none');
            btnSpinner.classList.remove('d-none');

            const payload = new URLSearchParams({
                modelo_id: modeloId,
                turma_id: turmaId
            });

            // Enviar requisição AJAX para processamento do lote
            fetch('<?php echo BASE_URL; ?>app/modules/documentos_gerados/ajax_generate_turma.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: payload.toString()
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erro na resposta do servidor.');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Redirecionar com list_state para a listagem contendo mensagem de sucesso
                    const count = data.count || 0;
                    const redirectUrl = '<?php echo list_state_return_destination('documentos_gerados', 'list', ['msg' => 'lote_success']); ?>' + '&count=' + count;
                    window.location.href = redirectUrl;
                } else {
                    // Tratar falha retornada pela lógica de negócio
                    throw new Error(data.error || 'Ocorreu um erro desconhecido.');
                }
            })
            .catch(error => {
                // Restaurar botões e exibir erro amigável
                btnSubmit.disabled = false;
                btnText.classList.remove('d-none');
                btnSpinner.classList.add('d-none');

                alertBox.classList.remove('d-none');
                alertMsg.innerHTML = '<strong>Erro na geração em lote:</strong> ' + error.message;
            });
        });
    });
</script>
