<?php
// app/modules/etapas_turmas/generate_lessons.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$etapaId = isset($_GET['etapa']) ? (int) $_GET['etapa'] : 0;
$turmaId = isset($_GET['turma']) ? (int) $_GET['turma'] : 0;
$returnPath = $_GET['return'] ?? 'm=etapas_turmas&a=list';

if ($id <= 0 || $etapaId <= 0 || $turmaId <= 0) {
    echo "ID, Etapa e Turma são obrigatórios.";
    exit;
}

try {
    // Verificar se já existem aulas para esta etapa/turma
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM 104_cronograma WHERE etapa = :etapa AND turma = :turma");
    $stmtCheck->execute([':etapa' => $etapaId, ':turma' => $turmaId]);
    $existingCount = (int) $stmtCheck->fetchColumn();

    if ($existingCount > 0) {
        // Já existem aulas, redireciona para o formulário de nova aula manual
        redirect("index.php?m=etapas_cronogramas&a=form&etapa=$etapaId&turma=$turmaId&return=" . urlencode($returnPath));
        exit;
    }

    // Não existem aulas, gerar automaticamente do horário padrão
    $stmtEtapa = $pdo->prepare("SELECT dia_1, dia_2 FROM 104_etapas WHERE id = :id");
    $stmtEtapa->execute([':id' => $etapaId]);
    $etapaDates = $stmtEtapa->fetch(PDO::FETCH_ASSOC);

    if (!$etapaDates) {
        throw new Exception('Datas da etapa não encontradas.');
    }

    $dia1 = $etapaDates['dia_1'] ?? null;
    $dia2 = $etapaDates['dia_2'] ?? null;

    $stmtHorarios = $pdo->prepare("
        SELECT dia, inicio, termino
        FROM 104_horario_padrao
        WHERE dia IN (1, 2)
        ORDER BY dia ASC, inicio ASC
    ");
    $stmtHorarios->execute();
    $horarios = $stmtHorarios->fetchAll(PDO::FETCH_ASSOC);

    if (empty($horarios)) {
        // Se não houver horário padrão definido, redireciona para o manual
        redirect("index.php?m=etapas_cronogramas&a=form&etapa=$etapaId&turma=$turmaId&return=" . urlencode($returnPath));
        exit;
    }

    // Obter o tema base (Etapa da Turma)
    $stmtEtapaTurma = $pdo->prepare("SELECT etapa_da_turma FROM 104_etapas_turmas WHERE id = :id");
    $stmtEtapaTurma->execute([':id' => $id]);
    $temaBase = (string) $stmtEtapaTurma->fetchColumn();
    if (trim($temaBase) === '') {
        $temaBase = 'Aula';
    }

    $values = [];
    $params = [];
    $ordemAula = 0;
    $cronogramasCriados = 0;

    foreach ($horarios as $horario) {
        $diaHorario = (int) $horario['dia'];
        $dataCronograma = $diaHorario === 1 ? $dia1 : $dia2;
        if (!$dataCronograma || $dataCronograma === '0000-00-00') {
            continue;
        }

        $ordemAula++;
        $values[] = "(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $params[] = $etapaId;
        $params[] = $turmaId;
        $params[] = $dataCronograma;
        $params[] = $horario['inicio'];
        $params[] = $horario['termino'];
        $params[] = $temaBase . ' - Aula ' . $ordemAula;
        $params[] = 0; // professor_1
        $params[] = null; // professor_2
        $params[] = null; // professor_3
        $params[] = null; // professor_4
        $params[] = 0; // coordenador
        $params[] = null; // arquivos
        $params[] = null; // link_para_aula
        $params[] = '0'; // sem_video
        $cronogramasCriados++;
    }

    if (!empty($values)) {
        $sql = "INSERT INTO 104_cronograma 
            (etapa, turma, data, inicio, termino, aula, professor_1, professor_2, professor_3, professor_4, coordenador, arquivos, link_para_aula, sem_video)
            VALUES " . implode(', ', $values);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        log_event($pdo, '104_etapas_turmas', 'UPDATE', "Geradas $cronogramasCriados aulas no cronograma via 'Nova Aula'. ID Etapa/Turma: $id");

        // Redireciona de volta para a aba de aulas
        redirect("index.php?$returnPath&msg=success");
    } else {
        // Se nada foi gerado, redireciona para o manual
        redirect("index.php?m=etapas_cronogramas&a=form&etapa=$etapaId&turma=$turmaId&return=" . urlencode($returnPath));
    }

} catch (Exception $e) {
    echo "Erro ao gerar aulas: " . $e->getMessage();
    exit;
}
