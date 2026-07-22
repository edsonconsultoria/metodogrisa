<?php
// app/modules/log/view.php
require_once __DIR__ . '/../../config/config.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    echo '<div class="alert alert-danger">ID inválido.</div>';
    return;
}

$stmt = $pdo->prepare("SELECT l.*, u.nome AS usuario_nome FROM log l LEFT JOIN pessoas u ON u.id = l.usuario_id WHERE l.id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$log = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$log) {
    echo '<div class="alert alert-danger">Registro não encontrado.</div>';
    return;
}

$desc = $log['descricao'] ?? '';
$formatDescricao = function (?string $desc): string {
    if ($desc === null || $desc === '') {
        return '';
    }
    $trimmed = ltrim($desc);
    $altPrefix = 'alterações:';
    if (stripos($trimmed, $altPrefix) === 0) {
        $jsonPart = trim(substr($trimmed, strlen($altPrefix)));
        $decodedAlt = json_decode($jsonPart, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decodedAlt)) {
            $prettyAlt = json_encode($decodedAlt, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return '<div class="small fw-bold mb-1">alterações:</div><pre class="mb-0 small" style="white-space:pre-wrap;">' . htmlspecialchars($prettyAlt) . '</pre>';
        }
    }
    $decoded = json_decode($desc, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return '<pre class="mb-0 small" style="white-space:pre-wrap;">' . htmlspecialchars($pretty) . '</pre>';
    }
    return nl2br(htmlspecialchars($desc));
};

?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">Log #<?php echo (int) $log['id']; ?></h4>
  <a href="<?php echo BASE_URL; ?>index.php?m=log&a=list" class="btn btn-outline-secondary btn-sm">Voltar</a>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <div class="row g-3 small">
      <div class="col-md-4"><strong>Data/Hora:</strong> <?php echo htmlspecialchars(date('d/m/Y H:i:s', strtotime($log['data_hora']))); ?></div>
      <div class="col-md-4"><strong>Usuário:</strong> <?php echo htmlspecialchars($log['usuario_nome'] ?? $log['usuario_id']); ?></div>
      <div class="col-md-4"><strong>IP:</strong> <?php echo htmlspecialchars($log['ip']); ?></div>
      <div class="col-md-4"><strong>Tabela:</strong> <?php echo htmlspecialchars($log['tabela_afetada']); ?></div>
      <div class="col-md-4"><strong>Ação:</strong> <?php echo htmlspecialchars($log['acao']); ?></div>
      <div class="col-12">
        <strong>Descrição:</strong>
        <div class="mt-1"><?php echo $formatDescricao($desc); ?></div>
      </div>
    </div>
  </div>
</div>
