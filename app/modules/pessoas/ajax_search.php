<?php
// app/modules/101_pessoas/ajax_search.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/auth.php';

if (!is_logged_in()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}

$term = $_GET['q'] ?? '';
$results = [];

if (strlen($term) >= 2) {
    $sql = "SELECT id, nome FROM 101_pessoas 
            WHERE nome LIKE :q 
            ORDER BY nome ASC 
            LIMIT 20";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':q' => "%$term%"]);
    $pessoas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($pessoas as $p) {
        $results[] = [
            'id' => (string) $p['id'],
            'text' => $p['nome']
        ];
    }
}

// Limpa qualquer saída anterior (erros, warnings) para garantir JSON válido
if (ob_get_length())
    ob_clean();

header('Content-Type: application/json');
echo json_encode(['results' => $results]);
exit;
