<?php
// app/modules/101_pessoas/relatorio.php

require_once __DIR__ . '/../../config/config.php';

if (!current_user_is_at_least_admin()) {
    echo '<div class="alert alert-danger mt-3">Acesso negado. Apenas administradores podem acessar este módulo.</div>';
    include __DIR__ . '/../../templates/footer.php';
    exit;
}

$baseHref = BASE_URL . 'index.php';

$nomeFiltro = trim($_GET['nome'] ?? '');
$emailFiltro = trim($_GET['email'] ?? '');
$telefoneFiltro = trim($_GET['telefone'] ?? '');

$where = [];
$params = [];

$sql = "
    SELECT 
        p.id, 
        p.nome, 
        p.email AS email,
        p.fone_1 AS telefone
    FROM 101_pessoas p
    WHERE 1=1
";

if ($nomeFiltro !== '') {
  $sql .= " AND p.nome LIKE :nome";
  $params[':nome'] = '%' . $nomeFiltro . '%';
}

if ($emailFiltro !== '') {
  $sql .= " AND p.email LIKE :email";
  $params[':email'] = '%' . $emailFiltro . '%';
}

if ($telefoneFiltro !== '') {
  $sql .= " AND p.fone_1 LIKE :telefone";
  $params[':telefone'] = '%' . $telefoneFiltro . '%';
}

$sql .= " ORDER BY p.nome ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pessoas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$queryParams = [
  'm' => '101_pessoas',
  'a' => 'relatorio_pdf',
];
if ($nomeFiltro !== '') {
  $queryParams['nome'] = $nomeFiltro;
}
if ($emailFiltro !== '') {
  $queryParams['email'] = $emailFiltro;
}
if ($telefoneFiltro !== '') {
  $queryParams['telefone'] = $telefoneFiltro;
}
$pdfUrl = $baseHref . '?' . http_build_query($queryParams);

// Log de acesso ao relatório (HTML)
log_event(
  $pdo,
  '101_pessoas',
  'RELATORIO',
  'Relatório simples - filtros: ' . json_encode($queryParams, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">Relatório de Pessoas</h4>
  <a class="btn btn-outline-secondary btn-sm" href="<?php echo $pdfUrl; ?>" target="_blank" rel="noopener">Imprimir
    PDF</a>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form method="get">
      <input type="hidden" name="m" value="101_pessoas">
      <input type="hidden" name="a" value="relatorio">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Nome</label>
          <input type="text" name="nome" class="form-control" value="<?php echo htmlspecialchars($nomeFiltro); ?>"
            placeholder="Digite um nome">
        </div>
        <div class="col-md-4">
          <label class="form-label">E-mail</label>
          <input type="text" name="email" class="form-control" value="<?php echo htmlspecialchars($emailFiltro); ?>"
            placeholder="Digite um e-mail">
        </div>
        <div class="col-md-4">
          <label class="form-label">Telefone</label>
          <input type="text" name="telefone" class="form-control"
            value="<?php echo htmlspecialchars($telefoneFiltro); ?>" placeholder="Digite um telefone">
        </div>
      </div>
      <div class="mt-3 text-end">
        <button type="submit" class="btn btn-primary">Buscar</button>
      </div>
    </form>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-striped table-sm mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:80px;">ID</th>
            <th>Nome</th>
            <th>E-mail</th>
            <th>Telefone</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($pessoas)): ?>
            <tr>
              <td colspan="4" class="text-center text-muted py-3">Nenhum registro encontrado.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($pessoas as $p): ?>
              <tr>
                <td><?php echo (int) $p['id']; ?></td>
                <td><?php echo htmlspecialchars($p['nome'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($p['email'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($p['telefone'] ?? ''); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>