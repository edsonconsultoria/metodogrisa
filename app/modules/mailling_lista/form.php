<?php
// app/modules/mailling_lista/form.php

require_once __DIR__ . '/../../config/config.php';

if (!current_user_is_at_least_admin()) {
    echo '<div class="alert alert-danger mt-3">Acesso negado. Apenas administradores podem acessar este módulo.</div>';
    include __DIR__ . '/../../templates/footer.php';
    exit;
}
require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';

$tableName = '101_mailling_lista';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$engine = new CrudEngine($pdo, $tableName);

$schema = $engine->getSchema();
$primaryKey = $engine->getPrimaryKey();

$record = $id ? $engine->getRecord($id) : null;
$baseHref = BASE_URL . 'index.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <?php echo ($id ? 'Editar' : 'Nova') . ' Lista de Mailing'; ?>
    </h4>
    <a href="<?php echo $baseHref; ?>?m=mailling_lista&a=list" class="btn btn-outline-secondary btn-sm">Voltar</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="<?php echo $baseHref; ?>?m=mailling_lista&a=save" method="post">
            <?php if ($id): ?>
                <input type="hidden" name="<?php echo $primaryKey; ?>" value="<?php echo htmlspecialchars($id); ?>">
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-md-12">
                    <label class="form-label">Nome da Lista <span class="text-danger">*</span></label>
                    <input type="text" name="nome_da_lista" class="form-control"
                        value="<?php echo htmlspecialchars($record['nome_da_lista'] ?? ''); ?>" required>
                </div>

                <div class="col-md-12">
                    <label class="form-label">Comando SQL <span class="text-danger">*</span></label>
                    <textarea name="sql" class="form-control font-monospace" rows="10" required
                        placeholder="SELECT ... FROM ... WHERE ..."><?php echo htmlspecialchars($record['sql'] ?? ''); ?></textarea>
                    <div class="form-text text-muted">
                        Insira o comando SQL que define os destinatários desta lista.
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-top">
                <button type="submit" class="btn btn-primary">Salvar Lista</button>
                <a href="<?php echo $baseHref; ?>?m=mailling_lista&a=list"
                    class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>