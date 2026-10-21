<?php
// app/modules/101_pessoas/relatorio_pdf.php

use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/../../config/config.php';

$nomeFiltro = trim($_GET['nome'] ?? '');
$emailFiltro = trim($_GET['email'] ?? '');
$telefoneFiltro = trim($_GET['telefone'] ?? '');

$where = [];
$params = [];

if ($nomeFiltro !== '') {
  $where[] = 'nome LIKE :nome';
  $params[':nome'] = '%' . $nomeFiltro . '%';
}
if ($emailFiltro !== '') {
  $where[] = '101_pessoas.email LIKE :email';
  $params[':email'] = '%' . $emailFiltro . '%';
}
if ($telefoneFiltro !== '') {
  $where[] = '101_pessoas.fone_1 LIKE :telefone';
  $params[':telefone'] = '%' . $telefoneFiltro . '%';
}

$whereClause = '';
if (!empty($where)) {
  $whereClause = 'WHERE ' . implode(' AND ', $where);
}

$sql = "
    SELECT
        101_pessoas.id,
        101_pessoas.nome,
        101_pessoas.email as email,
        101_pessoas.fone_1 as telefone
    FROM 101_pessoas
    {$whereClause}
    ORDER BY 101_pessoas.nome ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pessoas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$rows = '';
foreach ($pessoas as $p) {
  $rows .= '<tr>'
    . '<td style="padding:6px; border-bottom:1px solid #ddd; width:60px;">' . (int) ($p['id'] ?? 0) . '</td>'
    . '<td style="padding:6px; border-bottom:1px solid #ddd;">' . htmlspecialchars($p['nome'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>'
    . '<td style="padding:6px; border-bottom:1px solid #ddd;">' . htmlspecialchars($p['email'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>'
    . '<td style="padding:6px; border-bottom:1px solid #ddd;">' . htmlspecialchars($p['telefone'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>'
    . '</tr>';
}

$filtrosAtivos = [];
if ($nomeFiltro !== '')
  $filtrosAtivos[] = 'Nome: ' . htmlspecialchars($nomeFiltro, ENT_QUOTES, 'UTF-8');
if ($emailFiltro !== '')
  $filtrosAtivos[] = 'E-mail: ' . htmlspecialchars($emailFiltro, ENT_QUOTES, 'UTF-8');
if ($telefoneFiltro !== '')
  $filtrosAtivos[] = 'Telefone: ' . htmlspecialchars($telefoneFiltro, ENT_QUOTES, 'UTF-8');
$filtrosTexto = empty($filtrosAtivos) ? 'Sem filtros' : implode(' | ', $filtrosAtivos);

$html = <<<HTML
<!doctype html>
<html>
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #222; }
    .title { font-size: 16px; font-weight: bold; margin-bottom: 4px; }
    .subtitle { color: #555; margin-bottom: 12px; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; padding: 6px; border-bottom: 1px solid #333; background: #f2f2f2; }
  </style>
</head>
<body>
  <div class="title">Relatório de Pessoas</div>
  <div class="subtitle">{$filtrosTexto}</div>
  <table>
    <thead>
      <tr>
        <th style="width:60px;">ID</th>
        <th>Nome</th>
        <th>E-mail</th>
        <th>Telefone</th>
      </tr>
    </thead>
    <tbody>
      {$rows}
      {empty_rows}
    </tbody>
  </table>
</body>
</html>
HTML;

if ($rows === '') {
  $html = str_replace('{empty_rows}', '<tr><td colspan="4" style="padding:8px; text-align:center;">Nenhum registro encontrado.</td></tr>', $html);
} else {
  $html = str_replace('{empty_rows}', '', $html);
}

$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isRemoteEnabled', false);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Log de geração de PDF
log_event(
  $pdo,
  '101_pessoas',
  'RELATORIO',
  'Relatório simples PDF - filtros: ' . json_encode([
    'nome' => $nomeFiltro,
    'email' => $emailFiltro,
    'telefone' => $telefoneFiltro,
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);

while (ob_get_length()) {
  ob_end_clean();
}
$dompdf->stream('relatorio_101_pessoas.pdf', ['Attachment' => false]);
exit;
