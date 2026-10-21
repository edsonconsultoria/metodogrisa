<?php
// app/modules/interessados/delete.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/CrudEngine.php';

if (!current_user_is_at_least_admin()) {
    die('Acesso negado.');
}

$tableName = '101_interessados';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id > 0) {
    $engine = new CrudEngine($pdo, $tableName);
    try {
        if ($engine->delete($id)) {
            log_event($pdo, $tableName, 'DELETE', "Excluído registro de Interessado ID $id");
            redirect("index.php?m=interessados&a=list&msg=deleted");
        } else {
            echo "Erro ao excluir o registro.";
        }
    } catch (Exception $e) {
        echo "Erro: " . $e->getMessage();
    }
} else {
    redirect("index.php?m=interessados&a=list");
}
