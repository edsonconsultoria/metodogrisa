<?php
// app/modules/rel_pendencias/relatorio.php

require_once __DIR__ . '/../../config/config.php';

$baseHref = BASE_URL . 'index.php';

$turmaFiltro = (int) ($_GET['turma_id'] ?? 0);

// Turmas para o filtro
$stmtTurmas = $pdo->query("SELECT id, nome FROM 104_turmas ORDER BY nome ASC");
$turmas = $stmtTurmas->fetchAll(PDO::FETCH_ASSOC);

// Itens de documentação verificados para pendência
$docFields = [
    'rg_carteira_conselho' => 'RG/Carteira Conselho',
    'cpf' => 'CPF',
    'cer_nascimento_casamento' => 'Cert. Nascimento/Casamento',
    'comprovante_residencia' => 'Comprovante Residência',
    'fge' => 'FGE',
    'tcc' => 'TCC',
    'uso_imagem' => 'Uso de Imagem',
    'cert_grad' => 'Cert Grad',
    'emitido_cert_metodo_grisa' => 'Cert MG',
    'emitido_cert_universidade' => 'Cert Uni',
];

$guia = $_GET['guia'] ?? 'todas';
if (!in_array($guia, ['todas', 'pendentes', 'sem_pendencias'])) {
    $guia = 'todas';
}

$diplomaFiltroSel = $_GET['diploma_filtro'] ?? 'todos';
if (!in_array($diplomaFiltroSel, ['todos', 'pos', 'livre'])) {
    $diplomaFiltroSel = 'todos';
}

$todosAlunos = [];
$alunosFiltradosDiploma = [];
$alunosComPendencias = [];
$alunosSemPendencias = [];
$alunos = [];

$totalTodos = 0;
$totalPos = 0;
$totalLivre = 0;

if ($turmaFiltro > 0) {
    $stmt = $pdo->prepare("
        SELECT a.id, a.diploma, a." . implode(', a.', array_keys($docFields)) . ",
               p.nome AS aluno_nome
        FROM 104_alunos a
        LEFT JOIN 101_pessoas p ON p.id = a.aluno
        WHERE a.turma = :turma_id
        ORDER BY p.nome ASC
    ");
    $stmt->execute([':turma_id' => $turmaFiltro]);
    $todosAlunos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($todosAlunos as $aluno) {
        $totalTodos++;
        $d = $aluno['diploma'] ?? '';
        if ($d === 'Pós Graduação') {
            $totalPos++;
        } elseif ($d === 'Curso Livre') {
            $totalLivre++;
        }
        
        // Filter by diploma tab first
        if ($diplomaFiltroSel === 'pos' && $d !== 'Pós Graduação') {
            continue;
        }
        if ($diplomaFiltroSel === 'livre' && $d !== 'Curso Livre') {
            continue;
        }
        $alunosFiltradosDiploma[] = $aluno;
    }

    foreach ($alunosFiltradosDiploma as $aluno) {
        $hasPendency = false;
        foreach ($docFields as $field => $label) {
            if (($aluno[$field] ?? '') != '1') {
                $hasPendency = true;
                break;
            }
        }
        if ($hasPendency) {
            $alunosComPendencias[] = $aluno;
        } else {
            $alunosSemPendencias[] = $aluno;
        }
    }

    if ($guia === 'pendentes') {
        $alunos = $alunosComPendencias;
    } elseif ($guia === 'sem_pendencias') {
        $alunos = $alunosSemPendencias;
    } else {
        $alunos = $alunosFiltradosDiploma;
    }
}

$pdfUrl = $baseHref . '?' . http_build_query([
    'm' => 'rel_pendencias',
    'a' => 'relatorio_pdf',
    'turma_id' => $turmaFiltro,
    'diploma_filtro' => $diplomaFiltroSel,
    'guia' => $guia
]);
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Rel Pendências</h4>
    <?php if ($turmaFiltro > 0): ?>
        <a href="<?php echo $pdfUrl; ?>" target="_blank" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-file-earmark-pdf"></i> Imprimir PDF
        </a>
    <?php endif; ?>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="get" class="row g-3">
            <input type="hidden" name="m" value="rel_pendencias">
            <input type="hidden" name="a" value="relatorio">

            <div class="col-md-8">
                <label class="form-label">Turma</label>
                <select name="turma_id" class="form-select" onchange="this.form.submit()">
                    <option value="">Selecione a Turma...</option>
                    <?php foreach ($turmas as $t): ?>
                        <option value="<?php echo $t['id']; ?>" <?php echo ($turmaFiltro == $t['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($t['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Gerar Relatório</button>
            </div>
        </form>
    </div>
</div>

<?php if ($turmaFiltro > 0): ?>
    <!-- Abas de Diploma -->
    <ul class="nav nav-tabs mb-3" id="diplomaTabs">
        <li class="nav-item">
            <a class="nav-link <?php echo $diplomaFiltroSel === 'todos' ? 'active fw-bold' : ''; ?>" 
               href="<?php echo $baseHref; ?>?m=rel_pendencias&a=relatorio&turma_id=<?php echo $turmaFiltro; ?>&diploma_filtro=todos&guia=<?php echo $guia; ?>">
                Todos os Diplomas <span class="badge bg-secondary text-white ms-1"><?php echo $totalTodos; ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $diplomaFiltroSel === 'pos' ? 'active fw-bold' : ''; ?>" 
               href="<?php echo $baseHref; ?>?m=rel_pendencias&a=relatorio&turma_id=<?php echo $turmaFiltro; ?>&diploma_filtro=pos&guia=<?php echo $guia; ?>">
                Pós Graduação <span class="badge bg-secondary text-white ms-1"><?php echo $totalPos; ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $diplomaFiltroSel === 'livre' ? 'active fw-bold' : ''; ?>" 
               href="<?php echo $baseHref; ?>?m=rel_pendencias&a=relatorio&turma_id=<?php echo $turmaFiltro; ?>&diploma_filtro=livre&guia=<?php echo $guia; ?>">
                Curso Livre <span class="badge bg-secondary text-white ms-1"><?php echo $totalLivre; ?></span>
            </a>
        </li>
    </ul>

    <!-- Sub-filtros de Pendências -->
    <ul class="nav nav-pills mb-3" id="relatorioTabs">
        <li class="nav-item">
            <a class="nav-link <?php echo $guia === 'todas' ? 'active fw-bold' : 'text-secondary'; ?>" 
               href="<?php echo $baseHref; ?>?m=rel_pendencias&a=relatorio&turma_id=<?php echo $turmaFiltro; ?>&diploma_filtro=<?php echo $diplomaFiltroSel; ?>&guia=todas">
                Todos <span class="badge <?php echo $guia === 'todas' ? 'bg-primary text-white' : 'bg-light text-secondary border'; ?> ms-1"><?php echo count($alunosFiltradosDiploma); ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $guia === 'pendentes' ? 'active fw-bold' : 'text-secondary'; ?>" 
               href="<?php echo $baseHref; ?>?m=rel_pendencias&a=relatorio&turma_id=<?php echo $turmaFiltro; ?>&diploma_filtro=<?php echo $diplomaFiltroSel; ?>&guia=pendentes">
                Com Pendências <span class="badge <?php echo $guia === 'pendentes' ? 'bg-danger text-white' : 'bg-light text-secondary border'; ?> ms-1"><?php echo count($alunosComPendencias); ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $guia === 'sem_pendencias' ? 'active fw-bold' : 'text-secondary'; ?>" 
               href="<?php echo $baseHref; ?>?m=rel_pendencias&a=relatorio&turma_id=<?php echo $turmaFiltro; ?>&diploma_filtro=<?php echo $diplomaFiltroSel; ?>&guia=sem_pendencias">
                Sem Pendências <span class="badge <?php echo $guia === 'sem_pendencias' ? 'bg-success text-white' : 'bg-light text-secondary border'; ?> ms-1"><?php echo count($alunosSemPendencias); ?></span>
            </a>
        </li>
    </ul>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Aluno</th>
                        <th>Pendências</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($turmaFiltro <= 0): ?>
                        <tr>
                            <td colspan="2" class="text-center py-4 text-muted">Selecione uma turma para ver as
                                pendências.</td>
                        </tr>
                    <?php elseif (empty($alunos)): ?>
                        <tr>
                            <td colspan="2" class="text-center py-4 text-muted">Nenhum aluno encontrado para esta
                                turma.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($alunos as $aluno): ?>
                            <?php
                            $pendencias = [];
                            foreach ($docFields as $field => $label) {
                                if (($aluno[$field] ?? '') != '1') {
                                    $pendencias[] = $label;
                                }
                            }
                            ?>
                            <tr>
                                <td class="fw-semibold">
                                    <a href="javascript:void(0)" class="text-primary text-decoration-none edit-aluno-trigger" data-id="<?php echo htmlspecialchars($aluno['id']); ?>" data-nome="<?php echo htmlspecialchars($aluno['aluno_nome'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($aluno['aluno_nome'] ?? '—'); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if (empty($pendencias)): ?>
                                        <span class="badge bg-success">Sem pendências</span>
                                    <?php else: ?>
                                        <?php foreach ($pendencias as $p): ?>
                                            <span class="badge bg-danger me-1 mb-1"><?php echo htmlspecialchars($p); ?></span>
                                        <?php endforeach; ?>
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

<!-- Modal para Editar Aluno -->
<div class="modal fade" id="editAlunoModal" tabindex="-1" aria-labelledby="editAlunoModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light border-bottom-0 py-3">
                <h5 class="modal-title fw-bold" id="editAlunoModalLabel">
                    <i class="bi bi-pencil-square text-primary me-2"></i>
                    Editar Aluno: <span id="modalAlunoNome" class="text-muted"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" style="position: relative; min-height: 450px;">
                <!-- Loading Spinner -->
                <div id="modalLoadingSpinner" class="d-flex flex-column justify-content-center align-items-center position-absolute w-100 h-100 bg-white" style="z-index: 10; top: 0; left: 0;">
                    <div class="spinner-border text-primary mb-2" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                    <span class="text-muted small">Carregando formulário do aluno...</span>
                </div>
                <iframe id="editAlunoIframe" src="" style="width: 100%; height: 75vh; border: none;"></iframe>
            </div>
        </div>
    </div>
</div>

<style>
.edit-aluno-trigger {
    transition: color 0.15s ease-in-out;
    cursor: pointer;
}
.edit-aluno-trigger:hover {
    color: #0a58ca !important;
    text-decoration: underline !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Restaurar a posição do scroll anterior se existir
    const savedScrollPos = sessionStorage.getItem('relatorioScrollPosition');
    if (savedScrollPos !== null) {
        window.scrollTo(0, parseFloat(savedScrollPos));
        sessionStorage.removeItem('relatorioScrollPosition');
    }

    const editModalEl = document.getElementById('editAlunoModal');
    if (!editModalEl) return;

    const editModal = new bootstrap.Modal(editModalEl);
    const iframe = document.getElementById('editAlunoIframe');
    const modalAlunoNome = document.getElementById('modalAlunoNome');
    const spinner = document.getElementById('modalLoadingSpinner');
    let hasSaved = false;

    // Clique no nome do aluno para abrir o modal
    document.querySelectorAll('.edit-aluno-trigger').forEach(function (trigger) {
        trigger.addEventListener('click', function (e) {
            e.preventDefault();
            const alunoId = this.getAttribute('data-id');
            const alunoNome = this.getAttribute('data-nome');
            
            modalAlunoNome.textContent = alunoNome;
            spinner.classList.remove('d-none');
            
            // Define o URL de retorno para index.php?m=rel_pendencias&a=relatorio&turma_id=...&iframe_save=1
            const returnUrl = encodeURIComponent('m=rel_pendencias&a=relatorio&turma_id=<?php echo $turmaFiltro; ?>&diploma_filtro=<?php echo $diplomaFiltroSel; ?>&guia=<?php echo $guia; ?>&iframe_save=1');
            const formUrl = '<?php echo BASE_URL; ?>index.php?m=alunos&a=form&id=' + alunoId + '&layout=clean&iframe=1&return=' + returnUrl;
            
            iframe.src = formUrl;
            hasSaved = false;
            editModal.show();
        });
    });

    // Ocultar spinner quando o iframe carregar
    iframe.addEventListener('load', function () {
        spinner.classList.add('d-none');
        
        try {
            const iframeUrl = iframe.contentWindow.location.href;
            if (iframeUrl && iframeUrl.includes('iframe_save=1')) {
                hasSaved = true;
                editModal.hide();
            }
        } catch (e) {
            console.error('Erro de acesso ao iframe:', e);
        }
    });

    // Recarregar a página principal ao fechar o modal caso tenha salvo
    editModalEl.addEventListener('hidden.bs.modal', function () {
        if (hasSaved) {
            // Salvar a posição atual do scroll antes de recarregar
            sessionStorage.setItem('relatorioScrollPosition', window.scrollY);
            window.location.reload();
        }
        iframe.src = '';
    });
});
</script>
