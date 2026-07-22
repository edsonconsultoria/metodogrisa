<?php
// app/config/auth.php
// Rotinas simples de autenticação baseadas na tabela pessoas

if (!defined('REMEMBER_COOKIE_NAME')) {
    define('REMEMBER_COOKIE_NAME', 'icontrole_remember');
}

if (!defined('REMEMBER_TOKEN_TTL')) {
    define('REMEMBER_TOKEN_TTL', 60 * 60 * 24 * 30);
}

function auth_db(): ?PDO
{
    if (function_exists('db')) {
        $pdo = db();
        return $pdo instanceof PDO ? $pdo : null;
    }

    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        return $GLOBALS['pdo'];
    }

    return null;
}

function auth_tokens_ready(): bool
{
    return function_exists('tableExists')
        && tableExists('auth_remember_tokens')
        && auth_db() instanceof PDO;
}

function auth_cookie_path(): string
{
    if (defined('BASE_URL')) {
        $base = rtrim(BASE_URL, '/');
        return $base === '' ? '/' : $base . '/';
    }

    return '/';
}

function auth_cookie_secure(): bool
{
    return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}

function is_mobile_device(): bool
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if ($ua === '') {
        return false;
    }

    return (bool) preg_match('/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i', $ua);
}

function remember_cookie_value(): ?string
{
    $value = $_COOKIE[REMEMBER_COOKIE_NAME] ?? '';
    return $value !== '' ? $value : null;
}

function set_remember_cookie(string $value, int $expiresAt): void
{
    setcookie(REMEMBER_COOKIE_NAME, $value, [
        'expires' => $expiresAt,
        'path' => auth_cookie_path(),
        'secure' => auth_cookie_secure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[REMEMBER_COOKIE_NAME] = $value;
}

function clear_remember_cookie(): void
{
    setcookie(REMEMBER_COOKIE_NAME, '', [
        'expires' => time() - 3600,
        'path' => auth_cookie_path(),
        'secure' => auth_cookie_secure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE[REMEMBER_COOKIE_NAME]);
}

function remember_user_agent_hash(): string
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return hash('sha256', $ua);
}

function create_remember_token(int $userId): void
{
    if (!auth_tokens_ready()) {
        return;
    }

    $pdo = auth_db();
    if (!$pdo) {
        return;
    }

    $expiresAt = time() + REMEMBER_TOKEN_TTL;
    $expiresAtDb = date('Y-m-d H:i:s', $expiresAt);
    $userAgentHash = remember_user_agent_hash();

    for ($attempts = 0; $attempts < 3; $attempts++) {
        $selector = bin2hex(random_bytes(6));
        $validator = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $validator);

        $stmt = $pdo->prepare("
            INSERT INTO auth_remember_tokens (user_id, selector, token_hash, user_agent_hash, last_used_at, expires_at)
            VALUES (:user_id, :selector, :token_hash, :user_agent_hash, NOW(), :expires_at)
        ");

        try {
            $stmt->execute([
                ':user_id' => $userId,
                ':selector' => $selector,
                ':token_hash' => $tokenHash,
                ':user_agent_hash' => $userAgentHash,
                ':expires_at' => $expiresAtDb,
            ]);

            set_remember_cookie($selector . ':' . $validator, $expiresAt);
            return;
        } catch (PDOException $e) {
            // Se houve colisao de selector, tenta de novo.
            if ($attempts >= 2) {
                return;
            }
        }
    }
}

function forget_remember_token(): void
{
    $cookieValue = remember_cookie_value();
    if ($cookieValue === null) {
        return;
    }

    if (auth_tokens_ready()) {
        $parts = explode(':', $cookieValue, 2);
        $selector = $parts[0] ?? '';
        if ($selector !== '') {
            $pdo = auth_db();
            if ($pdo) {
                $stmt = $pdo->prepare("DELETE FROM auth_remember_tokens WHERE selector = :selector");
                $stmt->execute([':selector' => $selector]);
            }
        }
    }

    clear_remember_cookie();
}

function auto_login_from_remember(): void
{
    if (is_logged_in() || !auth_tokens_ready()) {
        return;
    }

    $cookieValue = remember_cookie_value();
    if ($cookieValue === null || strpos($cookieValue, ':') === false) {
        clear_remember_cookie();
        return;
    }

    [$selector, $validator] = explode(':', $cookieValue, 2);
    if ($selector === '' || $validator === '') {
        clear_remember_cookie();
        return;
    }

    $pdo = auth_db();
    if (!$pdo) {
        return;
    }

    $stmt = $pdo->prepare("
        SELECT
            t.id AS token_id,
            t.user_id,
            t.token_hash,
            t.user_agent_hash,
            t.expires_at,
            u.nome,
            u.login,
            u.perfil
        FROM auth_remember_tokens t
        JOIN 101_pessoas u ON u.id = t.user_id
        WHERE t.selector = :selector
        LIMIT 1
    ");
    $stmt->execute([':selector' => $selector]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        clear_remember_cookie();
        return;
    }

    if (strtotime($row['expires_at']) < time()) {
        $pdo->prepare("DELETE FROM auth_remember_tokens WHERE id = :id")
            ->execute([':id' => $row['token_id']]);
        clear_remember_cookie();
        return;
    }

    if (!hash_equals($row['user_agent_hash'], remember_user_agent_hash())) {
        $pdo->prepare("DELETE FROM auth_remember_tokens WHERE id = :id")
            ->execute([':id' => $row['token_id']]);
        clear_remember_cookie();
        return;
    }

    $validatorHash = hash('sha256', $validator);
    if (!hash_equals($row['token_hash'], $validatorHash)) {
        $pdo->prepare("DELETE FROM auth_remember_tokens WHERE id = :id")
            ->execute([':id' => $row['token_id']]);
        clear_remember_cookie();
        return;
    }

    login_user([
        'id' => (int) $row['user_id'],
        'nome' => $row['nome'],
        'login' => $row['login'],
        'perfil' => $row['perfil'],
    ]);

    $newValidator = bin2hex(random_bytes(32));
    $newHash = hash('sha256', $newValidator);
    $newExpiresAt = time() + REMEMBER_TOKEN_TTL;
    $newExpiresAtDb = date('Y-m-d H:i:s', $newExpiresAt);

    $pdo->prepare("
        UPDATE auth_remember_tokens
        SET token_hash = :token_hash, last_used_at = NOW(), expires_at = :expires_at
        WHERE id = :id
    ")->execute([
                ':token_hash' => $newHash,
                ':expires_at' => $newExpiresAtDb,
                ':id' => $row['token_id'],
            ]);

    set_remember_cookie($selector . ':' . $newValidator, $newExpiresAt);
}

/**
 * Retorna usuário logado ou null.
 */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/**
 * Verifica se há usuário autenticado.
 */
function is_logged_in(): bool
{
    return !empty($_SESSION['user']);
}

/**
 * Efetua login e guarda dados mínimos na sessão.
 */
function login_user(array $user): void
{
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'nome' => $user['nome'],
        'login' => $user['login'],
        'perfil' => $user['perfil'],
    ];
}

/**
 * Verifica se o usuário logado é SUPERADMIN.
 */
function current_user_is_superadmin(): bool
{
    $user = current_user();
    return $user !== null && isset($user['perfil']) && strtoupper($user['perfil']) === 'SUPERADMIN';
}

/**
 * Verifica se o usuário logado é SUPERADMIN ou ADMIN.
 */
function current_user_is_at_least_admin(): bool
{
    $user = current_user();
    if ($user === null || !isset($user['perfil'])) {
        return false;
    }
    $perfil = strtoupper($user['perfil']);
    return $perfil === 'SUPERADMIN' || $perfil === 'ADMIN';
}

/**
 * Verifica se o usuário logado é PROFESSOR.
 */
function current_user_is_professor(): bool
{
    $user = current_user();
    return $user !== null && isset($user['perfil']) && strtoupper($user['perfil']) === 'PROFESSOR';
}

/**
 * Verifica se o usuário logado é ALUNO.
 */
function current_user_is_aluno(): bool
{
    $user = current_user();
    return $user !== null && isset($user['perfil']) && strtoupper($user['perfil']) === 'ALUNO';
}

/**
 * Requer autenticação; redireciona para login se não houver usuário.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        auto_login_from_remember();
    }

    if (!is_logged_in()) {
        $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
        $currentUrl = $_SERVER['REQUEST_URI'] ?? '';
        // Evita loop de redirecionamento se já estiver no login
        if (strpos($currentUrl, '/login.php') !== false) {
            $currentUrl = '';
        }

        $redirectParam = $currentUrl ? '?redirect=' . urlencode($currentUrl) : '';
        header('Location: ' . $base . '/login.php' . $redirectParam);
        exit;
    }
}

/**
 * Faz logout limpando a sessão.
 */
function logout_user(): void
{
    forget_remember_token();
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}
