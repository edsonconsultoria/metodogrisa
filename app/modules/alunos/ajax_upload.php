<?php
// app/modules/alunos/ajax_upload.php
ini_set('display_errors', 0);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido.']);
    exit;
}

if (!isset($_FILES['file'])) {
    echo json_encode(['success' => false, 'message' => 'Nenhum arquivo recebido na requisição (check post_max_size?).']);
    exit;
}

if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $errorMsg = 'Erro no upload: ';
    switch ($_FILES['file']['error']) {
        case UPLOAD_ERR_INI_SIZE:
            $errorMsg .= 'Arquivo excede o limite definido no php.ini (upload_max_filesize).';
            break;
        case UPLOAD_ERR_FORM_SIZE:
            $errorMsg .= 'Arquivo excede o limite definido no formulário HTML.';
            break;
        case UPLOAD_ERR_PARTIAL:
            $errorMsg .= 'O upload foi interrompido antes do fim.';
            break;
        case UPLOAD_ERR_NO_FILE:
            $errorMsg .= 'Nenhum arquivo foi enviado.';
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            $errorMsg .= 'Pasta temporária ausente no servidor.';
            break;
        case UPLOAD_ERR_CANT_WRITE:
            $errorMsg .= 'Falha ao gravar arquivo no disco.';
            break;
        case UPLOAD_ERR_EXTENSION:
            $errorMsg .= 'Uma extensão do PHP interrompeu o upload.';
            break;
        default:
            $errorMsg .= 'Erro desconhecido (' . $_FILES['file']['error'] . ').';
            break;
    }
    echo json_encode(['success' => false, 'message' => $errorMsg]);
    exit;
}


$uploadDir = __DIR__ . '/../../../files/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$fileTmpPath = $_FILES['file']['tmp_name'];
$fileName = $_FILES['file']['name'];
$fileSize = $_FILES['file']['size'];
$fileType = $_FILES['file']['type'];

// Generate unique name: original_name + random_suffix
$fileNameCmps = explode(".", $fileName);
$fileExtension = strtolower(end($fileNameCmps));
$cleanName = preg_replace('/[^a-zA-Z0-9_-]/', '_', reset($fileNameCmps));
$newFileName = $cleanName . '_' . substr(md5(uniqid()), 0, 8) . '.' . $fileExtension;

$destPath = $uploadDir . $newFileName;

if (move_uploaded_file($fileTmpPath, $destPath)) {
    echo json_encode([
        'success' => true,
        'file' => [
            'name' => 'files/' . $newFileName,
            'usrName' => $fileName,
            'size' => $fileSize,
            'type' => $fileType
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao mover o arquivo.']);
}
exit;
