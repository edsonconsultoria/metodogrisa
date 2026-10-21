<?php
// app/modules/provas_alunos/form.php
$_TABLE = '104_provas_alunos';
$_LOOKUPS = [
    'aluno' => ['table' => '101_pessoas', 'label' => 'nome']
];

$_CHECKBOX_FIELDS = ['concluida', 'autorizacao_especial'];
$_READONLY_FIELDS = ['nota'];
$_ADMIN_ONLY_FIELDS = ['autorizacao_especial'];

// Custom lookups para o select de Prova com informações detalhadas
$stmtProvas = $pdo->query("
    SELECT p.id, p.tipo, p.data, p.inicio, t.nome as turma_nome
    FROM 104_provas p
    JOIN 104_turmas t ON t.id = p.turma
    ORDER BY p.data DESC, p.inicio DESC
");
$provasOpts = $stmtProvas->fetchAll();

$lookupsData['prova'] = [];
foreach ($provasOpts as $po) {
    $date = date('d/m/Y', strtotime($po['data']));
    $time = substr($po['inicio'], 0, 5);
    $label = "{$po['tipo']} | {$date} | {$po['turma_nome']} | {$time}";
    $lookupsData['prova'][] = ['id' => $po['id'], 'nome' => $label];
}

// Buscar informações extras da prova para exibir no topo
$pa_id = $_GET['id'] ?? null;
$provaInfo = null;

if ($pa_id) {
    $stmt = $pdo->prepare("
        SELECT p.data, p.inicio, p.tipo, t.nome as turma_nome, t.id as turma_id
        FROM 104_provas_alunos pa
        JOIN 104_provas p ON p.id = pa.prova
        JOIN 104_turmas t ON t.id = p.turma
        WHERE pa.id = ?
    ");
    $stmt->execute([$pa_id]);
    $provaInfo = $stmt->fetch();
}

// Inicializar Aluno vazio ou filtrado se já tiver prova (edição)
$lookupsData['aluno'] = [];
if ($provaInfo) {
    $stmtA = $pdo->prepare("
        SELECT p.id, p.nome 
        FROM 104_alunos a 
        JOIN 101_pessoas p ON p.id = a.aluno 
        WHERE a.turma = ?
        ORDER BY p.nome
    ");
    $stmtA->execute([$provaInfo['turma_id']]);
    $alunosDaTurma = $stmtA->fetchAll();
    foreach ($alunosDaTurma as $at) {
        $lookupsData['aluno'][] = ['id' => $at['id'], 'nome' => $at['nome']];
    }
}

if ($provaInfo): ?>
    <div class="card shadow-sm mb-4 border-0 bg-light">
        <div class="card-body py-3">
            <div class="row align-items-center">
                <div class="col-auto">
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center"
                        style="width: 45px; height: 45px;">
                        <i class="bi bi-journal-text h4 mb-0"></i>
                    </div>
                </div>
                <div class="col">
                    <h6 class="mb-1 text-primary text-uppercase small fw-bold">Contexto da Prova</h6>
                    <div class="d-flex gap-4 flex-wrap">
                        <span><i class="bi bi-people me-1"></i> <strong>Turma:</strong>
                            <?php echo htmlspecialchars($provaInfo['turma_nome']); ?></span>
                        <span><i class="bi bi-calendar3 me-1"></i> <strong>Data:</strong>
                            <?php echo date('d/m/Y', strtotime($provaInfo['data'])); ?></span>
                        <span><i class="bi bi-clock me-1"></i> <strong>Início:</strong>
                            <?php echo substr($provaInfo['inicio'], 0, 5); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../generic/form.php'; ?>

<script>
    $(document).ready(function () {
        const $provaSelect = $('select[name="prova"]');
        const $alunoSelect = $('select[name="aluno"]');

        if ($provaSelect.length && $alunoSelect.length) {
            const updateAlunos = function (selectedId = null) {
                const provaId = $provaSelect.val();
                if (!provaId) {
                    $alunoSelect.html('<option value="">Selecione primeiro a prova...</option>');
                    $alunoSelect.trigger('change');
                    return;
                }

                // Guardar valor atual se não foi passado um específico
                const currentVal = selectedId || $alunoSelect.val();

                // Mostrar estado de carregamento
                $alunoSelect.html('<option value="">Carregando alunos...</option>');
                $alunoSelect.trigger('change');

                $.getJSON('index.php?m=104_provas_alunos&a=ajax_alunos&prova_id=' + provaId + '&current_id=<?php echo $pa_id ?? ''; ?>', function (data) {
                    let html = '<option value="">Selecione o aluno...</option>';
                    data.forEach(function (item) {
                        const selected = (item.id == currentVal) ? 'selected' : '';
                        html += `<option value="${item.id}" ${selected}>${item.nome}</option>`;
                    });

                    $alunoSelect.html(html);
                    $alunoSelect.trigger('change');
                }).fail(function () {
                    $alunoSelect.html('<option value="">Erro ao carregar alunos</option>');
                    $alunoSelect.trigger('change');
                });
            };

            // Evento de mudança
            $provaSelect.on('change', function () {
                updateAlunos();
            });

            // Se já houver valor de prova mas a lista de alunos estiver vazia ou com valor padrão (ex: novo cadastro vindo com prova no URL)
            if ($provaSelect.val() && ($alunoSelect.find('option').length <= 1)) {
                updateAlunos();
            }

            // Lógica de Autorização Especial
            const $concluida = $('input[name="concluida"]');
            const $autorizacao = $('input[name="autorizacao_especial"]');

            const updateAutorizacaoState = function () {
                if ($concluida.is(':checked')) {
                    $autorizacao.prop('disabled', true);
                    if ($autorizacao.is(':checked')) {
                        $autorizacao.prop('checked', false);
                    }
                } else {
                    // Só habilita se for ADMIN (o PHP já cuida disso via atributo disabled, 
                    // mas aqui garantimos que o JS respeite a regra de negócio do estado)
                    <?php if (current_user_is_at_least_admin()): ?>
                        $autorizacao.prop('disabled', false);
                    <?php endif; ?>
                }
            };

            $concluida.on('change', updateAutorizacaoState);
            updateAutorizacaoState();
        }
    });
</script>