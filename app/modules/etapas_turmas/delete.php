<?php
// app/modules/etapas_turmas/delete.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/list_state.php';

$tableName = '104_etapas_turmas';
$engine = new CrudEngine($pdo, $tableName);
$primaryKey = $engine->getPrimaryKey();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id > 0) {
    try {
        if ($engine->delete($id)) {
            log_event($pdo, $tableName, 'DELETE', "Excluído registro de Etapa/Turma ID: $id");
            
            $returnUrl = list_state_return_destination('etapas_turmas', 'list');
            $returnUrl .= (strpos($returnUrl, '?') === false ? '?' : '&') . 'msg=deleted';
            header('Location: ' . $returnUrl);
            exit;
        } else {
            echo "Erro ao excluir o registro.";
        }
    } catch (Exception $e) {
        echo "Erro: " . $e->getMessage();
    }
} else {
    $returnUrl = list_state_return_destination('etapas_turmas', 'list');
    header('Location: ' . $returnUrl);
    exit;
}

