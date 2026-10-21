<?php
// prevenção a erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// app/modules/alunos/form.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';
require_once __DIR__ . '/../../helpers/list_state.php';
require_once __DIR__ . '/../../helpers/bulk_actions.php';

$tableName = '104_alunos';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$engine = new CrudEngine($pdo, $tableName);

// Lookups
$turmasOptions = $pdo->query("SELECT id, nome FROM 104_turmas ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$pessoasOptions = $pdo->query("SELECT id, nome FROM 101_pessoas ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

$schema = $engine->getSchema();
$primaryKey = $engine->getPrimaryKey();

$record = $id ? $engine->getRecord($id) : null;

// Pre-select turma from GET if not editing
if (!$id && isset($_GET['turma'])) {
    $record['turma'] = (int) $_GET['turma'];
}

$baseHref = BASE_URL . 'index.php';
$returnUrl = list_state_return_destination('alunos', 'list');
$returnParam = $_REQUEST['return'] ?? '';

// Opções de diploma
$diplomaOptions = [
    'Pós Graduação' => 'Pós Graduação',
    'Curso Livre' => 'Curso Livre',
];

// Checkbox fields
$checkboxFields = [
    'rg_carteira_conselho',
    'cpf',
    'cer_nascimento_casamento',
    'comprovante_residencia',
    'fge',
    'tcc',
    'uso_imagem',
    'cert_grad',
    'emitido_cert_metodo_grisa',
    'emitido_cert_universidade'
];

// Notas do aluno (somente ao editar)
$notas = [];
if ($id > 0) {
    $stmtNotas = $pdo->prepare("
        SELECT tn.id, d.id AS disciplina_id, d.nome AS disciplina_nome, t.nome AS turma_nome, tn.nota
        FROM 104_turmas_notas tn
        LEFT JOIN 104_disciplinas d ON tn.disciplina = d.id
        LEFT JOIN 104_turmas t ON tn.turma = t.id
        WHERE tn.aluno = :aluno_id
        ORDER BY d.id ASC
    ");
    $stmtNotas->execute([':aluno_id' => $id]);
    $notas = $stmtNotas->fetchAll(PDO::FETCH_ASSOC);

    // Schema for bulk actions on grades
    $notasEngine = new CrudEngine($pdo, '104_turmas_notas');
    $notasSchema = $notasEngine->getSchema();
    $notasTableName = '104_turmas_notas';
}

$activeTab = $_GET['tab'] ?? 'dados';
$isIframe = isset($_GET['iframe']) && $_GET['iframe'] == 1;
?>
<style>
    .dropzone-container {
        border: 2px dashed #dee2e6;
        border-radius: 0.5rem;
        transition: all 0.2s ease-in-out;
        background-color: #f8f9fa;
        min-height: 100px;
        position: relative;
    }

    .dropzone-container.dragover {
        border-color: #0d6efd;
        background-color: #e9ecef;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }

    .dropzone-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        display: none;
        align-items: center;
        justify-content: center;
        background: rgba(13, 110, 253, 0.1);
        z-index: 10;
        pointer-events: none;
    }

    .dropzone-container.dragover .dropzone-overlay {
        display: flex;
    }

    .file-item {
        transition: background-color 0.2s;
    }

    .file-item:hover {
        background-color: #f1f3f5 !important;
    }

    #list-documentos_recebidos {
        max-height: 800px;
        overflow-y: auto;
        background: #f8f9fa;
    }

    #list-documentos_enviados {
        max-height: 200px;
        overflow-y: auto;
        background: #f8f9fa;
    }
</style>

<?php if (!$isIframe): ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <?php echo ($id ? 'Editar' : 'Novo') . ' ' . get_table_label($tableName); ?>
    </h4>
    <a href="<?php echo $returnUrl; ?>" class="btn btn-outline-secondary btn-sm">Voltar</a>
</div>
<?php endif; ?>

<?php if ($id > 0): ?>
    <ul class="nav nav-tabs mb-3" id="alunoFormTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link <?php echo $activeTab === 'dados' ? 'active' : ''; ?>" id="dados-tab"
                data-bs-toggle="tab" data-bs-target="#dados" type="button" role="tab">
                Dados
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link <?php echo $activeTab === 'notas' ? 'active' : ''; ?>" id="notas-tab"
                data-bs-toggle="tab" data-bs-target="#notas" type="button" role="tab">
                Notas (<?php echo count($notas); ?>)
            </button>
        </li>
    </ul>
    <div class="tab-content">
        <div class="tab-pane fade <?php echo $activeTab === 'dados' ? 'show active' : ''; ?>" id="dados" role="tabpanel">
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-body">
                <form action="<?php echo $baseHref; ?>?m=alunos&a=save" method="post">
                    <?php if ($id): ?>
                        <input type="hidden" name="<?php echo $primaryKey; ?>" value="<?php echo htmlspecialchars($id); ?>">
                    <?php endif; ?>
                    <input type="hidden" name="return" value="<?php echo htmlspecialchars($returnParam); ?>">

                    <div class="row g-3">
                        <!-- Pessoa (Aluno) -->
                        <div class="col-md-6">
                            <label class="form-label">Pessoa (Nome) <span class="text-danger">*</span></label>
                            <select name="aluno" class="form-select select2-aluno" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($pessoasOptions as $opt): ?>
                                    <option value="<?php echo $opt['id']; ?>" <?php echo ((string) ($record['aluno'] ?? '') === (string) $opt['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($opt['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <script>
                            document.addEventListener('DOMContentLoaded', function () {
                                $('.select2-aluno').select2({
                                    theme: 'bootstrap-5',
                                    placeholder: 'Selecione um aluno...',
                                    allowClear: true,
                                    width: '100%'
                                });
                            });
                        </script>

                        <!-- Turma -->
                        <div class="col-md-6">
                            <label class="form-label">Turma</label>
                            <select name="turma" class="form-select">
                                <option value="">Selecione...</option>
                                <?php foreach ($turmasOptions as $opt): ?>
                                    <option value="<?php echo $opt['id']; ?>" <?php echo ((string) ($record['turma'] ?? '') === (string) $opt['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($opt['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Status e Tipo -->
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <input type="text" name="status" class="form-control"
                                value="<?php echo htmlspecialchars($record['status'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tipo</label>
                            <input type="text" name="tipo" class="form-control"
                                value="<?php echo htmlspecialchars($record['tipo'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Título TCC</label>
                            <input type="text" name="titulo_tcc" class="form-control"
                                value="<?php echo htmlspecialchars($record['titulo_tcc'] ?? ''); ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Diploma <span class="text-danger">*</span></label>
                            <select name="diploma" class="form-select" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($diplomaOptions as $value => $label): ?>
                                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo (($record['diploma'] ?? '') === $value) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Documentos (Checkboxes) -->
                        <div class="col-12 mt-4">
                            <h5 class="border-bottom pb-2">Documentação</h5>
                            <div class="row g-2">
                                <?php foreach ($checkboxFields as $field): ?>
                                    <div class="col-md-4 col-lg-3">
                                        <div class="form-check">
                                            <input type="hidden" name="<?php echo $field; ?>" value="0">
                                            <input class="form-check-input" type="checkbox" id="check_<?php echo $field; ?>"
                                                name="<?php echo $field; ?>" value="1" <?php echo ($record[$field] ?? '') == '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="check_<?php echo $field; ?>">
                                                <?php
                                                $label = ucfirst(str_replace('_', ' ', $field));
                                                if ($field === 'emitido_cert_metodo_grisa')
                                                    $label = 'Cert MG';
                                                if ($field === 'emitido_cert_universidade')
                                                    $label = 'Cert Uni';
                                                if ($field === 'cert_grad')
                                                    $label = 'Cert Grad';
                                                if ($field === 'uso_imagem')
                                                    $label = 'Uso de Imagem';
                                                if ($field === 'fge')
                                                    $label = 'FGE';
                                                echo htmlspecialchars($label);
                                                ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Textareas and Multi-Upload Visualization -->
                        <?php
                        /**
                         * Renders a list of files from a JSON string used by the multi-upload system.
                         */
                        function render_json_documents($jsonString, $fieldName)
                        {
                            if (empty($jsonString))
                                return '<div id="list-' . $fieldName . '"></div>';

                            $data = json_decode($jsonString, true);
                            if (!is_array($data))
                                return '<div id="list-' . $fieldName . '"></div>';

                            $html = '<div id="list-' . $fieldName . '" class="list-group list-group-flush mb-2 border rounded p-1">';
                            foreach ($data as $index => $file) {
                                $name = $file['name'] ?? '';
                                $usrName = $file['usrName'] ?? 'Arquivo';
                                $size = isset($file['size']) ? round($file['size'] / 1024, 1) . ' KB' : '';
                                $url = BASE_URL . $name;

                                $html .= '<div class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 bg-transparent file-item" data-index="' . $index . '">';
                                $html .= '  <div class="d-flex align-items-center overflow-hidden">';
                                $html .= '    <i class="bi bi-file-earmark-pdf text-danger me-2 fs-5"></i>';
                                $html .= '    <div class="text-truncate">';
                                $html .= '      <a href="' . htmlspecialchars($url) . '" target="_blank" class="text-decoration-none fw-medium text-dark" title="' . htmlspecialchars($usrName) . '">';
                                $html .= htmlspecialchars($usrName);
                                $html .= '      </a>';
                                if ($size) {
                                    $html .= '  <small class="text-muted d-block" style="font-size: 0.75rem;">' . $size . '</small>';
                                }
                                $html .= '    </div>';
                                $html .= '  </div>';
                                $html .= '  <div class="d-flex gap-1">';
                                $html .= '    <a href="' . htmlspecialchars($url) . '" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2" title="Abrir"><i class="bi bi-box-arrow-up-right"></i></a>';
                                $html .= '    <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="removeDoc(\'' . $fieldName . '\', ' . $index . ')" title="Remover"><i class="bi bi-trash"></i></button>';
                                $html .= '  </div>';
                                $html .= '</div>';
                            }
                            $html .= '</div>';
                            return $html;
                        }
                        ?>

                        <div class="col-12">
                            <label class="form-label d-flex justify-content-between">
                                Documentos Recebidos
                                <div class="d-flex gap-3">
                                    <button type="button" class="btn btn-link p-0 text-decoration-none"
                                        onclick="triggerUpload('documentos_recebidos')">+ Novo Documento</button>
                                    <button type="button" class="btn btn-link p-0 text-decoration-none text-danger"
                                        style="font-size: 0.8rem;" onclick="removeAllDocs('documentos_recebidos')"><i
                                            class="bi bi-trash"></i> Limpar Tudo</button>
                                </div>
                            </label>
                            <div class="dropzone-container p-2" id="dropzone-documentos_recebidos">
                                <div class="dropzone-overlay">
                                    <div class="text-primary fw-bold"><i class="bi bi-cloud-arrow-up fs-3"></i> Solte
                                        para carregar</div>
                                </div>
                                <input type="file" id="upload-documentos_recebidos" style="display: none;" multiple
                                    onchange="handleFiles(this.files, 'documentos_recebidos')">
                                <?php echo render_json_documents($record['documentos_recebidos'] ?? '', 'documentos_recebidos'); ?>
                            </div>
                            <textarea id="textarea-documentos_recebidos" name="documentos_recebidos"
                                style="display: none;"><?php echo htmlspecialchars($record['documentos_recebidos'] ?? ''); ?></textarea>

                        </div>
                        <div class="col-12">
                            <label class="form-label d-flex justify-content-between">
                                Documentos Enviados
                                <div class="d-flex gap-3">
                                    <button type="button" class="btn btn-link p-0 text-decoration-none"
                                        onclick="triggerUpload('documentos_enviados')">+ Novo Documento</button>
                                    <button type="button" class="btn btn-link p-0 text-decoration-none text-danger"
                                        style="font-size: 0.8rem;" onclick="removeAllDocs('documentos_enviados')"><i
                                            class="bi bi-trash"></i> Limpar Tudo</button>
                                </div>
                            </label>
                            <div class="dropzone-container p-2" id="dropzone-documentos_enviados">
                                <div class="dropzone-overlay">
                                    <div class="text-primary fw-bold"><i class="bi bi-cloud-arrow-up fs-3"></i> Solte
                                        para carregar</div>
                                </div>
                                <input type="file" id="upload-documentos_enviados" style="display: none;" multiple
                                    onchange="handleFiles(this.files, 'documentos_enviados')">
                                <?php echo render_json_documents($record['documentos_enviados'] ?? '', 'documentos_enviados'); ?>
                            </div>
                            <textarea id="textarea-documentos_enviados" name="documentos_enviados"
                                style="display: none;"><?php echo htmlspecialchars($record['documentos_enviados'] ?? ''); ?></textarea>

                        </div>

                        <div class="col-12">
                            <label class="form-label">Observações</label>
                            <textarea name="obs" class="form-control"
                                rows="3"><?php echo htmlspecialchars($record['obs'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Timestamps -->
                    <?php if ($id): ?>
                        <div class="col-12 mt-4 pt-3 border-top">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small fw-bold">Data de Inclusão</label>
                                    <div class="form-control form-control-sm bg-light">
                                        <?php echo !empty($record['data_inclusao']) ? date('d/m/Y H:i:s', strtotime($record['data_inclusao'])) : '—'; ?>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small fw-bold">Última Alteração</label>
                                    <div class="form-control form-control-sm bg-light">
                                        <?php echo !empty($record['data_alteracao']) ? date('d/m/Y H:i:s', strtotime($record['data_alteracao'])) : '—'; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="mt-4 pt-3 border-top">
                        <button type="submit" class="btn btn-primary">Salvar Aluno</button>
                        <?php if (!$isIframe): ?>
                            <a href="<?php echo $returnUrl; ?>" class="btn btn-outline-secondary">Cancelar</a>
                        <?php endif; ?>
                    </div>

                </form>
            </div>
        </div>

        <?php if ($id > 0): ?>
        </div><!-- /#dados tab-pane -->

        <!-- ABA NOTAS -->
        <div class="tab-pane fade <?php echo $activeTab === 'notas' ? 'show active' : ''; ?>" id="notas" role="tabpanel">
            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-3">
                        <span class="fw-bold">Notas</span>
                        <?php render_bulk_buttons(); ?>
                    </div>
                    <a href="<?php echo $baseHref; ?>?m=turmas_notas&a=form&aluno=<?php echo $id; ?>&return=<?php echo urlencode('m=alunos&a=form&id=' . $id . '&tab=notas'); ?>"
                        class="btn btn-primary btn-sm">+ Nova Nota</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 40px;" class="text-center">
                                        <input type="checkbox" class="form-check-input" id="bulk-select-all">
                                    </th>
                                    <th style="width: 100px;">Ações</th>
                                    <th style="width: 80px;">ID</th>
                                    <th>Disciplina</th>
                                    <th>Turma</th>
                                    <th>Nota</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($notas)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">Nenhuma nota registrada.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($notas as $nota): ?>
                                        <tr>
                                            <td class="text-center">
                                                <input type="checkbox" class="form-check-input bulk-select-item"
                                                    value="<?php echo (int) $nota['id']; ?>">
                                            </td>
                                            <td>
                                                <a href="<?php echo $baseHref; ?>?m=turmas_notas&a=form&id=<?php echo $nota['id']; ?>&return=<?php echo urlencode('m=alunos&a=form&id=' . $id . '&tab=notas'); ?>"
                                                    class="action-btn action-btn--editar" title="Editar"><i
                                                        class="bi bi-pencil"></i></a>
                                                <a href="<?php echo $baseHref; ?>?m=turmas_notas&a=delete&id=<?php echo $nota['id']; ?>&return=<?php echo urlencode('m=alunos&a=form&id=' . $id . '&tab=notas'); ?>"
                                                    class="action-btn action-btn--excluir" title="Excluir"
                                                    onclick="return confirm('Tem certeza que deseja excluir esta nota?')"><i
                                                        class="bi bi-trash"></i></a>
                                            </td>
                                            <td class="text-muted small"><?php echo $nota['disciplina_id']; ?></td>
                                            <td><?php echo htmlspecialchars($nota['disciplina_nome'] ?? '—'); ?></td>
                                            <td><?php echo htmlspecialchars($nota['turma_nome'] ?? '—'); ?></td>
                                            <td class="fw-semibold"><?php echo htmlspecialchars($nota['nota'] ?? '—'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div><!-- /#notas tab-pane -->
    </div><!-- /.tab-content -->

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var tabButtons = document.querySelectorAll('#alunoFormTabs button[data-bs-toggle="tab"]');
            tabButtons.forEach(function (button) {
                button.addEventListener('shown.bs.tab', function (event) {
                    var tabId = event.target.getAttribute('data-bs-target').replace('#', '');
                    var url = new URL(window.location);
                    url.searchParams.set('tab', tabId);
                    window.history.replaceState({}, '', url);
                });
            });
        });
    </script>
<?php endif; ?>

<script>
    function triggerUpload(fieldName) {
        document.getElementById('upload-' + fieldName).click();
    }

    function handleFiles(files, fieldName) {
        if (!files || files.length === 0) return;

        const btn = document.querySelector(`button[onclick="triggerUpload('${fieldName}')"]`);
        const originalText = btn.innerHTML;

        let uploadCount = 0;
        const totalFiles = files.length;
        const errors = []; // Initialize errors array

        btn.innerHTML = `<span class="spinner-border spinner-border-sm"></span> ${totalFiles} env.`;
        btn.disabled = true;

        const uploadPromises = Array.from(files).map(file => {
            const formData = new FormData();
            formData.append('file', file);

            return fetch('<?php echo $baseHref; ?>?m=alunos&a=ajax_upload', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        addDocToJson(fieldName, data.file);
                        uploadCount++;
                    } else {
                        errors.push(`${file.name}: ${data.message || 'Erro desconhecido'}`);
                    }
                })
                .catch(error => {
                    errors.push(`${file.name}: Erro de rede ou servidor.`);
                });
        });

        Promise.all(uploadPromises).then(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
            document.getElementById('upload-' + fieldName).value = ''; // Reset input
            if (errors.length > 0) {
                alert(`Alguns arquivos falharam no envio:\n\n${errors.join('\n')}`);
            }
        });
    }

    // Drag and Drop Initialization
    document.addEventListener('DOMContentLoaded', function () {
        const fields = ['documentos_recebidos', 'documentos_enviados'];
        fields.forEach(field => {
            const dz = document.getElementById('dropzone-' + field);
            if (!dz) return;

            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dz.addEventListener(eventName, e => {
                    e.preventDefault();
                    e.stopPropagation();
                }, false);
            });

            ['dragenter', 'dragover'].forEach(eventName => {
                dz.addEventListener(eventName, () => dz.classList.add('dragover'), false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dz.addEventListener(eventName, () => dz.classList.remove('dragover'), false);
            });

            dz.addEventListener('drop', e => {
                const dt = e.dataTransfer;
                const files = dt.files;
                handleFiles(files, field);
            }, false);
        });
    });


    function addDocToJson(fieldName, fileObj) {
        const textarea = document.getElementById('textarea-' + fieldName);
        let currentData = [];
        try {
            currentData = textarea.value.trim() ? JSON.parse(textarea.value) : [];
        } catch (e) {
            console.error('JSON Parse Error:', e);
            currentData = [];
        }

        if (!Array.isArray(currentData)) currentData = [];
        currentData.push(fileObj);
        textarea.value = JSON.stringify(currentData);

        // Refresh list UI
        refreshDocList(fieldName, currentData);
    }

    function removeDoc(fieldName, index) {
        if (!confirm('Deseja remover este documento?')) return;

        const textarea = document.getElementById('textarea-' + fieldName);
        let currentData = [];
        try {
            currentData = JSON.parse(textarea.value);
        } catch (e) { return; }

        currentData.splice(index, 1);
        textarea.value = currentData.length > 0 ? JSON.stringify(currentData) : '';

        refreshDocList(fieldName, currentData);
    }

    function removeAllDocs(fieldName) {
        const textarea = document.getElementById('textarea-' + fieldName);
        if (!textarea.value || textarea.value === '[]') return;

        if (!confirm('Deseja remover TODOS os documentos desta lista?')) return;

        textarea.value = '';
        refreshDocList(fieldName, []);
    }

    function refreshDocList(fieldName, data) {
        const container = document.getElementById('list-' + fieldName);
        if (!data || data.length === 0) {
            container.innerHTML = '';
            container.classList.remove('list-group', 'border');
            return;
        }

        container.classList.add('list-group', 'list-group-flush', 'mb-2', 'border', 'rounded', 'p-1');
        let html = '';
        data.forEach((file, index) => {
            const name = file.name || '';
            const usrName = file.usrName || 'Arquivo';
            const size = file.size ? Math.round(file.size / 1024 * 10) / 10 + ' KB' : '';
            const url = '<?php echo BASE_URL; ?>' + name;

            html += `<div class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 bg-transparent file-item" data-index="${index}">
            <div class="d-flex align-items-center overflow-hidden">
                <i class="bi bi-file-earmark-pdf text-danger me-2 fs-5"></i>
                <div class="text-truncate">
                    <a href="${url}" target="_blank" class="text-decoration-none fw-medium text-dark" title="${usrName}">${usrName}</a>
                    ${size ? `<small class="text-muted d-block" style="font-size: 0.75rem;">${size}</small>` : ''}
                </div>
            </div>
            <div class="d-flex gap-1">
                <a href="${url}" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2" title="Abrir"><i class="bi bi-box-arrow-up-right"></i></a>
                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="removeDoc('${fieldName}', ${index})" title="Remover"><i class="bi bi-trash"></i></button>
            </div>
        </div>`;
        });
        container.innerHTML = html;
    }
</script>

<?php
if ($id > 0) {
    render_bulk_modal('turmas_notas', $notasTableName, $notasSchema, $notasEngine->getLookupsOptions());
    render_bulk_js('turmas_notas', $notasTableName);
}
?>