<?php
// app/modules/manual/professor.php
require_once __DIR__ . '/../../config/config.php';

if (!current_user_is_at_least_admin() && !current_user_is_professor()) {
    echo "Acesso negado.";
    exit;
}
?>

<div class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-success text-white">
            <h3 class="mb-0"><i class="bi bi-book me-2"></i>Manual do Professor</h3>
        </div>
        <div class="card-body">
            <h4>Guia para Professores</h4>
            <p>Como professor, você tem ferramentas para gerenciar suas aulas e acompanhar o progresso dos alunos.</p>

            <hr>

            <h5>1. Lançamento de Notas</h5>
            <p>Acesse <strong>Cursos > Turmas Notas</strong> para registrar o desempenho dos seus alunos.</p>

            <h5>2. Relatório de Aulas</h5>
            <p>Em <strong>Cursos > Aulas Professores</strong>, você pode emitir relatórios das suas atividades.</p>

            <h5>3. Gestão de Conteúdo</h5>
            <p>Utilize o menu <strong>Aulas</strong> para organizar os materiais e vídeos disponibilizados.</p>
        </div>
        <div class="card-footer text-end">
            <a href="index.php?m=manual&a=list" class="btn btn-secondary">Voltar aos Manuais</a>
        </div>
    </div>
</div>