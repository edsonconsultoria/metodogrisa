<?php
// app/modules/documentos_gerados/baixar_lote.php

// Prevenção contra exibição de avisos/erros poluindo o arquivo binário do download
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/config.php';

// Garantir acesso exclusivo a usuários autenticados e administradores
if (!current_user_is_at_least_admin()) {
    http_response_code(403);
    die('Acesso negado.');
}

$ids = isset($_POST['ids']) ? $_POST['ids'] : [];

if (empty($ids) || !is_array($ids)) {
    http_response_code(400);
    die('Nenhum documento selecionado para download.');
}

// Higienizar e filtrar apenas IDs válidos (inteiros positivos)
$ids = array_filter(array_map('intval', $ids), function ($id) {
    return $id > 0;
});

if (empty($ids)) {
    http_response_code(400);
    die('IDs de documentos inválidos.');
}

// 1. Buscar os arquivos no banco de dados de forma parametrizada e segura
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT id, arquivo_docx, titulo FROM 104_documentos_gerados WHERE id IN ($placeholders)");
$stmt->execute($ids);
$docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($docs)) {
    http_response_code(404);
    die('Nenhum arquivo correspondente foi encontrado no banco de dados.');
}

// 2. Validar a extensão ZipArchive do PHP
if (!class_exists('ZipArchive')) {
    http_response_code(500);
    die('Erro: A extensão ZipArchive não está instalada ou ativada no PHP deste servidor.');
}

// 3. Criar caminho para o arquivo ZIP temporário
$zipFilename = 'lote_documentos_' . uniqid() . '.zip';
$zipPath = UPLOADS_DIR . '/' . $zipFilename;

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    die('Erro interno: Não foi possível criar o arquivo ZIP temporário no servidor.');
}

$filesAdded = 0;

// 4. Adicionar os arquivos físicos correspondentes ao ZIP
foreach ($docs as $row) {
    if (empty($row['arquivo_docx'])) {
        continue;
    }

    $filePath = UPLOADS_DIR . '/' . $row['arquivo_docx'];

    if (file_exists($filePath) && is_file($filePath)) {
        // Usa o próprio nome físico do arquivo docx como nome de exibição no ZIP
        $zip->addFile($filePath, $row['arquivo_docx']);
        $filesAdded++;
    }
}

// Fecha o arquivo ZIP para persistir em disco
$zip->close();

if ($filesAdded === 0) {
    // Caso nenhum arquivo DOCX físico exista em disco, remove o ZIP vazio e exibe aviso
    if (file_exists($zipPath)) {
        unlink($zipPath);
    }
    http_response_code(404);
    die('Os registros de documentos selecionados existem, mas os respectivos arquivos físicos .docx não foram localizados na pasta de uploads.');
}

// 5. Enviar cabeçalhos HTTP para forçar o download direto
if (file_exists($zipPath)) {
    // Limpar buffers de saída anteriores para garantir que não haja sujeira no arquivo ZIP
    if (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="documentos_gerados_' . date('Ymd_His') . '.zip"');
    header('Content-Length: ' . filesize($zipPath));
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');

    // Envia o conteúdo do arquivo
    readfile($zipPath);

    // 6. Exclui o arquivo ZIP temporário do servidor imediatamente após o download
    unlink($zipPath);
    exit;
} else {
    http_response_code(500);
    die('Erro ao processar o download do arquivo compactado.');
}
