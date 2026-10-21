<?php
// prevenção a erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// app/modules/alunos/view.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';
require_once __DIR__ . '/../../helpers/list_state.php';

$tableName = '104_alunos';
$engine = new CrudEngine($pdo, $tableName);

$engine->setLookups([
    'turma' => ['table' => '104_turmas', 'label' => 'nome']
]);

$id = (int) ($_GET['id'] ?? 0);
$record = $engine->getRecord($id);

if (!$record) {
    header("Location: " . BASE_URL . "index.php?m=alunos&a=list&msg=" . urlencode("Aluno não encontrado.") . "&type=danger");
    exit;
}

// Resolve student name
if (isset($record['aluno'])) {
    $sqlName = "SELECT nome FROM 101_pessoas WHERE id = ?";
    $stmtName = $pdo->prepare($sqlName);
    $stmtName->execute([$record['aluno']]);
    $record['aluno_label'] = $stmtName->fetchColumn() ?: $record['aluno'];
}

$schema = $engine->getSchema();
$baseHref = BASE_URL . 'index.php';

// Preserve return param for Voltar / Editar buttons
$returnParam = $_REQUEST['return'] ?? '';
$returnUrl = list_state_return_destination('alunos', 'list');
$editUrl = $baseHref . '?m=alunos&a=form&id=' . $id
    . ($returnParam !== '' ? '&return=' . urlencode($returnParam) : '');

$formatPtBrDate = function ($date) {
    if (!$date || $date === '0000-00-00')
        return '—';
    return date('d/m/Y', strtotime($date));
};

/**
 * Renders a JSON file list in read-only mode (same visual as form.php).
 */
function render_view_documents(string $jsonString, string $fieldName): string
{
    if (empty($jsonString)) {
        return '<span class="text-muted">—</span>';
    }
    $data = json_decode($jsonString, true);
    if (!is_array($data) || empty($data)) {
        return '<span class="text-muted">—</span>';
    }

    $maxHeight = ($fieldName === 'documentos_recebidos') ? '800px' : '220px';
    $html = '<div class="list-group list-group-flush border rounded p-1" style="max-height:' . $maxHeight . ';overflow-y:auto;background:#f8f9fa;">';
    foreach ($data as $file) {
        $name = $file['name'] ?? '';
        $usrName = $file['usrName'] ?? 'Arquivo';
        $size = isset($file['size']) ? round($file['size'] / 1024, 1) . ' KB' : '';
        $url = BASE_URL . $name;

        $html .= '<div class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 bg-transparent">';
        $html .= '  <div class="d-flex align-items-center overflow-hidden">';
        $html .= '    <i class="bi bi-file-earmark-pdf text-danger me-2 fs-5"></i>';
        $html .= '    <div class="text-truncate">';
        $html .= '      <a href="' . htmlspecialchars($url) . '" target="_blank" class="text-decoration-none fw-medium text-dark" title="' . htmlspecialchars($usrName) . '">';
        $html .= htmlspecialchars($usrName);
        $html .= '      </a>';
        if ($size) {
            $html .= '<small class="text-muted d-block" style="font-size:0.75rem;">' . $size . '</small>';
        }
        $html .= '    </div>';
        $html .= '  </div>';
        $html .= '  <a href="' . htmlspecialchars($url) . '" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2" title="Abrir"><i class="bi bi-box-arrow-up-right"></i></a>';
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}

// Columns that are rendered separately (skip in generic loop)
$jsonDocCols = ['documentos_recebidos', 'documentos_enviados'];
$hiddenCols = ['rne', 'diarios_classe', 'gerar_certificados'];
$checkboxCols = [
    'rg_carteira_conselho',
    'cpf',
    'cer_nascimento_casamento',
    'comprovante_residencia',
    'fge',
    'tcc',
    'uso_imagem',
    'cert_grad',
    'emitido_cert_metodo_grisa',
    'emitido_cert_universidade'
];
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Visualizar Aluno</h4>
    <div class="d-flex gap-2">
        <a href="<?php echo $editUrl; ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil"></i> Editar
        </a>
        <a href="<?php echo $returnUrl; ?>" class="btn btn-outline-secondary btn-sm">Voltar</a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="row g-3">
            <?php foreach ($schema as $col => $info): ?>
                <?php if (in_array($col, $hiddenCols)): ?>
                    <?php continue; ?>
                <?php elseif (in_array($col, $jsonDocCols)): ?>
                    <!-- JSON document field: rendered as file list -->
                    <div class="col-md-6 mb-3 border-bottom pb-2">
                        <label class="fw-bold text-muted small d-block">
                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $col))); ?>
                        </label>
                        <div class="mt-1">
                            <?php echo render_view_documents($record[$col] ?? '', $col); ?>
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

                            if (in_array($col, $checkboxCols)) {
                                echo $val === '1'
                                    ? '<span class="badge bg-success">SIM</span>'
                                    : '<span class="badge bg-secondary">NÃO</span>';
                            } else {
                                if ($info['type'] === 'date') {
                                    $val = $formatPtBrDate((string) $val);
                                } elseif ($info['type'] === 'datetime' || str_contains($col, 'data_')) {
                                    $val = ($val && $val !== '0000-00-00 00:00:00') ? date('d/m/Y H:i:s', strtotime((string) $val)) : '—';
                                }
                                echo nl2br(htmlspecialchars((string) ($val === '' ? '—' : $val)));
                            }

                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>


        </div>
    </div>
</div>
