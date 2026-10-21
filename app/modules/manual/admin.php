<?php
// app/modules/manual/admin.php
require_once __DIR__ . '/../../config/config.php';

if (!current_user_is_at_least_admin()) {
    echo "Acesso negado.";
    exit;
}
?>

<div class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-info text-white">
            <h3 class="mb-0"><i class="bi bi-book me-2"></i>Manual do Administrador</h3>
        </div>
        <div class="card-body">
            <h4>Gestão de Sistema (Admin)</h4>
            <p>Este manual detalha as funções administrativas do sistema Método Grisa.</p>

            <hr>

            <h5>1. Gestão de Pessoas</h5>
            <p>Acesse <strong>Pessoas > Pessoas</strong> para gerenciar usuários, alunos e professores.</p>

            <h5>2. Finanças</h5>
            <p>O menu <strong>Finanças</strong> permite o controle do fluxo de caixa, pagamentos e recebimentos.</p>

            <h5>3. Configurações de Cursos</h5>
            <p>Gerencie etapas, turmas, matrículas e horários no menu <strong>Cursos</strong>.</p>
        </div>
        <div class="card-footer text-end">
            <a href="index.php?m=manual&a=list" class="btn btn-secondary">Voltar aos Manuais</a>
        </div>
    </div>
</div>