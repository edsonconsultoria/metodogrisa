<?php
// app/modules/aluno_provas/ajax_save_answer.php
require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json');

$user = current_user();
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Sessão expirada.']);
    exit;
}

$alunoId = $user['id'];
$provaId = $_POST['prova_id'] ?? null;
$questaoId = $_POST['questao_id'] ?? null;
$respostaId = $_POST['resposta_id'] ?? null;

if (!$provaId || !$questaoId) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos.']);
    exit;
}

try {
    $isAdmin = current_user_is_at_least_admin();

    if ($isAdmin) {
        // Modo Simulação para administradores: Salvar na sessão
        if (!isset($_SESSION['test_respostas'])) {
            $_SESSION['test_respostas'] = [];
        }
        if (!isset($_SESSION['test_respostas'][$provaId])) {
            $_SESSION['test_respostas'][$provaId] = [];
        }
        $_SESSION['test_respostas'][$provaId][$questaoId] = $respostaId;

        echo json_encode(['success' => true, 'simulated' => true]);
        exit;
    }

    // Verificar se a prova ainda está aberta para este aluno
    $stmt = $pdo->prepare("SELECT concluida FROM 104_provas_alunos WHERE prova = ? AND aluno = ?");
    $stmt->execute([$provaId, $alunoId]);
    $pa = $stmt->fetch();

    if (!$pa || $pa['concluida']) {
        echo json_encode(['success' => false, 'message' => 'Esta prova já foi finalizada.']);
        exit;
    }

    // Upsert da resposta
    $stmtCheck = $pdo->prepare("SELECT id FROM 104_provas_respostas WHERE prova = ? AND aluno = ? AND questao = ?");
    $stmtCheck->execute([$provaId, $alunoId, $questaoId]);
    $existing = $stmtCheck->fetch();

    if ($existing) {
        $stmtUpd = $pdo->prepare("UPDATE 104_provas_respostas SET resposta = ? WHERE id = ?");
        $stmtUpd->execute([$respostaId, $existing['id']]);
    } else {
        $stmtIns = $pdo->prepare("INSERT INTO 104_provas_respostas (prova, aluno, questao, resposta) VALUES (?, ?, ?, ?)");
        $stmtIns->execute([$provaId, $alunoId, $questaoId, $respostaId]);
    }

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados.']);
}
