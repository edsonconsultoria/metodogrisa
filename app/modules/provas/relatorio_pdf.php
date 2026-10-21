<?php
// app/modules/provas/relatorio_pdf.php

use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/CrudEngine.php';

$id = $_GET['id'] ?? null;
if (!$id) die("ID não informado.");

$_TABLE = '104_provas';
$_LOOKUPS = [
    'turma' => ['table' => '104_turmas', 'label' => 'nome']
];

$engine = new CrudEngine($pdo, $_TABLE);
$engine->setLookups($_LOOKUPS);
$record = $engine->getRecord($id);

if (!$record) die("Registro não encontrado.");

// Buscar questões e opções vinculadas
$stmt = $pdo->prepare("
    SELECT q.* 
    FROM 104_questoes q
    JOIN 104_provas_questoes pq ON pq.questao = q.id
    WHERE pq.prova = ?
    ORDER BY pq.id
");
$stmt->execute([$id]);
$questoes = $stmt->fetchAll();

$questoesHtml = '';
foreach ($questoes as $i => $q) {
    $stmtOpt = $pdo->prepare("SELECT * FROM 104_questoes_opcoes WHERE questao = ? ORDER BY id");
    $stmtOpt->execute([$q['id']]);
    $opcoes = $stmtOpt->fetchAll();

    $opcoesHtml = '';
    foreach ($opcoes as $j => $opt) {
        $char = chr(65 + $j);
        $isCorreta = (bool)$opt['correta'];
        $style = $isCorreta ? 'background-color: #f0fff0; border: 1px solid #c3e6cb; color: #155724;' : 'border: 1px solid #eee;';
        
        $opcoesHtml .= "
            <div style='margin-bottom: 8px; padding: 10px; border-radius: 4px; $style'>
                <strong>$char)</strong> " . $opt['resposta'] . "
                " . ($isCorreta ? " <span style='float: right; font-weight: bold; font-size: 10px;'>[CORRETA]</span>" : "") . "
            </div>
        ";
    }

    $questoesHtml .= "
        <div style='margin-bottom: 30px; page-break-inside: avoid;'>
            <div style='background: #f8f9fa; padding: 10px; border-bottom: 2px solid #007bff; margin-bottom: 15px;'>
                <strong style='font-size: 14px;'>QUESTÃO " . ($i + 1) . "</strong>
            </div>
            <div style='margin-bottom: 15px; line-height: 1.5;'>
                {$q['enunciado']}
            </div>
            <div>
                $opcoesHtml
            </div>
        </div>
    ";
}

$dataStr = $record['data'] ? date('d/m/Y', strtotime($record['data'])) : '—';
$horarioStr = ($record['inicio'] ? substr($record['inicio'], 0, 5) : '—') . ' às ' . ($record['termino'] ? substr($record['termino'], 0, 5) : '—');

$dataGeracao = date('d/m/Y H:i:s');
$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #333; line-height: 1.4; }
        .header-table { width: 100%; border-bottom: 2px solid #333; margin-bottom: 20px; padding-bottom: 10px; }
        .header-title { font-size: 18px; font-weight: bold; color: #000; }
        .info-box { background: #f4f4f4; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .info-item { display: inline-block; width: 32%; vertical-align: top; }
        .label { color: #666; font-size: 10px; text-transform: uppercase; display: block; margin-bottom: 3px; }
        .value { font-weight: bold; font-size: 13px; }
        .footer { position: fixed; bottom: -30px; left: 0; right: 0; height: 30px; font-size: 10px; text-align: center; color: #999; border-top: 1px solid #eee; padding-top: 5px; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td class="header-title">PROVA DE AVALIAÇÃO</td>
            <td style="text-align: right; color: #666;">ID: #{$id}</td>
        </tr>
    </table>

    <div class="info-box">
        <div class="info-item">
            <span class="label">Turma</span>
            <span class="value">{$record['turma_label']}</span>
        </div>
        <div class="info-item">
            <span class="label">Tipo de Avaliação</span>
            <span class="value">{$record['tipo']}</span>
        </div>
        <div class="info-item" style="width: 35%;">
            <span class="label">Data e Horário</span>
            <span class="value">{$dataStr} | {$horarioStr}</span>
        </div>
    </div>

    <div class="content">
        {$questoesHtml}
    </div>

    <div class="footer">
        Gerado em {$dataGeracao} | Método Grisa Sistema
    </div>
</body>
</html>
HTML;

$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Log
log_event($pdo, '104_provas', 'IMPRIMIR', "Gerado PDF da prova ID $id");

while (ob_get_length()) {
    ob_end_clean();
}

$filename = "prova_{$id}_" . date('Ymd_Hi') . ".pdf";
$dompdf->stream($filename, ['Attachment' => false]);
exit;
