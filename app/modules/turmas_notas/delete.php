<?php
// app/modules/turmas_notas/delete.php

require_once __DIR__ . '/../../core/CrudEngine.php';

$tableName = '104_turmas_notas';
$engine = new CrudEngine($pdo, $tableName);

$id = (int) ($_GET['id'] ?? 0);

if ($id && $engine->delete($id)) {
    $msg = "Registro excluído com sucesso.";
    $type = "success";
    log_event($pdo, $tableName, 'DELETE', "Registro ID $id excluído.");
} else {
    $msg = "Erro ao excluir o registro.";
    $type = "danger";
}

header("Location: " . BASE_URL . "index.php?m=turmas_notas&a=list&msg=" . urlencode($msg) . "&type=$type");
exit;
