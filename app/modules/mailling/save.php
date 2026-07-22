<?php
// app/modules/101_mailling/save.php

require_once __DIR__ . '/../../config/config.php';

if (!current_user_is_at_least_admin()) {
    die('Acesso negado.');
}
require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';

$tableName = '101_mailling';
$engine = new CrudEngine($pdo, $tableName);
$primaryKey = $engine->getPrimaryKey();
$id = isset($_POST[$primaryKey]) ? (int) $_POST[$primaryKey] : null;

$data = $_POST;
unset($data[$primaryKey]);

// Tratamento de Uploads Multipos
if (!empty($_FILES['anexo']['name'][0])) {
    $uploadedFiles = [];
    $targetDir = UPLOADS_DIR . '/mailling/';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    foreach ($_FILES['anexo']['name'] as $key => $name) {
        if ($_FILES['anexo']['error'][$key] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['anexo']['tmp_name'][$key];
            // Sanitização do nome do arquivo
            $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
            if (move_uploaded_file($tmpName, $targetDir . $fileName)) {
                $uploadedFiles[] = 'mailling/' . $fileName;
            }
        }
    }

    if (!empty($uploadedFiles)) {
        $filesStr = implode(',', $uploadedFiles);

        // Se for edição e já tiver anexos, talvez queira concatenar. 
        // Mas o comportamento padrão de formulário costuma ser substituir se novos forem enviados, 
        // ou manter os antigos se nenhum novo for enviado.
        $data['anexo'] = $filesStr;
    }
} else {
    // Se não enviou novos arquivos, mantém os que já estavam (se for edição)
    if ($id) {
        $existing = $engine->getRecord($id);
        if ($existing && !empty($existing['anexo'])) {
            $data['anexo'] = $existing['anexo'];
        }
    }
}

try {
    $success = $engine->save($data, $id);
    if ($success) {
        log_event($pdo, $tableName, $id ? 'UPDATE' : 'INSERT', ($id ? "Editado" : "Criado") . " registro de Mailling");
        redirect("index.php?m=mailling&a=list&msg=success");
    } else {
        echo "Erro ao salvar o registro de Mailling.";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
