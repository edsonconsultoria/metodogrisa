<?php
// app/modules/interessados/form.php

require_once __DIR__ . '/../../config/config.php';

if (!current_user_is_at_least_admin()) {
    echo '<div class="alert alert-danger mt-3">Acesso negado. Apenas administradores podem acessar este módulo.</div>';
    include __DIR__ . '/../../templates/footer.php';
    exit;
}
require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';

$tableName = '101_interessados';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$engine = new CrudEngine($pdo, $tableName);

// Lookup para Origem
$stmt = $pdo->query("SELECT origem FROM 101_origem_interessados ORDER BY origem");
$origemOptions = $stmt->fetchAll();

$schema = $engine->getSchema();
$primaryKey = $engine->getPrimaryKey();

$record = $id ? $engine->getRecord($id) : null;
$baseHref = BASE_URL . 'index.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <?php echo ($id ? 'Editar' : 'Novo') . ' ' . get_table_label($tableName); ?>
    </h4>
    <a href="<?php echo $baseHref; ?>?m=interessados&a=list" class="btn btn-outline-secondary btn-sm">Voltar</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="<?php echo $baseHref; ?>?m=interessados&a=save" method="post">
            <?php if ($id): ?>
                <input type="hidden" name="<?php echo $primaryKey; ?>" value="<?php echo htmlspecialchars($id); ?>">
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nome <span class="text-danger">*</span></label>
                    <input type="text" name="nome" class="form-control"
                        value="<?php echo htmlspecialchars($record['nome'] ?? ''); ?>" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Telefone <span class="text-danger">*</span></label>
                    <input type="text" name="telefone" class="form-control"
                        value="<?php echo htmlspecialchars($record['telefone'] ?? ''); ?>" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label">E-mail <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control"
                        value="<?php echo htmlspecialchars($record['email'] ?? ''); ?>" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Origem</label>
                    <select name="origem" class="form-select">
                        <option value="">Selecione...</option>
                        <?php foreach ($origemOptions as $opt): ?>
                            <option value="<?php echo htmlspecialchars($opt['origem']); ?>" <?php echo ((string) ($record['origem'] ?? '') === (string) $opt['origem']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($opt['origem']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Cadastramento</label>
                    <input type="datetime-local" name="cadastramento" class="form-control"
                        value="<?php echo $record ? date('Y-m-d\TH:i', strtotime($record['cadastramento'])) : date('Y-m-d\TH:i'); ?>">
                </div>

                <div class="col-md-12">
                    <label class="form-label">Notas</label>
                    <textarea name="notas" class="form-control"
                        rows="5"><?php echo htmlspecialchars($record['notas'] ?? ''); ?></textarea>
                </div>
            </div>

            <div class="mt-4 pt-3 border-top">
                <button type="submit" class="btn btn-primary">Salvar <?php echo get_table_label($tableName); ?></button>
                <a href="<?php echo $baseHref; ?>?m=interessados&a=list" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>