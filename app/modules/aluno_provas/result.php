<?php
// app/modules/aluno_provas/result.php
// Este arquivo é incluído por view.php quando a prova já foi concluída.

// Buscar questões, opções e as respostas dadas pelo aluno
$stmtQ = $pdo->prepare("
    SELECT q.*, pr.resposta as opcao_escolhida_id
    FROM 104_questoes q
    JOIN 104_provas_questoes pq ON pq.questao = q.id
    LEFT JOIN 104_provas_respostas pr ON pr.questao = q.id AND pr.aluno = ? AND pr.prova = ?
    WHERE pq.prova = ?
    ORDER BY pq.id
");
$stmtQ->execute([$pa['aluno'], $pa['prova_id'], $pa['prova_id']]);
$questoes = $stmtQ->fetchAll();

foreach ($questoes as &$q) {
    $stmtOpt = $pdo->prepare("SELECT * FROM 104_questoes_opcoes WHERE questao = ? ORDER BY id");
    $stmtOpt->execute([$q['id']]);
    $q['opcoes'] = $stmtOpt->fetchAll();
}
unset($q);

// Aplicar o mesmo embaralhamento determinístico para que a revisão coincida com a prova
mt_srand($pa_id);
shuffle($questoes);
foreach ($questoes as &$q) {
    shuffle($q['opcoes']);
}
unset($q);
mt_srand();

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

$agora = date('Y-m-d H:i:s');
$termino_prova = $pa['data'] . ' ' . $pa['termino'];
$mostrar_correcao = ($agora > $termino_prova);
?>

<div class="mt-4 mb-5">
    <div class="card shadow-sm border-0 mb-4 bg-light text-center py-5">
        <div class="card-body">
            <h2 class="display-4 text-success fw-bold mb-2"><?php echo number_format($pa['nota'], 2, ',', '.'); ?></h2>
            <p class="h5 text-muted mb-4">Sua nota final nesta avaliação</p>
            <div class="d-flex justify-content-center gap-4">
                <div class="text-center">
                    <span class="d-block h4 mb-0"><?php echo $acertos; ?> / <?php echo $total; ?></span>
                    <small class="text-muted text-uppercase fw-bold ls-1">Acertos</small>
                </div>
                <div class="vr"></div>
                <div class="text-center">
                    <span class="d-block h4 mb-0"><?php echo round(($acertos / $total) * 100); ?>%</span>
                    <small class="text-muted text-uppercase fw-bold ls-1">Desempenho</small>
                </div>
            </div>
            <div class="mt-4">
                <a href="index.php?m=aluno_provas&a=list" class="btn btn-outline-primary px-4">
                    <i class="bi bi-arrow-left"></i> Voltar para a lista
                </a>
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
                        $title = "Questão " . ($i + 1);

                        if ($respondida) {
                            if ($mostrar_correcao) {
                                $circleClass = $acertou ? 'bg-success' : 'bg-danger';
                                $title .= $acertou ? ' - Correta' : ' - Incorreta';
                            } else {
                                $circleClass = 'bg-primary';
                                $title .= ' - Respondida';
                            }
                        } else {
                            $title .= ' - Não respondida';
                        }
                        ?>
                        <a href="#questao-<?php echo $i; ?>" class="text-decoration-none">
                            <div class="rounded-circle d-flex align-items-center justify-content-center text-white shadow-sm <?php echo $circleClass; ?>"
                                style="width: 35px; height: 35px; font-weight: bold; font-size: 0.95rem; cursor: pointer;"
                                title="<?php echo $title; ?>">
                                <?php echo $i + 1; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <h5 class="mb-3"><i class="bi bi-check-all me-2"></i>Revisão das Questões</h5>

    <?php foreach ($questoes as $i => $q): ?>
        <div class="card shadow-sm mb-4 border-0" id="questao-<?php echo $i; ?>">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <span class="fw-bold mb-0">Questão <?php echo $i + 1; ?></span>
                <?php
                $acertou = false;
                foreach ($q['opcoes'] as $opt) {
                    if ($opt['id'] == $q['opcao_escolhida_id'] && $opt['correta']) {
                        $acertou = true;
                        break;
                    }
                }
                ?>
                <?php if ($mostrar_correcao): ?>
                    <?php if ($acertou): ?>
                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> Você acertou</span>
                    <?php else: ?>
                        <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i> Você errou</span>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="badge bg-secondary"><i class="bi bi-clock me-1"></i> Correção disponível às
                        <?php echo substr($pa['termino'], 0, 5); ?></span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="enunciado mb-4 p-3 bg-light rounded">
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

                        if ($mostrar_correcao) {
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
                        } else {
                            if ($isEscolhida) {
                                $itemClass = 'bg-primary bg-opacity-10 border-primary';
                                $textClass = 'text-primary fw-bold';
                            }
                        }
                        ?>
                        <div class="col-12">
                            <div class="p-3 rounded border <?php echo $itemClass; ?> d-flex align-items-center">
                                <span
                                    class="badge <?php echo $mostrar_correcao && ($isCorreta || ($isEscolhida && !$isCorreta)) ? ($isCorreta ? 'bg-success' : 'bg-danger') : ($isEscolhida ? 'bg-primary' : 'bg-secondary'); ?> rounded-pill me-3">
                                    <?php echo chr(65 + $j); ?>
                                </span>
                                <div class="<?php echo $textClass; ?>"><?php echo $opt['resposta']; ?></div>
                                <?php echo $icon; ?>
                                <?php if ($isEscolhida): ?>
                                    <span class="ms-2 badge border border-primary text-primary small">Sua Escolha</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<style>
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