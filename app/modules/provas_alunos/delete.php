<?php
$_TABLE = '104_provas_alunos';
$_LOOKUPS = [
    'prova' => ['table' => '104_provas', 'label' => 'id'], 'aluno' => ['table' => '101_pessoas', 'label' => 'nome']
];
require __DIR__ . '/../generic/delete.php';
