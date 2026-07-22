<?php
// app/modules/101_pessoas/relatorio_completo.php

require_once __DIR__ . '/../../config/config.php';

$baseHref = BASE_URL . 'index.php';

$nomeFiltro = trim($_GET['nome'] ?? '');
$emailFiltro = trim($_GET['email'] ?? '');
$enderecoFiltro = trim($_GET['endereco'] ?? '');
$bairroFiltro = trim($_GET['bairro'] ?? '');
$cidadeFiltro = trim($_GET['cidade'] ?? '');
$estadoFiltro = trim($_GET['estado'] ?? '');
$perfilFiltro = trim($_GET['perfil'] ?? '');

$where = [];
$params = [];

if ($nomeFiltro !== '') {
  $where[] = 'nome LIKE :nome';
  $params[':nome'] = '%' . $nomeFiltro . '%';
}
if ($emailFiltro !== '') {
  $where[] = 'email LIKE :email';
  $params[':email'] = '%' . $emailFiltro . '%';
}
if ($enderecoFiltro !== '') {
  $where[] = 'endereco LIKE :endereco';
  $params[':endereco'] = '%' . $enderecoFiltro . '%';
}
if ($bairroFiltro !== '') {
  $where[] = 'bairro LIKE :bairro';
  $params[':bairro'] = '%' . $bairroFiltro . '%';
}
if ($cidadeFiltro !== '') {
  $where[] = 'cidade LIKE :cidade';
  $params[':cidade'] = '%' . $cidadeFiltro . '%';
}
if ($estadoFiltro !== '') {
  $where[] = 'estado LIKE :estado';
  $params[':estado'] = '%' . $estadoFiltro . '%';
}

$perfisValidos = ['SUPERADMIN', 'ADMIN', 'OPERACIONAL', 'CONSULTA', 'CLIENTE'];
if ($perfilFiltro !== '' && in_array($perfilFiltro, $perfisValidos, true)) {
  $where[] = 'perfil = :perfil';
  $params[':perfil'] = $perfilFiltro;
} else {
  $perfilFiltro = '';
}

$whereClause = '';
if (!empty($where)) {
  $whereClause = 'WHERE ' . implode(' AND ', $where);
}

$sql = "SELECT * FROM 101_pessoas {$whereClause} ORDER BY nome ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pessoas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$queryParams = ['m' => '101_pessoas', 'a' => 'relatorio_completo_pdf'];
if ($nomeFiltro !== '')
  $queryParams['nome'] = $nomeFiltro;
if ($emailFiltro !== '')
  $queryParams['email'] = $emailFiltro;
if ($enderecoFiltro !== '')
  $queryParams['endereco'] = $enderecoFiltro;
if ($bairroFiltro !== '')
  $queryParams['bairro'] = $bairroFiltro;
if ($cidadeFiltro !== '')
  $queryParams['cidade'] = $cidadeFiltro;
if ($estadoFiltro !== '')
  $queryParams['estado'] = $estadoFiltro;
if ($perfilFiltro !== '')
  $queryParams['perfil'] = $perfilFiltro;
$pdfUrl = $baseHref . '?' . http_build_query($queryParams);

// Junction tables are gone, extra info is in the main record
$emailsMap = [];
$fonesMap = [];

log_event(
  $pdo,
  '101_pessoas',
  'RELATORIO',
  'Relatório completo - filtros: ' . json_encode($queryParams, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);

$fmtCpfCnpj = function (string $doc): string {
  $digits = preg_replace('/\D+/', '', $doc);
  if (strlen($digits) === 11) {
    return substr($digits, 0, 3) . '.' . substr($digits, 3, 3) . '.' . substr($digits, 6, 3) . '-' . substr($digits, 9, 2);
  }
  if (strlen($digits) === 14) {
    return substr($digits, 0, 2) . '.' . substr($digits, 2, 3) . '.' . substr($digits, 5, 3) . '/' . substr($digits, 8, 4) . '-' . substr($digits, 12, 2);
  }
  return $doc;
};

?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">Relatório completo de 101_pessoas</h4>
  <a class="btn btn-outline-secondary btn-sm" href="<?php echo $pdfUrl; ?>" target="_blank" rel="noopener">Imprimir
    PDF</a>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form method="get">
      <input type="hidden" name="m" value="101_pessoas">
      <input type="hidden" name="a" value="relatorio_completo">
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
          <label class="form-label">Endereço</label>
          <input type="text" name="endereco" class="form-control"
            value="<?php echo htmlspecialchars($enderecoFiltro); ?>" placeholder="Digite um endereço">
        </div>
        <div class="col-md-4">
          <label class="form-label">Bairro</label>
          <input type="text" name="bairro" class="form-control" value="<?php echo htmlspecialchars($bairroFiltro); ?>"
            placeholder="Digite um bairro">
        </div>
        <div class="col-md-4">
          <label class="form-label">Cidade</label>
          <input type="text" name="cidade" class="form-control" value="<?php echo htmlspecialchars($cidadeFiltro); ?>"
            placeholder="Digite uma cidade">
        </div>
        <div class="col-md-4">
          <label class="form-label">Estado</label>
          <input type="text" name="estado" class="form-control" value="<?php echo htmlspecialchars($estadoFiltro); ?>"
            placeholder="Digite um estado">
        </div>
        <div class="col-md-4">
          <label class="form-label">Perfil</label>
          <select name="perfil" class="form-select">
            <option value="">Todos</option>
            <?php foreach ($perfisValidos as $perf): ?>
              <option value="<?php echo $perf; ?>" <?php echo $perfilFiltro === $perf ? 'selected' : ''; ?>>
                <?php echo $perf; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="mt-3 text-end">
        <button type="submit" class="btn btn-primary">Buscar</button>
      </div>
    </form>
  </div>
</div>

<?php if (empty($pessoas)): ?>
  <div class="alert alert-info">Nenhuma pessoa encontrada.</div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($pessoas as $pessoa): ?>
      <div class="col-12">
        <div class="card shadow-sm">
          <div class="card-body">
            <div class="fw-bold mb-2">#<?php echo (int) $pessoa['id']; ?> - <?php echo htmlspecialchars($pessoa['nome']); ?>
            </div>
            <?php
            $linhas = [];
            if (!empty($pessoa['cpf_cnpj']))
              $linhas[] = ['CPF/CNPJ', $fmtCpfCnpj($pessoa['cpf_cnpj'])];
            if (!empty($pessoa['rg']))
              $linhas[] = ['RG', $pessoa['rg']];

            if (!empty($pessoa['email']))
              $linhas[] = ['E-mail', $pessoa['email']];
            if (!empty($pessoa['cep']))
              $linhas[] = ['CEP', $pessoa['cep']];
            if (!empty($pessoa['endereco']))
              $linhas[] = ['Endereço', $pessoa['endereco']];
            if (!empty($pessoa['numero']))
              $linhas[] = ['Número', $pessoa['numero']];
            if (!empty($pessoa['complemento']))
              $linhas[] = ['Complemento', $pessoa['complemento']];
            if (!empty($pessoa['bairro']))
              $linhas[] = ['Bairro', $pessoa['bairro']];
            if (!empty($pessoa['cidade']))
              $linhas[] = ['Cidade', $pessoa['cidade']];
            if (!empty($pessoa['estado']))
              $linhas[] = ['Estado', $pessoa['estado']];
            if (!empty($pessoa['observacao']))
              $linhas[] = ['Observações', nl2br(htmlspecialchars($pessoa['observacao']))];
            if (!empty($pessoa['cliente']))
              $linhas[] = ['Cliente', 'Sim'];
            if (!empty($pessoa['eh_usuario'])) {
              $linhas[] = ['Acesso', 'Sim'];
              if (!empty($pessoa['login']))
                $linhas[] = ['Login', $pessoa['login']];
              if (!empty($pessoa['perfil']))
                $linhas[] = ['Perfil', $pessoa['perfil']];
            }
            if (!empty($pessoa['inclusao']))
              $linhas[] = ['Incluído em', date('d/m/Y H:i', strtotime($pessoa['inclusao']))];
            if (!empty($pessoa['alteracao']))
              $linhas[] = ['Alterado em', date('d/m/Y H:i', strtotime($pessoa['alteracao']))];
            ?>
            <div class="row row-cols-1 row-cols-md-2 g-2 small">
              <?php foreach ($linhas as [$rotulo, $valor]): ?>
                <div class="col">
                  <span class="fw-bold"><?php echo htmlspecialchars($rotulo); ?>:</span>
                  <span><?php echo $valor; ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>