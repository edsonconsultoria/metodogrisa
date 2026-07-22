<?php
// app/modules/101_pessoas/delete.php

require_once __DIR__ . '/../../config/config.php';

if (!current_user_is_at_least_admin()) {
    die('Acesso negado.');
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    die('ID inválido.');
}

$returnUrl = list_state_return_destination('101_pessoas', 'list');

try {
    $beforeStmt = $pdo->prepare("SELECT * FROM 101_pessoas WHERE id = :id");
    $beforeStmt->execute([':id' => $id]);
    $before = $beforeStmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("DELETE FROM 101_pessoas WHERE id = :id");
    $stmt->execute([':id' => $id]);

    if ($before) {
        log_event($pdo, '101_pessoas', 'EXCLUSAO', json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    header('Location: ' . $returnUrl);
    exit;
} catch (PDOException $e) {
    echo 'Não foi possível excluir. Talvez haja vínculos com outros registros.<br>';
    echo 'Erro: ' . $e->getMessage();
}
