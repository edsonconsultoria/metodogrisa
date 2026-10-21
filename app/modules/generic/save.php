<?php
// app/modules/generic/save.php

require_once __DIR__ . '/../../core/CrudEngine.php';

$tableName = $_TABLE ?? $_GET['m'] ?? '';
if (strpos($tableName, '102_') === 0 && !current_user_is_at_least_admin()) {
    die('Acesso negado.');
}

if ($tableName === '000_audit') {
    die('Alteração não permitida em registros de auditoria.');
}

$engine = new CrudEngine($pdo, $tableName);
$primaryKey = $engine->getPrimaryKey();
$id = $_POST[$primaryKey] ?? null;

$data = $_POST;
unset($data[$primaryKey]);

try {
    $success = $engine->save($data, $id);
    if ($success) {
        log_event($pdo, $tableName, $id ? 'UPDATE' : 'INSERT', ($id ? "Editado" : "Criado") . " registro na tabela $tableName");
        $target = $_POST['redirect_to'] ?? "index.php?m={$tableName}&a=list&msg=success";
        redirect($target);
    } else {
        echo "Erro ao salvar o registro.";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
