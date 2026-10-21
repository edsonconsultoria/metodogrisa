<?php
// scratch/db_find_file.php
require_once __DIR__ . '/../app/config/config.php';

try {
    // Search in 104_cronograma for any reference to this file prefix
    $stmt = $pdo->prepare("SELECT id, arquivos FROM 104_cronograma WHERE arquivos LIKE :query");
    $stmt->execute([':query' => '%20260308_113720_5bad3c95_Manual_%']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "=== FOUND IN 104_cronograma ===\n";
    foreach ($rows as $row) {
        echo "ID: {$row['id']} | " . $row['arquivos'] . "\n";
    }
    
    // Search in any other tables that have file columns
    // like 104_documentos_arquivos or 000_arquivos
    if (tableExists('104_documentos_arquivos')) {
        $stmt = $pdo->prepare("SELECT id, arquivo FROM 104_documentos_arquivos WHERE arquivo LIKE :query");
        $stmt->execute([':query' => '%20260308_113720_5bad3c95_Manual_%']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "=== FOUND IN 104_documentos_arquivos ===\n";
        foreach ($rows as $row) {
            echo "ID: {$row['id']} | " . $row['arquivo'] . "\n";
        }
    }
    
    if (tableExists('000_arquivos')) {
        $stmt = $pdo->prepare("SELECT id, arquivo FROM 000_arquivos WHERE arquivo LIKE :query");
        $stmt->execute([':query' => '%20260308_113720_5bad3c95_Manual_%']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "=== FOUND IN 000_arquivos ===\n";
        foreach ($rows as $row) {
            echo "ID: {$row['id']} | " . $row['arquivo'] . "\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
