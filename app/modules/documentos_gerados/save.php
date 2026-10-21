<?php
// app/modules/documentos_gerados/save.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';
require_once __DIR__ . '/../../helpers/DocumentProcessor.php';
require_once __DIR__ . '/../../helpers/list_state.php';

$tableName = '104_documentos_gerados';
$engine = new CrudEngine($pdo, $tableName);
$processor = new DocumentProcessor($pdo);

$id = isset($_POST['id']) ? (int) $_POST['id'] : null;
$data = $_POST;
unset($data['id']);

// Buscar dados do modelo para título e template
$stmtM = $pdo->prepare("SELECT nome, arquivo_template FROM 104_documentos_modelos WHERE id = ?");
$stmtM->execute([$data['modelo_id']]);
$modelo = $stmtM->fetch(PDO::FETCH_ASSOC);
$modeloNome = $modelo['nome'] ?? 'Modelo';
$templateFile = $modelo['arquivo_template'] ?? '';

// Automatizar Título e Nome do Arquivo se estiverem vazios
if (empty($data['titulo']) || empty($data['arquivo_docx'])) {
    $stmtP = $pdo->prepare("SELECT nome FROM 101_pessoas WHERE id = ?");
    $stmtP->execute([$data['pessoa_id']]);
    $pessoaNome = $stmtP->fetchColumn() ?: 'Pessoa';

    $dataHora = date('Ymd_His');

    if (empty($data['titulo'])) {
        $data['titulo'] = "{$modeloNome}_{$pessoaNome}_{$dataHora}";
    }

    if (empty($data['arquivo_docx'])) {
        $data['arquivo_docx'] = slugify($modeloNome) . '_' . slugify($pessoaNome) . '_' . $dataHora . '.docx';
    }
}

// Se for novo e não tiver hash, gera um básico para evitar erros de unique
if (!$id && empty($data['hash'])) {
    $data['hash'] = hash('sha256', uniqid($data['titulo'] ?? '', true));
}

if (!$id && empty($data['criado_por']) && isset($_SESSION['user_id'])) {
    $data['criado_por'] = $_SESSION['user_id'];
}

if (!$id) {
    $data['created_at'] = date('Y-m-d H:i:s');
}

// Resolver variáveis para salvar o histórico JSON
$variables = $processor->resolveVariables((int) $data['modelo_id'], (int) $data['pessoa_id']);
$data['vars_json'] = json_encode($variables, JSON_UNESCAPED_UNICODE);

try {
    // 1. Salvar no banco
    $success = $engine->save($data, $id);
    $lastId = $id ?: $pdo->lastInsertId();

    if ($success) {
        $errorMsg = '';
        // 2. Gerar arquivo físico se houver template
        if (!empty($templateFile)) {
            $templatePath = UPLOADS_DIR . '/' . $templateFile;
            $outputPath = UPLOADS_DIR . '/' . $data['arquivo_docx'];

            $generated = $processor->generate($templatePath, $outputPath, $variables);

            if (!$generated) {
                $errorMsg = "&msg=error_gen";
                log_event($pdo, $tableName, 'ERROR', "Falha ao gerar arquivo físico para ID " . $lastId . ". Verifique se o template é .docx");
            }
        }

        // 3. Sincronizar o documento gerado com a pasta de "Documentos Recebidos" do aluno
        try {
            require_once __DIR__ . '/../../core/services/DocumentoGeradoService.php';
            $docService = new DocumentoGeradoService($pdo);
            $docService->sincronizarDocumentoAluno(
                (int) $data['pessoa_id'],
                (int) $data['modelo_id'],
                $data['arquivo_docx'],
                $data['titulo']
            );
        } catch (Throwable $e) {
            error_log('[Método Grisa] Erro ao sincronizar documento gerado individual com o aluno: ' . $e->getMessage());
        }

        log_event($pdo, $tableName, $id ? 'UPDATE' : 'INSERT', ($id ? "Editado" : "Criado") . " registro de documento gerado ID " . $lastId);

        if (isset($_POST['modal']) && $_POST['modal'] == '1') {
            echo "<script>
                if (window.parent && window.parent.closeDocumentoModal) {
                    window.parent.closeDocumentoModal(true);
                } else {
                    window.close();
                }
            </script>";
            exit;
        }

        $msg = 'success';
        if (!empty($errorMsg)) {
            $msg = 'error_gen';
        }
        $dest = list_state_return_destination('documentos_gerados', 'list', ['msg' => $msg]);
        redirect($dest);
    } else {
        echo "Erro ao salvar o registro.";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
