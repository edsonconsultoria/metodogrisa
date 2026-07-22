<?php
// app/modules/faculdades/delete.php

require_once __DIR__ . '/../../core/CrudEngine.php';

$tableName = '104_faculdades';
$engine = new CrudEngine($pdo, $tableName);

$id = (int) ($_GET['id'] ?? 0);

if ($id && $engine->delete($id)) {
    $msg = "Faculdade removida com sucesso.";
    $type = "success";
    log_event($pdo, $tableName, 'DELETE', "Faculdade ID $id removida.");
} else {
    $msg = "Erro ao remover a faculdade.";
    $type = "danger";
}

header("Location: " . BASE_URL . "index.php?m=faculdades&a=list&msg=" . urlencode($msg) . "&type=$type");
exit;
