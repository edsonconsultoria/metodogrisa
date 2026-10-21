<?php
// app/modules/disciplinas/delete.php

require_once __DIR__ . '/../../core/CrudEngine.php';

$tableName = '104_disciplinas';
$engine = new CrudEngine($pdo, $tableName);

$id = (int) ($_GET['id'] ?? 0);

if ($id && $engine->delete($id)) {
    $msg = "Disciplina excluída com sucesso.";
    $type = "success";
    log_event($pdo, $tableName, 'DELETE', "Disciplina ID $id excluída.");
} else {
    $msg = "Erro ao excluir a disciplina.";
    $type = "danger";
}

header("Location: " . BASE_URL . "index.php?m=disciplinas&a=list&msg=" . urlencode($msg) . "&type=$type");
exit;
