<?php
// app/modules/provas/view.php

$_TABLE = '104_provas';
$_LOOKUPS = [
    'turma' => ['table' => '104_turmas', 'label' => 'nome']
];

$id = $_GET['id'] ?? null;

require_once __DIR__ . '/../../core/CrudEngine.php';
$engine = new CrudEngine($pdo, $_TABLE);
$engine->setLookups($_LOOKUPS);
$record = $engine->getRecord($id);

if (!$record) {
    echo '<div class="alert alert-danger">Registro não encontrado.</div>';
    return;
}

// Buscar questões e opções vinculadas
$stmt = $pdo->prepare("
    SELECT q.* 
    FROM 104_questoes q
    JOIN 104_provas_questoes pq ON pq.questao = q.id
    WHERE pq.prova = ?
    ORDER BY pq.id
");
$stmt->execute([$id]);
$questoes = $stmt->fetchAll();

foreach ($questoes as &$q) {
    $stmtOpt = $pdo->prepare("SELECT * FROM 104_questoes_opcoes WHERE questao = ? ORDER BY id");
    $stmtOpt->execute([$q['id']]);
    $q['opcoes'] = $stmtOpt->fetchAll();
}
unset($q);
?>

<div class="d-flex justify-content-between align-items-center mb-4 mt-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="index.php?m=104_provas&a=list">Provas</a></li>
                <li class="breadcrumb-item active">Visualizar Prova</li>
            </ol>
        </nav>
        <h4 class="mb-0">Visualização da Prova #<?php echo $id; ?></h4>
    </div>
    <div class="d-flex gap-2">
        <a href="index.php?m=104_provas&a=list" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
        <a href="index.php?m=104_provas&a=relatorio_pdf&id=<?php echo $id; ?>" target="_blank" class="btn btn-danger">
            <i class="bi bi-file-pdf"></i> Imprimir PDF
        </a>
        <a href="index.php?m=104_provas&a=form&id=<?php echo $id; ?>" class="btn btn-primary">
            <i class="bi bi-pencil"></i> Editar
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Informações da Prova -->
    <div class="col-md-12">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="card-title mb-0 text-primary"><i class="bi bi-info-circle me-2"></i>Informações da Prova</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="text-muted small d-block">Turma</label>
                        <span class="h6"><?php echo htmlspecialchars($record['turma_label'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small d-block">Tipo de Avaliação</label>
                        <span
                            class="badge bg-primary fs-6"><?php echo htmlspecialchars($record['tipo'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="col-md-2">
                        <label class="text-muted small d-block">Data</label>
                        <span
                            class="h6"><?php echo $record['data'] ? date('d/m/Y', strtotime($record['data'])) : '—'; ?></span>
                    </div>
                    <div class="col-md-2">
                        <label class="text-muted small d-block">Horário</label>
                        <span class="h6"><?php echo $record['inicio'] ? substr($record['inicio'], 0, 5) : '—'; ?> às
                            <?php echo $record['termino'] ? substr($record['termino'], 0, 5) : '—'; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de Questões -->
    <div class="col-md-12 mb-5">
        <h5 class="mb-3 d-flex align-items-center">
            <i class="bi bi-list-check me-2"></i> Questões da Prova
            <span class="badge bg-secondary ms-2"><?php echo count($questoes); ?></span>
        </h5>

        <?php if (empty($questoes)): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-2"></i> Esta prova ainda não possui questões vinculadas.
            </div>
        <?php else: ?>
            <?php foreach ($questoes as $i => $q): ?>
                <div class="card shadow-sm mb-4 border-0">
                    <div
                        class="card-header bg-white d-flex justify-content-between align-items-center py-3 border-bottom border-light">
                        <span class="fw-bold text-dark h5 mb-0">Questão <?php echo $i + 1; ?></span>
                        <span class="badge bg-light text-muted border">ID: <?php echo $q['id']; ?></span>
                    </div>
                    <div class="card-body">
                        <div class="enunciado-view mb-4 p-3 bg-light rounded border-start border-4 border-primary">
                            <?php echo $q['enunciado']; ?>
                        </div>

                        <div class="row g-3">
                            <?php foreach ($q['opcoes'] as $j => $opt): ?>
                                <?php
                                $char = chr(65 + $j); // A, B, C...
                                $isCorreta = (bool) $opt['correta'];
                                ?>
                                <div class="col-12">
                                    <div
                                        class="p-3 rounded border <?php echo $isCorreta ? 'bg-success bg-opacity-10 border-success shadow-sm' : 'border-light bg-white'; ?>">
                                        <div class="d-flex">
                                            <div class="flex-shrink-0">
                                                <span
                                                    class="badge <?php echo $isCorreta ? 'bg-success' : 'bg-secondary'; ?> rounded-pill me-3"
                                                    style="width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center;">
                                                    <?php echo $char; ?>
                                                </span>
                                            </div>
                                            <div class="flex-grow-1 pt-1">
                                                <?php echo $opt['resposta']; ?>
                                            </div>
                                            <?php if ($isCorreta): ?>
                                                <div class="ms-auto ps-3 d-flex align-items-center">
                                                    <span class="text-success fw-bold small"><i class="bi bi-check-circle-fill"></i>
                                                        RESPOSTA CORRETA</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<style>
    .enunciado-view p:last-child {
        margin-bottom: 0;
    }

    .card-title {
        font-weight: 600;
    }
</style>