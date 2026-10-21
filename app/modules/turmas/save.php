<?php
// prevenção a erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// app/modules/turmas/save.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';
require_once __DIR__ . '/../../helpers/cronograma_arquivos.php';

$tableName = '104_turmas';
$engine = new CrudEngine($pdo, $tableName);
$primaryKey = $engine->getPrimaryKey();
$id = isset($_POST[$primaryKey]) ? (int) $_POST[$primaryKey] : null;

$data = $_POST;
unset($data[$primaryKey]);

try {
    $arquivosJSON = $_POST['documentos_json'] ?? '';
    $arquivosBase = cronograma_parse_arquivos($arquivosJSON);
    $novosArquivos = cronograma_process_uploaded_files('novos_documentos');
    $arquivosFinal = array_merge($arquivosBase, $novosArquivos);
    $data['documentos'] = cronograma_encode_arquivos($arquivosFinal);
    unset($data['documentos_json']);

    $success = $engine->save($data, $id);
    if ($success) {
        log_event($pdo, $tableName, $id ? 'UPDATE' : 'INSERT', ($id ? "Editado" : "Criado") . " registro de Turma");
        redirect("index.php?m=turmas&a=list&msg=success");
    } else {
        echo "Erro ao salvar o registro de Turma.";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}


