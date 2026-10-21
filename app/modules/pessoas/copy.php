<?php
// app/modules/101_pessoas/copy.php

require_once __DIR__ . '/../../config/config.php';

if (!current_user_is_at_least_admin()) {
    die('Acesso negado.');
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    die('ID inválido.');
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM 101_pessoas WHERE id = :id");
$stmt->execute([':id' => $id]);
if ($stmt->fetchColumn() == 0) {
    die('Registro não encontrado para copiar.');
}

$returnEncoded = $_GET['return'] ?? '';
$url = BASE_URL . 'index.php?m=101_pessoas&a=form_copy&id=' . $id;
if ($returnEncoded !== '') {
    $url .= '&return=' . $returnEncoded;
}

header('Location: ' . $url);
exit;
