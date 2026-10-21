<?php
// app/modules/documentos_modelos/save.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';

$tableName = '104_documentos_modelos';
$engine = new CrudEngine($pdo, $tableName);
$primaryKey = $engine->getPrimaryKey();
$id = isset($_POST[$primaryKey]) ? (int) $_POST[$primaryKey] : null;
$copyFrom = isset($_POST['copy_from']) ? (int) $_POST['copy_from'] : null;

$data = $_POST;
unset($data[$primaryKey]);
unset($data['copy_from']);

// Adicionar timestamp de atualização se for edição
if ($id) {
    $data['updated_at'] = date('Y-m-d H:i:s');
}

// Processar Upload do Arquivo Template
if (isset($_FILES['arquivo_template']) && $_FILES['arquivo_template']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['arquivo_template']['tmp_name'];
    $fileName = $_FILES['arquivo_template']['name'];
    $fileNameCmps = explode(".", $fileName);
    $fileExtension = strtolower(end($fileNameCmps));

    $allowedExtensions = ['doc', 'docx'];
    if (in_array($fileExtension, $allowedExtensions)) {
        // Garantir que a pasta de uploads existe
        if (!is_dir(UPLOADS_DIR)) {
            mkdir(UPLOADS_DIR, 0755, true);
        }

        $newFileName = 'template_' . time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '', $fileName);
        $destPath = UPLOADS_DIR . '/' . $newFileName;

        if (move_uploaded_file($fileTmpPath, $destPath)) {
            $data['arquivo_template'] = $newFileName;
        } else {
            die("Erro ao mover o arquivo para a pasta de uploads.");
        }
    } else {
        die("Apenas arquivos .doc e .docx são permitidos.");
    }
} else {
    // Se não enviou arquivo e for edição, remove do array para não sobrescrever com vazio
    if ($id) {
        unset($data['arquivo_template']);
    } elseif ($copyFrom) {
        // Se for cópia e não enviou arquivo novo, copia o arquivo do original e outros campos não presentes no form
        $stmtOrig = $pdo->prepare("SELECT arquivo_template, conteudo_html FROM 104_documentos_modelos WHERE id = ?");
        $stmtOrig->execute([$copyFrom]);
        $origData = $stmtOrig->fetch();

        if ($origData && $origData['arquivo_template'] && file_exists(UPLOADS_DIR . '/' . $origData['arquivo_template'])) {
            $newFileName = 'copy_' . time() . '_' . $origData['arquivo_template'];
            if (copy(UPLOADS_DIR . '/' . $origData['arquivo_template'], UPLOADS_DIR . '/' . $newFileName)) {
                $data['arquivo_template'] = $newFileName;
            }
        }

        if ($origData && !isset($data['conteudo_html']) && !empty($origData['conteudo_html'])) {
            $data['conteudo_html'] = $origData['conteudo_html'];
        }
    }
}

try {
    $success = $engine->save($data, $id);
    if ($success) {
        $newId = $id ?: $pdo->lastInsertId();

        // Se for uma cópia, duplicar as variáveis
        if (!$id && $copyFrom) {
            $stmtVars = $pdo->prepare("SELECT * FROM 104_documentos_modelos_variaveis WHERE modelo_id = ?");
            $stmtVars->execute([$copyFrom]);
            $variaveis = $stmtVars->fetchAll(PDO::FETCH_ASSOC);

            foreach ($variaveis as $v) {
                $vData = $v;
                unset($vData['id']);
                unset($vData['created_at']);
                $vData['modelo_id'] = $newId;

                // Usar nova instância do CrudEngine para as variáveis
                $engineVars = new CrudEngine($pdo, '104_documentos_modelos_variaveis');
                $engineVars->save($vData);
            }
        }

        log_event($pdo, $tableName, $id ? 'UPDATE' : 'INSERT', ($id ? "Editado" : ($copyFrom ? "Copiado" : "Criado")) . " modelo de documento");
        redirect("index.php?m=documentos_modelos&a=list&msg=success");
    } else {
        echo "Erro ao salvar o modelo de documento.";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
