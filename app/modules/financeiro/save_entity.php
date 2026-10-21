<?php
if (!current_user_is_at_least_admin()) {
    http_response_code(403);
    exit('Acesso negado.');
}

require __DIR__ . '/configure.php';

$definitions = [
    'fin_contas' => ['nome', 'tipo_conta', 'banco', 'agencia', 'numero_conta', 'chave_pix', 'status'],
    'fin_categorias' => ['categoria_pai_id', 'nome', 'tipo', 'cor', 'icone', 'status'],
    'fin_lancamentos_caixa' => ['conta_id', 'categoria_id', 'pessoa_id', 'descricao', 'tipo', 'valor', 'data_movimento', 'data_vencimento', 'status', 'recorrente', 'frequencia', 'tipo_recorrencia', 'parcela_atual', 'total_parcelas', 'lancamento_pai_id', 'observacoes']
];
$required = [
    'fin_contas' => ['nome'], 'fin_categorias' => ['nome', 'tipo'],
    'fin_lancamentos_caixa' => ['conta_id', 'categoria_id', 'descricao', 'tipo', 'valor', 'data_movimento']
];

$id = (int) ($_POST['id'] ?? 0);
$data = [];
foreach ($definitions[$_TABLE] as $field) {
    if (array_key_exists($field, $_POST)) {
        $value = is_string($_POST[$field]) ? trim($_POST[$field]) : $_POST[$field];
        $data[$field] = $value === '' ? null : $value;
    }
}
foreach ($required[$_TABLE] as $field) {
    if (!isset($data[$field]) || $data[$field] === null) {
        exit('Preencha todos os campos obrigatórios.');
    }
}
if (isset($data['valor']) && (float) $data['valor'] <= 0) {
    exit('Os valores devem ser maiores que zero.');
}
if ($id > 0) {
    $sets = [];
    foreach ($data as $field => $value) $sets[] = "`{$field}` = :{$field}";
    $data['id'] = $id;
    $stmt = $pdo->prepare("UPDATE `{$_TABLE}` SET " . implode(', ', $sets) . ' WHERE id = :id');
    $stmt->execute($data);
    log_event($pdo, $_TABLE, 'UPDATE', "Editado registro ID {$id}");
} else {
    $fields = array_keys($data);
    $stmt = $pdo->prepare("INSERT INTO `{$_TABLE}` (`" . implode('`,`', $fields) . '`) VALUES (:' . implode(',:', $fields) . ')');
    $stmt->execute($data);
    $id = (int) $pdo->lastInsertId();
    log_event($pdo, $_TABLE, 'INSERT', "Criado registro ID {$id}");
}

$target = $_POST['redirect_to'] ?? "index.php?m={$_TABLE}&a=list&msg=success";
redirect($target);
