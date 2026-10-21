<?php
// app/modules/provas/save.php
$_TABLE = '104_provas';
require_once __DIR__ . '/../../core/CrudEngine.php';
$engine = new CrudEngine($pdo, $_TABLE);
$primaryKey = $engine->getPrimaryKey();
$id = $_POST[$primaryKey] ?? null;

$isNew = !$id;

// Dados originais antes de salvar
$oldTurma = null;
if (!$isNew) {
    $old = $engine->getRecord($id);
    $oldTurma = $old['turma'] ?? null;
}

$data = $_POST;
unset($data[$primaryKey]);

try {
    $success = $engine->save($data, $id);
    if ($success) {
        $newId = $id ?: $pdo->lastInsertId();
        $turma = $_POST['turma'];

        // Se for novo ou se a turma mudou, gera as provas para os alunos da turma
        if ($isNew || ($oldTurma != $turma)) {
            // Buscar todos os alunos desta turma
            $stmt = $pdo->prepare("SELECT aluno FROM 104_alunos WHERE turma = ?");
            $stmt->execute([$turma]);
            $alunos = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($alunos as $alunoId) {
                if (!$alunoId) continue;
                
                // Verificar se já existe para evitar duplicidade
                $stmtCheck = $pdo->prepare("SELECT id FROM 104_provas_alunos WHERE prova = ? AND aluno = ?");
                $stmtCheck->execute([$newId, $alunoId]);
                if (!$stmtCheck->fetch()) {
                    $stmtIns = $pdo->prepare("INSERT INTO 104_provas_alunos (prova, aluno, concluida, nota) VALUES (?, ?, 0, 0)");
                    $stmtIns->execute([$newId, $alunoId]);
                }
            }
        }

        log_event($pdo, $_TABLE, $id ? 'UPDATE' : 'INSERT', ($id ? "Editado" : "Criado") . " prova ID $newId e vinculado alunos.");
        $target = $_POST['redirect_to'] ?? "index.php?m={$_TABLE}&a=list&msg=success";
        redirect($target);
    }
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Erro ao salvar: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
