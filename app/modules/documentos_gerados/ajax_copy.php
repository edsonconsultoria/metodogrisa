<?php
// app/modules/documentos_gerados/ajax_copy.php
// Cria uma cópia do documento usando o modelo (template) atualizado.

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/DocumentProcessor.php';

// Garante resposta JSON limpa, sem lixo de output buffering anterior
if (ob_get_level()) {
    ob_clean();
}
header('Content-Type: application/json; charset=utf-8');

function json_exit(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_exit(['success' => false, 'message' => 'Método não permitido.'], 405);
}

// Sessão usa $_SESSION['user']['id'] (via login_user() em auth.php)
$currentUser = current_user();
if (!$currentUser) {
    json_exit(['success' => false, 'message' => 'Não autenticado.'], 401);
}

$sourceId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if ($sourceId <= 0) {
    json_exit(['success' => false, 'message' => 'ID inválido.'], 400);
}

try {
    // 1. Buscar documento original com dados do modelo e pessoa
    $stmt = $pdo->prepare(
        "SELECT dg.modelo_id, dg.pessoa_id,
                m.nome AS modelo_nome, m.arquivo_template,
                p.nome AS pessoa_nome
         FROM 104_documentos_gerados dg
         INNER JOIN 104_documentos_modelos m ON m.id = dg.modelo_id
         INNER JOIN 101_pessoas p ON p.id = dg.pessoa_id
         WHERE dg.id = ?
         LIMIT 1"
    );
    $stmt->execute([$sourceId]);
    $origem = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$origem) {
        json_exit(['success' => false, 'message' => 'Documento de origem não encontrado.'], 404);
    }

    $dataHora     = date('Ymd_His');
    $modeloNome   = $origem['modelo_nome'];
    $pessoaNome   = $origem['pessoa_nome'];
    $templateFile = $origem['arquivo_template'];

    // 2. Resolver variáveis com o modelo ATUAL (ignora vars_json do registro original)
    $processor = new DocumentProcessor($pdo);
    $variables = $processor->resolveVariables((int) $origem['modelo_id'], (int) $origem['pessoa_id']);

    $novoTitulo  = "{$modeloNome}_{$pessoaNome}_{$dataHora}";
    $novoArquivo = slugify($modeloNome) . '_' . slugify($pessoaNome) . '_' . $dataHora . '.docx';
    $novoHash    = hash('sha256', uniqid($novoTitulo, true));

    $novoRegistro = [
        'modelo_id'    => (int) $origem['modelo_id'],
        'pessoa_id'    => (int) $origem['pessoa_id'],
        'titulo'       => $novoTitulo,
        'arquivo_docx' => $novoArquivo,
        'vars_json'    => json_encode($variables, JSON_UNESCAPED_UNICODE),
        'hash'         => $novoHash,
        'criado_por'   => (int) $currentUser['id'],
        'created_at'   => date('Y-m-d H:i:s'),
    ];

    // 3. Persistir no banco
    $engine  = new CrudEngine($pdo, '104_documentos_gerados');
    $saved   = $engine->save($novoRegistro, null);
    $novoId  = (int) $pdo->lastInsertId();

    if (!$saved || $novoId <= 0) {
        throw new RuntimeException('Falha ao inserir o registro no banco de dados.');
    }

    // 4. Gerar arquivo físico com template atualizado
    $arquivoGerado = false;
    $avisoTemplate = '';

    if (!empty($templateFile)) {
        $templatePath  = UPLOADS_DIR . '/' . $templateFile;
        $outputPath    = UPLOADS_DIR . '/' . $novoArquivo;
        $arquivoGerado = $processor->generate($templatePath, $outputPath, $variables);

        if (!$arquivoGerado) {
            $avisoTemplate = 'Registro salvo, mas o .docx não pôde ser gerado. Verifique o template.';
            log_event($pdo, '104_documentos_gerados', 'ERROR',
                "Falha ao gerar arquivo na cópia ID={$novoId} (origem ID={$sourceId})");
        }
    } else {
        $avisoTemplate = 'O modelo não possui arquivo template configurado.';
    }

    log_event($pdo, '104_documentos_gerados', 'INSERT',
        "Cópia criada ID={$novoId} a partir de ID={$sourceId} com modelo atualizado");

    json_exit([
        'success'        => true,
        'id'             => $novoId,
        'titulo'         => $novoTitulo,
        'download_url'   => UPLOADS_URL . '/' . $novoArquivo,
        'view_url'       => BASE_URL . 'index.php?m=documentos_gerados&a=view&id=' . $novoId,
        'arquivo_gerado' => $arquivoGerado,
        'aviso'          => $avisoTemplate,
    ]);

} catch (Throwable $e) {
    log_event($pdo, '104_documentos_gerados', 'ERROR',
        "Erro ao copiar documento ID={$sourceId}: " . $e->getMessage());
    json_exit(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()], 500);
}
