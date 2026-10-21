<?php
// app/modules/etapas_turmas/view.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';

$tableName = '104_etapas_turmas';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$engine = new CrudEngine($pdo, $tableName);

$engine->setLookups([
    'etapa' => ['table' => '104_etapas', 'label' => 'nome'],
    'turma' => ['table' => '104_turmas', 'label' => 'nome']
]);

$record = $engine->getRecord($id);
if (!$record) {
    die("Registro não encontrado.");
}

$etapaId = (int) $record['etapa'];
$turmaId = (int) $record['turma'];

// Busca data final (dia_2) da etapa para exibição condicional dos links Zoom
$stmtEtapa = $pdo->prepare("SELECT dia_2 FROM 104_etapas WHERE id = :id");
$stmtEtapa->execute([':id' => $etapaId]);
$etapaRow = $stmtEtapa->fetch(PDO::FETCH_ASSOC);
$etapaFinalDate = $etapaRow['dia_2'] ?? null;
$etapaEncerrada = $etapaFinalDate && $etapaFinalDate !== '0000-00-00'
    && strtotime($etapaFinalDate) < strtotime('today');

$baseHref = BASE_URL . 'index.php';

// Nomes formatados por extenso (para o cronograma)
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
    return $dias[date('l', $ts)] . ' – ' . date('d', $ts) . ' de ' . $meses[date('F', $ts)] . ' de ' . date('Y', $ts);
};

// ── Aulas desta turma nesta etapa ────────────────────────────────────────────
function getYoutubeEmbedUrl($url)
{
    if (!$url)
        return null;
    preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $url, $m);
    $id = $m[1] ?? null;
    return $id ? "https://www.youtube.com/embed/$id?autoplay=1" : null;
}
function getYoutubeThumbnail($url)
{
    if (!$url)
        return BASE_URL . 'assets/img/aula_nao_gravada_peq.png';
    preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $url, $m);
    $id = $m[1] ?? null;
    return $id ? "https://img.youtube.com/vi/$id/mqdefault.jpg" : BASE_URL . 'assets/img/aula_nao_gravada_peq.png';
}

$stmtAulas = $pdo->prepare("
    SELECT c.id, c.data, c.inicio, c.termino, c.aula,
           c.link_para_aula, c.sem_video,
           p1.apelido AS prof1, p2.apelido AS prof2,
           p3.apelido AS prof3, p4.apelido AS prof4
    FROM 104_cronograma c
    LEFT JOIN 104_professores p1 ON p1.id = c.professor_1
    LEFT JOIN 104_professores p2 ON p2.id = c.professor_2
    LEFT JOIN 104_professores p3 ON p3.id = c.professor_3
    LEFT JOIN 104_professores p4 ON p4.id = c.professor_4
    WHERE c.etapa = :etapa AND c.turma = :turma
    ORDER BY c.data ASC, c.inicio ASC
");
$stmtAulas->execute([':etapa' => $etapaId, ':turma' => $turmaId]);
$aulasList = $stmtAulas->fetchAll(PDO::FETCH_ASSOC);

// Agrupar por data → slots
$byDay = [];
foreach ($aulasList as $a) {
    $byDay[$a['data']][] = $a;
}
ksort($byDay);
?>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <h4 class="mb-0">Visualizar <?php echo get_table_label($tableName); ?></h4>
    <div class="d-flex gap-2">
        <?php if (!empty($aulasList)): ?>
            <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal"
                data-bs-target="#modalListaVideos">
                <i class="bi bi-collection-play"></i> Lista Vídeos
            </button>
        <?php endif; ?>
        <button onclick="window.print()" class="btn btn-outline-dark btn-sm">
            <i class="bi bi-printer"></i> Imprimir
        </button>
        <a href="<?php echo $baseHref; ?>?m=etapas_turmas&a=form&id=<?php echo $id; ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil"></i> Editar
        </a>
        <a href="<?php echo $baseHref; ?>?m=etapas_turmas&a=list" class="btn btn-outline-secondary btn-sm">Voltar</a>
    </div>
</div>

<!-- Dados -->
<div class="card shadow-sm mb-4 no-print">
    <div class="card-body">
        <div class="row g-4">
            <div class="col-md-4">
                <label class="text-muted small d-block mb-1">Etapa</label>
                <div class="fw-bold fs-5"><?php echo htmlspecialchars($record['etapa_label'] ?? 'N/A'); ?></div>
            </div>
            <div class="col-md-4">
                <label class="text-muted small d-block mb-1">Turma</label>
                <div class="fw-bold fs-5"><?php echo htmlspecialchars($record['turma_label'] ?? 'N/A'); ?></div>
            </div>
            <div class="col-md-4">
                <label class="text-muted small d-block mb-1">Etapa da Turma</label>
                <div class="fw-bold"><?php echo htmlspecialchars($record['etapa_da_turma']); ?></div>
            </div>
            <?php if (!$etapaEncerrada): ?>
                <div class="col-md-6">
                    <label class="text-muted small d-block mb-1">Link Zoom Dia 1</label>
                    <div class="fw-bold">
                        <?php if ($record['link_zoom_dia_1']): ?>
                            <a href="<?php echo htmlspecialchars($record['link_zoom_dia_1']); ?>" target="_blank">
                                <?php echo htmlspecialchars($record['link_zoom_dia_1']); ?>
                            </a>
                        <?php else: ?>—<?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="text-muted small d-block mb-1">Link Zoom Dia 2</label>
                    <div class="fw-bold">
                        <?php if ($record['link_zoom_dia_2']): ?>
                            <a href="<?php echo htmlspecialchars($record['link_zoom_dia_2']); ?>" target="_blank">
                                <?php echo htmlspecialchars($record['link_zoom_dia_2']); ?>
                            </a>
                        <?php else: ?>—<?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($aulasList)): ?>
    <!-- Modal: Lista para Vídeos (sem links) -->
    <div class="modal fade" id="modalListaVideos" tabindex="-1" aria-labelledby="modalListaVideosLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white py-2">
                    <h6 class="modal-title" id="modalListaVideosLabel">
                        <i class="bi bi-collection-play me-2"></i>Lista para vídeos
                        <span class="badge bg-secondary ms-2"><?php echo count($aulasList); ?> aulas</span>
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Fechar"></button>
                </div>
                <div class="modal-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($aulasList as $i => $a):
                            $turma = $record['turma_label'] ?? 'SemTurma';
                            $etapa = $record['etapa_label'] ?? 'SemEtapa';
                            $titulo = $a['aula'] ?? 'SemTitulo';
                            $profs = array_filter([$a['prof1'] ?? '', $a['prof2'] ?? '', $a['prof3'] ?? '', $a['prof4'] ?? '']);
                            $profsStr = implode('-', $profs) ?: 'SemProf';
                            $filename = toFilename($turma) . '-' . toFilename($etapa) . '-' . toFilename($titulo) . '-' . toFilename($profsStr);
                            ?>
                            <li class="list-group-item px-3 py-2 d-flex align-items-center gap-2" style="font-size:.83rem;">
                                <span class="badge bg-light text-dark border"
                                    style="min-width:28px;"><?php echo $i + 1; ?></span>
                                <span class="text-truncate"
                                    style="max-width:100%;"><?php echo htmlspecialchars($filename); ?></span>
                                <?php if (empty($a['link_para_aula'])): ?>
                                    <span class="badge bg-warning text-dark ms-auto">sem vídeo</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
// ── Helper: converte string para nome de arquivo seguro ──────────────────────
function toFilename(string $str): string
{
    $str = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
    $str = preg_replace('/[^a-zA-Z0-9\s_-]/', '', $str);
    $str = trim(preg_replace('/\s+/', ' ', $str));
    $str = str_replace(' ', '_', $str);
    return $str;
}
?>

<!-- Lista para Vídeos -->
<?php if (!empty($aulasList)): ?>
    <div class="card shadow-sm mb-4 no-print">
        <div class="card-header bg-dark text-white d-flex align-items-center gap-2 py-2">
            <i class="bi bi-collection-play"></i>
            <strong class="small">Lista para vídeos</strong>
            <span class="badge bg-secondary ms-auto"><?php echo count($aulasList); ?> aulas</span>
        </div>
        <div class="card-body p-0">
            <ul class="list-group list-group-flush">
                <?php foreach ($aulasList as $i => $a):
                    $turma = $record['turma_label'] ?? 'SemTurma';
                    $etapa = $record['etapa_label'] ?? 'SemEtapa';
                    $titulo = $a['aula'] ?? 'SemTitulo';
                    $profs = array_filter([$a['prof1'] ?? '', $a['prof2'] ?? '', $a['prof3'] ?? '', $a['prof4'] ?? '']);
                    $profsStr = implode('-', $profs) ?: 'SemProf';
                    $filename = toFilename($turma) . '-' . toFilename($etapa) . '-' . toFilename($titulo) . '-' . toFilename($profsStr);
                    $aulaUrl = $a['link_para_aula'] ?? '';
                    ?>
                    <li class="list-group-item px-3 py-2 d-flex align-items-center gap-2" style="font-size:.83rem;">
                        <span class="badge bg-light text-dark border me-1" style="min-width:28px;"><?php echo $i + 1; ?></span>
                        <a href="<?php echo $aulaUrl ? htmlspecialchars($aulaUrl) : '#'; ?>" target="_blank" rel="noopener"
                            class="text-truncate <?php echo $aulaUrl ? 'text-primary' : 'text-muted'; ?>"
                            style="max-width:100%;" title="<?php echo htmlspecialchars($filename); ?>">
                            <?php echo htmlspecialchars($filename); ?>
                        </a>
                        <?php if (!$aulaUrl): ?>
                            <span class="badge bg-warning text-dark ms-auto">sem vídeo</span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<!-- Cronograma da Turma -->
<div id="print-area">

    <?php if (empty($byDay)): ?>
        <div class="alert alert-info no-print">Nenhuma aula cadastrada para esta turma nesta etapa.</div>
    <?php else: ?>

        <?php foreach ($byDay as $data => $aulas): ?>
            <?php
            $today = date('Y-m-d');
            $isPast = $data < $today;
            ?>
            <div class="mb-5">
                <!-- Título do Dia -->
                <h5 class="text-center fw-bold mb-3 <?php echo $isPast ? 'text-success' : ''; ?>">
                    <?php echo $formatPtBrDate($data); ?>
                    <span class="print-only text-dark">
                        &mdash; <?php echo htmlspecialchars($record['turma_label'] ?? ''); ?>
                        <?php if (!empty($record['etapa_da_turma'])): ?>
                            &middot; <?php echo htmlspecialchars($record['etapa_da_turma']); ?>
                        <?php endif; ?>
                    </span>
                </h5>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0" style="font-size:.88rem;">
                        <thead>
                            <tr class="table-dark">
                                <th style="width:90px;" class="text-center">Horários</th>
                                <th class="text-center">Tema</th>
                                <th style="width:120px;" class="text-center">Professores</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($aulas as $a):
                                $rowPast = ($data . ' ' . $a['inicio']) < date('Y-m-d H:i') ? 'table-success' : '';
                                $profs = array_filter([
                                    $a['prof1'] ?? '',
                                    $a['prof2'] ?? '',
                                    $a['prof3'] ?? '',
                                    $a['prof4'] ?? ''
                                ]);
                                $embedUrl = getYoutubeEmbedUrl($a['link_para_aula'] ?? '');
                                $thumbUrl = getYoutubeThumbnail($a['link_para_aula'] ?? '');
                                $profsText = implode(', ', $profs) ?: 'Não informado';
                                $fmtData = date('d/m/Y', strtotime($data));
                                $horario = substr((string) $a['inicio'], 0, 5) . ' - ' . substr((string) $a['termino'], 0, 5);
                                ?>
                                <tr class="<?php echo $rowPast; ?> btn-view-aula" style="cursor:pointer;"
                                    data-aula="<?php echo htmlspecialchars($a['aula']); ?>"
                                    data-video="<?php echo htmlspecialchars($embedUrl ?? ''); ?>"
                                    data-thumb="<?php echo htmlspecialchars($thumbUrl); ?>"
                                    data-turma="<?php echo htmlspecialchars($record['turma_label'] ?? ''); ?>"
                                    data-etapa="<?php echo htmlspecialchars($record['etapa_label'] ?? ''); ?>"
                                    data-profs="<?php echo htmlspecialchars($profsText); ?>"
                                    data-data="<?php echo htmlspecialchars($fmtData); ?>"
                                    data-horario="<?php echo htmlspecialchars($horario); ?>"
                                    data-sem-video="<?php echo $a['sem_video'] ?? '0'; ?>">
                                    <td class="text-center fw-semibold small text-nowrap">
                                        <?php echo substr((string) $a['inicio'], 0, 5); ?>h<br>
                                        <?php echo substr((string) $a['termino'], 0, 5); ?>h
                                    </td>
                                    <td><?php echo htmlspecialchars($a['aula']); ?></td>
                                    <td class="text-center fw-bold small">
                                        <?php echo $profs
                                            ? implode('<br>', array_map('htmlspecialchars', $profs))
                                            : '<span class="text-muted">—</span>'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>

    <?php endif; ?>
</div><!-- /#print-area -->

<!-- Modal de Visualização de Aula -->
<div class="modal fade" id="modalViewAula" tabindex="-1" aria-labelledby="modalViewAulaLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered shadow-lg">
        <div class="modal-content border-0 overflow-hidden">
            <div class="modal-header bg-dark text-white py-2 border-0">
                <h6 class="modal-title" id="modalViewAulaLabel"><i class="bi bi-play-circle me-2"></i>Visualizar Aula
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body p-0 bg-light">
                <div class="row g-0">
                    <!-- Coluna do Vídeo -->
                    <div class="col-lg-8 bg-black d-flex align-items-center justify-content-center"
                        style="min-height: 450px;">
                        <iframe id="view-video-iframe" src="" class="w-100 h-100 d-none" frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen style="min-height: 450px;"></iframe>
                        <div id="view-video-placeholder" class="text-center p-5 d-none">
                            <img id="view-placeholder-img" src="" class="img-fluid rounded shadow-sm mb-3"
                                style="max-height: 250px;">
                            <h5 id="view-placeholder-text" class="text-white opacity-75">Essa aula não possui gravação
                                disponível.</h5>
                        </div>
                    </div>
                    <!-- Coluna da Informação -->
                    <div class="col-lg-4 p-4 d-flex flex-column">
                        <div class="mb-4">
                            <h4 id="view-aula-title" class="fw-bold text-dark mb-1">Título da Aula</h4>
                            <p id="view-aula-subtitle" class="text-primary fw-bold small mb-3">Turma - Etapa</p>
                            <hr class="my-3 opacity-10">
                        </div>
                        <div class="flex-grow-1">
                            <div class="mb-3">
                                <label class="text-muted small d-block mb-1">Professores</label>
                                <div class="d-flex align-items-start">
                                    <i class="bi bi-person-badge text-primary me-2 mt-1"></i>
                                    <span id="view-aula-profs" class="fw-semibold">Nenhum informado</span>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="text-muted small d-block mb-1">Data</label>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-calendar3 text-primary me-2"></i>
                                        <span id="view-aula-data" class="fw-semibold">-</span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <label class="text-muted small d-block mb-1">Horário</label>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-clock text-primary me-2"></i>
                                        <span id="view-aula-horario" class="fw-semibold">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-auto pt-4 text-center">
                            <button type="button" class="btn btn-secondary w-100"
                                data-bs-dismiss="modal">Fechar</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const viewModalEl = document.getElementById('modalViewAula');
        const viewModal = new bootstrap.Modal(viewModalEl);

        document.querySelectorAll('.btn-view-aula').forEach(function (row) {
            row.addEventListener('click', function () {
                const d = this.dataset;
                const iframe = document.getElementById('view-video-iframe');
                const placeholder = document.getElementById('view-video-placeholder');

                document.getElementById('view-aula-title').textContent = d.aula;
                document.getElementById('view-aula-subtitle').textContent = d.turma + (d.etapa ? ' - ' + d.etapa : '');
                document.getElementById('view-aula-profs').textContent = d.profs;
                document.getElementById('view-aula-data').textContent = d.data;
                document.getElementById('view-aula-horario').textContent = d.horario;

                if (d.semVideo === '1') {
                    iframe.src = '';
                    iframe.classList.add('d-none');
                    placeholder.classList.remove('d-none');
                    document.getElementById('view-placeholder-img').src = d.thumb;
                    document.getElementById('view-placeholder-text').textContent = 'Por respeito à privacidade do aluno, esta aula não possui vídeo.';
                } else if (d.video && d.video !== '') {
                    iframe.src = d.video;
                    iframe.classList.remove('d-none');
                    placeholder.classList.add('d-none');
                } else {
                    iframe.src = '';
                    iframe.classList.add('d-none');
                    placeholder.classList.remove('d-none');
                    document.getElementById('view-placeholder-img').src = d.thumb;
                    document.getElementById('view-placeholder-text').textContent = 'Essa aula não possui gravação disponível.';
                }

                viewModal.show();
            });
        });

        // Para o vídeo ao fechar o modal
        viewModalEl.addEventListener('hidden.bs.modal', function () {
            document.getElementById('view-video-iframe').src = '';
        });
    });
</script>

<style>
    .print-only {
        display: none;
    }

    @media print {
        @page {
            size: A4 portrait;
            margin: 1cm;
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

        #print-area .mb-5 {
            page-break-after: avoid;
            break-after: avoid;
        }

        #print-area .mb-5:last-child {
            page-break-after: avoid;
            break-after: avoid;
        }

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

        .table-success td,
        .table-success th {
            background-color: #ffffff !important;
            color: #000 !important;
        }

        .print-only {
            display: inline !important;
        }

        h5 {
            font-size: 12pt !important;
            margin-bottom: 6px !important;
        }
    }
</style>