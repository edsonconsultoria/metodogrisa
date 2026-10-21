<?php
// app/modules/101_mailling/list.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';

$tableName = '101_mailling';
$engine = new CrudEngine($pdo, $tableName);

$engine->setLookups([
    'destinatarios' => ['table' => '101_mailling_lista', 'label' => 'nome_da_lista']
]);

$page = (int) ($_GET['page'] ?? 1);
$search = $_GET['search'] ?? '';

$result = $engine->getList($page, 20, $search);
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
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <?php echo get_table_label($tableName); ?>
    </h4>
    <a href="<?php echo $baseHref; ?>?m=<?php echo $tableName; ?>&a=form" class="btn btn-primary btn-sm">
        + Novo(a)
        <?php echo get_table_label($tableName); ?>
    </a>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 100px;">Ações</th>
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
                                <td class="text-start">
                                    <div class="d-flex gap-1">
                                        <a href="<?php echo $baseHref; ?>?m=<?php echo $tableName; ?>&a=view&id=<?php echo $row[$primaryKey]; ?>"
                                            class="action-btn action-btn--ver" title="Visualizar" data-bs-toggle="tooltip"><i
                                                class="bi bi-eye"></i></a>
                                        <a href="<?php echo $baseHref; ?>?m=<?php echo $tableName; ?>&a=form&id=<?php echo $row[$primaryKey]; ?>"
                                            class="action-btn action-btn--editar" title="Editar" data-bs-toggle="tooltip"><i
                                                class="bi bi-pencil"></i></a>
                                        <a href="<?php echo $baseHref; ?>?m=<?php echo $tableName; ?>&a=delete&id=<?php echo $row[$primaryKey]; ?>"
                                            class="action-btn action-btn--excluir" title="Excluir" data-bs-toggle="tooltip"
                                            onclick="return confirm('Tem certeza que deseja excluir este registro?')"><i
                                                class="bi bi-trash"></i></a>
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

                                    // HTML Handling for preview
                                    if ($col === 'mensagem') {
                                        $val = strip_tags((string) $val);
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
        <?php echo Pagination::render($page, $totalPages, $baseHref, ['m' => $tableName, 'a' => 'list']); ?>
    </div>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    });
</script>