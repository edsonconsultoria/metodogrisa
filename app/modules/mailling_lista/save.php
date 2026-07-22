<?php
// app/modules/mailling_lista/save.php

require_once __DIR__ . '/../../config/config.php';

if (!current_user_is_at_least_admin()) {
    die('Acesso negado.');
}
require_once __DIR__ . '/../../core/CrudEngine.php';

$tableName = '101_mailling_lista';
$engine = new CrudEngine($pdo, $tableName);
$primaryKey = $engine->getPrimaryKey();
$id = isset($_POST[$primaryKey]) ? (int) $_POST[$primaryKey] : null;

$data = $_POST;
unset($data[$primaryKey]);

try {
    $success = $engine->save($data, $id);
    if ($success) {
        log_event($pdo, $tableName, $id ? 'UPDATE' : 'INSERT', ($id ? "Editada" : "Criada") . " lista de mailing");
        redirect("index.php?m=mailling_lista&a=list&msg=success");
    } else {
        echo "Erro ao salvar a lista.";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
