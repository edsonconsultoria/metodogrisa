<?php
// app/modules/turmas_disciplinas/delete.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/CrudEngine.php';

$tableName = '104_turmas_disciplinas';
$engine = new CrudEngine($pdo, $tableName);
$primaryKey = $engine->getPrimaryKey();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

require_once __DIR__ . '/../../helpers/list_state.php';
if ($id > 0) {
    try {
        if ($engine->delete($id)) {
            log_event($pdo, $tableName, 'DELETE', "Excluído registro de Turma Disciplina ID: $id");
            header("Location: " . list_state_return_destination('turmas_disciplinas', 'list', ['msg' => 'deleted']));
            exit;
        } else {
            echo "Erro ao excluir o registro.";
        }
    } catch (Exception $e) {
        echo "Erro: " . $e->getMessage();
    }
} else {
    header("Location: " . list_state_return_destination('turmas_disciplinas', 'list'));
    exit;
}


