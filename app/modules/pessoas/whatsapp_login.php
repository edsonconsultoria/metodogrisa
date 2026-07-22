<?php
// app/modules/pessoas/whatsapp_login.php
require_once __DIR__ . '/../../config/config.php';

if (!current_user_is_at_least_admin()) {
    die('Acesso negado.');
}

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    die('ID inválido.');
}

// Busca os dados da pessoa
$stmt = $pdo->prepare("SELECT nome, login, fone_1 FROM 101_pessoas WHERE id = ?");
$stmt->execute([$id]);
$pessoa = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pessoa) {
    die('Pessoa não encontrada.');
}

$login = $pessoa['login'];
if (!$login) {
    die('Esta pessoa não possui um login cadastrado. Por favor, edite o cadastro e defina um login primeiro.');
}

$fone = preg_replace('/\D/', '', (string) $pessoa['fone_1']);
if (strlen($fone) < 10) {
    die('Esta pessoa não possui um telefone válido cadastrado (mínimo 10 dígitos).');
}

// Se o telefone não começar com o código do país, assume 55 (Brasil)
if (strlen($fone) <= 11) {
    $fone = '55' . $fone;
}

// Gera nova senha simples (6 dígitos: letras minúsculas e números)
$chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
$newPassword = '';
for ($i = 0; $i < 6; $i++) {
    $newPassword .= $chars[random_int(0, strlen($chars) - 1)];
}

$hash = password_hash($newPassword, PASSWORD_DEFAULT);

// Salva a nova senha
$stmt = $pdo->prepare("UPDATE 101_pessoas SET senha_hash = ?, alteracao = NOW() WHERE id = ?");
$stmt->execute([$hash, $id]);

// Log da ação
log_event($pdo, 'pessoas', 'ALTERACAO', "Senha resetada e enviada via WhatsApp para ID $id ($login)");

// Monta a mensagem do WhatsApp conforme solicitado
$message = "Acesse a plataforma do Método Grisa no endereço\n\nhttps://eadmetodogrisa.com.br/\n\nlogin: $login\n\nsenha: $newPassword\n\nObrigado por usar nossos seviços.";

$whatsappUrl = "https://wa.me/$fone?text=" . urlencode($message);

// Redireciona para o WhatsApp
header("Location: $whatsappUrl");
exit;
