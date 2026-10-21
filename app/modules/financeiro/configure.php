<?php
$financeTable = $_TABLE ?? $module ?? ($_GET['m'] ?? '');
$allowedFinanceTables = ['fin_contas', 'fin_categorias', 'fin_lancamentos_caixa'];
if (!in_array($financeTable, $allowedFinanceTables, true)) {
    throw new RuntimeException('Tabela financeira inválida.');
}

$_TABLE = $financeTable;
$_READONLY_FIELDS = ['created_at', 'updated_at'];
$_CHECKBOX_FIELDS = [];
$_LOOKUPS = [];
$_STATIC_OPTIONS = [];
$_LABELS = [
    'categoria_pai_id' => 'Categoria pai', 'tipo_conta' => 'Tipo de conta',
    'numero_conta' => 'Número da conta', 'chave_pix' => 'Chave PIX',
    'conta_id' => 'Conta', 'categoria_id' => 'Categoria', 'pessoa_id' => 'Pessoa',
    'data_movimento' => 'Data do movimento', 'data_vencimento' => 'Vencimento',
    'tipo_recorrencia' => 'Tipo de recorrência',
    'parcela_atual' => 'Parcela atual', 'total_parcelas' => 'Total de parcelas',
    'lancamento_pai_id' => 'Lançamento de origem', 'created_at' => 'Criado em',
    'updated_at' => 'Atualizado em'
];

$statusAtivo = ['ativo' => 'Ativo', 'inativo' => 'Inativo'];
$simNao = ['0' => 'Não', '1' => 'Sim'];

switch ($financeTable) {
    case 'fin_contas':
        $_LIST_COLUMNS = ['id', 'nome', 'tipo_conta', 'banco', 'status'];
        $_STATIC_OPTIONS = [
            'tipo_conta' => ['corrente' => 'Corrente', 'poupanca' => 'Poupança', 'investimento' => 'Investimento', 'dinheiro' => 'Dinheiro', 'outros' => 'Outros'],
            'status' => $statusAtivo
        ];
        break;
    case 'fin_categorias':
        $_LIST_COLUMNS = ['id', 'nome', 'tipo', 'categoria_pai_id', 'status'];
        $_LOOKUPS = ['categoria_pai_id' => ['table' => 'fin_categorias', 'label' => 'nome']];
        $_STATIC_OPTIONS = ['tipo' => ['receita' => 'Receita', 'despesa' => 'Despesa'], 'status' => $statusAtivo];
        break;
    case 'fin_lancamentos_caixa':
        $_LIST_COLUMNS = ['id', 'data_movimento', 'descricao', 'conta_id', 'categoria_id', 'tipo', 'valor', 'status'];
        $_MAX_COLUMNS = 7;
        $_LOOKUPS = [
            'conta_id' => ['table' => 'fin_contas', 'label' => 'nome'],
            'categoria_id' => ['table' => 'fin_categorias', 'label' => 'nome'],
            'pessoa_id' => ['table' => '101_pessoas', 'label' => 'nome'],
            'lancamento_pai_id' => ['table' => 'fin_lancamentos_caixa', 'label' => 'descricao']
        ];
        $_STATIC_OPTIONS = [
            'tipo' => ['receita' => 'Receita', 'despesa' => 'Despesa', 'transferencia' => 'Transferência'],
            'status' => ['pago' => 'Pago', 'pendente' => 'Pendente', 'cancelado' => 'Cancelado'],
            'recorrente' => $simNao,
            'frequencia' => ['diario' => 'Diário', 'semanal' => 'Semanal', 'mensal' => 'Mensal', 'anual' => 'Anual'],
            'tipo_recorrencia' => ['fixo' => 'Fixo', 'parcelado' => 'Parcelado']
        ];
        break;
}
