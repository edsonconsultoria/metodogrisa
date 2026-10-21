<?php
// app/modules/interessados/save.php

require_once __DIR__ . '/../../config/config.php';

if (!current_user_is_at_least_admin()) {
    die('Acesso negado.');
}
require_once __DIR__ . '/../../core/CrudEngine.php';

$tableName = '101_interessados';
$engine = new CrudEngine($pdo, $tableName);
$primaryKey = $engine->getPrimaryKey();
$id = isset($_POST[$primaryKey]) ? (int) $_POST[$primaryKey] : null;

$data = $_POST;
unset($data[$primaryKey]);

// Ajuste para datetime-local
if (!empty($data['cadastramento'])) {
    $data['cadastramento'] = str_replace('T', ' ', $data['cadastramento']) . ':00';
}

try {
    $success = $engine->save($data, $id);
    if ($success) {
        log_event($pdo, $tableName, $id ? 'UPDATE' : 'INSERT', ($id ? "Editado" : "Criado") . " registro de Interessado");
        redirect("index.php?m=interessados&a=list&msg=success");
    } else {
        echo "Erro ao salvar o registro.";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
