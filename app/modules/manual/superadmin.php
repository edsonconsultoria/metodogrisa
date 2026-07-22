<?php
// app/modules/manual/superadmin.php
require_once __DIR__ . '/../../config/config.php';

if (!current_user_is_superadmin()) {
    echo "Acesso negado.";
    exit;
}
?>

<div class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-dark text-white">
            <h3 class="mb-0"><i class="bi bi-shield-lock me-2"></i>Manual do Superadmin</h3>
        </div>
        <div class="card-body">
            <h4>Controle Total do Sistema</h4>
            <p>Como Superadmin, você possui acesso a configurações críticas e ferramentas de manutenção.</p>

            <hr>

            <h5>1. Apoio e Manutenção</h5>
            <p>Utilize o menu <strong>Apoio</strong> para backups, auditoria de sistema e parametrização geral.</p>

            <h5>2. Segurança</h5>
            <p>Gerencie permissões e visualize logs de acesso para garantir a integridade dos dados.</p>

            <h5>3. Vendas e Marketing</h5>
            <p>Controle produtos, categorias e ferramentas de mailling para impulsionar o negócio.</p>
        </div>
        <div class="card-footer text-end">
            <a href="index.php?m=manual&a=list" class="btn btn-secondary">Voltar aos Manuais</a>
        </div>
    </div>
</div>