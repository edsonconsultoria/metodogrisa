<?php
// app/modules/professores/form.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';
require_once __DIR__ . '/../../helpers/arquivos.php';

$tableName = '104_professores';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$engine = new CrudEngine($pdo, $tableName);

$record = $id ? $engine->getRecord($id) : null;
$schema = $engine->getSchema();
$primaryKey = $engine->getPrimaryKey();

// Lookups
$pessoasOptions = [];
if ($record && !empty($record['pessoa'])) {
    $stmtPessoa = $pdo->prepare("SELECT id, nome FROM 101_pessoas WHERE id = ?");
    $stmtPessoa->execute([$record['pessoa']]);
    $pessoasOptions = $stmtPessoa->fetchAll(PDO::FETCH_ASSOC);
}
$baseHref = BASE_URL . 'index.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <?php echo ($id ? 'Editar' : 'Novo') . ' ' . get_table_label($tableName); ?>
    </h4>
    <a href="<?php echo $baseHref; ?>?m=professores&a=list" class="btn btn-outline-secondary btn-sm">Voltar</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="<?php echo $baseHref; ?>?m=professores&a=save" method="post" enctype="multipart/form-data">
            <?php if ($id): ?>
                <input type="hidden" name="<?php echo $primaryKey; ?>" value="<?php echo htmlspecialchars($id); ?>">
            <?php endif; ?>

            <div class="row g-3">
                <!-- Pessoa -->
                <div class="col-md-6">
                    <label class="form-label">Pessoa <span class="text-danger">*</span></label>
                    <select name="pessoa" class="form-select select2-pessoa" required
                        data-placeholder="Selecione uma pessoa...">
                        <option value=""></option>
                        <?php foreach ($pessoasOptions as $opt): ?>
                            <option value="<?php echo $opt['id']; ?>" <?php echo ((string) ($record['pessoa'] ?? '') === (string) $opt['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($opt['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Apelido -->
                <div class="col-md-6">
                    <label class="form-label">Apelido <span class="text-danger">*</span></label>
                    <input type="text" name="apelido" class="form-control" required
                        value="<?php echo htmlspecialchars($record['apelido'] ?? ''); ?>">
                </div>

                <!-- Titulação -->
                <div class="col-md-12">
                    <label class="form-label">Titulação</label>
                    <input type="text" name="titulacao" class="form-control"
                        value="<?php echo htmlspecialchars($record['titulacao'] ?? ''); ?>">
                </div>

                <!-- Documentos -->
                <div class="col-md-12 mt-4">
                    <label class="form-label fw-bold">Documentos (Upload)</label>
                    <div id="drop-zone" class="border-dashed rounded-3 p-4 text-center bg-light mb-3"
                        style="border: 2px dashed #cbd5e0; transition: border-color .2s, background-color .2s; cursor: pointer;">
                        <i class="bi bi-cloud-arrow-up display-5 text-primary mb-2"></i>
                        <p class="mb-0 text-muted">Arraste documentos aqui ou clique para selecionar</p>
                        <span class="text-xs text-muted">PDF, DOCX, Imagens, ZIP, etc.</span>
                    </div>
                    <input type="file" name="novos_arquivos[]" id="file-input" class="d-none" multiple>

                    <div id="new-files-preview" class="small mb-3 d-flex flex-wrap gap-2"></div>

                    <input type="hidden" name="arquivos_json" id="arquivos-json"
                        value="<?php echo htmlspecialchars($record['documentos'] ?? '[]'); ?>">

                    <div class="label mb-2 small text-muted fw-bold">Arquivos Atuais:</div>
                    <div id="arquivos-lista"
                        class="list-group list-group-flush border rounded-3 overflow-hidden shadow-sm bg-white">
                        <!-- Preenchido via JS -->
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-top">
                <button type="submit" class="btn btn-primary">Salvar Professor</button>
                <a href="<?php echo $baseHref; ?>?m=professores&a=list" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
    $(document).ready(function () {
        $('.select2-pessoa').select2({
            theme: 'bootstrap-5',
            width: '100%',
            language: 'pt-BR',
            ajax: {
                url: 'index.php?m=pessoas&a=ajax_search',
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
            },
            minimumInputLength: 2
        });

        // --- Lógica de Upload de Documentos ---
        const dropZone = document.getElementById('drop-zone');
        const fileInput = document.getElementById('file-input');
        const preview = document.getElementById('new-files-preview');
        const listEl = document.getElementById('arquivos-lista');
        const jsonInput = document.getElementById('arquivos-json');

        if (dropZone && fileInput) {
            dropZone.addEventListener('click', () => fileInput.click());
            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropZone.style.borderColor = '#4299e1';
                dropZone.style.backgroundColor = '#edf2f7';
            });
            dropZone.addEventListener('dragleave', () => {
                dropZone.style.borderColor = '#cbd5e0';
                dropZone.style.backgroundColor = '#f8f9fa';
            });
            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.style.borderColor = '#cbd5e0';
                dropZone.style.backgroundColor = '#f8f9fa';
                fileInput.files = e.dataTransfer.files;
                updateNewFilesPreview();
            });
            fileInput.addEventListener('change', updateNewFilesPreview);
        }

        function updateNewFilesPreview() {
            preview.innerHTML = '';
            Array.from(fileInput.files).forEach(f => {
                const badge = document.createElement('span');
                badge.className = 'badge bg-primary d-flex align-items-center gap-1 p-2';
                badge.innerHTML = `<i class="bi bi-file-earmark-plus"></i> ${f.name} <i class="bi bi-check-circle-fill ms-1"></i>`;
                preview.appendChild(badge);
            });
        }

        window.removeFile = function (btn) {
            if (confirm('Deseja remover este arquivo?')) {
                btn.closest('.file-item').remove();
                updateArquivosState();
            }
        };

        function updateArquivosState() {
            const items = [];
            document.querySelectorAll('.file-item').forEach(item => {
                items.push({
                    name: item.dataset.name,
                    file: item.dataset.file,
                    size: parseInt(item.dataset.size),
                    type: item.dataset.type
                });
            });
            jsonInput.value = JSON.stringify(items);
        }

        const renderArquivos = (jsonStr) => {
            let files = [];
            try { files = JSON.parse(jsonStr || '[]'); } catch (e) { }
            if (!Array.isArray(files)) files = [];

            listEl.innerHTML = '';
            if (files.length === 0) {
                listEl.innerHTML = '<div class="p-3 text-center text-muted small">Nenhum documento anexado</div>';
                return;
            }

            files.forEach(f => {
                const item = document.createElement('div');
                item.className = 'list-group-item file-item d-flex align-items-center p-2';
                item.dataset.name = f.name;
                item.dataset.file = f.file;
                item.dataset.size = f.size;
                item.dataset.type = f.type || '';

                item.innerHTML = `
                    <div class="flex-grow-1 text-truncate" title="${f.name}">
                        <i class="bi bi-file-earmark me-1 text-primary"></i>
                        <span class="small fw-semibold">${f.name}</span>
                    </div>
                    <button type="button" class="btn btn-link text-danger btn-sm p-0 ms-2" onclick="removeFile(this)">
                        <i class="bi bi-x-circle"></i>
                    </button>
                `;
                listEl.appendChild(item);
            });
        };

        renderArquivos(jsonInput.value);
    });
</script>