<?php
// prevenção a erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// app/modules/pessoas/list.php

require_once __DIR__ . '/../../config/config.php'; // aqui vem $pdo

if (!current_user_is_at_least_admin()) {
  echo '<div class="alert alert-danger mt-3">Acesso negado. Apenas administradores podem acessar este módulo.</div>';
  include __DIR__ . '/../../templates/footer.php';
  exit;
}

$perPage = 30;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) {
  $page = 1;
}

$search = trim($_GET['search'] ?? '');
$params = [];
$where = ' WHERE 1 = 1 ';

if ($search !== '') {
  $searchClauses = [
    'p.nome LIKE :search1',
    'p.cpf_cnpj LIKE :search2',
    'p.email LIKE :search3',
  ];
  $params[':search1'] = '%' . $search . '%';
  $params[':search2'] = '%' . $search . '%';
  $params[':search3'] = '%' . $search . '%';

  // Busca por qualquer parte do telefone, ignorando a máscara: remove
  // ( ) - . e espaços tanto da coluna quanto do termo antes de comparar.
  $searchDigits = preg_replace('/\D/', '', $search);
  if ($searchDigits !== '') {
    $stripFone = static function (string $col): string {
      return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE($col, '(', ''), ')', ''), '-', ''), ' ', ''), '.', '')";
    };
    $searchClauses[] = $stripFone('p.fone_1') . ' LIKE :searchFone1';
    $searchClauses[] = $stripFone('p.fone_2') . ' LIKE :searchFone2';
    $searchClauses[] = $stripFone('p.fone_3') . ' LIKE :searchFone3';
    $params[':searchFone1'] = '%' . $searchDigits . '%';
    $params[':searchFone2'] = '%' . $searchDigits . '%';
    $params[':searchFone3'] = '%' . $searchDigits . '%';
  }

  $where .= ' AND (' . implode(' OR ', $searchClauses) . ') ';
}

// Advanced search filters
$advFields = [
  'adv_nome' => ['col' => 'p.nome', 'label' => 'Nome'],
  'adv_email' => ['col' => 'p.email', 'label' => 'E-mail'],
  'adv_cidade' => ['col' => 'p.cidade', 'label' => 'Cidade'],
  'adv_estado' => ['col' => 'p.estado', 'label' => 'Estado (UF)'],
  'adv_endereco' => ['col' => 'p.endereco', 'label' => 'Endereço'],
  'adv_bairro' => ['col' => 'p.bairro', 'label' => 'Bairro'],
  'adv_cep' => ['col' => 'p.cep', 'label' => 'CEP'],
  'adv_nascimento' => ['col' => 'p.nascimento', 'label' => 'Nascimento', 'type' => 'date'],
  'adv_perfil' => [
    'col' => 'p.perfil',
    'label' => 'Perfil',
    'type' => 'enum',
    'options' => ['SUPERADMIN', 'ADMIN', 'PROFESSOR', 'ALUNO']
  ],
  'adv_usuario' => [
    'col' => 'p.eh_usuario',
    'label' => 'É usuário?',
    'type' => 'enum',
    'options' => ['1' => 'Sim', '0' => 'Não']
  ],
];

$advActiveCount = 0;
foreach ($advFields as $key => $def) {
  $val = trim($_GET[$key] ?? '');
  $op = trim($_GET[$key . '_op'] ?? 'contains');
  if ($val === '' && !in_array($op, ['empty', 'notempty'], true))
    continue;
  $col = $def['col'];
  $paramKey = ':' . $key;
  switch ($op) {
    case 'equals':
      $where .= " AND {$col} = {$paramKey} ";
      $params[$paramKey] = $val;
      break;
    case 'startswith':
      $where .= " AND {$col} LIKE {$paramKey} ";
      $params[$paramKey] = $val . '%';
      break;
    case 'endswith':
      $where .= " AND {$col} LIKE {$paramKey} ";
      $params[$paramKey] = '%' . $val;
      break;
    case 'notcontains':
      $where .= " AND {$col} NOT LIKE {$paramKey} ";
      $params[$paramKey] = '%' . $val . '%';
      break;
    case 'empty':
      $where .= " AND ({$col} IS NULL OR {$col} = '') ";
      break;
    case 'notempty':
      $where .= " AND ({$col} IS NOT NULL AND {$col} != '') ";
      break;
    case 'gte':
      $where .= " AND {$col} >= {$paramKey} ";
      $params[$paramKey] = $val;
      break;
    case 'lte':
      $where .= " AND {$col} <= {$paramKey} ";
      $params[$paramKey] = $val;
      break;
    default: // contains
      $where .= " AND {$col} LIKE {$paramKey} ";
      $params[$paramKey] = '%' . $val . '%';
  }
  $advActiveCount++;
}

$hasClienteColumn = columnExists('101_pessoas', 'cliente');
$hasEhClienteColumn = columnExists('101_pessoas', 'eh_cliente');
$clienteColumn = $hasClienteColumn ? 'cliente' : ($hasEhClienteColumn ? 'eh_cliente' : null);
$hasCortesiaColumn = columnExists('101_pessoas', 'cortesia');
$hasMensalidadeColumn = columnExists('101_pessoas', 'mensalidade');
$hasFornecedorColumn = columnExists('101_pessoas', 'fornecedor');

$perfisFiltroValidos = ['SUPERADMIN', 'ADMIN', 'PROFESSOR', 'ALUNO'];
$perfilFiltro = trim($_GET['perfil'] ?? '');
$marcadorFilter = trim($_GET['marcador'] ?? '');
$clienteFiltro = $marcadorFilter === 'cliente';
$fornecedorFiltro = $marcadorFilter === 'fornecedor';
$usuarioFiltro = $marcadorFilter === 'usuario';
$semFiltro = $marcadorFilter === 'sem';
if ($perfilFiltro !== '' && in_array($perfilFiltro, $perfisFiltroValidos, true)) {
  $where .= ' AND perfil = :perfilFiltro ';
  $params[':perfilFiltro'] = $perfilFiltro;
}
$baseWhere = $where;
$baseParams = $params;
if ($clienteFiltro && $clienteColumn !== null) {
  $where .= " AND {$clienteColumn} = '1' ";
}
if ($fornecedorFiltro && $hasFornecedorColumn) {
  $where .= " AND fornecedor = '1' ";
}
if ($usuarioFiltro) {
  $where .= " AND eh_usuario = '1' ";
}
if ($semFiltro) {
  $conds = ["1=1"];
  if ($hasFornecedorColumn)
    $conds[] = "fornecedor != '1'";
  if ($clienteColumn !== null)
    $conds[] = "{$clienteColumn} != '1'";

  if (count($conds) > 1) {
    $where .= " AND (" . implode(" AND ", array_slice($conds, 1)) . ") ";
  }
}

// Tabs Logic
$activeTab = $_GET['tab'] ?? 'todos';
$tabJoin = "";
$tabWhere = "";

if ($activeTab === 'professores') {
  $tabJoin = " INNER JOIN 104_professores prof ON p.id = prof.pessoa ";
} elseif ($activeTab === 'alunos') {
  $tabJoin = " INNER JOIN 104_alunos alu ON p.id = alu.aluno ";
} elseif ($activeTab === 'usuarios') {
  $tabWhere = " AND p.eh_usuario = 1 ";
}

// Total de registros com filtros de busca e perfil (mas sem o filtro da aba em si)
$sqlCountBase = "SELECT COUNT(DISTINCT p.id) FROM 101_pessoas p " . $where;

// Counts para cada aba (respeitando busca e perfil)
$countTodos = (int) $pdo->prepare($sqlCountBase)->execute($params) ? (int) $pdo->prepare($sqlCountBase)->execute($params) : 0; // Placeholder, better below

function getTabCount($pdo, $baseWhere, $params, $join = "", $extraWhere = "")
{
  $sql = "SELECT COUNT(DISTINCT p.id) FROM 101_pessoas p {$join} {$baseWhere} {$extraWhere}";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  return (int) $stmt->fetchColumn();
}

$countTodos = getTabCount($pdo, $where, $params);
$countProfessores = getTabCount($pdo, $where, $params, " INNER JOIN 104_professores prof ON p.id = prof.pessoa ");
$countAlunos = getTabCount($pdo, $where, $params, " INNER JOIN 104_alunos alu ON p.id = alu.aluno ");
$countUsuarios = getTabCount($pdo, $where, $params, "", " AND p.eh_usuario = 1 ");

// total de registros da aba ativa
$sqlCount = "SELECT COUNT(DISTINCT p.id) FROM 101_pessoas p {$tabJoin} {$where} {$tabWhere}";
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$total = (int) $stmtCount->fetchColumn();

$totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
if ($page > $totalPages) {
  $page = $totalPages;
}

$offset = ($page - 1) * $perPage;

// Ordenacao
$allowedSort = [
  'id' => 'id',
  'nome' => 'nome',
  'cpf' => 'cpf_cnpj',
  'email' => 'p.email',
  'login' => 'login',
  'perfil' => 'perfil',
  'usuario' => 'eh_usuario',
  'nascimento' => 'nascimento',
  'idade' => 'nascimento',
  'data' => 'alteracao',
];
$sort = $_GET['sort'] ?? 'data';
$dir = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
$orderField = $allowedSort[$sort] ?? $allowedSort['data'];
$orderDirection = strtoupper($dir);
if ($sort === 'idade') {
  $orderDirection = $dir === 'asc' ? 'DESC' : 'ASC';
}
$baseHref = BASE_URL . 'index.php';
$returnParam = list_state_build_return_param(['m' => 'pessoas', 'a' => 'list']);
$clienteSelect = $hasClienteColumn ? 'cliente' : ($hasEhClienteColumn ? 'eh_cliente' : '0');
$buildSortUrl = function (string $field) use ($baseHref, $search, $perfilFiltro, $marcadorFilter, $sort, $dir, $advFields, $activeTab) {
  $newDir = ($sort === $field && $dir === 'asc') ? 'desc' : 'asc';
  $params = [
    'm' => 'pessoas',
    'a' => 'list',
    'sort' => $field,
    'dir' => $newDir,
  ];
  if ($search !== '') {
    $params['search'] = $search;
  }
  if ($perfilFiltro !== '') {
    $params['perfil'] = $perfilFiltro;
  }
  if ($marcadorFilter !== '') {
    $params['marcador'] = $marcadorFilter;
  }
  if ($activeTab !== 'todos') {
    $params['tab'] = $activeTab;
  }
  // Preserve advanced filters
  foreach (array_keys($advFields) as $key) {
    $v = trim($_GET[$key] ?? '');
    $o = trim($_GET[$key . '_op'] ?? '');
    if ($v !== '')
      $params[$key] = $v;
    if ($o !== '')
      $params[$key . '_op'] = $o;
  }
  return $baseHref . '?' . http_build_query($params);
};

// busca dos registros
$sql = "
    SELECT DISTINCT
        p.id,
        p.nome,
        p.nascimento,
        p.email AS email_principal,
        p.fone_1,
        p.login,
        p.perfil,
        {$clienteSelect} AS cliente,
        p.eh_usuario,
        p.endereco,
        p.numero,
        p.cidade,
        p.estado
    FROM 101_pessoas p
    {$tabJoin}
    {$where}
    {$tabWhere}
    ORDER BY {$orderField} {$orderDirection}, p.id DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
  $stmt->bindValue($k, $v, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$pessoas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pessoaIds = array_values(array_filter(array_unique(array_map(static function ($row) {
  return isset($row['id']) ? (int) $row['id'] : null;
}, $pessoas))));


$listBase = $baseHref . '?m=pessoas&a=list';
$formatDate = function (?string $value): string {
  $value = trim((string) $value);
  if ($value === '' || $value === '0000-00-00') {
    return '-';
  }
  try {
    $date = new DateTime($value);
    return $date->format('d/m/Y');
  } catch (Exception $e) {
    return '-';
  }
};
$calculateAge = function (?string $value): ?int {
  $value = trim((string) $value);
  if ($value === '' || $value === '0000-00-00') {
    return null;
  }
  try {
    $birth = new DateTime($value);
    $today = new DateTime('today');
    $age = $birth->diff($today)->y;
    return $age >= 0 ? $age : null;
  } catch (Exception $e) {
    return null;
  }
};
?>
<?php
?>
<style>
  .pessoas-actions {
    display: inline-flex;
    align-items: center;
    justify-content: flex-start;
    gap: 0.25rem;
    background: transparent !important;
  }


  @media (max-width: 767.98px) {
    .pessoas-actions {
      gap: 0.1rem;
      min-width: 140px;
    }

  }

  .table-responsive .table tbody td,
  .table-responsive .table tbody th {
    vertical-align: middle;
  }

  .link-bold {
    text-decoration: none !important;
    font-weight: 700;
    color: #000 !important;
  }

  .link-bold:hover {
    color: #000 !important;
  }

  .pagination .page-link {
    font-weight: 400;
    color: #0d6efd;
  }

  .pagination .page-link:hover,
  .pagination .page-link:focus {
    color: #0a58ca;
  }

  .pagination .page-item .page-link {
    border-color: #dee2e6;
  }

  .btn-whatsapp {
    background-color: #25D366 !important;
    color: white !important;
    border: none !important;
    box-shadow: none !important;
    outline: none !important;
  }

  .btn-whatsapp:hover {
    background-color: #128C7E !important;
    color: white !important;
  }

  .action-btn--whatsapp {
    border: none !important;
    color: #25D366 !important;
    background-color: transparent !important;
  }

  .action-btn--whatsapp:hover {
    background-color: #25D366 !important;
    color: white !important;
  }
</style>
<div class="row align-items-center mb-3 g-3">
  <div class="col-12 col-md-auto">
    <h4 class="mb-0"><?php echo $usuarioFiltro ? 'Usuários' : 'Pessoas'; ?></h4>
  </div>
  <div class="col-12 col-md">
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-md-end">
      <!-- Busca Simples -->
      <form method="get" class="d-flex gap-1 align-items-center flex-grow-1 flex-md-grow-0">
        <input type="hidden" name="m" value="pessoas">
        <input type="hidden" name="a" value="list">
        <input type="hidden" name="marcador" value="<?php echo htmlspecialchars($marcadorFilter); ?>">
        <select name="perfil" class="form-select form-select-sm" style="width: auto;">
          <option value="">Perfil...</option>
          <?php foreach ($perfisFiltroValidos as $pf): ?>
            <option value="<?php echo $pf; ?>" <?php echo $perfilFiltro === $pf ? 'selected' : ''; ?>>
              <?php echo $pf; ?>
            </option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Buscar..."
          value="<?php echo htmlspecialchars($search); ?>" style="min-width: 150px; flex: 1;">
        <button type="submit" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-search"></i>
        </button>
        <?php if ($search || $perfilFiltro): ?>
          <a href="<?php echo $baseHref; ?>?m=pessoas&a=list&marcador=<?php echo htmlspecialchars($marcadorFilter); ?>"
            class="btn btn-outline-danger btn-sm" title="Limpar busca">
            <i class="bi bi-x-lg"></i>
          </a>
        <?php endif; ?>
      </form>

      <!-- Busca Avançada -->
      <button type="button" class="btn btn-outline-info btn-sm flex-grow-1 flex-md-grow-0" id="btn-adv-search"
        data-bs-toggle="modal" data-bs-target="#modalAdvSearch" title="Busca Avançada">
        <i class="bi bi-funnel"></i> <span class="d-md-none d-lg-inline">Busca Avançada</span>
        <?php if ($advActiveCount > 0): ?>
          <span class="badge bg-info text-dark ms-1"><?php echo $advActiveCount; ?></span>
        <?php endif; ?>
      </button>

      <?php if ($advActiveCount > 0): ?>
        <a href="<?php echo $baseHref; ?>?m=pessoas&a=list<?php echo $search ? '&search=' . urlencode($search) : ''; ?>"
          class="btn btn-outline-warning btn-sm" title="Limpar busca avançada">
          <i class="bi bi-x-circle"></i>
        </a>
      <?php endif; ?>

      <!-- Ações em Massa e Novo -->
      <div class="btn-group flex-grow-1 flex-md-grow-0" id="pessoas-bulk-actions">
        <button type="button" class="btn btn-outline-danger btn-sm d-none" id="btn-bulk-delete" title="Apagar">
          <i class="bi bi-trash"></i>
        </button>
        <button type="button" class="btn btn-outline-primary btn-sm d-none" id="btn-bulk-update" title="Atualizar">
          <i class="bi bi-pencil"></i>
        </button>
        <a href="<?php echo $baseHref; ?>?m=pessoas&a=form<?php echo $returnParam; ?>"
          class="btn btn-primary btn-sm w-100">
          <i class="bi bi-plus-lg d-md-inline d-lg-none"></i>
          <span class="d-none d-lg-inline">+ Nov
            <?php echo $usuarioFiltro ? 'o usuário' : 'a pessoa'; ?>
          </span>
          <span class="d-inline d-md-none">+ Nov
            <?php echo $usuarioFiltro ? 'o usuário' : 'a pessoa'; ?>
          </span>
        </a>
      </div>
    </div>
  </div>
  <ul class="nav nav-tabs mb-3">
    <?php
    $tabs = [
      'todos' => ['label' => 'Todos', 'count' => $countTodos],
      'professores' => ['label' => 'Professores', 'count' => $countProfessores],
      'alunos' => ['label' => 'Alunos', 'count' => $countAlunos],
      'usuarios' => ['label' => 'Usuários', 'count' => $countUsuarios],
    ];

    foreach ($tabs as $key => $tab):
      $tabParams = array_merge($_GET, ['tab' => $key, 'page' => 1]);
      $url = $baseHref . '?' . http_build_query($tabParams);
      $active = ($activeTab === $key) ? 'active fw-bold' : '';
      ?>
      <li class="nav-item">
        <a class="nav-link <?php echo $active; ?>" href="<?php echo $url; ?>">
          <?php echo $tab['label']; ?>
          <span class="badge rounded-pill bg-light text-dark border ms-1"><?php echo $tab['count']; ?></span>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>

  <div class="card shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped table-hover table-sm mb-0">
          <thead class="table-light">
            <tr>
              <th style="width: 40px;" class="text-center">
                <input type="checkbox" class="form-check-input" id="bulk-select-all">
              </th>
              <th style="width: 220px;" class="text-start">Ações</th>
              <th>
                <a class="link-bold" href="<?php echo $buildSortUrl('nome'); ?>">
                  Nome <?php echo $sort === 'nome' ? ($dir === 'asc' ? '▲' : '▼') : ''; ?>
                </a>
              </th>
              <th class="text-nowrap">
                <a class="link-bold" href="<?php echo $buildSortUrl('contato'); ?>">
                  Contato <?php echo $sort === 'contato' ? ($dir === 'asc' ? '▲' : '▼') : ''; ?>
                </a>
              </th>
              <th class="text-nowrap">
                <a class="link-bold" href="<?php echo $buildSortUrl('nascimento'); ?>">
                  Nascimento <?php echo $sort === 'nascimento' ? ($dir === 'asc' ? '▲' : '▼') : ''; ?>
                </a>
              </th>
              <th class="text-end">
                <a class="link-bold" href="<?php echo $buildSortUrl('idade'); ?>">
                  Idade <?php echo $sort === 'idade' ? ($dir === 'asc' ? '▲' : '▼') : ''; ?>
                </a>
              </th>
              <th class="text-center">
                <a class="link-bold" href="<?php echo $buildSortUrl('usuario'); ?>">
                  Usuario? <?php echo $sort === 'usuario' ? ($dir === 'asc' ? '▲' : '▼') : ''; ?>
                </a>
              </th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($pessoas)): ?>
              <tr>
                <td colspan="9" class="text-center text-muted py-3">
                  Nenhum registro encontrado.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($pessoas as $p): ?>
                <?php
                $nascimentoValor = $p['nascimento'] ?? '';
                $idadeCompleta = $calculateAge($nascimentoValor);
                $street = trim($p['endereco'] ?? '');
                $numero = trim($p['numero'] ?? '');
                $cidade = trim($p['cidade'] ?? '');
                $estado = trim($p['estado'] ?? '');
                $addressParts = [];
                if ($street !== '') {
                  $addressParts[] = $street;
                }
                if ($numero !== '') {
                  $addressParts[] = 'nº ' . $numero;
                }
                if ($cidade !== '') {
                  $addressParts[] = $cidade;
                }
                if ($estado !== '') {
                  $addressParts[] = $estado;
                }
                $fullAddress = implode(', ', $addressParts);
                $mapsUrl = $fullAddress !== '' ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($fullAddress) : '';
                ?>
                <tr>
                  <td class="text-center">
                    <input type="checkbox" class="form-check-input bulk-select-item" value="<?php echo $p['id']; ?>">
                  </td>
                  <td class="text-start">
                    <div class="pessoas-actions d-flex gap-1 align-items-center">
                      <?php
                      $pessoaId = (int) $p['id'];
                      ?>
                      <a class="action-btn action-btn--ver"
                        href="<?php echo list_state_action_url('pessoas', 'view', ['id' => $pessoaId]); ?>" title="Ver"
                        data-bs-toggle="tooltip">
                        <i class="bi bi-eye"></i>
                      </a>
                      <a class="action-btn action-btn--editar"
                        href="<?php echo list_state_action_url('pessoas', 'form', ['id' => $pessoaId]); ?>" title="Editar"
                        data-bs-toggle="tooltip">
                        <i class="bi bi-pencil"></i>
                      </a>
                      <?php if (!$usuarioFiltro): ?>
                        <div class="dropdown">
                          <a href="javascript:void(0)" class="action-btn action-btn--mais" data-bs-toggle="dropdown"
                            data-bs-strategy="fixed" aria-expanded="false" title="Mais ações">
                            <i class="bi bi-three-dots-vertical"></i>
                          </a>
                          <ul class="dropdown-menu dropdown-menu-end action-kebab-menu">
                            <li><a class="dropdown-item"
                                href="<?php echo list_state_action_url('pessoas', 'form_copy', ['id' => $pessoaId]); ?>"><i
                                    class="bi bi-files text-secondary"></i> Copiar</a></li>
                            <?php if ($mapsUrl !== ''): ?>
                              <li><a class="dropdown-item" href="<?php echo htmlspecialchars($mapsUrl); ?>" target="_blank"
                                  rel="noopener"><i class="bi bi-geo-alt text-primary"></i> Mapa</a></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item"
                                href="<?php echo list_state_action_url('pessoas', 'delete', ['id' => $pessoaId]); ?>"
                                onclick="return confirm('Confirma excluir esta pessoa?');"><i
                                    class="bi bi-trash text-danger"></i> Excluir</a></li>
                          </ul>
                        </div>
                      <?php else: ?>
                        <div class="dropdown">
                          <a href="javascript:void(0)" class="action-btn action-btn--mais" data-bs-toggle="dropdown"
                            data-bs-strategy="fixed" aria-expanded="false" title="Mais ações">
                            <i class="bi bi-three-dots-vertical"></i>
                          </a>
                          <ul class="dropdown-menu dropdown-menu-end action-kebab-menu">
                            <li><a class="dropdown-item"
                                href="<?php echo list_state_action_url('pessoas', 'send_password', ['id' => $pessoaId]); ?>"><i
                                    class="bi bi-whatsapp text-success"></i> Mandar Senha</a></li>
                          </ul>
                        </div>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td>
                    <?php echo htmlspecialchars($p['nome']); ?>
                  </td>
                  <td>
                    <?php
                    $mail = $p['email_principal'] ?? '';
                    $fone = $p['fone_1'] ?? '';
                    if ($mail) {
                      echo '<div><a href="mailto:' . htmlspecialchars($mail) . '" class="text-decoration-none text-dark" target="_blank" rel="noopener">' . htmlspecialchars($mail) . '</a></div>';
                    }
                    if ($fone) {
                      $cleanPhone = preg_replace('/\D/', '', $fone);
                      if (strlen($cleanPhone) === 11 || strlen($cleanPhone) === 10)
                        $cleanPhone = '55' . $cleanPhone;
                      echo '<div><a href="https://wa.me/' . $cleanPhone . '" class="text-decoration-none text-dark" target="_blank" rel="noopener">' . htmlspecialchars($fone) . '</a></div>';
                    }
                    if (!$mail && !$fone) {
                      echo '<span class="text-muted">-</span>';
                    }
                    ?>
                  </td>
                  <td><?php echo $formatDate($nascimentoValor); ?></td>
                  <td class="text-end">
                    <?php if ($idadeCompleta !== null): ?>
                      <?php echo $idadeCompleta; ?>
                    <?php else: ?>
                      <span class="text-muted">-</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-center">
                    <?php if ($p['eh_usuario']): ?>
                      <span class="badge bg-success">Sim</span>
                    <?php else: ?>
                      <span class="badge bg-secondary">Não</span>
                    <?php endif; ?>
                    <?php if (!empty($p['perfil'])): ?>
                      <div class="mt-1 small">
                        <span class="badge bg-info text-dark"><?php echo htmlspecialchars($p['perfil']); ?></span>
                      </div>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <?php if ($total > 0): ?>
    <?php
    $queryBase = ['m' => 'pessoas', 'a' => 'list'];
    if ($search !== '')
      $queryBase['search'] = $search;
    if ($perfilFiltro !== '')
      $queryBase['perfil'] = $perfilFiltro;
    if ($marcadorFilter !== '')
      $queryBase['marcador'] = $marcadorFilter;
    if ($activeTab !== 'todos')
      $queryBase['tab'] = $activeTab;
    if (!empty($sort)) {
      $queryBase['sort'] = $sort;
      $queryBase['dir'] = $dir;
    }

    $pageUrl = function (int $p) use ($baseHref, $queryBase): string {
      return $baseHref . '?' . http_build_query(array_merge($queryBase, ['page' => $p]));
    };

    // Window: 5 pages around current
    $window = 2;
    $start = max(1, $page - $window);
    $end = min($totalPages, $page + $window);
    if ($end - $start < $window * 2) {
      $start = max(1, $end - $window * 2);
      $end = min($totalPages, $start + $window * 2);
    }

    $rangeFrom = ($page - 1) * $perPage + 1;
    $rangeTo = min($page * $perPage, $total);
    ?>
    <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
      <nav aria-label="Paginação">
        <ul class="pagination pagination-sm mb-0">

          <!-- Primeira página -->
          <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
            <a class="page-link" href="<?php echo $pageUrl(1); ?>" title="Primeira">&laquo;&laquo;</a>
          </li>
          <!-- Anterior -->
          <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
            <a class="page-link" href="<?php echo $pageUrl(max(1, $page - 1)); ?>">&laquo;</a>
          </li>

          <!-- Elipse inicial + pág 1 -->
          <?php if ($start > 1): ?>
            <li class="page-item <?php echo $page === 1 ? 'active' : ''; ?>">
              <a class="page-link link-bold" href="<?php echo $pageUrl(1); ?>">1</a>
            </li>
            <?php if ($start > 2): ?>
              <li class="page-item disabled"><span class="page-link">…</span></li>
            <?php endif; ?>
          <?php endif; ?>

          <!-- Páginas da janela -->
          <?php for ($p = $start; $p <= $end; $p++): ?>
            <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
              <a class="page-link link-bold" href="<?php echo $pageUrl($p); ?>"><?php echo $p; ?></a>
            </li>
          <?php endfor; ?>

          <!-- Elipse final + última pág -->
          <?php if ($end < $totalPages): ?>
            <?php if ($end < $totalPages - 1): ?>
              <li class="page-item disabled"><span class="page-link">…</span></li>
            <?php endif; ?>
            <li class="page-item <?php echo $page === $totalPages ? 'active' : ''; ?>">
              <a class="page-link link-bold" href="<?php echo $pageUrl($totalPages); ?>"><?php echo $totalPages; ?></a>
            </li>
          <?php endif; ?>

          <!-- Próxima -->
          <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
            <a class="page-link" href="<?php echo $pageUrl(min($totalPages, $page + 1)); ?>">&raquo;</a>
          </li>
          <!-- Última página -->
          <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
            <a class="page-link" href="<?php echo $pageUrl($totalPages); ?>" title="Última">&raquo;&raquo;</a>
          </li>

        </ul>
      </nav>
      <div class="text-muted small">
        Exibindo <strong><?php echo $rangeFrom; ?>–<?php echo $rangeTo; ?></strong>
        de <strong><?php echo $total; ?></strong> registros
      </div>
    </div>
  <?php endif; ?>



  <!-- Modal Busca Avançada -->
  <div class="modal fade" id="modalAdvSearch" tabindex="-1" aria-labelledby="modalAdvSearchLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header bg-info bg-opacity-10">
          <h5 class="modal-title" id="modalAdvSearchLabel"><i class="bi bi-funnel-fill me-2"></i>Busca Avançada —
            Pessoas
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3" id="adv-search-fields">

            <!-- Nome -->
            <div class="col-12">
              <label class="form-label fw-semibold">Nome</label>
              <div class="input-group input-group-sm">
                <select name="adv_nome_op" class="form-select adv-op-select" style="max-width:160px;">
                  <option value="contains" <?php echo (($_GET['adv_nome_op'] ?? '') == 'contains') ? 'selected' : ''; ?>>
                    Contém
                  </option>
                  <option value="equals" <?php echo (($_GET['adv_nome_op'] ?? '') == 'equals') ? 'selected' : ''; ?>>Igual
                    a
                  </option>
                  <option value="startswith" <?php echo (($_GET['adv_nome_op'] ?? '') == 'startswith') ? 'selected' : ''; ?>>
                    Começa com</option>
                  <option value="endswith" <?php echo (($_GET['adv_nome_op'] ?? '') == 'endswith') ? 'selected' : ''; ?>>
                    Termina
                    com</option>
                  <option value="notcontains" <?php echo (($_GET['adv_nome_op'] ?? '') == 'notcontains') ? 'selected' : ''; ?>>Não
                    contém</option>
                  <option value="empty" <?php echo (($_GET['adv_nome_op'] ?? '') == 'empty') ? 'selected' : ''; ?>>Está
                    vazio
                  </option>
                  <option value="notempty" <?php echo (($_GET['adv_nome_op'] ?? '') == 'notempty') ? 'selected' : ''; ?>>
                    Não
                    está
                    vazio</option>
                </select>
                <input type="text" name="adv_nome" class="form-control" placeholder="Digite..."
                  value="<?php echo htmlspecialchars($_GET['adv_nome'] ?? ''); ?>">
              </div>
            </div>

            <!-- E-mail -->
            <div class="col-12">
              <label class="form-label fw-semibold">E-mail</label>
              <div class="input-group input-group-sm">
                <select name="adv_email_op" class="form-select adv-op-select" style="max-width:160px;">
                  <option value="contains" <?php echo (($_GET['adv_email_op'] ?? '') == 'contains') ? 'selected' : ''; ?>>
                    Contém
                  </option>
                  <option value="equals" <?php echo (($_GET['adv_email_op'] ?? '') == 'equals') ? 'selected' : ''; ?>>
                    Igual
                    a
                  </option>
                  <option value="startswith" <?php echo (($_GET['adv_email_op'] ?? '') == 'startswith') ? 'selected' : ''; ?>>
                    Começa com</option>
                  <option value="endswith" <?php echo (($_GET['adv_email_op'] ?? '') == 'endswith') ? 'selected' : ''; ?>>
                    Termina
                    com</option>
                  <option value="notcontains" <?php echo (($_GET['adv_email_op'] ?? '') == 'notcontains') ? 'selected' : ''; ?>>
                    Não contém</option>
                  <option value="empty" <?php echo (($_GET['adv_email_op'] ?? '') == 'empty') ? 'selected' : ''; ?>>Está
                    vazio
                  </option>
                  <option value="notempty" <?php echo (($_GET['adv_email_op'] ?? '') == 'notempty') ? 'selected' : ''; ?>>
                    Não está
                    vazio</option>
                </select>
                <input type="text" name="adv_email" class="form-control" placeholder="Digite..."
                  value="<?php echo htmlspecialchars($_GET['adv_email'] ?? ''); ?>">
              </div>
            </div>

            <!-- CPF / CNPJ -->
            <div class="col-12">
              <label class="form-label fw-semibold">CPF / CNPJ</label>
              <div class="input-group input-group-sm">
                <select name="adv_cpf_op" class="form-select adv-op-select" style="max-width:160px;">
                  <option value="contains" <?php echo (($_GET['adv_cpf_op'] ?? '') == 'contains') ? 'selected' : ''; ?>>
                    Contém
                  </option>
                  <option value="equals" <?php echo (($_GET['adv_cpf_op'] ?? '') == 'equals') ? 'selected' : ''; ?>>Igual
                    a
                  </option>
                  <option value="startswith" <?php echo (($_GET['adv_cpf_op'] ?? '') == 'startswith') ? 'selected' : ''; ?>>
                    Começa
                    com</option>
                  <option value="empty" <?php echo (($_GET['adv_cpf_op'] ?? '') == 'empty') ? 'selected' : ''; ?>>Está
                    vazio
                  </option>
                  <option value="notempty" <?php echo (($_GET['adv_cpf_op'] ?? '') == 'notempty') ? 'selected' : ''; ?>>
                    Não
                    está
                    vazio</option>
                </select>
                <input type="text" name="adv_cpf" class="form-control" placeholder="Digite..."
                  value="<?php echo htmlspecialchars($_GET['adv_cpf'] ?? ''); ?>">
              </div>
            </div>

            <!-- Telefone -->
            <div class="col-12">
              <label class="form-label fw-semibold">Telefone</label>
              <div class="input-group input-group-sm">
                <select name="adv_fone_op" class="form-select adv-op-select" style="max-width:160px;">
                  <option value="contains" <?php echo (($_GET['adv_fone_op'] ?? '') == 'contains') ? 'selected' : ''; ?>>
                    Contém
                  </option>
                  <option value="equals" <?php echo (($_GET['adv_fone_op'] ?? '') == 'equals') ? 'selected' : ''; ?>>Igual
                    a
                  </option>
                  <option value="startswith" <?php echo (($_GET['adv_fone_op'] ?? '') == 'startswith') ? 'selected' : ''; ?>>
                    Começa com</option>
                  <option value="empty" <?php echo (($_GET['adv_fone_op'] ?? '') == 'empty') ? 'selected' : ''; ?>>Está
                    vazio
                  </option>
                  <option value="notempty" <?php echo (($_GET['adv_fone_op'] ?? '') == 'notempty') ? 'selected' : ''; ?>>
                    Não
                    está
                    vazio</option>
                </select>
                <input type="text" name="adv_fone" class="form-control" placeholder="Digite..."
                  value="<?php echo htmlspecialchars($_GET['adv_fone'] ?? ''); ?>">
              </div>
            </div>

            <!-- Cidade -->
            <div class="col-12">
              <label class="form-label fw-semibold">Cidade</label>
              <div class="input-group input-group-sm">
                <select name="adv_cidade_op" class="form-select adv-op-select" style="max-width:160px;">
                  <option value="contains" <?php echo (($_GET['adv_cidade_op'] ?? '') == 'contains') ? 'selected' : ''; ?>>
                    Contém
                  </option>
                  <option value="equals" <?php echo (($_GET['adv_cidade_op'] ?? '') == 'equals') ? 'selected' : ''; ?>>
                    Igual
                    a
                  </option>
                  <option value="startswith" <?php echo (($_GET['adv_cidade_op'] ?? '') == 'startswith') ? 'selected' : ''; ?>>
                    Começa com</option>
                  <option value="empty" <?php echo (($_GET['adv_cidade_op'] ?? '') == 'empty') ? 'selected' : ''; ?>>Está
                    vazio
                  </option>
                  <option value="notempty" <?php echo (($_GET['adv_cidade_op'] ?? '') == 'notempty') ? 'selected' : ''; ?>>
                    Não
                    está vazio</option>
                </select>
                <input type="text" name="adv_cidade" class="form-control" placeholder="Digite..."
                  value="<?php echo htmlspecialchars($_GET['adv_cidade'] ?? ''); ?>">
              </div>
            </div>

            <!-- Estado -->
            <div class="col-12">
              <label class="form-label fw-semibold">Estado (UF)</label>
              <div class="input-group input-group-sm">
                <select name="adv_estado_op" class="form-select adv-op-select" style="max-width:130px;">
                  <option value="equals" <?php echo (($_GET['adv_estado_op'] ?? 'equals') == 'equals') ? 'selected' : ''; ?>>Igual
                    a</option>
                  <option value="contains" <?php echo (($_GET['adv_estado_op'] ?? '') == 'contains') ? 'selected' : ''; ?>>
                    Contém
                  </option>
                  <option value="empty" <?php echo (($_GET['adv_estado_op'] ?? '') == 'empty') ? 'selected' : ''; ?>>Está
                    vazio
                  </option>
                  <option value="notempty" <?php echo (($_GET['adv_estado_op'] ?? '') == 'notempty') ? 'selected' : ''; ?>>
                    Não
                    está vazio</option>
                </select>
                <input type="text" name="adv_estado" class="form-control" maxlength="2" placeholder="SP, RJ..."
                  value="<?php echo htmlspecialchars($_GET['adv_estado'] ?? ''); ?>">
              </div>
            </div>

            <!-- Bairro -->
            <div class="col-12">
              <label class="form-label fw-semibold">Bairro</label>
              <div class="input-group input-group-sm">
                <select name="adv_bairro_op" class="form-select adv-op-select" style="max-width:160px;">
                  <option value="contains" <?php echo (($_GET['adv_bairro_op'] ?? '') == 'contains') ? 'selected' : ''; ?>>
                    Contém
                  </option>
                  <option value="equals" <?php echo (($_GET['adv_bairro_op'] ?? '') == 'equals') ? 'selected' : ''; ?>>
                    Igual
                    a
                  </option>
                  <option value="startswith" <?php echo (($_GET['adv_bairro_op'] ?? '') == 'startswith') ? 'selected' : ''; ?>>
                    Começa com</option>
                  <option value="empty" <?php echo (($_GET['adv_bairro_op'] ?? '') == 'empty') ? 'selected' : ''; ?>>Está
                    vazio
                  </option>
                  <option value="notempty" <?php echo (($_GET['adv_bairro_op'] ?? '') == 'notempty') ? 'selected' : ''; ?>>
                    Não
                    está vazio</option>
                </select>
                <input type="text" name="adv_bairro" class="form-control" placeholder="Digite..."
                  value="<?php echo htmlspecialchars($_GET['adv_bairro'] ?? ''); ?>">
              </div>
            </div>

            <!-- CEP -->
            <div class="col-12">
              <label class="form-label fw-semibold">CEP</label>
              <div class="input-group input-group-sm">
                <select name="adv_cep_op" class="form-select adv-op-select" style="max-width:130px;">
                  <option value="contains" <?php echo (($_GET['adv_cep_op'] ?? '') == 'contains') ? 'selected' : ''; ?>>
                    Contém
                  </option>
                  <option value="startswith" <?php echo (($_GET['adv_cep_op'] ?? '') == 'startswith') ? 'selected' : ''; ?>>
                    Começa
                    com</option>
                  <option value="equals" <?php echo (($_GET['adv_cep_op'] ?? '') == 'equals') ? 'selected' : ''; ?>>Igual
                    a
                  </option>
                  <option value="empty" <?php echo (($_GET['adv_cep_op'] ?? '') == 'empty') ? 'selected' : ''; ?>>Está
                    vazio
                  </option>
                  <option value="notempty" <?php echo (($_GET['adv_cep_op'] ?? '') == 'notempty') ? 'selected' : ''; ?>>
                    Não
                    está
                    vazio</option>
                </select>
                <input type="text" name="adv_cep" class="form-control" placeholder="00000-000"
                  value="<?php echo htmlspecialchars($_GET['adv_cep'] ?? ''); ?>">
              </div>
            </div>

            <!-- Nascimento -->
            <div class="col-12">
              <label class="form-label fw-semibold">Nascimento</label>
              <div class="input-group input-group-sm">
                <select name="adv_nascimento_op" class="form-select adv-op-select" style="max-width:160px;">
                  <option value="equals" <?php echo (($_GET['adv_nascimento_op'] ?? '') == 'equals') ? 'selected' : ''; ?>>
                    Igual a
                  </option>
                  <option value="gte" <?php echo (($_GET['adv_nascimento_op'] ?? '') == 'gte') ? 'selected' : ''; ?>>A
                    partir de
                  </option>
                  <option value="lte" <?php echo (($_GET['adv_nascimento_op'] ?? '') == 'lte') ? 'selected' : ''; ?>>Até
                  </option>
                  <option value="empty" <?php echo (($_GET['adv_nascimento_op'] ?? '') == 'empty') ? 'selected' : ''; ?>>
                    Está
                    vazio</option>
                  <option value="notempty" <?php echo (($_GET['adv_nascimento_op'] ?? '') == 'notempty') ? 'selected' : ''; ?>>Não
                    está vazio</option>
                </select>
                <input type="date" name="adv_nascimento" class="form-control"
                  value="<?php echo htmlspecialchars($_GET['adv_nascimento'] ?? ''); ?>">
              </div>
            </div>

            <!-- Perfil -->
            <div class="col-12">
              <label class="form-label fw-semibold">Perfil</label>
              <div class="input-group input-group-sm">
                <select name="adv_perfil_op" class="form-select adv-op-select" style="max-width:130px;">
                  <option value="equals" <?php echo (($_GET['adv_perfil_op'] ?? 'equals') == 'equals') ? 'selected' : ''; ?>>Igual
                    a</option>
                  <option value="empty" <?php echo (($_GET['adv_perfil_op'] ?? '') == 'empty') ? 'selected' : ''; ?>>Está
                    vazio
                  </option>
                  <option value="notempty" <?php echo (($_GET['adv_perfil_op'] ?? '') == 'notempty') ? 'selected' : ''; ?>>
                    Não
                    está vazio</option>
                </select>
                <select name="adv_perfil" class="form-control form-select">
                  <option value="">-- Qualquer --</option>
                  <?php foreach (['SUPERADMIN', 'ADMIN', 'PROFESSOR', 'ALUNO'] as $op): ?>
                    <option value="<?php echo $op; ?>" <?php echo (($_GET['adv_perfil'] ?? '') === $op) ? 'selected' : ''; ?>>
                      <?php echo $op; ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <!-- Sexo -->
            <div class="col-12">
              <label class="form-label fw-semibold">Sexo</label>
              <div class="input-group input-group-sm">
                <select name="adv_sexo_op" class="form-select adv-op-select" style="max-width:130px;">
                  <option value="equals" <?php echo (($_GET['adv_sexo_op'] ?? 'equals') == 'equals') ? 'selected' : ''; ?>>
                    Igual a
                  </option>
                  <option value="empty" <?php echo (($_GET['adv_sexo_op'] ?? '') == 'empty') ? 'selected' : ''; ?>>Está
                    vazio
                  </option>
                  <option value="notempty" <?php echo (($_GET['adv_sexo_op'] ?? '') == 'notempty') ? 'selected' : ''; ?>>
                    Não
                    está
                    vazio</option>
                </select>
                <select name="adv_sexo" class="form-control form-select">
                  <option value="">-- Qualquer --</option>
                  <option value="Masculino" <?php echo (($_GET['adv_sexo'] ?? '') === 'Masculino') ? 'selected' : ''; ?>>
                    Masculino
                  </option>
                  <option value="Feminino" <?php echo (($_GET['adv_sexo'] ?? '') === 'Feminino') ? 'selected' : ''; ?>>
                    Feminino
                  </option>
                </select>
              </div>
            </div>

            <!-- É Usuário? -->
            <div class="col-12">
              <label class="form-label fw-semibold">É usuário do sistema?</label>
              <div class="input-group input-group-sm">
                <select name="adv_usuario_op" class="form-select adv-op-select" style="max-width:130px;">
                  <option value="equals" <?php echo (($_GET['adv_usuario_op'] ?? 'equals') == 'equals') ? 'selected' : ''; ?>>
                    Igual a</option>
                </select>
                <select name="adv_usuario" class="form-control form-select">
                  <option value="">-- Qualquer --</option>
                  <option value="1" <?php echo (($_GET['adv_usuario'] ?? '') === '1') ? 'selected' : ''; ?>>Sim</option>
                  <option value="0" <?php echo (($_GET['adv_usuario'] ?? '') === '0') ? 'selected' : ''; ?>>Não</option>
                </select>
              </div>
            </div>

          </div><!-- /.row -->
        </div>
        <div class="modal-footer">
          <a href="<?php echo $baseHref; ?>?m=pessoas&a=list" class="btn btn-outline-secondary btn-sm">Limpar
            filtros</a>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-info" id="btn-adv-search-submit"><i class="bi bi-funnel"></i>
            Buscar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Bulk Update -->
  <div class="modal fade" id="modalBulkUpdate" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Atualizar Selecionados</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-warning small">
            <i class="bi bi-info-circle"></i> Apenas os campos preenchidos serão atualizados nos registros selecionados.
          </div>
          <form id="formBulkUpdate">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Nome</label>
                <input type="text" name="nome" class="form-control" placeholder="(Não alterar)">
              </div>
              <div class="col-md-6">
                <label class="form-label">Razão Social</label>
                <input type="text" name="razao_social" class="form-control" placeholder="(Não alterar)">
              </div>

              <div class="col-md-3">
                <label class="form-label">Data de Nascimento</label>
                <input type="date" name="data_nascimento" class="form-control">
              </div>
              <div class="col-md-3">
                <label class="form-label">Sexo</label>
                <select name="sexo" class="form-select">
                  <option value="">(Não alterar)</option>
                  <option value="Masculino">Masculino</option>
                  <option value="Feminino">Feminino</option>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">CPF/CNPJ</label>
                <input type="text" name="cpf_cnpj" class="form-control" placeholder="(Não alterar)">
              </div>
              <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                  <option value="">(Não alterar)</option>
                  <option value="ATIVO">ATIVO</option>
                  <option value="INATIVO">INATIVO</option>
                </select>
              </div>

              <div class="col-md-6">
                <label class="form-label">Telefone</label>
                <input type="text" name="telefone" class="form-control" placeholder="(Não alterar)">
              </div>
              <div class="col-md-6">
                <label class="form-label">E-mail</label>
                <input type="email" name="email" class="form-control"
                  placeholder="(Não é possível alterar e-mail principal por aqui)">
              </div>

              <div class="col-md-6">
                <label class="form-label">Site</label>
                <input type="text" name="site" class="form-control" placeholder="(Não alterar)">
              </div>
              <div class="col-md-6">
                <label class="form-label">Área de Atuação</label>
                <input type="text" name="area_atuacao" class="form-control" placeholder="(Não alterar)">
              </div>

              <div class="col-md-2">
                <label class="form-label">CEP</label>
                <input type="text" name="cep" class="form-control" placeholder="(Não alterar)">
              </div>
              <div class="col-md-4">
                <label class="form-label">Endereço</label>
                <input type="text" name="endereco" class="form-control" placeholder="(Não alterar)">
              </div>
              <div class="col-md-2">
                <label class="form-label">Número</label>
                <input type="text" name="nr" class="form-control" placeholder="(Não alterar)">
              </div>
              <div class="col-md-4">
                <label class="form-label">Complemento</label>
                <input type="text" name="complemento" class="form-control" placeholder="(Não alterar)">
              </div>

              <div class="col-md-5">
                <label class="form-label">Bairro</label>
                <input type="text" name="bairro" class="form-control" placeholder="(Não alterar)">
              </div>
              <div class="col-md-5">
                <label class="form-label">Cidade</label>
                <input type="text" name="cidade" class="form-control" placeholder="(Não alterar)">
              </div>
              <div class="col-md-2">
                <label class="form-label">Estado</label>
                <input type="text" name="estado" class="form-control" placeholder="(UF)">
              </div>

              <div class="col-md-12">
                <label class="form-label">Observações</label>
                <textarea name="observacao" class="form-control" rows="2" placeholder="(Não alterar)"></textarea>
              </div>

              <div class="col-md-3">
                <label class="form-label">Mensalidade (R$)</label>
                <input type="text" name="mensalidade" class="form-control" data-mask="money"
                  placeholder="(Não alterar)">
              </div>
              <div class="col-md-3">
                <label class="form-label">Periodicidade</label>
                <input type="number" name="periodicidade" class="form-control" placeholder="(Não alterar)">
              </div>

              <div class="col-12">
                <hr>
                <div class="form-label mb-2 fw-bold">Funções e Acesso</div>

                <div class="row g-3">
                  <div class="col-md-6">
                    <div class="form-check form-check-inline">
                      <input class="form-check-input" type="checkbox" id="chk_cliente" name="cliente" value="1">
                      <label class="form-check-label" for="chk_cliente">Definir como Cliente</label>
                    </div>
                    <div class="form-check form-check-inline">
                      <input class="form-check-input" type="checkbox" id="chk_fornecedor" name="fornecedor" value="1">
                      <label class="form-check-label" for="chk_fornecedor">Definir como Fornecedor</label>
                    </div>
                    <div class="form-check form-check-inline">
                      <input class="form-check-input" type="checkbox" id="chk_cortesia" name="cortesia" value="1">
                      <label class="form-check-label" for="chk_cortesia">Definir como Cortesia</label>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <div class="form-check mb-2">
                      <input class="form-check-input" type="checkbox" name="eh_usuario" value="1" id="chk_eh_usuario">
                      <label class="form-check-label" for="chk_eh_usuario">Definir como Usuário do Sistema</label>
                    </div>
                    <div class="row g-2">
                      <div class="col-6">
                        <label class="form-label small">Login</label>
                        <input type="text" name="login" class="form-control form-control-sm"
                          placeholder="(Não alterar)">
                      </div>
                      <div class="col-6">
                        <label class="form-label small">Perfil</label>
                        <select name="perfil" class="form-select form-select-sm">
                          <option value="">(Não alterar)</option>
                          <option value="SUPERADMIN">SUPERADMIN</option>
                          <option value="ADMIN">ADMIN</option>
                          <option value="PROFESSOR">PROFESSOR</option>
                          <option value="ALUNO">ALUNO</option>
                        </select>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-primary" id="btn-confirm-bulk-update">Atualizar Registros</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Advanced Search Modal handler
    (function () {
      var btnSubmit = document.getElementById('btn-adv-search-submit');
      if (!btnSubmit) return;
      btnSubmit.addEventListener('click', function () {
        var modal = document.getElementById('modalAdvSearch');
        var fields = modal.querySelectorAll('input[name], select[name]');
        var params = new URLSearchParams(window.location.search);
        // Remove previous adv params
        var keysToRemove = [];
        params.forEach(function (v, k) { if (k.startsWith('adv_')) keysToRemove.push(k); });
        keysToRemove.forEach(function (k) { params.delete(k); });
        params.delete('page');
        params.set('m', 'pessoas');
        params.set('a', 'list');
        fields.forEach(function (f) {
          if (f.name && f.name.startsWith('adv_')) {
            if (f.value !== '') {
              params.set(f.name, f.value);
            }
          }
        });
        // Close modal
        var bsModal = bootstrap.Modal.getInstance(modal);
        if (bsModal) bsModal.hide();
        window.location.href = 'index.php?' + params.toString();
      });
    })();

    document.addEventListener('DOMContentLoaded', function () {
      var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
      tooltipTriggerList.forEach(function (tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
      });

      // Bulk selection logic
      const selectAllDetails = document.getElementById('bulk-select-all');
      const checkboxes = document.querySelectorAll('.bulk-select-item');
      const btnDelete = document.getElementById('btn-bulk-delete');
      const btnUpdate = document.getElementById('btn-bulk-update');

      // Modal Update
      const modalBulkUpdateEl = document.getElementById('modalBulkUpdate');
      const modalBulkUpdate = new bootstrap.Modal(modalBulkUpdateEl);
      const btnConfirmUpdate = document.getElementById('btn-confirm-bulk-update');

      function updateButtons() {
        const checkedCount = document.querySelectorAll('.bulk-select-item:checked').length;
        if (checkedCount > 0) {
          btnDelete.classList.remove('d-none');
          btnUpdate.classList.remove('d-none');
        } else {
          btnDelete.classList.add('d-none');
          btnUpdate.classList.add('d-none');
        }
      }

      if (selectAllDetails) {
        selectAllDetails.addEventListener('change', function () {
          checkboxes.forEach(cb => cb.checked = this.checked);
          updateButtons();
        });
      }

      checkboxes.forEach(cb => {
        cb.addEventListener('change', updateButtons);
      });

      // Delete Action
      if (btnDelete) {
        btnDelete.addEventListener('click', function () {
          if (!confirm('Tem certeza que deseja apagar os registros selecionados?')) return;

          const selectedIds = Array.from(document.querySelectorAll('.bulk-select-item:checked')).map(cb => cb.value);

          // Send AJAX request
          const formData = new FormData();
          formData.append('action', 'delete');
          selectedIds.forEach(id => formData.append('ids[]', id));

          fetch('app/modules/pessoas/batch_action.php', {
            method: 'POST',
            body: formData
          })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                alert(data.message);
                window.location.reload();
              } else {
                alert('Erro: ' + data.message);
              }
            })
            .catch(err => {
              console.error(err);
              alert('Erro ao processar solicitação.');
            });
        });
      }

      // Update Action
      if (btnUpdate) {
        btnUpdate.addEventListener('click', function () {
          modalBulkUpdate.show();
        });
      }

      if (btnConfirmUpdate) {
        btnConfirmUpdate.addEventListener('click', function () {
          const selectedIds = Array.from(document.querySelectorAll('.bulk-select-item:checked')).map(cb => cb.value);
          const form = document.getElementById('formBulkUpdate');
          const formData = new FormData(form);

          formData.append('action', 'update');
          selectedIds.forEach(id => formData.append('ids[]', id));

          fetch('app/modules/pessoas/batch_action.php', {
            method: 'POST',
            body: formData
          })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                alert(data.message);
                window.location.reload();
              } else {
                alert('Erro: ' + data.message);
              }
            })
            .catch(err => {
              console.error(err);
              alert('Erro ao processar solicitação.');
            });
        });
      }
    });
  </script>