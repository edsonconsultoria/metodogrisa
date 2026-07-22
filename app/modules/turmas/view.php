<?php
// prevenção a erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// app/modules/turmas/view.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';

$tableName = '104_turmas';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$engine = new CrudEngine($pdo, $tableName);

$engine->setLookups([
    'faculdade' => ['table' => '104_faculdades', 'label' => 'nome']
]);

$record = $engine->getRecord($id);
if (!$record) {
    die("Registro não encontrado.");
}

$baseHref = BASE_URL . 'index.php';

$formatPtBrDate = function ($date) {
    if (!$date || $date === '0000-00-00')
        return '—';
    return date('d/m/Y', strtotime($date));
};

// Alunos da Turma
$alunos = [];
$alunosCount = 0;
if ($id > 0) {
    $stmtAlunos = $pdo->prepare("
        SELECT a.id, a.aluno, a.emitido_cert_metodo_grisa, a.emitido_cert_universidade,
               a.diploma, a.rg_carteira_conselho, a.cpf, a.cer_nascimento_casamento,
               a.comprovante_residencia, a.tcc, a.diarios_classe,
               p.nome, p.email, p.fone_1
        FROM 104_alunos a
        JOIN 101_pessoas p ON p.id = a.aluno
        WHERE a.turma = :turma_id
        ORDER BY p.nome ASC
    ");
    $stmtAlunos->execute([':turma_id' => $id]);
    $alunos = $stmtAlunos->fetchAll(PDO::FETCH_ASSOC);
    $alunosCount = count($alunos);

    // Disciplinas da Turma
    $stmtDisc = $pdo->prepare("
        SELECT td.id, d.nome AS disciplina_nome, d.carga_horaria, p_disc.apelido AS professor_nome
        FROM 104_turmas_disciplinas td
        JOIN 104_disciplinas d ON td.disciplina = d.id
        LEFT JOIN 104_professores p_disc ON td.professor = p_disc.id
        WHERE td.turma = :turma_id
        ORDER BY d.nome ASC
    ");
    $stmtDisc->execute([':turma_id' => $id]);
    $disciplinas = $stmtDisc->fetchAll(PDO::FETCH_ASSOC);
    $disciplinasCount = count($disciplinas);

    // Provas da Turma
    $stmtProvas = $pdo->prepare("
        SELECT id, data, inicio, termino
        FROM 104_provas
        WHERE turma = :turma_id
        ORDER BY data DESC, inicio DESC
    ");
    $stmtProvas->execute([':turma_id' => $id]);
    $provas = $stmtProvas->fetchAll(PDO::FETCH_ASSOC);
    $provasCount = count($provas);
}

$activeTab = $_GET['tab'] ?? 'dados';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Visualizar <?php echo htmlspecialchars($record['nome']); ?></h4>
    <div class="d-flex gap-2">
        <a href="<?php echo $baseHref; ?>?m=turmas&a=form&id=<?php echo $id; ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil"></i> Editar
        </a>
        <a href="<?php echo $baseHref; ?>?m=turmas&a=list" class="btn btn-outline-secondary btn-sm">Voltar</a>
    </div>
</div>

<ul class="nav nav-tabs mb-3" id="turmaViewTabs" role="tablist">
    <li class="nav-item">
        <button class="nav-link <?php echo $activeTab === 'dados' ? 'active' : ''; ?>" id="dados-tab"
            data-bs-toggle="tab" data-bs-target="#dados" type="button" role="tab">
            Dados da Turma
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link <?php echo $activeTab === 'alunos' ? 'active' : ''; ?>" id="alunos-tab"
            data-bs-toggle="tab" data-bs-target="#alunos" type="button" role="tab">
            Alunos (<?php echo $alunosCount; ?>)
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link <?php echo $activeTab === 'disciplinas' ? 'active' : ''; ?>" id="disciplinas-tab"
            data-bs-toggle="tab" data-bs-target="#disciplinas" type="button" role="tab">
            Disciplinas (<?php echo $disciplinasCount; ?>)
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link <?php echo $activeTab === 'provas' ? 'active' : ''; ?>" id="provas-tab"
            data-bs-toggle="tab" data-bs-target="#provas" type="button" role="tab">
            Provas (<?php echo $provasCount; ?>)
        </button>
    </li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade <?php echo $activeTab === 'dados' ? 'show active' : ''; ?>" id="dados" role="tabpanel">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-12">
                        <label class="text-muted small d-block mb-1">Nome da Turma</label>
                        <div class="fw-bold fs-5"><?php echo htmlspecialchars($record['nome']); ?></div>
                    </div>

                    <div class="col-md-6">
                        <label class="text-muted small d-block mb-1">Data de Início</label>
                        <div class="fw-bold"><?php echo $formatPtBrDate($record['inicio']); ?></div>
                    </div>

                    <div class="col-md-6">
                        <label class="text-muted small d-block mb-1">Data de Término</label>
                        <div class="fw-bold"><?php echo $formatPtBrDate($record['termino']); ?></div>
                    </div>

                    <div class="col-md-12">
                        <label class="text-muted small d-block mb-1">Faculdade</label>
                        <div class="fw-bold"><?php echo htmlspecialchars($record['faculdade_label'] ?? '—'); ?></div>
                    </div>

                    <div class="col-md-12">
                        <label class="text-muted small d-block mb-1">Observação</label>
                        <div class="fw-bold"><?php echo nl2br(htmlspecialchars($record['observacao'] ?? '—')); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade <?php echo $activeTab === 'alunos' ? 'show active' : ''; ?>" id="alunos" role="tabpanel">
        <div class="card shadow-sm">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <span class="fw-bold">Lista de Alunos</span>
                <a href="<?php echo $baseHref; ?>?m=alunos&a=form&turma=<?php echo $id; ?>"
                    class="btn btn-primary btn-sm">+ Vincular Aluno</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 100px;">Ações</th>
                                <th style="width: 80px;">ID</th>
                                <th>Nome</th>
                                <th class="text-center">Cert MG</th>
                                <th class="text-center">Cert Uni</th>
                                <th>Documentos</th>
                                <th>Contato</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($alunos)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">Nenhum aluno vinculado a esta turma.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($alunos as $aluno): ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo $baseHref; ?>?m=alunos&a=view&id=<?php echo $aluno['id']; ?>"
                                                class="action-btn action-btn--ver" title="Visualizar"><i
                                                    class="bi bi-eye"></i></a>
                                            <a href="<?php echo $baseHref; ?>?m=alunos&a=form&id=<?php echo $aluno['id']; ?>"
                                                class="action-btn action-btn--editar" title="Editar"><i
                                                    class="bi bi-pencil"></i></a>
                                        </td>
                                        <td class="text-muted small">
                                            <?php echo $aluno['id']; ?>
                                        </td>
                                        <td class="fw-semibold">
                                            <a
                                                href="<?php echo $baseHref; ?>?m=alunos&a=form&id=<?php echo $aluno['id']; ?>&return=<?php echo urlencode('m=alunos&a=list'); ?>">
                                                <?php echo htmlspecialchars($aluno['nome']); ?>
                                            </a>
                                        </td>
                                        <td class="text-center">
                                            <div class="cert-toggle-wrapper" style="cursor: pointer;"
                                                onclick="toggleCert(<?php echo $aluno['id']; ?>, 'emitido_cert_metodo_grisa', this)"
                                                data-status="<?php echo ($aluno['emitido_cert_metodo_grisa'] ?? '') === '1' ? '1' : '0'; ?>">
                                                <?php if (($aluno['emitido_cert_metodo_grisa'] ?? '') === '1'): ?>
                                                    <i class="bi bi-check-lg fw-bold text-success" title="Sim"></i>
                                                <?php else: ?>
                                                    <i class="bi bi-x-lg fw-bold text-danger" title="Não"></i>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="cert-toggle-wrapper" style="cursor: pointer;"
                                                onclick="toggleCert(<?php echo $aluno['id']; ?>, 'emitido_cert_universidade', this)"
                                                data-status="<?php echo ($aluno['emitido_cert_universidade'] ?? '') === '1' ? '1' : '0'; ?>">
                                                <?php if (($aluno['emitido_cert_universidade'] ?? '') === '1'): ?>
                                                    <i class="bi bi-check-lg fw-bold text-success" title="Sim"></i>
                                                <?php else: ?>
                                                    <i class="bi bi-x-lg fw-bold text-danger" title="Não"></i>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="small">
                                            <?php
                                            $docFields = [
                                                'rg_carteira_conselho' => 'RG/Cart. Conselho',
                                                'cpf' => 'CPF',
                                                'cer_nascimento_casamento' => 'Cert. Nasc./Casamento',
                                                'comprovante_residencia' => 'Comp. Residência',
                                                'tcc' => 'TCC',
                                                'diarios_classe' => 'Diários de Classe',
                                            ];
                                            foreach ($docFields as $field => $label):
                                                $ok = ($aluno[$field] ?? '0') === '1';
                                                ?>
                                                <div>
                                                    <i class="bi <?php echo $ok ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger'; ?> me-1"
                                                        title="<?php echo htmlspecialchars($label); ?>"></i>
                                                    <span class="<?php echo $ok ? 'text-success' : 'text-danger'; ?>">
                                                        <?php echo htmlspecialchars($label); ?>
                                                    </span>
                                                </div>
                                            <?php endforeach; ?>
                                            <div class="mt-1 pt-1 border-top">
                                                <span class="text-muted">Diploma:</span>
                                                <span class="fw-semibold">
                                                    <?php echo htmlspecialchars($aluno['diploma'] ?: '—'); ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="small">
                                            <?php if ($aluno['fone_1']):
                                                $wa_number = preg_replace('/\D/', '', $aluno['fone_1']);
                                                // Adiciona 55 se parecer um número brasileiro sem DDI
                                                if (strlen($wa_number) >= 10 && strlen($wa_number) <= 11 && substr($wa_number, 0, 2) !== '55') {
                                                    $wa_number = '55' . $wa_number;
                                                }
                                                ?>
                                                <div>
                                                    <a href="https://wa.me/<?php echo $wa_number; ?>" target="_blank"
                                                        class="text-decoration-none" title="Abrir no WhatsApp">
                                                        <i
                                                            class="bi bi-whatsapp text-success me-1"></i><?php echo htmlspecialchars($aluno['fone_1']); ?>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($aluno['email']): ?>
                                                <div>
                                                    <a href="mailto:<?php echo htmlspecialchars($aluno['email']); ?>"
                                                        class="text-decoration-none" title="Enviar e-mail">
                                                        <i
                                                            class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($aluno['email']); ?>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade <?php echo $activeTab === 'disciplinas' ? 'show active' : ''; ?>" id="disciplinas"
        role="tabpanel">
        <div class="card shadow-sm">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <span class="fw-bold">Disciplinas da Turma</span>
                <a href="<?php echo $baseHref; ?>?m=turmas_disciplinas&a=form&turma=<?php echo $id; ?>&return=<?php echo urlencode('m=turmas&a=view&id=' . $id . '&tab=disciplinas'); ?>"
                    class="btn btn-primary btn-sm">+ Vincular Disciplina</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 100px;">Ações</th>
                                <th>Disciplina</th>
                                <th>Carga Horária</th>
                                <th>Professor/Facilitador</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($disciplinas)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">Nenhuma disciplina vinculada a esta
                                        turma.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($disciplinas as $disc): ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo $baseHref; ?>?m=turmas_disciplinas&a=view&id=<?php echo $disc['id']; ?>"
                                                class="action-btn action-btn--ver" title="Visualizar"><i
                                                    class="bi bi-eye"></i></a>
                                            <a href="<?php echo $baseHref; ?>?m=turmas_disciplinas&a=form&id=<?php echo $disc['id']; ?>&return=<?php echo urlencode('m=turmas&a=view&id=' . $id . '&tab=disciplinas'); ?>"
                                                class="action-btn action-btn--editar" title="Editar"><i
                                                    class="bi bi-pencil"></i></a>
                                        </td>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($disc['disciplina_nome']); ?></td>
                                        <td><?php echo htmlspecialchars($disc['carga_horaria'] ?? '—'); ?>h</td>
                                        <td><?php echo htmlspecialchars($disc['professor_nome'] ?? '—'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade <?php echo $activeTab === 'provas' ? 'show active' : ''; ?>" id="provas"
        role="tabpanel">
        <div class="card shadow-sm">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <span class="fw-bold">Provas da Turma</span>
                <a href="<?php echo $baseHref; ?>?m=104_provas&a=form&turma=<?php echo $id; ?>&return=<?php echo urlencode('m=turmas&a=view&id=' . $id . '&tab=provas'); ?>"
                    class="btn btn-primary btn-sm">+ Nova Prova</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 120px;">Ações</th>
                                <th>Data</th>
                                <th>Início</th>
                                <th>Término</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($provas)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">Nenhuma prova cadastrada para esta turma.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($provas as $p):
                                    $status = 'Agendada';
                                    $now = date('Y-m-d H:i:s');
                                    $start = $p['data'] . ' ' . $p['inicio'];
                                    $end = $p['data'] . ' ' . $p['termino'];
                                    if ($now > $end) $status = 'Encerrada';
                                    elseif ($now >= $start) $status = 'Em andamento';
                                    ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo $baseHref; ?>?m=104_provas&a=view&id=<?php echo $p['id']; ?>"
                                                class="action-btn action-btn--ver" title="Visualizar"><i
                                                    class="bi bi-eye"></i></a>
                                            <a href="<?php echo $baseHref; ?>?m=104_provas&a=form&id=<?php echo $p['id']; ?>&return=<?php echo urlencode('m=turmas&a=view&id=' . $id . '&tab=provas'); ?>"
                                                class="action-btn action-btn--editar" title="Editar"><i
                                                    class="bi bi-pencil"></i></a>
                                            <a href="<?php echo $baseHref; ?>?m=104_provas_alunos&a=list&prova=<?php echo $p['id']; ?>"
                                                class="action-btn action-btn--copiar" title="Ver Resultados" style="background-color: #6f42c1; color: white;"><i
                                                    class="bi bi-trophy"></i></a>
                                        </td>
                                        <td class="fw-semibold"><?php echo $formatPtBrDate($p['data']); ?></td>
                                        <td><?php echo substr($p['inicio'], 0, 5); ?></td>
                                        <td><?php echo substr($p['termino'], 0, 5); ?></td>
                                        <td>
                                            <span class="badge <?php 
                                                echo $status === 'Encerrada' ? 'bg-secondary' : ($status === 'Em andamento' ? 'bg-success' : 'bg-info'); 
                                            ?>">
                                                <?php echo $status; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleCert(id, field, element) {
        const formData = new FormData();
        formData.append('id', id);
        formData.append('field', field);

        element.style.opacity = '0.5';
        element.style.pointerEvents = 'none';

        fetch('<?php echo $baseHref; ?>?m=alunos&a=ajax_toggle', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const isCheck = data.newValue === '1';
                    element.setAttribute('data-status', data.newValue);
                    element.innerHTML = isCheck
                        ? '<i class="bi bi-check-lg fw-bold text-success" title="Sim"></i>'
                        : '<i class="bi bi-x-lg fw-bold text-danger" title="Não"></i>';
                } else {
                    alert('Erro: ' + (data.error || 'Erro desconhecido'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Erro ao processar a requisição.');
            })
            .finally(() => {
                element.style.opacity = '1';
                element.style.pointerEvents = 'auto';
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Tab switching persistence via URL
        var tabButtons = document.querySelectorAll('button[data-bs-toggle="tab"]');
        tabButtons.forEach(function (button) {
            button.addEventListener('shown.bs.tab', function (event) {
                var tabId = event.target.getAttribute('data-bs-target').replace('#', '');
                var url = new URL(window.location);
                url.searchParams.set('tab', tabId);
                window.history.replaceState({}, '', url);
            });
        });

        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    });
</script>
