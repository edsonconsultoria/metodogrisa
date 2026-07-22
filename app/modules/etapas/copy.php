<?php
// app/modules/etapas/copy.php

require_once __DIR__ . '/../../config/config.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    die('ID inválido.');
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM 104_etapas WHERE id = :id");
$stmt->execute([':id' => $id]);
if ($stmt->fetchColumn() == 0) {
    die('Registro não encontrado para copiar.');
}

$url = BASE_URL . 'index.php?m=etapas&a=form_copy&id=' . $id;
header('Location: ' . $url);
exit;
