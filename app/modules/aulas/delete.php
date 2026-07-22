<?php
// app/modules/aulas/delete.php

require_once __DIR__ . '/../../core/CrudEngine.php';

$tableName = '104_cronograma';
$engine = new CrudEngine($pdo, $tableName);

// Apenas ADMIN ou SUPERADMIN podem excluir
$userPerfil = $_SESSION['user']['perfil'] ?? 'OPERACIONAL';
if (!in_array(strtolower($userPerfil), ['admin', 'superadmin'])) {
    die("Acesso negado.");
}

$id = (int) ($_GET['id'] ?? 0);
$turmaView = (int) ($_GET['turma_view'] ?? 0);
$etapaId = (int) ($_GET['etapa_id'] ?? 0);

// Só permite excluir aulas que não possuem vídeo
$registro = $id ? $engine->getRecord($id) : null;
$temVideo = $registro
    && ($registro['sem_video'] ?? '') !== '1'
    && !empty($registro['link_para_aula']);

if (!$registro) {
    $msg = "Aula não encontrada.";
    $type = "danger";
} elseif ($temVideo) {
    $msg = "Não é possível excluir uma aula que possui vídeo.";
    $type = "danger";
} elseif ($engine->delete($id)) {
    $msg = "Aula excluída com sucesso.";
    $type = "success";
    log_event($pdo, $tableName, 'DELETE', "Aula ID $id (sem vídeo) excluída via módulo Aulas.");
} else {
    $msg = "Erro ao excluir a aula.";
    $type = "danger";
}

$url = BASE_URL . "index.php?m=aulas&a=list";
if ($turmaView > 0) {
    $url .= "&turma_view=$turmaView";
}
$url .= "&msg=" . urlencode($msg) . "&type=$type";
if ($etapaId > 0) {
    $url .= "#etapa-block-$etapaId";
}

header("Location: $url");
exit;
