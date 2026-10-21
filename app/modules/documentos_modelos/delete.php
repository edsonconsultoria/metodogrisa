<?php
// app/modules/documentos_modelos/delete.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';

$tableName = '104_documentos_modelos';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$engine = new CrudEngine($pdo, $tableName);

if ($id <= 0) {
    die("ID inválido.");
}

try {
    $success = $engine->delete($id);
    if ($success) {
        log_event($pdo, $tableName, 'DELETE', "Excluído modelo de documento ID $id");
        redirect("index.php?m=documentos_modelos&a=list&msg=deleted");
    } else {
        echo "Erro ao excluir o modelo de documento.";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
