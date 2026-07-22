<?php
// app/modules/provas_alunos/ajax_alunos.php
require_once __DIR__ . '/../../config/config.php';

$provaId = $_GET['prova_id'] ?? null;
if (!$provaId) {
    echo json_encode([]);
    exit;
}

// Buscar a turma da prova
$stmtP = $pdo->prepare("SELECT turma FROM 104_provas WHERE id = ?");
$stmtP->execute([$provaId]);
$prova = $stmtP->fetch();

if (!$prova) {
    echo json_encode([]);
    exit;
}

// Buscar alunos da turma que NÃO tenham essa prova (ou que sejam o aluno atual do registro sendo editado)
$currentPaId = $_GET['current_id'] ?? null;

$sql = "
    SELECT p.id, p.nome 
    FROM 104_alunos a 
    JOIN 101_pessoas p ON p.id = a.aluno 
    WHERE a.turma = ?
    AND (
        a.aluno NOT IN (SELECT aluno FROM 104_provas_alunos WHERE prova = ?)
";

$params = [$prova['turma'], $provaId];

if ($currentPaId) {
    $sql .= " OR a.aluno = (SELECT aluno FROM 104_provas_alunos WHERE id = ?)";
    $params[] = $currentPaId;
}

$sql .= " ) ORDER BY p.nome";

$stmtA = $pdo->prepare($sql);
$stmtA->execute($params);
$alunos = $stmtA->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($alunos);
exit;
