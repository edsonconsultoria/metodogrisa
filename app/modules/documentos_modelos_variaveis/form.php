<?php
// app/modules/documentos_modelos_variaveis/form.php

require_once __DIR__ . '/../../core/CrudEngine.php';

$tableName = '104_documentos_modelos_variaveis';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$modeloId = isset($_GET['modelo_id']) ? (int) $_GET['modelo_id'] : 0;

$engine = new CrudEngine($pdo, $tableName);
$record = $id ? $engine->getRecord($id) : null;

if (!$id && !$modeloId) {
    die("ID do modelo não fornecido.");
}

$effectiveModeloId = $id ? $record['modelo_id'] : $modeloId;

// Buscar nome do modelo para o título
$stmtM = $pdo->prepare("SELECT nome FROM 104_documentos_modelos WHERE id = ?");
$stmtM->execute([$effectiveModeloId]);
$modeloNome = $stmtM->fetchColumn();

$baseHref = BASE_URL . 'index.php';
$returnUrl = $baseHref . "?m=documentos_modelos&a=view&id=" . $effectiveModeloId;
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <?php echo ($id ? 'Editar' : 'Nova') . ' Variável'; ?>
        <small class="text-muted">(Modelo:
            <?php echo htmlspecialchars($modeloNome); ?>)
        </small>
    </h4>
    <a href="<?php echo $returnUrl; ?>" class="btn btn-outline-secondary btn-sm">Voltar ao Modelo</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="<?php echo $baseHref; ?>?m=documentos_modelos_variaveis&a=save" method="post">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
            <input type="hidden" name="modelo_id" value="<?php echo htmlspecialchars($effectiveModeloId); ?>">

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Variável (Tag) <span class="text-danger">*</span></label>
                    <input type="text" name="variavel" class="form-control" required
                        value="<?php echo htmlspecialchars($record['variavel'] ?? ''); ?>"
                        placeholder="Ex: {{nome_aluno}}">
                    <div class="form-text small">Use um padrão como {{tag}} para facilitar a identificação.</div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Origem</label>
                    <select name="origem" class="form-select" required>
                        <option value="sql" <?php echo ($record['origem'] ?? '') == 'sql' ? 'selected' : ''; ?>>SQL
                            Query</option>
                        <option value="fixo" <?php echo ($record['origem'] ?? '') == 'fixo' ? 'selected' : ''; ?>>Valor
                            Fixo</option>
                        <option value="funcao" <?php echo ($record['origem'] ?? '') == 'funcao' ? 'selected' : ''; ?>
                            >Função PHP</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="ativo" class="form-select">
                        <option value="1" <?php echo ($record['ativo'] ?? '1') == '1' ? 'selected' : ''; ?>>Ativo
                        </option>
                        <option value="0" <?php echo ($record['ativo'] ?? '1') == '0' ? 'selected' : ''; ?>>Inativo
                        </option>
                    </select>
                </div>

                <div class="col-md-12">
                    <label class="form-label">Valor / Query <span class="text-danger">*</span></label>
                    <textarea name="valor" class="form-control font-monospace" rows="5" required
                        placeholder="Se origem for SQL: SELECT nome FROM tabela WHERE id = :id&#10;Se origem for Fixo: Qualquer texto"><?php echo htmlspecialchars($record['valor'] ?? ''); ?></textarea>
                    <div class="form-text small">Para SQL, use placeholders como :id se necessário.</div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-top">
                <button type="submit" class="btn btn-primary">Salvar Variável</button>
                <a href="<?php echo $returnUrl; ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>