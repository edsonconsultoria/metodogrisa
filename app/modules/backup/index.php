<?php
// app/modules/backup/index.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/service.php';

if (!current_user_is_superadmin()) {
    echo '<div class="alert alert-danger">Somente usuários SUPERADMIN podem acessar os backups.</div>';
    return;
}

$backupDir = __DIR__ . '/../../storage/backups';
$service = new BackupService($backupDir);

$backupSuccess = '';
$backupError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_backup'])) {
    try {
        $backupSuccess = $service->run();
    } catch (\Exception $e) {
        $backupError = $e->getMessage();
    }
}

$metadataFile = $backupDir . '/backup.json';
$recentBackups = [];
$now = new \DateTimeImmutable();
if (is_file($metadataFile)) {
    $raw = file_get_contents($metadataFile);
    $entries = json_decode($raw, true);
    if (is_array($entries)) {
        foreach ($entries as $entry) {
            $createdAt = $entry['created_at'] ?? null;
            $createdAtFormatted = null;
            $daysAgo = null;
            if ($createdAt) {
                try {
                    $createdDate = new \DateTimeImmutable($createdAt);
                    $createdAtFormatted = $createdDate->format('d/m/Y H:i');
                    $daysAgo = (int) $createdDate->diff($now)->format('%a');
                } catch (\Exception $e) {
                    // ignora carimbos inválidos
                }
            }
            $recentBackups[] = [
                'name' => $entry['filename'] ?? 'backup',
                'size' => $entry['size'] ?? 0,
                'created_at_formatted' => $createdAtFormatted,
                'days_ago' => $daysAgo,
            ];
        }
    }
}
?>

<div class="row g-3">
  <div class="col-12">
    <?php if ($backupSuccess): ?>
      <div class="alert alert-success">
        <?php echo htmlspecialchars($backupSuccess); ?>
      </div>
    <?php elseif ($backupError): ?>
      <div class="alert alert-danger">
        <?php echo htmlspecialchars($backupError); ?>
      </div>
    <?php endif; ?>
    <div class="card shadow-sm">
      <div class="card-header">
        <strong>Backup</strong>
      </div>
      <div class="card-body">
        <form method="post">
          <button type="submit" name="run_backup" class="btn btn-success">
            Gerar backup e enviar por email
          </button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card shadow-sm">
      <div class="card-header">
        Últimos backups
      </div>
      <div class="card-body p-0">
        <?php if (empty($recentBackups)): ?>
          <div class="p-3 text-muted small">Nenhum backup encontrado.</div>
        <?php else: ?>
          <table class="table table-sm mb-0">
            <thead class="table-light">
              <tr>
                <th>Arquivo</th>
                <th>Criado em</th>
                <th class="text-center">Dias</th>
                <th class="text-end">Tamanho</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentBackups as $backup): ?>
                <tr>
                  <td><?php echo htmlspecialchars($backup['name']); ?></td>
                  <td>
                    <?php if (!empty($backup['created_at_formatted'])): ?>
                      <?php echo htmlspecialchars($backup['created_at_formatted']); ?>
                    <?php else: ?>
                      —
                    <?php endif; ?>
                  </td>
                  <td class="text-center">
                    <?php
                    if ($backup['days_ago'] !== null) {
                        echo htmlspecialchars($backup['days_ago']) . ' dias';
                    } else {
                        echo '—';
                    }
                    ?>
                  </td>
                  <td class="text-end">
                    <?php echo number_format(($backup['size'] ?? 0) / 1024, 1, ',', '.'); ?> KB
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
