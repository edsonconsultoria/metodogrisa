<?php
// prevenção a erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// app/modules/alunos/ajax_link.php

require_once __DIR__ . '/../../core/CrudEngine.php';

// Garantir que a requisição seja POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

$tableName = '104_alunos';
$engine = new CrudEngine($pdo, $tableName);

$aluno = (int) ($_POST['aluno'] ?? 0);
$turma = (int) ($_POST['turma'] ?? 0);
$id = (int) ($_POST['id'] ?? 0);

if (!$aluno || !$turma) {
    http_response_code(400);
    echo json_encode(['error' => 'Aluno e Turma são obrigatórios']);
    exit;
}

// Verificar se já existe vínculo (apenas se for inserção ou se mudou a turma)
if ($id <= 0) {
    $stmtCheck = $pdo->prepare("SELECT id FROM 104_alunos WHERE aluno = ? AND turma = ?");
    $stmtCheck->execute([$aluno, $turma]);
    if ($stmtCheck->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Este aluno já está vinculado a esta turma']);
        exit;
    }
}

$data = [
    'aluno' => $aluno,
    'turma' => $turma,
    'status' => $_POST['status'] ?? 'Ativo',
    'tipo' => $_POST['tipo'] ?? ''
];

if ($id > 0) {
    $data['id'] = $id;
}

if ($engine->save($data)) {
    $finalId = $id > 0 ? $id : $pdo->lastInsertId();
    $acao = $id > 0 ? 'UPDATE' : 'INSERT';
    log_event($pdo, $tableName, $acao, "Vínculo de aluno ID $finalId (Aluno $aluno, Turma $turma) salvo via AJAX");

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'id' => $finalId]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao salvar o vínculo']);
}
exit;
