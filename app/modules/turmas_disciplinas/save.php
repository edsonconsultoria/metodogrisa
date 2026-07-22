<?php
// app/modules/turmas_disciplinas/save.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';

$tableName = '104_turmas_disciplinas';
$engine = new CrudEngine($pdo, $tableName);
$primaryKey = $engine->getPrimaryKey();
$id = isset($_POST[$primaryKey]) ? (int) $_POST[$primaryKey] : null;

$data = $_POST;
unset($data[$primaryKey]);

try {
    $success = $engine->save($data, $id);
    if ($success) {
        require_once __DIR__ . '/../../helpers/list_state.php';
        log_event($pdo, $tableName, $id ? 'UPDATE' : 'INSERT', ($id ? "Editado" : "Criado") . " registro de Turma Disciplina");
        header("Location: " . list_state_return_destination('turmas_disciplinas', 'list', ['msg' => 'success']));
        exit;
    } else {
        echo "Erro ao salvar o registro de Turma Disciplina.";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}



