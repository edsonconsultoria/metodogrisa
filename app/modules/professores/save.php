<?php
// app/modules/professores/save.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/arquivos.php';

$tableName = '104_professores';
$engine = new CrudEngine($pdo, $tableName);

$id = isset($_POST['id']) ? (int) $_POST['id'] : null;

try {
    // Processar arquivos
    $arquivosJSON = $_POST['arquivos_json'] ?? '[]';
    $arquivosBase = parse_arquivos_json($arquivosJSON);
    $novosArquivos = process_uploaded_files('novos_arquivos');
    $arquivosFinal = array_merge($arquivosBase, $novosArquivos);

    $data = $_POST;
    $data['documentos'] = encode_arquivos_json($arquivosFinal);

    if ($engine->save($data, $id)) {
        $msg = $id ? "Professor atualizado com sucesso." : "Professor criado com sucesso.";
        $type = "success";
        log_event($pdo, $tableName, $id ? 'UPDATE' : 'INSERT', $msg . ($id ? " ID $id" : ""));
    } else {
        $msg = "Erro ao salvar o professor.";
        $type = "danger";
        log_event($pdo, $tableName, 'ERROR', $msg);
    }
} catch (Exception $e) {
    $msg = "Erro: " . $e->getMessage();
    $type = "danger";
}

header("Location: " . BASE_URL . "index.php?m=professores&a=list&msg=" . urlencode($msg) . "&type=$type");
exit;
