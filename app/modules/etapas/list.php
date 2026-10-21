<?php
// app/modules/etapas/list.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';
require_once __DIR__ . '/../../helpers/pagination.php';
require_once __DIR__ . '/../../helpers/bulk_actions.php';

$tableName = '104_etapas';
$engine = new CrudEngine($pdo, $tableName);

$page = (int) ($_GET['page'] ?? 1);
$search = $_GET['search'] ?? '';

$result = $engine->getList($page, 20, $search, [], 'dia_1 DESC');
$data = $result['data'];
$totalPages = $result['totalPages'];
$schema = $engine->getSchema();
$primaryKey = $engine->getPrimaryKey();

$baseHref = BASE_URL . 'index.php';

$formatPtBrDate = function ($date) {
    if (!$date || $date === '0000-00-00')
        return '—';
    return date('d/m/Y', strtotime($date));
};

$agendaStmt = $pdo->prepare("
    SELECT c.data, c.inicio, c.termino, c.aula, t.nome AS turma_nome,
        p1.apelido AS prof1, p2.apelido AS prof2, p3.apelido AS prof3, p4.apelido AS prof4
    FROM 104_cronograma c
    LEFT JOIN 104_turmas t ON t.id = c.turma
    LEFT JOIN 104_professores p1 ON p1.id = c.professor_1
    LEFT JOIN 104_professores p2 ON p2.id = c.professor_2
    LEFT JOIN 104_professores p3 ON p3.id = c.professor_3
    LEFT JOIN 104_professores p4 ON p4.id = c.professor_4
    WHERE c.etapa = :etapa
    ORDER BY c.data ASC, c.inicio ASC
");

$buildAgendaEvents = function ($etapaId) use ($agendaStmt) {
    $agendaStmt->execute([':etapa' => $etapaId]);
    $events = [];
    foreach ($agendaStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $professores = array_filter([$row['prof1'], $row['prof2'], $row['prof3'], $row['prof4']]);
        $titulo = trim((string) $row['turma_nome']);
        if (!empty($professores)) {
            $titulo .= ' - ' . implode('/', $professores);
        }
        if (trim((string) $row['aula']) !== '') {
            $titulo .= ' - ' . trim((string) $row['aula']);
        }
        $events[] = [
            'titulo' => $titulo,
            'start' => date('Ymd\THis', strtotime($row['data'] . ' ' . $row['inicio'])),
            'end' => date('Ymd\THis', strtotime($row['data'] . ' ' . $row['termino'])),
        ];
    }
    return $events;
};
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <?php echo get_table_label($tableName); ?>
    </h4>
    <div class="d-flex gap-2 align-items-center">
        <form method="get" class="d-flex gap-1 align-items-center">
            <input type="hidden" name="m" value="etapas">
            <input type="hidden" name="a" value="list">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Buscar..."
                value="<?php echo htmlspecialchars($search); ?>" style="width: 200px;">
            <button type="submit" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-search"></i>
            </button>
            <?php if ($search): ?>
                <a href="<?php echo $baseHref; ?>?m=etapas&a=list" class="btn btn-outline-danger btn-sm"
                    title="Limpar busca">
                    <i class="bi bi-x-lg"></i>
                </a>
            <?php endif; ?>
        </form>
        <?php render_bulk_buttons(); ?>
        <a href="<?php echo $baseHref; ?>?m=etapas&a=form" class="btn btn-primary btn-sm">
            + Novo(a)
            <?php echo get_table_label($tableName); ?>
        </a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 40px;" class="text-center">
                            <input type="checkbox" class="form-check-input" id="bulk-select-all">
                        </th>
                        <th style="width: 140px;">Ações</th>
                        <?php
                        $vCols = 0;
                        foreach ($schema as $col => $info):
                            if ($vCols > 6)
                                break;
                            $vCols++;
                            ?>
                            <th>
                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $col))); ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data)): ?>
                        <tr>
                            <td colspan="<?php echo min(count($schema) + 1, 8); ?>" class="text-center py-4 text-muted">
                                Nenhum registro encontrado.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($data as $row): ?>
                            <tr>
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input bulk-select-item"
                                        value="<?php echo htmlspecialchars($row[$primaryKey]); ?>">
                                </td>
                                <td class="text-start">
                                    <div class="d-flex gap-1 align-items-center">
                                        <a href="<?php echo $baseHref; ?>?m=etapas&a=view&id=<?php echo $row[$primaryKey]; ?>"
                                            class="action-btn action-btn--ver" title="Visualizar" data-bs-toggle="tooltip"><i
                                                class="bi bi-eye"></i></a>
                                        <a href="<?php echo $baseHref; ?>?m=etapas&a=form&id=<?php echo $row[$primaryKey]; ?>"
                                            class="action-btn action-btn--editar" title="Editar" data-bs-toggle="tooltip"><i
                                                class="bi bi-pencil"></i></a>
                                        <div class="dropend">
                                            <a href="javascript:void(0)" class="action-btn action-btn--mais"
                                                data-bs-toggle="dropdown" aria-expanded="false"
                                                title="Mais ações"><i class="bi bi-three-dots-vertical"></i></a>
                                            <ul class="dropdown-menu action-kebab-menu">
                                                <li><a class="dropdown-item"
                                                        href="<?php echo $baseHref; ?>?m=etapas&a=cronograma&id=<?php echo $row[$primaryKey]; ?>"><i
                                                            class="bi bi-table text-indigo" style="color:#4f46e5;"></i>
                                                        Cronograma (grade)</a></li>
                                                <li><a class="dropdown-item" href="javascript:void(0)"
                                                        data-events='<?php echo htmlspecialchars(json_encode($buildAgendaEvents($row[$primaryKey]), JSON_UNESCAPED_UNICODE), ENT_QUOTES); ?>'
                                                        onclick="generateAgenda(this)"><i
                                                            class="bi bi-calendar-event text-primary"></i> Gerar
                                                        agenda</a></li>
                                                <li><a class="dropdown-item"
                                                        href="<?php echo $baseHref; ?>?m=etapas&a=copy&id=<?php echo $row[$primaryKey]; ?>"><i
                                                            class="bi bi-files text-secondary"></i> Copiar</a></li>
                                                <li><a class="dropdown-item"
                                                        href="<?php echo $baseHref; ?>?m=etapas&a=delete&id=<?php echo $row[$primaryKey]; ?>"
                                                        onclick="return confirm('Tem certeza que deseja excluir este registro?')"><i
                                                            class="bi bi-trash text-danger"></i> Excluir</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                                <?php
                                $vCols = 0;
                                foreach ($schema as $col => $info):
                                    if ($vCols > 6)
                                        break;
                                    $vCols++;

                                    if (isset($row[$col . '_label'])) {
                                        $val = $row[$col . '_label'];
                                    } else {
                                        $val = $row[$col];
                                        if ($info['type'] === 'date') {
                                            $val = $formatPtBrDate((string) $val);
                                        }
                                    }

                                    if (strlen((string) $val) > 50) {
                                        $val = substr((string) $val, 0, 47) . '...';
                                    }
                                    ?>
                                    <td>
                                        <?php echo htmlspecialchars((string) $val); ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($totalPages > 1): ?>
    <div class="mt-3">
        <?php echo Pagination::render($page, $totalPages, $baseHref, ['m' => 'etapas', 'a' => 'list', 'search' => $search]); ?>
    </div>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    });

    function generateAgenda(el) {
        var events = [];
        try {
            events = JSON.parse(el.dataset.events || '[]');
        } catch (e) {
            events = [];
        }

        if (!events.length) {
            alert('Nenhum agendamento encontrado para gerar a agenda.');
            return;
        }

        if (!confirm('Isso abrirá ' + events.length + ' aba(s) com eventos no Google Agenda. Deseja continuar?')) {
            return;
        }

        events.forEach(function (ev) {
            var url = 'https://calendar.google.com/calendar/u/0/r/eventedit'
                + '?text=' + encodeURIComponent(ev.titulo)
                + '&dates=' + ev.start + '/' + ev.end
                + '&ctz=America/Sao_Paulo';
            window.open(url, '_blank');
        });
    }
</script>
<?php
// Render dynamic modal for bulk update
render_bulk_modal('etapas', $tableName, $schema, $engine->getLookupsOptions());

// Render JavaScript for handling bulk actions
render_bulk_js('etapas');
?>