<?php
// prevenção a erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// app/modules/alunos/save.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/list_state.php';

$tableName = '104_alunos';
$engine = new CrudEngine($pdo, $tableName);

$id = isset($_POST['id']) ? (int) $_POST['id'] : null;
$data = $_POST;

$diploma = trim((string) ($data['diploma'] ?? ''));
$diplomaPermitidos = ['Pós Graduação', 'Curso Livre'];

if ($diploma === '' || !in_array($diploma, $diplomaPermitidos, true)) {
    $msg = "O campo Diploma é obrigatório.";
    $type = "danger";
    log_event($pdo, $tableName, 'ERROR', $msg . ($id ? " ID $id" : ""));

    header("Location: " . list_state_return_destination('alunos', 'list', ['msg' => $msg, 'type' => $type]));
    exit;
}

if ($engine->save($data, $id)) {
    $msg = $id ? "Aluno atualizado com sucesso." : "Aluno cadastrado com sucesso.";
    $type = "success";
    log_event($pdo, $tableName, $id ? 'UPDATE' : 'INSERT', $msg . ($id ? " ID $id" : ""));
} else {
    $msg = "Erro ao salvar o aluno.";
    $type = "danger";
    log_event($pdo, $tableName, 'ERROR', $msg);
}

header("Location: " . list_state_return_destination('alunos', 'list', ['msg' => $msg, 'type' => $type]));
exit;
