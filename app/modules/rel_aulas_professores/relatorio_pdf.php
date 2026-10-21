<?php
// app/modules/rel_aulas_professores/relatorio_pdf.php

use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/../../config/config.php';

$profFiltro = (int) ($_GET['professor_id'] ?? 0);
$etapaFiltro = (int) ($_GET['etapa_id'] ?? 0);
$viewMode = $_GET['view'] ?? 'summary';

$where = [];
$params = [];

if ($profFiltro > 0) {
    if ($viewMode === 'detail' || $viewMode === 'summary') {
        $where[] = "p.id = :prof_id";
    }
    $params[':prof_id'] = $profFiltro;
}

if ($etapaFiltro > 0) {
    if ($viewMode === 'detail' || $viewMode === 'summary') {
        $where[] = "c.etapa = :etapa_id";
    }
    $params[':etapa_id'] = $etapaFiltro;
}

$whereStr = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

if ($viewMode === 'summary') {
    $sql = "
        SELECT 
            e.nome as etapa_nome,
            p.apelido as prof_nome,
            COUNT(*) as total_aulas
        FROM 104_professores p
        JOIN (
            SELECT professor_1 as prof_id, etapa FROM 104_cronograma
            UNION ALL
            SELECT professor_2 as prof_id, etapa FROM 104_cronograma WHERE professor_2 IS NOT NULL AND professor_2 != 0
            UNION ALL
            SELECT professor_3 as prof_id, etapa FROM 104_cronograma WHERE professor_3 IS NOT NULL AND professor_3 != 0
            UNION ALL
            SELECT professor_4 as prof_id, etapa FROM 104_cronograma WHERE professor_4 IS NOT NULL AND professor_4 != 0
        ) c ON p.id = c.prof_id
        JOIN 104_etapas e ON c.etapa = e.id
        $whereStr
        GROUP BY c.etapa, p.id
        ORDER BY c.etapa DESC, prof_nome ASC
    ";
} else {
    $sql = "
        SELECT 
            p.apelido as prof_nome, 
            c.data, 
            c.inicio, 
            c.termino, 
            c.aula, 
            e.nome as etapa_nome, 
            t.nome as turma_nome
        FROM 104_professores p
        JOIN (
            SELECT professor_1 as prof_id, id, etapa, turma, data, inicio, termino, aula FROM 104_cronograma
            UNION ALL
            SELECT professor_2 as prof_id, id, etapa, turma, data, inicio, termino, aula FROM 104_cronograma WHERE professor_2 IS NOT NULL AND professor_2 != 0
            UNION ALL
            SELECT professor_3 as prof_id, id, etapa, turma, data, inicio, termino, aula FROM 104_cronograma WHERE professor_3 IS NOT NULL AND professor_3 != 0
            UNION ALL
            SELECT professor_4 as prof_id, id, etapa, turma, data, inicio, termino, aula FROM 104_cronograma WHERE professor_4 IS NOT NULL AND professor_4 != 0
        ) c ON p.id = c.prof_id
        JOIN 104_etapas e ON c.etapa = e.id
        JOIN 104_turmas t ON c.turma = t.id
        $whereStr
        ORDER BY prof_nome, data, inicio
    ";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filtrosTexto = "Filtros: ";
if ($profFiltro > 0) {
    $stmtP = $pdo->prepare("SELECT apelido FROM 104_professores WHERE id = ?");
    $stmtP->execute([$profFiltro]);
    $filtrosTexto .= "Professor: " . $stmtP->fetchColumn() . " | ";
} else {
    $filtrosTexto .= "Todos os Professores | ";
}

if ($etapaFiltro > 0) {
    $stmtE = $pdo->prepare("SELECT nome FROM 104_etapas WHERE id = ?");
    $stmtE->execute([$etapaFiltro]);
    $filtrosTexto .= "Etapa: " . $stmtE->fetchColumn();
} else {
    $filtrosTexto .= "Todas as Etapas";
}

$contentHtml = '';
if (empty($data)) {
    $contentHtml = '<p style="text-align:center; padding:20px;">Nenhuma aula encontrada.</p>';
} else {
    if ($viewMode === 'summary') {
        $contentHtml .= '<table style="width:100%; border-collapse:collapse; font-size:12px;">';
        $contentHtml .= '<thead><tr style="background:#eee;">';
        $contentHtml .= '<th style="border:1px solid #ccc; padding:8px; text-align:left;">Etapa</th>';
        $contentHtml .= '<th style="border:1px solid #ccc; padding:8px; text-align:left;">Professor</th>';
        $contentHtml .= '<th style="border:1px solid #ccc; padding:8px; text-align:center;">Qtd. Aulas</th>';
        $contentHtml .= '</tr></thead><tbody>';
        foreach ($data as $row) {
            $contentHtml .= '<tr>';
            $contentHtml .= '<td style="border:1px solid #ccc; padding:8px;">' . htmlspecialchars($row['etapa_nome']) . '</td>';
            $contentHtml .= '<td style="border:1px solid #ccc; padding:8px;">' . htmlspecialchars($row['prof_nome']) . '</td>';
            $contentHtml .= '<td style="border:1px solid #ccc; padding:8px; text-align:center;">' . $row['total_aulas'] . '</td>';
            $contentHtml .= '</tr>';
        }
        $contentHtml .= '</tbody></table>';
    } else {
        // Detail view grouped by professor
        $grouped = [];
        foreach ($data as $row) {
            $grouped[$row['prof_nome']][] = $row;
        }

        foreach ($grouped as $profNome => $aulas) {
            $contentHtml .= '<h3 style="background:#f4f4f4; padding:5px; margin-top:20px;">' . htmlspecialchars($profNome) . '</h3>';
            $contentHtml .= '<table style="width:100%; border-collapse:collapse; font-size:11px;">';
            $contentHtml .= '<thead><tr style="background:#eee;">';
            $contentHtml .= '<th style="border:1px solid #ccc; padding:5px; text-align:left;">Data</th>';
            $contentHtml .= '<th style="border:1px solid #ccc; padding:5px; text-align:left;">Horário</th>';
            $contentHtml .= '<th style="border:1px solid #ccc; padding:5px; text-align:left;">Aula</th>';
            $contentHtml .= '<th style="border:1px solid #ccc; padding:5px; text-align:left;">Turma / Etapa</th>';
            $contentHtml .= '</tr></thead><tbody>';
            foreach ($aulas as $aula) {
                $contentHtml .= '<tr>';
                $contentHtml .= '<td style="border:1px solid #ccc; padding:5px;">' . date('d/m/Y', strtotime($aula['data'])) . '</td>';
                $contentHtml .= '<td style="border:1px solid #ccc; padding:5px;">' . date('H:i', strtotime($aula['inicio'])) . ' - ' . date('H:i', strtotime($aula['termino'])) . '</td>';
                $contentHtml .= '<td style="border:1px solid #ccc; padding:5px;">' . htmlspecialchars($aula['aula']) . '</td>';
                $contentHtml .= '<td style="border:1px solid #ccc; padding:5px;">' . htmlspecialchars($aula['turma_nome']) . ' / ' . htmlspecialchars($aula['etapa_nome']) . '</td>';
                $contentHtml .= '</tr>';
            }
            $contentHtml .= '</tbody></table>';
        }
    }
}

$title = $viewMode === 'summary' ? 'Resumo: Aulas por Etapa/Professor' : 'Detalhes: Aulas por Professor';

$dataGeracao = date('d/m/Y H:i');

$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; }
        .header { text-align: center; margin-bottom: 20px; }
        .footer { position: fixed; bottom: 0; width: 100%; text-align: right; font-size: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>{$title}</h2>
        <p style="font-size:12px;">$filtrosTexto</p>
    </div>
    $contentHtml
    <div class="footer">
        Gerado em: {$dataGeracao}
    </div>
</body>
</html>
HTML;

log_event($pdo, 'rel_aulas_professores', 'RELATORIO_PDF', $filtrosTexto . ' | View: ' . $viewMode);

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
$dompdf->stream('relatorio_aulas_professores.pdf', ['Attachment' => false]);
exit;
