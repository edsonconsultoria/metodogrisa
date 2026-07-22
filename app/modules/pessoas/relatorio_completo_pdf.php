<?php
// app/modules/101_pessoas/relatorio_completo_pdf.php

use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/../../config/config.php';

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


$cards = '';
foreach ($pessoas as $pessoa) {
    $linhas = [];
    if (!empty($pessoa['cpf_cnpj']))
        $linhas[] = '<div class="field"><strong>CPF/CNPJ:</strong> ' . htmlspecialchars($fmtCpfCnpj($pessoa['cpf_cnpj']), ENT_QUOTES, 'UTF-8') . '</div>';
    if (!empty($pessoa['rg_ie']))
        $linhas[] = '<div class="field"><strong>RG:</strong> ' . htmlspecialchars($pessoa['rg_ie'], ENT_QUOTES, 'UTF-8') . '</div>';
    if (!empty($pessoa['email']))
        $linhas[] = '<div class="field"><strong>E-mail:</strong> ' . htmlspecialchars($pessoa['email'], ENT_QUOTES, 'UTF-8') . '</div>';
    if (!empty($pessoa['cep']))
        $linhas[] = '<div class="field"><strong>CEP:</strong> ' . htmlspecialchars($pessoa['cep'], ENT_QUOTES, 'UTF-8') . '</div>';
    if (!empty($pessoa['endereco']))
        $linhas[] = '<div class="field"><strong>Endereço:</strong> ' . htmlspecialchars($pessoa['endereco'], ENT_QUOTES, 'UTF-8') . '</div>';
    if (!empty($pessoa['numero']))
        $linhas[] = '<div class="field"><strong>Número:</strong> ' . htmlspecialchars($pessoa['numero'], ENT_QUOTES, 'UTF-8') . '</div>';
    if (!empty($pessoa['complemento']))
        $linhas[] = '<div class="field"><strong>Complemento:</strong> ' . htmlspecialchars($pessoa['complemento'], ENT_QUOTES, 'UTF-8') . '</div>';
    if (!empty($pessoa['bairro']))
        $linhas[] = '<div class="field"><strong>Bairro:</strong> ' . htmlspecialchars($pessoa['bairro'], ENT_QUOTES, 'UTF-8') . '</div>';
    if (!empty($pessoa['cidade']))
        $linhas[] = '<div class="field"><strong>Cidade:</strong> ' . htmlspecialchars($pessoa['cidade'], ENT_QUOTES, 'UTF-8') . '</div>';
    if (!empty($pessoa['estado']))
        $linhas[] = '<div class="field"><strong>Estado:</strong> ' . htmlspecialchars($pessoa['estado'], ENT_QUOTES, 'UTF-8') . '</div>';
    if (!empty($pessoa['obs']))
        $linhas[] = '<div class="field"><strong>Observações:</strong> ' . nl2br(htmlspecialchars($pessoa['obs'], ENT_QUOTES, 'UTF-8')) . '</div>';
    if (!empty($pessoa['cliente']))
        $linhas[] = '<div class="field"><strong>Cliente:</strong> Sim</div>';
    if (!empty($pessoa['eh_usuario'])) {
        $linhas[] = '<div class="field"><strong>Acesso:</strong> Sim</div>';
        if (!empty($pessoa['login']))
            $linhas[] = '<div class="field"><strong>Login:</strong> ' . htmlspecialchars($pessoa['login'], ENT_QUOTES, 'UTF-8') . '</div>';
        if (!empty($pessoa['perfil']))
            $linhas[] = '<div class="field"><strong>Perfil:</strong> ' . htmlspecialchars($pessoa['perfil'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    if (!empty($pessoa['inclusao']))
        $linhas[] = '<div class="field"><strong>Incluído em:</strong> ' . htmlspecialchars(date('d/m/Y H:i', strtotime($pessoa['inclusao'])), ENT_QUOTES, 'UTF-8') . '</div>';
    if (!empty($pessoa['alteracao']))
        $linhas[] = '<div class="field"><strong>Alterado em:</strong> ' . htmlspecialchars(date('d/m/Y H:i', strtotime($pessoa['alteracao'])), ENT_QUOTES, 'UTF-8') . '</div>';

    $conteudo = '<div class="fields">' . implode('', $linhas) . '</div>';
    $cards .= '<div class="card">'
        . '<div class="card-header">#' . (int) $pessoa['id'] . ' - ' . htmlspecialchars($pessoa['nome'], ENT_QUOTES, 'UTF-8') . '</div>'
        . '<div class="card-body">' . $conteudo . '</div>'
        . '</div>';
}

$filtrosAtivos = [];
if ($nomeFiltro !== '')
    $filtrosAtivos[] = 'Nome: ' . htmlspecialchars($nomeFiltro, ENT_QUOTES, 'UTF-8');
if ($emailFiltro !== '')
    $filtrosAtivos[] = 'E-mail: ' . htmlspecialchars($emailFiltro, ENT_QUOTES, 'UTF-8');
if ($enderecoFiltro !== '')
    $filtrosAtivos[] = 'Endereço: ' . htmlspecialchars($enderecoFiltro, ENT_QUOTES, 'UTF-8');
if ($bairroFiltro !== '')
    $filtrosAtivos[] = 'Bairro: ' . htmlspecialchars($bairroFiltro, ENT_QUOTES, 'UTF-8');
if ($cidadeFiltro !== '')
    $filtrosAtivos[] = 'Cidade: ' . htmlspecialchars($cidadeFiltro, ENT_QUOTES, 'UTF-8');
if ($estadoFiltro !== '')
    $filtrosAtivos[] = 'Estado: ' . htmlspecialchars($estadoFiltro, ENT_QUOTES, 'UTF-8');
if ($perfilFiltro !== '')
    $filtrosAtivos[] = 'Perfil: ' . htmlspecialchars($perfilFiltro, ENT_QUOTES, 'UTF-8');
$filtrosTexto = empty($filtrosAtivos) ? 'Sem filtros' : implode(' | ', $filtrosAtivos);
$dataEmissao = date('d/m/Y H:i');

$html = <<<HTML
<!doctype html>
<html>
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #222; }
    .title { font-size: 16px; font-weight: bold; margin-bottom: 4px; }
    .subtitle { color: #555; margin-bottom: 8px; }
    .meta { font-size: 11px; color: #555; margin-bottom: 12px; }
    .card { width: 100%; border: 1px solid #ccc; border-radius: 6px; margin-bottom: 10px; }
    .card-header { background: #f5f5f5; padding: 6px 10px; font-weight: bold; }
    .card-body { padding: 8px 10px; }
    .fields { display: flex; flex-wrap: wrap; gap: 6px 10px; }
    .field { width: 48%; margin-bottom: 4px; }
  </style>
</head>
<body>
  <div class="title">Relatório completo de 101_pessoas</div>
  <div class="meta">Emitido em: {$dataEmissao}</div>
  <div class="subtitle">{$filtrosTexto}</div>
  {cards}
</body>
</html>
HTML;

if ($cards === '') {
    $html = str_replace('{cards}', '<div>Nenhum registro encontrado.</div>', $html);
} else {
    $html = str_replace('{cards}', $cards, $html);
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
    'Relatório completo PDF - filtros: ' . json_encode([
        'nome' => $nomeFiltro,
        'email' => $emailFiltro,
        'endereco' => $enderecoFiltro,
        'bairro' => $bairroFiltro,
        'cidade' => $cidadeFiltro,
        'estado' => $estadoFiltro,
        'perfil' => $perfilFiltro,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);

while (ob_get_length()) {
    ob_end_clean();
}
$dompdf->stream('relatorio_completo_101_pessoas.pdf', ['Attachment' => false]);
exit;
