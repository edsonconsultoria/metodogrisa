<?php
// app/helpers/bulk_actions.php

/**
 * Render the bulk action buttons (Delete, Update)
 * To be placed next to the "Novo" button in list views.
 */
function render_bulk_buttons()
{
    echo '
    <div class="btn-group" id="bulk-actions-group">
      <button type="button" class="btn btn-outline-danger btn-sm d-none" id="btn-bulk-delete">
        <i class="bi bi-trash"></i> Apagar selecionados
      </button>
      <button type="button" class="btn btn-outline-primary btn-sm d-none" id="btn-bulk-update">
        <i class="bi bi-pencil"></i> Alterar selecionados
      </button>
    </div>
    ';
}

/**
 * Render the generic bulk update modal based on the table schema.
 * 
 * @param string $module The module name (e.g., 'alunos')
 * @param string $tableName The true table name (e.g., '104_alunos')
 * @param array $schema The schema array from CrudEngine
 * @param array $lookups Optional lookups array for select fields
 */
function render_bulk_modal(string $module, string $tableName, array $schema, array $lookups = [])
{
    ?>
    <div class="modal fade" id="modalBulkUpdate" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Alterar Selecionados</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning small">
                        <i class="bi bi-info-circle"></i> Apenas os campos preenchidos serão atualizados nos registros
                        selecionados.
                    </div>
                    <form id="formBulkUpdate">
                        <input type="hidden" name="table" value="<?php echo htmlspecialchars($tableName); ?>">
                        <div class="row g-3">
                            <?php foreach ($schema as $col => $info):
                                if ($info['extra'] === 'auto_increment' || $col === 'id' || strpos($col, 'inclusao') !== false || strpos($col, 'alteracao') !== false) {
                                    continue;
                                }
                                $label = ucfirst(str_replace('_', ' ', $col));
                                $type = 'text';
                                if ($info['type'] === 'date' || $info['type'] === 'datetime') {
                                    $type = 'date';
                                }
                                ?>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label mb-1 small text-muted">
                                        <?php echo htmlspecialchars($label); ?>
                                    </label>
                                    <?php if (isset($lookups[$col])): ?>
                                        <select name="<?php echo htmlspecialchars($col); ?>" class="form-select form-select-sm">
                                            <option value="">(Não alterar)</option>
                                            <?php foreach ($lookups[$col] as $val => $text): ?>
                                                <option value="<?php echo htmlspecialchars($val); ?>">
                                                    <?php echo htmlspecialchars($text); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ($info['type'] === 'tinyint' && strpos(($info['length'] ?? ''), '1') !== false): ?>
                                        <select name="<?php echo htmlspecialchars($col); ?>" class="form-select form-select-sm">
                                            <option value="">(Não alterar)</option>
                                            <option value="1">Sim ✓</option>
                                            <option value="0">Não ✗</option>
                                        </select>
                                    <?php elseif ($info['type'] === 'text' || $info['type'] === 'mediumtext' || $info['type'] === 'longtext'): ?>
                                        <textarea name="<?php echo htmlspecialchars($col); ?>" class="form-control form-control-sm"
                                            rows="1" placeholder="(Não alterar)"></textarea>
                                    <?php else: ?>
                                        <input type="<?php echo $type; ?>" name="<?php echo htmlspecialchars($col); ?>"
                                            class="form-control form-control-sm" placeholder="(Não alterar)">
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btn-confirm-bulk-update">Atualizar Registros</button>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Render the JavaScript to handle checkbox changes and AJAX requests.
 * 
 * @param string $module The module name for URL building
 */
function render_bulk_js(string $module, string $tableName = '')
{
    $ajaxUrl = BASE_URL . 'index.php?m=generic&a=batch_action';
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const phpTableName = '<?php echo htmlspecialchars($tableName); ?>';
            const selectAll = document.getElementById('bulk-select-all');
            let checkboxes = document.querySelectorAll('.bulk-select-item');
            const btnDelete = document.getElementById('btn-bulk-delete');
            const btnUpdate = document.getElementById('btn-bulk-update');
            const modalBulkUpdateEl = document.getElementById('modalBulkUpdate');
            let modalBulkUpdate = null;
            if (modalBulkUpdateEl) {
                modalBulkUpdate = new bootstrap.Modal(modalBulkUpdateEl);
            }
            const btnConfirmUpdate = document.getElementById('btn-confirm-bulk-update');

            function updateButtons() {
                checkboxes = document.querySelectorAll('.bulk-select-item'); // Re-query in case of dynamic DOM
                const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
                if (checkedCount > 0) {
                    if (btnDelete) btnDelete.classList.remove('d-none');
                    if (btnUpdate) btnUpdate.classList.remove('d-none');
                } else {
                    if (btnDelete) btnDelete.classList.add('d-none');
                    if (btnUpdate) btnUpdate.classList.add('d-none');
                }
            }

            if (selectAll) {
                selectAll.addEventListener('change', function () {
                    checkboxes = document.querySelectorAll('.bulk-select-item');
                    checkboxes.forEach(cb => cb.checked = this.checked);
                    updateButtons();
                });
            }

            // Attach to body for event delegation in case rows are loaded dynamically
            document.body.addEventListener('change', function (e) {
                if (e.target.classList.contains('bulk-select-item')) {
                    updateButtons();
                    if (selectAll) {
                        checkboxes = document.querySelectorAll('.bulk-select-item');
                        const allChecked = Array.from(checkboxes).every(c => c.checked);
                        const anyUnchecked = Array.from(checkboxes).some(c => !c.checked);
                        selectAll.checked = allChecked;
                        selectAll.indeterminate = !allChecked && !anyUnchecked; // Optional: indeterminate state
                    }
                }
            });


            if (btnDelete) {
                btnDelete.addEventListener('click', function () {
                    if (!confirm('Tem certeza que deseja apagar os registros selecionados?')) return;

                    const formUpdate = document.getElementById('formBulkUpdate');
                    const tableName = phpTableName || (formUpdate ? formUpdate.querySelector('input[name="table"]').value : '');

                    const selectedIds = Array.from(document.querySelectorAll('.bulk-select-item:checked')).map(cb => cb.value);
                    const formData = new FormData();
                    formData.append('action', 'delete');
                    formData.append('table', tableName);
                    selectedIds.forEach(id => formData.append('ids[]', id));

                    fetch('<?php echo $ajaxUrl; ?>', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert(data.message);
                                window.location.reload();
                            } else {
                                alert('Erro: ' + data.message);
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            alert('Erro ao processar solicitação.');
                        });
                });
            }

            if (btnUpdate) {
                btnUpdate.addEventListener('click', function () {
                    if (modalBulkUpdate) modalBulkUpdate.show();
                });
            }

            if (btnConfirmUpdate) {
                btnConfirmUpdate.addEventListener('click', function () {
                    const form = document.getElementById('formBulkUpdate');
                    const tableName = phpTableName || (form ? form.querySelector('input[name="table"]').value : '');
                    const formData = new FormData(form);

                    formData.append('action', 'update');
                    formData.append('table', tableName);
                    const selectedIds = Array.from(document.querySelectorAll('.bulk-select-item:checked')).map(cb => cb.value);
                    selectedIds.forEach(id => formData.append('ids[]', id));

                    fetch('<?php echo $ajaxUrl; ?>', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert(data.message);
                                window.location.reload();
                            } else {
                                alert('Erro: ' + data.message);
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            alert('Erro ao processar solicitação.');
                        });
                });
            }
        });
    </script>
    <?php
}
