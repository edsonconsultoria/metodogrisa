<?php
// app/helpers/csrf.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Retorna o token CSRF atual ou gera um novo.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Gera um campo HTML input hidden com o token CSRF.
 */
function csrf_field(): string
{
    $token = csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Adiciona o token CSRF como parâmetro de consulta para links.
 */
function csrf_query_param(): string
{
    return '&csrf_token=' . urlencode(csrf_token());
}

/**
 * Valida o token CSRF para requisições de alteração de dados.
 * Lança erro HTTP 403 e encerra a execução em caso de falha de segurança.
 */
function csrf_verify(): void
{
    // Ignora verificação para rotas públicas permitidas em index.php
    $module = strtolower(trim($_GET['m'] ?? ''));
    $action = strtolower(trim($_GET['a'] ?? ''));
    $publicAccess = ($module === 'financeiro' && $action === 'pix');
    
    if ($publicAccess) {
        return;
    }

    $isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');
    
    // Ações GET sensíveis que realizam exclusões, cópias ou alterações críticas de estado
    $criticalGetActions = [
        'delete', 
        'copy', 
        'form_copy', 
        'complete', 
        'reschedule', 
        'complete_action', 
        'batch_action', 
        'send_password',
        'ajax_delete',
        'ajax_send_docs'
    ];
    
    $isCriticalGet = ($_SERVER['REQUEST_METHOD'] === 'GET' && in_array($action, $criticalGetActions, true));

    if ($isPost || $isCriticalGet) {
        $token = '';
        if ($isPost) {
            // Busca o token do POST, GET ou dos headers (caso AJAX)
            $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
            if ($token === '') {
                // $_SERVER é confiável em todos os SAPIs (Apache mod_php, FPM, nginx);
                // getallheaders() pode não retornar headers customizados sob PHP-FPM.
                $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            }
            if ($token === '' && function_exists('getallheaders')) {
                $headers = getallheaders();
                $token = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? '';
            }
        } else {
            $token = $_GET['csrf_token'] ?? '';
        }

        if ($token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            // Define o código de resposta HTTP 403 (Proibido)
            http_response_code(403);
            
            // Retorna um JSON amigável para AJAX, ou mensagem padrão para carregamentos comuns
            $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
                   || (isset($_SERVER['HTTP_ACCEPT']) && strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json') !== false);
            
            if ($isAjax) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode([
                    'success' => false,
                    'error' => 'Sessão expirada ou falha de segurança (CSRF inválido). Recarregue a página e tente novamente.'
                ]);
            } else {
                echo '<!DOCTYPE html>
                <html lang="pt-br">
                <head>
                    <meta charset="utf-8">
                    <title>Erro de Segurança - Método Grisa</title>
                    <meta name="viewport" content="width=device-width, initial-scale=1">
                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
                </head>
                <body class="bg-light d-flex align-items-center justify-content-center" style="min-height: 100vh;">
                    <div class="card shadow-lg p-4 text-center border-0" style="max-width: 460px; border-radius: 12px;">
                        <div class="text-danger mb-3">
                            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="currentColor" class="bi bi-shield-slash" viewBox="0 0 16 16">
                              <path fill-rule="evenodd" d="M1.093 3.093c-.465.42-.75.9-.75 1.407v4.6c0 4.02 3.18 5.768 7.368 6.782a.5.5 0 0 0 .238 0c4.188-1.014 7.368-2.762 7.368-6.782V4.5c0-.507-.285-.987-.75-1.407l-1-.9c-.426-.384-.962-.643-1.53-.783l-4-1a.5.5 0 0 0-.238 0l-4 1c-.568.14-1.104.399-1.53.783zM2.26 4.305c-.173.156-.26.335-.26.495v4.6c0 3.327 2.49 4.8 6 5.61 3.51-.811 6-2.283 6-5.61V4.8c0-.16-.087-.339-.26-.495l-1-.9a1.6 1.6 0 0 0-.962-.486L8 2.056l-3.038.76a1.6 1.6 0 0 0-.962.485zm12.357-1.74a.5.5 0 0 0-.707-.707l-12 12a.5.5 0 1 0 .707.707z"/>
                            </svg>
                        </div>
                        <h4 class="fw-bold mb-2">Ação de Segurança Bloqueada</h4>
                        <p class="text-muted mb-4 small">Esta requisição não pôde ser validada devido a um token de segurança expirado ou inválido (CSRF). Isso protege sua conta contra envios automáticos não autorizados.</p>
                        <div class="d-grid gap-2">
                            <button onclick="window.location.reload();" class="btn btn-primary btn-sm rounded-pill">Recarregar Página</button>
                            <a href="' . BASE_URL . 'index.php" class="btn btn-outline-secondary btn-sm rounded-pill">Ir para a Dashboard</a>
                        </div>
                    </div>
                </body>
                </html>';
            }
            exit;
        }
    }
}
