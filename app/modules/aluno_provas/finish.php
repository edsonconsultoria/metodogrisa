<?php
// app/modules/aluno_provas/finish.php
$user = current_user();
$alunoId = $user['id'];
$pa_id = $_POST['pa_id'] ?? null;
$respostas = $_POST['respostas'] ?? [];

if (!$pa_id) {
    header('Location: index.php?m=aluno_provas&a=list');
    exit;
}

// Buscar dados da prova para segurança
$stmt = $pdo->prepare("
    SELECT pa.*, p.id as prova_id 
    FROM 104_provas_alunos pa 
    JOIN 104_provas p ON p.id = pa.prova 
    WHERE pa.id = ? AND pa.aluno = ?
");
$stmt->execute([$pa_id, $alunoId]);
$pa = $stmt->fetch();

if (!$pa || $pa['concluida']) {
    header('Location: index.php?m=aluno_provas&a=list');
    exit;
}

// 1. Consolidar respostas e calcular total de questões
$stmtQ = $pdo->prepare("
    SELECT pq.questao 
    FROM 104_provas_questoes pq
    JOIN 104_questoes q ON q.id = pq.questao
    WHERE pq.prova = ?
");
$stmtQ->execute([$pa['prova_id']]);
$questoesProva = $stmtQ->fetchAll(PDO::FETCH_COLUMN);
$totalQuestoes = count($questoesProva);

$isAdmin = current_user_is_at_least_admin();

// Se não for admin, salvamos tudo no banco
if (!$isAdmin) {
    try {
        $pdo->beginTransaction();

        // Ler respostas já salvas via AJAX antes de apagar
        // (proteção contra envio automático por timer que bypassa o JS de validação)
        $stmtExistentes = $pdo->prepare("SELECT questao, resposta FROM 104_provas_respostas WHERE prova = ? AND aluno = ?");
        $stmtExistentes->execute([$pa['prova_id'], $alunoId]);
        $respostasAjax = $stmtExistentes->fetchAll(PDO::FETCH_KEY_PAIR);

        $pdo->prepare("DELETE FROM 104_provas_respostas WHERE prova = ? AND aluno = ?")
            ->execute([$pa['prova_id'], $alunoId]);

        $stmtIns = $pdo->prepare("INSERT INTO 104_provas_respostas (prova, aluno, questao, resposta) VALUES (?, ?, ?, ?)");
        foreach ($questoesProva as $qId) {
            if (isset($respostas[$qId]) && $respostas[$qId] !== '' && $respostas[$qId] !== null) {
                // POST tem prioridade (resposta marcada no momento do envio)
                $escolhaId = $respostas[$qId];
            } else {
                // Fallback: usa a última resposta salva via AJAX (evita perda por envio automático do timer)
                $escolhaId = $respostasAjax[$qId] ?? null;
            }
            $stmtIns->execute([$pa['prova_id'], $alunoId, $qId, $escolhaId]);
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erro ao salvar respostas: " . $e->getMessage());
    }
}

// 2. Calcular Acertos
$acertos = 0;
if ($isAdmin) {
    // Modo Simulação: Calcular com base no POST
    foreach ($questoesProva as $qId) {
        $escolhaId = $respostas[$qId] ?? null;
        if ($escolhaId) {
            $stmtCheck = $pdo->prepare("SELECT correta FROM 104_questoes_opcoes WHERE id = ? AND questao = ?");
            $stmtCheck->execute([$escolhaId, $qId]);
            if ($stmtCheck->fetchColumn()) {
                $acertos++;
            }
        }
    }
} else {
    // Alunos Reais: Calcular com base no que foi gravado no banco para garantir consistência
    $stmtAcertos = $pdo->prepare("
        SELECT COUNT(*) 
        FROM 104_provas_respostas pr
        JOIN 104_questoes_opcoes qo ON qo.id = pr.resposta AND qo.questao = pr.questao
        WHERE pr.prova = ? AND pr.aluno = ? AND qo.correta = 1
    ");
    $stmtAcertos->execute([$pa['prova_id'], $alunoId]);
    $acertos = $stmtAcertos->fetchColumn();
}

// 3. Cálculo da nota: Total de 10 pontos distribuídos proporcionalmente
$valorPorQuestao = ($totalQuestoes > 0) ? (10 / $totalQuestoes) : 0;
$nota = $acertos * $valorPorQuestao;
$nota = round($nota, 2); // Garantir 2 casas decimais para o banco (decimal 5,2)

if ($isAdmin) {
    // Modo Simulação: Salvar resultado na sessão para visualização imediata
    $_SESSION['test_concluido'][$pa_id] = [
        'concluida' => 1,
        'nota' => $nota,
        'acertos' => $acertos,
        'total' => $totalQuestoes
    ];
    // Se for admin simulando, precisamos gravar as respostas na sessão para result.php (que as buscará lá no modo simulado)
    $_SESSION['test_respostas'][$pa['prova_id']] = $respostas;

    // Nota: Como no modo simulação não gravamos no banco, o result.php usará a sessão.
    // Mas o result.php original busca do banco. No modo simulado o view.php injeta os dados da sessão.

    header("Location: index.php?m=aluno_provas&a=view&id=$pa_id&msg=success&simulated=1");
    exit;
}

// 4. Finalizar para alunos reais
$stmtUpd = $pdo->prepare("UPDATE 104_provas_alunos SET concluida = 1, nota = ? WHERE id = ?");
$stmtUpd->execute([$nota, $pa_id]);

// Log de conclusão
log_event($pdo, '104_provas_alunos', 'CONCLUSAO', "Aluno $alunoId concluiu a prova {$pa['prova_id']} com nota $nota");

header("Location: index.php?m=aluno_provas&a=view&id=$pa_id&msg=success");
exit;
