<?php
// app/modules/turmas_notas/save.php

require_once __DIR__ . '/../../core/CrudEngine.php';

$tableName = '104_turmas_notas';
$engine = new CrudEngine($pdo, $tableName);

$id = isset($_POST['id']) ? (int) $_POST['id'] : null;
$data = $_POST;

// Lógica de preenchimento automático para primeira nota
if (!$id && !empty($data['aluno']) && !empty($data['turma'])) {
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM 104_turmas_notas WHERE aluno = ? AND turma = ?");
    $stmtCheck->execute([$data['aluno'], $data['turma']]);
    $count = $stmtCheck->fetchColumn();

    if ($count == 0) {
        // Buscar todas as disciplinas da turma
        $stmtDisc = $pdo->prepare("SELECT disciplina FROM 104_turmas_disciplinas WHERE turma = ?");
        $stmtDisc->execute([$data['turma']]);
        $disciplinas = $stmtDisc->fetchAll(PDO::FETCH_COLUMN);

        foreach ($disciplinas as $discId) {
            // Não insere a que está sendo salva agora para evitar duplicidade
            if ($discId != $data['disciplina']) {
                $stmtInsert = $pdo->prepare("INSERT INTO 104_turmas_notas (aluno, turma, disciplina, nota) VALUES (?, ?, ?, ?)");
                $stmtInsert->execute([$data['aluno'], $data['turma'], $discId, 10.0]);
            }
        }
    }
}

if ($engine->save($data, $id)) {
    $msg = $id ? "Registro atualizado com sucesso." : "Registro criado com sucesso.";
    $type = "success";
    log_event($pdo, $tableName, $id ? 'UPDATE' : 'INSERT', $msg . ($id ? " ID $id" : ""));
} else {
    $msg = "Erro ao salvar o registro.";
    $type = "danger";
    log_event($pdo, $tableName, 'ERROR', $msg);
}

$returnParam = $_POST['return'] ?? '';
$redirectUrl = $returnParam
    ? BASE_URL . 'index.php?' . $returnParam . '&msg=' . urlencode($msg) . '&type=' . $type
    : BASE_URL . 'index.php?m=turmas_notas&a=list&msg=' . urlencode($msg) . '&type=' . $type;

header("Location: " . $redirectUrl);
exit;
