<?php
// app/modules/horario_padrao/delete.php

require_once __DIR__ . '/../../core/CrudEngine.php';

$tableName = '104_horario_padrao';
$engine = new CrudEngine($pdo, $tableName);

$id = (int) ($_GET['id'] ?? 0);

if ($id && $engine->delete($id)) {
    $msg = "Horário removido com sucesso.";
    $type = "success";
    log_event($pdo, $tableName, 'DELETE', "Horário ID $id removido.");
} else {
    $msg = "Erro ao remover o horário.";
    $type = "danger";
}

header("Location: " . BASE_URL . "index.php?m=horario_padrao&a=list&msg=" . urlencode($msg) . "&type=$type");
exit;
