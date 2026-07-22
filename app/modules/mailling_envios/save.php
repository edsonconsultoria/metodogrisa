<?php
// app/modules/mailling_envios/save.php

require_once __DIR__ . '/../../config/config.php';

if (!current_user_is_at_least_admin()) {
    die('Acesso negado.');
}
require_once __DIR__ . '/../../core/CrudEngine.php';

$tableName = '101_mailling_envios';
$engine = new CrudEngine($pdo, $tableName);
$primaryKey = $engine->getPrimaryKey();
$id = isset($_POST[$primaryKey]) ? (int) $_POST[$primaryKey] : null;

$data = $_POST;
unset($data[$primaryKey]);

// Ajuste para datetime-local
if (!empty($data['enviado'])) {
    $data['enviado'] = str_replace('T', ' ', $data['enviado']) . ':00';
}

try {
    $success = $engine->save($data, $id);
    if ($success) {
        log_event($pdo, $tableName, $id ? 'UPDATE' : 'INSERT', ($id ? "Editado" : "Criado") . " registro de Log de Envio");
        redirect("index.php?m=mailling_envios&a=list&msg=success");
    } else {
        echo "Erro ao salvar o log.";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
