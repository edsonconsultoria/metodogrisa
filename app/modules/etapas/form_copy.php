<?php
// app/modules/etapas/form_copy.php
// Reusa o form padrão, carregando o registro informado e mantendo id=0 para inclusão.

require_once __DIR__ . '/../../config/config.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    die('ID inválido para copiar.');
}

$_GET['copy_from'] = $id;
$_GET['id'] = 0;

include __DIR__ . '/form.php';
