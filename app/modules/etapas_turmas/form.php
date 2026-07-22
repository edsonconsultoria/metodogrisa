<?php
// app/modules/etapas_turmas/form.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';
require_once __DIR__ . '/../../helpers/list_state.php';

$tableName = '104_etapas_turmas';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$copyFrom = isset($_GET['copy_from']) ? (int) $_GET['copy_from'] : 0;
$engine = new CrudEngine($pdo, $tableName);

// Lookups
$etapasOptions = $pdo->query("SELECT id, nome, dia_1, dia_2 FROM 104_etapas ORDER BY dia_1 DESC, nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$turmasOptions = $pdo->query("SELECT id, nome FROM 104_turmas ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$professoresOptions = $pdo->query("SELECT id, apelido FROM 104_professores ORDER BY apelido ASC")->fetchAll(PDO::FETCH_ASSOC);

$schema = $engine->getSchema();
$primaryKey = $engine->getPrimaryKey();

$lookupId = $id > 0 ? $id : $copyFrom;
$record = $lookupId > 0 ? $engine->getRecord($lookupId) : null;
$baseHref = BASE_URL . 'index.php';
$activeTab = $_GET['tab'] ?? 'dados';
$returnUrl = list_state_return_destination('etapas_turmas', 'list');

$aulasList = [];
$totalAulas = 0;

if ($id > 0 && !empty($record['etapa']) && !empty($record['turma'])) {
    $stmtAulas = $pdo->prepare("
        SELECT c.id, c.data, c.inicio, c.termino, c.aula,
               c.professor_1, c.professor_2, c.professor_3, c.professor_4,
               c.arquivos, c.link_para_aula, c.sem_video,
               p1.apelido AS prof1, p2.apelido AS prof2,
               p3.apelido AS prof3, p4.apelido AS prof4
        FROM 104_cronograma c
        LEFT JOIN 104_professores p1 ON p1.id = c.professor_1
        LEFT JOIN 104_professores p2 ON p2.id = c.professor_2
        LEFT JOIN 104_professores p3 ON p3.id = c.professor_3
        LEFT JOIN 104_professores p4 ON p4.id = c.professor_4
        WHERE c.etapa = :etapa AND c.turma = :turma
        ORDER BY c.data ASC, c.inicio ASC
    ");
    $stmtAulas->execute([
        ':etapa' => (int) $record['etapa'],
        ':turma' => (int) $record['turma']
    ]);
    $aulasList = $stmtAulas->fetchAll(PDO::FETCH_ASSOC);
    $totalAulas = count($aulasList);
}

$etapaNomeAtual = '';
$turmaNomeAtual = '';
if ($record) {
    foreach ($etapasOptions as $opt) {
        if ((string) $opt['id'] === (string) ($record['etapa'] ?? '')) {
            $etapaNomeAtual = $opt['nome'];
            break;
        }
    }
    foreach ($turmasOptions as $opt) {
        if ((string) $opt['id'] === (string) ($record['turma'] ?? '')) {
            $turmaNomeAtual = $opt['nome'];
            break;
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <?php echo ($id ? 'Editar' : 'Novo') . ' ' . get_table_label($tableName); ?>
    </h4>
    <a href="<?php echo $returnUrl; ?>" class="btn btn-outline-secondary btn-sm">Voltar</a>
</div>

<ul class="nav nav-tabs mb-3" id="etapasTurmasTabs">
    <li class="nav-item">
        <a class="nav-link <?php echo $activeTab === 'dados' ? 'active' : ''; ?>"
            href="<?php echo $baseHref; ?>?m=etapas_turmas&a=form<?php echo $id ? '&id=' . $id : ''; ?>&tab=dados<?php echo isset($_GET['return']) ? '&return=' . urlencode($_GET['return']) : ''; ?>">
            <i class="bi bi-card-text me-1"></i>Dados
        </a>
    </li>
    <?php if ($id > 0): ?>
        <li class="nav-item">
            <a class="nav-link <?php echo $activeTab === 'aulas' ? 'active' : ''; ?>"
                href="<?php echo $baseHref; ?>?m=etapas_turmas&a=form&id=<?php echo $id; ?>&tab=aulas<?php echo isset($_GET['return']) ? '&return=' . urlencode($_GET['return']) : ''; ?>">
                <i class="bi bi-calendar3 me-1"></i>Aulas
                <span class="badge bg-secondary ms-1"><?php echo $totalAulas; ?></span>
            </a>
        </li>
    <?php endif; ?>
</ul>

<?php if ($activeTab === 'dados'): ?>
    <div class="card shadow-sm">
        <div class="card-body">
            <form action="<?php echo $baseHref; ?>?m=etapas_turmas&a=save" method="post">
                <?php if ($id): ?>
                    <input type="hidden" name="<?php echo $primaryKey; ?>" value="<?php echo htmlspecialchars($id); ?>">
                <?php endif; ?>
                <input type="hidden" name="return" value="<?php echo htmlspecialchars($_GET['return'] ?? ''); ?>">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Etapa <span class="text-danger">*</span></label>
                        <select name="etapa" id="etapa-select" class="form-select" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($etapasOptions as $opt): ?>
                                <option value="<?php echo $opt['id']; ?>"
                                    data-dia-1="<?php echo htmlspecialchars((string) ($opt['dia_1'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-dia-2="<?php echo htmlspecialchars((string) ($opt['dia_2'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    <?php echo ((string) ($record['etapa'] ?? '') === (string) $opt['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($opt['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Turma <span class="text-danger">*</span></label>
                        <select name="turma" id="turma-select" class="form-select" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($turmasOptions as $opt): ?>
                                <option value="<?php echo $opt['id']; ?>" <?php echo ((string) ($record['turma'] ?? '') === (string) $opt['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($opt['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Etapa da Turma (Ex: Etapa I) <span class="text-danger">*</span></label>
                        <input type="text" name="etapa_da_turma" class="form-control"
                            value="<?php echo htmlspecialchars($record['etapa_da_turma'] ?? ''); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Link Zoom Dia 1</label>
                        <textarea name="link_zoom_dia_1" id="link-zoom-dia-1" class="form-control"
                            rows="3"><?php echo htmlspecialchars($record['link_zoom_dia_1'] ?? ''); ?></textarea>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Link Zoom Dia 2</label>
                        <textarea name="link_zoom_dia_2" id="link-zoom-dia-2" class="form-control"
                            rows="3"><?php echo htmlspecialchars($record['link_zoom_dia_2'] ?? ''); ?></textarea>
                    </div>
                </div>

                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        var etapaSelect = document.getElementById('etapa-select');
                        var turmaSelect = document.getElementById('turma-select');
                        var campoDia1 = document.getElementById('link-zoom-dia-1');
                        var campoDia2 = document.getElementById('link-zoom-dia-2');

                        if (!etapaSelect || !turmaSelect || !campoDia1 || !campoDia2) {
                            return;
                        }

                        var diasSemana = [
                            'Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira',
                            'Quinta-feira', 'Sexta-feira', 'Sábado'
                        ];

                        function formatarData(valor) {
                            if (!valor) {
                                return null;
                            }
                            // Espera "YYYY-MM-DD" (eventualmente com horário); ignora o que vier após o espaço/T.
                            var partes = String(valor).split(/[ T]/)[0].split('-');
                            if (partes.length < 3) {
                                return null;
                            }
                            var ano = parseInt(partes[0], 10);
                            var mes = parseInt(partes[1], 10);
                            var dia = parseInt(partes[2], 10);
                            if (!ano || !mes || !dia) {
                                return null;
                            }
                            var data = new Date(ano, mes - 1, dia);
                            var dd = String(dia).padStart(2, '0');
                            var mm = String(mes).padStart(2, '0');
                            return {
                                dataBr: dd + '/' + mm + '/' + ano,
                                diaSemana: diasSemana[data.getDay()]
                            };
                        }

                        function montarTexto(diaIso) {
                            var opt = etapaSelect.options[etapaSelect.selectedIndex];
                            var nomeEtapa = etapaSelect.value ? (opt ? opt.text.trim() : '') : '';
                            var nomeTurma = turmaSelect.value
                                ? turmaSelect.options[turmaSelect.selectedIndex].text.trim()
                                : '';
                            var info = formatarData(diaIso);

                            var partes = [];
                            if (nomeEtapa) partes.push(nomeEtapa);
                            if (nomeTurma) partes.push(nomeTurma);
                            if (info) {
                                partes.push(info.dataBr);
                                partes.push(info.diaSemana);
                            }
                            return partes.join(' ');
                        }

                        function preencherSeVazio() {
                            var opt = etapaSelect.options[etapaSelect.selectedIndex];
                            var dia1 = opt ? opt.getAttribute('data-dia-1') : '';
                            var dia2 = opt ? opt.getAttribute('data-dia-2') : '';

                            if (campoDia1.value.trim() === '') {
                                campoDia1.value = montarTexto(dia1);
                            }
                            if (campoDia2.value.trim() === '') {
                                campoDia2.value = montarTexto(dia2);
                            }
                        }

                        etapaSelect.addEventListener('change', preencherSeVazio);
                        turmaSelect.addEventListener('change', preencherSeVazio);

                        // Ao abrir o formulário (editar ou criar): preenche se estiver em branco.
                        preencherSeVazio();
                    });
                </script>

                <div class="mt-4 pt-3 border-top">
                    <button type="submit" class="btn btn-primary">Salvar
                        <?php echo get_table_label($tableName); ?>
                    </button>
                    <a href="<?php echo $returnUrl; ?>" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
<?php elseif ($activeTab === 'aulas' && $id > 0): ?>
    <?php
    function etapasTurmasYoutubeThumb($url)
    {
        $url = (string) $url;
        if ($url === '') {
            return BASE_URL . 'assets/img/aula_nao_gravada_peq.png';
        }
        preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $url, $m);
        $ytId = $m[1] ?? null;
        return $ytId ? "https://img.youtube.com/vi/$ytId/mqdefault.jpg" : BASE_URL . 'assets/img/aula_nao_gravada_peq.png';
    }
    ?>
    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold">Aulas da Etapa/Turma</span>
            <a href="<?php echo $baseHref; ?>?m=etapas_turmas&a=generate_lessons&id=<?php echo $id; ?>&etapa=<?php echo (int) $record['etapa']; ?>&turma=<?php echo (int) $record['turma']; ?>&return=<?php echo urlencode('m=etapas_turmas&a=form&id=' . $id . '&tab=aulas' . (isset($_GET['return']) ? '&return=' . urlencode($_GET['return']) : '')); ?>"
                class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg"></i> Nova Aula
            </a>
        </div>
        <div class="card-body p-0">
            <?php if (empty($aulasList)): ?>
                <div class="p-4 text-center text-muted">Nenhuma aula cadastrada para esta etapa/turma.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 90px;">Vídeo</th>
                                <th style="width: 90px;">Data</th>
                                <th style="width: 120px;">Horário</th>
                                <th>Tema / Aula</th>
                                <th style="width: 220px;">Professores</th>
                                <th style="width: 70px;">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($aulasList as $aula): ?>
                                <?php
                                $profs = array_filter([
                                    $aula['prof1'] ?? '',
                                    $aula['prof2'] ?? '',
                                    $aula['prof3'] ?? '',
                                    $aula['prof4'] ?? ''
                                ]);
                                $semVideoAula = (string) ($aula['sem_video'] ?? '0') === '1';
                                $linkAulaAtual = (string) ($aula['link_para_aula'] ?? '');
                                $thumbAula = etapasTurmasYoutubeThumb($linkAulaAtual);
                                ?>
                                <tr>
                                    <td>
                                        <?php if (!$semVideoAula && $linkAulaAtual !== ''): ?>
                                            <a href="<?php echo htmlspecialchars($linkAulaAtual, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                                                <img src="<?php echo htmlspecialchars($thumbAula, ENT_QUOTES, 'UTF-8'); ?>" alt="Miniatura do vídeo"
                                                    class="rounded" style="width: 70px; height: 44px; object-fit: cover;"
                                                    onerror="this.src='<?php echo BASE_URL; ?>assets/img/aula_nao_gravada_peq.png'">
                                            </a>
                                        <?php else: ?>
                                            <img src="<?php echo htmlspecialchars($thumbAula, ENT_QUOTES, 'UTF-8'); ?>" alt="Sem vídeo"
                                                class="rounded" style="width: 70px; height: 44px; object-fit: cover; opacity:.6;">
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(date('d/m/Y', strtotime((string) $aula['data']))); ?></td>
                                    <td><?php echo htmlspecialchars(substr((string) $aula['inicio'], 0, 5) . ' - ' . substr((string) $aula['termino'], 0, 5)); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars((string) $aula['aula']); ?></td>
                                    <td><?php echo htmlspecialchars(!empty($profs) ? implode(', ', $profs) : 'Não informado'); ?>
                                    </td>
                                    <td>
                                        <button type="button" class="action-btn action-btn--editar js-editar-aula"
                                            title="Editar aula" data-bs-toggle="modal" data-bs-target="#modalEditarAula"
                                            data-id="<?php echo (int) $aula['id']; ?>"
                                            data-data="<?php echo htmlspecialchars((string) $aula['data'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-inicio="<?php echo htmlspecialchars((string) $aula['inicio'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-termino="<?php echo htmlspecialchars((string) $aula['termino'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-aula="<?php echo htmlspecialchars((string) $aula['aula'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-professor-1="<?php echo (int) ($aula['professor_1'] ?? 0); ?>"
                                            data-professor-2="<?php echo (int) ($aula['professor_2'] ?? 0); ?>"
                                            data-professor-3="<?php echo (int) ($aula['professor_3'] ?? 0); ?>"
                                            data-professor-4="<?php echo (int) ($aula['professor_4'] ?? 0); ?>"
                                            data-arquivos="<?php echo htmlspecialchars((string) ($aula['arquivos'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-link="<?php echo htmlspecialchars((string) ($aula['link_para_aula'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-sem-video="<?php echo htmlspecialchars((string) ($aula['sem_video'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal fade" id="modalEditarAula" tabindex="-1" aria-labelledby="modalEditarAulaLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form action="<?php echo $baseHref; ?>?m=etapas_cronogramas&a=save" method="post" id="formEditarAulaModal"
                    enctype="multipart/form-data" data-etapa-nome="<?php echo htmlspecialchars($etapaNomeAtual, ENT_QUOTES, 'UTF-8'); ?>"
                    data-turma-nome="<?php echo htmlspecialchars($turmaNomeAtual, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalEditarAulaLabel">Editar Aula</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="modal-aula-id" value="">
                        <input type="hidden" name="etapa" value="<?php echo (int) $record['etapa']; ?>">
                        <input type="hidden" name="turma" value="<?php echo (int) $record['turma']; ?>">
<input type="hidden" name="return"
    value="<?php echo htmlspecialchars('m=etapas_turmas&a=form&id=' . $id . '&tab=aulas' . (isset($_GET['return']) ? '&return=' . urlencode($_GET['return']) : '')); ?>">

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Data <span class="text-danger">*</span></label>
                                <input type="date" name="data" id="modal-aula-data" class="form-control" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Início <span class="text-danger">*</span></label>
                                <input type="time" name="inicio" id="modal-aula-inicio" class="form-control" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Término <span class="text-danger">*</span></label>
                                <input type="time" name="termino" id="modal-aula-termino" class="form-control" required>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label">Aula / Tema <span class="text-danger">*</span></label>
                                <input type="text" name="aula" id="modal-aula-tema" class="form-control" required>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Professor 1 <span class="text-danger">*</span></label>
                                <select name="professor_1" id="modal-professor-1" class="form-select js-professor-select2"
                                    required>
                                    <option value="0">Selecione...</option>
                                    <?php foreach ($professoresOptions as $opt): ?>
                                        <option value="<?php echo (int) $opt['id']; ?>">
                                            <?php echo htmlspecialchars($opt['apelido']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Professor 2</label>
                                <select name="professor_2" id="modal-professor-2" class="form-select js-professor-select2">
                                    <option value="">Selecione...</option>
                                    <?php foreach ($professoresOptions as $opt): ?>
                                        <option value="<?php echo (int) $opt['id']; ?>">
                                            <?php echo htmlspecialchars($opt['apelido']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Professor 3</label>
                                <select name="professor_3" id="modal-professor-3" class="form-select js-professor-select2">
                                    <option value="">Selecione...</option>
                                    <?php foreach ($professoresOptions as $opt): ?>
                                        <option value="<?php echo (int) $opt['id']; ?>">
                                            <?php echo htmlspecialchars($opt['apelido']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Professor 4</label>
                                <select name="professor_4" id="modal-professor-4" class="form-select js-professor-select2">
                                    <option value="">Selecione...</option>
                                    <?php foreach ($professoresOptions as $opt): ?>
                                        <option value="<?php echo (int) $opt['id']; ?>">
                                            <?php echo htmlspecialchars($opt['apelido']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Vídeo Aula (Somente YouTube)</label>
                                <input type="url" name="link_para_aula" id="modal-aula-link" class="form-control"
                                    placeholder="https://www.youtube.com/watch?v=...">
                                <div class="js-sugestao-filename mt-1 px-2 py-1 rounded bg-warning-subtle border border-warning-subtle small shadow-sm-hover d-none"
                                    id="modal-aula-sugestao"
                                    style="font-size:0.75rem; cursor: pointer; transition: all 0.2s ease; user-select: none;"
                                    title="Clique para copiar o nome sugerido">
                                    <i class="bi bi-lightbulb-fill text-warning me-1"></i>
                                    <span class="text-muted">Sugerido:</span>
                                    <code id="modal-aula-sugestao-text" class="text-dark fw-semibold" style="font-size:0.75rem;"></code>
                                    <i class="bi bi-clipboard ms-1 text-muted small"></i>
                                </div>
                            </div>

                            <div class="col-md-6 d-flex align-items-end pb-1">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="sem_video"
                                        id="modal-aula-sem-video" value="1">
                                    <label class="form-check-label" for="modal-aula-sem-video">
                                        Sem vídeo
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label">Arquivos Anexos</label>
                                <div id="modal-aula-drop-zone" class="border-dashed rounded-3 p-4 text-center bg-white mb-2"
                                    style="border: 2px dashed #cbd5e0; transition: border-color .2s, background-color .2s; cursor: pointer;">
                                    <i class="bi bi-cloud-arrow-up display-5 text-primary mb-2"></i>
                                    <p class="mb-0 text-muted">Arraste arquivos aqui ou clique para selecionar</p>
                                    <span class="text-xs text-muted">PDF, DOCX, ZIP, JPG, PNG e outros</span>
                                </div>
                                <input type="file" name="novos_arquivos[]" id="modal-aula-file-input" class="d-none" multiple>
                                <div id="modal-aula-new-files-preview" class="small mb-3 d-flex flex-wrap gap-2"></div>

                                <input type="hidden" name="arquivos_json" id="modal-aula-arquivos-json">
                                <div id="modal-aula-arquivos-lista"
                                    class="list-group list-group-flush border rounded-3 overflow-hidden shadow-xs"></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar Aula</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999">
        <div id="copyToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body"><i class="bi bi-check-circle me-1"></i> Copiado!</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var botoesEditarAula = document.querySelectorAll('.js-editar-aula');
            if (!botoesEditarAula.length) {
                return;
            }

            var modalEditarAula = document.getElementById('modalEditarAula');
            var formEditarAula = document.getElementById('formEditarAulaModal');
            var etapaNomeAtual = formEditarAula ? (formEditarAula.dataset.etapaNome || '') : '';
            var turmaNomeAtual = formEditarAula ? (formEditarAula.dataset.turmaNome || '') : '';

            if (typeof jQuery !== 'undefined' && typeof jQuery.fn.select2 !== 'undefined' && modalEditarAula) {
                var $profSelects = jQuery(modalEditarAula).find('.js-professor-select2');
                $profSelects.select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    placeholder: 'Selecione...',
                    allowClear: true,
                    dropdownParent: jQuery(modalEditarAula)
                });
            }

            var campoId = document.getElementById('modal-aula-id');
            var campoData = document.getElementById('modal-aula-data');
            var campoInicio = document.getElementById('modal-aula-inicio');
            var campoTermino = document.getElementById('modal-aula-termino');
            var campoTema = document.getElementById('modal-aula-tema');
            var campoProf1 = document.getElementById('modal-professor-1');
            var campoProf2 = document.getElementById('modal-professor-2');
            var campoProf3 = document.getElementById('modal-professor-3');
            var campoProf4 = document.getElementById('modal-professor-4');
            var campoLink = document.getElementById('modal-aula-link');
            var campoSemVideo = document.getElementById('modal-aula-sem-video');

            // ─── Sugestão de nome de arquivo ───────────────────────────
            var hintEl = document.getElementById('modal-aula-sugestao');
            var hintText = document.getElementById('modal-aula-sugestao-text');

            function toFilename(s) {
                return (s || '')
                    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                    .replace(/[^a-zA-Z0-9\s_-]/g, '')
                    .trim().replace(/\s+/g, '_');
            }

            function recalcularSugestao() {
                if (!hintText) return;
                var turma = toFilename(turmaNomeAtual || 'SemTurma');
                var etapa = toFilename(etapaNomeAtual || 'SemEtapa');
                var titulo = toFilename(campoTema ? campoTema.value : 'SemTitulo');

                var profsList = [];
                [campoProf1, campoProf2, campoProf3, campoProf4].forEach(function (sel) {
                    if (sel && sel.value) {
                        var opt = sel.options[sel.selectedIndex];
                        if (opt && opt.text && opt.text.trim() !== '' && opt.text.trim() !== 'Selecione...') {
                            profsList.push(toFilename(opt.text.trim()));
                        }
                    }
                });
                var profs = profsList.length ? profsList.join('-') : 'SemProf';

                hintText.textContent = turma + '-' + etapa + '-' + titulo + '-' + profs;
            }

            function atualizarVisibilidadeSugestao() {
                if (!hintEl) return;
                var linkVal = campoLink ? campoLink.value.trim() : '';
                var semVideo = campoSemVideo ? campoSemVideo.checked : false;
                hintEl.classList.toggle('d-none', !!(linkVal || semVideo));
            }

            if (hintEl) {
                hintEl.addEventListener('click', function () {
                    if (hintText && hintText.textContent) {
                        copyToClipboard(hintText.textContent, 'Nome sugerido copiado!');
                    }
                });
            }
            [campoTema, campoProf1, campoProf2, campoProf3, campoProf4].forEach(function (el) {
                if (!el) return;
                el.addEventListener(el.tagName === 'SELECT' ? 'change' : 'input', recalcularSugestao);
            });
            if (campoLink) {
                campoLink.addEventListener('input', function () {
                    recalcularSugestao();
                    atualizarVisibilidadeSugestao();
                });
            }
            if (campoSemVideo) {
                campoSemVideo.addEventListener('change', function () {
                    recalcularSugestao();
                    atualizarVisibilidadeSugestao();
                });
            }

            // ─── Upload de arquivos (arrastar/soltar) ──────────────────
            var listaArquivosEl = document.getElementById('modal-aula-arquivos-lista');
            var campoArquivosJson = document.getElementById('modal-aula-arquivos-json');
            var dropZone = document.getElementById('modal-aula-drop-zone');
            var fileInput = document.getElementById('modal-aula-file-input');
            var novosArquivosPreview = document.getElementById('modal-aula-new-files-preview');

            if (listaArquivosEl && typeof Sortable !== 'undefined') {
                Sortable.create(listaArquivosEl, {
                    animation: 150,
                    handle: '.drag-handle',
                    ghostClass: 'bg-light',
                    onEnd: function () {
                        atualizarEstadoArquivos();
                    }
                });
            }

            function atualizarEstadoArquivos() {
                var items = [];
                listaArquivosEl.querySelectorAll('.file-item').forEach(function (item) {
                    items.push({
                        name: item.dataset.name,
                        file: item.dataset.file,
                        size: parseInt(item.dataset.size, 10) || 0,
                        type: item.dataset.type || ''
                    });
                });
                campoArquivosJson.value = JSON.stringify(items);
            }

            window.removeFileModalAula = function (btn) {
                if (confirm('Deseja remover este arquivo?')) {
                    btn.closest('.file-item').remove();
                    atualizarEstadoArquivos();
                }
            };

            function renderizarArquivos(jsonStr) {
                var files = [];
                try { files = JSON.parse(jsonStr || '[]'); } catch (e) { }
                if (!Array.isArray(files)) files = [];

                listaArquivosEl.innerHTML = '';

                if (files.length === 0) {
                    listaArquivosEl.innerHTML = '<div class="p-3 text-center text-muted small">Nenhum arquivo anexado</div>';
                    campoArquivosJson.value = '[]';
                    return;
                }

                files.forEach(function (f) {
                    var item = document.createElement('div');
                    item.className = 'list-group-item file-item d-flex align-items-center p-2';
                    item.dataset.name = f.name;
                    item.dataset.file = f.file;
                    item.dataset.size = f.size;
                    item.dataset.type = f.type || '';

                    item.innerHTML =
                        '<div class="drag-handle me-2" style="cursor: move;"><i class="bi bi-grip-vertical text-muted"></i></div>' +
                        '<div class="flex-grow-1 text-truncate" title="' + f.name + '">' +
                        '<i class="bi bi-file-earmark me-1 text-primary"></i>' +
                        '<span class="small fw-semibold">' + f.name + '</span>' +
                        '</div>' +
                        '<button type="button" class="btn btn-link text-danger btn-sm p-0 ms-2" onclick="removeFileModalAula(this)">' +
                        '<i class="bi bi-x-circle"></i>' +
                        '</button>';
                    listaArquivosEl.appendChild(item);
                });
                atualizarEstadoArquivos();
            }

            if (dropZone && fileInput) {
                dropZone.addEventListener('click', function () { fileInput.click(); });
                dropZone.addEventListener('dragover', function (e) {
                    e.preventDefault();
                    dropZone.style.borderColor = '#4299e1';
                    dropZone.style.backgroundColor = '#f7fafc';
                });
                dropZone.addEventListener('dragleave', function () {
                    dropZone.style.borderColor = '#cbd5e0';
                    dropZone.style.backgroundColor = '#ffffff';
                });
                dropZone.addEventListener('drop', function (e) {
                    e.preventDefault();
                    dropZone.style.borderColor = '#cbd5e0';
                    dropZone.style.backgroundColor = '#ffffff';
                    fileInput.files = e.dataTransfer.files;
                    atualizarPreviewNovosArquivos();
                });
                fileInput.addEventListener('change', atualizarPreviewNovosArquivos);
            }

            function atualizarPreviewNovosArquivos() {
                if (!novosArquivosPreview) return;
                novosArquivosPreview.innerHTML = '';
                Array.from(fileInput.files).forEach(function (f) {
                    var badge = document.createElement('span');
                    badge.className = 'badge bg-primary d-flex align-items-center gap-1 p-2';
                    badge.innerHTML = '<i class="bi bi-file-earmark-plus"></i> ' + f.name + ' <i class="bi bi-check-circle-fill ms-1"></i>';
                    novosArquivosPreview.appendChild(badge);
                });
            }

            window.copyToClipboard = function (text, message) {
                message = message || 'Copiado!';
                if (!text) return;
                var showToast = function () {
                    var toastEl = document.getElementById('copyToast');
                    if (!toastEl) return;
                    var toastBody = toastEl.querySelector('.toast-body');
                    if (toastBody) toastBody.innerHTML = '<i class="bi bi-check-circle me-1"></i> ' + message;
                    var toast = new bootstrap.Toast(toastEl, { delay: 2000 });
                    toast.show();
                };

                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(showToast).catch(function () {
                        copyFallback(text, showToast);
                    });
                } else {
                    copyFallback(text, showToast);
                }
            };

            function copyFallback(text, callback) {
                var el = document.createElement('textarea');
                el.value = text;
                el.style.position = 'fixed';
                el.style.opacity = '0';
                document.body.appendChild(el);
                el.select();
                try {
                    document.execCommand('copy');
                    if (callback) callback();
                } catch (err) {
                    console.error('Erro ao copiar', err);
                }
                document.body.removeChild(el);
            }

            botoesEditarAula.forEach(function (botao) {
                botao.addEventListener('click', function () {
                    campoId.value = botao.getAttribute('data-id') || '';
                    campoData.value = botao.getAttribute('data-data') || '';
                    campoInicio.value = (botao.getAttribute('data-inicio') || '').substring(0, 5);
                    campoTermino.value = (botao.getAttribute('data-termino') || '').substring(0, 5);
                    campoTema.value = botao.getAttribute('data-aula') || '';
                    campoProf1.value = botao.getAttribute('data-professor-1') || '0';
                    campoProf2.value = (botao.getAttribute('data-professor-2') || '0') === '0' ? '' : botao.getAttribute('data-professor-2');
                    campoProf3.value = (botao.getAttribute('data-professor-3') || '0') === '0' ? '' : botao.getAttribute('data-professor-3');
                    campoProf4.value = (botao.getAttribute('data-professor-4') || '0') === '0' ? '' : botao.getAttribute('data-professor-4');
                    campoLink.value = botao.getAttribute('data-link') || '';
                    campoSemVideo.checked = (botao.getAttribute('data-sem-video') || '0') === '1';

                    if (typeof jQuery !== 'undefined' && typeof jQuery.fn.select2 !== 'undefined') {
                        jQuery(campoProf1).trigger('change.select2');
                        jQuery(campoProf2).trigger('change.select2');
                        jQuery(campoProf3).trigger('change.select2');
                        jQuery(campoProf4).trigger('change.select2');
                    }

                    renderizarArquivos(botao.getAttribute('data-arquivos') || '[]');
                    if (novosArquivosPreview) novosArquivosPreview.innerHTML = '';
                    if (fileInput) fileInput.value = '';

                    recalcularSugestao();
                    atualizarVisibilidadeSugestao();
                });
            });
        });
    </script>
<?php endif; ?>