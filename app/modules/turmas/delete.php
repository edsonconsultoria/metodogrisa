<?php
// prevenção a erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// app/modules/turmas/delete.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/CrudEngine.php';

$tableName = '104_turmas';
$engine = new CrudEngine($pdo, $tableName);
$primaryKey = $engine->getPrimaryKey();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id > 0) {
    try {
        if ($engine->delete($id)) {
            log_event($pdo, $tableName, 'DELETE', "Excluído registro de Turma ID: $id");
            redirect("index.php?m=turmas&a=list&msg=deleted");
        } else {
            echo "Erro ao excluir o registro.";
        }
    } catch (Exception $e) {
        echo "Erro: " . $e->getMessage();
    }
} else {
    redirect("index.php?m=turmas&a=list");
}

