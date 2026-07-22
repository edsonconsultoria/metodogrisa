<?php
$_TABLE = '104_questoes';
$_BULK_ACTIONS = true;
$_VIRTUAL_COLUMNS = ['qtd_opcoes' => 'SELECT COUNT(*) FROM 104_questoes_opcoes WHERE questao = t.id'];
$_LIST_COLUMNS = ['id', 'tipo', 'enunciado', 'qtd_opcoes'];
$_LABELS = ['qtd_opcoes' => 'Opções'];
require __DIR__ . '/../generic/list.php';
