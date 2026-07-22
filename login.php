<?php
// public/login.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/app/config/config.php';
require __DIR__ . '/app/config/auth.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$action = $_GET['action'] ?? 'login';
$msgRecuperacao = '';
$tipoMsg = '';

try {
  // Logout
  if (isset($_GET['logout'])) {
    if (is_logged_in()) {
      log_event($pdo, 'pessoas', 'LOGOUT', 'Desconectado do sistema');
    }
    logout_user();
    header('Location: ' . BASE_URL . 'login.php');
    exit;
  }

  // Se já logado, redireciona para o destino original ou home
  auto_login_from_remember();
  if (is_logged_in()) {
    $redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? '';
    header('Location: ' . (!empty($redirect) ? $redirect : BASE_URL . 'index.php'));
    exit;
  }

  $erro = '';

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? 'login';

    if ($postAction === 'recuperar') {
      $email = trim($_POST['email'] ?? '');
      if (!empty($email)) {
        $stmt = $pdo->prepare("SELECT id, nome, email FROM 101_pessoas WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $userRec = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($userRec) {
          $novaSenha = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()'), 0, 10);
          $hash = password_hash($novaSenha, PASSWORD_DEFAULT);

          $pdo->prepare("UPDATE 101_pessoas SET senha_hash = :hash WHERE id = :id")
            ->execute([':hash' => $hash, ':id' => $userRec['id']]);

          log_event($pdo, 'login', 'RECUPERAR_SENHA', 'Usuário solicitou nova senha: ' . $email);

          require_once __DIR__ . '/vendor/autoload.php';

          $mailConfigs = [
            ['port' => 465, 'secure' => PHPMailer::ENCRYPTION_SMTPS],
            ['port' => 587, 'secure' => PHPMailer::ENCRYPTION_STARTTLS],
            ['port' => 2525, 'secure' => PHPMailer::ENCRYPTION_STARTTLS],
            ['port' => 25, 'secure' => PHPMailer::ENCRYPTION_STARTTLS],
            ['port' => 587, 'secure' => ''],
            ['port' => 25, 'secure' => '']
          ];

          $enviado = false;
          $errosMultiplos = '';

          foreach ($mailConfigs as $cfg) {
            $mail = new PHPMailer(true);
            try {
              $mail->isSMTP();
              $mail->Host = 'mail.eadmetodogrisa.com.br';
              $mail->SMTPAuth = true;
              $mail->Username = 'contato@eadmetodogrisa.com.br';
              $mail->Password = 'D,EgACJ8UWNG';
              if (!empty($cfg['secure'])) {
                $mail->SMTPSecure = $cfg['secure'];
              }
              $mail->Port = $cfg['port'];
              $mail->CharSet = 'UTF-8';
              $mail->Timeout = 10; // Reduzido para não travar muito tempo

              $mail->setFrom('contato@eadmetodogrisa.com.br', 'Método Grisa');
              $mail->addAddress($userRec['email'], $userRec['nome']);

              $mail->isHTML(true);
              $mail->Subject = 'Sua nova senha de acesso - Método Grisa';
              $mail->Body = "Olá <b>{$userRec['nome']}</b>,<br><br>Você solicitou a recuperação de senha.<br><br>Sua nova senha é: <b>{$novaSenha}</b><br><br>Recomendamos que você altere esta senha assim que acessar o sistema.";
              $mail->AltBody = "Olá {$userRec['nome']},\n\nVocê solicitou a recuperação de senha.\n\nSua nova senha é: {$novaSenha}\n\nRecomendamos que você altere esta senha assim que acessar o sistema.";

              $mail->send();
              $enviado = true;
              break; // Sai do loop se enviar com sucesso
            } catch (Exception $e) {
              $errosMultiplos .= "Porta {$cfg['port']}: {$mail->ErrorInfo} | ";
            }
          }

          if ($enviado) {
            $msgRecuperacao = "Uma nova senha segura foi enviada para o seu e-mail.";
            $tipoMsg = "success";
            $action = 'login'; // Volta pro login após sucesso
          } else {
            $msgRecuperacao = "Erro ao enviar o e-mail. Tente novamente mais tarde.";
            $tipoMsg = "danger";
            error_log("Erro PHPMailer (Múltiplas tentativas): " . rtrim($errosMultiplos, ' | '));
          }
        } else {
          $msgRecuperacao = "E-mail não encontrado no sistema.";
          $tipoMsg = "danger";
        }
      } else {
        $msgRecuperacao = "Por favor, informe seu e-mail.";
        $tipoMsg = "warning";
      }
    } elseif ($postAction === 'login') {
      $login = trim($_POST['login'] ?? '');
      $senha = $_POST['senha'] ?? '';

      if ($login !== '' && $senha !== '') {
        // Usuário hardcoded (prioridade)
        $hardcodedUser = [
          'id' => 0,
          'nome' => 'Administrador do Sistema',
          'login' => 'admin',
          'senha_hash' => '$2y$10$0FBfwBV7yuBGKAxkMQvKqOlLhKYBpRMTg.q17sdizZ8/IwP8xeE76',
          'perfil' => 'SUPERADMIN'
        ];

        if ($login === $hardcodedUser['login'] && password_verify($senha, $hardcodedUser['senha_hash'])) {
          login_user($hardcodedUser);
          log_event($pdo, 'login', 'LOGIN_HARDCODED', 'Acesso administrativo via código');

          $redirect = $_POST['redirect'] ?? '';
          header('Location: ' . (!empty($redirect) ? $redirect : BASE_URL . 'index.php'));
          exit;
        }

        $stmt = $pdo->prepare("
              SELECT p.id, p.nome, p.login, p.senha_hash, p.perfil
              FROM 101_pessoas p
              WHERE (p.login = :login_user OR p.email = :login_email)
              LIMIT 1
          ");
        $stmt->execute([
          ':login_user' => $login,
          ':login_email' => $login,
        ]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && !empty($user['senha_hash']) && password_verify($senha, $user['senha_hash'])) {
          login_user($user);
          if (is_mobile_device()) {
            create_remember_token((int) $user['id']);
          }
          // garante que a tabela exista antes de gravar
          log_event($pdo, 'login', 'LOGIN', 'Acesso ao sistema');

          $redirect = $_POST['redirect'] ?? '';
          if (!empty($redirect)) {
            header('Location: ' . $redirect);
          } else {
            header('Location: ' . BASE_URL . 'index.php');
          }
          exit;
        }
      }

      $erro = 'Login ou senha inválidos.';
    }
  }
} catch (Throwable $e) {
  // Catch FATAL errors that usually cause 500
  $erro = 'ERRO CRÍTICO NO LOGIN: ' . $e->getMessage() .
    ' em ' . $e->getFile() . ':' . $e->getLine();
  error_log($erro);
}
?>
<!doctype html>
<html lang="pt-br">

<head>
  <meta charset="utf-8">
  <title>Método Grisa - Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="<?php echo BASE_URL; ?>assets/img/favicon-32x32.png" sizes="32x32" type="image/png">
  <link rel="icon" href="<?php echo BASE_URL; ?>assets/img/favicon-16x16.png" sizes="16x16" type="image/png">
  <link rel="shortcut icon" href="<?php echo BASE_URL; ?>assets/img/favicon.ico">
  <link rel="apple-touch-icon" href="<?php echo BASE_URL; ?>assets/img/apple-touch-icon.png">
  <link rel="manifest" href="<?php echo BASE_URL; ?>manifest.json">

  <!-- Bootstrap CSS (CDN) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    body {
      background-color: #1f2933;
      min-height: 100vh;
      display: flex;
      align-items: center;
    }

    .login-card {
      max-width: 380px;
      margin: 0 auto;
    }

    .logo-box {
      width: 70px;
      height: 70px;
      border-radius: 14px;
      background: #0d1117;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 10px;
      overflow: hidden;
    }

    .logo-box img {
      max-width: 100%;
      max-height: 100%;
      object-fit: contain;
      border-radius: 12px;
    }
  </style>
</head>

<body>
  <div class="container">
    <div class="login-card card shadow-lg border-0">
      <div class="card-body p-4">
        <div class="text-center mb-3">
          <div class="logo-box mx-auto">
            <img src="assets/img/logo.png" alt="Método Grisa">
          </div>
          <h5 class="mb-1">Método Grisa</h5>
          <p class="text-muted small mb-0">Acesso ao sistema</p>
        </div>

        <?php if ($msgRecuperacao): ?>
          <div class="alert alert-<?php echo $tipoMsg; ?> py-2 small">
            <?php echo htmlspecialchars($msgRecuperacao); ?>
          </div>
          <?php
        endif; ?>

        <?php if (!empty($erro)): ?>
          <div class="alert alert-danger py-2 small">
            <?php echo htmlspecialchars($erro); ?>
          </div>
          <?php
        endif; ?>

        <?php if ($action === 'recuperar'): ?>
          <form method="post" autocomplete="off">
            <input type="hidden" name="action" value="recuperar">
            <p class="small text-muted mb-3">Digite seu e-mail abaixo. Enviaremos uma nova senha para você.</p>
            <div class="mb-3">
              <label class="form-label small">E-mail</label>
              <input type="email" name="email" class="form-control" required>
            </div>
            <div class="d-grid mb-3">
              <button type="submit" class="btn btn-primary">
                Enviar nova senha
              </button>
            </div>
            <div class="text-center">
              <a href="?action=login" class="text-decoration-none small text-muted">Voltar para o login</a>
            </div>
          </form>

          <?php
        else: ?>
          <form method="post" autocomplete="off">
            <input type="hidden" name="action" value="login">
            <input type="hidden" name="redirect"
              value="<?php echo htmlspecialchars($_GET['redirect'] ?? $_POST['redirect'] ?? ''); ?>">
            <div class="mb-3">
              <label class="form-label small">Login ou e-mail</label>
              <input type="text" name="login" class="form-control" required>
            </div>

            <div class="mb-3">
              <label class="form-label small">Senha</label>
              <input type="password" name="senha" class="form-control" required>
            </div>

            <div class="d-grid mb-3">
              <button type="submit" class="btn btn-primary">
                Entrar
              </button>
            </div>
            <div class="text-center">
              <a href="?action=recuperar" class="text-decoration-none small text-muted">Esqueceu a senha? Recuperar</a>
            </div>
          </form>
          <?php
        endif; ?>

        <div class="mt-4 pt-3 border-top text-center">
          <p class="small text-muted mb-0">
            Se tiver dificuldade de acessar ou tiver alguma dúvida entre em contato pelo email
            <a href="mailto:contato@eadmetodogrisa.com.br"
              class="text-primary text-decoration-none">contato@eadmetodogrisa.com.br</a>
            ou pelo
            <a href="https://wa.me/5548996804889" target="_blank" class="text-success text-decoration-none fw-bold">
              <i class="bi bi-whatsapp"></i> WhatsApp
            </a>
          </p>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS (CDN) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>