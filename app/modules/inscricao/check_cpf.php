<?php
/**
 * AJAX: verifica CPF/CNPJ no cadastro local e, se não encontrar,
 * tenta buscar CNPJ na BrasilAPI (apenas para CNPJs).
 */
ob_start();
require_once __DIR__ . '/../../config/config.php';
ob_clean();

header('Content-Type: application/json');

$cpf = preg_replace('/\D/', '', trim($_GET['cpf'] ?? ''));

if (strlen($cpf) !== 11 && strlen($cpf) !== 14) {
    echo json_encode(['encontrado' => false]);
    exit;
}

// Formata para o padrão salvo no banco
if (strlen($cpf) === 14) {
    $cpfFmt = preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $cpf);
} else {
    $cpfFmt = preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $cpf);
}

// Busca no cadastro local
$stmt = $pdo->prepare("
    SELECT nome, cpf_cnpj, nascimento, email, fone_1, fone_2,
           cep, endereco, numero, complemento, bairro, cidade, estado,
           estado_civil, escolaridade, nacionalidade,
           local_nascimento, religiao, profissao_atual
    FROM 101_pessoas
    WHERE cpf_cnpj = :cpf
    LIMIT 1
");
$stmt->execute([':cpf' => $cpfFmt]);
$pessoa = $stmt->fetch(PDO::FETCH_ASSOC);

if ($pessoa) {
    // Formata data para input[type=date]
    if ($pessoa['nascimento'] && strlen($pessoa['nascimento']) > 0) {
        $pessoa['nascimento'] = date('Y-m-d', strtotime($pessoa['nascimento']));
    }
    echo json_encode(['encontrado' => true] + $pessoa);
    exit;
}

// Para CNPJ: tenta BrasilAPI
if (strlen($cpf) === 14) {
    $url = 'https://brasilapi.com.br/api/cnpj/v1/' . $cpf;
    $ctx = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
    $res = @file_get_contents($url, false, $ctx);
    if ($res) {
        $data = json_decode($res, true);
        if ($data && isset($data['razao_social'])) {
            $nome = ucwords(strtolower($data['razao_social']));
            $cep  = preg_replace('/\D/', '', $data['cep'] ?? '');
            $cepFmt = strlen($cep) === 8 ? substr($cep,0,5).'-'.substr($cep,5) : ($data['cep'] ?? '');
            echo json_encode([
                'encontrado' => false,
                'receita' => [
                    'nome'        => $nome,
                    'cpf_cnpj'    => $cpfFmt,
                    'email'       => $data['email'] ?? '',
                    'cep'         => $cepFmt,
                    'endereco'    => ucwords(strtolower($data['logradouro'] ?? '')),
                    'numero'      => $data['numero'] ?? '',
                    'complemento' => ucwords(strtolower($data['complemento'] ?? '')),
                    'bairro'      => ucwords(strtolower($data['bairro'] ?? '')),
                    'cidade'      => ucwords(strtolower($data['municipio'] ?? '')),
                    'estado'      => $data['uf'] ?? '',
                ],
            ]);
            exit;
        }
    }
}

// CPF não encontrado em lugar nenhum
echo json_encode(['encontrado' => false]);
