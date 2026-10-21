<?php
// scratch/db_find_all_file_columns.php
require_once __DIR__ . '/../app/config/config.php';

try {
    $stmt = $pdo->query("SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH 
                         FROM INFORMATION_SCHEMA.COLUMNS 
                         WHERE (COLUMN_NAME LIKE '%arquivo%' OR COLUMN_NAME LIKE '%anexo%' OR COLUMN_NAME LIKE '%file%') 
                           AND TABLE_SCHEMA = '" . DB_NAME . "'");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "=== COLUMNS CONTAINING 'arquivo', 'anexo', OR 'file' ===\n";
    foreach ($rows as $row) {
        print_r($row);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
