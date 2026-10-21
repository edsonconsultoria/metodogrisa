<?php
// app/modules/coordenadores/form.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';

$tableName = '104_coordenadores';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$engine = new CrudEngine($pdo, $tableName);

// Lookups
$pessoasOptions = $pdo->query("SELECT id, nome FROM 101_pessoas ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

$schema = $engine->getSchema();
$primaryKey = $engine->getPrimaryKey();

$record = $id ? $engine->getRecord($id) : null;
$baseHref = BASE_URL . 'index.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <?php echo ($id ? 'Editar' : 'Novo') . ' ' . get_table_label($tableName); ?>
    </h4>
    <a href="<?php echo $baseHref; ?>?m=coordenadores&a=list" class="btn btn-outline-secondary btn-sm">Voltar</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="<?php echo $baseHref; ?>?m=coordenadores&a=save" method="post">
            <?php if ($id): ?>
                <input type="hidden" name="<?php echo $primaryKey; ?>" value="<?php echo htmlspecialchars($id); ?>">
            <?php endif; ?>

            <div class="row g-3">
                <!-- Pessoa -->
                <div class="col-md-6">
                    <label class="form-label">Pessoa <span class="text-danger">*</span></label>
                    <select name="pessoa" class="form-select" required>
                        <option value="">Selecione...</option>
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
            </div>

            <div class="mt-4 pt-3 border-top">
                <button type="submit" class="btn btn-primary">Salvar Coordenador</button>
                <a href="<?php echo $baseHref; ?>?m=coordenadores&a=list" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>