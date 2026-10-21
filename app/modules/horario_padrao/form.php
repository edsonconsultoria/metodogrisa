<?php
// app/modules/horario_padrao/form.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';

$tableName = '104_horario_padrao';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$engine = new CrudEngine($pdo, $tableName);

$schema = $engine->getSchema();
$primaryKey = $engine->getPrimaryKey();

$record = $id ? $engine->getRecord($id) : null;
$baseHref = BASE_URL . 'index.php';

$dias = [
    1 => 'Dia 1',
    2 => 'Dia 2',
    3 => 'Dia 3',
    4 => 'Dia 4',
    5 => 'Dia 5',
    6 => 'Dia 6',
    7 => 'Dia 7'
];
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <?php echo ($id ? 'Editar' : 'Novo') . ' ' . get_table_label($tableName); ?>
    </h4>
    <a href="<?php echo $baseHref; ?>?m=horario_padrao&a=list" class="btn btn-outline-secondary btn-sm">Voltar</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="<?php echo $baseHref; ?>?m=horario_padrao&a=save" method="post">
            <?php if ($id): ?>
                <input type="hidden" name="<?php echo $primaryKey; ?>" value="<?php echo htmlspecialchars($id); ?>">
            <?php endif; ?>

            <div class="row g-3">
                <!-- Dia -->
                <div class="col-md-6">
                    <label class="form-label">Dia <span class="text-danger">*</span></label>
                    <select name="dia" class="form-select" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($dias as $num => $label): ?>
                            <option value="<?php echo $num; ?>" <?php echo ((string) ($record['dia'] ?? '') === (string) $num) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Início <span class="text-danger">*</span></label>
                    <input type="time" name="inicio" class="form-control" required
                        value="<?php echo htmlspecialchars($record['inicio'] ?? ''); ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Término <span class="text-danger">*</span></label>
                    <input type="time" name="termino" class="form-control" required
                        value="<?php echo htmlspecialchars($record['termino'] ?? ''); ?>">
                </div>
            </div>

            <div class="mt-4 pt-3 border-top">
                <button type="submit" class="btn btn-primary">Salvar Horário</button>
                <a href="<?php echo $baseHref; ?>?m=horario_padrao&a=list"
                    class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>