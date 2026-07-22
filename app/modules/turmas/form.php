<?php
// prevenção a erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// app/modules/turmas/form.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/CrudEngine.php';

$tableName = '104_turmas';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$activeTab = $_GET['tab'] ?? 'dados';

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM $tableName WHERE id = ?");
    $stmt->execute([$id]);
    $record = $stmt->fetch();

    if (!$record) {
        die('Registro não encontrado.');
    }

    // Carregar alunos vinculados
    $stmtAlunos = $pdo->prepare("
        SELECT a.id, a.aluno, a.emitido_cert_metodo_grisa, a.emitido_cert_universidade,
               a.diploma, a.rg_carteira_conselho, a.cpf, a.cer_nascimento_casamento,
               a.comprovante_residencia, a.tcc, a.diarios_classe,
               p.nome, p.email, p.fone_1 
        FROM 104_alunos a
        JOIN 101_pessoas p ON a.aluno = p.id
        WHERE a.turma = ?
        ORDER BY p.nome ASC
    ");
    $stmtAlunos->execute([$id]);
    $alunos = $stmtAlunos->fetchAll();

    // Carregar disciplinas vinculadas
    $stmtDisc = $pdo->prepare("
        SELECT td.id, d.nome AS disciplina_nome, d.carga_horaria, p_disc.apelido AS professor_nome
        FROM 104_turmas_disciplinas td
        JOIN 104_disciplinas d ON td.disciplina = d.id
        LEFT JOIN 104_professores p_disc ON td.professor = p_disc.id
        WHERE td.turma = ?
        ORDER BY d.nome ASC
    ");
    $stmtDisc->execute([$id]);
    $disciplinas = $stmtDisc->fetchAll();

    // Carregar etapas vinculadas
    $stmtEtapas = $pdo->prepare("
        SELECT et.id, e.nome AS etapa_nome, et.etapa_da_turma, et.link_ead
        FROM 104_etapas_turmas et
        JOIN 104_etapas e ON et.etapa = e.id
        WHERE et.turma = ?
        ORDER BY e.nome ASC
    ");
    $stmtEtapas->execute([$id]);
    $etapas = $stmtEtapas->fetchAll();
} else {
    $record = [];
    $alunos = [];
    $disciplinas = [];
    $etapas = [];
}

// Carregar faculdades para o dropdown
$stmtFac = $pdo->query("SELECT id, nome FROM 104_faculdades ORDER BY nome ASC");
$faculdades = $stmtFac->fetchAll();

$baseHref = BASE_URL . 'index.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>
        <?php if ($id > 0): ?>
            Editar <?php echo get_table_label($tableName); ?>: <?php echo htmlspecialchars($record['nome'] ?? ''); ?>
        <?php else: ?>
            Nova <?php echo get_table_label($tableName); ?>
        <?php endif; ?>
    </h2>
    <div class="d-flex gap-2">
        <?php if ($id > 0):
            $inscricaoKey  = hash('sha256', 'mg_inscricao_turma_2024_grisa_secret_' . $id);
            $proto         = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $linkInscricao = $proto . '://' . $_SERVER['HTTP_HOST'] . rtrim(BASE_URL, '/') . '/inscricao.php?turma=' . $id . '&key=' . $inscricaoKey;
        ?>
        <button type="button" class="btn btn-outline-success"
                onclick="copiarLinkInscricao()"
                title="Copiar link de inscrição para esta turma">
            <i class="bi bi-link-45deg"></i> Link de Inscrição
        </button>
        <span id="link-inscricao-hidden" class="d-none"><?php echo htmlspecialchars($linkInscricao); ?></span>
        <?php endif; ?>
        <a href="<?php echo $baseHref; ?>?m=turmas&a=list" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Voltar para a lista
        </a>
    </div>
</div>

<!-- Nav tabs -->
<ul class="nav nav-tabs mb-4" id="turmaTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link <?php echo $activeTab === 'dados' ? 'active' : ''; ?>" id="dados-tab"
            data-bs-toggle="tab" data-bs-target="#dados" type="button" role="tab">Dados da Turma</button>
    </li>
    <?php if ($id > 0): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $activeTab === 'alunos' ? 'active' : ''; ?>" id="alunos-tab"
                data-bs-toggle="tab" data-bs-target="#alunos" type="button" role="tab">
                Alunos <span class="badge bg-secondary ms-1"><?php echo count($alunos); ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $activeTab === 'disciplinas' ? 'active' : ''; ?>" id="disciplinas-tab"
                data-bs-toggle="tab" data-bs-target="#disciplinas" type="button" role="tab">
                Disciplinas <span class="badge bg-secondary ms-1"><?php echo count($disciplinas); ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $activeTab === 'etapas' ? 'active' : ''; ?>" id="etapas-tab"
                data-bs-toggle="tab" data-bs-target="#etapas" type="button" role="tab">
                Etapas <span class="badge bg-secondary ms-1"><?php echo count($etapas); ?></span>
            </button>
        </li>
    <?php endif; ?>
</ul>

<!-- Tab panes -->
<div class="tab-content">
    <div class="tab-pane fade <?php echo $activeTab === 'dados' ? 'show active' : ''; ?>" id="dados" role="tabpanel">
        <div class="card shadow-sm p-4">
            <form action="<?php echo $baseHref; ?>?m=turmas&a=save" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?php echo $id; ?>">

                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Nome da Turma <span class="text-danger">*</span></label>
                        <input type="text" name="nome" class="form-control"
                            value="<?php echo htmlspecialchars($record['nome'] ?? ''); ?>" required>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Data Início</label>
                        <input type="date" name="inicio" class="form-control"
                            value="<?php echo $record['inicio'] ?? ''; ?>">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Data Fim</label>
                        <input type="date" name="termino" class="form-control"
                            value="<?php echo $record['termino'] ?? ''; ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Faculdade</label>
                        <select name="faculdade" class="form-select">
                            <option value="">Selecione a Faculdade</option>
                            <?php foreach ($faculdades as $fac): ?>
                                <option value="<?php echo $fac['id']; ?>" <?php echo (isset($record['faculdade']) && $record['faculdade'] == $fac['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($fac['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">ID Moodle (EAD)
                            <?php if (!empty($record['id_moodle'])): ?>
                                <a href="https://eadmetodogrisa.com.br/course/view.php?id=<?php echo (int) $record['id_moodle']; ?>"
                                    target="_blank" class="ms-1 text-muted" title="Abrir no EAD"><i
                                        class="bi bi-box-arrow-up-right" style="font-size:0.75rem;"></i></a>
                            <?php endif; ?>
                        </label>
                        <input type="number" name="id_moodle" class="form-control"
                            value="<?php echo htmlspecialchars($record['id_moodle'] ?? ''); ?>" placeholder="Ex: 15"
                            min="1">
                    </div>

                    <!-- Documentos da Turma con drag and drop y visualização de arquivos existentes -->
                    <div class="col-md-12">
                        <label class="form-label fw-bold">Documentos da Turma</label>
                        <div id="drop-zone-docs" class="border-dashed rounded-3 p-4 text-center bg-white mb-2"
                            style="border: 2px dashed #cbd5e0; transition: border-color .2s, background-color .2s; cursor: pointer;">
                            <i class="bi bi-cloud-arrow-up display-5 text-primary mb-2"></i>
                            <p class="mb-0 text-muted">Arraste os documentos aqui ou clique para selecionar</p>
                            <span class="text-xs text-muted">Formatos aceitos: PDF, DOCX, ZIP, JPG, PNG e outros</span>
                        </div>
                        <input type="file" name="novos_documentos[]" id="file-input-docs" class="d-none" multiple>
                        <div id="new-docs-preview" class="small mb-3 d-flex flex-wrap gap-2"></div>

                        <input type="hidden" name="documentos_json" id="documentos-json" value="<?php echo htmlspecialchars($record['documentos'] ?? ''); ?>">
                        
                        <!-- Lista de documentos existentes/adicionados -->
                        <div id="documentos-lista" class="list-group list-group-flush border rounded-3 overflow-hidden shadow-xs mb-3"></div>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Observações</label>
                        <textarea name="observacao" class="form-control"
                            rows="3"><?php echo htmlspecialchars($record['observacao'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top">
                    <button type="submit" class="btn btn-primary">Salvar
                        <?php echo get_table_label($tableName); ?>
                    </button>
                    <a href="<?php echo $baseHref; ?>?m=turmas&a=list" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>

    <?php if ($id > 0): ?>
        <div class="tab-pane fade <?php echo $activeTab === 'alunos' ? 'show active' : ''; ?>" id="alunos" role="tabpanel">
            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span class="fw-bold">Lista de Alunos</span>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div class="d-flex align-items-center gap-1" style="min-width: 280px;">
                            <div style="flex-grow: 1; min-width: 220px;">
                                <select id="select-aluno-individual" class="form-select" data-placeholder="Buscar aluno para vincular..."></select>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-pessoa-modal" title="Cadastrar Nova Pessoa" style="display: none; height: 31px; width: 31px; padding: 0; line-height: 29px; border-radius: 4px;">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        </div>
                        <button type="button" class="btn btn-sm btn-success" id="btn-vincular-individual">
                            <i class="bi bi-person-plus"></i> Vincular Individual
                        </button>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                            data-bs-target="#modalBulkLink">
                            <i class="bi bi-people"></i> Vincular em massa
                        </button>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="table-alunos-turma">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 100px;">Ações</th>
                                    <th>Nome</th>
                                    <th class="text-center">Cert MG</th>
                                    <th class="text-center">Cert Uni</th>
                                    <th>Documentos</th>
                                    <th>Contato</th>
                                    <th class="text-center" style="width:70px;">Acesso</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($alunos)): ?>
                                    <tr class="empty-row">
                                        <td colspan="5" class="text-center py-4 text-muted">Nenhum aluno vinculado a esta turma.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($alunos as $aluno): ?>
                                        <tr data-id="<?php echo $aluno['id']; ?>">
                                            <td>
                                                <a href="<?php echo $baseHref; ?>?m=alunos&a=form&id=<?php echo $aluno['id']; ?>"
                                                    class="btn btn-outline-secondary btn-sm" title="Editar Vínculo"><i
                                                        class="bi bi-pencil"></i></a>
                                                <a href="<?php echo $baseHref; ?>?m=alunos&a=delete&id=<?php echo $aluno['id']; ?>&return=<?php echo urlencode('m=turmas&a=form&id=' . $id . '&tab=alunos'); ?>"
                                                    class="btn btn-outline-danger btn-sm" title="Remover da Turma"
                                                    onclick="return confirm('Deseja realmente remover este aluno da turma?')"><i
                                                        class="bi bi-trash"></i></a>
                                            </td>
                                            <td class="fw-semibold">
                                                <a
                                                    href="<?php echo $baseHref; ?>?m=pessoas&a=form&id=<?php echo $aluno['aluno']; ?>&return=<?php echo urlencode('m=turmas&a=form&id=' . $id . '&tab=alunos'); ?>">
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
                                                    <div class="doc-toggle-wrapper" style="cursor: pointer;"
                                                        onclick="toggleCert(<?php echo $aluno['id']; ?>, '<?php echo $field; ?>', this)"
                                                        data-status="<?php echo $ok ? '1' : '0'; ?>" title="Clique para alternar">
                                                        <i
                                                            class="bi <?php echo $ok ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger'; ?> me-1"></i>
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
                                                <?php if ($aluno['fone_1']): ?>
                                                    <?php $wa = preg_replace('/\D/', '', $aluno['fone_1']); ?>
                                                    <div>
                                                        <a href="https://wa.me/55<?php echo $wa; ?>" target="_blank"
                                                            class="text-decoration-none text-dark">
                                                            <i class="bi bi-whatsapp text-success me-1"></i><?php echo htmlspecialchars($aluno['fone_1']); ?>
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($aluno['email']): ?>
                                                    <div>
                                                        <a href="mailto:<?php echo htmlspecialchars($aluno['email']); ?>"
                                                            class="text-decoration-none text-dark">
                                                            <i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($aluno['email']); ?>
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($aluno['fone_1']): ?>
                                                    <button type="button"
                                                        class="btn btn-sm btn-success btn-enviar-acesso"
                                                        title="Enviar credenciais de acesso via WhatsApp"
                                                        data-pessoa="<?php echo $aluno['aluno']; ?>"
                                                        data-turma="<?php echo $id; ?>"
                                                        data-nome="<?php echo htmlspecialchars($aluno['nome']); ?>">
                                                        <i class="bi bi-whatsapp"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted small" title="Sem telefone cadastrado">—</span>
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
                    <div class="d-flex gap-2">
                        <?php if (empty($disciplinas)): ?>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="btn-link-all-disciplinas">
                                <i class="bi bi-collection"></i> Vincular todas as disciplinas disponíveis
                            </button>
                        <?php endif; ?>
                        <a href="index.php?m=turmas_disciplinas&a=form&turma=<?php echo $id; ?>&return=<?php echo urlencode('m=turmas&a=form&id=' . $id . '&tab=disciplinas'); ?>"
                            class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-lg"></i> Vincular Disciplina
                        </a>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Disciplina</th>
                                    <th>Carga Horária</th>
                                    <th>Professor/Facilitador</th>
                                    <th style="width: 100px;">Ações</th>
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
                                            <td class="fw-semibold"><?php echo htmlspecialchars($disc['disciplina_nome']); ?></td>
                                            <td><?php echo htmlspecialchars($disc['carga_horaria'] ?? '—'); ?>h</td>
                                            <td><?php echo htmlspecialchars($disc['professor_nome'] ?? '—'); ?></td>
                                            <td>
                                                <a href="index.php?m=turmas_disciplinas&a=form&id=<?php echo $disc['id']; ?>&return=<?php echo urlencode('m=turmas&a=form&id=' . $id . '&tab=disciplinas'); ?>"
                                                    class="btn btn-outline-secondary btn-sm" title="Editar"><i
                                                        class="bi bi-pencil"></i></a>
                                                <a href="index.php?m=turmas_disciplinas&a=delete&id=<?php echo $disc['id']; ?>&return=<?php echo urlencode('m=turmas&a=form&id=' . $id . '&tab=disciplinas'); ?>"
                                                    class="btn btn-outline-danger btn-sm" title="Remover"
                                                    onclick="return confirm('Deseja realmente remover esta disciplina?')"><i
                                                        class="bi bi-trash"></i></a>
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

        <div class="tab-pane fade <?php echo $activeTab === 'etapas' ? 'show active' : ''; ?>" id="etapas" role="tabpanel">
            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <span class="fw-bold">Etapas da Turma</span>
                    <a href="index.php?m=etapas&a=form&turma_id=<?php echo $id; ?>&return=<?php echo urlencode('m=turmas&a=form&id=' . $id . '&tab=etapas'); ?>"
                        class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg"></i> Criar Nova Etapa
                    </a>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Etapa (Modelo)</th>
                                    <th>Nome na Turma</th>
                                    <th>Link EAD</th>
                                    <th style="width: 100px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($etapas)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">Nenhuma etapa vinculada a esta
                                            turma.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($etapas as $etapa): ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo htmlspecialchars($etapa['etapa_nome']); ?></td>
                                            <td><?php echo htmlspecialchars($etapa['etapa_da_turma']); ?></td>
                                            <td>
                                                <?php if ($etapa['link_ead']): ?>
                                                    <a href="<?php echo htmlspecialchars($etapa['link_ead']); ?>" target="_blank"
                                                        class="btn btn-link btn-sm p-0">
                                                        <i class="bi bi-box-arrow-up-right"></i> Abrir EAD
                                                    </a>
                                                <?php else: ?>
                                                    —
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="index.php?m=etapas_turmas&a=form&id=<?php echo $etapa['id']; ?>&return=<?php echo urlencode('m=turmas&a=form&id=' . $id . '&tab=etapas'); ?>"
                                                    class="btn btn-outline-secondary btn-sm" title="Editar"><i
                                                        class="bi bi-pencil"></i></a>
                                                <a href="index.php?m=etapas_turmas&a=delete&id=<?php echo $etapa['id']; ?>&return=<?php echo urlencode('m=turmas&a=form&id=' . $id . '&tab=etapas'); ?>"
                                                    class="btn btn-outline-danger btn-sm" title="Remover"
                                                    onclick="return confirm('Deseja realmente remover esta etapa?')"><i
                                                        class="bi bi-trash"></i></a>
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
    <?php endif; ?>
</div>
</div>

<!-- Modal Bulk Link Alunos -->
<div class="modal fade" id="modalBulkLink" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="form-bulk-link">
                <input type="hidden" name="turma_id" value="<?php echo $id; ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Vincular Alunos em Massa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Cole a lista de alunos abaixo no formato: <strong>Nome – email@dominio.com</strong> (uma por
                        linha).<br>
                        O sistema buscará a pessoa por e-mail ou nome. Se não existir, criará o cadastro
                        automaticamente.
                    </p>
                    <div class="mb-3">
                        <textarea name="raw_text" id="raw_text_bulk" class="form-control" rows="12"
                            placeholder="Exemplo:&#10;Maria Lair Regis de Andrade – maria@mercocentro.com.br&#10;Ieda de Fátima Anselmo – ieda.anselmo@hotmail.com"
                            required></textarea>
                    </div>
                    <div id="bulk-results" style="display:none;">
                        <div class="alert alert-info">
                            <ul class="mb-0">
                                <li>Linhas processadas: <span id="res-total">0</span></li>
                                <li>Novas pessoas cadastradas: <span id="res-added">0</span></li>
                                <li>Novos vínculos criados: <span id="res-linked">0</span></li>
                                <li>Já estavam vinculados: <span id="res-existing">0</span></li>
                            </ul>
                        </div>
                        <div id="bulk-errors" class="alert alert-danger p-2 small mt-2"
                            style="display:none; max-height:150px; overflow-y:auto;"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="submit" class="btn btn-primary" id="btn-submit-bulk">Processar e Vincular</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Nova Pessoa -->
<div class="modal fade" id="modalNovaPessoa" tabindex="-1" aria-labelledby="modalNovaPessoaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content shadow-lg border-0">
            <form id="form-nova-pessoa">
                <div class="modal-header bg-primary text-white py-2">
                    <h6 class="modal-title" id="modalNovaPessoaLabel"><i class="bi bi-person-plus-fill me-2"></i>Cadastrar Nova Pessoa</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4" style="font-size: 0.88rem;">
                    <!-- Alert for errors -->
                    <div id="new-pessoa-error" class="alert alert-danger p-2 small mb-3" style="display:none;"></div>
                    
                    <!-- Row 1: Cadastro -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold mb-1">Nome <span class="text-danger">*</span></label>
                            <input type="text" name="nome" id="new-pessoa-nome" class="form-control form-control-sm" required placeholder="Nome Completo">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label mb-1">Razão Social</label>
                            <input type="text" name="razao_social" class="form-control form-control-sm" placeholder="Se aplicável">
                        </div>
                    </div>
                    
                    <!-- Row 2: Documentos e Nascimento -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label mb-1">Data de Nascimento</label>
                            <input type="date" name="nascimento" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-1">CPF/CNPJ</label>
                            <input type="text" name="cpf_cnpj" class="form-control form-control-sm cpf-cnpj-mask" placeholder="Apenas números">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-1">RG/IE</label>
                            <input type="text" name="rg_ie" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-1">Estado Civil</label>
                            <input type="text" name="estado_civil" class="form-control form-control-sm">
                        </div>
                    </div>

                    <!-- Row 3: Contatos -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label mb-1">E-mail</label>
                            <input type="email" name="email" class="form-control form-control-sm" placeholder="email@exemplo.com">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-1">Telefone 1</label>
                            <input type="text" name="fone_1" class="form-control form-control-sm" placeholder="Apenas números">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-1">Telefone 3 (WhatsApp)</label>
                            <input type="text" name="fone_3" class="form-control form-control-sm" placeholder="Apenas números">
                        </div>
                    </div>

                    <!-- Accordion or Section for address (CEP, Endereco, etc) to keep it compact but complete -->
                    <div class="border rounded p-3 bg-light mb-3 shadow-xs">
                        <div class="fw-bold mb-2 small text-primary"><i class="bi bi-geo-alt-fill me-1"></i>Endereço</div>
                        <div class="row g-2">
                            <div class="col-md-2">
                                <label class="form-label small mb-1">CEP</label>
                                <input type="text" name="cep" id="new-pessoa-cep" class="form-control form-control-sm" maxlength="9" placeholder="00000-000">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small mb-1">Endereço</label>
                                <input type="text" name="endereco" id="new-pessoa-endereco" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-1">Número</label>
                                <input type="text" name="numero" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">Complemento</label>
                                <input type="text" name="complemento" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small mb-1">Bairro</label>
                                <input type="text" name="bairro" id="new-pessoa-bairro" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small mb-1">Cidade</label>
                                <input type="text" name="cidade" id="new-pessoa-cidade" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">Estado</label>
                                <input type="text" name="estado" id="new-pessoa-estado" class="form-control form-control-sm">
                            </div>
                        </div>
                    </div>

                    <!-- Row 4: Escolaridade & Profissão & Observações -->
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label mb-1">Escolaridade</label>
                            <input type="text" name="escolaridade" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-1">Profissão Atual</label>
                            <input type="text" name="profissao_atual" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-1">Nacionalidade</label>
                            <input type="text" name="nacionalidade" class="form-control form-control-sm" value="Brasileira">
                        </div>
                        <div class="col-md-12 mt-2">
                            <label class="form-label mb-1">Observações</label>
                            <textarea name="obs" class="form-control form-control-sm" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary" id="btn-submit-new-pessoa">Salvar e Vincular</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
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

        // Initialize Select2 for individual selection
        $('#select-aluno-individual').select2({
            theme: 'bootstrap-5',
            width: '100%',
            language: {
                noResults: function() {
                    return `Nenhum resultado encontrado. <a href="#" id="link-add-pessoa-select2" class="text-primary fw-bold text-decoration-none"><i class="bi bi-plus-circle"></i> Cadastrar Nova Pessoa</a>`;
                },
                searching: function() {
                    return "Buscando...";
                },
                inputTooShort: function() {
                    return "Digite 2 ou mais caracteres para buscar";
                }
            },
            escapeMarkup: function (markup) {
                return markup;
            },
            templateResult: function (data) {
                if (data.isAddNew) {
                    const safeTerm = $('<div>').text(data.term || '').html();
                    return $('<span class="text-primary fw-bold"><i class="bi bi-plus-circle me-1"></i>Cadastrar nova pessoa' +
                        (safeTerm ? ': "' + safeTerm + '"' : '') + '</span>');
                }
                return data.text;
            },
            ajax: {
                url: 'index.php?m=pessoas&a=ajax_search',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { q: params.term };
                },
                processResults: function (data, params) {
                    const term = (params.term || '').trim();
                    const results = (data.results || []).slice();
                    const btnAdd = document.getElementById('btn-add-pessoa-modal');
                    // Botão "+" disponível sempre que houver um termo pesquisável,
                    // independentemente de ter encontrado resultados ou não.
                    if (btnAdd) {
                        if (term.length >= 2) {
                            btnAdd.style.display = 'inline-block';
                            btnAdd.dataset.searchName = term;
                        } else {
                            btnAdd.style.display = 'none';
                        }
                    }
                    // Oferece "Cadastrar nova pessoa" dentro do dropdown mesmo quando há
                    // resultados, como último item da lista.
                    if (term.length >= 2) {
                        results.push({ id: '__add_new__', text: term, term: term, isAddNew: true });
                    }
                    return { results: results };
                },
                cache: true
            },
            placeholder: 'Buscar aluno para vincular...',
            minimumInputLength: 2,
            selectionCssClass: 'select2--small',
            dropdownCssClass: 'select2--small'
        });

        // Se o usuário escolher "Cadastrar nova pessoa" no dropdown, abre o modal
        // em vez de selecionar o item sintético.
        $('#select-aluno-individual').on('select2:select', function (e) {
            const data = e.params && e.params.data;
            if (data && data.isAddNew) {
                const term = data.term || '';
                $(this).val(null).trigger('change');
                $(this).select2('close');
                $('#btn-add-pessoa-modal').attr('data-search-name', term).trigger('click');
            }
        });

        $('#select-aluno-individual').on('change', function() {
            $('#btn-add-pessoa-modal').hide();
        });

        $('#btn-vincular-individual').on('click', function () {
            const alunoId = $('#select-aluno-individual').val();
            const turmaId = <?php echo $id; ?>;

            if (!alunoId) {
                alert('Por favor, selecione um aluno primeiro.');
                return;
            }

            const btn = $(this);
            const originalText = btn.html();
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

            $.ajax({
                url: 'index.php?m=alunos&a=ajax_link',
                method: 'POST',
                dataType: 'json',
                data: { aluno: alunoId, turma: turmaId, csrf_token: '<?php echo csrf_token(); ?>' },
                success: function (response) {
                    if (response.success) {
                        window.location.href = window.location.pathname + window.location.search + '&tab=alunos';
                    } else {
                        alert('Erro: ' + (response.error || 'Erro desconhecido ao vincular aluno.'));
                        btn.prop('disabled', false).html(originalText);
                    }
                },
                error: function (xhr) {
                    let errorMessage = 'Erro ao processar a requisição.';
                    if (xhr.status === 409) {
                        errorMessage = 'Este aluno já está vinculado a esta turma.';
                    } else if (xhr.responseJSON && xhr.responseJSON.error) {
                        errorMessage = xhr.responseJSON.error;
                    }
                    alert('Erro: ' + errorMessage);
                    btn.prop('disabled', false).html(originalText);
                }
            });
        });

        // Handle Bulk Link Submission
        $('#form-bulk-link').on('submit', function (e) {
            e.preventDefault();
            const btn = $('#btn-submit-bulk');
            const resultsDiv = $('#bulk-results');
            const errorsDiv = $('#bulk-errors');

            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Processando...');
            resultsDiv.hide();
            errorsDiv.hide().empty();

            $.ajax({
                url: 'index.php?m=alunos&a=ajax_bulk_link',
                method: 'POST',
                dataType: 'json',
                data: $(this).serialize(),
                success: function (response) {
                    console.log('Bulk Response:', response);
                    $('#res-total').text(response.total);
                    $('#res-added').text(response.added_pessoa);
                    $('#res-linked').text(response.linked);
                    $('#res-existing').text(response.already_linked);
                    resultsDiv.fadeIn();

                    if (response.errors && response.errors.length > 0) {
                        errorsDiv.html('<strong>Erros:</strong><br>' + response.errors.join('<br>')).show();
                    }

                    if (response.linked > 0 || response.added_pessoa > 0) {
                        // Espera um pouco para o usuário ver o resultado antes de recarregar
                        setTimeout(function () {
                            window.location.href = window.location.pathname + window.location.search + '&tab=alunos';
                        }, 2500);
                    } else {
                        btn.prop('disabled', false).text('Processar e Vincular');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Bulk AJAX Error:', status, error, xhr.responseText);
                    alert('Erro ao processar a lista. Verifique o console para mais detalhes.');
                    btn.prop('disabled', false).text('Processar e Vincular');
                }
            });
        });

        // Handle Link All Disciplines
        $('#btn-link-all-disciplinas').on('click', function () {
            if (!confirm('Deseja realmente vincular todas as disciplinas disponíveis do sistema a esta turma?')) {
                return;
            }

            const btn = $(this);
            const originalText = btn.html();

            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Processando...');

            $.ajax({
                url: 'index.php?m=turmas&a=ajax_link_all_disciplinas',
                method: 'POST',
                dataType: 'json',
                data: { turma_id: <?php echo $id; ?> },
                success: function (response) {
                    if (response.success) {
                        window.location.href = window.location.pathname + window.location.search + '&tab=disciplinas';
                    } else {
                        alert('Erro: ' + response.message);
                        btn.prop('disabled', false).html(originalText);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Link All AJAX Error:', status, error, xhr.responseText);
                    alert('Erro ao vincular disciplinas. Verifique o console para mais detalhes.');
                    btn.prop('disabled', false).html(originalText);
                }
            });
        });
    });

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

                    if (element.classList.contains('doc-toggle-wrapper')) {
                        // Documentos: ícone filled + texto com label
                        const span = element.querySelector('span');
                        const icon = element.querySelector('i');
                        if (icon) {
                            icon.className = 'bi ' + (isCheck ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger') + ' me-1';
                        }
                        if (span) {
                            span.className = isCheck ? 'text-success' : 'text-danger';
                        }
                    } else {
                        // Cert: apenas ícone simples
                        element.innerHTML = isCheck
                            ? '<i class="bi bi-check-lg fw-bold text-success" title="Sim"></i>'
                            : '<i class="bi bi-x-lg fw-bold text-danger" title="Não"></i>';
                    }
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

    // Modal Nova Pessoa Logic
    let modalNovaPessoa;
    document.addEventListener('DOMContentLoaded', function () {
        const modalEl = document.getElementById('modalNovaPessoa');
        if (modalEl) {
            modalNovaPessoa = new bootstrap.Modal(modalEl);
        }

        // Click on '+' button next to search box
        $('#btn-add-pessoa-modal').on('click', function () {
            const term = $(this).attr('data-search-name') || '';
            abrirModalNovaPessoa(term);
        });

        // Click on "Cadastrar Nova Pessoa" link inside Select2
        $(document).on('click', '#link-add-pessoa-select2', function (e) {
            e.preventDefault();
            $('#select-aluno-individual').select2('close');
            const term = $('#btn-add-pessoa-modal').attr('data-search-name') || '';
            abrirModalNovaPessoa(term);
        });

        function abrirModalNovaPessoa(term) {
            const form = document.getElementById('form-nova-pessoa');
            if (form) {
                form.reset();
                $('#new-pessoa-error').hide().empty();
                
                // Format name: uppercase first letters
                const formattedTerm = (term || '').trim().replace(/(^\w|\s\w)/g, m => m.toUpperCase());
                document.getElementById('new-pessoa-nome').value = formattedTerm;
                
                if (modalNovaPessoa) {
                    modalNovaPessoa.show();
                }
            }
        }

        // Phone and CPF/CNPJ mask and CEP auto-fill in modal
        const newCep = document.getElementById('new-pessoa-cep');
        if (newCep) {
            const maskCep = (value) => {
                const digits = value.replace(/\D/g, '').slice(0, 8);
                if (digits.length > 5) {
                    return digits.slice(0, 5) + '-' + digits.slice(5);
                }
                return digits;
            };

            newCep.addEventListener('input', (e) => {
                const masked = maskCep(e.target.value);
                if (e.target.value !== masked) {
                    e.target.value = masked;
                }
            });

            newCep.addEventListener('blur', () => {
                const cepDigits = (newCep.value || '').replace(/\D/g, '');
                if (cepDigits.length !== 8) {
                    return;
                }
                fetch(`https://viacep.com.br/ws/${cepDigits}/json/`)
                    .then((resp) => resp.ok ? resp.json() : Promise.reject())
                    .then((data) => {
                        if (!data || data.erro) return;
                        const end = document.getElementById('new-pessoa-endereco');
                        const b = document.getElementById('new-pessoa-bairro');
                        const cid = document.getElementById('new-pessoa-cidade');
                        const est = document.getElementById('new-pessoa-estado');
                        if (end) end.value = data.logradouro || '';
                        if (b) b.value = data.bairro || '';
                        if (cid) cid.value = data.localidade || '';
                        if (est) est.value = data.uf || '';
                    })
                    .catch(() => {
                        // Silently fail
                    });
            });
        }

        // Máscara de telefone removida: os campos aceitam números puros.

        const cpfCnpjInputs = document.querySelectorAll('#modalNovaPessoa .cpf-cnpj-mask');
        cpfCnpjInputs.forEach(input => {
            input.addEventListener('input', (e) => {
                let v = e.target.value.replace(/\D/g, '');
                if (v.length <= 11) { // CPF
                    let x = v.match(/(\d{0,3})(\d{0,3})(\d{0,3})(\d{0,2})/);
                    e.target.value = !x[2] ? x[1] : x[1] + '.' + x[2] + (x[3] ? '.' + x[3] : '') + (x[4] ? '-' + x[4] : '');
                } else { // CNPJ
                    let x = v.match(/(\d{0,2})(\d{0,3})(\d{0,3})(\d{0,4})(\d{0,2})/);
                    e.target.value = !x[2] ? x[1] : x[1] + '.' + x[2] + '.' + x[3] + '/' + x[4] + (x[5] ? '-' + x[5] : '');
                }
            });
        });

        // Form submission
        $('#form-nova-pessoa').on('submit', function (e) {
            e.preventDefault();
            const btn = $('#btn-submit-new-pessoa');
            const errorDiv = $('#new-pessoa-error');
            const turmaId = <?php echo $id; ?>;

            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Salvando...');
            errorDiv.hide().empty();

            $.ajax({
                url: 'index.php?m=pessoas&a=ajax_save',
                method: 'POST',
                dataType: 'json',
                data: $(this).serialize(),
                success: function (response) {
                    if (response.success && response.id) {
                        // Successfully saved, now link to this turma
                        $.ajax({
                            url: 'index.php?m=alunos&a=ajax_link',
                            method: 'POST',
                            dataType: 'json',
                            data: { aluno: response.id, turma: turmaId },
                            success: function (linkResponse) {
                                if (linkResponse.success) {
                                    window.location.href = window.location.pathname + window.location.search + '&tab=alunos';
                                } else {
                                    alert('Pessoa criada, mas erro ao vincular à turma: ' + (linkResponse.error || 'Erro desconhecido.'));
                                    window.location.href = window.location.pathname + window.location.search + '&tab=alunos';
                                }
                            },
                            error: function (xhr) {
                                let errMsg = 'Erro ao vincular pessoa à turma.';
                                if (xhr.status === 409) {
                                    errMsg = 'Esta pessoa já está vinculada a esta turma.';
                                } else if (xhr.responseJSON && xhr.responseJSON.error) {
                                    errMsg = xhr.responseJSON.error;
                                }
                                alert('Pessoa criada, mas: ' + errMsg);
                                window.location.href = window.location.pathname + window.location.search + '&tab=alunos';
                            }
                        });
                    } else {
                        errorDiv.text('Erro: ' + (response.error || response.message || 'Erro desconhecido ao salvar.')).show();
                        btn.prop('disabled', false).text('Salvar e Vincular');
                    }
                },
                error: function (xhr) {
                    let errMsg = 'Erro ao salvar pessoa.';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        errMsg = xhr.responseJSON.error;
                    }
                    errorDiv.text('Erro: ' + errMsg).show();
                    btn.prop('disabled', false).text('Salvar e Vincular');
                }
            });
        });

        // --- Documentos da Turma Logic ---
        const docsListEl = document.getElementById('documentos-lista');
        const docsJsonInput = document.getElementById('documentos-json');
        const dropZoneDocs = document.getElementById('drop-zone-docs');
        const fileInputDocs = document.getElementById('file-input-docs');
        const previewDocs = document.getElementById('new-docs-preview');

        function updateDocsJson() {
            const items = [];
            docsListEl.querySelectorAll('.file-item').forEach(item => {
                items.push({
                    name: item.dataset.name,
                    file: item.dataset.file,
                    size: parseInt(item.dataset.size),
                    type: item.dataset.type
                });
            });
            docsJsonInput.value = JSON.stringify(items);
        }

        window.removeDoc = function (btn) {
            if (confirm('Deseja remover este documento?')) {
                btn.closest('.file-item').remove();
                updateDocsJson();
            }
        };

        const renderDocs = (jsonStr) => {
            let files = [];
            try { files = JSON.parse(jsonStr || '[]'); } catch (e) { }
            if (!Array.isArray(files)) files = [];

            docsListEl.innerHTML = '';

            if (files.length === 0) {
                docsListEl.innerHTML = '<div class="p-3 text-center text-muted small">Nenhum documento anexado</div>';
                docsJsonInput.value = '[]';
                return;
            }

            files.forEach(f => {
                const item = document.createElement('div');
                item.className = 'list-group-item file-item d-flex align-items-center p-2';
                item.dataset.name = f.name;
                item.dataset.file = f.file;
                item.dataset.size = f.size;
                item.dataset.type = f.type || '';

                // Link absoluto para download
                const baseUrl = '<?php echo BASE_URL; ?>';
                const fileUrl = (f.file.indexOf('http') === 0) ? f.file : baseUrl + 'uploads/' + f.file;

                item.innerHTML = `
                    <div class="flex-grow-1 text-truncate" title="${f.name}">
                        <i class="bi bi-file-earmark-text me-1 text-primary"></i>
                        <a href="${fileUrl}" target="_blank" class="small fw-semibold text-decoration-none">${f.name}</a>
                    </div>
                    <button type="button" class="btn btn-link text-danger btn-sm p-0 ms-2" onclick="removeDoc(this)">
                        <i class="bi bi-x-circle"></i>
                    </button>
                `;
                docsListEl.appendChild(item);
            });
            updateDocsJson();
        };

        if (dropZoneDocs && fileInputDocs) {
            dropZoneDocs.addEventListener('click', () => fileInputDocs.click());
            dropZoneDocs.addEventListener('dragover', (e) => { 
                e.preventDefault(); 
                dropZoneDocs.style.borderColor = '#4299e1'; 
                dropZoneDocs.style.backgroundColor = '#f7fafc'; 
            });
            dropZoneDocs.addEventListener('dragleave', () => { 
                dropZoneDocs.style.borderColor = '#cbd5e0'; 
                dropZoneDocs.style.backgroundColor = '#ffffff'; 
            });
            dropZoneDocs.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZoneDocs.style.borderColor = '#cbd5e0';
                dropZoneDocs.style.backgroundColor = '#ffffff';
                fileInputDocs.files = e.dataTransfer.files;
                updateNewDocsPreview();
            });
            fileInputDocs.addEventListener('change', updateNewDocsPreview);
        }

        function updateNewDocsPreview() {
            previewDocs.innerHTML = '';
            Array.from(fileInputDocs.files).forEach(f => {
                const badge = document.createElement('span');
                badge.className = 'badge bg-primary d-flex align-items-center gap-1 p-2';
                badge.innerHTML = `<i class="bi bi-file-earmark-plus"></i> ${f.name} <i class="bi bi-check-circle-fill ms-1"></i>`;
                previewDocs.appendChild(badge);
            });
        }

        // Inicializar com arquivos existentes
        if (docsJsonInput) {
            renderDocs(docsJsonInput.value);
        }
    });

    // ── Enviar acesso via WhatsApp ──────────────────────────────────────────
    document.querySelectorAll('.btn-enviar-acesso').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const nome   = this.dataset.nome;
            const pessoa = this.dataset.pessoa;
            const turma  = this.dataset.turma;

            if (!confirm('Gerar credenciais de acesso e enviar via WhatsApp para ' + nome + '?')) return;

            const url = 'index.php?m=turmas&a=send_access&pessoa_id=' + pessoa + '&turma_id=' + turma;
            window.location.href = url;
        });
    });

    function copiarLinkInscricao() {
        const el = document.getElementById('link-inscricao-hidden');
        if (!el) return;
        const link = el.textContent.trim();
        navigator.clipboard.writeText(link).then(function () {
            const btn = document.querySelector('[onclick="copiarLinkInscricao()"]');
            const original = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Link copiado!';
            btn.classList.replace('btn-outline-success', 'btn-success');
            setTimeout(function () {
                btn.innerHTML = original;
                btn.classList.replace('btn-success', 'btn-outline-success');
            }, 2500);
        }).catch(function () {
            prompt('Copie o link abaixo:', link);
        });
    }
</script>

<?php // footer handled by index.php ?>
