<?php
// app/modules/matriculas/save.php

require_once __DIR__ . '/../../core/CrudEngine.php';

$tableName = '104_matriculas';
$engine = new CrudEngine($pdo, $tableName);

$id = isset($_POST['id']) ? (int) $_POST['id'] : null;
$data = $_POST;

if ($engine->save($data, $id)) {
    $msg = $id ? "Matrícula atualizada com sucesso." : "Matrícula cadastrada com sucesso.";
    $type = "success";
    log_event($pdo, $tableName, $id ? 'UPDATE' : 'INSERT', $msg . ($id ? " ID $id" : ""));
} else {
    $msg = "Erro ao salvar a matrícula.";
    $type = "danger";
    log_event($pdo, $tableName, 'ERROR', $msg);
}

header("Location: " . BASE_URL . "index.php?m=matriculas&a=list&msg=" . urlencode($msg) . "&type=$type");
exit;
