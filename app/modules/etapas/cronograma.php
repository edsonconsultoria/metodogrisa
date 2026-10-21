<?php
// app/modules/etapas/cronograma.php
// Editor em grade (formato do cronograma Word): linhas = horários, colunas = turmas (Tema/Prof.)

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';

$tableName = '104_etapas';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$baseHref = BASE_URL . 'index.php';

if ($id <= 0) {
    echo '<div class="alert alert-warning">Etapa não informada.</div>';
    return;
}

$engine = new CrudEngine($pdo, $tableName);
$etapa = $engine->getRecord($id);
if (!$etapa) {
    echo '<div class="alert alert-danger">Etapa não encontrada.</div>';
    return;
}

// Turmas vinculadas à etapa (colunas). Ordem decrescente p/ casar com o Word (2026, 2025, 2024).
$stmtT = $pdo->prepare("
    SELECT t.id, t.nome, et.etapa_da_turma
    FROM 104_etapas_turmas et
    INNER JOIN 104_turmas t ON t.id = et.turma
    WHERE et.etapa = :etapa
    ORDER BY t.nome DESC, t.id DESC
");
$stmtT->execute([':etapa' => $id]);
$turmas = $stmtT->fetchAll(PDO::FETCH_ASSOC);

// Professores (para os selects)
$professores = $pdo->query("SELECT id, apelido FROM 104_professores ORDER BY apelido ASC")->fetchAll(PDO::FETCH_ASSOC);

// Cronograma existente desta etapa
$stmtC = $pdo->prepare("
    SELECT id, turma, data, inicio, termino, aula, professor_1, professor_2, professor_3, professor_4,
           sem_video, link_para_aula
    FROM 104_cronograma
    WHERE etapa = :etapa
    ORDER BY data ASC, inicio ASC
");
$stmtC->execute([':etapa' => $id]);
$rows = $stmtC->fetchAll(PDO::FETCH_ASSOC);

// Dias da etapa
$dias = [];
foreach (['dia_1', 'dia_2'] as $dk) {
    $d = $etapa[$dk] ?? '';
    if ($d && $d !== '0000-00-00') {
        $dias[] = $d;
    }
}

// Monta estrutura: grid[data][inicio] = ['termino'=>..., 'cells'=>[turma_id=>row]]
$grid = [];
foreach ($dias as $d) {
    $grid[$d] = [];
}
foreach ($rows as $r) {
    $d = $r['data'];
    if (!isset($grid[$d])) {
        // aula em data fora dos dias cadastrados: ainda assim exibe
        $grid[$d] = [];
        if (!in_array($d, $dias, true)) {
            $dias[] = $d;
        }
    }
    $ini = substr((string) $r['inicio'], 0, 5);
    if (!isset($grid[$d][$ini])) {
        $grid[$d][$ini] = ['termino' => substr((string) $r['termino'], 0, 5), 'cells' => []];
    }
    if (empty($grid[$d][$ini]['termino'])) {
        $grid[$d][$ini]['termino'] = substr((string) $r['termino'], 0, 5);
    }
    $grid[$d][$ini]['cells'][$r['turma']] = $r;
}
foreach ($grid as $d => &$slots) {
    ksort($slots);
}
unset($slots);
sort($dias);

// Helpers de data PT-BR
$diasSemana = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
$meses = ['', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
$fmtDiaExtenso = function ($d) use ($diasSemana, $meses) {
    $ts = strtotime($d);
    return $diasSemana[(int) date('w', $ts)] . ' – ' . date('d', $ts) . ' de ' . $meses[(int) date('n', $ts)] . ' de ' . date('Y', $ts);
};

// Quantos selects de professor renderizar numa célula
$profSlots = function ($cell) {
    $n = 0;
    foreach (['professor_1', 'professor_2', 'professor_3', 'professor_4'] as $pk) {
        if (!empty($cell[$pk])) {
            $n++;
        }
    }
    return min(4, max(2, $n + 1));
};

// Render de um <select> de professor
$renderProf = function ($name, $selected) use ($professores) {
    $html = '<select name="' . $name . '" class="form-select form-select-sm mb-1 js-professor-select2">';
    $html .= '<option value="">— Prof. —</option>';
    foreach ($professores as $p) {
        $sel = ((string) $selected === (string) $p['id']) ? ' selected' : '';
        $html .= '<option value="' . $p['id'] . '"' . $sel . '>' . htmlspecialchars($p['apelido']) . '</option>';
    }
    $html .= '</select>';
    return $html;
};
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <i class="bi bi-table me-1 text-indigo"></i>
        Cronograma — <?php echo htmlspecialchars($etapa['nome']); ?>
    </h4>
    <a href="<?php echo $baseHref; ?>?m=etapas&a=list" class="btn btn-outline-secondary btn-sm">Voltar</a>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'success'): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-1"></i> Cronograma salvo com sucesso.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (empty($turmas)): ?>
    <div class="alert alert-warning">
        Esta etapa não possui turmas vinculadas. Vincule turmas em
        <a href="<?php echo $baseHref; ?>?m=etapas_turmas&a=list">Etapas × Turmas</a> antes de montar o cronograma.
    </div>
    <?php return; ?>
<?php endif; ?>

<form action="<?php echo $baseHref; ?>?m=etapas&a=cronograma_save" method="post" id="cronograma-form">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="etapa" value="<?php echo (int) $id; ?>">

    <?php foreach ($dias as $di => $data): ?>
        <input type="hidden" name="dia[<?php echo $di; ?>]" value="<?php echo htmlspecialchars($data); ?>">

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-indigo text-white fw-bold" style="background-color:#4f46e5;">
                <?php echo htmlspecialchars($fmtDiaExtenso($data)); ?>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0 cronograma-grid">
                    <thead class="table-light text-center">
                        <tr>
                            <th rowspan="2" style="width:110px;">Horários</th>
                            <?php foreach ($turmas as $t): ?>
                                <th colspan="2">
                                    <?php echo htmlspecialchars($t['nome']); ?>
                                    <?php if (!empty($t['etapa_da_turma'])): ?>
                                        <div class="small fw-normal text-muted"><?php echo htmlspecialchars($t['etapa_da_turma']); ?></div>
                                    <?php endif; ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <?php foreach ($turmas as $t): ?>
                                <th style="min-width:220px;">Tema</th>
                                <th style="min-width:150px;">Prof.</th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody data-day="<?php echo $di; ?>" data-turmas="<?php echo count($turmas); ?>">
                        <?php
                        $slots = $grid[$data] ?? [];
                        // Turmas que possuem ao menos uma aula neste dia.
                        // Turma sem nenhuma aula no dia não tem coluna editável (evita exibir
                        // dia que "não existe" para aquela turma, ex.: domingo da Turma 2024).
                        $turmasNoDia = [];
                        foreach ($slots as $slotRow) {
                            foreach (array_keys($slotRow['cells']) as $tid) {
                                $turmasNoDia[(int) $tid] = true;
                            }
                        }
                        $si = 0;
                        foreach ($slots as $ini => $slot):
                            ?>
                            <tr>
                                <td class="text-center">
                                    <input type="time" name="slot[<?php echo $di; ?>][<?php echo $si; ?>][inicio]"
                                        class="form-control form-control-sm mb-1" value="<?php echo htmlspecialchars($ini); ?>">
                                    <input type="time" name="slot[<?php echo $di; ?>][<?php echo $si; ?>][termino]"
                                        class="form-control form-control-sm" value="<?php echo htmlspecialchars($slot['termino']); ?>">
                                </td>
                                <?php foreach ($turmas as $t): ?>
                                    <?php if (!isset($turmasNoDia[(int) $t['id']])): ?>
                                        <td class="bg-light text-center text-muted align-middle">—</td>
                                        <td class="bg-light text-center text-muted align-middle">—</td>
                                        <?php continue; ?>
                                    <?php endif; ?>
                                    <?php
                                    $cell = $slot['cells'][$t['id']] ?? null;
                                    $base = "cell[$di][$si][{$t['id']}]";
                                    $nProf = $cell ? $profSlots($cell) : 2;
                                    ?>
                                    <?php
                                    $semVideo = !empty($cell['sem_video']) && (string) $cell['sem_video'] === '1';
                                    $linkAula = $cell['link_para_aula'] ?? '';
                                    $svId = "sv_{$di}_{$si}_{$t['id']}";
                                    ?>
                                    <td>
                                        <input type="hidden" name="<?php echo $base; ?>[id]"
                                            value="<?php echo $cell ? (int) $cell['id'] : ''; ?>">
                                        <textarea name="<?php echo $base; ?>[aula]" rows="2" maxlength="150"
                                            class="form-control form-control-sm"
                                            placeholder="Tema da aula"><?php echo htmlspecialchars($cell['aula'] ?? ''); ?></textarea>
                                        <div class="form-check form-switch mt-1">
                                            <input class="form-check-input js-sem-video" type="checkbox"
                                                name="<?php echo $base; ?>[sem_video]" value="1"
                                                id="<?php echo $svId; ?>" <?php echo $semVideo ? 'checked' : ''; ?>>
                                            <label class="form-check-label small" for="<?php echo $svId; ?>">Sem vídeo</label>
                                        </div>
                                        <input type="url" name="<?php echo $base; ?>[link_para_aula]"
                                            class="form-control form-control-sm mt-1 js-link-aula<?php echo $semVideo ? ' d-none' : ''; ?>"
                                            placeholder="Link da Vídeo Aula (YouTube)"
                                            value="<?php echo htmlspecialchars($linkAula); ?>">
                                        <div class="js-sugestao-filename mt-1 px-2 py-1 rounded bg-warning-subtle border border-warning-subtle small shadow-sm-hover<?php echo ($semVideo || !empty($linkAula)) ? ' d-none' : ''; ?>"
                                            style="font-size:0.75rem; cursor: pointer; transition: all 0.2s ease; user-select: none;"
                                            title="Clique para copiar o nome sugerido"
                                            data-turma="<?php echo htmlspecialchars($t['nome']); ?>"
                                            data-etapa="<?php echo htmlspecialchars($etapa['nome']); ?>">
                                            <i class="bi bi-lightbulb-fill text-warning me-1"></i>
                                            <span class="text-muted">Sugerido:</span>
                                            <code class="text-dark fw-semibold js-sugestao-text" style="font-size:0.75rem;"></code>
                                            <i class="bi bi-clipboard ms-1 text-muted small"></i>
                                        </div>
                                    </td>
                                    <td>
                                        <?php for ($pi = 0; $pi < $nProf; $pi++):
                                            $pv = $cell['professor_' . ($pi + 1)] ?? '';
                                            echo $renderProf("{$base}[prof][]", $pv);
                                        endfor; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php $si++; endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="<?php echo 1 + count($turmas) * 2; ?>" class="bg-light">
                                <button type="button" class="btn btn-outline-primary btn-sm add-slot-btn"
                                    data-day="<?php echo $di; ?>" data-next="<?php echo $si; ?>">
                                    <i class="bi bi-plus-lg"></i> Adicionar horário
                                </button>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="d-flex gap-2 mb-4 sticky-bottom bg-body py-2">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-save me-1"></i> Salvar tudo
        </button>
        <a href="<?php echo $baseHref; ?>?m=etapas&a=list" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999">
    <div id="copyToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body"><i class="bi bi-check-circle me-1"></i> Copiado!</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<script>
    (function () {
        // Opções de professor para linhas adicionadas dinamicamente
        var PROFS = <?php echo json_encode(array_map(function ($p) {
                        return ['id' => $p['id'], 'apelido' => $p['apelido']];
                    }, $professores), JSON_UNESCAPED_UNICODE); ?>;
        var NTURMAS = <?php echo count($turmas); ?>;
        var TURMA_IDS = <?php echo json_encode(array_map(function ($t) {
            return (int) $t['id'];
        }, $turmas)); ?>;
        var TURMA_NAMES = <?php echo json_encode(array_combine(array_column($turmas, 'id'), array_column($turmas, 'nome')), JSON_UNESCAPED_UNICODE); ?>;
        var ETAPA_NAME = <?php echo json_encode($etapa['nome'], JSON_UNESCAPED_UNICODE); ?>;

        function escapeHtml(text) {
            return (text || '')
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        function toFilename(s) {
            return (s || '')
                .normalize('NFD').replace(/[\u0300-\u036f]/g, '') // remove acentos
                .replace(/[^a-zA-Z0-9\s_-]/g, '')
                .trim().replace(/\s+/g, '_');
        }

        function recalculateCellHint(td) {
            const hintBox = td.querySelector('.js-sugestao-filename');
            if (!hintBox) return;

            const semVideoChk = td.querySelector('.js-sem-video');
            const linkInput = td.querySelector('.js-link-aula');
            
            // Se sem_video está marcado ou link de vídeo está preenchido, esconde a sugestão
            if ((semVideoChk && semVideoChk.checked) || (linkInput && linkInput.value.trim() !== '')) {
                hintBox.classList.add('d-none');
                return;
            }
            hintBox.classList.remove('d-none');

            const turma = toFilename(hintBox.dataset.turma || 'SemTurma');
            const etapa = toFilename(hintBox.dataset.etapa || 'SemEtapa');
            
            const textarea = td.querySelector('textarea');
            const titulo = toFilename(textarea ? textarea.value : 'SemTitulo');

            // Pegar nomes dos professores da td adjacente
            const nextTd = td.nextElementSibling;
            const profsList = [];
            if (nextTd) {
                const selects = nextTd.querySelectorAll('select');
                selects.forEach(sel => {
                    if (sel && sel.value) {
                        const opt = sel.options[sel.selectedIndex];
                        if (opt && opt.text && opt.text.trim() !== '' && opt.text.trim() !== '— Prof. —') {
                            profsList.push(toFilename(opt.text.trim()));
                        }
                    }
                });
            }
            const profs = profsList.length ? profsList.join('-') : 'SemProf';
            
            const hintText = hintBox.querySelector('.js-sugestao-text');
            if (hintText) {
                hintText.textContent = `${turma}-${etapa}-${titulo}-${profs}`;
            }
        }

        function profSelect(name) {
            var s = '<select name="' + name + '" class="form-select form-select-sm mb-1 js-professor-select2"><option value="">— Prof. —</option>';
            PROFS.forEach(function (p) {
                s += '<option value="' + p.id + '">' + p.apelido + '</option>';
            });
            s += '</select>';
            return s;
        }

        function initProfSelect2(scope) {
            if (typeof jQuery === 'undefined' || typeof jQuery.fn.select2 === 'undefined') {
                return;
            }
            jQuery(scope || document).find('.js-professor-select2').each(function () {
                var $el = jQuery(this);
                if ($el.hasClass('select2-hidden-accessible')) {
                    return;
                }
                $el.select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    placeholder: '— Prof. —',
                    allowClear: true
                });
                $el.on('change', function() {
                    const profTd = this.closest('td');
                    if (profTd) {
                        const themeTd = profTd.previousElementSibling;
                        if (themeTd) recalculateCellHint(themeTd);
                    }
                });
            });
        }

        initProfSelect2(document);

        document.querySelectorAll('.add-slot-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var day = this.dataset.day;
                var idx = parseInt(this.dataset.next, 10);
                this.dataset.next = idx + 1;
                var tbody = document.querySelector('tbody[data-day="' + day + '"]');

                var tr = document.createElement('tr');
                var html = '<td class="text-center">' +
                    '<input type="time" name="slot[' + day + '][' + idx + '][inicio]" class="form-control form-control-sm mb-1">' +
                    '<input type="time" name="slot[' + day + '][' + idx + '][termino]" class="form-control form-control-sm">' +
                    '</td>';
                TURMA_IDS.forEach(function (tid) {
                    var base = 'cell[' + day + '][' + idx + '][' + tid + ']';
                    var svId = 'sv_' + day + '_' + idx + '_' + tid;
                    var tName = TURMA_NAMES[tid] || '';
                    html += '<td>' +
                        '<input type="hidden" name="' + base + '[id]" value="">' +
                        '<textarea name="' + base + '[aula]" rows="2" maxlength="150" class="form-control form-control-sm" placeholder="Tema da aula"></textarea>' +
                        '<div class="form-check form-switch mt-1">' +
                        '<input class="form-check-input js-sem-video" type="checkbox" name="' + base + '[sem_video]" value="1" id="' + svId + '">' +
                        '<label class="form-check-label small" for="' + svId + '">Sem vídeo</label>' +
                        '</div>' +
                        '<input type="url" name="' + base + '[link_para_aula]" class="form-control form-control-sm mt-1 js-link-aula" placeholder="Link da Vídeo Aula (YouTube)">' +
                        '<div class="js-sugestao-filename mt-1 px-2 py-1 rounded bg-warning-subtle border border-warning-subtle small shadow-sm-hover" style="font-size:0.75rem; cursor: pointer; transition: all 0.2s ease; user-select: none;" title="Clique para copiar o nome sugerido" data-turma="' + escapeHtml(tName) + '" data-etapa="' + escapeHtml(ETAPA_NAME) + '">' +
                        '<i class="bi bi-lightbulb-fill text-warning me-1"></i>' +
                        '<span class="text-muted">Sugerido:</span>' +
                        '<code class="text-dark fw-semibold js-sugestao-text" style="font-size:0.75rem;"></code>' +
                        '<i class="bi bi-clipboard ms-1 text-muted small"></i>' +
                        '</div>' +
                        '</td>';
                    html += '<td>' + profSelect(base + '[prof][]') + profSelect(base + '[prof][]') + '</td>';
                });
                tr.innerHTML = html;
                tbody.appendChild(tr);
                initProfSelect2(tr);

                // Recalcular para as novas células adicionadas
                tr.querySelectorAll('td').forEach(td => {
                    if (td.querySelector('.js-sugestao-filename')) {
                        recalculateCellHint(td);
                    }
                });
            });
        });

        // Mostra/esconde o campo de link conforme "Sem vídeo", e recalcula sugestões
        const form = document.getElementById('cronograma-form');
        form.addEventListener('change', function (e) {
            if (e.target.classList.contains('js-sem-video')) {
                var td = e.target.closest('td');
                if (td) {
                    var link = td.querySelector('.js-link-aula');
                    if (link) {
                        link.classList.toggle('d-none', e.target.checked);
                    }
                    recalculateCellHint(td);
                }
            } else if (e.target.classList.contains('js-link-aula')) {
                var td = e.target.closest('td');
                if (td) recalculateCellHint(td);
            } else if (e.target.tagName === 'SELECT') {
                var profTd = e.target.closest('td');
                if (profTd && profTd.previousElementSibling) {
                    recalculateCellHint(profTd.previousElementSibling);
                }
            }
        });

        form.addEventListener('input', function (e) {
            if (e.target.tagName === 'TEXTAREA' || e.target.classList.contains('js-link-aula')) {
                const td = e.target.closest('td');
                if (td) recalculateCellHint(td);
            }
        });

        window.copyToClipboard = function (text, message = 'Copiado!') {
            if (!text) return;
            const showToast = () => {
                const toastEl = document.getElementById('copyToast');
                const toastBody = toastEl.querySelector('.toast-body');
                if (toastBody) toastBody.innerHTML = `<i class="bi bi-check-circle me-1"></i> ${message}`;
                const toast = new bootstrap.Toast(toastEl, { delay: 2000 });
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
            const el = document.createElement('textarea');
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

        // Copiar ao clicar na sugestão
        form.addEventListener('click', function(e) {
            const hintBox = e.target.closest('.js-sugestao-filename');
            if (hintBox) {
                const textEl = hintBox.querySelector('.js-sugestao-text');
                if (textEl && textEl.textContent) {
                    copyToClipboard(textEl.textContent, 'Nome sugerido copiado!');
                }
            }
        });

        // Inicializar sugestões existentes na carga da página
        document.querySelectorAll('.cronograma-grid td').forEach(td => {
            if (td.querySelector('.js-sugestao-filename')) {
                recalculateCellHint(td);
            }
        });
    })();
</script>
