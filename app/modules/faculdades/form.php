<?php
// app/modules/faculdades/form.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';

$tableName = '104_faculdades';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$engine = new CrudEngine($pdo, $tableName);

$schema = $engine->getSchema();
$primaryKey = $engine->getPrimaryKey();

$record = $id ? $engine->getRecord($id) : null;
$baseHref = BASE_URL . 'index.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <?php echo ($id ? 'Editar' : 'Novo') . ' ' . get_table_label($tableName); ?>
    </h4>
    <a href="<?php echo $baseHref; ?>?m=faculdades&a=list" class="btn btn-outline-secondary btn-sm">Voltar</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="<?php echo $baseHref; ?>?m=faculdades&a=save" method="post">
            <?php if ($id): ?>
                <input type="hidden" name="<?php echo $primaryKey; ?>" value="<?php echo htmlspecialchars($id); ?>">
            <?php endif; ?>

            <div class="row g-3">
                <!-- Nome -->
                <div class="col-md-6">
                    <label class="form-label">Nome da Faculdade <span class="text-danger">*</span></label>
                    <input type="text" name="nome" class="form-control" required
                        value="<?php echo htmlspecialchars($record['nome'] ?? ''); ?>">
                </div>

                <!-- Email -->
                <div class="col-md-6">
                    <label class="form-label">E-mail</label>
                    <input type="email" name="email" class="form-control"
                        value="<?php echo htmlspecialchars($record['email'] ?? ''); ?>">
                </div>

            </div>

            <div class="mt-4 pt-3 border-top">
                <button type="submit" class="btn btn-primary">Salvar Faculdade</button>
                <a href="<?php echo $baseHref; ?>?m=faculdades&a=list" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>