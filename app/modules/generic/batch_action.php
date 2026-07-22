<?php
// app/modules/generic/batch_action.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/CrudEngine.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$tableName = $_POST['table'] ?? $_POST['m'] ?? $_GET['m'] ?? '';

// Se o nome da tabela vier como 'generic' (nome do módulo centralizador), 
// ele deve ser ignorado para evitar erros de tabela inexistente.
if ($tableName === 'generic') {
    $tableName = $_POST['table'] ?? '';
}

if (strpos($tableName, '102_') === 0 && !current_user_is_at_least_admin()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}
$ids = $_POST['ids'] ?? [];

if (empty($tableName)) {
    echo json_encode(['success' => false, 'message' => 'Tabela não especificada.']);
    exit;
}

if (empty($ids) || !is_array($ids)) {
    echo json_encode(['success' => false, 'message' => 'Nenhum registro selecionado.']);
    exit;
}

// Convert all IDs to integers safely
$ids = array_filter(array_map('intval', $ids));
if (empty($ids)) {
    echo json_encode(['success' => false, 'message' => 'IDs inválidos.']);
    exit;
}
$idsStr = implode(',', $ids);

try {
    $engine = new CrudEngine($pdo, $tableName);

    if ($action === 'delete') {
        $count = 0;
        // Proceed with individual deletions to trigger events/logging if added to CrudEngine
        foreach ($ids as $id) {
            if ($engine->delete($id)) {
                $count++;
                log_event($pdo, $tableName, 'DELETE', "Registro ID $id apagado em lote.");
            }
        }
        echo json_encode(['success' => true, 'message' => "$count registros apagados com sucesso."]);

    } elseif ($action === 'update') {
        $fieldsToUpdate = [];
        $params = [];
        $schema = $engine->getSchema();

        foreach ($schema as $col => $info) {
            if ($info['extra'] === 'auto_increment' || $col === 'id' || strpos($col, 'inclusao') !== false || strpos($col, 'alteracao') !== false) {
                continue;
            }
            if (isset($_POST[$col]) && $_POST[$col] !== '') {
                $fieldsToUpdate[] = "`$col` = :$col";
                $params[":$col"] = $_POST[$col];
            }
        }

        if (empty($fieldsToUpdate)) {
            echo json_encode(['success' => false, 'message' => 'Nenhum campo para atualizar.']);
            exit;
        }

        $sql = "UPDATE `$tableName` SET " . implode(', ', $fieldsToUpdate);

        // Add auth-style update timestamp if schema has it
        if (isset($schema['alteracao'])) {
            $sql .= ", `alteracao` = NOW()";
        }

        $sql .= " WHERE `id` IN ($idsStr)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $count = $stmt->rowCount();

        log_event($pdo, $tableName, 'UPDATE', "$count registros atualizados em lote.");
        echo json_encode(['success' => true, 'message' => "$count registros atualizados com sucesso."]);

    } else {
        echo json_encode(['success' => false, 'message' => 'Ação inválida.']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
