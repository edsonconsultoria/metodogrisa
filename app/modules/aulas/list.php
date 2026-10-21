<?php
// app/modules/aulas/list.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';
require_once __DIR__ . '/../../helpers/pagination.php';
$cronogramaHelper = __DIR__ . '/../../helpers/cronograma_arquivos.php';
if (file_exists($cronogramaHelper)) {
    require_once $cronogramaHelper;
} elseif (!function_exists('cronograma_parse_arquivos')) {
    function cronograma_parse_arquivos($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $result = [];
            foreach ($decoded as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $file = trim((string) ($item['file'] ?? ''));
                if ($file === '') {
                    continue;
                }
                $result[] = [
                    'name' => trim((string) ($item['name'] ?? basename($file))),
                    'file' => $file,
                    'size' => (int) ($item['size'] ?? 0),
                    'type' => trim((string) ($item['type'] ?? ''))
                ];
            }
            return $result;
        }
        
        // Se falhar o json_decode mas começar com '[', é um JSON malformado/truncado
        if (strpos($raw, '[') === 0 || strpos($raw, '{') === 0) {
            $result = [];
            // Tentar capturar objetos JSON válidos individuais
            preg_match_all('/\{"name"\s*:\s*"(.*?)"\s*,\s*"file"\s*:\s*"(.*?)"(.*?)\}/i', $raw, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $name = $match[1];
                $file = $match[2];
                $extra = $match[3];
                
                $size = 0;
                if (preg_match('/"size"\s*:\s*(\d+)/i', $extra, $sz)) {
                    $size = (int) $sz[1];
                }
                $type = '';
                if (preg_match('/"type"\s*:\s*"(.*?)"/i', $extra, $tp)) {
                    $type = $tp[1];
                }
                
                $result[] = [
                    'name' => stripslashes($name),
                    'file' => stripslashes($file),
                    'size' => $size,
                    'type' => $type
                ];
            }
            
            // Caso nenhum objeto completo tenha sido casado, mas temos um parcial:
            if (empty($result)) {
                if (preg_match('/"name"\s*:\s*"(.*?)"/i', $raw, $nm)) {
                    $name = $nm[1];
                    $file = '';
                    if (preg_match('/"file"\s*:\s*"(.*?)"/i', $raw, $fl)) {
                        $file = $fl[1];
                    }
                    $result[] = [
                        'name' => stripslashes($name),
                        'file' => stripslashes($file),
                        'size' => 0,
                        'type' => ''
                    ];
                }
            }
            
            if (!empty($result)) {
                return $result;
            }
            return [];
        }
        
        return [['name' => basename($raw), 'file' => $raw, 'size' => 0, 'type' => '']];
    }
    function cronograma_file_url($filePath)
    {
        $filePath = trim((string) $filePath);
        if ($filePath === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $filePath)) {
            return $filePath;
        }
        $relative = ltrim(str_replace('\\', '/', $filePath), '/');
        $base = rtrim(BASE_URL, '/');
        return ($base === '' ? '' : $base) . '/uploads/' . $relative;
    }
    function cronograma_render_arquivos_html(array $items)
    {
        if (empty($items)) {
            return '<span class="text-muted">—</span>';
        }
        $html = '<ul class="list-unstyled mb-0">';
        foreach ($items as $item) {
            $name = htmlspecialchars((string) ($item['name'] ?? 'Arquivo'), ENT_QUOTES, 'UTF-8');
            $url = htmlspecialchars(cronograma_file_url($item['file'] ?? ''), ENT_QUOTES, 'UTF-8');
            $html .= '<li><a href="' . $url . '" target="_blank" rel="noopener" download>' . $name . '</a></li>';
        }
        $html .= '</ul>';
        return $html;
    }
}

$tableName = '104_cronograma';
$engine = new CrudEngine($pdo, $tableName);

$current_user = current_user();
$perfil_usuario = $current_user['perfil'] ?? 'OPERACIONAL';
$user_id = $current_user['id'] ?? 0;

$engine->setLookups([
    'etapa' => ['table' => '104_etapas', 'label' => 'nome'],
    'turma' => ['table' => '104_turmas', 'label' => 'nome'],
    'professor_1' => ['table' => '104_professores', 'label' => 'apelido'],
    'professor_2' => ['table' => '104_professores', 'label' => 'apelido'],
    'professor_3' => ['table' => '104_professores', 'label' => 'apelido'],
    'professor_4' => ['table' => '104_professores', 'label' => 'apelido']
]);

$page = (int) ($_GET['page'] ?? 1);
$search = $_GET['search'] ?? '';
$filtroEtapa = (int) ($_GET['filtro_etapa'] ?? 0);
$filtroTurma = (int) ($_GET['filtro_turma'] ?? 0);
$filtroProfessor = (int) ($_GET['filtro_professor'] ?? 0);
$filtroPendente = (int) ($_GET['filtro_pendente'] ?? 0);

// Opções para os selects com filtros de perfil
if (current_user_is_aluno()) {
    $etapasOpts = $pdo->prepare("SELECT DISTINCT e.id, e.nome FROM 104_etapas e JOIN 104_cronograma c ON c.etapa = e.id WHERE c.turma IN (SELECT turma FROM 104_alunos WHERE aluno = :aluno_id AND (status = 'Ativo' OR status = '' OR status IS NULL)) ORDER BY e.nome ASC");
    $etapasOpts->execute([':aluno_id' => $user_id]);
    $etapasOpts = $etapasOpts->fetchAll(PDO::FETCH_ASSOC);

    $turmasOpts = $pdo->prepare("SELECT id, nome FROM 104_turmas WHERE id IN (SELECT turma FROM 104_alunos WHERE aluno = :aluno_id AND (status = 'Ativo' OR status = '' OR status IS NULL)) ORDER BY nome DESC");
    $turmasOpts->execute([':aluno_id' => $user_id]);
    $turmasOpts = $turmasOpts->fetchAll(PDO::FETCH_ASSOC);

    $professoresOpts = $pdo->prepare("SELECT DISTINCT p.id, p.apelido FROM 104_professores p JOIN 104_cronograma c ON (c.professor_1 = p.id OR c.professor_2 = p.id OR c.professor_3 = p.id OR c.professor_4 = p.id) WHERE c.turma IN (SELECT turma FROM 104_alunos WHERE aluno = :aluno_id AND (status = 'Ativo' OR status = '' OR status IS NULL)) ORDER BY p.apelido ASC");
    $professoresOpts->execute([':aluno_id' => $user_id]);
    $professoresOpts = $professoresOpts->fetchAll(PDO::FETCH_ASSOC);
} else {
    $etapasOpts = $pdo->query("SELECT id, nome FROM 104_etapas ORDER BY id DESC, nome ASC")->fetchAll(PDO::FETCH_ASSOC);
    $turmasOpts = $pdo->query("SELECT id, nome FROM 104_turmas ORDER BY nome DESC")->fetchAll(PDO::FETCH_ASSOC);
    $professoresOpts = $pdo->query("SELECT id, apelido FROM 104_professores ORDER BY apelido ASC")->fetchAll(PDO::FETCH_ASSOC);
}
$moodleVideos = $pdo->query("SELECT youtube_url, aula_titulo, curso_nome, etapa_nome FROM xmoodle_aulas ORDER BY curso_nome ASC, etapa_nome ASC, aula_titulo ASC")->fetchAll(PDO::FETCH_ASSOC);

// Montar filtros para o CrudEngine
$filters = [];
if ($filtroEtapa > 0)
    $filters['etapa'] = $filtroEtapa;
if ($filtroTurma > 0)
    $filters['turma'] = $filtroTurma;

$_customWhereParts = [];
if ($filtroProfessor > 0) {
    $_customWhereParts[] = "(professor_1 = $filtroProfessor OR professor_2 = $filtroProfessor OR professor_3 = $filtroProfessor OR professor_4 = $filtroProfessor)";
}

if (current_user_is_aluno()) {
    $_customWhereParts[] = "turma IN (SELECT turma FROM 104_alunos WHERE aluno = $user_id AND (status = 'Ativo' OR status = '' OR status IS NULL))";
}

if ($filtroPendente === 1) {
    $_customWhereParts[] = "(link_para_aula = '' OR link_para_aula IS NULL) AND (sem_video != '1' OR sem_video IS NULL)";
}
if (!empty($_customWhereParts)) {
    $filters['_custom_where'] = implode(' AND ', $_customWhereParts);
}

// Buscamos apenas aulas que NÃO tenham sem_video = 'S' (opcional, dependendo do que o usuário quer)
// Mas a pedido do usuário, vamos mostrar as caixas com miniatura.
$result = $engine->getList($page, 12, $search, $filters, 'data DESC, inicio DESC');
$data = $result['data'];
$totalPages = $result['totalPages'];

$isAdmin = current_user_is_at_least_admin();
$isProfessor = current_user_is_professor();
$canEdit = $isAdmin || $isProfessor;

$baseHref = BASE_URL . 'index.php';

function getYoutubeId($url)
{
    if (!$url)
        return null;
    preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $url, $match);
    return $match[1] ?? null;
}

function getYoutubeEmbedUrl($url)
{
    $id = getYoutubeId($url);
    return $id ? "https://www.youtube.com/embed/$id?autoplay=1" : null;
}

function getYoutubeThumbnail($url)
{
    $id = getYoutubeId($url);
    if ($id) {
        return "https://img.youtube.com/vi/$id/mqdefault.jpg";
    }
    // Usando imagem local quando não tem vídeo
    return BASE_URL . "assets/img/aula_nao_gravada_peq.png";
}
// ─── PAINEL DE TURMAS: buscar turmas e aulas agrupadas por etapa ────────────

// 1. Buscar turmas disponíveis (respeitando perfil do usuário)
if (current_user_is_aluno()) {
    $stmtTurmasPainel = $pdo->prepare(
        "SELECT t.id, t.nome, t.inicio, t.termino, t.documentos
         FROM 104_turmas t
         WHERE t.id IN (
             SELECT turma FROM 104_alunos
             WHERE aluno = :aluno_id AND (status = 'Ativo' OR status = '' OR status IS NULL)
         )
         ORDER BY t.inicio DESC, t.nome DESC"
    );
    $stmtTurmasPainel->execute([':aluno_id' => $user_id]);
    $turmasPainel = $stmtTurmasPainel->fetchAll(PDO::FETCH_ASSOC);
} else {
    $turmasPainel = $pdo->query(
        "SELECT id, nome, inicio, termino, documentos FROM 104_turmas ORDER BY inicio DESC, nome DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
}

// 2. Buscar aulas agrupadas por turma e etapa para o painel
$aulasPorTurma = [];
if (!empty($turmasPainel)) {
    $turmaIds = array_column($turmasPainel, 'id');
    $placeholdersTurmas = implode(',', array_fill(0, count($turmaIds), '?'));

    $sqlAulasPainel = "
        SELECT
            c.id, c.aula, c.data, c.inicio, c.termino,
            c.link_para_aula, c.arquivos, c.sem_video,
            c.turma, c.etapa,
            e.nome AS etapa_nome,
            e.id   AS etapa_id,
            et.etapa_da_turma AS etapa_custom,
            p1.apelido AS prof1_apelido,
            p2.apelido AS prof2_apelido,
            p3.apelido AS prof3_apelido,
            p4.apelido AS prof4_apelido
        FROM 104_cronograma c
        LEFT JOIN 104_etapas e ON e.id = c.etapa
        LEFT JOIN 104_etapas_turmas et ON et.etapa = c.etapa AND et.turma = c.turma
        LEFT JOIN 104_professores p1 ON p1.id = c.professor_1
        LEFT JOIN 104_professores p2 ON p2.id = c.professor_2
        LEFT JOIN 104_professores p3 ON p3.id = c.professor_3
        LEFT JOIN 104_professores p4 ON p4.id = c.professor_4
        WHERE c.turma IN ($placeholdersTurmas)
        ORDER BY c.turma ASC, e.dia_1 ASC, e.id ASC, c.data ASC, c.inicio ASC
    ";
    $stmtAulasPainel = $pdo->prepare($sqlAulasPainel);
    $stmtAulasPainel->execute(array_values($turmaIds));
    $rowsAulasPainel = $stmtAulasPainel->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rowsAulasPainel as $ra) {
        $tid = (int) $ra['turma'];
        $eid = (int) $ra['etapa_id'];
        if (!isset($aulasPorTurma[$tid])) {
            $aulasPorTurma[$tid] = [];
        }
        if (!isset($aulasPorTurma[$tid][$eid])) {
            $aulasPorTurma[$tid][$eid] = [
                'etapa_nome' => $ra['etapa_nome'] ?: 'Sem Etapa',
                'etapa_da_turma' => $ra['etapa_custom'] ?? '',
                'aulas'      => []
            ];
        }
        $aulasPorTurma[$tid][$eid]['aulas'][] = $ra;
    }
}

// Helper: formata mês/ano a partir de data
function _mg_mes_ano($dateStr) {
    if (!$dateStr || $dateStr === '0000-00-00') return '';
    $ts = strtotime($dateStr);
    $meses = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
    return $meses[(int)date('n', $ts) - 1] . '/' . date('Y', $ts);
}
// Helper: retorna o dia da semana em português
function _mg_dia_semana_pt($dateStr) {
    if (!$dateStr || $dateStr === '0000-00-00') return '';
    $w = date('w', strtotime($dateStr));
    $dias = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
    return $dias[$w] ?? '';
}
?>

<?php
$turmaViewId = isset($_GET['turma_view']) ? (int) $_GET['turma_view'] : 0;
$turmaViewInfo = null;
$aulasTurmaView = [];
if ($turmaViewId > 0) {
    $podeVer = false;
    foreach ($turmasPainel as $tp) {
        if ((int)$tp['id'] === $turmaViewId) {
            $podeVer = true;
            $turmaViewInfo = $tp;
            break;
        }
    }
    if ($podeVer && $turmaViewInfo) {
        $stmtAulasView = $pdo->prepare("
            SELECT c.*, e.nome AS etapa_nome, et.etapa_da_turma AS etapa_custom
            FROM 104_cronograma c
            LEFT JOIN 104_etapas e ON e.id = c.etapa
            LEFT JOIN 104_etapas_turmas et ON et.etapa = c.etapa AND et.turma = c.turma
            WHERE c.turma = :turma_id
            ORDER BY e.dia_1 ASC, e.id ASC, c.data ASC, c.inicio ASC
        ");
        $stmtAulasView->execute([':turma_id' => $turmaViewId]);
        $aulasTurmaView = $stmtAulasView->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $turmaViewId = 0;
    }
}
?>

<!-- ═══════════════════════════════════════════════════════════════
     PAINEL DE TURMAS — estilo Moodle
     ═══════════════════════════════════════════════════════════════ -->
<div class="mg-turmas-painel mb-4">

    <?php if ($turmaViewId > 0 && $turmaViewInfo): ?>
        <!-- Visualização Única da Turma (aulas ordenadas por horário, sem sanfona) -->
        <div class="mg-turma-view mb-4" id="mg-turma-view-top" style="scroll-margin-top: 20px;">
            <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
                <a href="index.php?m=aulas&a=list" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-2">
                    <i class="bi bi-arrow-left"></i> Voltar para as Turmas
                </a>
                <h4 class="m-0 text-dark fw-bold">
                    <i class="bi bi-mortarboard-fill text-primary me-2"></i>
                    <?php echo htmlspecialchars($turmaViewInfo['nome']); ?>
                </h4>
            </div>

            <?php if (!empty($aulasPorTurma[$turmaViewId])): ?>
                <!-- Âncoras rápidas para as etapas -->
                <div class="mg-etapas-anchors d-flex align-items-center gap-2 flex-wrap mb-4 bg-white p-3 rounded border shadow-sm">
                    <?php $anchorNum = 0; foreach ($aulasPorTurma[$turmaViewId] as $eid => $etGrp): $anchorNum++; ?>
                        <a href="#etapa-block-<?php echo $eid; ?>" class="btn btn-sm btn-outline-primary fw-bold" style="border-radius: 20px; font-size: .65rem; padding: 2px 8px; transition: all .15s;">
                            <?php echo htmlspecialchars($etGrp['etapa_da_turma'] ?: 'Etapa ' . sprintf('%02d', $anchorNum)); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php 
            $turmaDocs = [];
            if (!empty($turmaViewInfo['documentos'])) {
                $turmaDocs = cronograma_parse_arquivos($turmaViewInfo['documentos']);
            }
            if (!empty($turmaDocs)):
            ?>
                <!-- Caixa de documentos da turma -->
                <div class="mg-turma-documentos-box mb-4 p-4 rounded bg-white border shadow-sm">
                    <h5 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                        <i class="bi bi-folder2-open text-warning" style="font-size: 1.25rem;"></i>
                        Documentos da Turma
                    </h5>
                    <div class="row g-2">
                        <?php foreach ($turmaDocs as $doc): 
                            $docName = htmlspecialchars((string) ($doc['name'] ?? 'Arquivo'), ENT_QUOTES, 'UTF-8');
                            $docUrl = htmlspecialchars(cronograma_file_url($doc['file'] ?? ''), ENT_QUOTES, 'UTF-8');
                        ?>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="d-flex align-items-center p-2 rounded border bg-light shadow-xs" style="transition: all 0.2s;">
                                    <div class="me-2 text-primary d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: rgba(13, 110, 253, 0.1); border-radius: 6px;">
                                        <i class="bi bi-file-earmark-text" style="font-size: 1.1rem;"></i>
                                    </div>
                                    <div class="flex-grow-1 min-width-0">
                                        <a href="<?php echo $docUrl; ?>" target="_blank" rel="noopener" download class="fw-semibold text-decoration-none text-dark d-block text-truncate" style="font-size: 0.85rem;" title="<?php echo $docName; ?>">
                                            <?php echo $docName; ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (empty($aulasPorTurma[$turmaViewId])): ?>
                <div class="mg-empty-section border rounded bg-white py-5 shadow-sm text-center">
                    <i class="bi bi-inbox-fill text-muted display-4 d-block mb-3"></i>
                    <p class="text-muted">Nenhuma aula cadastrada para esta turma.</p>
                </div>
            <?php else: ?>
                <?php $etNum = 0; foreach ($aulasPorTurma[$turmaViewId] as $eid => $etGrp): ?>
                    <?php
                    $etNum++;
                    $etNome      = $etGrp['etapa_nome'];
                    $primAula    = $etGrp['aulas'][0] ?? null;
                    $etMesAno    = $primAula ? _mg_mes_ano($primAula['data']) : '';
                    $totAulasSec = count($etGrp['aulas']);

                    // Agrupar aulas desta etapa por data
                    $aulasPorData = [];
                    foreach ($etGrp['aulas'] as $aula) {
                        $dataKey = !empty($aula['data']) ? $aula['data'] : 'sem-data';
                        if (!isset($aulasPorData[$dataKey])) {
                            $aulasPorData[$dataKey] = [];
                        }
                        $aulasPorData[$dataKey][] = $aula;
                    }
                    ?>
                    <div class="mg-etapa-block mb-5 p-4 rounded bg-light border shadow-sm" id="etapa-block-<?php echo $eid; ?>" style="scroll-margin-top: 20px;">
                        <!-- Cabeçalho da Etapa (Etapa, Nome e Mês por cima) -->
                        <div class="mg-etapa-header-section mb-4 pb-2 border-bottom">
                            <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between">
                                <div class="d-flex align-items-center gap-3 flex-wrap">
                                    <span class="badge bg-danger px-3 py-2 text-uppercase fw-bold" style="border-radius: 20px; font-size: .75rem;">
                                        <?php echo htmlspecialchars($etGrp['etapa_da_turma'] ?: 'Etapa ' . sprintf('%02d', $etNum)); ?>
                                    </span>
                                    <h4 class="m-0 fw-bold text-dark" style="font-size: 1.25rem;"><?php echo htmlspecialchars($etNome); ?></h4>
                                    <?php if ($etMesAno): ?>
                                        <span class="badge bg-dark px-3 py-2" style="border-radius: 20px; font-size: .75rem;"><i class="bi bi-calendar3 me-1"></i> <?php echo htmlspecialchars($etMesAno); ?></span>
                                    <?php endif; ?>
                                </div>
                                <span class="text-muted small fw-semibold">
                                    <i class="bi bi-play-circle-fill me-1 text-primary"></i> <?php echo $totAulasSec; ?> atividades
                                </span>
                            </div>
                        </div>

                        <!-- Aulas Abaixo Separadas por Data -->
                        <div class="mg-etapa-content-section">
                            <?php foreach ($aulasPorData as $dataKey => $aulasDaData): ?>
                                <?php
                                if ($dataKey !== 'sem-data') {
                                    $dataFmt = date('d/m/Y', strtotime($dataKey));
                                    $diaSemana = _mg_dia_semana_pt($dataKey);
                                } else {
                                    $dataFmt = 'Sem data';
                                    $diaSemana = '';
                                }
                                ?>
                                <div class="mg-data-group mb-4">
                                    <!-- Separador com Data e Dia da Semana -->
                                    <div class="mg-data-header d-flex align-items-center mb-3">
                                        <span class="fw-bold text-primary me-2" style="font-size: .95rem;">
                                            <i class="bi bi-calendar-event me-1"></i> <?php echo $dataFmt; ?>
                                        </span>
                                        <?php if ($diaSemana): ?>
                                            <span class="badge bg-white text-secondary border px-2 py-1 small" style="font-size: .7rem; font-weight: 600;"><?php echo $diaSemana; ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Lista de Atividades desta Data -->
                                    <ul class="mg-activity-list rounded border shadow-sm bg-white overflow-hidden mb-0">
                                        <?php foreach ($aulasDaData as $aula): ?>
                                            <?php
                                            $thumbUrl    = getYoutubeThumbnail($aula['link_para_aula']);
                                            $embedUrl    = getYoutubeEmbedUrl($aula['link_para_aula']);
                                            $arquivosArr = cronograma_parse_arquivos($aula['arquivos'] ?? '');
                                            $semVideo    = ($aula['sem_video'] ?? '') === '1';
                                            $temVideo    = !$semVideo && !empty($aula['link_para_aula']);
                                            $aulaSlug    = addslashes(htmlspecialchars_decode($aula['aula']));
                                            ?>
                                            <li class="mg-activity">
                                                <div class="mg-activity__icon <?php echo $temVideo ? 'mg-activity__icon--video' : 'mg-activity__icon--pending'; ?>"
                                                     <?php if ($embedUrl): ?>onclick="mgAbrirVideo('<?php echo addslashes($embedUrl); ?>', '<?php echo $aulaSlug; ?>')" style="cursor:pointer;"<?php endif; ?>>
                                                    <?php if ($temVideo): ?>
                                                        <i class="bi bi-play-circle-fill"></i>
                                                    <?php elseif ($semVideo): ?>
                                                        <i class="bi bi-lock-fill"></i>
                                                    <?php else: ?>
                                                        <i class="bi bi-hourglass-split"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="mg-activity__body">
                                                    <div class="mg-activity__main">
                                                        <div class="mg-activity__thumb <?php echo $temVideo ? 'mg-activity__thumb--click' : ''; ?>"
                                                             <?php if ($embedUrl): ?>onclick="mgAbrirVideo('<?php echo addslashes($embedUrl); ?>', '<?php echo $aulaSlug; ?>')"<?php endif; ?>>
                                                            <img src="<?php echo htmlspecialchars($thumbUrl); ?>" alt="Thumb" loading="lazy"
                                                                 onerror="this.src='<?php echo BASE_URL; ?>assets/img/aula_nao_gravada_peq.png'">
                                                            <?php if ($temVideo): ?><span class="mg-activity__play"><i class="bi bi-play-fill"></i></span><?php endif; ?>
                                                        </div>
                                                                                        <!-- Título e meta -->
                                                        <div class="mg-activity__info">
                                                            <?php
                                                            $profs = [];
                                                            if (!empty($aula['prof1_apelido'])) $profs[] = $aula['prof1_apelido'];
                                                            if (!empty($aula['prof2_apelido'])) $profs[] = $aula['prof2_apelido'];
                                                            if (!empty($aula['prof3_apelido'])) $profs[] = $aula['prof3_apelido'];
                                                            if (!empty($aula['prof4_apelido'])) $profs[] = $aula['prof4_apelido'];
                                                            $profsStr = !empty($profs) ? implode(', ', $profs) : '';
                                                            ?>
                                                            <div class="mg-activity__title"
                                                                 <?php if ($embedUrl): ?>onclick="mgAbrirVideo('<?php echo addslashes($embedUrl); ?>', '<?php echo $aulaSlug; ?>')" style="cursor:pointer;"<?php endif; ?>>
                                                                <?php echo htmlspecialchars($aula['aula']); ?>
                                                                <?php if ($profsStr): ?>
                                                                    <span class="text-muted fw-normal ms-2 small" style="font-size: .75rem;">
                                                                        (<i class="bi bi-person me-1"></i><?php echo htmlspecialchars($profsStr); ?>)
                                                                    </span>
                                                                <?php endif; ?>
                                                                <?php if ($canEdit): ?>
                                                                    <button class="btn btn-xs btn-link text-secondary p-0 ms-2 btn-edit-aula"
                                                                            style="font-size: .8rem; transition: color .15s; line-height: 1;"
                                                                            title="Editar Aula"
                                                                            data-id="<?php echo $aula['id']; ?>"
                                                                            data-aula="<?php echo htmlspecialchars($aula['aula']); ?>"
                                                                            data-link="<?php echo htmlspecialchars($aula['link_para_aula'] ?? ''); ?>"
                                                                            data-date="<?php echo htmlspecialchars($aula['data'] ?? ''); ?>"
                                                                            data-inicio="<?php echo htmlspecialchars($aula['inicio'] ?? ''); ?>"
                                                                            data-termino="<?php echo htmlspecialchars($aula['termino'] ?? ''); ?>"
                                                                            data-sem-video="<?php echo htmlspecialchars($aula['sem_video'] ?? ''); ?>"
                                                                            data-p1="<?php echo htmlspecialchars($aula['professor_1'] ?? ''); ?>"
                                                                            data-p2="<?php echo htmlspecialchars($aula['professor_2'] ?? ''); ?>"
                                                                            data-prof1="<?php echo htmlspecialchars($aula['prof1_apelido'] ?? ''); ?>"
                                                                            data-prof2="<?php echo htmlspecialchars($aula['prof2_apelido'] ?? ''); ?>"
                                                                            data-turma="<?php echo htmlspecialchars($turmaViewInfo['nome'] ?? ''); ?>"
                                                                            data-etapa="<?php echo htmlspecialchars($etNome ?? ''); ?>"
                                                                            data-etapa-id="<?php echo htmlspecialchars($aula['etapa'] ?? ''); ?>">
                                                                        <i class="bi bi-pencil-square"></i>
                                                                    </button>
                                                                    <?php if (!$temVideo): ?>
                                                                        <a class="btn btn-xs btn-link text-danger p-0 ms-1 btn-delete-aula"
                                                                           style="font-size: .8rem; transition: color .15s; line-height: 1;"
                                                                           title="Excluir Aula (sem vídeo)"
                                                                           href="<?php echo $baseHref; ?>?m=aulas&a=delete&id=<?php echo (int) $aula['id']; ?>&turma_view=<?php echo $turmaViewId; ?>&etapa_id=<?php echo (int) ($aula['etapa'] ?? 0); ?><?php echo csrf_query_param(); ?>"
                                                                           onclick="event.stopPropagation(); return confirm('Tem certeza que deseja excluir esta aula sem vídeo?');">
                                                                            <i class="bi bi-trash"></i>
                                                                        </a>
                                                                    <?php endif; ?>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="d-flex align-items-center flex-wrap gap-2 mt-1">
                                                                <?php if (!empty($aula['inicio'])): ?>
                                                                    <span class="mg-activity__meta">
                                                                        <i class="bi bi-clock me-1"></i><?php echo substr($aula['inicio'], 0, 5); ?><?php if (!empty($aula['termino'])): ?> — <?php echo substr($aula['termino'], 0, 5); ?><?php endif; ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <!-- Arquivos indentados -->
                                                    <?php if (!empty($arquivosArr)): ?>
                                                        <ul class="mg-resource-list">
                                                            <?php foreach ($arquivosArr as $arq): ?>
                                                                <li class="mg-resource">
                                                                    <div class="mg-resource__icon"><i class="bi bi-file-earmark-text"></i></div>
                                                                    <a href="<?php echo htmlspecialchars(cronograma_file_url($arq['file'])); ?>"
                                                                       target="_blank" rel="noopener" download
                                                                       class="mg-resource__link"
                                                                       onclick="event.stopPropagation()">
                                                                        <?php echo htmlspecialchars($arq['name']); ?>
                                                                    </a>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php endif; ?>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <!-- Botão de Voltar ao Topo -->
                                    <div class="text-end mt-2">
                                        <a href="#mg-turma-view-top" class="btn btn-link btn-sm text-decoration-none text-muted small p-0 d-inline-flex align-items-center gap-1" style="font-size: .7rem; font-weight: 600;">
                                            <i class="bi bi-arrow-up-circle"></i> Voltar ao início
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

    <?php else: ?>

        <!-- Título da Página de Aulas -->
        <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom">
            <div>
                <h3 class="fw-bold text-dark mb-1">
                    <i class="bi bi-play-circle-fill text-danger me-2"></i>Aulas & Turmas
                </h3>
                <p class="text-muted small mb-0">Selecione uma turma para visualizar as etapas, gravações das aulas e materiais pedagógicos.</p>
            </div>
        </div>

        <?php if (empty($turmasPainel)): ?>
            <div class="text-center py-4 text-muted small">
                <i class="bi bi-people display-6 d-block mb-2"></i>
                Nenhuma turma disponível.
            </div>
        <?php else: ?>

            <!-- CARDS DE TURMA (grandes, clicáveis) -->
            <div class="mg-turma-cards-grid">
                <?php
                $gradients = [
                    'linear-gradient(135deg,#1a1a2e,#16213e)',
                    'linear-gradient(135deg,#2c3e50,#34495e)',
                    'linear-gradient(135deg,#1b2838,#2d4059)',
                    'linear-gradient(135deg,#2d1b69,#11998e)',
                    'linear-gradient(135deg,#373b44,#4286f4)',
                    'linear-gradient(135deg,#360033,#0b8793)',
                ];
                foreach ($turmasPainel as $idx => $turma):
                    $tid = (int) $turma['id'];
                    $totalAulasTur = 0; $totalEtapasTur = 0;
                    if (isset($aulasPorTurma[$tid])) {
                        $totalEtapasTur = count($aulasPorTurma[$tid]);
                        foreach ($aulasPorTurma[$tid] as $etGrp) $totalAulasTur += count($etGrp['aulas']);
                    }
                    $mesAnoTur    = !empty($turma['inicio'])  ? _mg_mes_ano($turma['inicio'])  : '';
                    $mesAnoFimTur = !empty($turma['termino']) ? _mg_mes_ano($turma['termino']) : '';
                    $grad = $gradients[$idx % count($gradients)];
                ?>
                <!-- Card clicável da turma -->
                <div class="mg-turma-bigcard" data-turma-id="<?php echo $tid; ?>"
                     onclick="window.location.href='index.php?m=aulas&a=list&turma_view=<?php echo $tid; ?>'"
                     style="background:<?php echo $grad; ?>">
                    <div class="mg-turma-bigcard__overlay"></div>
                    <div class="mg-turma-bigcard__body">
                        <div class="mg-turma-bigcard__icon"><i class="bi bi-mortarboard-fill"></i></div>
                        <div class="mg-turma-bigcard__info">
                            <div class="mg-turma-bigcard__nome"><?php echo htmlspecialchars($turma['nome']); ?></div>
                            <?php if ($mesAnoTur): ?>
                                <div class="mg-turma-bigcard__period">
                                    <i class="bi bi-calendar3 me-1"></i><?php echo htmlspecialchars($mesAnoTur); ?><?php if ($mesAnoFimTur): ?> — <?php echo htmlspecialchars($mesAnoFimTur); ?><?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="mg-turma-bigcard__stats">
                            <span class="mg-turma-bigcard__badge"><?php echo $totalAulasTur; ?> aulas</span>
                            <span class="mg-turma-bigcard__badge"><?php echo $totalEtapasTur; ?> etapas</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Modal player de vídeo -->
<div class="modal fade" id="mgVideoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 overflow-hidden" style="background:#000;">
            <div class="modal-header border-0 py-2 px-3" style="background:#1a1a2e;">
                <span id="mgVideoModalTitle" class="text-white fw-semibold" style="font-size:.9rem;"></span>
                <button type="button" class="btn-close btn-close-white btn-sm ms-auto" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" style="aspect-ratio:16/9;">
                <iframe id="mgVideoIframe" src="" class="w-100 h-100" frameborder="0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen style="min-height:320px;"></iframe>
            </div>
        </div>
    </div>
</div>

<style>
html { scroll-behavior: smooth; }
/* ════ PAINEL ESTILO MOODLE ════════════════════════════════════════ */

/* Grid de cards grandes de turma */
.mg-turma-cards-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap:16px; }

.mg-turma-bigcard {
    position:relative; overflow:hidden;
    border-radius:10px; margin-bottom:0;
    cursor:pointer; user-select:none;
    transition:transform .18s, box-shadow .18s;
    min-height:90px;
    box-shadow:0 2px 10px rgba(0,0,0,.18);
}
.mg-turma-bigcard:hover { transform:translateY(-2px); box-shadow:0 6px 24px rgba(0,0,0,.28); }
.mg-turma-bigcard__overlay {
    position:absolute; inset:0;
    background:rgba(0,0,0,.15);
    transition:background .18s;
}
.mg-turma-bigcard:hover .mg-turma-bigcard__overlay { background:rgba(0,0,0,.08); }
.mg-turma-bigcard__body {
    position:relative; z-index:1;
    display:flex; align-items:center; gap:16px;
    padding:18px 22px;
}
.mg-turma-bigcard__icon {
    width:52px; height:52px; border-radius:12px;
    background:rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.22);
    display:flex; align-items:center; justify-content:center;
    font-size:1.5rem; color:#fff; flex-shrink:0;
    transition:background .18s;
}
.mg-turma-bigcard:hover .mg-turma-bigcard__icon { background:rgba(255,255,255,.22); }
.mg-turma-bigcard__info { flex:1; min-width:0; }
.mg-turma-bigcard__nome { font-size:1rem; font-weight:700; color:#fff; line-height:1.3; margin-bottom:3px; }
.mg-turma-bigcard__period { font-size:.75rem; color:rgba(255,255,255,.72); }
.mg-turma-bigcard__stats { display:flex; gap:8px; flex-shrink:0; flex-direction:column; align-items:flex-end; }
.mg-turma-bigcard__badge {
    background:rgba(255,255,255,.18); color:#fff;
    border:1px solid rgba(255,255,255,.28);
    border-radius:20px; font-size:.7rem; font-weight:700;
    padding:2px 10px; white-space:nowrap;
}
.mg-turma-bigcard__chevron { color:rgba(255,255,255,.8); font-size:1rem; transition:transform .25s; flex-shrink:0; }
.mg-turma-bigcard.active .mg-turma-bigcard__chevron { transform:rotate(180deg); }

/* Painel de curso */
.mg-course-panel { display:none; border:1px solid rgba(0,0,0,.12); border-top:none; border-radius:0 0 10px 10px; background:#fff; margin-bottom:12px; }
.mg-course-panel.active { display:block; }

/* Cabeçalho do curso */
.mg-course-header { display:flex; align-items:center; gap:14px; padding:16px 20px; background:linear-gradient(135deg,#1a1a2e,#2c3e50); color:#fff; }
.mg-course-header__icon { width:48px; height:48px; border-radius:10px; background:rgba(255,255,255,.12); display:flex; align-items:center; justify-content:center; font-size:1.4rem; flex-shrink:0; border:1px solid rgba(255,255,255,.18); }
.mg-course-header__info { flex:1; min-width:0; }
.mg-course-header__title { font-size:1rem; font-weight:700; line-height:1.3; margin-bottom:3px; }
.mg-course-header__meta { font-size:.75rem; color:rgba(255,255,255,.65); }
.mg-course-header__stats { display:flex; flex-direction:column; gap:4px; align-items:flex-end; font-size:.72rem; color:rgba(255,255,255,.7); flex-shrink:0; }

/* Seções */
.mg-section { border-bottom:1px solid #e9ecef; }
.mg-section:last-child { border-bottom:none; }
.mg-section__header { display:flex; align-items:center; justify-content:space-between; padding:12px 20px; background:#f4f6f9; cursor:pointer; user-select:none; transition:background .15s; gap:12px; }
.mg-section__header:hover { background:#eaeff5; }
.mg-section__left { display:flex; align-items:center; gap:12px; min-width:0; flex:1; }
.mg-section__num { width:28px; height:28px; border-radius:50%; background:#c0392b; color:#fff; font-size:.75rem; font-weight:800; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.mg-section__info { min-width:0; }
.mg-section__name { font-weight:700; font-size:.88rem; color:#1a1a2e; display:block; }
.mg-section__period { font-size:.72rem; color:#6c757d; margin-left:6px; }
.mg-section__right { display:flex; align-items:center; gap:10px; flex-shrink:0; }
.mg-section__count { font-size:.72rem; color:#6c757d; white-space:nowrap; }
.mg-section__chevron { color:#adb5bd; font-size:.8rem; transition:transform .22s; }
.mg-section.collapsed .mg-section__chevron { transform:rotate(180deg); }
.mg-section.collapsed .mg-section__content { display:none; }

/* Atividades */
.mg-activity-list { list-style:none; margin:0; padding:0; }
.mg-activity { display:flex; align-items:flex-start; padding:10px 20px; border-bottom:1px solid #f1f3f5; transition:background .12s; }
.mg-activity:last-child { border-bottom:none; }
.mg-activity:hover { background:#f8f9ff; }

.mg-activity__icon { width:36px; height:36px; border-radius:6px; display:flex; align-items:center; justify-content:center; font-size:1rem; flex-shrink:0; margin-right:12px; margin-top:2px; }
.mg-activity__icon--video { background:#fff3f2; color:#e74c3c; border:1px solid #fecaca; }
.mg-activity__icon--pending { background:#f4f6f9; color:#adb5bd; border:1px solid #e9ecef; }

.mg-activity__body { flex:1; min-width:0; }
.mg-activity__main { display:flex; align-items:center; gap:12px; }

.mg-activity__thumb { width:88px; height:56px; border-radius:5px; overflow:hidden; flex-shrink:0; position:relative; background:#1a1a2e; border:1px solid #e9ecef; }
.mg-activity__thumb img { width:100%; height:100%; object-fit:cover; display:block; transition:opacity .2s; }
.mg-activity__thumb--click { cursor:pointer; }
.mg-activity__thumb--click:hover img { opacity:.82; }
.mg-activity__play { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,.28); color:#fff; font-size:1.2rem; pointer-events:none; }

.mg-activity__info { flex:1; min-width:0; }
.mg-activity__title { font-size:.85rem; font-weight:600; color:#1a1a2e; line-height:1.4; margin-bottom:3px; transition:color .12s; }
.mg-activity__title:hover { color:#c0392b; }
.mg-activity__meta { font-size:.71rem; color:#868e96; }

/* Recursos */
.mg-resource-list { list-style:none; margin:8px 0 0 48px; padding:0; display:flex; flex-direction:column; gap:3px; }
.mg-resource { display:flex; align-items:center; gap:7px; }
.mg-resource__icon { width:22px; height:22px; border-radius:4px; background:#fff8e1; color:#e67e22; border:1px solid #fdebd0; display:flex; align-items:center; justify-content:center; font-size:.72rem; flex-shrink:0; }
.mg-resource__link { font-size:.75rem; color:#2980b9; text-decoration:none; transition:color .12s; }
.mg-resource__link:hover { color:#1a5276; text-decoration:underline; }

.mg-empty-section { text-align:center; padding:40px 20px; color:#adb5bd; }
.mg-empty-section i { font-size:2rem; display:block; margin-bottom:8px; }
.mg-empty-section p { font-size:.85rem; margin:0; }

@media (max-width:576px) {
    .mg-course-tab { padding:6px 10px 7px; max-width:140px; }
    .mg-course-tab__nome { max-width:80px; }
    .mg-activity { padding:9px 12px; }
    .mg-resource-list { margin-left:36px; }
    .mg-activity__thumb { width:68px; height:44px; }
}
</style>

<script>
function mgSelectTurma(turmaId, cardEl) {
    // Fechar tudo
    document.querySelectorAll('.mg-turma-bigcard').forEach(c => c.classList.remove('active'));
    document.querySelectorAll('.mg-course-panel').forEach(p => p.classList.remove('active'));
    const panel = document.getElementById('mg-panel-' + turmaId);
    // Toggle: se já estava aberto, fecha
    if (cardEl.classList.contains('was-active')) {
        cardEl.classList.remove('was-active');
        return;
    }
    document.querySelectorAll('.mg-turma-bigcard').forEach(c => c.classList.remove('was-active'));
    cardEl.classList.add('active', 'was-active');
    if (panel) panel.classList.add('active');
    // Scroll suave até o card
    setTimeout(() => cardEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 50);
}
function mgToggleSection(headerEl) {
    const section = headerEl.closest('.mg-section');
    if (section) section.classList.toggle('collapsed');
}
function mgAbrirVideo(embedUrl, titulo) {
    const modalEl = document.getElementById('mgVideoModal');
    if (!modalEl) return;
    document.getElementById('mgVideoIframe').src = embedUrl;
    document.getElementById('mgVideoModalTitle').textContent = titulo || 'Aula';
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}

document.addEventListener('DOMContentLoaded', function () {
    const mgModal = document.getElementById('mgVideoModal');
    if (mgModal) {
        mgModal.addEventListener('hidden.bs.modal', function () {
            document.getElementById('mgVideoIframe').src = '';
        });
    }
});
</script>

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

                            <div class="mb-3">
                                <label class="text-muted small d-block mb-1">Arquivos</label>
                                <div id="view-aula-arquivos" class="fw-semibold small">—</div>
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

                        <div class="mt-auto pt-4 text-center d-flex gap-2">
                            <button type="button" class="btn btn-secondary flex-grow-1"
                                data-bs-dismiss="modal">Fechar</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($canEdit): ?>
    <?php
    $professoresOptions = $pdo->query("SELECT id, apelido FROM 104_professores ORDER BY apelido ASC")->fetchAll(PDO::FETCH_ASSOC);
    $isAdminUser = in_array(strtolower($perfil_usuario), ['superadmin', 'admin']);
    ?>
    <div class="modal fade" id="modalEditAula" tabindex="-1" aria-labelledby="modalEditAulaLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg shadow-lg">
            <form action="<?php echo $baseHref; ?>?m=aulas&a=save" method="post" enctype="multipart/form-data">
                <div class="modal-content border-0">
                    <div class="modal-header bg-primary text-white py-2">
                        <h6 class="modal-title" id="modalEditAulaLabel"><i class="bi bi-pencil-square me-2"></i>Editar Aula
                        </h6>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4" style="font-size: 0.9rem;">
                        <input type="hidden" name="id" id="edit-id">
                        <input type="hidden" name="redirect_page" id="edit-redirect-page">
                        <input type="hidden" name="redirect_search" id="edit-redirect-search">
                        <input type="hidden" name="redirect_filtro_etapa" id="edit-redirect-filtro-etapa">
                        <input type="hidden" name="redirect_filtro_turma" id="edit-redirect-filtro-turma">
                        <input type="hidden" name="redirect_filtro_professor" id="edit-redirect-filtro-professor">
                        <input type="hidden" name="redirect_filtro_pendente" id="edit-redirect-filtro-pendente">
                        <input type="hidden" name="redirect_turma_view" id="edit-redirect-turma-view">
                        <input type="hidden" name="redirect_etapa_id" id="edit-redirect-etapa-id">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Título da Aula <span class="text-danger">*</span></label>
                            <input type="text" name="aula" id="edit-aula" class="form-control form-control-sm" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Link da Vídeo Aula (YouTube)</label>
                            <div class="input-group input-group-sm">
                                <input type="url" name="link_para_aula" id="edit-link" class="form-control"
                                    placeholder="https://www.youtube.com/watch?v=...">
                                <button class="btn btn-outline-secondary dropdown-toggle" type="button"
                                    data-bs-toggle="dropdown">Importar Moodle</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow"
                                    style="max-height: 400px; overflow-y: auto; width: 450px;">
                                    <li class="px-3 py-2 border-bottom sticky-top bg-white"><input type="text"
                                            class="form-control form-control-sm" id="moodle-search"
                                            placeholder="Filtrar..."></li>
                                    <?php foreach ($moodleVideos as $mv): ?>
                                        <?php preg_match('/(https:\/\/www\.youtube\.com\/watch\?v=[^"\'\s&<>]+)/', $mv['youtube_url'], $matches);
                                        $cleanUrl = $matches[1] ?? '';
                                        if (!$cleanUrl)
                                            continue; ?>
                                        <li class="moodle-item">
                                            <a class="dropdown-item small py-2" href="#"
                                                onclick="document.getElementById('edit-link').value = '<?php echo addslashes($cleanUrl); ?>'; return false;">
                                                <div class="fw-bold text-dark item-title">
                                                    <?php echo htmlspecialchars($mv['aula_titulo']); ?>
                                                </div>
                                                <div class="text-muted item-meta" style="font-size: 0.75rem;">
                                                    <?php echo htmlspecialchars($mv['curso_nome']); ?>
                                                </div>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                        <?php if ($isAdminUser): ?>
                            <div id="edit-sugestao-filename"
                                class="d-none mt-1 px-2 py-1 rounded bg-warning-subtle border border-warning-subtle small shadow-sm-hover"
                                style="font-size:0.78rem; cursor: pointer; transition: all 0.2s ease; user-select: none;"
                                title="Clique para copiar o nome sugerido"
                                onclick="copyToClipboard(document.getElementById('edit-sugestao-text').textContent, 'Nome sugerido copiado!')">
                                <i class="bi bi-lightbulb-fill text-warning me-1"></i>
                                <span class="text-muted">Nome sugerido:</span>
                                <code id="edit-sugestao-text" class="text-dark fw-semibold ms-1"></code>
                                <i class="bi bi-clipboard ms-2 text-muted small"></i>
                            </div>
                        <?php endif; ?>
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label">Data</label><input type="date" name="data"
                                    id="edit-date" class="form-control form-control-sm" required></div>
                            <div class="col-md-4"><label class="form-label">Início</label><input type="time" name="inicio"
                                    id="edit-inicio" class="form-control form-control-sm" required></div>
                            <div class="col-md-4"><label class="form-label">Término</label><input type="time" name="termino"
                                    id="edit-termino" class="form-control form-control-sm" required></div>
                        </div>
                        <div class="row g-2 mt-2">
                            <div class="col-md-3"><label class="form-label">Prof. 1</label>
                                <select name="professor_1" id="edit-p1" class="form-select form-select-sm">
                                    <option value="">-</option>
                                    <?php foreach ($professoresOptions as $opt): ?>
                                        <option value="<?php echo $opt['id']; ?>">
                                            <?php echo htmlspecialchars($opt['apelido']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3"><label class="form-label">Prof. 2</label>
                                <select name="professor_2" id="edit-p2" class="form-select form-select-sm">
                                    <option value="">-</option>
                                    <?php foreach ($professoresOptions as $opt): ?>
                                        <option value="<?php echo $opt['id']; ?>">
                                            <?php echo htmlspecialchars($opt['apelido']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="form-check form-switch"><input class="form-check-input" type="checkbox"
                                    name="sem_video" value="1" id="edit-sem-video"><label class="form-check-label">Sem
                                    Vídeo</label></div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label fw-bold">Arquivos Anexos</label>
                            <div id="edit-drop-zone" class="border-dashed rounded-3 p-4 text-center bg-white mb-2"
                                style="border: 2px dashed #cbd5e0; transition: border-color .2s, background-color .2s; cursor: pointer;">
                                <i class="bi bi-cloud-arrow-up display-5 text-primary mb-2"></i>
                                <p class="mb-0 text-muted">Arraste arquivos aqui ou clique para selecionar</p>
                                <span class="text-xs text-muted">PDF, DOCX, ZIP, JPG, PNG e outros</span>
                            </div>
                            <input type="file" name="novos_arquivos[]" id="edit-file-input" class="d-none" multiple>
                            <div id="new-files-preview" class="small mb-3 d-flex flex-wrap gap-2"></div>

                            <input type="hidden" name="arquivos_json" id="edit-arquivos-json">
                            <div id="edit-arquivos-lista"
                                class="list-group list-group-flush border rounded-3 overflow-hidden shadow-xs"></div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="submit" class="btn btn-sm btn-primary">Salvar</button></div>
                </div>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const editModalEl = document.getElementById('modalEditAula');
        const editModal = editModalEl ? new bootstrap.Modal(editModalEl) : null;
        const viewModalEl = document.getElementById('modalViewAula');
        const viewModal = viewModalEl ? new bootstrap.Modal(viewModalEl) : null;

        // Armazena valores dos professores para reaplicar após animação do modal
        let _pendingP1 = '', _pendingP2 = '';
        if (editModalEl) {
            editModalEl.addEventListener('shown.bs.modal', function () {
                const s1 = document.getElementById('edit-p1');
                const s2 = document.getElementById('edit-p2');
                if (s1) s1.value = _pendingP1;
                if (s2) s2.value = _pendingP2;
            });
        }

        document.querySelectorAll('.btn-view-aula').forEach(card => {
            card.addEventListener('click', function () {
                if (!viewModal) return;
                const d = this.dataset;
                const iframe = document.getElementById('view-video-iframe');
                const placeholder = document.getElementById('view-video-placeholder');

                if (document.getElementById('view-aula-title')) document.getElementById('view-aula-title').textContent = d.aula;
                if (document.getElementById('view-aula-subtitle')) document.getElementById('view-aula-subtitle').textContent = (d.turma || '') + (d.etapa ? ' - ' + d.etapa : '');
                if (document.getElementById('view-aula-profs')) document.getElementById('view-aula-profs').textContent = d.profs || 'Não informado';
                if (document.getElementById('view-aula-data')) document.getElementById('view-aula-data').textContent = d.data;
                if (document.getElementById('view-aula-horario')) document.getElementById('view-aula-horario').textContent = d.horario;

                viewModalEl.dataset.currentAulaId = d.id;
                const viewArcs = document.getElementById('view-aula-arquivos');
                const srcArcs = document.getElementById('aula-arquivos-' + d.id);
                if (viewArcs) viewArcs.innerHTML = srcArcs ? srcArcs.innerHTML : '<span class="text-muted">—</span>';

                if (iframe && placeholder) {
                    if (d.semVideo === '1') {
                        iframe.src = ''; iframe.classList.add('d-none'); placeholder.classList.remove('d-none');
                        const pImg = document.getElementById('view-placeholder-img'); if (pImg) pImg.src = d.thumb;
                        const pTxt = document.getElementById('view-placeholder-text'); if (pTxt) pTxt.textContent = 'Por privacidade, esta aula não possui vídeo.';
                    } else if (d.video && d.video !== 'null') {
                        iframe.src = d.video; iframe.classList.remove('d-none'); placeholder.classList.add('d-none');
                    } else {
                        iframe.src = ''; iframe.classList.add('d-none'); placeholder.classList.remove('d-none');
                        const pImg = document.getElementById('view-placeholder-img'); if (pImg) pImg.src = d.thumb;
                        const pTxt = document.getElementById('view-placeholder-text'); if (pTxt) pTxt.textContent = 'Aula sem gravação disponível.';
                    }
                }
                viewModal.show();
            });
        });

        if (viewModalEl) {
            viewModalEl.addEventListener('hidden.bs.modal', function () {
                const iframe = document.getElementById('view-video-iframe');
                if (iframe) iframe.src = '';
            });
        }


        // Drag and Drop Logic for File List
        const listEl = document.getElementById('edit-arquivos-lista');
        let sortable = null;
        if (listEl) {
            sortable = Sortable.create(listEl, {
                animation: 150,
                handle: '.drag-handle',
                ghostClass: 'bg-light',
                onEnd: function () {
                    updateArquivosState();
                }
            });
        }

        function updateArquivosState() {
            const items = [];
            document.querySelectorAll('.file-item').forEach(item => {
                items.push({
                    name: item.dataset.name,
                    file: item.dataset.file,
                    size: parseInt(item.dataset.size),
                    type: item.dataset.type
                });
            });
            document.getElementById('edit-arquivos-json').value = JSON.stringify(items);
        }

        window.removeFile = function (btn) {
            if (confirm('Deseja remover este arquivo?')) {
                btn.closest('.file-item').remove();
                updateArquivosState();
            }
        };

        const renderArquivos = (jsonStr) => {
            let files = [];
            try { files = JSON.parse(jsonStr || '[]'); } catch (e) { }
            if (!Array.isArray(files)) files = [];

            const listEl = document.getElementById('edit-arquivos-lista');
            listEl.innerHTML = '';

            if (files.length === 0) {
                listEl.innerHTML = '<div class="p-3 text-center text-muted small">Nenhum arquivo anexado</div>';
                document.getElementById('edit-arquivos-json').value = '[]';
                return;
            }

            files.forEach(f => {
                const item = document.createElement('div');
                item.className = 'list-group-item file-item d-flex align-items-center p-2';
                item.dataset.name = f.name;
                item.dataset.file = f.file;
                item.dataset.size = f.size;
                item.dataset.type = f.type || '';

                item.innerHTML = `
                    <div class="drag-handle me-2" style="cursor: move;"><i class="bi bi-grip-vertical text-muted"></i></div>
                    <div class="flex-grow-1 text-truncate" title="${f.name}">
                        <i class="bi bi-file-earmark me-1 text-primary"></i>
                        <span class="small fw-semibold">${f.name}</span>
                    </div>
                    <button type="button" class="btn btn-link text-danger btn-sm p-0 ms-2" onclick="removeFile(this)">
                        <i class="bi bi-x-circle"></i>
                    </button>
                `;
                listEl.appendChild(item);
            });
            updateArquivosState();
        };

        // UI Interactions for Drop Zone
        const dropZone = document.getElementById('edit-drop-zone');
        const fileInput = document.getElementById('edit-file-input');
        const preview = document.getElementById('new-files-preview');

        if (dropZone && fileInput) {
            dropZone.addEventListener('click', () => fileInput.click());
            dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.style.borderColor = '#4299e1'; dropZone.style.backgroundColor = '#f7fafc'; });
            dropZone.addEventListener('dragleave', () => { dropZone.style.borderColor = '#cbd5e0'; dropZone.style.backgroundColor = '#ffffff'; });
            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.style.borderColor = '#cbd5e0';
                dropZone.style.backgroundColor = '#ffffff';
                fileInput.files = e.dataTransfer.files;
                updateNewFilesPreview();
            });
            fileInput.addEventListener('change', updateNewFilesPreview);
        }

        function updateNewFilesPreview() {
            preview.innerHTML = '';
            Array.from(fileInput.files).forEach(f => {
                const badge = document.createElement('span');
                badge.className = 'badge bg-primary d-flex align-items-center gap-1 p-2';
                badge.innerHTML = `<i class="bi bi-file-earmark-plus"></i> ${f.name} <i class="bi bi-check-circle-fill ms-1"></i>`;
                preview.appendChild(badge);
            });
        }

        document.querySelectorAll('.btn-edit-aula').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                if (!editModal) return;
                const d = this.dataset;
                document.getElementById('edit-id').value = d.id;
                document.getElementById('edit-aula').value = d.aula;
                document.getElementById('edit-link').value = d.link;
                document.getElementById('edit-date').value = d.date;
                document.getElementById('edit-inicio').value = d.inicio;
                document.getElementById('edit-termino').value = d.termino;
                document.getElementById('edit-sem-video').checked = d.semVideo === '1';

                // Professores: setar direto e também no evento shown por segurança
                const _p1 = String(d.p1 || '');
                const _p2 = String(d.p2 || '');
                _pendingP1 = _p1;
                _pendingP2 = _p2;
                const selP1 = document.getElementById('edit-p1');
                const selP2 = document.getElementById('edit-p2');
                if (selP1) selP1.value = _p1;
                if (selP2) selP2.value = _p2;

                // Set redirects for state preservation
                const urlParams = new URLSearchParams(window.location.search);
                document.getElementById('edit-redirect-page').value = urlParams.get('page') || '1';
                document.getElementById('edit-redirect-search').value = urlParams.get('search') || '';
                document.getElementById('edit-redirect-filtro-etapa').value = urlParams.get('filtro_etapa') || '0';
                document.getElementById('edit-redirect-filtro-turma').value = urlParams.get('filtro_turma') || '0';
                document.getElementById('edit-redirect-filtro-professor').value = urlParams.get('filtro_professor') || '0';
                document.getElementById('edit-redirect-filtro-pendente').value = urlParams.get('filtro_pendente') || '0';
                document.getElementById('edit-redirect-turma-view').value = urlParams.get('turma_view') || '0';
                document.getElementById('edit-redirect-etapa-id').value = d.etapaId || '';

                renderArquivos(d.arquivosRaw);
                if (preview) preview.innerHTML = '';
                if (fileInput) fileInput.value = '';

                // Sugestão de nome de arquivo (apenas ADMIN/SUPERADMIN)
                const hintEl = document.getElementById('edit-sugestao-filename');
                const hintText = document.getElementById('edit-sugestao-text');
                if (hintEl && hintText) {
                    const toFilename = s => (s || '')
                        .normalize('NFD').replace(/[\u0300-\u036f]/g, '') // remove acentos
                        .replace(/[^a-zA-Z0-9\s_-]/g, '')
                        .trim().replace(/\s+/g, '_');

                    const recalculateHint = () => {
                        const cardEl = btn.closest('.card');
                        const cardData = cardEl ? cardEl.dataset : {};

                        const turma = toFilename(d.turma || cardData.turma || 'SemTurma');
                        const etapa = toFilename(d.etapa || cardData.etapa || 'SemEtapa');
                        const titulo = toFilename(document.getElementById('edit-aula').value || 'SemTitulo');

                        const getProfName = (id, dataName) => {
                             const sel = document.getElementById(id);
                             if (sel) {
                                 if (sel.value && sel.value !== '0') {
                                     const opt = sel.options[sel.selectedIndex];
                                     if (opt && opt.text && opt.text.trim() !== '-' && opt.text.trim() !== 'Selecione...') {
                                         return opt.text.trim();
                                     }
                                 }
                                 return '';
                             }
                             return d[dataName] || '';
                         };
                         const profParts = [getProfName('edit-p1', 'prof1'), getProfName('edit-p2', 'prof2')]
                             .filter(Boolean).map(toFilename);
                         const profs = profParts.length ? profParts.join('-') : 'SemProf';

                        hintText.textContent = `${turma}-${etapa}-${titulo}-${profs}`;
                    };

                    const updateHintVisibility = () => {
                        const linkVal = document.getElementById('edit-link').value.trim();
                        const semVideo = document.getElementById('edit-sem-video').checked;
                        hintEl.classList.toggle('d-none', !!(linkVal || semVideo));
                    };

                    // Initial state
                    recalculateHint();
                    updateHintVisibility();

                    // Event Listeners for Dynamic Update
                    const fields = ['edit-aula', 'edit-p1', 'edit-p2'];
                    fields.forEach(id => {
                        const el = document.getElementById(id);
                        if (el) {
                            el.removeEventListener('input', el._recalcListener);
                            el.removeEventListener('change', el._recalcListener);
                            el._recalcListener = recalculateHint;
                            el.addEventListener(el.tagName === 'SELECT' ? 'change' : 'input', el._recalcListener);
                        }
                    });

                    const linkInput = document.getElementById('edit-link');
                    const semVideoChk = document.getElementById('edit-sem-video');
                    linkInput.removeEventListener('input', linkInput._hintVisibilityListener);
                    semVideoChk.removeEventListener('change', semVideoChk._hintVisibilityListener);
                    linkInput._hintVisibilityListener = updateHintVisibility;
                    semVideoChk._hintVisibilityListener = updateHintVisibility;
                    linkInput.addEventListener('input', linkInput._hintVisibilityListener);
                    semVideoChk.addEventListener('change', semVideoChk._hintVisibilityListener);
                }

                editModal.show();
            });
        });

        const mSearch = document.getElementById('moodle-search');
        if (mSearch) {
            mSearch.addEventListener('input', function () {
                const t = this.value.toLowerCase();
                document.querySelectorAll('.moodle-item').forEach(i => {
                    i.classList.toggle('d-none', !i.textContent.toLowerCase().includes(t));
                });
            });
            mSearch.addEventListener('click', e => e.stopPropagation());
        }

        window.copyToClipboard = function (text, message = 'Copiado!') {
            if (!text) return;
            const showToast = () => {
                const toastEl = document.getElementById('copyToast');
                const toastBody = toastEl.querySelector('.toast-body');
                if (toastBody) toastBody.innerHTML = `<i class="bi bi-check-circle me-1"></i> ${message}`;
                const toast = new bootstrap.Toast(toastEl, { delay: 2000 });
                toast.show();
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(showToast).catch(function () {
                    copyFallback(text, showToast);
                });
            } else {
                copyFallback(text, showToast);
            }
        };

        function copyFallback(text, callback) {
            const el = document.createElement('textarea');
            el.value = text;
            el.style.position = 'fixed';
            el.style.opacity = '0';
            document.body.appendChild(el);
            el.select();
            try {
                document.execCommand('copy');
                if (callback) callback();
            } catch (err) {
                console.error('Erro ao copiar', err);
            }
            document.body.removeChild(el);
        }

    });
</script>

<!-- Antiga grade de aulas removida: as aulas ficam no painel de turma acima -->
<style>
    #edit-sugestao-filename:hover { background-color:#ffe69c!important; transform:translateY(-1px); box-shadow:0 2px 4px rgba(0,0,0,.05); }
    #edit-sugestao-filename:active { transform:translateY(0); filter:brightness(.9); }
</style>
<!-- Toast feedback para cópia -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999">
    <div id="copyToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body"><i class="bi bi-check-circle me-1"></i> Copiado!</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>
