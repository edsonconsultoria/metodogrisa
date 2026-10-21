<?php
// app/modules/rel_aulas_professores/relatorio.php

require_once __DIR__ . '/../../config/config.php';

$baseHref = BASE_URL . 'index.php';

$profFiltro = (int) ($_GET['professor_id'] ?? 0);
$etapaFiltro = (int) ($_GET['etapa_id'] ?? 0);
$viewMode = $_GET['view'] ?? 'summary'; // 'summary' or 'detail'

// Fetch professors for the filter (ASC)
$stmtProfs = $pdo->query("SELECT p.id, ps.nome, p.apelido FROM 104_professores p JOIN 101_pessoas ps ON p.pessoa = ps.id ORDER BY p.apelido ASC");
$professores = $stmtProfs->fetchAll(PDO::FETCH_ASSOC);

// Fetch stages for the filter (DESC)
$stmtEtapas = $pdo->query("SELECT id, nome FROM 104_etapas ORDER BY id DESC");
$etapas = $stmtEtapas->fetchAll(PDO::FETCH_ASSOC);

$where = [];
$params = [];

if ($profFiltro > 0) {
    if ($viewMode === 'detail' || $viewMode === 'summary') {
        $where[] = "p.id = :prof_id";
    }
    $params[':prof_id'] = $profFiltro;
}

if ($etapaFiltro > 0) {
    if ($viewMode === 'detail' || $viewMode === 'summary') {
        $where[] = "c.etapa = :etapa_id";
    }
    $params[':etapa_id'] = $etapaFiltro;
}

$whereStr = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

if ($viewMode === 'summary') {
    $sql = "
        SELECT 
            e.id as etapa_id,
            e.nome as etapa_nome,
            p.id as prof_id,
            p.apelido as prof_nome,
            COUNT(*) as total_aulas
        FROM 104_professores p
        JOIN (
            SELECT professor_1 as prof_id, etapa FROM 104_cronograma
            UNION ALL
            SELECT professor_2 as prof_id, etapa FROM 104_cronograma WHERE professor_2 IS NOT NULL AND professor_2 != 0
            UNION ALL
            SELECT professor_3 as prof_id, etapa FROM 104_cronograma WHERE professor_3 IS NOT NULL AND professor_3 != 0
            UNION ALL
            SELECT professor_4 as prof_id, etapa FROM 104_cronograma WHERE professor_4 IS NOT NULL AND professor_4 != 0
        ) c ON p.id = c.prof_id
        JOIN 104_etapas e ON c.etapa = e.id
        $whereStr
        GROUP BY etapa_id, prof_id
        ORDER BY etapa_id DESC, prof_nome ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $summaryData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Detail view
    $sql = "
        SELECT 
            p.id as prof_id, 
            p.apelido as prof_nome, 
            c.id as class_id,
            c.data, 
            c.inicio, 
            c.termino, 
            c.aula, 
            e.nome as etapa_nome, 
            t.nome as turma_nome
        FROM 104_professores p
        JOIN (
            SELECT professor_1 as prof_id, id, etapa, turma, data, inicio, termino, aula FROM 104_cronograma
            UNION ALL
            SELECT professor_2 as prof_id, id, etapa, turma, data, inicio, termino, aula FROM 104_cronograma WHERE professor_2 IS NOT NULL AND professor_2 != 0
            UNION ALL
            SELECT professor_3 as prof_id, id, etapa, turma, data, inicio, termino, aula FROM 104_cronograma WHERE professor_3 IS NOT NULL AND professor_3 != 0
            UNION ALL
            SELECT professor_4 as prof_id, id, etapa, turma, data, inicio, termino, aula FROM 104_cronograma WHERE professor_4 IS NOT NULL AND professor_4 != 0
        ) c ON p.id = c.prof_id
        JOIN 104_etapas e ON c.etapa = e.id
        JOIN 104_turmas t ON c.turma = t.id
        $whereStr
        ORDER BY prof_nome, data, inicio
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $detailData = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$pdfUrl = $baseHref . '?' . http_build_query([
    'm' => 'rel_aulas_professores',
    'a' => 'relatorio_pdf',
    'professor_id' => $profFiltro,
    'etapa_id' => $etapaFiltro,
    'view' => $viewMode
]);
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <?php echo $viewMode === 'summary' ? 'Resumo: Aulas por Etapa/Professor' : 'Detalhes: Aulas por Professor/Etapa'; ?>
    </h4>
    <div class="d-flex gap-2">
        <?php if ($viewMode === 'detail'): ?>
            <a href="<?php echo $baseHref; ?>?m=rel_aulas_professores&a=relatorio&professor_id=<?php echo $profFiltro; ?>&etapa_id=<?php echo $etapaFiltro; ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Voltar ao Resumo
            </a>
        <?php endif; ?>
        <a href="<?php echo $pdfUrl; ?>" target="_blank" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-file-earmark-pdf"></i> Imprimir PDF
        </a>
    </div>
</div>

<!-- Filtros sempre visíveis -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="get" class="row g-3">
            <input type="hidden" name="m" value="rel_aulas_professores">
            <input type="hidden" name="a" value="relatorio">
            <!-- Ao filtrar, sempre volta pro resumo para não confundir se estiver no detalhe de outro par -->
            <input type="hidden" name="view" value="summary">
            
            <div class="col-md-5">
                <label class="form-label">Professor</label>
                <select name="professor_id" class="form-select">
                    <option value="">Todos os Professores</option>
                    <?php foreach ($professores as $prof): ?>
                        <option value="<?php echo $prof['id']; ?>" <?php echo ($profFiltro == $prof['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($prof['apelido']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-5">
                <label class="form-label">Etapa</label>
                <select name="etapa_id" class="form-select">
                    <option value="">Todas as Etapas</option>
                    <?php foreach ($etapas as $etapa): ?>
                        <option value="<?php echo $etapa['id']; ?>" <?php echo ($etapaFiltro == $etapa['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($etapa['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Filtrar</button>
            </div>
        </form>
    </div>
</div>

<?php if ($viewMode === 'summary'): ?>
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width: 100px;">Ações</th>
                            <th>Etapa</th>
                            <th>Professor</th>
                            <th class="text-center" style="width: 150px;">Aulas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($summaryData)): ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted">Nenhuma aula encontrada.</td></tr>
                        <?php else: ?>
                            <?php foreach ($summaryData as $row): ?>
                                <tr>
                                    <td class="text-center">
                                        <a href="<?php echo $baseHref; ?>?m=rel_aulas_professores&a=relatorio&view=detail&professor_id=<?php echo $row['prof_id']; ?>&etapa_id=<?php echo $row['etapa_id']; ?>" 
                                           class="btn btn-sm btn-outline-primary" title="Ver Aulas">
                                            <i class="bi bi-eye"></i> Ver
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['etapa_nome']); ?></td>
                                    <td><?php echo htmlspecialchars($row['prof_nome']); ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-primary rounded-pill"><?php echo $row['total_aulas']; ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- Detail view -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 120px;">Data</th>
                            <th style="width: 150px;">Horário</th>
                            <th>Aula</th>
                            <th>Turma / Etapa</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($detailData)): ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted">Nenhuma aula encontrada.</td></tr>
                        <?php else: ?>
                            <?php foreach ($detailData as $aula): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($aula['data'])); ?></td>
                                    <td><?php echo date('H:i', strtotime($aula['inicio'])) . ' - ' . date('H:i', strtotime($aula['termino'])); ?></td>
                                    <td><?php echo htmlspecialchars($aula['aula']); ?></td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($aula['turma_nome']); ?></span>
                                        <span class="text-muted small"><?php echo htmlspecialchars($aula['etapa_nome']); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>
