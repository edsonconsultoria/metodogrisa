<?php
// app/modules/aluno_provas/view.php
$user = current_user();
$alunoId = $user['id'];
$pa_id = $_GET['id'] ?? null;

if (!$pa_id) die("Acesso inválido.");

// Buscar dados do vínculo aluno-prova
$stmt = $pdo->prepare("
    SELECT pa.*, p.tipo, p.data, p.inicio, p.termino, p.id as prova_id
    FROM 104_provas_alunos pa
    JOIN 104_provas p ON p.id = pa.prova
    WHERE pa.id = ? AND pa.aluno = ?
");
$stmt->execute([$pa_id, $alunoId]);
$pa = $stmt->fetch();

if (!$pa) die("Prova não encontrada ou acesso negado.");

// Se já concluiu, redireciona ou mostra resultado
if ($pa['concluida'] || (isset($_GET['simulated']) && isset($_SESSION['test_concluido'][$pa_id]))) {
    if (isset($_GET['simulated']) && isset($_SESSION['test_concluido'][$pa_id])) {
        $pa['concluida'] = 1;
        $pa['nota'] = $_SESSION['test_concluido'][$pa_id]['nota'];
    }
    include __DIR__ . '/result.php';
    return;
}

// Verificar horário (com autorização especial)
$agora = date('Y-m-d H:i:s');
$inicio_prova = $pa['data'] . ' ' . $pa['inicio'];
$termino_prova = $pa['data'] . ' ' . $pa['termino'];
$fora_do_horario = ($agora < $inicio_prova || $agora > $termino_prova);

if ($fora_do_horario && $pa['autorizacao_especial'] != 1) {
    echo '<div class="alert alert-warning mt-4">
            <h4><i class="bi bi-clock"></i> Fora do Horário</h4>
            <p>Esta prova está agendada para: ' . date('d/m/Y', strtotime($pa['data'])) . ' das ' . substr($pa['inicio'], 0, 5) . ' às ' . substr($pa['termino'], 0, 5) . '</p>
          </div>';
    echo '<a href="index.php?m=aluno_provas&a=list" class="btn btn-secondary">Voltar</a>';
    return;
}

// Gravar data de início na sessão se não existir
if (!isset($_SESSION['prova_start_' . $pa_id])) {
    $_SESSION['prova_start_' . $pa_id] = date('H:i:s');
}
$hora_inicio = $_SESSION['prova_start_' . $pa_id];

// Buscar questões da prova
$stmtQ = $pdo->prepare("
    SELECT q.* 
    FROM 104_questoes q
    JOIN 104_provas_questoes pq ON pq.questao = q.id
    WHERE pq.prova = ?
    ORDER BY pq.id
");
$stmtQ->execute([$pa['prova_id']]);
$questoes = $stmtQ->fetchAll();

// Embaralhar questões e alternativas de forma determinística por tentativa
mt_srand($pa_id);

shuffle($questoes);
foreach ($questoes as &$q) {
    $stmtOpt = $pdo->prepare("SELECT * FROM 104_questoes_opcoes WHERE questao = ? ORDER BY id");
    $stmtOpt->execute([$q['id']]);
    $q['opcoes'] = $stmtOpt->fetchAll();
    shuffle($q['opcoes']);
}
unset($q);

// Resetar semente para não afetar outros componentes
mt_srand();

$isAdmin = current_user_is_at_least_admin();

// Buscar respostas já gravadas (para retomada)
if ($isAdmin) {
    $respostasGravadas = $_SESSION['test_respostas'][$pa['prova_id']] ?? [];
} else {
    $stmtRes = $pdo->prepare("SELECT questao, resposta FROM 104_provas_respostas WHERE prova = ? AND aluno = ?");
    $stmtRes->execute([$pa['prova_id'], $alunoId]);
    $respostasGravadas = $stmtRes->fetchAll(PDO::FETCH_KEY_PAIR);
}
?>

<div class="exam-container">
    <div class="exam-header d-flex justify-content-between align-items-center px-4 py-3 bg-white shadow-sm border-bottom">
        <div>
            <h5 class="mb-0 fw-bold text-primary"><?php echo htmlspecialchars($pa['tipo']); ?></h5>
            <small class="text-muted">Início: <span class="fw-bold"><?php echo $hora_inicio; ?></span></small>
        </div>
        <div class="text-center">
            <div class="progress" style="width: 200px; height: 8px;">
                <div id="examProgress" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
            </div>
            <small class="text-muted"><span id="currentStep">1</span> de <?php echo count($questoes); ?> questões</small>
        </div>
        <div class="text-end">
            <div class="timer h4 mb-0 text-danger font-monospace" id="test-timer" data-end="<?php echo $termino_prova; ?>">
                --:--:--
            </div>
            <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Tempo Restante</small>
        </div>
    </div>

    <form action="index.php?m=aluno_provas&a=finish" method="post" id="examForm" class="exam-body">
        <input type="hidden" name="pa_id" value="<?php echo $pa_id; ?>">
        
        <?php foreach ($questoes as $i => $q): ?>
            <div class="question-step" id="step_<?php echo $i; ?>" style="display: <?php echo $i === 0 ? 'block' : 'none'; ?>;">
                <div class="container py-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light py-3 border-bottom d-flex justify-content-between align-items-center">
                            <span class="fw-bold h6 mb-0 text-uppercase">Questão <?php echo $i + 1; ?></span>
                            <span class="badge bg-primary rounded-pill">Peso Igual</span>
                        </div>
                        <div class="card-body p-4">
                            <div class="enunciado mb-4 lh-base" style="font-size: 1.1rem;">
                                <?php echo $q['enunciado']; ?>
                            </div>
                            
                            <div class="options-group">
                                <?php foreach ($q['opcoes'] as $j => $opt): ?>
                                    <div class="option-item mb-3 border rounded shadow-sm d-flex align-items-center p-0 overflow-hidden" style="transition: all 0.2s;">
                                        <div class="ps-3 d-flex align-items-center">
                                            <?php $checked = ($respostasGravadas[$q['id']] ?? null) == $opt['id'] ? 'checked' : ''; ?>
                                            <input class="questao-radio" type="radio" name="respostas[<?php echo $q['id']; ?>]" 
                                                   id="s<?php echo $i; ?>_q<?php echo $q['id']; ?>_opt<?php echo $opt['id']; ?>" value="<?php echo $opt['id']; ?>" 
                                                   data-questao="<?php echo $q['id']; ?>"
                                                   data-step="<?php echo $i; ?>" <?php echo $checked; ?>
                                                   style="width: 20px; height: 20px; cursor: pointer;">
                                        </div>
                                        <label class="d-flex align-items-center p-3 flex-grow-1 m-0" for="s<?php echo $i; ?>_q<?php echo $q['id']; ?>_opt<?php echo $opt['id']; ?>" style="cursor: pointer;">
                                            <span class="option-letter me-3 fw-bold"><?php echo chr(65 + $j); ?>)</span>
                                            <div class="option-text flex-grow-1"><?php echo $opt['resposta']; ?></div>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="card-footer bg-white p-4 border-top d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-secondary btn-lg px-4 prev-btn" data-prev="<?php echo $i - 1; ?>" <?php echo $i === 0 ? 'disabled style="visibility:hidden;"' : ''; ?>>
                                <i class="bi bi-arrow-left me-2"></i> Anterior
                            </button>
                            
                            <?php if ($i < count($questoes) - 1): ?>
                                <button type="button" class="btn btn-primary btn-lg px-5 next-btn" data-next="<?php echo $i + 1; ?>">
                                    Próxima <i class="bi bi-arrow-right ms-2"></i>
                                </button>
                            <?php else: ?>
                                <button type="submit" id="finishBtn" class="btn btn-success btn-lg px-5 submit-btn">
                                    <i class="bi bi-check2-all me-2"></i> Finalizar Prova
                                </button>
<?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const totalSteps = <?php echo count($questoes); ?>;
    const form = document.getElementById('examForm');
    const timerEl = document.getElementById('test-timer');
    const endTime = new Date(timerEl.getAttribute('data-end')).getTime();
    const progressEl = document.getElementById('examProgress');
    const currentStepEl = document.getElementById('currentStep');

    // Timer logic
    function updateTimer() {
        const now = new Date().getTime();
        const diff = endTime - now;

        if (diff <= 0) {
            timerEl.innerHTML = "00:00:00";
            alert("O tempo acabou! Sua prova será enviada automaticamente.");
            form.submit();
            return;
        }

        const hours = Math.floor(diff / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((diff % (1000 * 60)) / 1000);

        timerEl.innerHTML = 
            String(hours).padStart(2, '0') + ":" + 
            String(minutes).padStart(2, '0') + ":" + 
            String(seconds).padStart(2, '0');
    }
    setInterval(updateTimer, 1000);
    updateTimer();

    // Wizard logic
    document.querySelectorAll('.next-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const currentStep = parseInt(this.getAttribute('data-next')) - 1;
            const nextStep = parseInt(this.getAttribute('data-next'));

            // Verificar se a questão atual foi respondida
            const stepEl = document.getElementById('step_' + currentStep);
            const answered = stepEl.querySelector('input[type="radio"]:checked');
            
            if (!answered) {
                alert("Por favor, responda a questão antes de prosseguir.");
                return;
            }

            document.getElementById('step_' + currentStep).style.display = 'none';
            document.getElementById('step_' + nextStep).style.display = 'block';

            updateProgress(nextStep);
        });
    });

    document.querySelectorAll('.prev-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const currentStep = parseInt(this.getAttribute('data-prev')) + 1;
            const prevStep = parseInt(this.getAttribute('data-prev'));

            document.getElementById('step_' + currentStep).style.display = 'none';
            document.getElementById('step_' + prevStep).style.display = 'block';

            updateProgress(prevStep);
        });
    });

    function updateProgress(step) {
        const progress = ((step + 1) / totalSteps) * 100;
        progressEl.style.width = progress + '%';
        currentStepEl.innerText = step + 1;
    }

    // Validation before finish
    form.addEventListener('submit', function(e) {
        const unanswered = [];
        <?php foreach ($questoes as $i => $q): ?>
        if (!form.querySelector('input[name="respostas[<?php echo $q['id']; ?>]"]:checked')) {
            unanswered.push(<?php echo $i + 1; ?>);
        }
        <?php endforeach; ?>

        if (unanswered.length > 0) {
            e.preventDefault();
            alert("Você ainda não respondeu as seguintes questões: " + unanswered.join(', ') + ". Responda todas para finalizar.");
            
            // Go to first unanswered step
            const firstUnanswered = unanswered[0] - 1;
            document.querySelectorAll('.question-step').forEach(s => s.style.display = 'none');
            document.getElementById('step_' + firstUnanswered).style.display = 'block';
            updateProgress(firstUnanswered);
            return;
        }

        if (!confirm('Tem certeza que deseja finalizar e entregar sua prova agora?')) {
            e.preventDefault();
            return;
        }
        form.submitted = true;
    });

    // Prevent accidental reload
    window.addEventListener('beforeunload', function (e) {
        if (!form.submitted) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    // Tornar toda a área da alternativa clicável
    document.querySelectorAll('.option-item').forEach(item => {
        item.addEventListener('click', function(e) {
            if (e.target.tagName !== 'INPUT') {
                const radio = this.querySelector('input[type="radio"]');
                if (radio) {
                    radio.checked = true;
                    // Disparar evento change manualmente para rodar o salvamento AJAX e destaques
                    radio.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
        });
    });

    // Progressive Saving via AJAX
    document.querySelectorAll('.questao-radio').forEach(radio => {
        radio.addEventListener('change', function() {
            const questaoId = this.getAttribute('data-questao');
            const respostaId = this.value;
            const provaId = <?php echo $pa['prova_id']; ?>;

            // Highlight the option immediately for UX
            const container = this.closest('.card-body');
            container.querySelectorAll('.option-item').forEach(item => {
                item.classList.remove('border-primary');
                item.style.backgroundColor = '';
            });
            this.closest('.option-item').classList.add('border-primary');

            // Send to server
            fetch('index.php?m=aluno_provas&a=ajax_save_answer', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `prova_id=${provaId}&questao_id=${questaoId}&resposta_id=${respostaId}`
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    console.error('Erro ao salvar resposta:', data.message);
                    // Opcionalmente mostrar um toast ou alerta discreto
                }
            })
            .catch(error => console.error('Erro na requisição AJAX:', error));
        });
    });
});
</script>

<style>
.question-step { position: relative; z-index: 10; }
/* Estilo da Prova - Fluído e sem 'boxes' internos */
.exam-container {
    min-height: 100vh;
    background-color: #f5f6fa;
    padding-bottom: 3rem;
}
.exam-header {
    position: sticky;
    top: 0;
    z-index: 1020;
}
.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: none;
}

.option-item:hover {
    background-color: #f8f9fa;
    border-color: #0d6efd !important;
}
.option-item:has(input:checked) {
    background-color: #f0f7ff;
    border-color: #0d6efd !important;
    box-shadow: 0 0 0 0.2rem rgba(13,110,253,.25) !important;
}
.option-letter, .option-text {
    pointer-events: none;
}
.option-letter {
    color: #0d6efd;
}
.option-text {
    display: block;
}
</style>
