<?php
// app/modules/pessoas/send_password.php

require_once __DIR__ . '/../../config/config.php';

if (!current_user_is_at_least_admin()) {
    die('Acesso negado.');
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    die('ID inválido.');
}

// 1. Buscar dados do usuário
$stmt = $pdo->prepare("SELECT nome, login, fone_1 FROM 101_pessoas WHERE id = :id");
$stmt->execute([':id' => $id]);
$user = $stmt->fetch();

if (!$user) {
    die('Usuário não encontrado.');
}

$login = $user['login'];
$fone = $user['fone_1'];

if (!$login) {
    die('Este usuário não possui um login cadastrado.');
}

if (!$fone) {
    die('Este usuário não possui um telefone cadastrado para o envio via WhatsApp.');
}

// 2. Gerar nova senha (3 letras minúsculas e 3 números)
function generateRandomPassword()
{
    $letters = 'abcdefghijklmnopqrstuvwxyz';
    $numbers = '0123456789';
    $passwordArr = [];

    for ($i = 0; $i < 3; $i++) {
        $passwordArr[] = $letters[rand(0, strlen($letters) - 1)];
        $passwordArr[] = $numbers[rand(0, strlen($numbers) - 1)];
    }

    shuffle($passwordArr);
    return implode('', $passwordArr);
}

$newPassword = generateRandomPassword();
$senhaHash = password_hash($newPassword, PASSWORD_DEFAULT);

// 3. Atualizar no banco de dados
try {
    $updateStmt = $pdo->prepare("UPDATE 101_pessoas SET senha_hash = :hash, alteracao = NOW() WHERE id = :id");
    $updateStmt->execute([
        ':hash' => $senhaHash,
        ':id' => $id
    ]);

    // Logar evento
    log_event($pdo, 'pessoas', 'ALTERACAO', "Nova senha gerada via WhatsApp para ID $id ($login)");

} catch (PDOException $e) {
    die('Erro ao atualizar senha: ' . $e->getMessage());
}

// 4. Preparar mensagem de WhatsApp
$message = "Olá " . $user['nome'] . "! Seguem seus dados de acesso ao sistema:\n\n*Login:* $login\n*Senha:* $newPassword\n\nAcesse em: " . (defined('BASE_URL_CLIENTE') ? BASE_URL_CLIENTE : 'https://eadmetodogrisa.com.br/');

$cleanPhone = preg_replace('/\D/', '', $fone);
if (strlen($cleanPhone) === 11 || strlen($cleanPhone) === 10) {
    $cleanPhone = '55' . $cleanPhone;
}

$waUrl = "https://wa.me/$cleanPhone?text=" . urlencode($message);

// 5. Redirecionar
header("Location: $waUrl");
exit;
