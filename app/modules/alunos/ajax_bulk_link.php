<?php
// app/modules/alunos/ajax_bulk_link.php

// Prevenção a erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Desativar a exibição de erros durante o processamento para não quebrar o JSON
// Mas manter o log ativado no sistema
ini_set('display_errors', 0);

try {
    // Limpar qualquer saída anterior que possa estar no buffer (de config.php etc)
    if (ob_get_length())
        ob_clean();

    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../../core/CrudEngine.php';

    // Garantir que a requisição seja POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }

    $turmaId = (int) ($_POST['turma_id'] ?? 0);
    $rawText = trim($_POST['raw_text'] ?? '');

    if (!$turmaId || empty($rawText)) {
        throw new Exception('Turma e lista de alunos são obrigatórios');
    }

    $lines = explode("\n", $rawText);
    $results = [
        'total' => 0,
        'added_pessoa' => 0,
        'linked' => 0,
        'already_linked' => 0,
        'errors' => []
    ];

    // Audit columns check
    $hasInclusao = columnExists('101_pessoas', 'inclusao');
    $hasDataInclusao = columnExists('101_pessoas', 'data_inclusao');
    $hasAlteracao = columnExists('101_pessoas', 'alteracao');
    $hasDataAlteracao = columnExists('101_pessoas', 'data_alteracao');

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line))
            continue;

        $results['total']++;

        // Parsing simples para evitar problemas com regex e caracteres especiais do traço
        // Tenta separar por " – " (dash), depois por " - " (hifen)
        $nome = '';
        $email = '';

        if (strpos($line, ' – ') !== false) {
            $parts = explode(' – ', $line, 2);
        } elseif (strpos($line, ' - ') !== false) {
            $parts = explode(' - ', $line, 2);
        } else {
            $parts = [$line];
        }

        $nome = trim($parts[0]);
        $email = isset($parts[1]) ? trim($parts[1]) : '';

        if (empty($nome)) {
            $results['errors'][] = "Linha vazia ou nome não encontrado: '$line'";
            continue;
        }

        try {
            $pessoaId = 0;

            // 1. Tentar encontrar por e-mail se houver
            if (!empty($email)) {
                $stmt = $pdo->prepare("SELECT id FROM 101_pessoas WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $pessoaId = (int) $stmt->fetchColumn();
            }

            // 2. Se não encontrou por e-mail, tentar por nome
            if (!$pessoaId) {
                $stmt = $pdo->prepare("SELECT id FROM 101_pessoas WHERE nome = ? LIMIT 1");
                $stmt->execute([$nome]);
                $pessoaId = (int) $stmt->fetchColumn();
            }

            // 3. Se ainda não encontrou, cadastrar nova pessoa
            if (!$pessoaId) {
                $cols = ['nome', 'email', 'perfil'];
                $vals = [':nome', ':email', "'CLIENTE'"];
                $params = [':nome' => $nome, ':email' => $email];

                if ($hasInclusao) {
                    $cols[] = 'inclusao';
                    $vals[] = 'NOW()';
                }
                if ($hasDataInclusao) {
                    $cols[] = 'data_inclusao';
                    $vals[] = 'NOW()';
                }
                if ($hasAlteracao) {
                    $cols[] = 'alteracao';
                    $vals[] = 'NOW()';
                }
                if ($hasDataAlteracao) {
                    $cols[] = 'data_alteracao';
                    $vals[] = 'NOW()';
                }

                $sqlPessoa = "INSERT INTO 101_pessoas (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")";
                $stmtPessoa = $pdo->prepare($sqlPessoa);
                $stmtPessoa->execute($params);
                $pessoaId = (int) $pdo->lastInsertId();
                $results['added_pessoa']++;
            }

            // 4. Vincular à turma
            $stmtCheck = $pdo->prepare("SELECT id FROM 104_alunos WHERE aluno = ? AND turma = ?");
            $stmtCheck->execute([$pessoaId, $turmaId]);
            if ($stmtCheck->fetch()) {
                $results['already_linked']++;
            } else {
                $stmtLink = $pdo->prepare("INSERT INTO 104_alunos (aluno, turma, status) VALUES (?, ?, 'Ativo')");
                $stmtLink->execute([$pessoaId, $turmaId]);
                $results['linked']++;
            }

        } catch (Exception $e) {
            $results['errors'][] = "Erro na linha '$nome': " . $e->getMessage();
        }
    }

    // Saída JSON limpa
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($results);
    exit;

} catch (Throwable $e) {
    if (ob_get_length())
        ob_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    exit;
}
