<?php
// app/modules/horario_padrao/save.php

require_once __DIR__ . '/../../core/CrudEngine.php';

$tableName = '104_horario_padrao';
$engine = new CrudEngine($pdo, $tableName);

$id = isset($_POST['id']) ? (int) $_POST['id'] : null;
$data = $_POST;

if ($engine->save($data, $id)) {
    $msg = $id ? "Horário atualizado com sucesso." : "Horário cadastrado com sucesso.";
    $type = "success";
    log_event($pdo, $tableName, $id ? 'UPDATE' : 'INSERT', $msg . ($id ? " ID $id" : ""));
} else {
    $msg = "Erro ao salvar o horário.";
    $type = "danger";
    log_event($pdo, $tableName, 'ERROR', $msg);
}

header("Location: " . BASE_URL . "index.php?m=horario_padrao&a=list&msg=" . urlencode($msg) . "&type=$type");
exit;
