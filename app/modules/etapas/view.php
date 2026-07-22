<?php
// app/modules/etapas/view.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';

$tableName = '104_etapas';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$engine = new CrudEngine($pdo, $tableName);

$record = $engine->getRecord($id);
if (!$record) {
    die("Registro não encontrado.");
}

$baseHref = BASE_URL . 'index.php';

$formatPtBrDate = function ($date) {
    if (!$date || $date === '0000-00-00')
        return '—';
    $dias = [
        'Sunday' => 'Domingo',
        'Monday' => 'Segunda',
        'Tuesday' => 'Terça',
        'Wednesday' => 'Quarta',
        'Thursday' => 'Quinta',
        'Friday' => 'Sexta',
        'Saturday' => 'Sábado'
    ];
    $meses = [
        'January' => 'janeiro',
        'February' => 'fevereiro',
        'March' => 'março',
        'April' => 'abril',
        'May' => 'maio',
        'June' => 'junho',
        'July' => 'julho',
        'August' => 'agosto',
        'September' => 'setembro',
        'October' => 'outubro',
        'November' => 'novembro',
        'December' => 'dezembro'
    ];
    $ts = strtotime($date);
    $diaSemana = $dias[date('l', $ts)];
    $mes = $meses[date('F', $ts)];
    return $diaSemana . ' – ' . date('d', $ts) . ' de ' . $mes . ' de ' . date('Y', $ts);
};
$formatShort = function ($date) {
    return ($date && $date !== '0000-00-00') ? date('d/m/Y', strtotime($date)) : '—';
};

// ── Buscar aulas desta etapa agrupadas por data e turma ──────────────────────
$stmtAulas = $pdo->prepare("
    SELECT c.id, c.data, c.inicio, c.termino, c.aula,
           c.turma AS turma_id,
           t.nome AS turma_nome,
           p1.apelido AS prof1, p2.apelido AS prof2,
           p3.apelido AS prof3, p4.apelido AS prof4
    FROM 104_cronograma c
    LEFT JOIN 104_turmas t ON t.id = c.turma
    LEFT JOIN 104_professores p1 ON p1.id = c.professor_1
    LEFT JOIN 104_professores p2 ON p2.id = c.professor_2
    LEFT JOIN 104_professores p3 ON p3.id = c.professor_3
    LEFT JOIN 104_professores p4 ON p4.id = c.professor_4
    WHERE c.etapa = :etapa
    ORDER BY c.data ASC, c.inicio ASC, t.nome ASC
");
$stmtAulas->execute([':etapa' => $id]);
$todasAulas = $stmtAulas->fetchAll(PDO::FETCH_ASSOC);

// Agrupar: data → turma_id → lista de aulas
$byDayTurma = [];
$turmasCols = []; // turma_id => info
foreach ($todasAulas as $a) {
    $data = $a['data'];
    $tid = $a['turma_id'];
    $byDayTurma[$data][$tid][] = $a;
    if (!isset($turmasCols[$tid])) {
        $turmasCols[$tid] = ['nome' => $a['turma_nome']];
    }
}
ksort($byDayTurma);

// Slots de horários por data (ordenados)
$slotsByDay = [];
foreach ($byDayTurma as $data => $turmas) {
    $slots = [];
    foreach ($turmas as $tid => $aulas) {
        foreach ($aulas as $a) {
            $key = $a['inicio'] . '|' . $a['termino'];
            $slots[$key] = ['inicio' => substr($a['inicio'], 0, 5), 'termino' => substr($a['termino'], 0, 5)];
        }
    }
    ksort($slots);
    $slotsByDay[$data] = $slots;
}

// Turmas ordenadas por nome
uasort($turmasCols, function ($a, $b) {
    return strcmp($a['nome'], $b['nome']);
});
$turmaIds = array_keys($turmasCols);
?>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <h4 class="mb-0">Visualizar <?php echo get_table_label($tableName); ?></h4>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-outline-dark btn-sm">
            <i class="bi bi-printer"></i> Imprimir
        </button>
        <a href="<?php echo $baseHref; ?>?m=etapas&a=form&id=<?php echo $id; ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil"></i> Editar
        </a>
        <a href="<?php echo $baseHref; ?>?m=etapas&a=list" class="btn btn-outline-secondary btn-sm">Voltar</a>
    </div>
</div>

<!-- Dados da Etapa -->
<div class="card shadow-sm mb-4 no-print">
    <div class="card-body">
        <div class="row g-4">
            <div class="col-md-12">
                <label class="text-muted small d-block mb-1">Nome da Etapa</label>
                <div class="fw-bold fs-5"><?php echo htmlspecialchars($record['nome']); ?></div>
            </div>
            <div class="col-md-6">
                <label class="text-muted small d-block mb-1">Dia 1</label>
                <div class="fw-bold"><?php echo $formatShort($record['dia_1']); ?></div>
            </div>
            <div class="col-md-6">
                <label class="text-muted small d-block mb-1">Dia 2</label>
                <div class="fw-bold"><?php echo $formatShort($record['dia_2']); ?></div>
            </div>
        </div>
    </div>
</div>

<div id="print-area">

    <!-- Cronograma por dia -->
    <?php if (empty($byDayTurma)): ?>
        <div class="alert alert-info">Nenhuma aula cadastrada para esta etapa.</div>
    <?php else: ?>

        <?php foreach ($byDayTurma as $data => $turmaAulas): ?>
            <?php
            $slots = $slotsByDay[$data];
            // Turmas que têm aulas neste dia
            $turmasDia = array_keys($turmaAulas);
            $today = date('Y-m-d');
            $isPast = $data < $today;
            ?>

            <div class="mb-5">
                <!-- Cabeçalho do Dia -->
                <h5 class="text-center fw-bold mb-3 <?php echo $isPast ? 'text-success' : ''; ?>">
                    <?php echo $formatPtBrDate($data); ?>
                    <span class="print-only text-dark"> — <?php echo htmlspecialchars($record['nome']); ?></span>
                </h5>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0" style="font-size:.88rem;">
                        <thead>
                            <!-- Linha 1: Turmas -->
                            <tr class="table-dark">
                                <th style="width:90px;" rowspan="2" class="text-center align-middle">Horários</th>
                                <?php foreach ($turmasDia as $tid): ?>
                                    <?php $tc = $turmasCols[$tid]; ?>
                                    <th colspan="2" class="text-center">
                                        <?php echo htmlspecialchars($tc['nome']); ?>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                            <!-- Linha 2: Tema / Prof. -->
                            <tr class="table-secondary">
                                <?php foreach ($turmasDia as $tid): ?>
                                    <th class="text-center" style="min-width:220px;">Tema</th>
                                    <th class="text-center" style="width:80px;">Prof.</th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($slots as $slotKey => $slot): ?>
                                <?php
                                $rowPast = ($data . ' ' . $slot['inicio']) < date('Y-m-d H:i') ? 'table-success' : '';
                                ?>
                                <tr class="<?php echo $rowPast; ?>">
                                    <td class="text-center fw-semibold small text-nowrap">
                                        <?php echo $slot['inicio']; ?>h<br><?php echo $slot['termino']; ?>h
                                    </td>
                                    <?php foreach ($turmasDia as $tid): ?>
                                        <?php
                                        // Encontrar aula deste slot nesta turma neste dia
                                        $aulaMatch = null;
                                        foreach ($turmaAulas[$tid] ?? [] as $a) {
                                            if (substr($a['inicio'], 0, 5) === $slot['inicio'] && substr($a['termino'], 0, 5) === $slot['termino']) {
                                                $aulaMatch = $a;
                                                break;
                                            }
                                        }
                                        $profs = array_filter([
                                            $aulaMatch['prof1'] ?? '',
                                            $aulaMatch['prof2'] ?? '',
                                            $aulaMatch['prof3'] ?? '',
                                            $aulaMatch['prof4'] ?? ''
                                        ]);
                                        ?>
                                        <td class="text-center small">
                                            <?php echo $aulaMatch ? htmlspecialchars($aulaMatch['aula']) : '<span class="text-muted">—</span>'; ?>
                                        </td>
                                        <td class="text-center fw-bold small">
                                            <?php echo $aulaMatch && $profs ? implode('<br>', array_map('htmlspecialchars', $profs)) : '<span class="text-muted">—</span>'; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div><!-- /#print-area -->

<style>
    @media print {
        @page {
            size: A4 landscape;
            margin: 1cm;
        }

        /* Esconder tudo fora da área de impressão */
        body>*:not(.container-fluid) {
            display: none !important;
        }

        .no-print,
        nav,
        header,
        footer,
        .topmenu,
        .sidebar,
        .breadcrumb,
        .btn,
        button {
            display: none !important;
        }

        #print-area {
            display: block !important;
        }

        /* Quebra de página entre dias */
        #print-area .mb-5 {
            page-break-after: always;
            break-after: page;
        }

        #print-area .mb-5:last-child {
            page-break-after: avoid;
            break-after: avoid;
        }

        /* Tabela ocupa largura total */
        table {
            width: 100% !important;
            font-size: 12pt !important;
            border-collapse: collapse !important;
        }

        th,
        td {
            border: 1px solid #333 !important;
            padding: 4px 6px !important;
        }

        .table-dark th {
            background-color: #343a40 !important;
            color: #fff !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .table-secondary th {
            background-color: #e9ecef !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .table-success td,
        .table-success th {
            background-color: #ffffff !important;
            color: #000 !important;
        }

        /* Mostrar nome da etapa ao lado do dia */
        .print-only {
            display: inline !important;
        }

        /* Título do dia */
        h5 {
            font-size: 12pt !important;
            margin-bottom: 6px !important;
        }
    }
</style>