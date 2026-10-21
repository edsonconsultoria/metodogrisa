<?php
// app/modules/aluno_provas/list.php
$user = current_user();
$alunoId = $user['id'];
$isAdmin = current_user_is_at_least_admin();

if ($isAdmin) {
    // Para administradores, mostramos todas as provas para que possam testar
    $sql = "
        SELECT 
            pa.id as pa_id,
            COALESCE(pa.concluida, 0) as concluida,
            pa.nota,
            p.id as prova_id,
            p.tipo,
            p.data,
            p.inicio,
            p.termino,
            t.nome as turma_nome
        FROM 104_provas p
        JOIN 104_turmas t ON t.id = p.turma
        LEFT JOIN 104_provas_alunos pa ON pa.prova = p.id AND pa.aluno = ?
        ORDER BY p.data DESC, p.inicio DESC
    ";
    $params = [$alunoId];
} else {
    // Para alunos, mostramos apenas as provas vinculadas
    $sql = "
        SELECT 
            pa.id as pa_id,
            pa.concluida,
            pa.nota,
            p.id as prova_id,
            p.tipo,
            p.data,
            p.inicio,
            p.termino,
            t.nome as turma_nome
        FROM 104_provas_alunos pa
        JOIN 104_provas p ON p.id = pa.prova
        JOIN 104_turmas t ON t.id = p.turma
        WHERE pa.aluno = ?
        ORDER BY p.data DESC, p.inicio DESC
    ";
    $params = [$alunoId];
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$provas = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4 mt-3">
    <h4><?php echo $isAdmin ? 'Testar Provas (Modo Administrador)' : 'Minhas Provas'; ?></h4>
</div>

<div class="row g-4">
    <?php if (empty($provas)): ?>
        <div class="col-12">
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i> Nenhuma prova disponível para exibição.
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($provas as $p): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm h-100 <?php echo $p['concluida'] ? 'border-success' : 'border-primary'; ?>">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span class="badge <?php echo $p['concluida'] ? 'bg-success' : 'bg-primary'; ?>">
                            <?php echo $p['concluida'] ? 'Concluída' : 'Disponível'; ?>
                        </span>
                        <small class="text-muted">ID: #<?php echo $p['prova_id']; ?></small>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($p['tipo']); ?></h5>
                        <p class="card-text mb-1"><strong>Turma:</strong> <?php echo htmlspecialchars($p['turma_nome']); ?></p>
                        <p class="card-text mb-1"><strong>Data:</strong> <?php echo date('d/m/Y', strtotime($p['data'])); ?></p>
                        <p class="card-text"><strong>Horário:</strong> <?php echo substr($p['inicio'], 0, 5); ?> às
                            <?php echo substr($p['termino'], 0, 5); ?></p>

                        <?php if ($p['concluida']): ?>
                            <div class="mt-3 p-2 bg-light rounded text-center">
                                <span class="d-block text-muted small">Última Nota de Teste</span>
                                <span class="h4 text-success mb-0"><?php echo number_format($p['nota'], 2, ',', '.'); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-white border-0 pt-0 pb-3 text-center">
                        <?php if ($isAdmin): ?>
                            <a href="index.php?m=aluno_provas&a=start_test&prova_id=<?php echo $p['prova_id']; ?>"
                                class="btn btn-warning w-100">
                                <i class="bi bi-play-circle"></i> <?php echo $p['concluida'] ? 'Refazer Teste' : 'Iniciar Teste'; ?>
                            </a>
                        <?php else: ?>
                            <?php if ($p['concluida']): ?>
                                <a href="index.php?m=aluno_provas&a=view&id=<?php echo $p['pa_id']; ?>"
                                    class="btn btn-outline-success w-100">
                                    <i class="bi bi-eye"></i> Ver Resultado
                                </a>
                            <?php else: ?>
                                <?php
                                $agora = date('Y-m-d H:i:s');
                                $inicio = $p['data'] . ' ' . $p['inicio'];
                                $termino = $p['data'] . ' ' . $p['termino'];
                                $disponivel = ($agora >= $inicio && $agora <= $termino);
                                ?>
                                <?php if ($disponivel): ?>
                                    <a href="index.php?m=aluno_provas&a=view&id=<?php echo $p['pa_id']; ?>" class="btn btn-primary w-100">
                                        <i class="bi bi-pencil-square"></i> Iniciar Prova
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-secondary w-100" disabled title="Fora do horário permitido">
                                        <i class="bi bi-clock"></i> Fora do Horário
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>