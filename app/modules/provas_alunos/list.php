<?php
// app/modules/provas_alunos/list.php
$_TABLE = '104_provas_alunos';
$_BULK_ACTIONS = true;

// Definir colunas virtuais para trazer dados relacionados no list genérico
$_VIRTUAL_COLUMNS = [
    'aluno_nome'       => ['expression' => "SELECT p.nome FROM 101_pessoas p WHERE p.id = `t`.`aluno` LIMIT 1",      'searchable' => true],
    'prova_tipo'       => ['expression' => "SELECT pr.tipo FROM 104_provas pr WHERE pr.id = `t`.`prova` LIMIT 1",    'searchable' => true],
    'turma_nome'       => ['expression' => "SELECT tu.nome FROM 104_turmas tu JOIN 104_provas pr ON pr.turma = tu.id WHERE pr.id = `t`.`prova` LIMIT 1", 'searchable' => false],
    'prova_data'       => ['expression' => "SELECT pr.data FROM 104_provas pr WHERE pr.id = `t`.`prova` LIMIT 1",    'searchable' => false],
    'prova_inicio'     => ['expression' => "SELECT pr.inicio FROM 104_provas pr WHERE pr.id = `t`.`prova` LIMIT 1",  'searchable' => false],
    'concluida_status' => ['expression' => "CASE WHEN `t`.`concluida` = 1 THEN 'Sim' ELSE 'Não' END",               'searchable' => false],
];

// Colunas que serão exibidas na tabela
$_LIST_COLUMNS = ['aluno_nome', 'prova_tipo', 'turma_nome', 'prova_data', 'prova_inicio', 'concluida_status', 'nota'];
$_MAX_COLUMNS  = 7;

// Rótulos amigáveis para as colunas
$_LABELS = [
    'aluno_nome'       => 'Aluno',
    'prova_tipo'       => 'Prova',
    'turma_nome'       => 'Turma',
    'prova_data'       => 'Data',
    'prova_inicio'     => 'Início',
    'concluida_status' => 'Concluída',
    'nota'             => 'Nota'
];

// Ordenação padrão por nome do aluno
if (!isset($_GET['sort'])) {
    $_GET['sort'] = 'aluno_nome';
    $_GET['order'] = 'ASC';
}

// Opções para o filtro por prova
$_stmtProvas = $pdo->query("SELECT id, tipo FROM 104_provas ORDER BY tipo");
$_provasOpcoes = [];
foreach ($_stmtProvas->fetchAll(PDO::FETCH_ASSOC) as $p) {
    $_provasOpcoes[] = ['value' => $p['id'], 'label' => $p['tipo']];
}
$_SELECT_FILTERS = [
    [
        'name'    => 'filter_prova',
        'column'  => 'prova',
        'label'   => 'Todas as Provas',
        'options' => $_provasOpcoes,
    ]
];
?>

<div class="text-end mb-3">
    <a href="index.php?m=104_provas_alunos&a=relatorio_notas_pdf" class="btn btn-outline-danger btn-sm" target="_blank">
        <i class="bi bi-file-pdf"></i> Gerar PDF de Notas
    </a>
</div>

<?php
require __DIR__ . '/../generic/list.php';
