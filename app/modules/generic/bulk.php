<?php
// app/modules/generic/bulk.php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php?m=$module&a=list");
    exit;
}

$ids = $_POST['ids'] ?? [];
$actionType = $_POST['action'] ?? '';

if (empty($ids)) {
    $_SESSION['flash_error'] = 'Nenhum registro selecionado.';
    header("Location: index.php?m=$module&a=list");
    exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));

try {
    if ($actionType === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM `{$_TABLE}` WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $_SESSION['flash_success'] = count($ids) . ' registros excluídos com sucesso.';
    } elseif ($actionType === 'update') {
        $field = $_POST['field'] ?? '';
        $value = $_POST['value'] ?? '';

        if (!$field) {
            throw new Exception('Campo não especificado para atualização.');
        }

        // Validação básica do campo (deve estar no schema)
        require_once __DIR__ . '/../../core/CrudEngine.php';
        $engine = new CrudEngine($pdo, $_TABLE);
        $schema = $engine->getSchema();
        if (!isset($schema[$field])) {
            throw new Exception('Campo inválido.');
        }

        $stmt = $pdo->prepare("UPDATE `{$_TABLE}` SET `{$field}` = ? WHERE id IN ($placeholders)");
        $stmt->execute(array_merge([$value], $ids));
        $_SESSION['flash_success'] = count($ids) . ' registros atualizados com sucesso.';
    }
} catch (Exception $e) {
    $_SESSION['flash_error'] = 'Erro ao processar ação em massa: ' . $e->getMessage();
}

$params = $_GET;
$params['a'] = 'list';
if ($actionType === 'delete') $params['msg'] = 'deleted';
if ($actionType === 'update') $params['msg'] = 'success';

$target = "index.php?" . http_build_query($params);
header("Location: $target");
exit;
