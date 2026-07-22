<?php
// app/modules/generic/form.php

require_once __DIR__ . '/../../core/CrudEngine.php';

$tableName = $_TABLE ?? $_GET['m'] ?? '';
$id = $_GET['id'] ?? null;

if (!$tableName) {
    echo '<div class="alert alert-danger">Tabela não especificada.</div>';
    return;
}

$engine = new CrudEngine($pdo, $tableName);

if (isset($_LOOKUPS) && is_array($_LOOKUPS)) {
    $engine->setLookups($_LOOKUPS);
}

if (strpos($tableName, '102_') === 0 && !current_user_is_at_least_admin()) {
    echo '<div class="alert alert-danger mt-3">Acesso negado. Apenas administradores podem acessar os dados financeiros.</div>';
    include __DIR__ . '/../../templates/footer.php';
    exit;
}

// Lookups para dropdowns
$lookupsData = $lookupsData ?? [];
if (isset($_LOOKUPS) && is_array($_LOOKUPS)) {
    $opts = $engine->getLookupsOptions();
    foreach ($opts as $field => $items) {
        if (isset($lookupsData[$field])) continue;
        foreach ($items as $id_opt => $label_opt) {
            $lookupsData[$field][] = ['id' => $id_opt, 'nome' => $label_opt];
        }
    }
}

$schema = $engine->getSchema();
$primaryKey = $engine->getPrimaryKey();

$record = $id ? $engine->getRecord($id) : null;
$baseHref = BASE_URL . 'index.php';

// Cálculo do redirecionamento para manter estado (filtros, paginação, etc)
$redirectTo = $_GET['redirect_to'] ?? '';
if (!$redirectTo) {
    $params = $_GET;
    unset($params['a'], $params['id']); // Remove ação atual e id
    $params['a'] = 'list';
    $redirectTo = "index.php?" . http_build_query($params);
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <?php echo ($id ? 'Editar' : 'Novo') . ' ' . get_table_label($tableName); ?>
    </h4>
    <a href="<?php echo $redirectTo; ?>"
        class="btn btn-outline-secondary btn-sm">Voltar</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="<?php echo $baseHref; ?>?m=<?php echo $tableName; ?>&a=save" method="post"
            enctype="multipart/form-data">
            <?php if ($id): ?>
                <input type="hidden" name="<?php echo $primaryKey; ?>" value="<?php echo htmlspecialchars($id); ?>">
            <?php endif; ?>
            <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($redirectTo); ?>">

            <div class="row g-3">
                <?php
                $orderedFields = isset($_FIELD_ORDER) ? $_FIELD_ORDER : array_keys($schema);
                foreach ($orderedFields as $col):
                    if (!isset($schema[$col]))
                        continue;
                    $info = $schema[$col];

                    if ($col === $primaryKey && str_contains($info['extra'], 'auto_increment'))
                        continue;

                    $val = $record[$col] ?? $_GET[$col] ?? $info['default'] ?? '';
                    $required = (isset($info['null']) && !$info['null']) ? 'required' : '';

                    $colClass = $_FORM_LAYOUT[$col] ?? (($info['type'] === 'textarea' || (isset($info['html']) && $info['html'])) ? 'col-12' : 'col-md-6');
                    if (is_numeric($colClass))
                        $colClass = "col-md-{$colClass}";
                    ?>
                    <div class="<?php echo $colClass; ?>">
                        <div class="mb-3">
                        <label class="form-label fw-bold"><?php echo ucwords(str_replace('_', ' ', $col)); ?></label>
                        <?php
                        $isReadonly = isset($_READONLY_FIELDS) && in_array($col, $_READONLY_FIELDS);
                        $isAdminOnly = isset($_ADMIN_ONLY_FIELDS) && in_array($col, $_ADMIN_ONLY_FIELDS);
                        $isCheckbox = isset($_CHECKBOX_FIELDS) && in_array($col, $_CHECKBOX_FIELDS);
                        
                        $readonlyAttr = $isReadonly ? 'readonly' : '';
                        $disabledAttr = ($isAdminOnly && !current_user_is_at_least_admin()) ? 'disabled' : '';
                        
                        if ($isCheckbox): ?>
                            <div class="form-check form-switch">
                                <input type="hidden" name="<?php echo $col; ?>" value="0">
                                <input type="checkbox" name="<?php echo $col; ?>" value="1" 
                                    class="form-check-input" <?php echo (int)$val === 1 ? 'checked' : ''; ?> 
                                    <?php echo $disabledAttr; ?>>
                                <small class="text-muted d-block">Marque para ativar</small>
                            </div>
                        <?php elseif (isset($_STATIC_OPTIONS[$col])): ?>
                            <select name="<?php echo $col; ?>" class="form-select" <?php echo $required; ?> <?php echo $disabledAttr; ?>>
                                <option value="">Selecione...</option>
                                <?php foreach ($_STATIC_OPTIONS[$col] as $optVal => $optLabel): 
                                    $optionValue = is_numeric($optVal) ? $optLabel : $optVal;
                                    ?>
                                    <option value="<?php echo htmlspecialchars($optionValue); ?>" <?php echo (string) $val === (string) $optionValue ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($optLabel); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif (isset($lookupsData[$col])): ?>
                            <select name="<?php echo $col; ?>" class="form-select select2-basic" <?php echo $required; ?> <?php echo $disabledAttr; ?>>
                                <option value="">Selecione...</option>
                                <?php foreach ($lookupsData[$col] as $opt): ?>
                                    <option value="<?php echo $opt['id']; ?>" <?php echo (string) $val === (string) $opt['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($opt['nome'] ?? $opt['id']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($info['type'] === 'textarea'):
                            $isHtml = isset($_HTML_FIELDS) && in_array($col, $_HTML_FIELDS);
                            ?>
                            <textarea name="<?php echo $col; ?>" id="editor_<?php echo $col; ?>"
                                class="form-control <?php echo $isHtml ? 'trumbowyg' : ''; ?>" rows="4" <?php echo $required; ?> <?php echo $readonlyAttr; ?> <?php echo $disabledAttr; ?>><?php echo htmlspecialchars($val); ?></textarea>
                        <?php elseif ($info['type'] === 'date'): ?>
                            <input type="date" name="<?php echo $col; ?>" class="form-control"
                                value="<?php echo htmlspecialchars($val); ?>" <?php echo $required; ?> <?php echo $readonlyAttr; ?> <?php echo $disabledAttr; ?>>
                        <?php elseif ($info['type'] === 'time'): ?>
                            <input type="time" name="<?php echo $col; ?>" class="form-control"
                                value="<?php echo htmlspecialchars($val); ?>" <?php echo $required; ?> <?php echo $readonlyAttr; ?> <?php echo $disabledAttr; ?>>
                        <?php elseif ($info['type'] === 'number'): ?>
                            <input type="number" name="<?php echo $col; ?>" class="form-control"
                                value="<?php echo htmlspecialchars($val); ?>" <?php echo $required; ?> <?php echo $readonlyAttr; ?> <?php echo $disabledAttr; ?>>
                        <?php elseif ($info['type'] === 'decimal'): ?>
                            <input type="number" step="0.01" name="<?php echo $col; ?>" class="form-control"
                                value="<?php echo htmlspecialchars($val); ?>" <?php echo $required; ?> <?php echo $readonlyAttr; ?> <?php echo $disabledAttr; ?>>
                        <?php else: ?>
                            <input type="text" name="<?php echo $col; ?>" class="form-control"
                                value="<?php echo htmlspecialchars($val); ?>" <?php echo $required; ?> <?php echo $readonlyAttr; ?> <?php echo $disabledAttr; ?>>
                        <?php endif; ?>
                    </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-4 text-end">
                <button type="submit" class="btn btn-primary px-5">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
            jQuery('.select2-basic').select2({
                theme: 'bootstrap-5',
                width: '100%'
            });
        }
        if (typeof jQuery !== 'undefined' && jQuery.fn.trumbowyg) {
            jQuery('.trumbowyg').trumbowyg({
                lang: 'pt_br',
                btns: [
                    ['viewHTML'],
                    ['undo', 'redo'], // Only supported in newer versions or with plugins
                    ['formatting'],
                    ['strong', 'em', 'del'],
                    ['superscript', 'subscript'],
                    ['link'],
                    ['insertImage'],
                    ['unorderedList', 'orderedList'],
                    ['horizontalRule'],
                    ['removeformat'],
                    ['fullscreen']
                ],
                autogrow: true
            });
        }
    });
</script>