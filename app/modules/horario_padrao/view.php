<?php
// app/modules/horario_padrao/view.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';

$tableName = '104_horario_padrao';
$engine = new CrudEngine($pdo, $tableName);

$id = (int) ($_GET['id'] ?? 0);
$record = $engine->getRecord($id);

if (!$record) {
    header("Location: " . BASE_URL . "index.php?m=horario_padrao&a=list&msg=" . urlencode("Horário não encontrado.") . "&type=danger");
    exit;
}

$dias = [
    1 => 'Dia 1',
    2 => 'Dia 2',
    3 => 'Dia 3',
    4 => 'Dia 4',
    5 => 'Dia 5',
    6 => 'Dia 6',
    7 => 'Dia 7'
];

if (isset($record['dia']) && isset($dias[$record['dia']])) {
    $record['dia_label'] = $dias[$record['dia']];
}

$schema = $engine->getSchema();
$baseHref = BASE_URL . 'index.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Visualizar Horário</h4>
    <div class="d-flex gap-2">
        <a href="<?php echo $baseHref; ?>?m=horario_padrao&a=form&id=<?php echo $id; ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil"></i> Editar
        </a>
        <a href="<?php echo $baseHref; ?>?m=horario_padrao&a=list" class="btn btn-outline-secondary btn-sm">Voltar</a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="row g-3">
            <?php foreach ($schema as $col => $info): ?>
                <div class="col-md-6 mb-3 border-bottom pb-2">
                    <label class="fw-bold text-muted small d-block">
                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $col))); ?>
                    </label>
                    <div class="fs-5">
                        <?php
                        $val = isset($record[$col . '_label']) ? $record[$col . '_label'] : ($record[$col] ?? '—');
                        echo nl2br(htmlspecialchars((string) ($val === '' ? '—' : $val)));
                        ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>