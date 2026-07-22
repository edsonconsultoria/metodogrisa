<?php
// app/modules/etapas_cronogramas/delete.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/CrudEngine.php';

$tableName = '104_cronograma';
$engine = new CrudEngine($pdo, $tableName);
$primaryKey = $engine->getPrimaryKey();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$returnUrl = isset($_GET['return']) ? $_GET['return'] : '';

if ($id > 0) {
    try {
        if ($engine->delete($id)) {
            log_event($pdo, $tableName, 'DELETE', "Excluído registro de Cronograma ID: $id");
            $redirect = $returnUrl ? ("index.php?" . $returnUrl . "&msg=deleted") : "index.php?m=etapas_cronogramas&a=list&msg=deleted";
            redirect($redirect);
        } else {
            echo "Erro ao excluir o registro.";
        }
    } catch (Exception $e) {
        echo "Erro: " . $e->getMessage();
    }
} else {
    $redirect = $returnUrl ? ("index.php?" . $returnUrl) : "index.php?m=etapas_cronogramas&a=list";
    redirect($redirect);
}
