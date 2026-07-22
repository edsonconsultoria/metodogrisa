<?php
// app/modules/alunos/ajax_delete.php
require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

$tableName = '104_alunos';
$id = (int) ($_POST['id'] ?? 0);

if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'ID é obrigatório']);
    exit;
}

// Verificar se existe
$stmt = $pdo->prepare("SELECT id FROM $tableName WHERE id = ?");
$stmt->execute([$id]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'Vínculo não encontrado']);
    exit;
}

$stmtDel = $pdo->prepare("DELETE FROM $tableName WHERE id = ?");
if ($stmtDel->execute([$id])) {
    log_event($pdo, $tableName, 'DELETE', "Vínculo de aluno ID $id removido via AJAX");
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao remover o vínculo']);
}
exit;
