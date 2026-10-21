<?php
// app/modules/documentos_gerados/ajax_generate_turma.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/services/DocumentoGeradoService.php';

header('Content-Type: application/json');

$modeloId = isset($_POST['modelo_id']) ? (int) $_POST['modelo_id'] : 0;
$turmaId = isset($_POST['turma_id']) ? (int) $_POST['turma_id'] : 0;
$usuarioId = $_SESSION['user_id'] ?? null;

if ($modeloId <= 0 || $turmaId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos.']);
    exit;
}

try {
    $service = new DocumentoGeradoService($pdo);
    $result = $service->gerarDocumentosTurma($modeloId, $turmaId, $usuarioId);
    echo json_encode($result);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro interno: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
