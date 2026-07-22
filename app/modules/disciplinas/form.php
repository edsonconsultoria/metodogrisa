<?php
// app/modules/disciplinas/form.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';

$tableName = '104_disciplinas';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$engine = new CrudEngine($pdo, $tableName);

// Lookups
$professoresOptions = $pdo->query("SELECT id, apelido FROM 104_professores ORDER BY apelido ASC")->fetchAll(PDO::FETCH_ASSOC);

$schema = $engine->getSchema();
$primaryKey = $engine->getPrimaryKey();

$record = $id ? $engine->getRecord($id) : null;
$baseHref = BASE_URL . 'index.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <?php echo ($id ? 'Editar' : 'Novo') . ' ' . get_table_label($tableName); ?>
    </h4>
    <a href="<?php echo $baseHref; ?>?m=disciplinas&a=list" class="btn btn-outline-secondary btn-sm">Voltar</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="<?php echo $baseHref; ?>?m=disciplinas&a=save" method="post">
            <?php if ($id): ?>
                <input type="hidden" name="<?php echo $primaryKey; ?>" value="<?php echo htmlspecialchars($id); ?>">
            <?php endif; ?>

            <div class="row g-3">
                <!-- Nome -->
                <div class="col-md-12">
                    <label class="form-label">Nome da Disciplina <span class="text-danger">*</span></label>
                    <input type="text" name="nome" class="form-control" required
                        value="<?php echo htmlspecialchars($record['nome'] ?? ''); ?>">
                </div>

                <!-- Carga Horária -->
                <div class="col-md-6">
                    <label class="form-label">Carga Horária (h)</label>
                    <input type="number" name="carga_horaria" class="form-control"
                        value="<?php echo htmlspecialchars($record['carga_horaria'] ?? ''); ?>">
                </div>

                <!-- Professor -->
                <div class="col-md-6">
                    <label class="form-label">Professor Responsável</label>
                    <select name="professor" class="form-select">
                        <option value="">Selecione...</option>
                        <?php foreach ($professoresOptions as $opt): ?>
                            <option value="<?php echo $opt['id']; ?>" <?php echo ((string) ($record['professor'] ?? '') === (string) $opt['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($opt['apelido']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mt-4 pt-3 border-top">
                <button type="submit" class="btn btn-primary">Salvar Disciplina</button>
                <a href="<?php echo $baseHref; ?>?m=disciplinas&a=list" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>