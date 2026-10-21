<?php
// app/modules/manual/aluno.php
require_once __DIR__ . '/../../config/config.php';

if (!is_logged_in()) {
    require_login();
}
?>

<div class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h3 class="mb-0"><i class="bi bi-book me-2"></i>Manual do Aluno</h3>
        </div>
        <div class="card-body">
            <h4>Bem-vindo ao Sistema Método Grisa</h4>
            <p>Este manual fornece informações básicas sobre como utilizar o sistema como aluno.</p>

            <hr>

            <h5>1. Minhas Aulas</h5>
            <p>Acesse o menu <strong>Cursos > Aulas</strong> para visualizar o conteúdo disponível para você.</p>

            <h5>2. Cronograma</h5>
            <p>No menu <strong>Cursos > Cronograma</strong>, você pode acompanhar as datas e horários das etapas do seu
                curso.</p>

            <h5>3. Cadastro</h5>
            <p>Mantenha seus dados atualizados clicando no seu nome no canto superior direito e acessando suas
                informações.</p>
        </div>
        <div class="card-footer text-end">
            <a href="index.php?m=manual&a=list" class="btn btn-secondary">Voltar aos Manuais</a>
        </div>
    </div>
</div>