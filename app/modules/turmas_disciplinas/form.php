<?php
// app/modules/turmas_disciplinas/form.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';
require_once __DIR__ . '/../../helpers/list_state.php';

$tableName = '104_turmas_disciplinas';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$engine = new CrudEngine($pdo, $tableName);

// Lookups
$turmasOptions = $pdo->query("SELECT id, nome FROM 104_turmas ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$disciplinasOptions = $pdo->query("SELECT id, nome FROM 104_disciplinas ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$professoresOptions = $pdo->query("SELECT id, apelido FROM 104_professores ORDER BY apelido ASC")->fetchAll(PDO::FETCH_ASSOC);

$schema = $engine->getSchema();
$primaryKey = $engine->getPrimaryKey();

$record = $id ? $engine->getRecord($id) : null;

// Pre-fill turma if provided in GET and not editing
if (!$id && isset($_GET['turma'])) {
    $record['turma'] = (int) $_GET['turma'];
}

$baseHref = BASE_URL . 'index.php';
$returnUrl = list_state_return_destination('turmas_disciplinas', 'list');
$returnParam = $_REQUEST['return'] ?? '';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <?php echo ($id ? 'Editar' : 'Novo') . ' ' . get_table_label($tableName); ?>
    </h4>
    <a href="<?php echo $returnUrl; ?>" class="btn btn-outline-secondary btn-sm">Voltar</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="<?php echo $baseHref; ?>?m=turmas_disciplinas&a=save" method="post">
            <?php if ($id): ?>
                <input type="hidden" name="<?php echo $primaryKey; ?>" value="<?php echo htmlspecialchars($id); ?>">
            <?php endif; ?>
            <input type="hidden" name="return" value="<?php echo htmlspecialchars($returnParam); ?>">

            <div class="row g-3">
                <!-- Turma -->
                <div class="col-md-6">
                    <label class="form-label">Turma <span class="text-danger">*</span></label>
                    <select name="turma" class="form-select" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($turmasOptions as $opt): ?>
                            <option value="<?php echo $opt['id']; ?>" <?php echo ((string) ($record['turma'] ?? '') === (string) $opt['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($opt['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Disciplina -->
                <div class="col-md-6">
                    <label class="form-label">Disciplina <span class="text-danger">*</span></label>
                    <select name="disciplina" class="form-select" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($disciplinasOptions as $opt): ?>
                            <option value="<?php echo $opt['id']; ?>" <?php echo ((string) ($record['disciplina'] ?? '') === (string) $opt['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($opt['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Carga Horária -->
                <div class="col-md-6">
                    <label class="form-label">Carga Horária (h)</label>
                    <input type="number" name="carga_horaria" class="form-control"
                        value="<?php echo htmlspecialchars($record['carga_horaria'] ?? ''); ?>">
                </div>

                <!-- Professor -->
                <div class="col-md-6">
                    <label class="form-label">Professor</label>
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
                <button type="submit" class="btn btn-primary">Salvar
                    <?php echo get_table_label($tableName); ?>
                </button>
                <a href="<?php echo $returnUrl; ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>