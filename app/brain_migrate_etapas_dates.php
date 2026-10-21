<?php
require_once '/Applications/XAMPP/xamppfiles/htdocs/mg/app/config/config.php';

echo "=== MIGRANDO DATAS DAS ETAPAS ===\n";

// Atualizar dia_1 e dia_2 das etapas com base nas datas das aulas associadas no cronograma
$stmt = $pdo->query("
    SELECT id, nome FROM 104_etapas
");
$etapas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$updated = 0;
foreach ($etapas as $e) {
    $eid = $e['id'];
    
    // Obter a menor e a maior data de aula associadas
    $stmtDates = $pdo->prepare("
        SELECT MIN(data) as min_date, MAX(data) as max_date 
        FROM 104_cronograma 
        WHERE etapa = :etapa_id AND data IS NOT NULL AND data <> '0000-00-00'
    ");
    $stmtDates->execute([':etapa_id' => $eid]);
    $dates = $stmtDates->fetch(PDO::FETCH_ASSOC);
    
    if ($dates && $dates['min_date']) {
        $stmtUpdate = $pdo->prepare("
            UPDATE 104_etapas 
            SET dia_1 = :dia_1, dia_2 = :dia_2 
            WHERE id = :id
        ");
        $stmtUpdate->execute([
            ':dia_1' => $dates['min_date'],
            ':dia_2' => $dates['max_date'] ?: $dates['min_date'],
            ':id' => $eid
        ]);
        $updated++;
    }
}

echo "Migração concluída! $updated etapas atualizadas com datas de início e fim válidas.\n";
