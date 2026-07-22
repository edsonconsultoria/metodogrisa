<?php
// app/modules/provas_alunos/relatorio_notas_pdf.php

use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/../../config/config.php';

$provaId = $_GET['prova'] ?? null;

// Se não houver filtro, busca a última prova cadastrada que tenha alunos vinculados
if (!$provaId) {
    $stmtUltima = $pdo->query("SELECT prova FROM 104_provas_alunos ORDER BY id DESC LIMIT 1");
    $provaId = $stmtUltima->fetchColumn();
}

if (!$provaId) die("Nenhuma prova encontrada para gerar relatório.");

// Buscar dados da prova
$stmtP = $pdo->prepare("
    SELECT p.*, t.nome as turma_nome 
    FROM 104_provas p 
    JOIN 104_turmas t ON t.id = p.turma 
    WHERE p.id = ?
");
$stmtP->execute([$provaId]);
$prova = $stmtP->fetch();

if (!$prova) die("Prova não encontrada.");

// Buscar notas dos alunos
$stmtNotas = $pdo->prepare("
    SELECT p.nome as aluno_nome, pa.nota, pa.concluida
    FROM 104_provas_alunos pa
    JOIN 101_pessoas p ON p.id = pa.aluno
    WHERE pa.prova = ?
    ORDER BY p.nome ASC
");
$stmtNotas->execute([$provaId]);
$notas = $stmtNotas->fetchAll();

$concluidos   = [];
$pendentes    = [];
$somaNotas    = 0;

foreach ($notas as $n) {
    if ($n['concluida']) {
        $concluidos[] = $n;
        $somaNotas   += $n['nota'];
    } else {
        $pendentes[] = $n;
    }
}

$contConcluidos = count($concluidos);
$media    = $contConcluidos > 0 ? ($somaNotas / $contConcluidos) : 0;
$mediaStr = number_format($media, 2, ',', '.');
$dataStr  = date('d/m/Y', strtotime($prova['data']));
$geradoEm = date('d/m/Y H:i:s');

// Tabela de concluídos
$rowsConcluidos = '';
foreach ($concluidos as $i => $n) {
    $bg      = ($i % 2 === 0) ? '#ffffff' : '#f8f9fa';
    $nota    = number_format($n['nota'], 2, ',', '.');
    $cor     = $n['nota'] >= 6 ? '#28a745' : '#dc3545';
    $rowsConcluidos .= "
        <tr style='background:{$bg};'>
            <td>" . htmlspecialchars($n['aluno_nome']) . "</td>
            <td style='text-align:center; font-weight:bold; color:{$cor};'>{$nota}</td>
        </tr>";
}

// Tabela de pendentes
$rowsPendentes = '';
foreach ($pendentes as $i => $n) {
    $bg = ($i % 2 === 0) ? '#ffffff' : '#fff8f8';
    $rowsPendentes .= "
        <tr style='background:{$bg};'>
            <td>" . htmlspecialchars($n['aluno_nome']) . "</td>
            <td style='text-align:center; color:#999;'>—</td>
        </tr>";
}

$secaoPendentes = '';
if (!empty($pendentes)) {
    $secaoPendentes = "
    <div class='section-title pendente'>Não Concluíram &nbsp;<span class='badge-count'>" . count($pendentes) . "</span></div>
    <table>
        <thead>
            <tr>
                <th>Nome do Aluno</th>
                <th style='text-align:center; width:100px;'>Nota</th>
            </tr>
        </thead>
        <tbody>{$rowsPendentes}</tbody>
    </table>";
}

$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body        { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #333; margin: 0; padding: 20px; }
        .header     { border-bottom: 2px solid #2c3e50; margin-bottom: 18px; padding-bottom: 10px; display: table; width: 100%; }
        .title      { font-size: 18px; font-weight: bold; color: #2c3e50; display: table-cell; }
        .subtitle   { font-size: 11px; color: #888; display: table-cell; text-align: right; vertical-align: bottom; }
        .info-box   { background: #f0f4f8; padding: 12px 15px; border-left: 4px solid #007bff; margin-bottom: 20px; font-size: 12px; }
        .section-title {
            font-size: 13px; font-weight: bold; padding: 7px 12px;
            border-radius: 4px; margin-bottom: 6px; margin-top: 22px;
        }
        .section-title.concluido { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #28a745; }
        .section-title.pendente  { background: #fff3e0; color: #e65100; border-left: 4px solid #ff9800; }
        .badge-count { font-size: 11px; background: rgba(0,0,0,.12); padding: 1px 7px; border-radius: 10px; }
        table       { width: 100%; border-collapse: collapse; }
        th          { background: #2c3e50; color: white; padding: 9px 12px; text-align: left; font-size: 11px; }
        td          { padding: 8px 12px; border-bottom: 1px solid #eee; }
        .summary    { margin-top: 18px; text-align: right; font-size: 13px; }
        .footer     { position: fixed; bottom: -20px; left: 0; right: 0; font-size: 9px; text-align: center; color: #bbb; border-top: 1px solid #eee; padding-top: 4px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">RELATÓRIO DE NOTAS</div>
        <div class="subtitle">Sistema Método Grisa</div>
    </div>

    <div class="info-box">
        <strong>Prova:</strong> {$prova['tipo']} &nbsp;&nbsp;
        <strong>Turma:</strong> {$prova['turma_nome']} &nbsp;&nbsp;
        <strong>Data:</strong> {$dataStr}
    </div>

    <div class="section-title concluido">Concluíram &nbsp;<span class="badge-count">{$contConcluidos}</span></div>
    <table>
        <thead>
            <tr>
                <th>Nome do Aluno</th>
                <th style="text-align:center; width:100px;">Nota</th>
            </tr>
        </thead>
        <tbody>{$rowsConcluidos}</tbody>
    </table>

    <div class="summary">
        <strong>Média da turma:</strong> <span style="color:#28a745; font-size:14px;">{$mediaStr}</span>
    </div>

    {$secaoPendentes}

    <div class="footer">
        Gerado em {$geradoEm} &nbsp;|&nbsp; Método Grisa Sistema
    </div>
</body>
</html>
HTML;

$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

while (ob_get_length()) ob_end_clean();

$filename = "notas_prova_{$provaId}_" . date('Ymd_Hi') . ".pdf";
$dompdf->stream($filename, ['Attachment' => false]);
exit;
