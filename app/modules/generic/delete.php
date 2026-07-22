<?php
// app/modules/generic/delete.php

require_once __DIR__ . '/../../core/CrudEngine.php';

$tableName = $_TABLE ?? $_GET['m'] ?? '';
$id = $_GET['id'] ?? null;

if (!$tableName || !$id) {
    die('Parâmetros inválidos.');
}

if (strpos($tableName, '102_') === 0 && !current_user_is_at_least_admin()) {
    die('Acesso negado.');
}

$engine = new CrudEngine($pdo, $tableName);

try {
    if ($engine->delete($id)) {
        log_event($pdo, $tableName, 'DELETE', "Excluído registro ID $id na tabela $tableName");
        $target = $_GET['return'] ?? "index.php?m={$tableName}&a=list&msg=deleted";
        redirect($target);
    } else {
        echo "Erro ao excluir o registro.";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
