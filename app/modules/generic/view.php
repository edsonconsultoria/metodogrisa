<?php
// app/modules/generic/view.php

require_once __DIR__ . '/../../core/CrudEngine.php';

$tableName = $_TABLE ?? $_GET['m'] ?? '';
$id = $_GET['id'] ?? null;

if (!$tableName || !$id) {
    echo '<div class="alert alert-danger">Parâmetros inválidos.</div>';
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

$record = $engine->getRecord($id);
$schema = $engine->getSchema();

if (!$record) {
    echo '<div class="alert alert-warning">Registro não encontrado.</div>';
    return;
}

$baseHref = BASE_URL . 'index.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Visualizar
        <?php echo get_table_label($tableName); ?> #
        <?php echo htmlspecialchars($id); ?>
    </h4>
    <div>
        <a href="<?php echo $baseHref; ?>?m=<?php echo $tableName; ?>&a=form&id=<?php echo $id; ?>"
            class="btn btn-primary btn-sm">Editar</a>
        <a href="<?php echo $baseHref; ?>?m=<?php echo $tableName; ?>&a=list"
            class="btn btn-outline-secondary btn-sm">Voltar</a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <dl class="row mb-0">
            <?php foreach ($schema as $col => $info): ?>
                <dt class="col-sm-3 text-muted">
                    <?php echo htmlspecialchars($_LABELS[$col] ?? ucfirst(str_replace('_', ' ', $col))); ?>
                </dt>
                <dd class="col-sm-9">
                    <?php
                    $val = $record[$col];
                    if ($val === null) {
                        echo '<span class="text-muted small"><em>Nulo</em></span>';
                    } elseif (isset($record[$col . '_label'])) {
                        echo htmlspecialchars($record[$col . '_label']);
                    } elseif ($info['type'] === 'date') {
                        echo $val ? date('d/m/Y', strtotime($val)) : '—';
                    } elseif ($info['type'] === 'textarea') {
                        $isHtml = isset($_HTML_FIELDS) && in_array($col, $_HTML_FIELDS);
                        if ($isHtml) {
                            echo $val; // Render HTML
                        } else {
                            echo nl2br(htmlspecialchars($val));
                        }
                    } else {
                        echo htmlspecialchars($val);
                    }
                    ?>
                </dd>
                <hr class="text-black-50 my-2">
            <?php endforeach; ?>
        </dl>
    </div>
</div>
