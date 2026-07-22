<?php
// app/modules/pessoas/batch_action.php

ob_start();
require_once __DIR__ . '/../../config/config.php';
ob_clean();

header('Content-Type: application/json');

if (!current_user_is_at_least_admin()) {
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}

$action = $_POST['action'] ?? '';
$ids = $_POST['ids'] ?? [];

if (empty($ids) || !is_array($ids)) {
    echo json_encode(['success' => false, 'message' => 'Nenhum registro selecionado.']);
    exit;
}

// Convert all IDs to integers
$ids = array_map('intval', $ids);
$idsStr = implode(',', $ids);

try {
    if ($action === 'delete') {
        $successCount = 0;
        $failedIds = [];
        $failedNames = [];

        foreach ($ids as $id) {
            try {
                $stmtName = $pdo->prepare("SELECT nome, eh_usuario, login, perfil FROM 101_pessoas WHERE id = :id");
                $stmtName->execute([':id' => $id]);
                $person = $stmtName->fetch(PDO::FETCH_ASSOC);

                if ($person) {
                    // Se a pessoa for um usuário do sistema, podemos impedir a exclusão em massa direta ou tratar normalmente.
                    // Para garantir que tudo seja excluído com segurança, procedemos com a exclusão.
                    $stmtDel = $pdo->prepare("DELETE FROM 101_pessoas WHERE id = :id");
                    $stmtDel->execute([':id' => $id]);

                    if ($stmtDel->rowCount() > 0) {
                        $successCount++;
                        log_event($pdo, '101_pessoas', 'EXCLUSAO', json_encode($person, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                    }
                }
            } catch (PDOException $e) {
                $failedIds[] = $id;
                $failedNames[] = ($person['nome'] ?? "ID $id");
            }
        }

        if (empty($failedIds)) {
            echo json_encode(['success' => true, 'message' => "$successCount registros apagados com sucesso."]);
        } else {
            if ($successCount > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => "$successCount registros apagados com sucesso. Não foi possível apagar os seguintes registros pois possuem vínculos com outros cadastros (ex: turmas, alunos, notas): " . implode(', ', $failedNames)
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => "Nenhum registro pôde ser apagado. Os seguintes registros possuem vínculos ativos no sistema: " . implode(', ', $failedNames)
                ]);
            }
        }
    } elseif ($action === 'update') {
        $fieldsToUpdate = [];
        $params = [];

        // Define allowed fields for batch update
        $allowedFields = [
            'nome',
            'razao_social',
            'nascimento',
            'cpf_cnpj',
            'email',
            'cep',
            'endereco',
            'numero',
            'complemento',
            'bairro',
            'cidade',
            'estado',
            'obs',
            'cliente',
            'fornecedor',
            'eh_usuario',
            'login',
            'perfil'
        ];

        foreach ($allowedFields as $field) {
            if (isset($_POST[$field]) && $_POST[$field] !== '') {
                // Special handling for flags/checkboxes if passed as '0' or '1'
                // But typically for batch update, if the user leaves it empty/unselected, we don't update it.
                // We need to differentiate "empty" from "value 0".
                // In our implementation, we'll send only fields that changed.

                $fieldsToUpdate[] = "$field = :$field";
                $params[":$field"] = $_POST[$field];
            }
        }

        if (empty($fieldsToUpdate)) {
            echo json_encode(['success' => false, 'message' => 'Nenhum campo para atualizar.']);
            exit;
        }

        $sql = "UPDATE 101_pessoas SET " . implode(', ', $fieldsToUpdate) . ", alteracao = NOW() WHERE id IN ($idsStr)";
        file_put_contents(__DIR__ . '/debug_batch.log', date('Y-m-d H:i:s') . " - SQL: $sql - Params: " . print_r($params, true) . "\n", FILE_APPEND);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $count = $stmt->rowCount();

        echo json_encode(['success' => true, 'message' => "$count registros atualizados com sucesso."]);

    } else {
        echo json_encode(['success' => false, 'message' => 'Ação inválida.']);
    }

} catch (PDOException $e) {
    $msg = 'Erro no banco de dados: ' . $e->getMessage();
    error_log('[batch_action pessoas] ' . $msg);
    echo json_encode(['success' => false, 'message' => $msg]);
} catch (Exception $e) {
    $msg = 'Erro: ' . $e->getMessage();
    error_log('[batch_action pessoas] ' . $msg);
    echo json_encode(['success' => false, 'message' => $msg]);
}
