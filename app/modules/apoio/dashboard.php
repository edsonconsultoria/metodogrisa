<?php
// app/modules/apoio/dashboard.php

require_once __DIR__ . '/../../config/config.php';

if (!current_user_is_at_least_admin()) {
    echo '<div class="alert alert-danger mt-3">Acesso negado. Apenas administradores podem acessar este módulo.</div>';
    include __DIR__ . '/../../templates/footer.php';
    exit;
}

$baseHref = BASE_URL . 'index.php';

// Helper function to safely count rows in table with fallback
function safeCountApoio(PDO $pdo, array $possibleTables, string $where = '1=1'): int {
    foreach ($possibleTables as $t) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$t}` WHERE {$where}");
            $stmt->execute();
            return (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            continue;
        }
    }
    return 0;
}

// 1. KPI Statistics
$totalAudit = safeCountApoio($pdo, ['000_audit', 'audit', '000_log', 'log']);
$totalParametros = safeCountApoio($pdo, ['000_parametros', 'parametros']);
$totalLocking = safeCountApoio($pdo, ['000_locking', 'locking']);
$totalVideos = safeCountApoio($pdo, ['000_videos_treinamento', 'videos_treinamento']);
$totalSearches = safeCountApoio($pdo, ['000_searches', 'searches']);
$totalEstados = safeCountApoio($pdo, ['000_estados', 'estados']);

// 2. Fetch Recent Audit Logs
$recentAudit = [];
foreach (['000_audit', 'audit', '000_log', 'log'] as $t) {
    try {
        $stmt = $pdo->query("SELECT * FROM `{$t}` ORDER BY id DESC LIMIT 5");
        $recentAudit = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($recentAudit)) break;
    } catch (Throwable $e) {}
}

// 3. Fetch Training Videos
$recentVideos = [];
foreach (['000_videos_treinamento', 'videos_treinamento'] as $t) {
    try {
        $stmt = $pdo->query("SELECT * FROM `{$t}` ORDER BY id DESC LIMIT 5");
        $recentVideos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($recentVideos)) break;
    } catch (Throwable $e) {}
}
?>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<div class="container-fluid py-4">
  <!-- Banner Header -->
  <div class="card border-0 shadow-sm p-4 rounded-4 mb-4" style="background: linear-gradient(135deg, #475569 0%, #334155 100%); color: white;">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
      <div>
        <h2 class="fw-bold mb-1"><i class="bi bi-info-circle-fill me-2"></i>Dashboard de Apoio & Sistema</h2>
        <p class="opacity-75 mb-0">Central de ferramentas de suporte, manuais de uso, backup, auditoria e parâmetros do sistema.</p>
      </div>
      <div class="mt-3 mt-md-0 d-flex gap-2">
        <a href="<?php echo $baseHref; ?>?m=manual&a=list" class="btn btn-light btn-sm fw-bold px-3 shadow-sm">
          <i class="bi bi-journal-text me-1"></i> Manuais
        </a>
        <a href="<?php echo $baseHref; ?>?m=backup&a=index" class="btn btn-outline-light btn-sm px-3">
          <i class="bi bi-hdd-network me-1"></i> Backup
        </a>
      </div>
    </div>
  </div>

  <!-- Central De Módulos e Tabelas (Submenu Integrado) -->
  <div class="mb-4">
    <h5 class="fw-bold text-dark mb-3"><i class="bi bi-grid-fill me-2 text-secondary"></i>Ferramentas e Módulos do Sistema</h5>
    <div class="row g-3">
      <!-- Manuais -->
      <div class="col-12 col-sm-6 col-md-4 col-xl-2-4">
        <a href="<?php echo $baseHref; ?>?m=manual&a=list" class="card h-100 border-0 shadow-sm text-decoration-none transition-hover p-3 rounded-3 text-start">
          <div class="d-flex align-items-center mb-2 text-primary">
            <div class="bg-primary bg-opacity-10 p-2 rounded-3 me-3">
              <i class="bi bi-journal-bookmark-fill fs-4"></i>
            </div>
            <div>
              <h6 class="mb-0 fw-bold text-dark">Manuais</h6>
              <small class="text-muted">Instruções de uso</small>
            </div>
          </div>
          <p class="text-secondary small mb-0">Manuais por perfil do sistema.</p>
        </a>
      </div>

      <!-- Backup -->
      <div class="col-12 col-sm-6 col-md-4 col-xl-2-4">
        <a href="<?php echo $baseHref; ?>?m=backup&a=index" class="card h-100 border-0 shadow-sm text-decoration-none transition-hover p-3 rounded-3 text-start">
          <div class="d-flex align-items-center mb-2 text-success">
            <div class="bg-success bg-opacity-10 p-2 rounded-3 me-3">
              <i class="bi bi-database-down fs-4"></i>
            </div>
            <div>
              <h6 class="mb-0 fw-bold text-dark">Backup</h6>
              <small class="text-muted">Cópia de segurança</small>
            </div>
          </div>
          <p class="text-secondary small mb-0">Exportação e restauração do banco.</p>
        </a>
      </div>

      <!-- Parâmetros -->
      <div class="col-12 col-sm-6 col-md-4 col-xl-2-4">
        <a href="<?php echo $baseHref; ?>?m=000_parametros&a=list" class="card h-100 border-0 shadow-sm text-decoration-none transition-hover p-3 rounded-3 text-start">
          <div class="d-flex align-items-center mb-2 text-warning">
            <div class="bg-warning bg-opacity-10 p-2 rounded-3 me-3">
              <i class="bi bi-sliders fs-4"></i>
            </div>
            <div>
              <h6 class="mb-0 fw-bold text-dark">Parâmetros</h6>
              <small class="text-muted"><?php echo $totalParametros; ?> configurações</small>
            </div>
          </div>
          <p class="text-secondary small mb-0">Parâmetros e regras do sistema.</p>
        </a>
      </div>

      <!-- Auditoria -->
      <div class="col-12 col-sm-6 col-md-4 col-xl-2-4">
        <a href="<?php echo $baseHref; ?>?m=000_audit&a=list" class="card h-100 border-0 shadow-sm text-decoration-none transition-hover p-3 rounded-3 text-start">
          <div class="d-flex align-items-center mb-2 text-info">
            <div class="bg-info bg-opacity-10 p-2 rounded-3 me-3">
              <i class="bi bi-shield-check fs-4"></i>
            </div>
            <div>
              <h6 class="mb-0 fw-bold text-dark">Auditoria</h6>
              <small class="text-muted"><?php echo $totalAudit; ?> logs</small>
            </div>
          </div>
          <p class="text-secondary small mb-0">Histórico de ações e alterações.</p>
        </a>
      </div>

      <!-- Vídeos de Treinamento -->
      <div class="col-12 col-sm-6 col-md-4 col-xl-2-4">
        <a href="<?php echo $baseHref; ?>?m=000_videos_treinamento&a=list" class="card h-100 border-0 shadow-sm text-decoration-none transition-hover p-3 rounded-3 text-start">
          <div class="d-flex align-items-center mb-2 text-danger">
            <div class="bg-danger bg-opacity-10 p-2 rounded-3 me-3">
              <i class="bi bi-play-circle-fill fs-4"></i>
            </div>
            <div>
              <h6 class="mb-0 fw-bold text-dark">Treinamentos</h6>
              <small class="text-muted"><?php echo $totalVideos; ?> vídeos</small>
            </div>
          </div>
          <p class="text-secondary small mb-0">Vídeos explicativos de uso.</p>
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
            <span class="text-muted small fw-bold">REGISTROS DE AUDITORIA</span>
            <h3 class="fw-bold text-info mb-0 mt-1"><?php echo number_format($totalAudit, 0, ',', '.'); ?></h3>
          </div>
          <div class="bg-info text-white p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
            <i class="bi bi-shield-check fs-4"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-md-3">
      <div class="card border-0 shadow-sm p-3 rounded-3 bg-white h-100">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <span class="text-muted small fw-bold">PARÂMETROS DO SISTEMA</span>
            <h3 class="fw-bold text-warning mb-0 mt-1"><?php echo number_format($totalParametros, 0, ',', '.'); ?></h3>
          </div>
          <div class="bg-warning text-white p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
            <i class="bi bi-sliders fs-4"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-md-3">
      <div class="card border-0 shadow-sm p-3 rounded-3 bg-white h-100">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <span class="text-muted small fw-bold">BLOQUEIOS DE REGISTRO</span>
            <h3 class="fw-bold text-danger mb-0 mt-1"><?php echo number_format($totalLocking, 0, ',', '.'); ?></h3>
          </div>
          <div class="bg-danger text-white p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
            <i class="bi bi-lock-fill fs-4"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-md-3">
      <div class="card border-0 shadow-sm p-3 rounded-3 bg-white h-100">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <span class="text-muted small fw-bold">VÍDEOS DE TREINAMENTO</span>
            <h3 class="fw-bold text-success mb-0 mt-1"><?php echo number_format($totalVideos, 0, ',', '.'); ?></h3>
          </div>
          <div class="bg-success text-white p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
            <i class="bi bi-play-circle fs-4"></i>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- BI Charts Section -->
  <div class="row g-4 mb-4">
    <!-- Chart 1: Proporção do Módulo -->
    <div class="col-12 col-md-6 col-lg-4">
      <div class="card border-0 shadow-sm p-3 rounded-4 bg-white h-100">
        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-pie-chart-fill me-2 text-secondary"></i>Proporção das Ferramentas de Sistema</h6>
        <div class="chart-container" style="position: relative; height: 240px;">
          <canvas id="chartApoioProporcao"></canvas>
        </div>
      </div>
    </div>

    <!-- Chart 2: Volume por Recurso -->
    <div class="col-12 col-md-6 col-lg-4">
      <div class="card border-0 shadow-sm p-3 rounded-4 bg-white h-100">
        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-bar-chart-line-fill me-2 text-info"></i>Volume por Módulo Administrativo</h6>
        <div class="chart-container" style="position: relative; height: 240px;">
          <canvas id="chartApoioVolume"></canvas>
        </div>
      </div>
    </div>

    <!-- Chart 3: Suporte & Instrução -->
    <div class="col-12 col-md-12 col-lg-4">
      <div class="card border-0 shadow-sm p-3 rounded-4 bg-white h-100">
        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-camera-reels-fill me-2 text-success"></i>Conteúdo de Suporte</h6>
        <div class="chart-container" style="position: relative; height: 240px;">
          <canvas id="chartApoioSuporte"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Quick Data Lists Section -->
  <div class="row g-4">
    <!-- ÚLTIMOS LOGS DE AUDITORIA -->
    <div class="col-12 col-lg-6">
      <div class="card border-0 shadow-sm p-3 rounded-4 bg-white h-100">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="fw-bold text-dark mb-0"><i class="bi bi-shield-check me-2 text-info"></i>Últimos Logs de Auditoria</h6>
          <a href="<?php echo $baseHref; ?>?m=000_audit&a=list" class="btn btn-link btn-sm p-0 text-decoration-none">Ver Histórico &rarr;</a>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
              <tr>
                <th>#ID</th>
                <th>Ação / Registro</th>
                <th class="text-end">Ação</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($recentAudit)): ?>
                <?php foreach ($recentAudit as $a): ?>
                  <tr>
                    <td class="fw-bold text-muted">#<?php echo $a['id']; ?></td>
                    <td class="fw-bold text-dark">
                      <?php echo htmlspecialchars($a['descricao'] ?? ($a['acao'] ?? ($a['modulo'] ?? 'Ação do Sistema'))); ?>
                    </td>
                    <td class="text-end">
                      <a href="<?php echo $baseHref; ?>?m=000_audit&a=view&id=<?php echo $a['id']; ?>" class="btn btn-sm btn-outline-primary py-0 px-2">
                        <i class="bi bi-eye"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="3" class="text-center text-muted py-3">Nenhum log de auditoria recente.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- VÍDEOS DE TREINAMENTO CADASTRAOS -->
    <div class="col-12 col-lg-6">
      <div class="card border-0 shadow-sm p-3 rounded-4 bg-white h-100">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="fw-bold text-dark mb-0"><i class="bi bi-play-circle me-2 text-danger"></i>Vídeos de Treinamento</h6>
          <a href="<?php echo $baseHref; ?>?m=000_videos_treinamento&a=list" class="btn btn-link btn-sm p-0 text-decoration-none">Ver Todos &rarr;</a>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
              <tr>
                <th>#ID</th>
                <th>Título do Vídeo</th>
                <th class="text-end">Ação</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($recentVideos)): ?>
                <?php foreach ($recentVideos as $v): ?>
                  <tr>
                    <td class="fw-bold text-muted">#<?php echo $v['id']; ?></td>
                    <td class="fw-bold text-dark">
                      <?php echo htmlspecialchars($v['titulo'] ?? ($v['nome'] ?? 'Vídeo de Treinamento')); ?>
                    </td>
                    <td class="text-end">
                      <a href="<?php echo $baseHref; ?>?m=000_videos_treinamento&a=view&id=<?php echo $v['id']; ?>" class="btn btn-sm btn-outline-primary py-0 px-2">
                        <i class="bi bi-eye"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="3" class="text-center text-muted py-3">Nenhum vídeo cadastrado até o momento.</td></tr>
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
  // Chart 1: Proporção
  const ctxProporcao = document.getElementById('chartApoioProporcao');
  if (ctxProporcao) {
    new Chart(ctxProporcao, {
      type: 'doughnut',
      data: {
        labels: ['Auditoria', 'Parâmetros', 'Bloqueios', 'Vídeos', 'Buscas'],
        datasets: [{
          data: [<?php echo $totalAudit; ?>, <?php echo $totalParametros; ?>, <?php echo $totalLocking; ?>, <?php echo $totalVideos; ?>, <?php echo $totalSearches; ?>],
          backgroundColor: ['#06b6d4', '#f59e0b', '#ef4444', '#10b981', '#6366f1']
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

  // Chart 2: Volume
  const ctxVolume = document.getElementById('chartApoioVolume');
  if (ctxVolume) {
    new Chart(ctxVolume, {
      type: 'bar',
      data: {
        labels: ['Auditoria', 'Parâmetros', 'Bloqueios', 'Vídeos', 'Estados'],
        datasets: [{
          label: 'Quantidade',
          data: [<?php echo $totalAudit; ?>, <?php echo $totalParametros; ?>, <?php echo $totalLocking; ?>, <?php echo $totalVideos; ?>, <?php echo $totalEstados; ?>],
          backgroundColor: '#475569',
          borderRadius: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
      }
    });
  }

  // Chart 3: Suporte
  const ctxSuporte = document.getElementById('chartApoioSuporte');
  if (ctxSuporte) {
    new Chart(ctxSuporte, {
      type: 'bar',
      data: {
        labels: ['Vídeos Treinamento', 'Parâmetros', 'Registros Auditoria'],
        datasets: [{
          data: [<?php echo $totalVideos; ?>, <?php echo $totalParametros; ?>, <?php echo $totalAudit; ?>],
          backgroundColor: ['#10b981', '#f59e0b', '#06b6d4'],
          borderRadius: 6
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
      }
    });
  }
});
</script>
