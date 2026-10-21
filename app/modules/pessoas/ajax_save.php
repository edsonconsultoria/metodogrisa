<?php
// app/modules/pessoas/ajax_save.php

require_once __DIR__ . '/../../config/config.php';

if (!current_user_is_at_least_admin()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}

// Garantir que a requisição seja POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

$nome = trim($_POST['nome'] ?? '');
if (empty($nome)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'O nome é obrigatório']);
    exit;
}

$razao_social = trim($_POST['razao_social'] ?? '');
$nascimento = trim($_POST['nascimento'] ?? '');
if ($nascimento === '') $nascimento = null;

$cpf_cnpj = trim($_POST['cpf_cnpj'] ?? '');
$rg_ie = trim($_POST['rg_ie'] ?? '');
$nacionalidade = trim($_POST['nacionalidade'] ?? '');
$local_nascimento = trim($_POST['local_nascimento'] ?? '');
$estado_civil = trim($_POST['estado_civil'] ?? '');
$escolaridade = trim($_POST['escolaridade'] ?? '');

$email = trim($_POST['email'] ?? '');
$fone_1 = trim($_POST['fone_1'] ?? '');
$fone_2 = trim($_POST['fone_2'] ?? '');
$fone_3 = trim($_POST['fone_3'] ?? '');

$cep = trim($_POST['cep'] ?? '');
$endereco = trim($_POST['endereco'] ?? '');
$numero = trim($_POST['numero'] ?? '');
$complemento = trim($_POST['complemento'] ?? '');
$bairro = trim($_POST['bairro'] ?? '');
$cidade = trim($_POST['cidade'] ?? '');
$estado = trim($_POST['estado'] ?? '');

$obs = trim($_POST['obs'] ?? '');

try {
    // Verificar se as colunas de auditoria existem (padrão do sistema)
    $hasInclusao = columnExists('101_pessoas', 'inclusao');
    $hasAlteracao = columnExists('101_pessoas', 'alteracao');
    $hasDataInclusao = columnExists('101_pessoas', 'data_inclusao');
    $hasDataAlteracao = columnExists('101_pessoas', 'data_alteracao');

    $fields = [
        'nome' => $nome,
        'razao_social' => $razao_social !== '' ? $razao_social : null,
        'nascimento' => $nascimento,
        'cpf_cnpj' => $cpf_cnpj !== '' ? $cpf_cnpj : null,
        'rg_ie' => $rg_ie !== '' ? $rg_ie : null,
        'nacionalidade' => $nacionalidade !== '' ? $nacionalidade : null,
        'local_nascimento' => $local_nascimento !== '' ? $local_nascimento : null,
        'estado_civil' => $estado_civil !== '' ? $estado_civil : null,
        'escolaridade' => $escolaridade !== '' ? $escolaridade : null,
        'email' => $email !== '' ? $email : null,
        'fone_1' => $fone_1 !== '' ? $fone_1 : null,
        'fone_2' => $fone_2 !== '' ? $fone_2 : null,
        'fone_3' => $fone_3 !== '' ? $fone_3 : null,
        'cep' => $cep !== '' ? $cep : null,
        'endereco' => $endereco !== '' ? $endereco : null,
        'numero' => $numero !== '' ? $numero : null,
        'complemento' => $complemento !== '' ? $complemento : null,
        'bairro' => $bairro !== '' ? $bairro : null,
        'cidade' => $cidade !== '' ? $cidade : null,
        'estado' => $estado !== '' ? $estado : null,
        'obs' => $obs !== '' ? $obs : null,
        'perfil' => 'CLIENTE'
    ];

    $insertColumns = array_keys($fields);
    $insertValues = array_map(function (string $col) {
        return ':' . $col;
    }, $insertColumns);

    $params = [];
    foreach ($fields as $column => $value) {
        $params[':' . $column] = $value;
    }

    if ($hasInclusao) {
        $insertColumns[] = 'inclusao';
        $insertValues[] = 'NOW()';
    }
    if ($hasDataInclusao) {
        $insertColumns[] = 'data_inclusao';
        $insertValues[] = 'NOW()';
    }
    if ($hasAlteracao) {
        $insertColumns[] = 'alteracao';
        $insertValues[] = 'NOW()';
    }
    if ($hasDataAlteracao) {
        $insertColumns[] = 'data_alteracao';
        $insertValues[] = 'NOW()';
    }

    $sql = "INSERT INTO 101_pessoas (" . implode(', ', $insertColumns) . ") VALUES (" . implode(', ', $insertValues) . ")";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $id = $pdo->lastInsertId();

    // Log do evento de inclusão
    log_event($pdo, '101_pessoas', 'INCLUSAO', "Pessoa $nome (ID $id) cadastrada via Modal");

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'id' => $id,
        'nome' => $nome
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erro de Banco: ' . $e->getMessage(), 'sql' => $sql ?? '']);
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erro: ' . $e->getMessage()]);
}
exit;
