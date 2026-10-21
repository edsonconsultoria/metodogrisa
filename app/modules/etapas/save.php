<?php
// app/modules/etapas/save.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';

$tableName = '104_etapas';
$engine = new CrudEngine($pdo, $tableName);
$primaryKey = $engine->getPrimaryKey();
$id = isset($_POST[$primaryKey]) ? (int) $_POST[$primaryKey] : null;

$data = $_POST;
unset($data[$primaryKey]);

try {
    $turmaId = isset($_POST['turma_id']) ? (int) $_POST['turma_id'] : 0;
    
    $success = $engine->save($data, $id);
    if ($success) {
        $lastId = $id ?: $pdo->lastInsertId();
        
        log_event($pdo, $tableName, $id ? 'UPDATE' : 'INSERT', ($id ? "Editado" : "Criado") . " registro de Etapa");

        // Se houver uma turma vinculada (criação de nova etapa a partir da turma)
        if ($turmaId > 0 && !$id) {
            $stmtLink = $pdo->prepare("INSERT INTO 104_etapas_turmas (etapa, turma, etapa_da_turma) VALUES (?, ?, ?)");
            $stmtLink->execute([$lastId, $turmaId, $_POST['nome']]);
            
            // Redireciona de volta para a turma se houver return
            $return = $_GET['return'] ?? "index.php?m=turmas&a=form&id={$turmaId}&tab=etapas";
            redirect($return);
        }

        redirect("index.php?m=etapas&a=list&msg=success");
    } else {
        echo "Erro ao salvar o registro de Etapa.";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}

