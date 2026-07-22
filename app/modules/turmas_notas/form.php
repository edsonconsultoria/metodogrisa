<?php
// app/modules/turmas_notas/form.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';

$tableName = '104_turmas_notas';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$engine = new CrudEngine($pdo, $tableName);

// Lookups
$turmasOptions = $pdo->query("SELECT id, nome FROM 104_turmas ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$disciplinasOptions = $pdo->query("SELECT id, nome FROM 104_disciplinas ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$alunosOptions = $pdo->query("SELECT a.id, p.nome 
                             FROM 104_alunos a 
                             JOIN 101_pessoas p ON a.aluno = p.id 
                             ORDER BY p.nome ASC")->fetchAll(PDO::FETCH_ASSOC);

$schema = $engine->getSchema();
$primaryKey = $engine->getPrimaryKey();

$record = $id ? $engine->getRecord($id) : null;

// Pre-select aluno from GET if creating new
if (!$id && isset($_GET['aluno'])) {
    $record['aluno'] = (int) $_GET['aluno'];
}

$baseHref = BASE_URL . 'index.php';
$returnParam = $_REQUEST['return'] ?? '';
$returnUrl = $returnParam ? $baseHref . '?' . $returnParam : $baseHref . '?m=turmas_notas&a=list';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <?php echo ($id ? 'Editar' : 'Novo') . ' ' . get_table_label($tableName); ?>
    </h4>
    <a href="<?php echo $returnUrl; ?>" class="btn btn-outline-secondary btn-sm">Voltar</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="<?php echo $baseHref; ?>?m=turmas_notas&a=save" method="post">
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

                <!-- Aluno -->
                <div class="col-md-6">
                    <label class="form-label">Aluno <span class="text-danger">*</span></label>
                    <select name="aluno" class="form-select" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($alunosOptions as $opt): ?>
                            <option value="<?php echo $opt['id']; ?>" <?php echo ((string) ($record['aluno'] ?? '') === (string) $opt['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($opt['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Nota -->
                <div class="col-md-6">
                    <label class="form-label">Nota <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" name="nota" class="form-control" required
                        value="<?php echo htmlspecialchars($record['nota'] ?? ''); ?>">
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