<?php
// app/modules/manual/list.php
require_once __DIR__ . '/../../config/config.php';

if (!is_logged_in()) {
    require_login();
}

$can_see_superadmin = current_user_is_superadmin();
$can_see_admin = current_user_is_at_least_admin();
$can_see_professor = current_user_is_at_least_admin() || current_user_is_professor();
$can_see_aluno = true; // Todo usuário logado pode pelo menos ver o manual do aluno, conforme regra de visibilidade solicitada (professor vê de aluno, admin vê de aluno, etc)
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2 class="border-bottom pb-2"><i class="bi bi-journal-bookmark-fill me-2"></i>Manuais do Sistema</h2>
            <p class="text-muted">Selecione o manual detalhado com base no seu nível de acesso.</p>
        </div>
    </div>

    <div class="row g-4">
        <?php if ($can_see_superadmin): ?>
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 shadow-sm border-dark">
                    <div class="card-body text-center">
                        <i class="bi bi-shield-lock text-dark mb-3" style="font-size: 3rem;"></i>
                        <h5 class="card-title">Manual Superadmin</h5>
                        <p class="card-text small">Funções globais, backups e auditoria.</p>
                        <a href="index.php?m=manual&a=superadmin" class="btn btn-dark w-100">Acessar</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($can_see_admin): ?>
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 shadow-sm border-info">
                    <div class="card-body text-center">
                        <i class="bi bi-person-badge text-info mb-3" style="font-size: 3rem;"></i>
                        <h5 class="card-title">Manual Admin</h5>
                        <p class="card-text small">Gestão de pessoas, finanças e cadastros.</p>
                        <a href="index.php?m=manual&a=admin" class="btn btn-info w-100 text-white">Acessar</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($can_see_professor): ?>
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 shadow-sm border-success">
                    <div class="card-body text-center">
                        <i class="bi bi-mortarboard text-success mb-3" style="font-size: 3rem;"></i>
                        <h5 class="card-title">Manual Professor</h5>
                        <p class="card-text small">Lançamento de notas e gestão pedagógica.</p>
                        <a href="index.php?m=manual&a=professor" class="btn btn-success w-100">Acessar</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($can_see_aluno): ?>
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 shadow-sm border-primary">
                    <div class="card-body text-center">
                        <i class="bi bi-person-video3 text-primary mb-3" style="font-size: 3rem;"></i>
                        <h5 class="card-title">Manual Aluno</h5>
                        <p class="card-text small">Acesso a aulas, cronogramas e dados.</p>
                        <a href="index.php?m=manual&a=aluno" class="btn btn-primary w-100">Acessar</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>