<?php
// app/modules/coordenadores/delete.php

require_once __DIR__ . '/../../core/CrudEngine.php';

$tableName = '104_coordenadores';
$engine = new CrudEngine($pdo, $tableName);

$id = (int) ($_GET['id'] ?? 0);

if ($id && $engine->delete($id)) {
    $msg = "Coordenador removido com sucesso.";
    $type = "success";
    log_event($pdo, $tableName, 'DELETE', "Coordenador ID $id removido.");
} else {
    $msg = "Erro ao remover o coordenador.";
    $type = "danger";
}

header("Location: " . BASE_URL . "index.php?m=coordenadores&a=list&msg=" . urlencode($msg) . "&type=$type");
exit;
