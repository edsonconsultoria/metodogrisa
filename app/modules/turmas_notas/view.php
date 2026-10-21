<?php
// app/modules/turmas_notas/view.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';

$tableName = '104_turmas_notas';
$engine = new CrudEngine($pdo, $tableName);

$engine->setLookups([
    'turma' => ['table' => '104_turmas', 'label' => 'nome'],
    'disciplina' => ['table' => '104_disciplinas', 'label' => 'nome']
]);

$id = (int) ($_GET['id'] ?? 0);
$record = $engine->getRecord($id);

if (!$record) {
    header("Location: " . BASE_URL . "index.php?m=turmas_notas&a=list&msg=" . urlencode("Registro não encontrado.") . "&type=danger");
    exit;
}

// Resolve student name
if (isset($record['aluno'])) {
    $sqlName = "SELECT p.nome 
                FROM 104_alunos a 
                JOIN 101_pessoas p ON a.aluno = p.id 
                WHERE a.id = ?";
    $stmtName = $pdo->prepare($sqlName);
    $stmtName->execute([$record['aluno']]);
    $record['aluno_label'] = $stmtName->fetchColumn() ?: $record['aluno'];
}

$schema = $engine->getSchema();
$baseHref = BASE_URL . 'index.php';

$formatPtBrDate = function ($date) {
    if (!$date || $date === '0000-00-00')
        return '—';
    return date('d/m/Y', strtotime($date));
};
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Visualizar
        <?php echo get_table_label($tableName); ?>
    </h4>
    <div class="d-flex gap-2">
        <a href="<?php echo $baseHref; ?>?m=turmas_notas&a=form&id=<?php echo $id; ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil"></i> Editar
        </a>
        <a href="<?php echo $baseHref; ?>?m=turmas_notas&a=list" class="btn btn-outline-secondary btn-sm">Voltar</a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="row g-3">
            <?php foreach ($schema as $col => $info): ?>
                <div class="col-md-6 mb-3 border-bottom pb-2">
                    <label class="fw-bold text-muted small d-block">
                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $col))); ?>
                    </label>
                    <div class="fs-5">
                        <?php
                        $val = isset($record[$col . '_label']) ? $record[$col . '_label'] : ($record[$col] ?? '—');
                        if ($info['type'] === 'date') {
                            $val = $formatPtBrDate((string) $val);
                        }
                        echo nl2br(htmlspecialchars((string) ($val === '' ? '—' : $val)));
                        ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>