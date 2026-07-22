<?php
// app/modules/apoio/importar_aulas_youtube.php

$userPerfil = $_SESSION['user']['perfil'] ?? 'OPERACIONAL';
if (!in_array(strtolower($userPerfil), ['admin', 'superadmin'])) {
    die('Acesso negado.');
}

/**
 * Extrai a URL limpa do YouTube a partir de texto com tags HTML.
 */
function cleanYoutubeUrl($text)
{
    if (!$text)
        return '';
    if (preg_match('%(https?://(?:www\.)?(?:youtube\.com|youtu\.be)/[^\s<"\'\?&]+(?:\?[^\s<"\'&]*(?:&[^\s<"\'&]*)*)?)%i', $text, $match)) {
        return html_entity_decode(strip_tags($match[1]));
    }
    return '';
}

/**
 * Normaliza uma string para comparação: minúsculas, sem acentos, sem pontuação excessiva.
 */
function normalizeTitle($str)
{
    $str = mb_strtolower(trim($str), 'UTF-8');
    // Strip leading timestamps like "09:00 às 10:30h", "13:15:00 14:45:00"
    $str = preg_replace('/^\d{1,2}:\d{2}(?::\d{2})?\s*(?:às|a|-|–)?\s*\d{1,2}:\d{2}(?::\d{2})?h?\s*/iu', '', $str);
    // Remove professor names like "Prof. João", "Profª Tere"
    $str = preg_replace('/\bprof[aª]?\.?\s+\w+.*$/iu', '', $str);
    // Transliterate accents
    $from = ['á', 'à', 'ã', 'â', 'é', 'ê', 'í', 'ó', 'ô', 'õ', 'ú', 'ü', 'ç'];
    $to = ['a', 'a', 'a', 'a', 'e', 'e', 'i', 'o', 'o', 'o', 'u', 'u', 'c'];
    $str = str_replace($from, $to, $str);
    // Remove punctuation, normalize whitespace
    $str = preg_replace('/[^\w\s]/u', ' ', $str);
    $str = preg_replace('/\s+/', ' ', trim($str));
    return $str;
}

/**
 * Calcula a similaridade entre dois títulos (0-100).
 * Requer score base >= 30% antes de aplicar o boost por palavras (evita falsos positivos).
 */
function titleSimilarity($a, $b)
{
    $na = normalizeTitle($a);
    $nb = normalizeTitle($b);
    similar_text($na, $nb, $pct);

    // Only boost if there is reasonable base similarity
    if ($pct < 30)
        return $pct;

    // Word overlap boost
    $wordsA = array_values(array_filter(explode(' ', $na), function ($w) {
        return strlen($w) > 3;
    }));
    $wordsB = array_values(array_filter(explode(' ', $nb), function ($w) {
        return strlen($w) > 3;
    }));
    if (count($wordsA) === 0)
        return min(100, $pct);
    $overlap = count(array_intersect($wordsA, $wordsB));
    $boost = ($overlap / count($wordsA)) * 30;
    return min(100, $pct + $boost);
}

/**
 * Extrai o ano de uma string de turma/curso.
 */
function extractYear($str)
{
    if (preg_match('/\b(20\d{2})\b/', $str, $m))
        return $m[1];
    return null;
}

// Extend time limit for potentially large dataset comparisons
set_time_limit(120);

// ---------------
// LOAD DATA
// ---------------
$cronogramaRows = $pdo->query("
    SELECT c.id, c.aula, c.turma, t.nome as turma_nome
    FROM 104_cronograma c
    LEFT JOIN 104_turmas t ON t.id = c.turma
    WHERE (c.link_para_aula = '' OR c.link_para_aula IS NULL)
      AND (c.sem_video != '1' OR c.sem_video IS NULL)
")->fetchAll();

$moodleRaw = $pdo->query("
    SELECT aula_titulo, curso_nome, etapa_nome, youtube_url
    FROM xmoodle_aulas
    WHERE youtube_url IS NOT NULL AND youtube_url != ''
")->fetchAll();

// Pre-normalize moodle titles ONCE and group by year to reduce comparison loop size
$moodleByYear = [];  // ['2021' => [...], 'none' => [...]]
$moodleByYear['none'] = [];

foreach ($moodleRaw as $m) {
    // Pre-normalize and filter degenerate entries here (not inside inner loop)
    $norm = normalizeTitle($m['aula_titulo']);
    $words = array_filter(explode(' ', $norm), function ($w) {
        return strlen($w) > 2;
    });
    if (count($words) < 2)
        continue; // skip "Reinaldo Mafli" type entries

    $m['_norm'] = $norm;
    $m['_words'] = array_values($words);
    $year = extractYear($m['curso_nome']);
    $m['_year'] = $year;
    $key = $year ? $year : 'none';
    $moodleByYear[$key][] = $m;
}

$SIMILARITY_THRESHOLD = isset($_GET['threshold']) ? max(20, min(95, (int) $_GET['threshold'])) : 55;

// ---------------
// MATCH LOGIC
// ---------------
/**
 * Finds the best matching moodle row for a given cronograma row.
 * Uses pre-normalized data and year-bucketed moodle rows.
 */
function findBestMatch($cronRow, &$moodleByYear, $threshold)
{
    $bestScore = 0;
    $bestMatch = null;
    $cronYear = extractYear($cronRow['turma_nome'] ?? '');
    $normCron = normalizeTitle($cronRow['aula']);

    // Only compare against matching year's bucket (+ 'none' bucket as fallback)
    $buckets = [];
    if ($cronYear && isset($moodleByYear[$cronYear])) {
        $buckets = $moodleByYear[$cronYear];
    } else {
        // If no year match, compare against all (but no year bonus)
        foreach ($moodleByYear as $rows) {
            $buckets = array_merge($buckets, $rows);
        }
    }

    foreach ($buckets as $m) {
        // Use pre-normalized moodle title for efficiency
        $nb = $m['_norm'];
        similar_text($normCron, $nb, $pct);

        if ($pct < 30)
            continue; // Early exit for low base similarity

        // Word overlap boost using pre-computed word lists
        $wordsA = array_values(array_filter(explode(' ', $normCron), function ($w) {
            return strlen($w) > 3;
        }));
        $wordsB = $m['_words'];
        if (count($wordsA) > 0) {
            $overlap = count(array_intersect($wordsA, $wordsB));
            $boost = ($overlap / count($wordsA)) * 30;
            $pct = min(100, $pct + $boost);
        }

        // Year bonus (only if base is already decent)
        if ($cronYear && isset($m['_year']) && $m['_year'] === $cronYear && $pct >= 40) {
            $pct = min(100, $pct + 8);
        }

        if ($pct > $bestScore) {
            $bestScore = $pct;
            $bestMatch = $m;
        }
    }

    if ($bestScore >= $threshold) {
        return ['match' => $bestMatch, 'score' => round($bestScore, 1)];
    }
    return null;
}

// ---------------
// HANDLE FORM SUBMIT
// ---------------
$report = [];
$totalDone = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_import'])) {
    $selected = $_POST['selected'] ?? [];
    $urlsMap = json_decode($_POST['urls_map'] ?? '{}', true);
    $selectedIds = array_map('intval', $selected);

    foreach ($selectedIds as $cronId) {
        if (!isset($urlsMap[$cronId]))
            continue;
        $url = $urlsMap[$cronId];
        if (!$url)
            continue;

        $stmt = $pdo->prepare("UPDATE 104_cronograma SET link_para_aula = ? WHERE id = ?");
        $stmt->execute([$url, $cronId]);

        // Find aula name for report
        $aulaName = '';
        foreach ($cronogramaRows as $r) {
            if ((int) $r['id'] === $cronId) {
                $aulaName = $r['aula'];
                break;
            }
        }
        $report[] = ['aula' => $aulaName, 'url' => $url, 'status' => 'success'];
        $totalDone++;
    }
}

// ---------------
// PREVIEW MATCHES
// ---------------
$potentialMatches = [];
if (!isset($_POST['confirm_import'])) {
    foreach ($cronogramaRows as $c) {
        $result = findBestMatch($c, $moodleByYear, $SIMILARITY_THRESHOLD);
        if ($result) {
            $cleanUrl = cleanYoutubeUrl($result['match']['youtube_url']);
            if ($cleanUrl) {
                $potentialMatches[] = [
                    'id' => $c['id'],
                    'aula' => $c['aula'],
                    'turma' => $c['turma_nome'] ?? '',
                    'moodleTitulo' => $result['match']['aula_titulo'],
                    'moodleCurso' => $result['match']['curso_nome'],
                    'score' => $result['score'],
                    'url' => $cleanUrl,
                ];
            }
        }
    }
    // Sort by score desc
    usort($potentialMatches, function ($a, $b) {
        return $b['score'] <=> $a['score'];
    });
}

$cronogramaCount = count($cronogramaRows);
$moodleCount = array_sum(array_map('count', $moodleByYear));
?>

<div class="container-fluid mt-4">
    <div class="row mb-3">
        <div class="col">
            <h2 class="h4"><i class="bi bi-magic me-2"></i>Importar Aulas do YouTube</h2>
            <p class="text-muted small">Rotina inteligente que busca correspondências entre títulos do Cronograma e do
                Moodle, filtrando por turma/ano.</p>
        </div>
    </div>

    <?php if ($totalDone > 0): ?>
        <div class="alert alert-success shadow-sm border-0 d-flex align-items-center mb-4">
            <i class="bi bi-check-circle-fill fs-4 me-3"></i>
            <div><strong>Sucesso!</strong> <?php echo $totalDone; ?> aulas foram atualizadas.</div>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase mb-1">Cronograma sem vídeo</div>
                    <div class="fs-2 fw-bold"><?php echo $cronogramaCount; ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase mb-1">Moodle com YouTube</div>
                    <div class="fs-2 fw-bold"><?php echo $moodleCount; ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 bg-primary text-white">
                <div class="card-body">
                    <div class="small text-white-50 text-uppercase mb-1">Correspondências ≥
                        <?php echo $SIMILARITY_THRESHOLD; ?>%
                    </div>
                    <div class="fs-2 fw-bold"><?php echo count($potentialMatches); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <?php if (!isset($_POST['confirm_import'])): ?>
                <?php if (count($potentialMatches) > 0): ?>
                    <form method="POST" id="import-form">
                        <input type="hidden" name="confirm_import" value="1">
                        <input type="hidden" name="urls_map" id="urls_map_input">

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <button type="button" id="select-all-btn"
                                    class="btn btn-sm btn-outline-secondary me-2">Selecionar todos</button>
                                <button type="button" id="deselect-all-btn" class="btn btn-sm btn-outline-secondary">Desmarcar
                                    todos</button>
                            </div>
                            <button type="submit" class="btn btn-primary px-4" onclick="prepareSubmit()">
                                <i class="bi bi-play-fill me-2"></i>Importar selecionados
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle small">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:30px"><input type="checkbox" id="master-check"></th>
                                        <th>Aula (Cronograma)</th>
                                        <th>Turma</th>
                                        <th>Título no Moodle</th>
                                        <th>Curso</th>
                                        <th>Similaridade</th>
                                        <th>URL</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($potentialMatches as $m): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="selected[]" value="<?php echo $m['id']; ?>"
                                                    data-url="<?php echo htmlspecialchars($m['url']); ?>" class="row-check" checked>
                                            </td>
                                            <td class="fw-semibold"><?php echo htmlspecialchars($m['aula']); ?></td>
                                            <td><span
                                                    class="badge bg-light text-dark border"><?php echo htmlspecialchars($m['turma']); ?></span>
                                            </td>
                                            <td class="text-muted"><?php echo htmlspecialchars($m['moodleTitulo']); ?></td>
                                            <td class="text-muted" style="max-width:180px; white-space:normal;">
                                                <?php echo htmlspecialchars($m['moodleCurso']); ?>
                                            </td>
                                            <td>
                                                <?php
                                                $sc = $m['score'];
                                                $cls = $sc >= 80 ? 'success' : ($sc >= 65 ? 'warning' : 'secondary');
                                                ?>
                                                <span class="badge bg-<?php echo $cls; ?>"><?php echo $sc; ?>%</span>
                                            </td>
                                            <td
                                                style="max-width:150px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                                <a href="<?php echo htmlspecialchars($m['url']); ?>" target="_blank"
                                                    class="text-primary small"><?php echo htmlspecialchars($m['url']); ?></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3 text-end">
                            <button type="submit" class="btn btn-primary px-5" onclick="prepareSubmit()">
                                <i class="bi bi-play-fill me-2"></i>Importar selecionados
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="text-center py-5">
                        <div class="display-1 text-muted mb-3"><i class="bi bi-search"></i></div>
                        <h5>Nenhuma correspondência encontrada</h5>
                        <p class="text-muted">Tente reduzir o limiar de similaridade ou verifique se os dados do Moodle estão
                            importados.</p>
                        <a href="index.php?m=apoio&a=importar_aulas_youtube&threshold=40" class="btn btn-outline-primary">Tentar
                            com limiar de 40%</a>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Aula</th>
                                <th>URL Importada</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report as $r): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($r['aula']); ?></td>
                                    <td><small><?php echo htmlspecialchars($r['url']); ?></small></td>
                                    <td><span class="badge bg-success">Atualizado</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">
                    <a href="index.php?m=apoio&a=importar_aulas_youtube" class="btn btn-outline-secondary me-2">
                        <i class="bi bi-arrow-repeat me-1"></i>Rodar novamente
                    </a>
                    <a href="index.php?m=aulas&a=list" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Voltar para Aulas
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Threshold slider -->
    <?php if (!isset($_POST['confirm_import'])): ?>
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-body py-2">
                <form method="GET" class="d-flex align-items-center gap-3">
                    <input type="hidden" name="m" value="apoio">
                    <input type="hidden" name="a" value="importar_aulas_youtube">
                    <label class="form-label mb-0 small fw-semibold">Limiar de similaridade:</label>
                    <input type="range" name="threshold" min="20" max="95" step="5"
                        value="<?php echo $SIMILARITY_THRESHOLD; ?>" class="form-range" style="width:200px;"
                        oninput="this.nextElementSibling.textContent = this.value + '%'">
                    <span class="badge bg-secondary"><?php echo $SIMILARITY_THRESHOLD; ?>%</span>
                    <button type="submit" class="btn btn-sm btn-outline-primary">Aplicar</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    // Master checkbox
    document.getElementById('master-check')?.addEventListener('change', function () {
        document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
    });
    document.getElementById('select-all-btn')?.addEventListener('click', () => {
        document.querySelectorAll('.row-check').forEach(cb => cb.checked = true);
        if (document.getElementById('master-check')) document.getElementById('master-check').checked = true;
    });
    document.getElementById('deselect-all-btn')?.addEventListener('click', () => {
        document.querySelectorAll('.row-check').forEach(cb => cb.checked = false);
        if (document.getElementById('master-check')) document.getElementById('master-check').checked = false;
    });

    function prepareSubmit() {
        const map = {};
        document.querySelectorAll('.row-check:checked').forEach(cb => {
            map[cb.value] = cb.dataset.url;
        });
        document.getElementById('urls_map_input').value = JSON.stringify(map);
    }
</script>