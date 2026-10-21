<?php
// app/modules/pessoas/relatorio_aniversariantes_pdf.php

use Dompdf\Dompdf;
use Dompdf\Options;

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
        if ($month === 1 && $day >= 20 || $month === 2 && $day <= 18)
            return 'Aquário';
        if ($month === 2 && $day >= 19 || $month === 3 && $day <= 20)
            return 'Peixes';
        if ($month === 3 && $day >= 21 || $month === 4 && $day <= 19)
            return 'Áries';
        if ($month === 4 && $day >= 20 || $month === 5 && $day <= 20)
            return 'Touro';
        if ($month === 5 && $day >= 21 || $month === 6 && $day <= 20)
            return 'Gêmeos';
        if ($month === 6 && $day >= 21 || $month === 7 && $day <= 22)
            return 'Câncer';
        if ($month === 7 && $day >= 23 || $month === 8 && $day <= 22)
            return 'Leão';
        if ($month === 8 && $day >= 23 || $month === 9 && $day <= 22)
            return 'Virgem';
        if ($month === 9 && $day >= 23 || $month === 10 && $day <= 22)
            return 'Libra';
        if ($month === 10 && $day >= 23 || $month === 11 && $day <= 21)
            return 'Escorpião';
        if ($month === 11 && $day >= 22 || $month === 12 && $day <= 21)
            return 'Sagitário';
        return 'Capricórnio';
    } catch (Exception $e) {
        return '-';
    }
};

$generationLabel = static function (?string $value): string {
    if (empty($value) || $value === '0000-00-00')
        return '-';
    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    if ($date === false)
        return '-';
    $year = (int) $date->format('Y');
    if ($year >= 1946 && $year <= 1964)
        return 'Baby Boomers';
    if ($year >= 1965 && $year <= 1980)
        return 'Geração X';
    if ($year >= 1981 && $year <= 1996)
        return 'Geração Y';
    if ($year >= 1997 && $year <= 2012)
        return 'Geração Z';
    if ($year >= 2013 && $year <= 2025)
        return 'Geração Alpha';
    if ($year >= 2026)
        return 'Geração Beta';
    return 'Pré-1946';
};

// Statistics calculation for PDF charts
$statsAge = ['0-12' => 0, '13-17' => 0, '18-29' => 0, '30-45' => 0, '46-60' => 0, '60+' => 0];
$statsGeneration = [];
$statsZodiac = [];

foreach ($pessoas as $p) {
    if (!empty($p['nascimento']) && $p['nascimento'] !== '0000-00-00') {
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
        $gen = $generationLabel($p['nascimento']);
        if ($gen !== '-')
            $statsGeneration[$gen] = ($statsGeneration[$gen] ?? 0) + 1;
        $sign = $zodiacSign($p['nascimento']);
        if ($sign !== '-')
            $statsZodiac[$sign] = ($statsZodiac[$sign] ?? 0) + 1;
    }
}

// QuickChart URL generator helper
$getChartUrl = function ($type, $labels, $data, $colors) {
    $config = [
        'type' => $type,
        'data' => [
            'labels' => $labels,
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => $colors
                ]
            ]
        ],
        'options' => [
            'plugins' => [
                'legend' => ['position' => 'bottom', 'labels' => ['fontSize' => 10]],
                'datalabels' => ['display' => true, 'color' => '#fff', 'font' => ['weight' => 'bold', 'size' => 10]]
            ]
        ]
    ];
    return 'https://quickchart.io/chart?w=250&h=200&c=' . urlencode(json_encode($config));
};

$chartAgeLabels = [];
foreach ($statsAge as $l => $v)
    $chartAgeLabels[] = "$l ($v)";
$chartAgeUrl = $getChartUrl('pie', $chartAgeLabels, array_values($statsAge), ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#C9CBCF']);

// Remove empty generations for cleaner chart and add counts
$genLabels = [];
$genData = [];
foreach ($statsGeneration as $l => $v) {
    if ($v > 0) {
        $genLabels[] = "$l ($v)";
        $genData[] = $v;
    }
}
$chartGenUrl = $getChartUrl('doughnut', $genLabels, $genData, ['#0d6efd', '#6610f2', '#6f42c1', '#d63384', '#dc3545', '#fd7e14', '#ffc107', '#198754']);

$zodiacLabels = [];
foreach ($statsZodiac as $l => $v)
    $zodiacLabels[] = "$l ($v)";
$zodiacData = array_values($statsZodiac);
$chartZodiacUrl = $getChartUrl('pie', $zodiacLabels, $zodiacData, ['#20c997', '#0dcaf0', '#6f42c1', '#6c757d', '#fd7e14', '#dc3545', '#36A2EB', '#FF6384', '#4BC0C0', '#9966FF', '#C9CBCF', '#FFCE56']);

$rows = '';
$currentMonthSection = 0;
foreach ($pessoas as $p) {
    $monthNumber = 0;
    if (!empty($p['nascimento']) && $p['nascimento'] !== '0000-00-00') {
        $dateObj = DateTimeImmutable::createFromFormat('Y-m-d', $p['nascimento']);
        if ($dateObj !== false) {
            $monthNumber = (int) $dateObj->format('n');
        }
    }

    if ($monthNumber !== $currentMonthSection) {
        $currentMonthSection = $monthNumber;
        $rows .= '<tr style="background:#f2f2f2; font-weight:bold;"><td colspan="5">' . htmlspecialchars($months[$monthNumber] ?? 'Sem mês') . '</td></tr>';
    }

    $rows .= '<tr>'
        . '<td style="padding:4px; border-bottom:1px solid #ddd;">' . htmlspecialchars($p['nome'] ?? '') . '</td>'
        . '<td style="padding:4px; border-bottom:1px solid #ddd;">' . $formatDate($p['nascimento']) . '</td>'
        . '<td style="padding:4px; border-bottom:1px solid #ddd;">' . $calculateAge($p['nascimento']) . '</td>'
        . '<td style="padding:4px; border-bottom:1px solid #ddd;">' . htmlspecialchars($zodiacSign($p['nascimento'])) . '</td>'
        . '<td style="padding:4px; border-bottom:1px solid #ddd;">' . htmlspecialchars($generationLabel($p['nascimento'])) . '</td>'
        . '</tr>';
}

$filtrosTexto = 'Mês: ' . ($months[$monthFilter] ?? 'Todos');
if ($search !== '')
    $filtrosTexto .= ' | Busca: ' . htmlspecialchars($search);

$html = <<<HTML
<!doctype html>
<html>
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #222; }
    .title { font-size: 14px; font-weight: bold; margin-bottom: 4px; }
    .subtitle { color: #555; margin-bottom: 15px; }
    .charts-container { margin-bottom: 20px; width: 100%; }
    .chart-box { width: 32%; display: inline-block; text-align: center; vertical-align: top; }
    .chart-title { font-weight: bold; margin-bottom: 5px; color: #666; font-size: 9px; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; padding: 4px; border-bottom: 1px solid #333; background: #eee; }
    td { padding: 4px; border-bottom: 1px solid #ddd; }
  </style>
</head>
<body>
  <div class="title">Relatório de Aniversariantes</div>
  <div class="subtitle">{$filtrosTexto}</div>

  <div class="charts-container">
    <div class="chart-box">
      <div class="chart-title">Faixa Etária</div>
      <img src="{$chartAgeUrl}" width="180">
    </div>
    <div class="chart-box">
      <div class="chart-title">Geração</div>
      <img src="{$chartGenUrl}" width="180">
    </div>
    <div class="chart-box">
      <div class="chart-title">Signo</div>
      <img src="{$chartZodiacUrl}" width="180">
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th>Nome</th>
        <th>Nascimento</th>
        <th>Idade</th>
        <th>Signo</th>
        <th>Geração</th>
      </tr>
    </thead>
    <tbody>
      {$rows}
    </tbody>
  </table>
</body>
</html>
HTML;

$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

while (ob_get_length()) {
    ob_end_clean();
}
$dompdf->stream('relatorio_aniversariantes.pdf', ['Attachment' => false]);
exit;
