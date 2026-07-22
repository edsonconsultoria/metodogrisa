<?php
// app/modules/documentos_gerados/view.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';
require_once __DIR__ . '/../../helpers/list_state.php';

$tableName = '104_documentos_gerados';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$engine = new CrudEngine($pdo, $tableName);

$engine->setLookups([
    'modelo_id' => ['table' => '104_documentos_modelos', 'column' => 'nome'],
    'pessoa_id' => ['table' => '101_pessoas', 'column' => 'nome'],
    'criado_por' => ['table' => '101_pessoas', 'column' => 'nome']
]);

$record = $engine->getRecord($id);
if (!$record) {
    die("Documento não encontrado.");
}

$modeloNome = $record['modelo_id_label'] ?? '—';
$pessoaNome = $record['pessoa_id_label'] ?? '—';
$usuarioLogin = $record['criado_por_label'] ?? 'Sistema';

$baseHref = BASE_URL . 'index.php';
$returnUrl = list_state_return_destination('documentos_gerados', 'list');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Visualizar Documento Gerado</h4>
    <div class="d-flex gap-2">
        <a href="<?php echo UPLOADS_URL . '/' . $record['arquivo_docx']; ?>" target="_blank"
            class="btn btn-primary btn-sm">
            <i class="bi bi-download"></i> Baixar DOCX
        </a>
        <a href="<?php echo $returnUrl; ?>"
            class="btn btn-outline-secondary btn-sm">Voltar</a>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="row g-4">
            <div class="col-md-12">
                <label class="text-muted small d-block mb-1">Título do Documento</label>
                <div class="fw-bold fs-5">
                    <?php echo htmlspecialchars($record['titulo']); ?>
                </div>
            </div>

            <div class="col-md-6">
                <label class="text-muted small d-block mb-1">Modelo Base</label>
                <div class="fw-bold">
                    <?php echo htmlspecialchars($modeloNome); ?>
                </div>
            </div>

            <div class="col-md-6">
                <label class="text-muted small d-block mb-1">Pessoa / Aluno</label>
                <div class="fw-bold">
                    <?php echo htmlspecialchars($pessoaNome); ?>
                </div>
            </div>

            <div class="col-md-6">
                <label class="text-muted small d-block mb-1">Gerado em</label>
                <div class="fw-bold">
                    <?php echo date('d/m/Y H:i:s', strtotime($record['created_at'])); ?>
                </div>
            </div>

            <div class="col-md-6">
                <label class="text-muted small d-block mb-1">Gerado por</label>
                <div class="fw-bold">
                    <?php echo htmlspecialchars($usuarioLogin); ?>
                </div>
            </div>

            <div class="col-md-12">
                <label class="text-muted small d-block mb-1">Hash de Segurança</label>
                <div class="font-monospace small bg-light p-2 border rounded">
                    <?php echo htmlspecialchars($record['hash']); ?>
                </div>
            </div>

            <?php if (!empty($record['vars_json'])): ?>
                <div class="col-md-12">
                    <label class="text-muted small d-block mb-1">Variáveis Utilizadas (JSON)</label>
                    <pre class="bg-dark text-light p-3 rounded small"><code><?php
                    $vars = json_decode($record['vars_json'], true);
                    echo htmlspecialchars(json_encode($vars, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    ?></code></pre>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>