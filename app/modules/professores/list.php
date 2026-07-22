<?php
// app/modules/professores/list.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';
require_once __DIR__ . '/../../helpers/arquivos.php';
require_once __DIR__ . '/../../helpers/pagination.php';
require_once __DIR__ . '/../../helpers/bulk_actions.php';

$tableName = '104_professores';
$engine = new CrudEngine($pdo, $tableName);

$page = (int) ($_GET['page'] ?? 1);
$search = $_GET['search'] ?? '';

$result = $engine->getList($page, 20, $search);
$data = $result['data'];
$totalPages = $result['totalPages'];
$schema = $engine->getSchema();
$primaryKey = $engine->getPrimaryKey();

// Resolve professor names (104_professores.pessoa -> 101_pessoas.id)
if (!empty($data)) {
    $pessoaIds = array_unique(array_filter(array_column($data, 'pessoa')));
    if (!empty($pessoaIds)) {
        $placeholders = implode(',', array_fill(0, count($pessoaIds), '?'));
        $sqlNames = "SELECT id, nome 
                     FROM 101_pessoas 
                     WHERE id IN ($placeholders)";
        $stmtNames = $pdo->prepare($sqlNames);
        $stmtNames->execute(array_values($pessoaIds));
        $pessoaNames = $stmtNames->fetchAll(PDO::FETCH_KEY_PAIR);

        foreach ($data as &$row) {
            if (isset($pessoaNames[$row['pessoa']])) {
                $row['pessoa_label'] = $pessoaNames[$row['pessoa']];
            }
        }
        unset($row);
    }
}

$baseHref = BASE_URL . 'index.php';

?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <?php echo get_table_label($tableName); ?>
    </h4>
    <div class="d-flex gap-2 align-items-center">
        <form method="get" class="d-flex gap-1 align-items-center">
            <input type="hidden" name="m" value="professores">
            <input type="hidden" name="a" value="list">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Buscar..."
                value="<?php echo htmlspecialchars($search); ?>" style="width: 200px;">
            <button type="submit" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-search"></i>
            </button>
            <?php if ($search): ?>
                <a href="<?php echo $baseHref; ?>?m=professores&a=list" class="btn btn-outline-danger btn-sm"
                    title="Limpar busca">
                    <i class="bi bi-x-lg"></i>
                </a>
            <?php endif; ?>
        </form>
        <?php render_bulk_buttons(); ?>
        <a href="<?php echo $baseHref; ?>?m=professores&a=form" class="btn btn-primary btn-sm">
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
                        <th style="width: 100px;">Ações</th>
                        <?php
                        foreach ($schema as $col => $info):
                            if ($col === 'documentos')
                                continue;
                            ?>
                            <th>
                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $col))); ?>
                            </th>
                        <?php endforeach; ?>
                        <th style="width: 40px;"><i class="bi bi-paperclip"></i></th>
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
                                        <a href="<?php echo $baseHref; ?>?m=professores&a=view&id=<?php echo $row[$primaryKey]; ?>"
                                            class="action-btn action-btn--ver" title="Visualizar" data-bs-toggle="tooltip"><i
                                                class="bi bi-eye"></i></a>
                                        <a href="<?php echo $baseHref; ?>?m=professores&a=form&id=<?php echo $row[$primaryKey]; ?>"
                                            class="action-btn action-btn--editar" title="Editar" data-bs-toggle="tooltip"><i
                                                class="bi bi-pencil"></i></a>
                                        <div class="dropdown">
                                            <a href="javascript:void(0)" class="action-btn action-btn--mais"
                                                data-bs-toggle="dropdown" data-bs-strategy="fixed" aria-expanded="false"
                                                title="Mais ações"><i class="bi bi-three-dots-vertical"></i></a>
                                            <ul class="dropdown-menu dropdown-menu-end action-kebab-menu">
                                                <li><a class="dropdown-item" href="javascript:void(0)"
                                                        onclick="sendDocs(<?php echo $row[$primaryKey]; ?>, this)"><i
                                                            class="bi bi-envelope text-primary"></i> Enviar documentos
                                                        FGE</a></li>
                                                <li><a class="dropdown-item"
                                                        href="<?php echo $baseHref; ?>?m=professores&a=delete&id=<?php echo $row[$primaryKey]; ?>"
                                                        onclick="return confirm('Tem certeza que deseja excluir este registro?')"><i
                                                            class="bi bi-trash text-danger"></i> Excluir</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                                <?php
                                foreach ($schema as $col => $info):
                                    if ($col === 'documentos')
                                        continue;

                                    if (isset($row[$col . '_label'])) {
                                        $val = $row[$col . '_label'];
                                    } else {
                                        $val = $row[$col];
                                    }

                                    if (strlen((string) $val) > 50) {
                                        $val = substr((string) $val, 0, 47) . '...';
                                    }
                                    ?>
                                    <td>
                                        <?php echo htmlspecialchars((string) $val); ?>
                                    </td>
                                <?php endforeach; ?>
                                <td>
                                    <?php
                                    $docs = parse_arquivos_json($row['documentos'] ?? '');
                                    if (!empty($docs)): ?>
                                        <span class="badge bg-info" title="<?php echo count($docs); ?> documento(s)"
                                            data-bs-toggle="tooltip">
                                            <?php echo count($docs); ?>
                                        </span>
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

<?php if ($totalPages > 1): ?>
    <div class="mt-3">
        <?php echo Pagination::render($page, $totalPages, $baseHref, ['m' => 'professores', 'a' => 'list', 'search' => $search]); ?>
    </div>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    });

    function sendDocs(id, element) {
        if (!confirm('Deseja enviar a documentação deste professor para a FGE?')) return;

        const originalHtml = element.innerHTML;
        element.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        element.style.pointerEvents = 'none';

        fetch('<?php echo $baseHref; ?>?m=professores&a=ajax_send_docs&id=' + id)
            .then(async response => {
                const text = await response.text();
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        alert('E-mail enviado com sucesso!');
                    } else {
                        alert('Erro ao enviar e-mail: ' + (data.message || 'Erro desconhecido.'));
                    }
                } catch (e) {
                    console.error('Invalid JSON response:', text);
                    alert('Erro na requisição ao servidor. O servidor retornou uma resposta inválida.');
                }
            })
            .catch(error => {
                console.error('Network Error:', error);
                alert('Erro de conexão ou rede.');
            })
            .finally(() => {
                element.innerHTML = originalHtml;
                element.style.pointerEvents = 'auto';
            });
    }
</script>
<?php
// Render dynamic modal for bulk update
render_bulk_modal('professores', $tableName, $schema, $engine->getLookupsOptions());

// Render JavaScript for handling bulk actions
render_bulk_js('professores');
?>