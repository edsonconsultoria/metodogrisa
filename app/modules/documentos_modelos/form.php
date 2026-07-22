<?php
// app/modules/documentos_modelos/form.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';
require_once __DIR__ . '/../../core/services/DocumentoGeradoService.php';

$tableName = '104_documentos_modelos';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$copyFrom = isset($_GET['copy_from']) ? (int) $_GET['copy_from'] : 0;
$engine = new CrudEngine($pdo, $tableName);

$schema = $engine->getSchema();
$primaryKey = $engine->getPrimaryKey();

if ($id > 0 || $copyFrom > 0) {
    $lookupId = $id > 0 ? $id : $copyFrom;
    $record = $engine->getRecord($lookupId);
    if ($copyFrom > 0 && isset($record['nome'])) {
        $record['nome'] .= ' (Cópia)';
    }
} else {
    $record = null;
}

$baseHref = BASE_URL . 'index.php';
$activeTab = $_GET['tab'] ?? 'config';

// Inicializa serviço de documentos gerados se for edição
$documentosGerados = [];
if ($id > 0) {
    $docService = new DocumentoGeradoService($pdo);
    $documentosGerados = $docService->listarPorModelo($id);
}

// Buscar turmas para o modal de geração em lote
$stmtTurmas = $pdo->query("SELECT id, nome FROM 104_turmas ORDER BY nome ASC");
$turmasOptions = $stmtTurmas->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <?php echo ($id ? 'Editar' : 'Novo') . ' Modelo de Documento'; ?>
    </h4>
    <a href="<?php echo $baseHref; ?>?m=documentos_modelos&a=list" class="btn btn-outline-secondary btn-sm">Voltar</a>
</div>

<?php if ($id > 0): ?>
    <ul class="nav nav-tabs mb-3" id="modeloFormTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link <?php echo $activeTab === 'config' ? 'active' : ''; ?>" id="config-tab"
                data-bs-toggle="tab" data-bs-target="#config" type="button" role="tab">
                <i class="bi bi-gear me-1"></i> Configuração do Modelo
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link <?php echo $activeTab === 'documentos' ? 'active' : ''; ?>" id="documentos-tab"
                data-bs-toggle="tab" data-bs-target="#documentos" type="button" role="tab">
                <i class="bi bi-file-earmark-text me-1"></i> Documentos Gerados (<?php echo count($documentosGerados); ?>)
            </button>
        </li>
    </ul>
<?php endif; ?>

<div class="tab-content">
    <!-- ABA CONFIGURAÇÃO -->
    <div class="tab-pane fade <?php echo ($id <= 0 || $activeTab === 'config') ? 'show active' : ''; ?>" id="config"
        role="tabpanel">
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form action="<?php echo $baseHref; ?>?m=documentos_modelos&a=save" method="post"
                    enctype="multipart/form-data">
                    <?php if ($id): ?>
                        <input type="hidden" name="<?php echo $primaryKey; ?>" value="<?php echo htmlspecialchars($id); ?>">
                    <?php endif; ?>
                    <?php if ($copyFrom): ?>
                        <input type="hidden" name="copy_from" value="<?php echo htmlspecialchars($copyFrom); ?>">
                    <?php endif; ?>

                    <div class="row g-3">
                        <div class="col-md-9">
                            <label class="form-label">Nome do Modelo <span class="text-danger">*</span></label>
                            <input type="text" name="nome" class="form-control"
                                value="<?php echo htmlspecialchars($record['nome'] ?? ''); ?>" required
                                placeholder="Ex: Contrato de Matrícula">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="ativo" class="form-select">
                                <option value="1" <?php echo ($record['ativo'] ?? '1') == '1' ? 'selected' : ''; ?>>Ativo
                                </option>
                                <option value="0" <?php echo ($record['ativo'] ?? '1') == '0' ? 'selected' : ''; ?>>
                                    Inativo</option>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Descrição</label>
                            <input type="text" name="descricao" class="form-control"
                                value="<?php echo htmlspecialchars($record['descricao'] ?? ''); ?>"
                                placeholder="Breve descrição sobre o uso deste modelo">
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Arquivo Template (Word) <span class="text-danger">*</span></label>
                            <input type="file" name="arquivo_template" class="form-control"
                                accept=".doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                                <?php echo $id ? '' : 'required'; ?>>
                            <?php if (!empty($record['arquivo_template'])): ?>
                                <div class="mt-1 small text-muted">
                                    <i class="bi bi-file-earmark-word"></i> Arquivo atual:
                                    <strong><?php echo htmlspecialchars($record['arquivo_template']); ?></strong>
                                </div>
                            <?php endif; ?>
                            <div class="form-text small">Selecione o arquivo de template Word (.doc ou .docx).</div>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top text-end">
                        <a href="<?php echo $baseHref; ?>?m=documentos_modelos&a=list"
                            class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary ms-2">Salvar Modelo</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($id > 0): ?>
            <!-- Sub-cadastro: Variáveis -->
            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 card-title small uppercase fw-bold">Variáveis do Modelo</h5>
                    <a href="<?php echo $baseHref; ?>?m=documentos_modelos_variaveis&a=form&modelo_id=<?php echo $id; ?>"
                        class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg"></i> Adicionar Variável
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light text-muted small uppercase">
                            <tr>
                                <th>Variável</th>
                                <th>Origem</th>
                                <th>Valor / Query</th>
                                <th>Status</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            <?php
                            $stmtV = $pdo->prepare("SELECT * FROM 104_documentos_modelos_variaveis WHERE modelo_id = ? ORDER BY variavel");
                            $stmtV->execute([$id]);
                            $variaveis = $stmtV->fetchAll(PDO::FETCH_ASSOC);

                            if (empty($variaveis)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-3 text-muted">Nenhuma variável cadastrada.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($variaveis as $v): ?>
                                    <tr>
                                        <td class="fw-bold"><?php echo htmlspecialchars($v['variavel']); ?></td>
                                        <td><?php echo strtoupper($v['origem']); ?></td>
                                        <td class="font-monospace small" style="white-space: pre-wrap; word-break: break-all;">
                                            <?php echo htmlspecialchars($v['valor']); ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $v['ativo'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?php echo $v['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?php echo $baseHref; ?>?m=documentos_modelos_variaveis&a=form&id=<?php echo $v['id']; ?>"
                                                class="btn btn-link btn-sm p-0 m-0" title="Editar"><i class="bi bi-pencil"></i></a>
                                            <a href="<?php echo $baseHref; ?>?m=documentos_modelos_variaveis&a=delete&id=<?php echo $v['id']; ?>"
                                                class="btn btn-link btn-sm p-0 m-0 ms-1 text-danger" title="Excluir"
                                                onclick="return confirm('Excluir esta variável?')"><i class="bi bi-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($id > 0): ?>
        <!-- ABA DOCUMENTOS GERADOS -->
        <div class="tab-pane fade <?php echo $activeTab === 'documentos' ? 'show active' : ''; ?>" id="documentos"
            role="tabpanel">
            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 card-title small uppercase fw-bold">Documentos Gerados com este Modelo</h5>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal"
                            data-bs-target="#modalBatchTurma">
                            <i class="bi bi-people"></i> Gerar doc da turma
                        </button>
                        <button type="button" class="btn btn-primary btn-sm" onclick="openDocumentoModal()">
                            <i class="bi bi-plus-lg"></i> Gerar Novo Documento
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-muted small uppercase">
                            <tr>
                                <th width="120">Ações</th>
                                <th width="80">ID</th>
                                <th>Título</th>
                                <th>Pessoa</th>
                                <th>Data de Geração</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            <?php if (empty($documentosGerados)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">Nenhum documento gerado para este
                                        modelo.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($documentosGerados as $doc): ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo $baseHref; ?>?m=documentos_gerados&a=view&id=<?php echo $doc['id']; ?>"
                                                class="btn btn-link btn-sm p-0 m-0 text-dark" title="Visualizar">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <button type="button" class="btn btn-link btn-sm p-0 m-0 ms-2 text-success"
                                                title="Gerar novo para esta pessoa"
                                                onclick="openDocumentoModal(<?php echo $doc['pessoa_id']; ?>)">
                                                <i class="bi bi-plus-circle"></i>
                                            </button>
                                            <a href="<?php echo UPLOADS_URL . '/' . $doc['arquivo_docx']; ?>" target="_blank"
                                                class="btn btn-link btn-sm p-0 m-0 ms-2 text-primary" title="Baixar DOCX">
                                                <i class="bi bi-download"></i>
                                            </a>
                                            <a href="<?php echo $baseHref; ?>?m=documentos_gerados&a=delete&id=<?php echo $doc['id']; ?>&return=<?php echo urlencode('index.php?m=documentos_modelos&a=form&id=' . $id . '&tab=documentos'); ?>"
                                                class="btn btn-link btn-sm p-0 m-0 ms-2 text-danger" title="Excluir"
                                                onclick="return confirm('Deseja realmente excluir este documento gerado? O arquivo físico também será removido.')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                        <td class="text-muted"><?php echo $doc['id']; ?></td>
                                        <td class="fw-bold"><?php echo htmlspecialchars($doc['titulo']); ?></td>
                                        <td><?php echo htmlspecialchars($doc['pessoa_nome'] ?? '—'); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($doc['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal para Gerar em Lote por Turma -->
    <div class="modal fade" id="modalBatchTurma" tabindex="-1" aria-labelledby="modalBatchTurmaLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalBatchTurmaLabel">Gerar Documentos por Turma</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">Selecione a turma para gerar um documento para cada aluno
                        vinculado.</p>
                    <div class="mb-3">
                        <label class="form-label">Selecione a Turma</label>
                        <select id="selectTurmaBatch" class="form-select">
                            <option value="">Selecione...</option>
                            <?php foreach ($turmasOptions as $t): ?>
                                <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="generateTurmaDocs()">Gerar
                        Documentos</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Gerar Documento (Individual) -->
    <div class="modal fade" id="documentoModal" tabindex="-1" aria-labelledby="documentoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="documentoModalLabel">Gerar Novo Documento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <iframe id="documentoIframe" src="" style="width: 100%; height: 500px; border: none;"></iframe>
                </div>
            </div>
        </div>
    </div>

    <script>
        var docModal;
        var batchModal;
        var documentGenerated = false;

        function openDocumentoModal(pessoaId = null) {
            documentGenerated = false;
            var iframe = document.getElementById('documentoIframe');
            var url = '<?php echo $baseHref; ?>?m=documentos_gerados&a=form&modelo_id=<?php echo $id; ?>&modal=1&layout=clean';
            if (pessoaId) {
                url += '&pessoa_id=' + pessoaId;
            }
            iframe.src = url;
            docModal.show();
        }

        function generateTurmaDocs() {
            var turmaId = document.getElementById('selectTurmaBatch').value;
            if (!turmaId) {
                alert('Por favor, selecione uma turma.');
                return;
            }

            if (!confirm('Deseja gerar um documento para todos os alunos desta turma? Isso pode levar alguns segundos.')) {
                return;
            }

            var btn = event.target;
            var originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Gerando...';

            var formData = new FormData();
            formData.append('modelo_id', '<?php echo $id; ?>');
            formData.append('turma_id', turmaId);

            fetch('<?php echo BASE_URL; ?>app/modules/documentos_gerados/ajax_generate_turma.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Sucesso! Foram gerados ' + data.count + ' documentos.');
                        window.location.reload();
                    } else {
                        alert('Erro: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Erro ao processar a requisição.');
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                });
        }

        function closeDocumentoModal(success) {
            if (success) {
                documentGenerated = true;
                docModal.hide();
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            docModal = new bootstrap.Modal(document.getElementById('documentoModal'));
            batchModal = new bootstrap.Modal(document.getElementById('modalBatchTurma'));

            // Recarregar se algo foi gerado ao fechar o modal
            document.getElementById('documentoModal').addEventListener('hidden.bs.modal', function () {
                if (documentGenerated) {
                    window.location.reload();
                }
            });

            // Persistência da aba na URL
            var tabButtons = document.querySelectorAll('button[data-bs-toggle="tab"]');
            tabButtons.forEach(function (button) {
                button.addEventListener('shown.bs.tab', function (event) {
                    var tabId = event.target.getAttribute('data-bs-target').replace('#', '');
                    var url = new URL(window.location);
                    url.searchParams.set('tab', tabId);
                    window.history.replaceState({}, '', url);
                });
            });
        });
    </script>