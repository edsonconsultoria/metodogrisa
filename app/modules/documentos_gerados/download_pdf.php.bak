<?php
// app/modules/documentos_gerados/download_pdf.php

use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/../../config/config.php';

// Aumentar limites para processamento pesado
ini_set('memory_limit', '1024M');
set_time_limit(600);
ini_set('pcre.backtrack_limit', '5000000');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Log de início para debug
error_log("[PDF Export] --- NOVA TENTATIVA --- ID: " . ($_GET['id'] ?? 'N/A'));

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$id) {
    error_log("[PDF Export] ID não informado.");
    die("ID do documento não informado.");
}

// Verificar se as bibliotecas foram carregadas (Vendor atualizado)
if (!class_exists('\PhpOffice\PhpWord\IOFactory')) {
    die("<b>Erro Crítico:</b> A biblioteca PHPWord não foi encontrada no servidor.<br><br>
         <b>Como resolver:</b> Você precisa subir a pasta <code>vendor/</code> atualizada do seu computador para o servidor, 
         pois novos arquivos foram adicionados para suportar a conversão de Word para PDF.");
}

// 1. Buscar dados do documento gerado
$sql = "SELECT g.* FROM 104_documentos_gerados g WHERE g.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doc) {
    die("Documento não encontrado.");
}

$docxPath = UPLOADS_DIR . '/' . $doc['arquivo_docx'];

if (!file_exists($docxPath)) {
    die("Arquivo Word original não encontrado em: " . $docxPath);
}

// 2. Configurar o PHPWord para exportar PDF usando Dompdf
try {
    error_log("[PDF Export] Carregando DOCX: " . $docxPath);
    $phpWord = \PhpOffice\PhpWord\IOFactory::load($docxPath);

    // Configurar o renderizador de PDF (Dompdf)
    // O PHPWord precisa do nome do renderizador e do caminho para a sua pasta raiz
    $rendererName = \PhpOffice\PhpWord\Settings::PDF_RENDERER_DOMPDF;
    // __DIR__ = .../app/modules/documentos_gerados → subir 3 níveis = raiz do projeto
    $rendererPath = __DIR__ . '/../../../vendor/dompdf/dompdf';

    \PhpOffice\PhpWord\Settings::setPdfRendererName($rendererName);
    \PhpOffice\PhpWord\Settings::setPdfRendererPath($rendererPath);
    error_log("[PDF Export] Renderer path: " . realpath($rendererPath));

    error_log("[PDF Export] Gerando PDF via PHPWord Writer...");

    // Criar o Writer de PDF
    $pdfWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'PDF');

    // Headers para download
    header('Content-Type: application/pdf');
    $filename = slugify($doc['titulo']) . '.pdf';
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    // Log da geração
    log_event($pdo, '104_documentos_gerados', 'PDF_DOWNLOAD', "Download de PDF (Native) ID: " . $id);

    // Enviar para o browser
    while (ob_get_length())
        ob_end_clean();
    $pdfWriter->save('php://output');
    error_log("[PDF Export] PDF enviado com sucesso.");
    exit;

} catch (Exception $e) {
    error_log("[PDF Export] Erro Crítico: " . $e->getMessage());
    die("Erro ao gerar PDF: " . $e->getMessage());
}
