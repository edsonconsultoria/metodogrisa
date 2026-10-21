<?php
// app/modules/etapas_turmas/save.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';
require_once __DIR__ . '/../../helpers/list_state.php';

$tableName = '104_etapas_turmas';
$engine = new CrudEngine($pdo, $tableName);
$primaryKey = $engine->getPrimaryKey();
$id = isset($_POST[$primaryKey]) ? (int) $_POST[$primaryKey] : null;

$data = $_POST;
unset($data[$primaryKey]);

try {
    $etapaId = isset($data['etapa']) ? (int) $data['etapa'] : 0;
    $turmaId = isset($data['turma']) ? (int) $data['turma'] : 0;

    if ($etapaId <= 0 || $turmaId <= 0) {
        throw new Exception('Etapa e turma são obrigatórias.');
    }

    // Validação de duplicidade
    $sqlCheck = "SELECT id FROM $tableName WHERE etapa = ? AND turma = ?";
    $paramsCheck = [$etapaId, $turmaId];
    if ($id) {
        $sqlCheck .= " AND $primaryKey != ?";
        $paramsCheck[] = $id;
    }
    $stmtCheck = $pdo->prepare($sqlCheck);
    $stmtCheck->execute($paramsCheck);
    if ($stmtCheck->fetch()) {
        throw new Exception("Esta etapa já está cadastrada para esta turma.");
    }

    $pdo->beginTransaction();

    $success = $engine->save($data, $id);
    if (!$success) {
        throw new Exception('Erro ao salvar o registro de Etapa/Turma.');
    }

    $notasCriadas = 0;
    $cronogramasCriados = 0;
    $isInsert = !$id;

    if ($isInsert) {
        $stmtDias = $pdo->prepare("SELECT DISTINCT dia FROM 104_horario_padrao WHERE dia IN (1, 2) ORDER BY dia ASC");
        $stmtDias->execute();
        $diasDisponiveis = $stmtDias->fetchAll(PDO::FETCH_COLUMN);
        $qtdAvaliacoes = count($diasDisponiveis);

        if ($qtdAvaliacoes > 0) {
            $stmtAlunos = $pdo->prepare("SELECT id FROM 104_alunos WHERE turma = :turma");
            $stmtAlunos->execute([':turma' => $turmaId]);
            $alunosIds = $stmtAlunos->fetchAll(PDO::FETCH_COLUMN);

            $stmtDisciplinas = $pdo->prepare("SELECT DISTINCT disciplina FROM 104_turmas_disciplinas WHERE turma = :turma");
            $stmtDisciplinas->execute([':turma' => $turmaId]);
            $disciplinasIds = $stmtDisciplinas->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($alunosIds) && !empty($disciplinasIds)) {
                $values = [];
                $params = [];
                $count = 0;

                foreach ($alunosIds as $alunoId) {
                    foreach ($disciplinasIds as $disciplinaId) {
                        for ($i = 0; $i < $qtdAvaliacoes; $i++) {
                            $values[] = "(?, ?, ?, ?)";
                            $params[] = (int) $turmaId;
                            $params[] = (int) $disciplinaId;
                            $params[] = (int) $alunoId;
                            $params[] = 0;
                            $notasCriadas++;
                            $count++;

                            // Executar em lotes de 200 para evitar limites de tamanho de pacote ou placeholders
                            if ($count >= 200) {
                                $sql = "INSERT INTO 104_turmas_notas (turma, disciplina, aluno, nota) VALUES " . implode(', ', $values);
                                $stmt = $pdo->prepare($sql);
                                $stmt->execute($params);
                                $values = [];
                                $params = [];
                                $count = 0;
                            }
                        }
                    }
                }

                if ($count > 0) {
                    $sql = "INSERT INTO 104_turmas_notas (turma, disciplina, aluno, nota) VALUES " . implode(', ', $values);
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                }
            }
        }

        // Criar cronograma automaticamente para novos registros (inclusão/cópia)
        $stmtEtapa = $pdo->prepare("SELECT dia_1, dia_2 FROM 104_etapas WHERE id = :id");
        $stmtEtapa->execute([':id' => $etapaId]);
        $etapaDates = $stmtEtapa->fetch(PDO::FETCH_ASSOC);

        if ($etapaDates) {
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

            if (!empty($horarios)) {
                $temaBase = trim((string) ($data['etapa_da_turma'] ?? ''));
                if ($temaBase === '') {
                    $temaBase = 'Aula';
                }

                $values = [];
                $params = [];
                $ordemAula = 0;

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
                    $params[] = 0;
                    $params[] = null;
                    $params[] = null;
                    $params[] = null;
                    $params[] = 0;
                    $params[] = null;
                    $params[] = null;
                    $params[] = '0';
                    $cronogramasCriados++;
                }

                if (!empty($values)) {
                    $sql = "INSERT INTO 104_cronograma 
                        (etapa, turma, data, inicio, termino, aula, professor_1, professor_2, professor_3, professor_4, coordenador, arquivos, link_para_aula, sem_video)
                        VALUES " . implode(', ', $values);
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                }
            }
        }
    }

    $pdo->commit();

    $logMsg = ($id ? "Editado" : "Criado") . " registro de Etapa/Turma";
    if ($notasCriadas > 0) {
        $logMsg .= " e geradas {$notasCriadas} notas iniciais (dias 1 e 2).";
    }
    if ($cronogramasCriados > 0) {
        $logMsg .= " e geradas {$cronogramasCriados} aulas no cronograma.";
    }

    log_event($pdo, $tableName, $id ? 'UPDATE' : 'INSERT', $logMsg);
    
    $returnUrl = list_state_return_destination('etapas_turmas', 'list');
    $returnUrl .= (strpos($returnUrl, '?') === false ? '?' : '&') . 'msg=success';
    header('Location: ' . $returnUrl);
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Erro: " . $e->getMessage();
}
