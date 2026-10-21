<?php
// app/templates/dashboard.php

$user = current_user();
$userName = $user['nome'] ?? 'Usuário';

// Basic stats for the welcome screen (optional but nice)
$totalPessoas = (int) $pdo->query("SELECT COUNT(*) FROM 101_pessoas")->fetchColumn();
?>

<div class="container-fluid py-4">
  <div class="row justify-content-center">
    <div class="col-lg-10 text-center">
      <div class="welcome-card card border-0 shadow-lg p-3 mb-4 rounded-4 overflow-hidden position-relative"
        style="background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); color: white;">
        <!-- Decorative element -->
        <div class="position-absolute translate-middle"
          style="top: 0; right: -50px; width: 200px; height: 200px; background: rgba(255,255,255,0.1); border-radius: 50%;">
        </div>

        <div class="position-relative z-1 py-1">
          <h3 class="fw-bold mb-1">Bem-vindo, <?php echo htmlspecialchars($userName); ?>!</h3>
          <p class="small mb-2 opacity-90">
            O Sistema Método Grisa está à sua disposição. Explore os módulos no menu superior para começar.
          </p>

          <?php if (current_user_is_at_least_admin()): ?>
            <div
              class="d-inline-block px-3 py-1 bg-white bg-opacity-10 rounded-pill border border-white border-opacity-20 shadow-sm small">
              <a href="index.php?m=pessoas&a=list" class="text-white text-decoration-none">
                <i class="bi bi-people-fill me-1"></i>
              </a>
              Pessoas cadastradas: <strong><?php echo $totalPessoas; ?></strong>
            </div>
          <?php endif; ?>
        </div>
      </div>


      <div class="row g-4">
        <?php if (current_user_is_at_least_admin()): ?>
          <div class="col-12 col-sm-6 col-md-4">
            <a href="index.php?m=pessoas&a=list"
              class="card h-100 border-0 shadow-sm text-decoration-none transition-hover p-4 rounded-3 text-start">
              <div class="d-flex align-items-center mb-3 text-primary">
                <i class="bi bi-people-fill fs-3 me-3"></i>
                <h5 class="mb-0 fw-bold">Pessoas</h5>
              </div>
              <p class="text-muted small mb-0">Gerencie todos os contatos e clientes da base de dados.</p>
            </a>
          </div>
          <div class="col-12 col-sm-6 col-md-4">
            <a href="index.php?m=102_diario&a=list"
              class="card h-100 border-0 shadow-sm text-decoration-none transition-hover p-4 rounded-3 text-start">
              <div class="d-flex align-items-center mb-3 text-success">
                <i class="bi bi-currency-dollar fs-3 me-3"></i>
                <h5 class="mb-0 fw-bold">Finanças</h5>
              </div>
              <p class="text-muted small mb-0">Acompanhe o diário, lançamentos e fluxo de caixa.</p>
            </a>
          </div>
        <?php endif; ?>

        <div class="col-12 col-sm-6 col-md-4">
          <a href="index.php?m=etapas_cronogramas&a=list"
            class="card h-100 border-0 shadow-sm text-decoration-none transition-hover p-4 rounded-3 text-start">
            <div class="d-flex align-items-center mb-3 text-info">
              <i class="bi bi-book fs-3 me-3"></i>
              <h5 class="mb-0 fw-bold">Cursos</h5>
            </div>
            <p class="text-muted small mb-0">Gerencie cronogramas e notas dos alunos.</p>
          </a>
        </div>

        <?php if (current_user_is_at_least_admin()): ?>
          <div class="col-12 col-sm-6 col-md-4">
            <a href="index.php?m=etapas&a=list"
              class="card h-100 border-0 shadow-sm text-decoration-none transition-hover p-4 rounded-3 text-start">
              <div class="d-flex align-items-center mb-3 text-info">
                <i class="bi bi-layers fs-3 me-3"></i>
                <h5 class="mb-0 fw-bold">Etapas</h5>
              </div>
              <p class="text-muted small mb-0">Gerencie as etapas dos cursos e seus cronogramas.</p>
            </a>
          </div>
          <div class="col-12 col-sm-6 col-md-4">
            <a href="index.php?m=turmas&a=list"
              class="card h-100 border-0 shadow-sm text-decoration-none transition-hover p-4 rounded-3 text-start">
              <div class="d-flex align-items-center mb-3 text-warning">
                <i class="bi bi-mortarboard fs-3 me-3"></i>
                <h5 class="mb-0 fw-bold">Turmas</h5>
              </div>
              <p class="text-muted small mb-0">Gerencie as turmas e visualização de alunos.</p>
            </a>
          </div>
          <div class="col-12 col-sm-6 col-md-4">
            <a href="index.php?m=alunos&a=list"
              class="card h-100 border-0 shadow-sm text-decoration-none transition-hover p-4 rounded-3 text-start">
              <div class="d-flex align-items-center mb-3 text-info">
                <i class="bi bi-person-badge fs-3 me-3"></i>
                <h5 class="mb-0 fw-bold">Alunos</h5>
              </div>
              <p class="text-muted small mb-0">Gerencie o cadastro de todos os alunos do sistema.</p>
            </a>
          </div>
        <?php endif; ?>
        <div class="col-12 col-sm-6 col-md-4">
          <a href="index.php?m=aulas&a=list"
            class="card h-100 border-0 shadow-sm text-decoration-none transition-hover p-4 rounded-3 text-start">
            <div class="d-flex align-items-center mb-3 text-danger">
              <i class="bi bi-play-btn fs-3 me-3"></i>
              <h5 class="mb-0 fw-bold">Aulas</h5>
            </div>
            <p class="text-muted small mb-0">Assista e gerencie as gravações das aulas.</p>
          </a>
        </div>
        <div class="col-12 col-sm-6 col-md-4">
          <a href="index.php?m=aluno_provas&a=list"
            class="card h-100 border-0 shadow-sm text-decoration-none transition-hover p-4 rounded-3 text-start">
            <div class="d-flex align-items-center mb-3 text-primary">
              <i class="bi bi-journal-check fs-3 me-3"></i>
              <h5 class="mb-0 fw-bold">Provas</h5>
            </div>
            <p class="text-muted small mb-0">Acesse e realize suas provas agendadas.</p>
          </a>
        </div>
        <div class="col-12 col-sm-6 col-md-4">
          <a href="index.php?m=manual&a=list"
            class="card h-100 border-0 shadow-sm text-decoration-none transition-hover p-4 rounded-3 text-start">
            <div class="d-flex align-items-center mb-3 text-secondary">
              <i class="bi bi-journal-text fs-3 me-3"></i>
              <h5 class="mb-0 fw-bold">Manuais</h5>
            </div>
            <p class="text-muted small mb-0">Manuais detalhados de uso do sistema para cada perfil.</p>
          </a>
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
    transform: translateY(-5px);
    box-shadow: 0 1rem 3rem rgba(0, 0, 0, .15) !important;
  }

  .welcome-card i.display-1 {
    filter: drop-shadow(0 0.5rem 1rem rgba(0, 0, 0, 0.2));
  }

  /* Ajustes para evitar que o título saia da caixa */
  .transition-hover h5 {
    font-size: clamp(0.9rem, 2vw, 1.2rem);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    flex-grow: 1;
    margin-right: 5px;
  }

  .transition-hover i {
    flex-shrink: 0;
  }

  /* Ajuste responsivo para telas menores */
  @media (max-width: 768px) {
    .transition-hover h5 {
      font-size: 1rem;
      white-space: normal;
      word-break: break-word;
    }
  }
</style>