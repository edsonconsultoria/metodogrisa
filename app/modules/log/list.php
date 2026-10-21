<?php
// app/modules/log/list.php

require_once __DIR__ . '/../../config/config.php';

$perPage = 50;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
  $page = 1;

$search = trim($_GET['q'] ?? '');
$params = [];
$where = ' WHERE 1=1 ';
if ($search !== '') {
  $like = '%' . $search . '%';
  $where .= ' AND (l.tabela_afetada LIKE :tabela OR l.acao LIKE :acao OR l.descricao LIKE :descricao OR l.ip LIKE :ip OR u.nome LIKE :usuario)';
  $params = [
    ':tabela' => $like,
    ':acao' => $like,
    ':descricao' => $like,
    ':ip' => $like,
    ':usuario' => $like,
  ];
}

$sqlCount = "SELECT COUNT(*) FROM log l LEFT JOIN 101_pessoas u ON u.id = l.usuario_id {$where}";
$stmtCount = $pdo->prepare($sqlCount);
foreach ($params as $k => $v) {
  $stmtCount->bindValue($k, $v, PDO::PARAM_STR);
}
$stmtCount->execute();
$total = (int) $stmtCount->fetchColumn();

$totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
if ($page > $totalPages)
  $page = $totalPages;
$offset = ($page - 1) * $perPage;

// Ordena��o
$allowedSort = [
  'id' => 'l.id',
  'data' => 'l.data_hora',
  'usuario' => 'u.nome',
  'ip' => 'l.ip',
  'tabela' => 'l.tabela_afetada',
  'acao' => 'l.acao',
];
$sort = $_GET['sort'] ?? 'data';
$dir = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
$orderField = $allowedSort[$sort] ?? $allowedSort['data'];
$baseHref = BASE_URL . 'index.php';
$buildSortUrl = function (string $field) use ($baseHref, $search, $dir) {
  $current = $_GET['sort'] ?? '';
  $newDir = ($current === $field && $dir === 'asc') ? 'desc' : 'asc';
  $params = [
    'm' => 'log',
    'a' => 'list',
    'sort' => $field,
    'dir' => $newDir,
  ];
  if ($search !== '') {
    $params['q'] = $search;
  }
  return $baseHref . '?' . http_build_query($params);
};

$sql = "
    SELECT l.id, l.data_hora, l.usuario_id, u.nome AS usuario_nome, l.ip, l.tabela_afetada, l.acao, l.descricao
    FROM log l
    LEFT JOIN 101_pessoas u ON u.id = l.usuario_id
    {$where}
    ORDER BY {$orderField} {$dir}, l.id DESC
    LIMIT :limit OFFSET :offset
";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
  $stmt->bindValue($k, $v, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$baseHref = BASE_URL . 'index.php';

$formatDescricao = function (?string $desc): string {
  if ($desc === null || $desc === '') {
    return '';
  }

  // Caso especial: começa com "alterações:" seguido de JSON
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
  <h4 class="mb-0">Log do sistema</h4>
</div>

<style>
  .log-actions {
    display: flex;
    align-items: center;
    height: 100%;
    padding: 0;
    vertical-align: middle;
  }

  .log-actions .actions-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(44px, 1fr));
    grid-template-rows: repeat(2, auto);
    grid-auto-flow: column;
    gap: 0.35rem;
    min-width: 40px;
    width: 100%;
    justify-content: center;
    align-items: center;
  }

  .log-actions .action-btn i {
    font-size: 1.05rem;
  }

  .table-responsive .table tbody td,
  .table-responsive .table tbody th {
    vertical-align: middle;
  }

  @media (max-width: 767.98px) {
    .log-actions .actions-grid {
      grid-template-columns: repeat(4, minmax(44px, 1fr));
      grid-template-rows: repeat(2, auto);
      grid-auto-flow: column;
      min-width: 0;
      justify-content: center;
    }
  }
</style>

<form method="get" class="mb-3">
  <input type="hidden" name="m" value="log">
  <input type="hidden" name="a" value="list">
  <div class="row g-2">
    <div class="col-md-10">
      <input type="text" name="q" class="form-control" placeholder="Buscar por tabela, ação, descrição, IP ou usuário"
        value="<?php echo htmlspecialchars($search); ?>">
    </div>
    <div class="col-md-2 d-grid">
      <button type="submit" class="btn btn-outline-secondary">Buscar</button>
    </div>
  </div>
</form>

<div class="card shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover table-sm mb-0">
        <thead class="table-light">
          <tr>
            <th style="width: 40px;" class="text-start">Ver</th>
            <th style="width: 160px;"><a href="<?php echo $buildSortUrl('data'); ?>"
                style="color:#000;text-decoration:underline;">Data/Hora</a></th>
            <th style="width: 200px;"><a href="<?php echo $buildSortUrl('usuario'); ?>"
                style="color:#000;text-decoration:underline;">Usuário</a></th>
            <th style="width: 120px;"><a href="<?php echo $buildSortUrl('ip'); ?>"
                style="color:#000;text-decoration:underline;">IP</a></th>
            <th style="width: 160px;"><a href="<?php echo $buildSortUrl('tabela'); ?>"
                style="color:#000;text-decoration:underline;">Tabela</a></th>
            <th style="width: 120px;"><a href="<?php echo $buildSortUrl('acao'); ?>"
                style="color:#000;text-decoration:underline;">Ação</a></th>
            <th>Descrição</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr>
              <td colspan="7" class="text-center text-muted py-3">Nenhum registro encontrado.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td class="text-center log-actions">
                  <div class="actions-grid">
                    <a href="<?php echo $baseHref; ?>?m=log&a=view&id=<?php echo (int) $r['id']; ?>"
                      class="btn btn-sm action-btn action-btn--ver" title="Ver" aria-label="Ver" data-bs-toggle="tooltip"
                      data-bs-placement="top">
                      <i class="bi bi-eye"></i>
                    </a>
                  </div>
                </td>
                <td><?php echo htmlspecialchars(date('d/m/Y H:i:s', strtotime($r['data_hora']))); ?></td>
                <td><?php echo htmlspecialchars($r['usuario_nome'] ?? $r['usuario_id']); ?></td>
                <td><?php echo htmlspecialchars($r['ip']); ?></td>
                <td><?php echo htmlspecialchars($r['tabela_afetada']); ?></td>
                <td><?php echo htmlspecialchars($r['acao']); ?></td>
                <td><?php echo $formatDescricao($r['descricao']); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php if ($total > 0): ?>
  <div class="d-flex justify-content-between align-items-center mt-3">
    <nav aria-label="Paginacao">
      <ul class="pagination pagination-sm mb-0">
        <?php
        $queryBase = ['m' => 'log', 'a' => 'list'];
        if ($search !== '')
          $queryBase['q'] = $search;
        $prev = max(1, $page - 1);
        $next = min($totalPages, $page + 1);
        $maxLinks = 5;
        $startPage = max(1, $page - (int) floor($maxLinks / 2));
        $endPage = min($totalPages, $startPage + $maxLinks - 1);
        if ($endPage - $startPage + 1 < $maxLinks) {
          $startPage = max(1, $endPage - $maxLinks + 1);
        }
        ?>
        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
          <a class="page-link"
            href="<?php echo $baseHref . '?' . http_build_query(array_merge($queryBase, ['page' => 1])); ?>">&lt;&lt;</a>
        </li>
        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
          <a class="page-link"
            href="<?php echo $baseHref . '?' . http_build_query(array_merge($queryBase, ['page' => $prev])); ?>">&lt;</a>
        </li>
        <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
          <li class="page-item <?php echo $p == $page ? 'active' : ''; ?>">
            <a class="page-link"
              href="<?php echo $baseHref . '?' . http_build_query(array_merge($queryBase, ['page' => $p])); ?>"><?php echo $p; ?></a>
          </li>
        <?php endfor; ?>
        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
          <a class="page-link"
            href="<?php echo $baseHref . '?' . http_build_query(array_merge($queryBase, ['page' => $next])); ?>">&gt;</a>
        </li>
        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
          <a class="page-link"
            href="<?php echo $baseHref . '?' . http_build_query(array_merge($queryBase, ['page' => $totalPages])); ?>">&gt;&gt;</a>
        </li>
      </ul>
    </nav>
    <div class="text-muted small">Registros: <?php echo $total; ?></div>
  </div>
<?php endif; ?>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
      new bootstrap.Tooltip(tooltipTriggerEl);
    });
  });
</script>