<?php
// app/modules/alunos/ajax_toggle.php

// prevenção a erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../core/CrudEngine.php';

// Garantir que a requisição seja POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$field = $_POST['field'] ?? '';

// Lista de campos permitidos para toggle
$allowedFields = [
    'emitido_cert_metodo_grisa',
    'emitido_cert_universidade',
    'rg_carteira_conselho',
    'cpf',
    'cer_nascimento_casamento',
    'comprovante_residencia',
    'fge',
    'tcc',
    'uso_imagem',
    'cert_grad',
];

if (!$id || !in_array($field, $allowedFields)) {
    http_response_code(400);
    echo json_encode(['error' => 'Parâmetros inválidos']);
    exit;
}

// Buscar valor atual
$stmt = $pdo->prepare("SELECT `{$field}` FROM 104_alunos WHERE id = ?");
$stmt->execute([$id]);
$currentValue = $stmt->fetchColumn();

// Toggle valor (1 -> 0/NULL, 0/NULL -> 1)
$newValue = ($currentValue === '1') ? '0' : '1';

// Atualizar no banco
$stmtUpdate = $pdo->prepare("UPDATE 104_alunos SET `{$field}` = ? WHERE id = ?");
if ($stmtUpdate->execute([$newValue, $id])) {
    log_event($pdo, '104_alunos', 'UPDATE', "Status de {$field} alterado para {$newValue} no aluno ID {$id} via AJAX");

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'newValue' => $newValue]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao atualizar o status']);
}
exit;
