<?php
// app/modules/documentos_gerados/delete.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/services/DocumentoGeradoService.php';
require_once __DIR__ . '/../../helpers/list_state.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$return = $_GET['return'] ?? '';

if ($id <= 0) {
    die("ID inválido.");
}

$service = new DocumentoGeradoService($pdo);

try {
    if ($service->excluir($id)) {
        log_event($pdo, '104_documentos_gerados', 'DELETE', "Excluído registro de documento ID $id");

        $dest = list_state_return_destination('documentos_gerados', 'list', ['msg' => 'deleted']);
        header("Location: " . $dest);
    } else {
        echo "Erro ao excluir o registro ou registro não encontrado.";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
