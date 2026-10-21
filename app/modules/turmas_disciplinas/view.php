<?php
// app/modules/turmas_disciplinas/view.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';

$tableName = '104_turmas_disciplinas';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$engine = new CrudEngine($pdo, $tableName);

$engine->setLookups([
    'turma' => ['table' => '104_turmas', 'label' => 'nome'],
    'disciplina' => ['table' => '104_disciplinas', 'label' => 'nome'],
    'professor' => ['table' => '104_professores', 'label' => 'apelido']
]);

require_once __DIR__ . '/../../helpers/list_state.php';
$record = $engine->getRecord($id);
if (!$record) {
    die("Registro não encontrado.");
}

$baseHref = BASE_URL . 'index.php';
$editUrl = list_state_action_url('turmas_disciplinas', 'form', ['id' => $id]);
$returnUrl = list_state_return_destination('turmas_disciplinas', 'list');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Visualizar <?php echo get_table_label($tableName); ?></h4>
    <div class="d-flex gap-2">
        <a href="<?php echo $editUrl; ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil"></i> Editar
        </a>
        <a href="<?php echo $returnUrl; ?>" class="btn btn-outline-secondary btn-sm">Voltar</a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="row g-4">
            <div class="col-md-6">
                <label class="text-muted small d-block mb-1">Turma</label>
                <div class="fw-bold fs-5"><?php echo htmlspecialchars($record['turma_label'] ?? '—'); ?></div>
            </div>

            <div class="col-md-6">
                <label class="text-muted small d-block mb-1">Disciplina</label>
                <div class="fw-bold fs-5"><?php echo htmlspecialchars($record['disciplina_label'] ?? '—'); ?></div>
            </div>

            <div class="col-md-6">
                <label class="text-muted small d-block mb-1">Carga Horária</label>
                <div class="fw-bold"><?php echo htmlspecialchars($record['carga_horaria'] ?? '—'); ?> h</div>
            </div>

            <div class="col-md-6">
                <label class="text-muted small d-block mb-1">Professor</label>
                <div class="fw-bold"><?php echo htmlspecialchars($record['professor_label'] ?? '—'); ?></div>
            </div>
        </div>
    </div>
</div>