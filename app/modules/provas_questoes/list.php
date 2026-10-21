<?php
$_TABLE = '104_provas_questoes';
$_LOOKUPS = [
    'prova' => ['table' => '104_provas', 'label' => 'id'], 'questao' => ['table' => '104_questoes', 'label' => 'enunciado']
];
require __DIR__ . '/../generic/list.php';
