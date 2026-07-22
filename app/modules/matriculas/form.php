<?php
// app/modules/matriculas/form.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';

$tableName = '104_matriculas';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$engine = new CrudEngine($pdo, $tableName);

// Lookups
$sqlAlunos = "SELECT a.id, p.nome 
              FROM 104_alunos a 
              JOIN 101_pessoas p ON a.pessoa = p.id 
              ORDER BY p.nome ASC";
$alunosOptions = $pdo->query($sqlAlunos)->fetchAll(PDO::FETCH_ASSOC);

$turmasOptions = $pdo->query("SELECT id, nome FROM 104_turmas ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

$schema = $engine->getSchema();
$primaryKey = $engine->getPrimaryKey();

$record = $id ? $engine->getRecord($id) : null;
$baseHref = BASE_URL . 'index.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <?php echo ($id ? 'Editar' : 'Novo') . ' ' . get_table_label($tableName); ?>
    </h4>
    <a href="<?php echo $baseHref; ?>?m=matriculas&a=list" class="btn btn-outline-secondary btn-sm">Voltar</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="<?php echo $baseHref; ?>?m=matriculas&a=save" method="post">
            <?php if ($id): ?>
                <input type="hidden" name="<?php echo $primaryKey; ?>" value="<?php echo htmlspecialchars($id); ?>">
            <?php endif; ?>

            <div class="row g-3">
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

                <!-- Tipo -->
                <div class="col-md-12">
                    <label class="form-label">Tipo de Matrícula</label>
                    <input type="text" name="tipo" class="form-control"
                        value="<?php echo htmlspecialchars($record['tipo'] ?? ''); ?>"
                        placeholder="Ex: Regular, Especial, etc.">
                </div>

                <!-- Observação -->
                <div class="col-md-12">
                    <label class="form-label">Observação</label>
                    <textarea name="observacao" class="form-control"
                        rows="3"><?php echo htmlspecialchars($record['observacao'] ?? ''); ?></textarea>
                </div>
            </div>

            <div class="mt-4 pt-3 border-top">
                <button type="submit" class="btn btn-primary">Salvar Matrícula</button>
                <a href="<?php echo $baseHref; ?>?m=matriculas&a=list" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>