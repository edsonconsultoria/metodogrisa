<?php
// app/modules/pessoas/relatorio_aniversariantes.php

require_once __DIR__ . '/../../config/config.php';

$search = trim($_GET['q'] ?? '');
$monthFilter = isset($_GET['mes']) ? (int) $_GET['mes'] : 0;
$dayFilter = isset($_GET['dia']) ? (int) $_GET['dia'] : 0;

$months = [
  0 => 'Todos',
  1 => 'Janeiro',
  2 => 'Fevereiro',
  3 => 'Março',
  4 => 'Abril',
  5 => 'Maio',
  6 => 'Junho',
  7 => 'Julho',
  8 => 'Agosto',
  9 => 'Setembro',
  10 => 'Outubro',
  11 => 'Novembro',
  12 => 'Dezembro',
];

$params = [];
$where = 'WHERE nascimento IS NOT NULL AND nascimento <> \'0000-00-00\'';
if ($monthFilter >= 1 && $monthFilter <= 12) {
  $where .= ' AND MONTH(nascimento) = :mes';
  $params[':mes'] = $monthFilter;
}
if ($dayFilter >= 1 && $dayFilter <= 31) {
  $where .= ' AND DAY(nascimento) = :dia';
  $params[':dia'] = $dayFilter;
}
if ($search !== '') {
  $where .= ' AND (p.nome LIKE :search_nome OR p.email LIKE :search_email)';
  $params[':search_nome'] = '%' . $search . '%';
  $params[':search_email'] = '%' . $search . '%';
}

$sql = "
    SELECT 
        p.id, 
        p.nome, 
        p.email AS email,
        p.nascimento
    FROM 101_pessoas p
    {$where}
    ORDER BY MONTH(p.nascimento) ASC, DAY(p.nascimento) ASC, p.nome ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pessoas = $stmt->fetchAll(PDO::FETCH_ASSOC);

log_event(
  $pdo,
  'pessoas',
  'RELATORIO',
  'Relatório aniversariantes - mês ' . ($months[$monthFilter] ?? 'Todos') . ' - filtros: ' . json_encode([
    'q' => $search,
    'mes' => $monthFilter,
    'dia' => $dayFilter,
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);

$formatDate = static function (?string $value): string {
  if (empty($value) || $value === '0000-00-00') {
    return '-';
  }
  try {
    return (new DateTimeImmutable($value))->format('d/m/Y');
  } catch (Exception $e) {
    return '-';
  }
};

$calculateAge = static function (?string $value): string {
  if (empty($value) || $value === '0000-00-00') {
    return '-';
  }
  try {
    $birth = new DateTimeImmutable($value);
    $today = new DateTimeImmutable('today');
    return (string) $birth->diff($today)->y;
  } catch (Exception $e) {
    return '-';
  }
};

$zodiacSign = static function (?string $value): string {
  if (empty($value) || $value === '0000-00-00') {
    return '-';
  }
  try {
    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    if ($date === false) {
      return '-';
    }
    $day = (int) $date->format('j');
    $month = (int) $date->format('n');
    if ($month === 1 && $day >= 20 || $month === 2 && $day <= 18) {
      return 'Aquário';
    }
    if ($month === 2 && $day >= 19 || $month === 3 && $day <= 20) {
      return 'Peixes';
    }
    if ($month === 3 && $day >= 21 || $month === 4 && $day <= 19) {
      return 'Áries';
    }
    if ($month === 4 && $day >= 20 || $month === 5 && $day <= 20) {
      return 'Touro';
    }
    if ($month === 5 && $day >= 21 || $month === 6 && $day <= 20) {
      return 'Gêmeos';
    }
    if ($month === 6 && $day >= 21 || $month === 7 && $day <= 22) {
      return 'Câncer';
    }
    if ($month === 7 && $day >= 23 || $month === 8 && $day <= 22) {
      return 'Leão';
    }
    if ($month === 8 && $day >= 23 || $month === 9 && $day <= 22) {
      return 'Virgem';
    }
    if ($month === 9 && $day >= 23 || $month === 10 && $day <= 22) {
      return 'Libra';
    }
    if ($month === 10 && $day >= 23 || $month === 11 && $day <= 21) {
      return 'Escorpião';
    }
    if ($month === 11 && $day >= 22 || $month === 12 && $day <= 21) {
      return 'Sagitário';
    }
    return 'Capricórnio';
  } catch (Exception $e) {
    return '-';
  }
};

$generationLabel = static function (?string $value): string {
  if (empty($value) || $value === '0000-00-00') {
    return '-';
  }
  $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
  if ($date === false) {
    return '-';
  }
  $year = (int) $date->format('Y');
  if ($year >= 1946 && $year <= 1964) {
    return 'Baby Boomers';
  }
  if ($year >= 1965 && $year <= 1980) {
    return 'Geração X';
  }
  if ($year >= 1981 && $year <= 1996) {
    return 'Geração Y';
  }
  if ($year >= 1997 && $year <= 2012) {
    return 'Geração Z';
  }
  if ($year >= 2013 && $year <= 2025) {
    return 'Geração Alpha';
  }
  if ($year >= 2026) {
    return 'Geração Beta';
  }
  return 'Pré-1946';
};

// Statistics calculation
$statsAge = [
  '0-12' => 0,
  '13-17' => 0,
  '18-29' => 0,
  '30-45' => 0,
  '46-60' => 0,
  '60+' => 0,
];
$statsGeneration = [];
$statsZodiac = [];

foreach ($pessoas as $p) {
  if (!empty($p['nascimento']) && $p['nascimento'] !== '0000-00-00') {
    // Age Group
    $ageStr = $calculateAge($p['nascimento']);
    if ($ageStr !== '-') {
      $age = (int) $ageStr;
      if ($age <= 12)
        $statsAge['0-12']++;
      elseif ($age <= 17)
        $statsAge['13-17']++;
      elseif ($age <= 29)
        $statsAge['18-29']++;
      elseif ($age <= 45)
        $statsAge['30-45']++;
      elseif ($age <= 60)
        $statsAge['46-60']++;
      else
        $statsAge['60+']++;
    }

    // Generation
    $gen = $generationLabel($p['nascimento']);
    if ($gen !== '-') {
      $statsGeneration[$gen] = ($statsGeneration[$gen] ?? 0) + 1;
    }

    // Zodiac
    $sign = $zodiacSign($p['nascimento']);
    if ($sign !== '-') {
      $statsZodiac[$sign] = ($statsZodiac[$sign] ?? 0) + 1;
    }
  }
}

$baseHref = BASE_URL . 'index.php';
$todayKey = (new DateTimeImmutable('today'))->format('m-d');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">Relatório de Aniversariantes</h4>
  <a href="<?php echo $baseHref; ?>?m=pessoas&a=relatorio_aniversariantes_pdf&mes=<?php echo $monthFilter; ?>&q=<?php echo urlencode($search); ?>"
    class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">Exportar PDF</a>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form method="get" class="row g-3">
      <input type="hidden" name="m" value="pessoas">
      <input type="hidden" name="a" value="relatorio_aniversariantes">
      <div class="col-md-4">
        <label class="form-label">Mês</label>
        <select name="mes" class="form-select">
          <?php foreach ($months as $num => $label): ?>
            <option value="<?php echo $num; ?>" <?php echo $num === $monthFilter ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($label); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Busca</label>
        <input type="text" name="q" class="form-control" placeholder="Nome ou e-mail"
          value="<?php echo htmlspecialchars($search); ?>">
      </div>
      <div class="col-md-4 d-flex align-items-end">
        <button type="submit" class="btn btn-primary me-2">Filtrar</button>
        <a href="<?php echo $baseHref; ?>?m=pessoas&a=relatorio_aniversariantes"
          class="btn btn-outline-secondary">Limpar</a>
      </div>
    </form>
  </div>
</div>

<?php if (!empty($pessoas)): ?>
  <div class="row g-3 mb-3">
    <div class="col-md-4">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h6 class="card-title text-muted mb-3">Faixa Etária</h6>
          <div style="height: 200px;">
            <canvas id="chartAge"></canvas>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h6 class="card-title text-muted mb-3">Geração</h6>
          <div style="height: 200px;">
            <canvas id="chartGeneration"></canvas>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h6 class="card-title text-muted mb-3">Signo</h6>
          <div style="height: 200px;">
            <canvas id="chartZodiac"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="card shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-striped table-sm mb-0">
        <thead class="table-light">
          <tr>
            <th>Nome</th>
            <th>Data de nascimento</th>
            <th>Idade</th>
            <th>Signo</th>
            <th>Geração</th>
            <th>E-mail</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($pessoas)): ?>
            <tr>
              <td colspan="7" class="text-center text-muted py-3">Nenhum aniversariante encontrado.</td>
            </tr>
          <?php else: ?>
            <?php $currentMonthSection = 0; ?>
            <?php foreach ($pessoas as $p): ?>
              <?php
              $monthNumber = 0;
              if (!empty($p['data_nascimento']) && $p['data_nascimento'] !== '0000-00-00') {
                $dateObj = DateTimeImmutable::createFromFormat('Y-m-d', $p['data_nascimento']);
                if ($dateObj !== false) {
                  $monthNumber = (int) $dateObj->format('n');
                }
              }
              $isTodayBirthday = false;
              if (!empty($p['nascimento']) && $p['nascimento'] !== '0000-00-00') {
                $dateKey = DateTimeImmutable::createFromFormat('Y-m-d', $p['nascimento']);
                if ($dateKey !== false && $dateKey->format('m-d') === $todayKey) {
                  $isTodayBirthday = true;
                }
              }
              if ($monthNumber !== $currentMonthSection) {
                $currentMonthSection = $monthNumber;
                ?>
                <tr class="table-secondary">
                  <td colspan="7" class="fw-bold">
                    <?php echo htmlspecialchars($months[$monthNumber] ?? 'Sem mês'); ?>
                  </td>
                </tr>
              <?php } ?>
              <tr class="<?php echo $isTodayBirthday ? 'table-danger text-danger' : ''; ?>">
                <td><?php echo htmlspecialchars($p['nome'] ?? ''); ?></td>
                <td><?php echo $formatDate($p['nascimento']); ?></td>
                <td><?php echo $calculateAge($p['nascimento']); ?></td>
                <td><?php echo htmlspecialchars($zodiacSign($p['nascimento'])); ?></td>
                <td><?php echo htmlspecialchars($generationLabel($p['nascimento'])); ?></td>
                <td><?php echo htmlspecialchars($p['email'] ?? ''); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const commonOptions = {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'right',
          labels: {
            boxWidth: 12,
            font: { size: 10 }
          }
        }
      }
    };

    // Age Chart
    new Chart(document.getElementById('chartAge'), {
      type: 'pie',
      data: {
        labels: <?php
        $ageLabels = [];
        foreach ($statsAge as $label => $val) {
          $ageLabels[] = "$label ($val)";
        }
        echo json_encode($ageLabels);
        ?>,
        datasets: [{
          data: <?php echo json_encode(array_values($statsAge)); ?>,
          backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#C9CBCF']
        }]
      },
      options: commonOptions
    });

    // Generation Chart
    new Chart(document.getElementById('chartGeneration'), {
      type: 'doughnut',
      data: {
        labels: <?php
        $genLabels = [];
        foreach ($statsGeneration as $label => $val) {
          $genLabels[] = "$label ($val)";
        }
        echo json_encode($genLabels);
        ?>,
        datasets: [{
          data: <?php echo json_encode(array_values($statsGeneration)); ?>,
          backgroundColor: ['#0d6efd', '#6610f2', '#6f42c1', '#d63384', '#dc3545', '#fd7e14', '#ffc107', '#198754']
        }]
      },
      options: commonOptions
    });

    // Zodiac Chart
    new Chart(document.getElementById('chartZodiac'), {
      type: 'pie',
      data: {
        labels: <?php
        $zodiacLabels = [];
        foreach ($statsZodiac as $label => $val) {
          $zodiacLabels[] = "$label ($val)";
        }
        echo json_encode($zodiacLabels);
        ?>,
        datasets: [{
          data: <?php echo json_encode(array_values($statsZodiac)); ?>,
          backgroundColor: ['#20c997', '#0dcaf0', '#6f42c1', '#6c757d', '#fd7e14', '#dc3545', '#36A2EB', '#FF6384', '#4BC0C0', '#9966FF', '#C9CBCF', '#FFCE56']
        }]
      },
      options: commonOptions
    });
  });
</script>