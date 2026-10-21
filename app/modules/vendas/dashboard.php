<?php
// app/modules/vendas/dashboard.php

require_once __DIR__ . '/../../config/config.php';

if (!current_user_is_at_least_admin()) {
    echo '<div class="alert alert-danger mt-3">Acesso negado. Apenas administradores podem acessar este módulo.</div>';
    include __DIR__ . '/../../templates/footer.php';
    exit;
}

$baseHref = BASE_URL . 'index.php';

// Helper function to safely count rows in table with fallback
function safeCountVenda(PDO $pdo, array $possibleTables, string $where = '1=1'): int {
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
$totalVendas = safeCountVenda($pdo, ['106_vendas', 'vendas']);
$totalCompras = safeCountVenda($pdo, ['106_compras', 'compras']);
$totalProdutos = safeCountVenda($pdo, ['106_produtos', 'produtos']);
$totalCategorias = safeCountVenda($pdo, ['106_categorias', 'categorias']);
$totalAudios = safeCountVenda($pdo, ['106_audios_venda', 'audios_venda']);
$totalFrases = safeCountVenda($pdo, ['106_frases', 'frases']);
$totalHashtags = safeCountVenda($pdo, ['106_hashtag', 'hashtag']);

// 2. Fetch Recent Vendas
$recentVendas = [];
foreach (['106_vendas', 'vendas'] as $t) {
    try {
        $stmt = $pdo->query("SELECT * FROM `{$t}` ORDER BY id DESC LIMIT 5");
        $recentVendas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($recentVendas)) break;
    } catch (Throwable $e) {}
}

// 3. Fetch Recent Produtos
$topProdutos = [];
foreach (['106_produtos', 'produtos'] as $t) {
    try {
        $stmt = $pdo->query("SELECT * FROM `{$t}` ORDER BY id DESC LIMIT 5");
        $topProdutos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($topProdutos)) break;
    } catch (Throwable $e) {}
}
?>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<div class="container-fluid py-4">
  <!-- Banner Header -->
  <div class="card border-0 shadow-sm p-4 rounded-4 mb-4" style="background: linear-gradient(135deg, #be123c 0%, #fb7185 100%); color: white;">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
      <div>
        <h2 class="fw-bold mb-1"><i class="bi bi-cart3 me-2"></i>Dashboard de Vendas & BI Comercial</h2>
        <p class="opacity-75 mb-0">Gestão integrada de vendas, pedidos, catálogo de produtos, compras e áudios de promoção.</p>
      </div>
      <div class="mt-3 mt-md-0 d-flex gap-2">
        <a href="<?php echo $baseHref; ?>?m=106_vendas&a=form" class="btn btn-light btn-sm fw-bold px-3 shadow-sm">
          <i class="bi bi-cart-plus-fill me-1"></i> Nova Venda
        </a>
        <a href="<?php echo $baseHref; ?>?m=106_produtos&a=form" class="btn btn-outline-light btn-sm px-3">
          <i class="bi bi-box-seam me-1"></i> Novo Produto
        </a>
      </div>
    </div>
  </div>

  <!-- Central De Módulos e Tabelas (Submenu Integrado) -->
  <div class="mb-4">
    <h5 class="fw-bold text-dark mb-3"><i class="bi bi-grid-fill me-2 text-danger"></i>Acesso aos Módulos e Tabelas de Vendas</h5>
    <div class="row g-3">
      <!-- Vendas -->
      <div class="col-12 col-sm-6 col-md-4 col-xl-2-4">
        <a href="<?php echo $baseHref; ?>?m=106_vendas&a=list" class="card h-100 border-0 shadow-sm text-decoration-none transition-hover p-3 rounded-3 text-start">
          <div class="d-flex align-items-center mb-2 text-danger">
            <div class="bg-danger bg-opacity-10 p-2 rounded-3 me-3">
              <i class="bi bi-cart-check-fill fs-4"></i>
            </div>
            <div>
              <h6 class="mb-0 fw-bold text-dark">Vendas</h6>
              <small class="text-muted"><?php echo $totalVendas; ?> vendas</small>
            </div>
          </div>
          <p class="text-secondary small mb-0">Registros de pedidos e vendas.</p>
        </a>
      </div>

      <!-- Produtos -->
      <div class="col-12 col-sm-6 col-md-4 col-xl-2-4">
        <a href="<?php echo $baseHref; ?>?m=106_produtos&a=list" class="card h-100 border-0 shadow-sm text-decoration-none transition-hover p-3 rounded-3 text-start">
          <div class="d-flex align-items-center mb-2 text-primary">
            <div class="bg-primary bg-opacity-10 p-2 rounded-3 me-3">
              <i class="bi bi-box-seam-fill fs-4"></i>
            </div>
            <div>
              <h6 class="mb-0 fw-bold text-dark">Produtos</h6>
              <small class="text-muted"><?php echo $totalProdutos; ?> produtos</small>
            </div>
          </div>
          <p class="text-secondary small mb-0">Catálogo de produtos e valores.</p>
        </a>
      </div>

      <!-- Compras -->
      <div class="col-12 col-sm-6 col-md-4 col-xl-2-4">
        <a href="<?php echo $baseHref; ?>?m=106_compras&a=list" class="card h-100 border-0 shadow-sm text-decoration-none transition-hover p-3 rounded-3 text-start">
          <div class="d-flex align-items-center mb-2 text-warning">
            <div class="bg-warning bg-opacity-10 p-2 rounded-3 me-3">
              <i class="bi bi-bag-check-fill fs-4"></i>
            </div>
            <div>
              <h6 class="mb-0 fw-bold text-dark">Compras</h6>
              <small class="text-muted"><?php echo $totalCompras; ?> compras</small>
            </div>
          </div>
          <p class="text-secondary small mb-0">Entradas e compras de fornecedores.</p>
        </a>
      </div>

      <!-- Categorias -->
      <div class="col-12 col-sm-6 col-md-4 col-xl-2-4">
        <a href="<?php echo $baseHref; ?>?m=106_categorias&a=list" class="card h-100 border-0 shadow-sm text-decoration-none transition-hover p-3 rounded-3 text-start">
          <div class="d-flex align-items-center mb-2 text-info">
            <div class="bg-info bg-opacity-10 p-2 rounded-3 me-3">
              <i class="bi bi-tags-fill fs-4"></i>
            </div>
            <div>
              <h6 class="mb-0 fw-bold text-dark">Categorias</h6>
              <small class="text-muted"><?php echo $totalCategorias; ?> categorias</small>
            </div>
          </div>
          <p class="text-secondary small mb-0">Categorização do catálogo.</p>
        </a>
      </div>

      <!-- Áudios Venda -->
      <div class="col-12 col-sm-6 col-md-4 col-xl-2-4">
        <a href="<?php echo $baseHref; ?>?m=106_audios_venda&a=list" class="card h-100 border-0 shadow-sm text-decoration-none transition-hover p-3 rounded-3 text-start">
          <div class="d-flex align-items-center mb-2 text-success">
            <div class="bg-success bg-opacity-10 p-2 rounded-3 me-3">
              <i class="bi bi-mic-fill fs-4"></i>
            </div>
            <div>
              <h6 class="mb-0 fw-bold text-dark">Áudios Venda</h6>
              <small class="text-muted"><?php echo $totalAudios; ?> áudios</small>
            </div>
          </div>
          <p class="text-secondary small mb-0">Áudios comerciais e envios.</p>
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
            <span class="text-muted small fw-bold">TOTAL DE VENDAS</span>
            <h3 class="fw-bold text-danger mb-0 mt-1"><?php echo number_format($totalVendas, 0, ',', '.'); ?></h3>
          </div>
          <div class="bg-danger text-white p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
            <i class="bi bi-cart3 fs-4"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-md-3">
      <div class="card border-0 shadow-sm p-3 rounded-3 bg-white h-100">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <span class="text-muted small fw-bold">PRODUTOS CADASTRADOS</span>
            <h3 class="fw-bold text-primary mb-0 mt-1"><?php echo number_format($totalProdutos, 0, ',', '.'); ?></h3>
          </div>
          <div class="bg-primary text-white p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
            <i class="bi bi-box-seam fs-4"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-md-3">
      <div class="card border-0 shadow-sm p-3 rounded-3 bg-white h-100">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <span class="text-muted small fw-bold">REGISTRO DE COMPRAS</span>
            <h3 class="fw-bold text-warning mb-0 mt-1"><?php echo number_format($totalCompras, 0, ',', '.'); ?></h3>
          </div>
          <div class="bg-warning text-white p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
            <i class="bi bi-bag-check fs-4"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-md-3">
      <div class="card border-0 shadow-sm p-3 rounded-3 bg-white h-100">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <span class="text-muted small fw-bold">ÁUDIOS DE VENDA</span>
            <h3 class="fw-bold text-success mb-0 mt-1"><?php echo number_format($totalAudios, 0, ',', '.'); ?></h3>
          </div>
          <div class="bg-success text-white p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
            <i class="bi bi-mic fs-4"></i>
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
        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-pie-chart-fill me-2 text-danger"></i>Proporção do Módulo Comercial</h6>
        <div class="chart-container" style="position: relative; height: 240px;">
          <canvas id="chartVendasProporcao"></canvas>
        </div>
      </div>
    </div>

    <!-- Chart 2: Volume por Módulo -->
    <div class="col-12 col-md-6 col-lg-4">
      <div class="card border-0 shadow-sm p-3 rounded-4 bg-white h-100">
        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-bar-chart-line-fill me-2 text-primary"></i>Volume de Vendas vs Compras</h6>
        <div class="chart-container" style="position: relative; height: 240px;">
          <canvas id="chartVendasVolume"></canvas>
        </div>
      </div>
    </div>

    <!-- Chart 3: Recursos de Vendas -->
    <div class="col-12 col-md-12 col-lg-4">
      <div class="card border-0 shadow-sm p-3 rounded-4 bg-white h-100">
        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-chat-quote-fill me-2 text-success"></i>Áudios, Frases & Hashtags</h6>
        <div class="chart-container" style="position: relative; height: 240px;">
          <canvas id="chartVendasRecursos"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Quick Data Lists Section -->
  <div class="row g-4">
    <!-- ÚLTIMAS VENDAS -->
    <div class="col-12 col-lg-6">
      <div class="card border-0 shadow-sm p-3 rounded-4 bg-white h-100">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="fw-bold text-dark mb-0"><i class="bi bi-cart-check me-2 text-danger"></i>Últimas Vendas Registradas</h6>
          <a href="<?php echo $baseHref; ?>?m=106_vendas&a=list" class="btn btn-link btn-sm p-0 text-decoration-none">Ver Lista &rarr;</a>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
              <tr>
                <th>#ID</th>
                <th>Descrição / Venda</th>
                <th class="text-end">Ação</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($recentVendas)): ?>
                <?php foreach ($recentVendas as $v): ?>
                  <tr>
                    <td class="fw-bold text-muted">#<?php echo $v['id']; ?></td>
                    <td class="fw-bold text-dark">
                      <?php echo htmlspecialchars($v['nome'] ?? ($v['descricao'] ?? ($v['cliente'] ?? 'Venda #' . $v['id']))); ?>
                    </td>
                    <td class="text-end">
                      <a href="<?php echo $baseHref; ?>?m=106_vendas&a=view&id=<?php echo $v['id']; ?>" class="btn btn-sm btn-outline-primary py-0 px-2">
                        <i class="bi bi-eye"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="3" class="text-center text-muted py-3">Nenhuma venda registrada até o momento.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- PRODUTOS CADASTRADOS -->
    <div class="col-12 col-lg-6">
      <div class="card border-0 shadow-sm p-3 rounded-4 bg-white h-100">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="fw-bold text-dark mb-0"><i class="bi bi-box-seam me-2 text-primary"></i>Catálogo de Produtos</h6>
          <a href="<?php echo $baseHref; ?>?m=106_produtos&a=list" class="btn btn-link btn-sm p-0 text-decoration-none">Ver Todos &rarr;</a>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
              <tr>
                <th>#ID</th>
                <th>Nome do Produto</th>
                <th class="text-end">Ação</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($topProdutos)): ?>
                <?php foreach ($topProdutos as $p): ?>
                  <tr>
                    <td class="fw-bold text-muted">#<?php echo $p['id']; ?></td>
                    <td class="fw-bold text-dark">
                      <?php echo htmlspecialchars($p['nome'] ?? ($p['descricao'] ?? 'Produto #' . $p['id'])); ?>
                    </td>
                    <td class="text-end">
                      <a href="<?php echo $baseHref; ?>?m=106_produtos&a=view&id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-primary py-0 px-2">
                        <i class="bi bi-eye"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="3" class="text-center text-muted py-3">Nenhum produto cadastrado até o momento.</td></tr>
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
  const ctxProporcao = document.getElementById('chartVendasProporcao');
  if (ctxProporcao) {
    new Chart(ctxProporcao, {
      type: 'doughnut',
      data: {
        labels: ['Vendas', 'Produtos', 'Compras', 'Categorias', 'Áudios'],
        datasets: [{
          data: [<?php echo $totalVendas; ?>, <?php echo $totalProdutos; ?>, <?php echo $totalCompras; ?>, <?php echo $totalCategorias; ?>, <?php echo $totalAudios; ?>],
          backgroundColor: ['#ef4444', '#3b82f6', '#f59e0b', '#06b6d4', '#10b981']
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
  const ctxVolume = document.getElementById('chartVendasVolume');
  if (ctxVolume) {
    new Chart(ctxVolume, {
      type: 'bar',
      data: {
        labels: ['Vendas', 'Produtos', 'Compras', 'Categorias'],
        datasets: [{
          label: 'Quantidade',
          data: [<?php echo $totalVendas; ?>, <?php echo $totalProdutos; ?>, <?php echo $totalCompras; ?>, <?php echo $totalCategorias; ?>],
          backgroundColor: '#ef4444',
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

  // Chart 3: Recursos
  const ctxRecursos = document.getElementById('chartVendasRecursos');
  if (ctxRecursos) {
    new Chart(ctxRecursos, {
      type: 'bar',
      data: {
        labels: ['Áudios de Venda', 'Frases Engajamento', 'Hashtags'],
        datasets: [{
          data: [<?php echo $totalAudios; ?>, <?php echo $totalFrases; ?>, <?php echo $totalHashtags; ?>],
          backgroundColor: ['#10b981', '#3b82f6', '#f59e0b'],
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
