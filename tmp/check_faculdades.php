<?php
require_once __DIR__ . '/../app/config/config.php';

try {
    $stmt = $pdo->query("DESCRIBE `104_faculdades` ");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($columns, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
