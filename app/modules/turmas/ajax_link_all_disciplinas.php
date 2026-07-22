<?php
// app/modules/turmas/ajax_link_all_disciplinas.php

// prevenção a erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json');

$turmaId = isset($_POST['turma_id']) ? (int) $_POST['turma_id'] : 0;

if ($turmaId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID da turma inválido.']);
    exit;
}

try {
    // 1. Verificar se já existem disciplinas vinculadas
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM 104_turmas_disciplinas WHERE turma = ?");
    $stmtCheck->execute([$turmaId]);
    $existingCount = $stmtCheck->fetchColumn();

    if ($existingCount > 0) {
        echo json_encode(['success' => false, 'message' => 'Esta turma já possui disciplinas vinculadas.']);
        exit;
    }

    // 2. Buscar todas as disciplinas disponíveis no sistema
    $stmtDisc = $pdo->query("SELECT id, nome, carga_horaria, professor FROM 104_disciplinas ORDER BY nome ASC");
    $disciplinas = $stmtDisc->fetchAll(PDO::FETCH_ASSOC);

    if (empty($disciplinas)) {
        echo json_encode(['success' => false, 'message' => 'Nenhuma disciplina encontrada no sistema.']);
        exit;
    }

    // 3. Vincular todas
    $stmtInsert = $pdo->prepare("INSERT INTO 104_turmas_disciplinas (turma, disciplina, carga_horaria, professor) VALUES (?, ?, ?, ?)");


    $count = 0;

    foreach ($disciplinas as $disc) {
        $stmtInsert->execute([
            $turmaId,
            $disc['id'],
            $disc['carga_horaria'],
            $disc['professor']
        ]);
        $count++;
    }

    log_event($pdo, '104_turmas_disciplinas', 'INSERT', "Vinculadas todas as $count disciplinas disponíveis para a turma ID: $turmaId");

    echo json_encode(['success' => true, 'count' => $count]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
}
