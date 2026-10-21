<?php
// app/modules/provas_alunos/save.php
$_TABLE = '104_provas_alunos';
require_once __DIR__ . '/../../core/CrudEngine.php';
$engine = new CrudEngine($pdo, $_TABLE);
$primaryKey = $engine->getPrimaryKey();
$id = $_POST[$primaryKey] ?? null;

$isNew = !$id;

$data = $_POST;
unset($data[$primaryKey]);

try {
    $success = $engine->save($data, $id);
    if ($success) {
        $pa_id = $id ?: $pdo->lastInsertId();
        
        // Recarrega o registro para ter os IDs corretos
        $record = $engine->getRecord($pa_id);
        $provaId = $record['prova'];
        $alunoId = $record['aluno'];

        if ($isNew) {
            // 1. Verificar se é o primeiro aluno desta prova
            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM 104_provas_alunos WHERE prova = ?");
            $stmtCount->execute([$provaId]);
            $count = $stmtCount->fetchColumn();

            if ($count == 1) {
                // É o primeiro! Vamos cadastrar todos os outros alunos da turma desta prova
                
                // Buscar a turma da prova
                $stmtP = $pdo->prepare("SELECT turma FROM 104_provas WHERE id = ?");
                $stmtP->execute([$provaId]);
                $prova = $stmtP->fetch();

                if ($prova && $prova['turma']) {
                    // Buscar todos os alunos da turma
                    $stmtAlunos = $pdo->prepare("SELECT aluno FROM 104_alunos WHERE turma = ? AND aluno != ?");
                    $stmtAlunos->execute([$prova['turma'], $alunoId]);
                    $outrosAlunos = $stmtAlunos->fetchAll(PDO::FETCH_COLUMN);

                    foreach ($outrosAlunos as $outroAlunoId) {
                        if (!$outroAlunoId) continue;
                        
                        // Evitar duplicidade
                        $stmtCheck = $pdo->prepare("SELECT id FROM 104_provas_alunos WHERE prova = ? AND aluno = ?");
                        $stmtCheck->execute([$provaId, $outroAlunoId]);
                        if (!$stmtCheck->fetch()) {
                            $stmtIns = $pdo->prepare("INSERT INTO 104_provas_alunos (prova, aluno, concluida, nota) VALUES (?, ?, 0, 0)");
                            $stmtIns->execute([$provaId, $outroAlunoId]);
                        }
                    }
                }
            }
        }

        log_event($pdo, $_TABLE, $id ? 'UPDATE' : 'INSERT', ($id ? "Editado" : "Criado") . " vínculo aluno-prova e processado automações.");
        $target = $_POST['redirect_to'] ?? "index.php?m={$_TABLE}&a=list&msg=success";
        redirect($target);
    }
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Erro ao salvar: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
