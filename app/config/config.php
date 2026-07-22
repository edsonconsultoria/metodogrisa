<?php
// app/config/config.php

// Autoload do Composer (bibliotecas externas)
$composerAutoload = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

// Saída buffering para evitar headers enviados prematuramente (link: header.php começa com HTML)
ob_start();

// Polyfills para funções do PHP 8.0 (Compatibilidade com PHP 7.2.34)
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle)
    {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle)
    {
        return 0 === mb_strpos($haystack, $needle);
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle)
    {
        return $needle !== '' && mb_substr($haystack, -mb_strlen($needle)) === $needle;
    }
}

// Helpers de log
require_once __DIR__ . '/log.php';
require_once __DIR__ . '/../helpers/financeiro.php';

// Inicia sessão se ainda não tiver sido iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Timezone padrão do sistema
date_default_timezone_set('America/Sao_Paulo');

// ================================
// CONFIGURAÇÕES DE BANCO DE DADOS
// ================================
define('DB_HOST', 'eadmetodogrisa.com.br'); // troque pelo host do seu banco
define('DB_NAME', 'eadmetodogrisa_sistema');    // troque pelo nome do seu banco
define('DB_USER', 'eadmetodogrisa_usuario');  // troque pelo usuário do MySQL
define('DB_PASS', '+OUvjajqM_ms');    // troque pela senha do MySQL

// URL base do sistema (subdiretório em relaÇõÇœo ao DOCUMENT_ROOT, ex: /b8/)
$docRoot = isset($_SERVER['DOCUMENT_ROOT'])
    ? rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/')
    : '';
$appRoot = rtrim(str_replace('\\', '/', realpath(dirname(__DIR__, 2))), '/');
$basePath = '/';
if ($docRoot !== '' && strncasecmp($appRoot, $docRoot, strlen($docRoot)) === 0) {
    $relative = substr($appRoot, strlen($docRoot));
    $basePath = $relative === '' ? '/' : $relative . '/';
}
define('BASE_URL', $basePath);
$uploadsDir = $appRoot . '/uploads';
define('UPLOADS_DIR', $uploadsDir);
$baseUrlTrimmed = rtrim(BASE_URL, '/');
$uploadsUrl = $baseUrlTrimmed === '' ? '/uploads' : $baseUrlTrimmed . '/uploads';
define('UPLOADS_URL', $uploadsUrl);
// Chave PIX padrão (configure conforme necessário)
define('PIX_KEY', '71272534987');
define('PIX_HASH_SECRET', 'chave-secreta-oscura');

require_once __DIR__ . '/../helpers/list_state.php';
require_once __DIR__ . '/../helpers/texto.php';
require_once __DIR__ . '/../helpers/csrf.php';

// ================================
// CONEXÃO PDO
// ================================
try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

    // Locale para português
    setlocale(LC_ALL, 'pt_BR', 'pt_BR.utf8', 'pt_BR.UTF-8', 'portuguese');

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // exceções em erros
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // fetch padrão como array associativo
        PDO::ATTR_EMULATE_PREPARES => false,                  // prepared statements nativos
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY','')), time_zone='-03:00'"
    ]);

} catch (PDOException $e) {
    // Em produção, ideal logar isso em arquivo ao invés de exibir
    die('Erro ao conectar ao banco de dados: ' . $e->getMessage());
}

// ================================
// FUNÇÕES AUXILIARES (OPCIONAL)
// ================================

/**
 * Atalho para obter a conexão PDO em qualquer lugar, se quiser usar como função.
 */
function db()
{
    global $pdo;
    return $pdo;
}

/**
 * Redirecionamento simples usando BASE_URL.
 */
function redirect($path)
{
    // Se o caminho já for uma URL completa ou já começar com a BASE_URL, não acrescenta novamente
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
        $url = $path;
    } elseif (BASE_URL !== '/' && strpos($path, BASE_URL) === 0) {
        $url = $path;
    } else {
        $url = rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
    }
    
    header("Location: {$url}");
    exit;
}

/**
 * Verifica se uma coluna existe em uma tabela do banco de dados (cache simples).
 */
function columnExists(string $table, string $column): bool
{
    global $pdo;
    static $cache = [];

    $tableName = preg_replace('/[^a-z0-9_]/i', '', $table);
    $columnName = preg_replace('/[^a-z0-9_]/i', '', $column);
    if ($tableName === '' || $columnName === '') {
        return false;
    }

    $key = "{$tableName}.{$columnName}";
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $sql = "SHOW COLUMNS FROM `{$tableName}` LIKE '{$columnName}'";
    $stmt = $pdo->query($sql);
    $cache[$key] = (bool) $stmt->fetchColumn();
    return $cache[$key];
}

/**
 * Verifica se uma tabela existe no banco de dados (cache simples).
 */
function tableExists(string $table): bool
{
    global $pdo;
    static $cache = [];

    $tableName = preg_replace('/[^a-z0-9_]/i', '', $table);
    if ($tableName === '') {
        return false;
    }

    if (isset($cache[$tableName])) {
        return $cache[$tableName];
    }

    $sql = "SHOW TABLES LIKE '{$tableName}'";
    $stmt = $pdo->query($sql);
    $cache[$tableName] = (bool) $stmt->fetchColumn();
    return $cache[$tableName];
}

// Verifica lançamentos recorrentes ao carregar configurações (entrada no sistema)
if (function_exists('financeiro_verificar_recorrentes')) {
    try {
        financeiro_verificar_recorrentes($pdo);
    } catch (Throwable $e) {
        error_log('[Método Grisa] Erro ao validar recorrentes: ' . $e->getMessage());
    }
} else {
    error_log('[Método Grisa] Função financeiro_verificar_recorrentes indisponível na configuração.');
}
