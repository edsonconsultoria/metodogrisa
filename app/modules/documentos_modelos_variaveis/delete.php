<?php
// app/modules/documentos_modelos_variaveis/delete.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/CrudEngine.php';

$tableName = '104_documentos_modelos_variaveis';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$engine = new CrudEngine($pdo, $tableName);

if ($id <= 0) {
    die("ID inválido.");
}

// Buscar modelo_id antes de excluir para redirecionar corretamente
$stmt = $pdo->prepare("SELECT modelo_id FROM 104_documentos_modelos_variaveis WHERE id = ?");
$stmt->execute([$id]);
$modeloId = $stmt->fetchColumn();

try {
    $success = $engine->delete($id);
    if ($success) {
        log_event($pdo, $tableName, 'DELETE', "Excluída variável ID $id");
        redirect("index.php?m=documentos_modelos&a=view&id=$modeloId&msg=deleted");
    } else {
        echo "Erro ao excluir a variável.";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
