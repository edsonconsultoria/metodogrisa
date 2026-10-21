<?php
// app/modules/professores/view.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';
require_once __DIR__ . '/../../helpers/arquivos.php';

$tableName = '104_professores';
$engine = new CrudEngine($pdo, $tableName);

$id = (int) ($_GET['id'] ?? 0);
$record = $engine->getRecord($id);

if (!$record) {
    header("Location: " . BASE_URL . "index.php?m=professores&a=list&msg=" . urlencode("Professor não encontrado.") . "&type=danger");
    exit;
}

// Resolve pessoa name
if (isset($record['pessoa'])) {
    $sqlName = "SELECT nome FROM 101_pessoas WHERE id = ?";
    $stmtName = $pdo->prepare($sqlName);
    $stmtName->execute([$record['pessoa']]);
    $record['pessoa_label'] = $stmtName->fetchColumn() ?: $record['pessoa'];
}

$schema = $engine->getSchema();
$baseHref = BASE_URL . 'index.php';

function render_professor_documents(string $jsonString): string
{
    $docs = parse_arquivos_json($jsonString);
    if (empty($docs)) {
        return '<span class="text-muted">—</span>';
    }

    $html = '<div class="list-group list-group-flush border rounded p-1" style="max-height:220px;overflow-y:auto;background:#f8f9fa;">';
    foreach ($docs as $doc) {
        $name   = htmlspecialchars($doc['name'] ?? 'Arquivo');
        $file   = $doc['file'] ?? '';
        $size   = isset($doc['size']) && $doc['size'] > 0 ? human_filesize($doc['size']) : '';
        $url    = htmlspecialchars(get_file_url($file));

        $html .= '<div class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 bg-transparent">';
        $html .= '  <div class="d-flex align-items-center overflow-hidden">';
        $html .= '    <i class="bi bi-file-earmark-pdf text-danger me-2 fs-5"></i>';
        $html .= '    <div class="text-truncate">';
        $html .= '      <a href="' . $url . '" target="_blank" class="text-decoration-none fw-medium text-dark" title="' . $name . '">' . $name . '</a>';
        if ($size) {
            $html .= '<small class="text-muted d-block" style="font-size:0.75rem;">' . $size . '</small>';
        }
        $html .= '    </div>';
        $html .= '  </div>';
        $html .= '  <a href="' . $url . '" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2" title="Abrir"><i class="bi bi-box-arrow-up-right"></i></a>';
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Visualizar Professor</h4>
    <div class="d-flex gap-2">
        <button type="button" id="btn-send-docs" onclick="sendDocs(<?php echo $id; ?>, this)"
            class="btn btn-success btn-sm" title="Enviar documentos para a FGE">
            <i class="bi bi-envelope"></i> Enviar docs FGE
        </button>
        <a href="<?php echo $baseHref; ?>?m=professores&a=form&id=<?php echo $id; ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil"></i> Editar
        </a>
        <a href="<?php echo $baseHref; ?>?m=professores&a=list" class="btn btn-outline-secondary btn-sm">Voltar</a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="row g-3">
            <?php foreach ($schema as $col => $info): ?>
                <?php if ($col === 'documentos'): ?>
                    <div class="col-md-6 mb-3 border-bottom pb-2">
                        <label class="fw-bold text-muted small d-block">Documentos</label>
                        <div class="mt-1">
                            <?php echo render_professor_documents($record['documentos'] ?? ''); ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="col-md-6 mb-3 border-bottom pb-2">
                        <label class="fw-bold text-muted small d-block">
                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $col))); ?>
                        </label>
                        <div class="fs-6">
                            <?php
                            $val = isset($record[$col . '_label']) ? $record[$col . '_label'] : ($record[$col] ?? '—');
                            if ($info['type'] === 'date') {
                                $val = ($val && $val !== '0000-00-00') ? date('d/m/Y', strtotime((string) $val)) : '—';
                            } elseif ($info['type'] === 'datetime' || str_contains($col, 'data_')) {
                                $val = ($val && $val !== '0000-00-00 00:00:00') ? date('d/m/Y H:i:s', strtotime((string) $val)) : '—';
                            }
                            echo nl2br(htmlspecialchars((string) ($val === '' ? '—' : $val)));
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
function sendDocs(id, element) {
    if (!confirm('Deseja enviar a documentação deste professor para a FGE?')) return;

    const originalHtml = element.innerHTML;
    element.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enviando...';
    element.disabled = true;

    fetch('<?php echo $baseHref; ?>?m=professores&a=ajax_send_docs&id=' + id)
        .then(async response => {
            const text = await response.text();
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    alert('E-mail enviado com sucesso para a FGE!');
                } else {
                    alert('Erro ao enviar e-mail: ' + (data.message || 'Erro desconhecido.'));
                }
            } catch (e) {
                console.error('Invalid JSON response:', text);
                alert('Erro na requisição ao servidor.');
            }
        })
        .catch(error => {
            console.error('Network Error:', error);
            alert('Erro de conexão ou rede.');
        })
        .finally(() => {
            element.innerHTML = originalHtml;
            element.disabled = false;
        });
}
</script>
