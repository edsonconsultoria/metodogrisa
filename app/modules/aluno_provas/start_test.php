<?php
// app/modules/aluno_provas/start_test.php
$user = current_user();
$userId = $user['id'];
$provaId = $_GET['prova_id'] ?? null;

if (!current_user_is_at_least_admin()) {
    die("Acesso negado. Apenas administradores podem iniciar provas de teste.");
}

if (!$provaId) {
    die("ID da prova não informado.");
}

// Verificar se já existe um vínculo
$stmt = $pdo->prepare("SELECT id FROM 104_provas_alunos WHERE prova = ? AND aluno = ?");
$stmt->execute([$provaId, $userId]);
$pa = $stmt->fetch();

if ($pa) {
    $pa_id = $pa['id'];
    // Resetar para um novo teste
    $pdo->prepare("UPDATE 104_provas_alunos SET concluida = 0, nota = NULL, autorizacao_especial = 1 WHERE id = ?")
        ->execute([$pa_id]);

    // Limpar respostas antigas
    $pdo->prepare("DELETE FROM 104_provas_respostas WHERE prova = ? AND aluno = ?")
        ->execute([$provaId, $userId]);
} else {
    // Criar novo vínculo para o administrador
    $stmtIns = $pdo->prepare("INSERT INTO 104_provas_alunos (prova, aluno, concluida, autorizacao_especial) VALUES (?, ?, 0, 1)");
    $stmtIns->execute([$provaId, $userId]);
    $pa_id = $pdo->lastInsertId();
}

// Remover dados de sessão de início de prova para forçar novo timestamp
unset($_SESSION['prova_start_' . $pa_id]);
unset($_SESSION['test_respostas'][$provaId]);
unset($_SESSION['test_concluido'][$pa_id]);

header("Location: index.php?m=aluno_provas&a=view&id=$pa_id");
exit;
