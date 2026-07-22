<?php
$_TABLE = '104_provas_respostas';
$_LOOKUPS = [
    'prova' => ['table' => '104_provas', 'label' => 'id'], 'aluno' => ['table' => '101_pessoas', 'label' => 'nome'], 'questao' => ['table' => '104_questoes', 'label' => 'enunciado']
];
require __DIR__ . '/../generic/view.php';
