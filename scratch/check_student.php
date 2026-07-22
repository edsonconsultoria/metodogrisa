<?php
require_once __DIR__ . '/../app/config/config.php';
$stmt = $pdo->prepare("SELECT * FROM 104_alunos WHERE id = ?");
$stmt->execute([18]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);
var_dump($record);
