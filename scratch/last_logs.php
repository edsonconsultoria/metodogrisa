<?php
require_once __DIR__ . '/../app/config/config.php';
$stmt = $pdo->query("SELECT * FROM `log` ORDER BY id DESC LIMIT 10");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($logs);
