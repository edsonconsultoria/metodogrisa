<?php
// prevenção a erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// app/modules/alunos/delete.php

require_once __DIR__ . '/../../core/CrudEngine.php';

$tableName = '104_alunos';
$engine = new CrudEngine($pdo, $tableName);

$id = (int) ($_GET['id'] ?? 0);

if ($id && $engine->delete($id)) {
    $msg = "Aluno removido com sucesso.";
    $type = "success";
    log_event($pdo, $tableName, 'DELETE', "Aluno ID $id removido.");
} else {
    $msg = "Erro ao remover o aluno.";
    $type = "danger";
}

require_once __DIR__ . '/../../helpers/list_state.php';
header("Location: " . list_state_return_destination('alunos', 'list', ['msg' => $msg, 'type' => $type]));
exit;
