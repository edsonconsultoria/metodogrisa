<?php
// app/modules/provas_alunos/view.php

$pa_id = $_GET['id'] ?? null;

if (!$pa_id) {
    echo '<div class="alert alert-danger">ID da prova do aluno não informado.</div>';
    return;
}

// Buscar dados do vínculo aluno-prova
$stmt = $pdo->prepare("
    SELECT pa.*, p.tipo as prova_nome, p.data, p.inicio, p.termino, p.id as prova_id,
           pes.nome as aluno_nome
    FROM 104_provas_alunos pa
    JOIN 104_provas p ON p.id = pa.prova
    JOIN 101_pessoas pes ON pes.id = pa.aluno
    WHERE pa.id = ?
");
$stmt->execute([$pa_id]);
$pa = $stmt->fetch();

if (!$pa) {
    echo '<div class="alert alert-warning">Registro não encontrado.</div>';
    return;
}

$alunoId = $pa['aluno'];
$provaId = $pa['prova_id'];

// Buscar questões, opções e as respostas dadas pelo aluno
$stmtQ = $pdo->prepare("
    SELECT q.*, pr.id as resposta_id, pr.resposta as opcao_escolhida_id
    FROM 104_questoes q
    JOIN 104_provas_questoes pq ON pq.questao = q.id
    LEFT JOIN 104_provas_respostas pr ON pr.questao = q.id AND pr.aluno = ? AND pr.prova = ?
    WHERE pq.prova = ?
    ORDER BY pq.id
");
$stmtQ->execute([$alunoId, $provaId, $provaId]);
$questoes = $stmtQ->fetchAll();

foreach ($questoes as &$q) {
    $stmtOpt = $pdo->prepare("SELECT * FROM 104_questoes_opcoes WHERE questao = ? ORDER BY id");
    $stmtOpt->execute([$q['id']]);
    $q['opcoes'] = $stmtOpt->fetchAll();
}
unset($q);

$acertos = 0;
foreach ($questoes as $q) {
    foreach ($q['opcoes'] as $opt) {
        if ($opt['id'] == $q['opcao_escolhida_id'] && $opt['correta']) {
            $acertos++;
            break;
        }
    }
}
$total = count($questoes);
$desempenho = $total > 0 ? round(($acertos / $total) * 100) : 0;
?>

<div class="d-flex justify-content-between align-items-center mb-4 mt-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="index.php?m=104_provas_alunos&a=list">Provas de Alunos</a></li>
                <li class="breadcrumb-item active">Visualizar Resultado</li>
            </ol>
        </nav>
        <h4 class="mb-0">Resultado: <?php echo htmlspecialchars($pa['aluno_nome']); ?></h4>
        <small class="text-muted"><?php echo htmlspecialchars($pa['prova_nome']); ?> | Realizada em
            <?php echo date('d/m/Y', strtotime($pa['data'])); ?></small>
    </div>
    <div>
        <a href="index.php?m=104_provas_alunos&a=list" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100 bg-primary text-white">
            <div class="card-body text-center d-flex flex-column justify-content-center py-4">
                <h2 class="display-4 fw-bold mb-0"><?php echo number_format($pa['nota'], 2, ',', '.'); ?></h2>
                <p class="h6 opacity-75 mb-0">Nota Final</p>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body py-4">
                <div class="row text-center">
                    <div class="col-6 border-end">
                        <span class="d-block h3 mb-0 fw-bold"><?php echo $acertos; ?> / <?php echo $total; ?></span>
                        <small class="text-muted text-uppercase fw-bold">Acertos</small>
                    </div>
                    <div class="col-6">
                        <span
                            class="d-block h3 mb-0 fw-bold <?php echo $desempenho >= 70 ? 'text-success' : 'text-warning'; ?>">
                            <?php echo $desempenho; ?>%
                        </span>
                        <small class="text-muted text-uppercase fw-bold">Desempenho</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body py-3">
        <h6 class="text-muted mb-3 text-uppercase fw-bold text-center text-sm" style="font-size: 0.85rem;">Mapa de
            Respostas</h6>
        <div class="d-flex flex-wrap gap-2 justify-content-center">
            <?php if (!empty($questoes)): ?>
                <?php foreach ($questoes as $i => $q): ?>
                    <?php
                    $acertou = false;
                    $respondida = $q['opcao_escolhida_id'] !== null;
                    if ($respondida) {
                        foreach ($q['opcoes'] as $opt) {
                            if ($opt['id'] == $q['opcao_escolhida_id'] && $opt['correta']) {
                                $acertou = true;
                                break;
                            }
                        }
                    }
                    $circleClass = 'bg-secondary';
                    if ($respondida) {
                        $circleClass = $acertou ? 'bg-success' : 'bg-danger';
                    }
                    ?>
                    <a href="#questao-<?php echo $i; ?>" class="text-decoration-none">
                        <div class="rounded-circle d-flex align-items-center justify-content-center text-white shadow-sm <?php echo $circleClass; ?>"
                            style="width: 35px; height: 35px; font-weight: bold; font-size: 0.95rem; cursor: pointer;"
                            title="Questão <?php echo $i + 1; ?><?php echo $respondida ? ($acertou ? ' - Correta' : ' - Incorreta') : ' - Não respondida'; ?>">
                            <?php echo $i + 1; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<h5 class="mb-3 mt-4"><i class="bi bi-list-check me-2"></i>Detalhamento das Questões</h5>

<?php if (empty($questoes)): ?>
    <div class="alert alert-info">Nenhuma questão encontrada para esta prova.</div>
<?php else: ?>
    <?php foreach ($questoes as $i => $q): ?>
        <div class="card shadow-sm mb-4 border-0" id="questao-<?php echo $i; ?>">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <span class="fw-bold mb-0 text-primary">Questão <?php echo $i + 1; ?></span>
                <?php
                $acertou = false;
                foreach ($q['opcoes'] as $opt) {
                    if ($opt['id'] == $q['opcao_escolhida_id'] && $opt['correta']) {
                        $acertou = true;
                        break;
                    }
                }
                ?>
                <?php if ($q['opcao_escolhida_id'] === null): ?>
                    <span class="badge bg-secondary"><i class="bi bi-dash-circle me-1"></i> Não respondida</span>
                <?php elseif ($acertou): ?>
                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> Correta</span>
                <?php else: ?>
                    <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i> Incorreta</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="enunciado mb-4 p-3 bg-light rounded border-start border-4 border-primary">
                    <?php echo $q['enunciado']; ?>
                </div>

                <div class="row g-2">
                    <?php foreach ($q['opcoes'] as $j => $opt): ?>
                        <?php
                        $isEscolhida = ($opt['id'] == $q['opcao_escolhida_id']);
                        $isCorreta = (bool) $opt['correta'];

                        $itemClass = 'border-light bg-white';
                        $icon = '';
                        $textClass = '';

                        if ($isCorreta) {
                            $itemClass = 'bg-success bg-opacity-10 border-success';
                            $icon = '<i class="bi bi-check-circle-fill text-success ms-auto"></i>';
                            $textClass = 'text-success fw-bold';
                        }

                        if ($isEscolhida && !$isCorreta) {
                            $itemClass = 'bg-danger bg-opacity-10 border-danger';
                            $icon = '<i class="bi bi-x-circle-fill text-danger ms-auto"></i>';
                            $textClass = 'text-danger fw-bold';
                        }
                        ?>
                        <div class="col-12">
                            <div class="p-3 rounded border <?php echo $itemClass; ?> d-flex align-items-center">
                                <span
                                    class="badge <?php echo $isCorreta || ($isEscolhida && !$isCorreta) ? ($isCorreta ? 'bg-success' : 'bg-danger') : 'bg-secondary'; ?> rounded-pill me-3"
                                    style="width: 25px;">
                                    <?php echo chr(65 + $j); ?>
                                </span>
                                <div class="<?php echo $textClass; ?> flex-grow-1"><?php echo $opt['resposta']; ?></div>
                                <?php echo $icon; ?>
                                <?php if ($isEscolhida): ?>
                                    <span class="ms-2 badge border border-primary text-primary small">Resposta do Aluno</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<style>
    .breadcrumb-item+.breadcrumb-item::before {
        content: "/";
    }

    .bg-opacity-10 {
        --bs-bg-opacity: 0.1;
    }

    .ls-1 {
        letter-spacing: 1px;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('a[href^="#questao-"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                const targetEl = document.querySelector(targetId);
                if (targetEl) {
                    targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    // Premium visual feedback: pulse effect
                    targetEl.style.transition = 'all 0.3s ease';
                    targetEl.classList.add('border-primary', 'shadow-lg');
                    targetEl.style.transform = 'scale(1.02)';
                    setTimeout(() => {
                        targetEl.style.transform = 'scale(1)';
                        targetEl.classList.remove('shadow-lg');
                        setTimeout(() => {
                            targetEl.classList.remove('border-primary');
                        }, 500);
                    }, 1000);
                }
            });
        });
    });
</script>