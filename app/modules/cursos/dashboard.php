<?php
// app/modules/cursos/dashboard.php

require_once __DIR__ . '/../../config/config.php';

if (!current_user_is_at_least_admin() && !current_user_is_professor() && !current_user_is_aluno()) {
    echo '<div class="alert alert-danger mt-3">Acesso negado.</div>';
    include __DIR__ . '/../../templates/footer.php';
    exit;
}

$baseHref = BASE_URL . 'index.php';
$isAlunoDashboard = current_user_is_aluno()
    && !current_user_is_professor()
    && !current_user_is_at_least_admin();
$provasHref = $isAlunoDashboard
    ? $baseHref . '?m=aluno_provas&a=list'
    : $baseHref . '?m=104_provas&a=list';

// Helper function to safely count rows in table with fallback
function safeCountCurso(PDO $pdo, array $possibleTables, string $where = '1=1'): int {
    foreach ($possibleTables as $t) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$t}` WHERE {$where}");
            $stmt->execute();
            return (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            continue;
        }
    }
    return 0;
}

// 1. KPI Statistics
$totalEtapas = safeCountCurso($pdo, ['104_etapas', '103_etapas', 'etapas']);
$totalTurmas = safeCountCurso($pdo, ['104_turmas', '103_turmas', 'turmas']);
$totalAulas = safeCountCurso($pdo, ['104_cronograma', '103_aulas', 'aulas']);
$totalCronogramas = safeCountCurso($pdo, ['104_cronograma', '103_etapas_cronogramas', 'etapas_cronogramas']);
$totalAlunos = safeCountCurso($pdo, ['104_alunos', '101_alunos', 'alunos']);
$totalProfessores = safeCountCurso($pdo, ['104_professores', '101_professores', 'professores']);
$totalProvas = safeCountCurso($pdo, ['104_provas', 'provas']);
$totalProvasRealizadas = safeCountCurso($pdo, ['104_provas_alunos', 'provas_alunos'], 'concluida = 1');
$totalQuestoes = safeCountCurso($pdo, ['104_questoes', 'questoes']);
$totalDocumentos = safeCountCurso($pdo, ['104_documentos_modelos', 'documentos_modelos']);
$totalDocumentosGerados = safeCountCurso($pdo, ['104_documentos_gerados', 'documentos_gerados']);
$totalHorarios = safeCountCurso($pdo, ['104_horario_padrao', 'horario_padrao']);
$totalEtapasTurmas = safeCountCurso($pdo, ['104_etapas_turmas', 'etapas_turmas']);

// 2. Fetch Latest Classes / Cronograma
$currentUser = current_user();
$currentUserId = (int) ($currentUser['id'] ?? 0);
$lessonsWhere = '';
$lessonsParams = [];
if ($isAlunoDashboard) {
    $lessonsWhere = "WHERE c.turma IN (
        SELECT turma FROM 104_alunos
        WHERE aluno = :aluno_id AND (status = 'Ativo' OR status = '' OR status IS NULL)
    )";
    $lessonsParams[':aluno_id'] = $currentUserId;
}

$stmt = $pdo->prepare("
    SELECT c.id, c.aula, c.data, c.inicio, c.termino,
           t.nome AS turma_nome, e.nome AS etapa_nome
    FROM 104_cronograma c
    LEFT JOIN 104_turmas t ON t.id = c.turma
    LEFT JOIN 104_etapas e ON e.id = c.etapa
    {$lessonsWhere}
    ORDER BY c.id DESC
    LIMIT 5
");
$stmt->execute($lessonsParams);
$upcomingLessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Fetch Latest Turmas
$recentTurmas = [];
foreach (['104_turmas', '103_turmas', 'turmas'] as $t) {
    try {
        $stmt = $pdo->query("SELECT * FROM `{$t}` ORDER BY id DESC LIMIT 5");
        $recentTurmas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($recentTurmas)) break;
    } catch (Throwable $e) {}
}
?>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<div class="container-fluid py-4">
  <!-- Banner Header -->
  <div class="card border-0 shadow-sm p-4 rounded-4 mb-4" style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); color: white;">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
      <div>
        <h2 class="fw-bold mb-1"><i class="bi bi-mortarboard-fill me-2"></i>Dashboard de Cursos & BI Educacional</h2>
        <p class="opacity-75 mb-0">Gestão acadêmica de etapas, turmas, aulas, alunos, professores e exames.</p>
      </div>
      <div class="mt-3 mt-md-0 d-flex gap-2">
        <?php if (current_user_is_at_least_admin()): ?>
          <a href="<?php echo $baseHref; ?>?m=etapas&a=form" class="btn btn-light btn-sm fw-bold px-3 shadow-sm">
            <i class="bi bi-plus-circle-fill me-1"></i> Nova Etapa
          </a>
          <a href="<?php echo $baseHref; ?>?m=turmas&a=form" class="btn btn-outline-light btn-sm px-3">
            <i class="bi bi-collection me-1"></i> Nova Turma
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Central De Módulos e Tabelas (Submenu Integrado) -->
  <div class="mb-4">
    <h5 class="fw-bold text-dark mb-3"><i class="bi bi-grid-fill me-2 text-info"></i>Acesso aos Módulos e Tabelas de Cursos</h5>
    <div class="row g-3">
      <!-- Etapas -->
      <div class="col-12 col-sm-6 col-md-4 col-xl-2-4">
        <a href="<?php echo $baseHref; ?>?m=etapas&a=list" class="card h-100 border-0 shadow-sm text-decoration-none transition-hover p-3 rounded-3 text-start">
          <div class="d-flex align-items-center mb-2 text-info">
            <div class="bg-info bg-opacity-10 p-2 rounded-3 me-3">
              <i class="bi bi-layers-fill fs-4"></i>
            </div>
            <div>
              <h6 class="mb-0 fw-bold text-dark">Etapas (Cursos)</h6>
              <small class="text-muted"><?php echo $totalEtapas; ?> etapas</small>
            </div>
          </div>
          <p class="text-secondary small mb-0">Gerenciar etapas e turmas do curso.</p>
        </a>
      </div>

      <!-- Turmas -->
      <div class="col-12 col-sm-6 col-md-4 col-xl-2-4">
        <a href="<?php echo $baseHref; ?>?m=turmas&a=list" class="card h-100 border-0 shadow-sm text-decoration-none transition-hover p-3 rounded-3 text-start">
          <div class="d-flex align-items-center mb-2 text-primary">
            <div class="bg-primary bg-opacity-10 p-2 rounded-3 me-3">
              <i class="bi bi-collection-fill fs-4"></i>
            </div>
            <div>
              <h6 class="mb-0 fw-bold text-dark">Turmas</h6>
              <small class="text-muted"><?php echo $totalTurmas; ?> turmas</small>
            </div>
          </div>
          <p class="text-secondary small mb-0">Turmas, disciplinas e notas.</p>
        </a>
      </div>

      <!-- Etapas x Turmas -->
      <?php if (current_user_is_at_least_admin()): ?>
      <div class="col-12 col-sm-6 col-md-4 col-xl-2-4">
        <a href="<?php echo $baseHref; ?>?m=etapas_turmas&a=list" class="card h-100 border-0 shadow-sm text-decoration-none transition-hover p-3 rounded-3 text-start">
          <div class="d-flex align-items-center mb-2" style="color: #0d9488;">
            <div class="p-2 rounded-3 me-3" style="background-color: rgba(13, 148, 136, .1);">
              <i class="bi bi-diagram-3-fill fs-4"></i>
            </div>
            <div>
              <h6 class="mb-0 fw-bold text-dark">Etapas x Turmas</h6>
              <small class="text-muted"><?php echo $totalEtapasTurmas; ?> vínculos</small>
            </div>
          </div>
          <p class="text-secondary small mb-0">Vínculo de etapas às turmas, datas e links.</p>
        </a>
      </div>
      <?php endif; ?>

      <!-- Aulas & Cronograma -->
      <div class="col-12 col-sm-6 col-md-4 col-xl-2-4">
        <a href="<?php echo $baseHref; ?>?m=aulas&a=list" class="card h-100 border-0 shadow-sm text-decoration-none transition-hover p-3 rounded-3 text-start">
          <div class="d-flex align-items-center mb-2 text-danger">
            <div class="bg-danger bg-opacity-10 p-2 rounded-3 me-3">
              <i class="bi bi-play-btn-fill fs-4"></i>
            </div>
            <div>
              <h6 class="mb-0 fw-bold text-dark">Aulas</h6>
              <small class="text-muted"><?php echo $totalAulas; ?> aulas</small>
            </div>
          </div>
          <p class="text-secondary small mb-0">Gravações de aulas e cronogramas.</p>
        </a>
      </div>

      <!-- Alunos -->
      <?php if (current_user_is_at_least_admin()): ?>
      <div class="col-12 col-sm-6 col-md-4 col-xl-2-4">
        <a href="<?php echo $baseHref; ?>?m=alunos&a=list" class="card h-100 border-0 shadow-sm text-decoration-none transition-hover p-3 rounded-3 text-start">
          <div class="d-flex align-items-center mb-2 text-secondary">
            <div class="bg-secondary bg-opacity-10 p-2 rounded-3 me-3">
              <i class="bi bi-people-fill fs-4"></i>
            </div>
            <div>
              <h6 class="mb-0 fw-bold text-dark">Alunos</h6>
              <small class="text-muted"><?php echo $totalAlunos; ?> alunos</small>
            </div>
          </div>
          <p class="text-secondary small mb-0">Cadastro de alunos, turmas e matrículas.</p>
        </a>
      </div>

      <!-- Horário das Aulas -->
      <div class="col-12 col-sm-6 col-md-4 col-xl-2-4">
        <a href="<?php echo $baseHref; ?>?m=horario_padrao&a=list" class="card h-100 border-0 shadow-sm text-decoration-none transition-hover p-3 rounded-3 text-start">
          <div class="d-flex align-items-center mb-2" style="color: #8b5cf6;">
            <div class="p-2 rounded-3 me-3" style="background-color: rgba(139, 92, 246, .1);">
              <i class="bi bi-clock-history fs-4"></i>
            </div>
            <div>
              <h6 class="mb-0 fw-bold text-dark">Horário das Aulas</h6>
              <small class="text-muted"><?php echo $totalHorarios; ?> horários</small>
            </div>
          </div>
          <p class="text-secondary small mb-0">Grade de horário padrão das aulas.</p>
        </a>
      </div>
      <?php endif; ?>

      <!-- Professores -->
      <div class="col-12 col-sm-6 col-md-4 col-xl-2-4">
        <a href="<?php echo $baseHref; ?>?m=professores&a=list" class="card h-100 border-0 shadow-sm text-decoration-none transition-hover p-3 rounded-3 text-start">
          <div class="d-flex align-items-center mb-2 text-warning">
            <div class="bg-warning bg-opacity-10 p-2 rounded-3 me-3">
              <i class="bi bi-person-badge-fill fs-4"></i>
            </div>
            <div>
              <h6 class="mb-0 fw-bold text-dark">Professores</h6>
              <small class="text-muted"><?php echo $totalProfessores; ?> docentes</small>
            </div>
          </div>
          <p class="text-secondary small mb-0">Corpo docente e relatórios de aulas.</p>
        </a>
      </div>

      <!-- Provas & Avaliações -->
      <div class="col-12 col-sm-6 col-md-4 col-xl-2-4">
        <a href="<?php echo htmlspecialchars($provasHref, ENT_QUOTES, 'UTF-8'); ?>" class="card h-100 border-0 shadow-sm text-decoration-none transition-hover p-3 rounded-3 text-start">
          <div class="d-flex align-items-center mb-2 text-success">
            <div class="bg-success bg-opacity-10 p-2 rounded-3 me-3">
              <i class="bi bi-journal-check fs-4"></i>
            </div>
            <div>
              <h6 class="mb-0 fw-bold text-dark"><?php echo $isAlunoDashboard ? 'Minhas Provas' : 'Provas & Questões'; ?></h6>
              <small class="text-muted"><?php echo $totalProvas; ?> provas</small>
            </div>
          </div>
          <p class="text-secondary small mb-0"><?php echo $isAlunoDashboard ? 'Acesse e realize suas provas agendadas.' : 'Avaliações, questões e exames.'; ?></p>
        </a>
      </div>

      <?php if (current_user_is_at_least_admin()): ?>
      <!-- Acesso a todas as provas -->
      <div class="col-12 col-sm-6 col-md-4 col-xl-2-4">
        <a href="<?php echo $baseHref; ?>?m=aluno_provas&amp;a=list" class="card h-100 border-0 shadow-sm text-decoration-none transition-hover p-3 rounded-3 text-start">
          <div class="d-flex align-items-center mb-2" style="color: #2563eb;">
            <div class="p-2 rounded-3 me-3" style="background-color: rgba(37, 99, 235, .1);">
              <i class="bi bi-ui-checks-grid fs-4"></i>
            </div>
            <div>
              <h6 class="mb-0 fw-bold text-dark">Acessar todas as provas</h6>
              <small class="text-muted"><?php echo $totalProvas; ?> provas</small>
            </div>
          </div>
          <p class="text-secondary small mb-0">Visualizar e testar todas as provas disponíveis.</p>
        </a>
      </div>
      <?php endif; ?>

      <?php if (current_user_is_at_least_admin()): ?>
      <!-- Banco de questões -->
      <div class="col-12 col-sm-6 col-md-4 col-xl-2-4">
        <a href="<?php echo $baseHref; ?>?m=104_questoes&amp;a=list" class="card h-100 border-0 shadow-sm text-decoration-none transition-hover p-3 rounded-3 text-start">
          <div class="d-flex align-items-center mb-2" style="color: #0891b2;">
            <div class="p-2 rounded-3 me-3" style="background-color: rgba(8, 145, 178, .1);">
              <i class="bi bi-patch-question-fill fs-4"></i>
            </div>
            <div>
              <h6 class="mb-0 fw-bold text-dark">Banco de Questões</h6>
              <small class="text-muted"><?php echo $totalQuestoes; ?> questões</small>
            </div>
          </div>
          <p class="text-secondary small mb-0">Cadastrar, consultar e editar questões das provas.</p>
        </a>
      </div>
      <?php endif; ?>

      <?php if (current_user_is_at_least_admin()): ?>
      <!-- Provas de todos os alunos -->
      <div class="col-12 col-sm-6 col-md-4 col-xl-2-4">
        <a href="<?php echo $baseHref; ?>?m=104_provas_alunos&amp;a=list" class="card h-100 border-0 shadow-sm text-decoration-none transition-hover p-3 rounded-3 text-start">
          <div class="d-flex align-items-center mb-2" style="color: #7c3aed;">
            <div class="p-2 rounded-3 me-3" style="background-color: rgba(124, 58, 237, .1);">
              <i class="bi bi-people-fill fs-4"></i>
            </div>
            <div>
              <h6 class="mb-0 fw-bold text-dark">Provas dos alunos</h6>
              <small class="text-muted">Todos os alunos</small>
            </div>
          </div>
          <p class="text-secondary small mb-0">Ver provas pendentes, concluídas, respostas e notas.</p>
        </a>
      </div>
      <?php endif; ?>

      <?php if (current_user_is_at_least_admin() || current_user_is_professor()): ?>
      <!-- Provas realizadas -->
      <div class="col-12 col-sm-6 col-md-4 col-xl-2-4">
        <a href="<?php echo $baseHref; ?>?m=104_provas_alunos&amp;a=list&amp;filter_concluida=1" class="card h-100 border-0 shadow-sm text-decoration-none transition-hover p-3 rounded-3 text-start">
          <div class="d-flex align-items-center mb-2" style="color: #ea580c;">
            <div class="p-2 rounded-3 me-3" style="background-color: rgba(234, 88, 12, .1);">
              <i class="bi bi-clipboard2-check-fill fs-4"></i>
            </div>
            <div>
              <h6 class="mb-0 fw-bold text-dark">Provas realizadas</h6>
              <small class="text-muted"><?php echo $totalProvasRealizadas; ?> concluídas</small>
            </div>
          </div>
          <p class="text-secondary small mb-0">Consultar respostas e notas dos alunos.</p>
        </a>
      </div>
      <?php endif; ?>

      <?php if (current_user_is_at_least_admin()): ?>
      <!-- Modelos de documentos -->
      <div class="col-12 col-sm-6 col-md-4 col-xl-2-4">
        <a href="<?php echo $baseHref; ?>?m=documentos_modelos&a=list" class="card h-100 border-0 shadow-sm text-decoration-none transition-hover p-3 rounded-3 text-start">
          <div class="d-flex align-items-center mb-2" style="color: #7c3aed;">
            <div class="p-2 rounded-3 me-3" style="background-color: rgba(124, 58, 237, .1);">
              <i class="bi bi-file-earmark-text-fill fs-4"></i>
            </div>
            <div>
              <h6 class="mb-0 fw-bold text-dark">Modelos de documentos</h6>
              <small class="text-muted"><?php echo $totalDocumentos; ?> modelos</small>
            </div>
          </div>
          <p class="text-secondary small mb-0">Cadastrar e editar modelos para geração.</p>
        </a>
      </div>

      <!-- Documentos gerados -->
      <div class="col-12 col-sm-6 col-md-4 col-xl-2-4">
        <a href="<?php echo $baseHref; ?>?m=documentos_gerados&a=list" class="card h-100 border-0 shadow-sm text-decoration-none transition-hover p-3 rounded-3 text-start">
          <div class="d-flex align-items-center mb-2" style="color: #0f766e;">
            <div class="p-2 rounded-3 me-3" style="background-color: rgba(15, 118, 110, .1);">
              <i class="bi bi-files fs-4"></i>
            </div>
            <div>
              <h6 class="mb-0 fw-bold text-dark">Documentos gerados</h6>
              <small class="text-muted"><?php echo $totalDocumentosGerados; ?> documentos</small>
            </div>
          </div>
          <p class="text-secondary small mb-0">Consultar, gerar e baixar documentos.</p>
        </a>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- KPI Indicators Cards -->
  <div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-md-3">
      <div class="card border-0 shadow-sm p-3 rounded-3 bg-white h-100">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <span class="text-muted small fw-bold">ETAPAS DO CURSO</span>
            <h3 class="fw-bold text-info mb-0 mt-1"><?php echo number_format($totalEtapas, 0, ',', '.'); ?></h3>
          </div>
          <div class="bg-info text-white p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
            <i class="bi bi-layers fs-4"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-md-3">
      <div class="card border-0 shadow-sm p-3 rounded-3 bg-white h-100">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <span class="text-muted small fw-bold">TURMAS ATIVAS</span>
            <h3 class="fw-bold text-primary mb-0 mt-1"><?php echo number_format($totalTurmas, 0, ',', '.'); ?></h3>
          </div>
          <div class="bg-primary text-white p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
            <i class="bi bi-collection fs-4"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-md-3">
      <div class="card border-0 shadow-sm p-3 rounded-3 bg-white h-100">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <span class="text-muted small fw-bold">AULAS / CRONOGRAMAS</span>
            <h3 class="fw-bold text-danger mb-0 mt-1"><?php echo number_format($totalAulas, 0, ',', '.'); ?></h3>
          </div>
          <div class="bg-danger text-white p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
            <i class="bi bi-play-btn fs-4"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-md-3">
      <div class="card border-0 shadow-sm p-3 rounded-3 bg-white h-100">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <span class="text-muted small fw-bold">PROVAS & QUESTÕES</span>
            <h3 class="fw-bold text-success mb-0 mt-1"><?php echo number_format($totalProvas, 0, ',', '.'); ?></h3>
          </div>
          <div class="bg-success text-white p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
            <i class="bi bi-journal-check fs-4"></i>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- BI Charts Section -->
  <div class="row g-4 mb-4">
    <!-- Chart 1: Proporção Educacional -->
    <div class="col-12 col-md-6 col-lg-4">
      <div class="card border-0 shadow-sm p-3 rounded-4 bg-white h-100">
        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-pie-chart-fill me-2 text-info"></i>Proporção do Módulo Acadêmico</h6>
        <div class="chart-container" style="position: relative; height: 240px;">
          <canvas id="chartCursoProporcao"></canvas>
        </div>
      </div>
    </div>

    <!-- Chart 2: Volume Acadêmico -->
    <div class="col-12 col-md-6 col-lg-4">
      <div class="card border-0 shadow-sm p-3 rounded-4 bg-white h-100">
        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-bar-chart-line-fill me-2 text-primary"></i>Volume por Área do Curso</h6>
        <div class="chart-container" style="position: relative; height: 240px;">
          <canvas id="chartCursoVolume"></canvas>
        </div>
      </div>
    </div>

    <!-- Chart 3: Provas & Banco de Questões -->
    <div class="col-12 col-md-12 col-lg-4">
      <div class="card border-0 shadow-sm p-3 rounded-4 bg-white h-100">
        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-journal-text me-2 text-success"></i>Provas vs Questões</h6>
        <div class="chart-container" style="position: relative; height: 240px;">
          <canvas id="chartCursoProvas"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Quick Data Lists Section -->
  <div class="row g-4">
    <!-- TURMAS RECENTES -->
    <div class="col-12 col-lg-6">
      <div class="card border-0 shadow-sm p-3 rounded-4 bg-white h-100">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="fw-bold text-dark mb-0"><i class="bi bi-collection me-2 text-primary"></i>Turmas Cadastradas</h6>
          <a href="<?php echo $baseHref; ?>?m=turmas&a=list" class="btn btn-link btn-sm p-0 text-decoration-none">Ver Todas &rarr;</a>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
              <tr>
                <th>#ID</th>
                <th>Nome da Turma / Descrição</th>
                <th class="text-end">Ação</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($recentTurmas)): ?>
                <?php foreach ($recentTurmas as $t): ?>
                  <tr>
                    <td class="fw-bold text-muted">#<?php echo $t['id']; ?></td>
                    <td>
                      <span class="fw-bold text-dark">
                        <?php echo htmlspecialchars($t['nome'] ?? ($t['turma'] ?? 'Turma sem nome')); ?>
                      </span>
                    </td>
                    <td class="text-end">
                      <a href="<?php echo $baseHref; ?>?m=turmas&a=view&id=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-primary py-0 px-2">
                        <i class="bi bi-eye"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="3" class="text-center text-muted py-3">Nenhuma turma cadastrada.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- CRONOGRAMA DE AULAS RECENTES -->
    <div class="col-12 col-lg-6">
      <div class="card border-0 shadow-sm p-3 rounded-4 bg-white h-100">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="fw-bold text-dark mb-0"><i class="bi bi-calendar-event me-2 text-danger"></i>Aulas & Cronogramas Recentes</h6>
          <a href="<?php echo $baseHref; ?>?m=aulas&a=list" class="btn btn-link btn-sm p-0 text-decoration-none">Ver Lista &rarr;</a>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
              <tr>
                <th>#ID</th>
                <th>Aula / Assunto</th>
                <th class="text-end">Ação</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($upcomingLessons)): ?>
                <?php foreach ($upcomingLessons as $l): ?>
                  <tr>
                    <td class="fw-bold text-muted">#<?php echo $l['id']; ?></td>
                    <td>
                      <a href="<?php echo $baseHref; ?>?m=aulas&a=view&id=<?php echo (int) $l['id']; ?>"
                         class="fw-bold text-dark text-decoration-none">
                        <?php echo htmlspecialchars($l['aula'] ?: 'Aula sem título'); ?>
                      </a>
                      <small class="text-muted d-block">
                        <?php echo htmlspecialchars(implode(' • ', array_filter([$l['turma_nome'] ?? '', $l['etapa_nome'] ?? '']))); ?>
                      </small>
                    </td>
                    <td class="text-end">
                      <a href="<?php echo $baseHref; ?>?m=aulas&a=view&id=<?php echo $l['id']; ?>" class="btn btn-sm btn-outline-primary py-0 px-2">
                        <i class="bi bi-eye"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="3" class="text-center text-muted py-3">Nenhuma aula cadastrada.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
  .transition-hover {
    transition: all 0.3s cubic-bezier(.25, .8, .25, 1);
  }
  .transition-hover:hover {
    transform: translateY(-4px);
    box-shadow: 0 0.8rem 2rem rgba(0, 0, 0, .08) !important;
  }
  @media (min-width: 1200px) {
    .col-xl-2-4 {
      flex: 0 0 auto;
      width: 20%;
    }
  }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
  // Chart 1: Proporção
  const ctxProporcao = document.getElementById('chartCursoProporcao');
  if (ctxProporcao) {
    new Chart(ctxProporcao, {
      type: 'doughnut',
      data: {
        labels: ['Etapas', 'Turmas', 'Aulas', 'Professores', 'Provas'],
        datasets: [{
          data: [<?php echo $totalEtapas; ?>, <?php echo $totalTurmas; ?>, <?php echo $totalAulas; ?>, <?php echo $totalProfessores; ?>, <?php echo $totalProvas; ?>],
          backgroundColor: ['#0284c7', '#3b82f6', '#ef4444', '#f59e0b', '#10b981']
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } }
        }
      }
    });
  }

  // Chart 2: Volume
  const ctxVolume = document.getElementById('chartCursoVolume');
  if (ctxVolume) {
    new Chart(ctxVolume, {
      type: 'bar',
      data: {
        labels: ['Etapas', 'Turmas', 'Aulas', 'Alunos', 'Provas'],
        datasets: [{
          label: 'Quantidade',
          data: [<?php echo $totalEtapas; ?>, <?php echo $totalTurmas; ?>, <?php echo $totalAulas; ?>, <?php echo $totalAlunos; ?>, <?php echo $totalProvas; ?>],
          backgroundColor: '#0284c7',
          borderRadius: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
      }
    });
  }

  // Chart 3: Provas vs Questões
  const ctxProvas = document.getElementById('chartCursoProvas');
  if (ctxProvas) {
    new Chart(ctxProvas, {
      type: 'bar',
      data: {
        labels: ['Banco de Questões', 'Provas Criadas', 'Documentos'],
        datasets: [{
          data: [<?php echo $totalQuestoes; ?>, <?php echo $totalProvas; ?>, <?php echo $totalDocumentos; ?>],
          backgroundColor: ['#10b981', '#0284c7', '#8b5cf6'],
          borderRadius: 6
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
      }
    });
  }
});
</script>
