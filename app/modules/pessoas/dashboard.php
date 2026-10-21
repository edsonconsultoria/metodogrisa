<?php
// app/modules/pessoas/dashboard.php

require_once __DIR__ . '/../../config/config.php';

if (!current_user_is_at_least_admin()) {
    echo '<div class="alert alert-danger mt-3">Acesso negado. Apenas administradores podem acessar este módulo.</div>';
    include __DIR__ . '/../../templates/footer.php';
    exit;
}

$baseHref = BASE_URL . 'index.php';

// Helper function to safely count rows in table
function safeCount(PDO $pdo, string $table, string $where = '1=1', array $params = []): int {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

// 1. KPI Statistics
$totalPessoas = safeCount($pdo, '101_pessoas');
$totalUsuarios = safeCount($pdo, '101_pessoas', "eh_usuario = 1 OR (perfil IS NOT NULL AND perfil != '')");
$totalAniversariantesMes = safeCount($pdo, '101_pessoas', "nascimento IS NOT NULL AND nascimento != '0000-00-00' AND MONTH(nascimento) = MONTH(CURRENT_DATE())");
$totalInteressados = safeCount($pdo, '101_interessados');
$totalMailling = safeCount($pdo, '101_mailling');
$totalMaillingEnvios = safeCount($pdo, '101_mailling_envios');
$totalMaillingListas = safeCount($pdo, '101_mailling_lista');

// Classification breakdown
$totalClientes = safeCount($pdo, '101_pessoas', "cliente = '1' OR cliente = 1");
$totalFornecedores = safeCount($pdo, '101_pessoas', "fornecedor = '1' OR fornecedor = 1");
$totalAlunos = safeCount($pdo, '101_pessoas', "aluno_unidade_fpolis = '1' OR aluno_outras_unidades = '1'");
$totalProfessores = safeCount($pdo, '101_pessoas', "professor_fpolis = '1' OR professor_unidades = '1'");
$totalCursoInteressados = safeCount($pdo, '101_pessoas', "interessado_no_curso = '1'");

// 2. Profile Distribution Data for BI Chart
$profileLabels = [];
$profileCounts = [];
try {
    $stmt = $pdo->query("SELECT COALESCE(NULLIF(perfil, ''), 'Sem Perfil') as p_name, COUNT(*) as qty FROM 101_pessoas GROUP BY p_name ORDER BY qty DESC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $profileLabels[] = $row['p_name'];
        $profileCounts[] = (int) $row['qty'];
    }
} catch (Throwable $e) {}

// 3. Top Cities for BI Chart
$cityLabels = [];
$cityCounts = [];
try {
    $stmt = $pdo->query("SELECT COALESCE(NULLIF(cidade, ''), 'Não Informada') as c_name, COUNT(*) as qty FROM 101_pessoas GROUP BY c_name ORDER BY qty DESC LIMIT 7");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cityLabels[] = $row['c_name'];
        $cityCounts[] = (int) $row['qty'];
    }
} catch (Throwable $e) {}

// 4. Upcoming Birthdays (Current/Next days)
$upcomingBirthdays = [];
try {
    $sqlBirth = "SELECT id, nome, nascimento, fone_1, email FROM 101_pessoas 
                 WHERE nascimento IS NOT NULL AND nascimento != '0000-00-00' 
                   AND (MONTH(nascimento) > MONTH(CURRENT_DATE()) OR (MONTH(nascimento) = MONTH(CURRENT_DATE()) AND DAY(nascimento) >= DAY(CURRENT_DATE())))
                 ORDER BY MONTH(nascimento) ASC, DAY(nascimento) ASC LIMIT 5";
    $upcomingBirthdays = $pdo->query($sqlBirth)->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

// 5. Recent Registers
$recentPessoas = [];
try {
    $sqlRecent = "SELECT id, nome, email, fone_1, cidade, estado, perfil FROM 101_pessoas ORDER BY id DESC LIMIT 5";
    $recentPessoas = $pdo->query($sqlRecent)->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}
?>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<div class="container-fluid py-4">
  <!-- Banner Header -->
  <div class="card border-0 shadow-sm p-4 rounded-4 mb-4" style="background: linear-gradient(135deg, #0d0c31 0%, #1e3a8a 100%); color: white;">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
      <div>
        <h2 class="fw-bold mb-1"><i class="bi bi-people-fill me-2"></i>Dashboard de Pessoas & BI</h2>
        <p class="opacity-75 mb-0">Central unificada de navegação, relatórios e inteligência de dados de pessoas.</p>
      </div>
      <div class="mt-3 mt-md-0 d-flex gap-2">
        <a href="<?php echo $baseHref; ?>?m=pessoas&a=form" class="btn btn-light btn-sm fw-bold px-3 shadow-sm">
          <i class="bi bi-person-plus-fill me-1"></i> Nova Pessoa
        </a>
        <a href="<?php echo $baseHref; ?>?m=pessoas&a=relatorio_aniversariantes" class="btn btn-outline-light btn-sm px-3">
          <i class="bi bi-cake2 me-1"></i> Aniversariantes
        </a>
      </div>
    </div>
  </div>

  <!-- Central De Módulos e Tabelas (Submenu Integrado) -->
  <div class="mb-4">
    <h5 class="fw-bold text-dark mb-3"><i class="bi bi-grid-fill me-2 text-primary"></i>Acesso às Tabelas do Módulo Pessoas</h5>
    <div class="row g-3">
      <!-- Pessoas -->
      <div class="col-12 col-sm-6 col-md-4 col-xl-2-4">
        <a href="<?php echo $baseHref; ?>?m=pessoas&a=list" class="card h-100 border-0 shadow-sm text-decoration-none transition-hover p-3 rounded-3 text-start">
          <div class="d-flex align-items-center mb-2 text-primary">
            <div class="bg-primary bg-opacity-10 p-2 rounded-3 me-3">
              <i class="bi bi-person-lines-fill fs-4"></i>
            </div>
            <div>
              <h6 class="mb-0 fw-bold text-dark">Pessoas</h6>
              <small class="text-muted"><?php echo $totalPessoas; ?> cadastros</small>
            </div>
          </div>
          <p class="text-secondary small mb-0">Gerenciar todos os contatos e clientes.</p>
        </a>
      </div>

      <!-- Interessados -->
      <div class="col-12 col-sm-6 col-md-4 col-xl-2-4">
        <a href="<?php echo $baseHref; ?>?m=interessados&a=list" class="card h-100 border-0 shadow-sm text-decoration-none transition-hover p-3 rounded-3 text-start">
          <div class="d-flex align-items-center mb-2 text-success">
            <div class="bg-success bg-opacity-10 p-2 rounded-3 me-3">
              <i class="bi bi-person-heart fs-4"></i>
            </div>
            <div>
              <h6 class="mb-0 fw-bold text-dark">Interessados</h6>
              <small class="text-muted"><?php echo $totalInteressados; ?> leads</small>
            </div>
          </div>
          <p class="text-secondary small mb-0">Leads e potenciais interessados.</p>
        </a>
      </div>

      <!-- Mailling -->
      <div class="col-12 col-sm-6 col-md-4 col-xl-2-4">
        <a href="<?php echo $baseHref; ?>?m=mailling&a=list" class="card h-100 border-0 shadow-sm text-decoration-none transition-hover p-3 rounded-3 text-start">
          <div class="d-flex align-items-center mb-2 text-info">
            <div class="bg-info bg-opacity-10 p-2 rounded-3 me-3">
              <i class="bi bi-envelope-paper-fill fs-4"></i>
            </div>
            <div>
              <h6 class="mb-0 fw-bold text-dark">Mailling</h6>
              <small class="text-muted"><?php echo $totalMailling; ?> campanhas</small>
            </div>
          </div>
          <p class="text-secondary small mb-0">Comunicação e mensagens de e-mail.</p>
        </a>
      </div>

      <!-- Envia Mailling -->
      <div class="col-12 col-sm-6 col-md-4 col-xl-2-4">
        <a href="<?php echo $baseHref; ?>?m=mailling_envios&a=list" class="card h-100 border-0 shadow-sm text-decoration-none transition-hover p-3 rounded-3 text-start">
          <div class="d-flex align-items-center mb-2 text-warning">
            <div class="bg-warning bg-opacity-10 p-2 rounded-3 me-3">
              <i class="bi bi-send-fill fs-4"></i>
            </div>
            <div>
              <h6 class="mb-0 fw-bold text-dark">Envia Mailling</h6>
              <small class="text-muted"><?php echo $totalMaillingEnvios; ?> disparos</small>
            </div>
          </div>
          <p class="text-secondary small mb-0">Histórico e disparos de e-mail.</p>
        </a>
      </div>

      <!-- Lista Mailling -->
      <div class="col-12 col-sm-6 col-md-4 col-xl-2-4">
        <a href="<?php echo $baseHref; ?>?m=mailling_lista&a=list" class="card h-100 border-0 shadow-sm text-decoration-none transition-hover p-3 rounded-3 text-start">
          <div class="d-flex align-items-center mb-2 text-danger">
            <div class="bg-danger bg-opacity-10 p-2 rounded-3 me-3">
              <i class="bi bi-card-checklist fs-4"></i>
            </div>
            <div>
              <h6 class="mb-0 fw-bold text-dark">Lista Mailling</h6>
              <small class="text-muted"><?php echo $totalMaillingListas; ?> listas</small>
            </div>
          </div>
          <p class="text-secondary small mb-0">Listas e segmentação de destinatários.</p>
        </a>
      </div>
    </div>
  </div>

  <!-- KPI Indicators Cards -->
  <div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-md-3">
      <div class="card border-0 shadow-sm p-3 rounded-3 bg-white h-100">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <span class="text-muted small fw-bold">TOTAL DE PESSOAS</span>
            <h3 class="fw-bold text-dark mb-0 mt-1"><?php echo number_format($totalPessoas, 0, ',', '.'); ?></h3>
          </div>
          <div class="bg-primary text-white p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
            <i class="bi bi-people fs-4"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-md-3">
      <div class="card border-0 shadow-sm p-3 rounded-3 bg-white h-100">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <span class="text-muted small fw-bold">USUÁRIOS DO SISTEMA</span>
            <h3 class="fw-bold text-success mb-0 mt-1"><?php echo number_format($totalUsuarios, 0, ',', '.'); ?></h3>
          </div>
          <div class="bg-success text-white p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
            <i class="bi bi-shield-lock-fill fs-4"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-md-3">
      <div class="card border-0 shadow-sm p-3 rounded-3 bg-white h-100">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <span class="text-muted small fw-bold">ANIVERSARIANTES (MÊS)</span>
            <h3 class="fw-bold text-warning mb-0 mt-1"><?php echo number_format($totalAniversariantesMes, 0, ',', '.'); ?></h3>
          </div>
          <div class="bg-warning text-white p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
            <i class="bi bi-cake2-fill fs-4"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-md-3">
      <div class="card border-0 shadow-sm p-3 rounded-3 bg-white h-100">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <span class="text-muted small fw-bold">INTERESSADOS / LEADS</span>
            <h3 class="fw-bold text-info mb-0 mt-1"><?php echo number_format($totalInteressados, 0, ',', '.'); ?></h3>
          </div>
          <div class="bg-info text-white p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
            <i class="bi bi-person-lines-fill fs-4"></i>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- BI Charts Section -->
  <div class="row g-4 mb-4">
    <!-- Chart 1: Perfil de Usuários -->
    <div class="col-12 col-md-6 col-lg-4">
      <div class="card border-0 shadow-sm p-3 rounded-4 bg-white h-100">
        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-pie-chart-fill me-2 text-primary"></i>Distribuição por Perfil</h6>
        <div class="chart-container" style="position: relative; height: 240px;">
          <canvas id="chartPerfil"></canvas>
        </div>
      </div>
    </div>

    <!-- Chart 2: Top Cidades -->
    <div class="col-12 col-md-6 col-lg-4">
      <div class="card border-0 shadow-sm p-3 rounded-4 bg-white h-100">
        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-geo-alt-fill me-2 text-danger"></i>Top Cidades com Mais Cadastros</h6>
        <div class="chart-container" style="position: relative; height: 240px;">
          <canvas id="chartCidades"></canvas>
        </div>
      </div>
    </div>

    <!-- Chart 3: Vínculos e Categorias -->
    <div class="col-12 col-md-12 col-lg-4">
      <div class="card border-0 shadow-sm p-3 rounded-4 bg-white h-100">
        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-diagram-3-fill me-2 text-success"></i>Categorias e Vínculos</h6>
        <div class="chart-container" style="position: relative; height: 240px;">
          <canvas id="chartCategorias"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Quick Data Lists Section -->
  <div class="row g-4">
    <!-- Próximos Aniversariantes -->
    <div class="col-12 col-lg-6">
      <div class="card border-0 shadow-sm p-3 rounded-4 bg-white h-100">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="fw-bold text-dark mb-0"><i class="bi bi-gift-fill me-2 text-warning"></i>Próximos Aniversariantes</h6>
          <a href="<?php echo $baseHref; ?>?m=pessoas&a=relatorio_aniversariantes" class="btn btn-link btn-sm p-0 text-decoration-none">Ver Todos &rarr;</a>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
              <tr>
                <th>Nome</th>
                <th>Data</th>
                <th>Contato</th>
                <th class="text-end">Ação</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($upcomingBirthdays)): ?>
                <?php foreach ($upcomingBirthdays as $b): ?>
                  <tr>
                    <td class="fw-bold"><?php echo htmlspecialchars($b['nome']); ?></td>
                    <td><span class="badge bg-warning bg-opacity-10 text-dark"><?php echo date('d/m', strtotime($b['nascimento'])); ?></span></td>
                    <td><?php echo htmlspecialchars($b['fone_1'] ?: ($b['email'] ?: 'N/D')); ?></td>
                    <td class="text-end">
                      <?php if (!empty($b['fone_1'])): ?>
                        <?php 
                          $phoneClean = preg_replace('/\D/', '', $b['fone_1']); 
                          if (strlen($phoneClean) >= 10 && !str_starts_with($phoneClean, '55')) {
                              $phoneClean = '55' . $phoneClean;
                          }
                        ?>
                        <a href="https://wa.me/<?php echo $phoneClean; ?>" target="_blank" class="btn btn-sm btn-success py-0 px-2 shadow-sm" title="Enviar Mensagem no WhatsApp">
                          <i class="bi bi-whatsapp"></i>
                        </a>
                      <?php endif; ?>
                      <a href="<?php echo $baseHref; ?>?m=pessoas&a=view&id=<?php echo $b['id']; ?>" class="btn btn-sm btn-outline-primary py-0 px-2">
                        <i class="bi bi-eye"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="4" class="text-center text-muted py-3">Nenhum aniversariante próximo encontrado.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Últimas Pessoas Cadastradas -->
    <div class="col-12 col-lg-6">
      <div class="card border-0 shadow-sm p-3 rounded-4 bg-white h-100">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="fw-bold text-dark mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>Últimos Cadastros</h6>
          <a href="<?php echo $baseHref; ?>?m=pessoas&a=list" class="btn btn-link btn-sm p-0 text-decoration-none">Ver Lista Completa &rarr;</a>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
              <tr>
                <th>Nome</th>
                <th>Perfil</th>
                <th>Cidade/UF</th>
                <th class="text-end">Ação</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($recentPessoas)): ?>
                <?php foreach ($recentPessoas as $p): ?>
                  <tr>
                    <td class="fw-bold">
                      <a href="<?php echo $baseHref; ?>?m=pessoas&a=view&id=<?php echo $p['id']; ?>" class="text-dark text-decoration-none">
                        <?php echo htmlspecialchars($p['nome']); ?>
                      </a>
                    </td>
                    <td>
                      <span class="badge bg-secondary bg-opacity-10 text-secondary border">
                        <?php echo htmlspecialchars($p['perfil'] ?: 'CLIENTE'); ?>
                      </span>
                    </td>
                    <td><?php echo htmlspecialchars(($p['cidade'] ? $p['cidade'] . '/' : '') . $p['estado']); ?></td>
                    <td class="text-end">
                      <a href="<?php echo $baseHref; ?>?m=pessoas&a=form&id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-secondary py-0 px-2">
                        <i class="bi bi-pencil"></i>
                      </a>
                      <a href="<?php echo $baseHref; ?>?m=pessoas&a=view&id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-primary py-0 px-2">
                        <i class="bi bi-eye"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="4" class="text-center text-muted py-3">Nenhum cadastro recente encontrado.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
  .transition-hover {
    transition: all 0.3s cubic-bezier(.25, .8, .25, 1);
  }
  .transition-hover:hover {
    transform: translateY(-4px);
    box-shadow: 0 0.8rem 2rem rgba(0, 0, 0, .08) !important;
  }
  @media (min-width: 1200px) {
    .col-xl-2-4 {
      flex: 0 0 auto;
      width: 20%;
    }
  }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
  // Chart 1: Perfil
  const ctxPerfil = document.getElementById('chartPerfil');
  if (ctxPerfil) {
    new Chart(ctxPerfil, {
      type: 'doughnut',
      data: {
        labels: <?php echo json_encode($profileLabels); ?>,
        datasets: [{
          data: <?php echo json_encode($profileCounts); ?>,
          backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#64748b']
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } }
        }
      }
    });
  }

  // Chart 2: Top Cidades
  const ctxCidades = document.getElementById('chartCidades');
  if (ctxCidades) {
    new Chart(ctxCidades, {
      type: 'bar',
      data: {
        labels: <?php echo json_encode($cityLabels); ?>,
        datasets: [{
          label: 'Pessoas',
          data: <?php echo json_encode($cityCounts); ?>,
          backgroundColor: '#3b82f6',
          borderRadius: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false }
        },
        scales: {
          y: { beginAtZero: true, ticks: { precision: 0 } },
          x: { ticks: { font: { size: 10 } } }
        }
      }
    });
  }

  // Chart 3: Categorias e Vínculos
  const ctxCategorias = document.getElementById('chartCategorias');
  if (ctxCategorias) {
    new Chart(ctxCategorias, {
      type: 'bar',
      data: {
        labels: ['Clientes', 'Alunos', 'Professores', 'Fornecedores', 'Interessados'],
        datasets: [{
          data: [
            <?php echo $totalClientes; ?>, 
            <?php echo $totalAlunos; ?>, 
            <?php echo $totalProfessores; ?>, 
            <?php echo $totalFornecedores; ?>, 
            <?php echo $totalCursoInteressados; ?>
          ],
          backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#8b5cf6', '#ec4899'],
          borderRadius: 6
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false }
        },
        scales: {
          x: { beginAtZero: true, ticks: { precision: 0 } }
        }
      }
    });
  }
});
</script>
