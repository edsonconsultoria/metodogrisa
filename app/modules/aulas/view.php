<?php
require_once __DIR__ . '/../../helpers/cronograma_arquivos.php';

$id = (int) ($_GET['id'] ?? 0);
$currentUser = current_user();
$currentUserId = (int) ($currentUser['id'] ?? 0);

$accessWhere = '';
$params = [':id' => $id];
if (current_user_is_aluno()) {
    $accessWhere = "AND c.turma IN (
        SELECT turma FROM 104_alunos
        WHERE aluno = :aluno_id AND (status = 'Ativo' OR status = '' OR status IS NULL)
    )";
    $params[':aluno_id'] = $currentUserId;
}

$stmt = $pdo->prepare("
    SELECT c.*, t.nome AS turma_nome, e.nome AS etapa_nome,
           p1.apelido AS professor_1_nome, p2.apelido AS professor_2_nome,
           p3.apelido AS professor_3_nome, p4.apelido AS professor_4_nome
    FROM 104_cronograma c
    LEFT JOIN 104_turmas t ON t.id = c.turma
    LEFT JOIN 104_etapas e ON e.id = c.etapa
    LEFT JOIN 104_professores p1 ON p1.id = c.professor_1
    LEFT JOIN 104_professores p2 ON p2.id = c.professor_2
    LEFT JOIN 104_professores p3 ON p3.id = c.professor_3
    LEFT JOIN 104_professores p4 ON p4.id = c.professor_4
    WHERE c.id = :id {$accessWhere}
    LIMIT 1
");
$stmt->execute($params);
$aula = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$aula) {
    echo '<div class="alert alert-warning">Aula não encontrada ou acesso não permitido.</div>';
    return;
}

$professores = array_filter([
    $aula['professor_1_nome'] ?? '', $aula['professor_2_nome'] ?? '',
    $aula['professor_3_nome'] ?? '', $aula['professor_4_nome'] ?? ''
]);
$arquivos = cronograma_parse_arquivos($aula['arquivos'] ?? '');
$videoUrl = trim((string) ($aula['link_para_aula'] ?? ''));
if ($videoUrl !== '' && (!filter_var($videoUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $videoUrl))) {
    $videoUrl = '';
}
$youtubeId = null;
if ($videoUrl !== '' && preg_match('%(?:youtube(?:-nocookie)?\.com/(?:.*[?&]v=|(?:embed|shorts)/)|youtu\.be/)([^"&?/ ]{11})%i', $videoUrl, $match)) {
    $youtubeId = $match[1];
}
$listUrl = BASE_URL . 'index.php?m=aulas&a=list';
if (!empty($aula['turma'])) {
    $listUrl .= '&turma_view=' . (int) $aula['turma'];
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <span class="badge bg-primary-subtle text-primary mb-2">Aula #<?php echo $id; ?></span>
        <h2 class="h4 fw-bold mb-1"><?php echo htmlspecialchars($aula['aula'] ?: 'Aula sem título'); ?></h2>
        <div class="text-muted">
            <?php echo htmlspecialchars($aula['turma_nome'] ?? 'Turma não informada'); ?>
            <?php if (!empty($aula['etapa_nome'])): ?> • <?php echo htmlspecialchars($aula['etapa_nome']); ?><?php endif; ?>
        </div>
    </div>
    <a href="<?php echo htmlspecialchars($listUrl); ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Voltar para as aulas
    </a>
</div>

<div class="row g-4">
    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm overflow-hidden">
            <?php if ($youtubeId): ?>
                <div class="ratio ratio-16x9 bg-dark">
                    <iframe src="https://www.youtube-nocookie.com/embed/<?php echo htmlspecialchars($youtubeId); ?>"
                            title="<?php echo htmlspecialchars($aula['aula'] ?: 'Aula'); ?>"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                            allowfullscreen></iframe>
                </div>
            <?php elseif ($videoUrl !== '' && ($aula['sem_video'] ?? '') !== '1'): ?>
                <div class="card-body text-center py-5">
                    <i class="bi bi-play-circle display-3 text-primary"></i>
                    <p class="mt-3 mb-3">A gravação desta aula está disponível em um site externo.</p>
                    <a href="<?php echo htmlspecialchars($videoUrl); ?>" target="_blank" rel="noopener" class="btn btn-primary">
                        Abrir gravação <i class="bi bi-box-arrow-up-right ms-1"></i>
                    </a>
                </div>
            <?php else: ?>
                <div class="card-body text-center py-5 text-muted">
                    <i class="bi bi-camera-video-off display-3"></i>
                    <p class="mt-3 mb-0">Esta aula não possui gravação disponível.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h5 class="card-title fw-bold mb-3">Detalhes da aula</h5>
                <dl class="mb-0">
                    <dt class="text-muted small">Data</dt>
                    <dd><?php echo !empty($aula['data']) && $aula['data'] !== '0000-00-00' ? date('d/m/Y', strtotime($aula['data'])) : '—'; ?></dd>
                    <dt class="text-muted small">Horário</dt>
                    <dd>
                        <?php echo !empty($aula['inicio']) ? substr($aula['inicio'], 0, 5) : '—'; ?>
                        <?php if (!empty($aula['termino'])): ?> às <?php echo substr($aula['termino'], 0, 5); ?><?php endif; ?>
                    </dd>
                    <dt class="text-muted small">Professor(es)</dt>
                    <dd><?php echo $professores ? htmlspecialchars(implode(', ', $professores)) : '—'; ?></dd>
                </dl>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="card-title fw-bold mb-3">Materiais</h5>
                <?php echo cronograma_render_arquivos_html($arquivos); ?>
            </div>
        </div>
    </div>
</div>
