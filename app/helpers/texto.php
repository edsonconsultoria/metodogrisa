<?php
// app/helpers/texto.php

/**
 * Formata nomes, logradouros e outros textos de pessoas com maiúsculas de título
 * e preserva conectores em minúsculo (ex: "da", "dos").
 */
function formatarNome($nome)
{
    $nome = trim((string) $nome);
    $nome = mb_strtolower($nome, 'UTF-8');
    $nome = mb_convert_case($nome, MB_CASE_TITLE, 'UTF-8');

    $minusculas = [' Da ', ' De ', ' Do ', ' Dos ', ' Das ', ' E '];
    foreach ($minusculas as $palavra) {
        $nome = str_replace($palavra, mb_strtolower($palavra, 'UTF-8'), $nome);
    }

    return $nome;
}

/**
 * Atalho para htmlspecialchars para evitar XSS.
 */
function h($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}
/**
 * Converte nomes técnicos de tabelas (ex: 101_interessados) para nomes amigáveis.
 */
function get_table_label(string $tableName): string
{
    // Mapeamento manual para casos específicos
    $labels = [
        '101_interessados' => 'Interessados',
        '101_pessoas' => 'Pessoas',
        '104_questoes' => 'Questões',
        '104_questoes_opcoes' => 'Opções de Questões',
        '104_provas' => 'Provas',
        '104_provas_questoes' => 'Questões da Prova',
        '104_provas_alunos' => 'Alunos da Prova',
        '104_provas_respostas' => 'Respostas da Prova',
    ];

    if (isset($labels[$tableName])) {
        return $labels[$tableName];
    }

    // Fallback: remove prefixos numéricos (ex: 101_) e underscores
    $label = preg_replace('/^\d+_/', '', $tableName);
    $label = str_replace('_', ' ', $label);

    return mb_convert_case($label, MB_CASE_TITLE, 'UTF-8');
}

/**
 * Converte uma string em um slug amigável para URLs ou nomes de arquivos.
 */
function slugify($text)
{
    $text = preg_replace('~[^\pL\d]+~u', '_', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '_');
    $text = preg_replace('~_+~', '_', $text);
    $text = strtolower($text);
    if (empty($text))
        return 'n-a';
    return $text;
}

/**
 * Retorna a data atual por extenso em Português.
 */
function data_extenso($date = null)
{
    $date = $date ? strtotime($date) : time();
    $meses = [
        1 => 'janeiro',
        'fevereiro',
        'março',
        'abril',
        'maio',
        'junho',
        'julho',
        'agosto',
        'setembro',
        'outubro',
        'novembro',
        'dezembro'
    ];
    $dia = date('d', $date);
    $mes = $meses[(int) date('m', $date)];
    $ano = date('Y', $date);
    return "$dia de $mes de $ano";
}
