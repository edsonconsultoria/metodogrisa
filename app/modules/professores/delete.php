<?php
// app/modules/professores/delete.php

require_once __DIR__ . '/../../core/CrudEngine.php';

$tableName = '104_professores';
$engine = new CrudEngine($pdo, $tableName);

$id = (int) ($_GET['id'] ?? 0);

if ($id && $engine->delete($id)) {
    $msg = "Professor excluído com sucesso.";
    $type = "success";
    log_event($pdo, $tableName, 'DELETE', "Professor ID $id excluído.");
} else {
    $msg = "Erro ao excluir o professor.";
    $type = "danger";
}

header("Location: " . BASE_URL . "index.php?m=professores&a=list&msg=" . urlencode($msg) . "&type=$type");
exit;
