<?php
// scratch/db_schema.php
require_once __DIR__ . '/../app/config/config.php';

try {
    $stmt = $pdo->query("SHOW CREATE TABLE 104_cronograma");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "=== TABLE 104_cronograma ===\n";
    echo $row['Create Table'] . "\n\n";

    // Let's also check other tables just in case, like 104_etapas or 104_etapas_cronogramas
    if (tableExists('104_etapas_cronogramas')) {
        $stmt = $pdo->query("SHOW CREATE TABLE 104_etapas_cronogramas");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "=== TABLE 104_etapas_cronogramas ===\n";
        echo $row['Create Table'] . "\n\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
