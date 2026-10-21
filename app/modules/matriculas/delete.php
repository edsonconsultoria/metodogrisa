<?php
// app/modules/matriculas/delete.php

require_once __DIR__ . '/../../core/CrudEngine.php';

$tableName = '104_matriculas';
$engine = new CrudEngine($pdo, $tableName);

$id = (int) ($_GET['id'] ?? 0);

if ($id && $engine->delete($id)) {
    $msg = "Matrícula removida com sucesso.";
    $type = "success";
    log_event($pdo, $tableName, 'DELETE', "Matrícula ID $id removida.");
} else {
    $msg = "Erro ao remover a matrícula.";
    $type = "danger";
}

header("Location: " . BASE_URL . "index.php?m=matriculas&a=list&msg=" . urlencode($msg) . "&type=$type");
exit;
