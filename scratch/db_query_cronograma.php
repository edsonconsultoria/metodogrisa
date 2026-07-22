<?php
// scratch/db_query_cronograma.php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/helpers/cronograma_arquivos.php';

try {
    $stmt = $pdo->prepare("SELECT id, turma, arquivos, aula FROM 104_cronograma WHERE arquivos LIKE :query");
    $stmt->execute([':query' => '%Manual de Metodologia%']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "=== MANUAL DE METODOLOGIA RECORDS ===\n";
    foreach ($rows as $row) {
        echo "ID: {$row['id']} | Turma: {$row['turma']} | Aula: {$row['aula']}\n";
        echo "Arquivos Raw: " . $row['arquivos'] . "\n";
        $parsed = cronograma_parse_arquivos($row['arquivos']);
        echo "Parsed result count: " . count($parsed) . "\n";
        print_r($parsed);
        echo "--------------------------------------------------\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
