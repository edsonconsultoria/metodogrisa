<?php
// app/modules/etapas/cronograma_save.php
// Salva (em lote) toda a grade do cronograma de uma etapa.

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/texto.php';

$etapa = isset($_POST['etapa']) ? (int) $_POST['etapa'] : 0;
if ($etapa <= 0) {
    redirect("index.php?m=etapas&a=list");
}

$dias = $_POST['dia'] ?? [];      // [diaIdx => 'Y-m-d']
$slots = $_POST['slot'] ?? [];    // [diaIdx][slotIdx => ['inicio'=>, 'termino'=>]]
$cells = $_POST['cell'] ?? [];    // [diaIdx][slotIdx][turmaId => ['id'=>, 'aula'=>, 'prof'=>[]]]

$inserts = 0;
$updates = 0;

try {
    $pdo->beginTransaction();

    $stmtUpdFull = $pdo->prepare("
        UPDATE 104_cronograma
        SET data = :data, inicio = :inicio, termino = :termino, aula = :aula,
            professor_1 = :p1, professor_2 = :p2, professor_3 = :p3, professor_4 = :p4,
            sem_video = :sem_video, link_para_aula = :link
        WHERE id = :id AND etapa = :etapa
    ");
    $stmtUpdKeepTime = $pdo->prepare("
        UPDATE 104_cronograma
        SET data = :data, aula = :aula,
            professor_1 = :p1, professor_2 = :p2, professor_3 = :p3, professor_4 = :p4,
            sem_video = :sem_video, link_para_aula = :link
        WHERE id = :id AND etapa = :etapa
    ");
    $stmtIns = $pdo->prepare("
        INSERT INTO 104_cronograma (etapa, turma, data, inicio, termino, aula, professor_1, professor_2, professor_3, professor_4, sem_video, link_para_aula)
        VALUES (:etapa, :turma, :data, :inicio, :termino, :aula, :p1, :p2, :p3, :p4, :sem_video, :link)
    ");

    foreach ($cells as $diaIdx => $slotList) {
        $data = isset($dias[$diaIdx]) ? trim((string) $dias[$diaIdx]) : '';
        if ($data === '') {
            continue;
        }
        foreach ($slotList as $slotIdx => $turmaCells) {
            $inicio = trim((string) ($slots[$diaIdx][$slotIdx]['inicio'] ?? ''));
            $termino = trim((string) ($slots[$diaIdx][$slotIdx]['termino'] ?? ''));
            $hasTime = ($inicio !== '' && $termino !== '');

            foreach ($turmaCells as $turmaId => $cell) {
                $turmaId = (int) $turmaId;
                $rowId = isset($cell['id']) ? (int) $cell['id'] : 0;
                $aula = trim((string) ($cell['aula'] ?? ''));
                $aula = mb_substr($aula, 0, 150);

                // Professores -> até 4 ids distintos
                $profs = [];
                foreach ((array) ($cell['prof'] ?? []) as $p) {
                    $p = (int) $p;
                    if ($p > 0 && !in_array($p, $profs, true)) {
                        $profs[] = $p;
                    }
                }
                $p1 = $profs[0] ?? null;
                $p2 = $profs[1] ?? null;
                $p3 = $profs[2] ?? null;
                $p4 = $profs[3] ?? null;

                $semVideo = !empty($cell['sem_video']) ? '1' : '0';
                $link = $semVideo === '1' ? '' : trim((string) ($cell['link_para_aula'] ?? ''));

                $hasContent = ($aula !== '' || !empty($profs) || $link !== '' || $semVideo === '1');

                if ($rowId > 0) {
                    // Atualiza registro existente
                    if ($hasTime) {
                        $stmtUpdFull->execute([
                            ':data' => $data, ':inicio' => $inicio, ':termino' => $termino, ':aula' => $aula,
                            ':p1' => $p1, ':p2' => $p2, ':p3' => $p3, ':p4' => $p4,
                            ':sem_video' => $semVideo, ':link' => $link,
                            ':id' => $rowId, ':etapa' => $etapa,
                        ]);
                    } else {
                        $stmtUpdKeepTime->execute([
                            ':data' => $data, ':aula' => $aula,
                            ':p1' => $p1, ':p2' => $p2, ':p3' => $p3, ':p4' => $p4,
                            ':sem_video' => $semVideo, ':link' => $link,
                            ':id' => $rowId, ':etapa' => $etapa,
                        ]);
                    }
                    $updates++;
                } elseif ($hasContent && $hasTime) {
                    // Cria novo registro para célula preenchida
                    $stmtIns->execute([
                        ':etapa' => $etapa, ':turma' => $turmaId, ':data' => $data,
                        ':inicio' => $inicio, ':termino' => $termino, ':aula' => $aula,
                        ':p1' => $p1, ':p2' => $p2, ':p3' => $p3, ':p4' => $p4,
                        ':sem_video' => $semVideo, ':link' => $link,
                    ]);
                    $inserts++;
                }
                // Célula nova vazia: ignorada
            }
        }
    }

    $pdo->commit();
    log_event($pdo, '104_cronograma', 'UPDATE', "Cronograma da etapa #{$etapa} salvo (atualizados: {$updates}, novos: {$inserts})");
    redirect("index.php?m=etapas&a=cronograma&id={$etapa}&msg=success");
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo '<div class="alert alert-danger">Erro ao salvar o cronograma: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
