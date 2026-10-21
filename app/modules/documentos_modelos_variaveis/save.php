<?php
// app/modules/documentos_modelos_variaveis/save.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/CrudEngine.php';

$tableName = '104_documentos_modelos_variaveis';
$engine = new CrudEngine($pdo, $tableName);

$id = isset($_POST['id']) ? (int) $_POST['id'] : null;
$modeloId = (int) $_POST['modelo_id'];

$data = $_POST;
unset($data['id']);

try {
    $success = $engine->save($data, $id);
    if ($success) {
        log_event($pdo, $tableName, $id ? 'UPDATE' : 'INSERT', ($id ? "Editada" : "Criada") . " variável para o modelo ID $modeloId");
        redirect("index.php?m=documentos_modelos&a=view&id=$modeloId&msg=success");
    } else {
        echo "Erro ao salvar a variável.";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
