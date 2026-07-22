<?php
// app/modules/etapas/form.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';

$tableName = '104_etapas';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$copyFrom = isset($_GET['copy_from']) ? (int) $_GET['copy_from'] : 0;
$engine = new CrudEngine($pdo, $tableName);

$schema = $engine->getSchema();
$primaryKey = $engine->getPrimaryKey();

if ($id > 0 || $copyFrom > 0) {
    $lookupId = $id > 0 ? $id : $copyFrom;
    $record = $engine->getRecord($lookupId);
} else {
    $record = null;
}

$baseHref = BASE_URL . 'index.php';

// Contagens relacionadas (somente ao editar)
$totalTurmas = 0;
$totalAulas = 0;
$turmasList = [];
$aulasList = [];

if ($id > 0) {
    // Turmas vinculadas a esta etapa via 104_etapas_turmas (ou direto na turma)
    // Verifica via cronograma: turmas distintas que têm aulas nesta etapa
    $stmtT = $pdo->prepare("
        SELECT t.id, t.nome, COUNT(c.id) AS qtd_aulas
        FROM 104_turmas t
        INNER JOIN 104_cronograma c ON c.turma = t.id AND c.etapa = :etapa
        GROUP BY t.id, t.nome
        ORDER BY t.nome
    ");
    $stmtT->execute([':etapa' => $id]);
    $turmasList = $stmtT->fetchAll(PDO::FETCH_ASSOC);
    $totalTurmas = count($turmasList);

    // Total de aulas (registros de cronograma) desta etapa
    $stmtA = $pdo->prepare("
        SELECT c.id, c.data, c.inicio, c.termino, c.aula,
               t.nome AS turma_nome,
               p1.apelido AS prof1, p2.apelido AS prof2,
               p3.apelido AS prof3, p4.apelido AS prof4
        FROM 104_cronograma c
        LEFT JOIN 104_turmas t ON t.id = c.turma
        LEFT JOIN 104_professores p1 ON p1.id = c.professor_1
        LEFT JOIN 104_professores p2 ON p2.id = c.professor_2
        LEFT JOIN 104_professores p3 ON p3.id = c.professor_3
        LEFT JOIN 104_professores p4 ON p4.id = c.professor_4
        WHERE c.etapa = :etapa
        ORDER BY c.data ASC, c.inicio ASC
    ");
    $stmtA->execute([':etapa' => $id]);
    $aulasList = $stmtA->fetchAll(PDO::FETCH_ASSOC);
    $totalAulas = count($aulasList);
}

$activeTab = $_GET['tab'] ?? 'dados';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <?php echo ($id ? 'Editar' : 'Novo') . ' ' . get_table_label($tableName); ?>
    </h4>
    <a href="<?php echo $baseHref; ?>?m=etapas&a=list" class="btn btn-outline-secondary btn-sm">Voltar</a>
</div>

<!-- Abas -->
<ul class="nav nav-tabs mb-3" id="etapasTabs">
    <li class="nav-item">
        <a class="nav-link <?php echo $activeTab === 'dados' ? 'active' : ''; ?>"
            href="<?php echo $baseHref; ?>?m=etapas&a=form<?php echo $id ? '&id=' . $id : ''; ?>&tab=dados">
            <i class="bi bi-card-text me-1"></i>Dados
        </a>
    </li>
    <?php if ($id > 0): ?>
        <li class="nav-item">
            <a class="nav-link <?php echo $activeTab === 'turmas' ? 'active' : ''; ?>"
                href="<?php echo $baseHref; ?>?m=etapas&a=form&id=<?php echo $id; ?>&tab=turmas">
                <i class="bi bi-people me-1"></i>Turmas
                <span class="badge bg-secondary ms-1"><?php echo $totalTurmas; ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $activeTab === 'aulas' ? 'active' : ''; ?>"
                href="<?php echo $baseHref; ?>?m=etapas&a=form&id=<?php echo $id; ?>&tab=aulas">
                <i class="bi bi-calendar3 me-1"></i>Aulas
                <span class="badge bg-secondary ms-1"><?php echo $totalAulas; ?></span>
            </a>
        </li>
    <?php endif; ?>
</ul>

<!-- Aba: Dados -->
<?php if ($activeTab === 'dados'): ?>
    <div class="card shadow-sm">
        <div class="card-body">
            <form action="<?php echo $baseHref; ?>?m=etapas&a=save" method="post">
                <?php if ($id): ?>
                    <input type="hidden" name="<?php echo $primaryKey; ?>" value="<?php echo htmlspecialchars($id); ?>">
                <?php endif; ?>

                <?php if (isset($_GET['turma_id'])): ?>
                    <input type="hidden" name="turma_id" value="<?php echo (int) $_GET['turma_id']; ?>">
                <?php endif; ?>

                <div class="row g-3">
                    <!-- Nome -->
                    <div class="col-md-12">
                        <label class="form-label">Nome da Etapa <span class="text-danger">*</span></label>
                        <input type="text" name="nome" class="form-control"
                            value="<?php echo htmlspecialchars($record['nome'] ?? ''); ?>" required>
                    </div>

                    <!-- Dia 1 -->
                    <div class="col-md-6">
                        <label class="form-label">Dia 1 <span class="text-danger">*</span></label>
                        <input type="date" name="dia_1" class="form-control"
                            value="<?php echo htmlspecialchars($record['dia_1'] ?? ''); ?>" required>
                    </div>

                    <!-- Dia 2 -->
                    <div class="col-md-6">
                        <label class="form-label">Dia 2 <span class="text-danger">*</span></label>
                        <input type="date" name="dia_2" class="form-control"
                            value="<?php echo htmlspecialchars($record['dia_2'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top">
                    <button type="submit" class="btn btn-primary">Salvar
                        <?php echo get_table_label($tableName); ?>
                    </button>
                    <a href="<?php echo $baseHref; ?>?m=etapas&a=list" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Aba: Turmas -->
<?php elseif ($activeTab === 'turmas'): ?>
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($turmasList)): ?>
                <div class="p-4 text-center text-muted">Nenhuma turma vinculada a esta etapa.</div>
            <?php else: ?>
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Turma</th>
                            <th class="text-center">Aulas nesta Etapa</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($turmasList as $i => $t): ?>
                            <tr>
                                <td class="text-muted small"><?php echo $i + 1; ?></td>
                                <td class="fw-semibold"><?php echo htmlspecialchars($t['nome']); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-primary"><?php echo $t['qtd_aulas']; ?></span>
                                </td>
                                <td>
                                    <a href="<?php echo $baseHref; ?>?m=etapas_cronogramas&a=list&filtro_etapa=<?php echo $id; ?>&filtro_turma=<?php echo $t['id']; ?>"
                                        class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-calendar3"></i> Ver aulas
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Aba: Aulas -->
<?php elseif ($activeTab === 'aulas'): ?>
    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-bold">Aulas desta Etapa</span>
            <a href="<?php echo $baseHref; ?>?m=etapas_cronogramas&a=form&etapa=<?php echo $id; ?>&return=<?php echo urlencode('m=etapas&a=form&id=' . $id . '&tab=aulas'); ?>"
                class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg"></i> Adicionar Aula
            </a>
        </div>
        <div class="card-body p-0">
            <?php if (empty($aulasList)): ?>
                <div class="p-4 text-center text-muted">Nenhuma aula cadastrada para esta etapa.</div>
            <?php else: ?>
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Data</th>
                            <th>Início</th>
                            <th>Término</th>
                            <th>Turma</th>
                            <th>Professores</th>
                            <th>Aula</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $fmtDate = function ($d) {
                            return ($d && $d !== '0000-00-00') ? date('d/m/Y', strtotime($d)) : '—';
                        };
                        foreach ($aulasList as $i => $a):
                            $isPast = !empty($a['data']) && $a['data'] !== '0000-00-00' && strtotime($a['data']) < strtotime('today');
                            ?>
                            <tr class="<?php echo $isPast ? 'table-success' : ''; ?>">
                                <td class="text-muted small"><?php echo $i + 1; ?></td>
                                <td><?php echo $fmtDate($a['data']); ?></td>
                                <td><?php echo substr((string) $a['inicio'], 0, 5); ?></td>
                                <td><?php echo substr((string) $a['termino'], 0, 5); ?></td>
                                <td class="fw-semibold"><?php echo htmlspecialchars($a['turma_nome'] ?? '—'); ?></td>
                                <td class="small">
                                    <?php
                                    $profs = array_filter([$a['prof1'] ?? '', $a['prof2'] ?? '', $a['prof3'] ?? '', $a['prof4'] ?? '']);
                                    echo htmlspecialchars(implode(', ', $profs)) ?: '—';
                                    ?>
                                </td>
                                <td class="small">
                                    <?php echo htmlspecialchars(substr($a['aula'], 0, 60) . (strlen($a['aula']) > 60 ? '…' : '')); ?>
                                </td>
                                <td>
                                    <a href="<?php echo $baseHref; ?>?m=etapas_cronogramas&a=form&id=<?php echo $a['id']; ?>&return=<?php echo urlencode('m=etapas&a=form&id=' . $id . '&tab=aulas'); ?>"
                                        class="action-btn action-btn--editar" title="Editar" data-bs-toggle="tooltip">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>