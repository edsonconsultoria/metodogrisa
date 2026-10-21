<?php
// app/modules/rel_pendencias/relatorio_pdf.php

use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/../../config/config.php';

$turmaFiltro = (int) ($_GET['turma_id'] ?? 0);

$docFields = [
    'rg_carteira_conselho' => 'RG/Carteira Conselho',
    'cpf' => 'CPF',
    'cer_nascimento_casamento' => 'Cert. Nascimento/Casamento',
    'comprovante_residencia' => 'Comprovante Residência',
    'fge' => 'FGE',
    'tcc' => 'TCC',
    'uso_imagem' => 'Uso de Imagem',
    'cert_grad' => 'Cert Grad',
    'emitido_cert_metodo_grisa' => 'Cert MG',
    'emitido_cert_universidade' => 'Cert Uni',
];

$guia = $_GET['guia'] ?? 'todas';
if (!in_array($guia, ['todas', 'pendentes', 'sem_pendencias'])) {
    $guia = 'todas';
}

$diplomaFiltroSel = $_GET['diploma_filtro'] ?? 'todos';
if (!in_array($diplomaFiltroSel, ['todos', 'pos', 'livre'])) {
    $diplomaFiltroSel = 'todos';
}

$turmaNome = '';
$alunos = [];
if ($turmaFiltro > 0) {
    $stmtT = $pdo->prepare("SELECT nome FROM 104_turmas WHERE id = ?");
    $stmtT->execute([$turmaFiltro]);
    $turmaNome = (string) $stmtT->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT a.id, a.diploma, a." . implode(', a.', array_keys($docFields)) . ",
               p.nome AS aluno_nome
        FROM 104_alunos a
        LEFT JOIN 101_pessoas p ON p.id = a.aluno
        WHERE a.turma = :turma_id
        ORDER BY p.nome ASC
    ");
    $stmt->execute([':turma_id' => $turmaFiltro]);
    $todosAlunos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $alunosFiltradosDiploma = [];
    foreach ($todosAlunos as $aluno) {
        $d = $aluno['diploma'] ?? '';
        if ($diplomaFiltroSel === 'pos' && $d !== 'Pós Graduação') {
            continue;
        }
        if ($diplomaFiltroSel === 'livre' && $d !== 'Curso Livre') {
            continue;
        }
        $alunosFiltradosDiploma[] = $aluno;
    }

    $alunosComPendencias = [];
    $alunosSemPendencias = [];
    foreach ($alunosFiltradosDiploma as $aluno) {
        $hasPendency = false;
        foreach ($docFields as $field => $label) {
            if (($aluno[$field] ?? '') != '1') {
                $hasPendency = true;
                break;
            }
        }
        if ($hasPendency) {
            $alunosComPendencias[] = $aluno;
        } else {
            $alunosSemPendencias[] = $aluno;
        }
    }

    if ($guia === 'pendentes') {
        $alunos = $alunosComPendencias;
    } elseif ($guia === 'sem_pendencias') {
        $alunos = $alunosSemPendencias;
    } else {
        $alunos = $alunosFiltradosDiploma;
    }
}

$contentHtml = '';
if ($turmaFiltro <= 0) {
    $contentHtml = '<p style="text-align:center; padding:20px;">Nenhuma turma selecionada.</p>';
} elseif (empty($alunos)) {
    $contentHtml = '<p style="text-align:center; padding:20px;">Nenhum aluno encontrado para esta turma.</p>';
} else {
    $contentHtml .= '<table style="width:100%; border-collapse:collapse; font-size:11px;">';
    $contentHtml .= '<thead><tr style="background:#eee;">';
    $contentHtml .= '<th style="border:1px solid #ccc; padding:6px; text-align:left; width:30%;">Aluno</th>';
    $contentHtml .= '<th style="border:1px solid #ccc; padding:6px; text-align:left;">Pendências</th>';
    $contentHtml .= '</tr></thead><tbody>';
    foreach ($alunos as $aluno) {
        $pendencias = [];
        foreach ($docFields as $field => $label) {
            if (($aluno[$field] ?? '') != '1') {
                $pendencias[] = $label;
            }
        }
        $pendenciasTexto = empty($pendencias) ? 'Sem pendências' : implode(', ', $pendencias);
        $contentHtml .= '<tr>';
        $contentHtml .= '<td style="border:1px solid #ccc; padding:6px;">' . htmlspecialchars($aluno['aluno_nome'] ?? '—') . '</td>';
        $contentHtml .= '<td style="border:1px solid #ccc; padding:6px;">' . htmlspecialchars($pendenciasTexto) . '</td>';
        $contentHtml .= '</tr>';
    }
    $contentHtml .= '</tbody></table>';
}

$diplomaNomes = [
    'todos' => 'Todos os Diplomas',
    'pos' => 'Pós Graduação',
    'livre' => 'Curso Livre',
];
$diplomaNome = $diplomaNomes[$diplomaFiltroSel] ?? 'Todos os Diplomas';

$guiaNomes = [
    'todas' => 'Todos',
    'pendentes' => 'Com Pendências',
    'sem_pendencias' => 'Sem Pendências',
];
$guiaNome = $guiaNomes[$guia] ?? 'Todos';

$filtrosTexto = 'Turma: ' . ($turmaNome !== '' ? $turmaNome : 'Nenhuma selecionada') . ' | Diploma: ' . $diplomaNome . ' | Filtro: ' . $guiaNome;
$dataGeracao = date('d/m/Y H:i');

$pdfTitle = 'Relatório de Pendências - ' . ($turmaNome !== '' ? $turmaNome : 'Sem Turma') . ' - ' . $diplomaNome . ' - ' . $guiaNome;
$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{$pdfTitle}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 1px solid #ccc; padding-bottom: 10px; }
        .header-subtitle { font-size: 11px; font-weight: bold; text-transform: uppercase; color: #666; letter-spacing: 1.5px; margin: 0; }
        .header-title { font-size: 18px; font-weight: bold; color: #111; margin: 5px 0; }
        .header-filters { font-size: 11px; color: #555; margin: 0; }
        .footer { position: fixed; bottom: 0; width: 100%; text-align: right; font-size: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-subtitle">Método Grisa</div>
        <div class="header-title">Relatório de Pendências</div>
        <div class="header-filters">{$filtrosTexto}</div>
    </div>
    {$contentHtml}
    <div class="footer">
        Gerado em: {$dataGeracao}
    </div>
</body>
</html>
HTML;

log_event($pdo, 'rel_pendencias', 'RELATORIO_PDF', $filtrosTexto);

$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isRemoteEnabled', false);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

while (ob_get_length()) {
    ob_end_clean();
}
$sanitizedFilename = str_replace(['/', '\\', '?', '*', ':', '|', '"', '<', '>'], '', $pdfTitle) . '.pdf';
$dompdf->stream($sanitizedFilename, ['Attachment' => false]);
exit;
