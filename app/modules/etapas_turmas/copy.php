<?php
// app/modules/etapas_turmas/copy.php

require_once __DIR__ . '/../../config/config.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    die('ID inválido.');
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM 104_etapas_turmas WHERE id = :id");
$stmt->execute([':id' => $id]);
if ($stmt->fetchColumn() == 0) {
    die('Registro não encontrado para copiar.');
}

$return = isset($_GET['return']) ? '&return=' . urlencode($_GET['return']) : '';
$url = BASE_URL . 'index.php?m=etapas_turmas&a=form_copy&id=' . $id . $return;
header('Location: ' . $url);
exit;
