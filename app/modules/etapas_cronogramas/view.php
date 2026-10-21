<?php
// app/modules/etapas_cronogramas/view.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';
$cronogramaHelper = __DIR__ . '/../../helpers/cronograma_arquivos.php';
if (file_exists($cronogramaHelper)) {
    require_once $cronogramaHelper;
} elseif (!function_exists('cronograma_parse_arquivos')) {
    function cronograma_parse_arquivos($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $result = [];
            foreach ($decoded as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $file = trim((string) ($item['file'] ?? ''));
                if ($file === '') {
                    continue;
                }
                $result[] = [
                    'name' => trim((string) ($item['name'] ?? basename($file))),
                    'file' => $file,
                    'size' => (int) ($item['size'] ?? 0),
                    'type' => trim((string) ($item['type'] ?? ''))
                ];
            }
            return $result;
        }

        // Se falhar o json_decode mas começar com '[', é um JSON malformado/truncado
        if (strpos($raw, '[') === 0 || strpos($raw, '{') === 0) {
            $result = [];
            // Tentar capturar objetos JSON válidos individuais
            preg_match_all('/\{"name"\s*:\s*"(.*?)"\s*,\s*"file"\s*:\s*"(.*?)"(.*?)\}/i', $raw, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $name = $match[1];
                $file = $match[2];
                $extra = $match[3];
                
                $size = 0;
                if (preg_match('/"size"\s*:\s*(\d+)/i', $extra, $sz)) {
                    $size = (int) $sz[1];
                }
                $type = '';
                if (preg_match('/"type"\s*:\s*"(.*?)"/i', $extra, $tp)) {
                    $type = $tp[1];
                }
                
                $result[] = [
                    'name' => stripslashes($name),
                    'file' => stripslashes($file),
                    'size' => $size,
                    'type' => $type
                ];
            }
            
            // Caso nenhum objeto completo tenha sido casado, mas temos um parcial:
            if (empty($result)) {
                if (preg_match('/"name"\s*:\s*"(.*?)"/i', $raw, $nm)) {
                    $name = $nm[1];
                    $file = '';
                    if (preg_match('/"file"\s*:\s*"(.*?)"/i', $raw, $fl)) {
                        $file = $fl[1];
                    }
                    $result[] = [
                        'name' => stripslashes($name),
                        'file' => stripslashes($file),
                        'size' => 0,
                        'type' => ''
                    ];
                }
            }
            
            if (!empty($result)) {
                return $result;
            }
            return [];
        }
        
        return [['name' => basename($raw), 'file' => $raw, 'size' => 0, 'type' => '']];
    }
    function cronograma_file_url($filePath)
    {
        $filePath = trim((string) $filePath);
        if ($filePath === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $filePath)) {
            return $filePath;
        }
        $relative = ltrim(str_replace('\\', '/', $filePath), '/');
        $base = rtrim(BASE_URL, '/');
        return ($base === '' ? '' : $base) . '/uploads/' . $relative;
    }
    function cronograma_render_arquivos_html(array $items)
    {
        if (empty($items)) {
            return '<span class="text-muted">—</span>';
        }
        $html = '<ul class="list-unstyled mb-0">';
        foreach ($items as $item) {
            $name = htmlspecialchars((string) ($item['name'] ?? 'Arquivo'), ENT_QUOTES, 'UTF-8');
            $url = htmlspecialchars(cronograma_file_url($item['file'] ?? ''), ENT_QUOTES, 'UTF-8');
            $html .= '<li><a href="' . $url . '" target="_blank" rel="noopener" download>' . $name . '</a></li>';
        }
        $html .= '</ul>';
        return $html;
    }
}

$tableName = '104_cronograma';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$returnUrl = isset($_GET['return']) ? $_GET['return'] : '';
$engine = new CrudEngine($pdo, $tableName);

$engine->setLookups([
    'etapa' => ['table' => '104_etapas', 'label' => 'nome'],
    'turma' => ['table' => '104_turmas', 'label' => 'nome'],
    'professor_1' => ['table' => '104_professores', 'label' => 'apelido'],
    'professor_2' => ['table' => '104_professores', 'label' => 'apelido'],
    'professor_3' => ['table' => '104_professores', 'label' => 'apelido'],
    'professor_4' => ['table' => '104_professores', 'label' => 'apelido']
]);

$record = $engine->getRecord($id);
if (!$record) {
    die("Registro não encontrado.");
}

$canEdit = current_user_is_at_least_admin() || current_user_is_professor();

$baseHref = BASE_URL . 'index.php';
$arquivos = cronograma_parse_arquivos($record['arquivos'] ?? '');
$backUrl = $returnUrl ? $baseHref . '?' . htmlspecialchars($returnUrl) : $baseHref . '?m=etapas_cronogramas&a=list';
$formatPtBrDate = function ($date) {
    if (!$date || $date === '0000-00-00')
        return '—';
    return date('d/m/Y', strtotime($date));
};
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Visualizar <?php echo get_table_label($tableName); ?></h4>
    <div class="d-flex gap-2">
        <?php if ($canEdit): ?>
            <a href="<?php echo $baseHref; ?>?m=etapas_cronogramas&a=form&id=<?php echo $id; ?><?php echo $returnUrl ? '&return=' . urlencode($returnUrl) : ''; ?>"
                class="btn btn-primary btn-sm">
                <i class="bi bi-pencil"></i> Editar
            </a>
        <?php endif; ?>
        <a href="<?php echo $backUrl; ?>" class="btn btn-outline-secondary btn-sm">Voltar</a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="row g-4">
            <div class="col-md-6">
                <label class="text-muted small d-block mb-1">Etapa</label>
                <div class="fw-bold fs-5"><?php echo htmlspecialchars($record['etapa_label'] ?? 'N/A'); ?></div>
            </div>

            <div class="col-md-6">
                <label class="text-muted small d-block mb-1">Turma</label>
                <div class="fw-bold fs-5"><?php echo htmlspecialchars($record['turma_label'] ?? 'N/A'); ?></div>
            </div>

            <div class="col-md-4">
                <label class="text-muted small d-block mb-1">Data</label>
                <div class="fw-bold fs-5"><?php echo $formatPtBrDate($record['data']); ?></div>
            </div>

            <div class="col-md-4">
                <label class="text-muted small d-block mb-1">Horário</label>
                <div class="fw-bold fs-5">
                    <?php echo substr((string) $record['inicio'], 0, 5); ?> às
                    <?php echo substr((string) $record['termino'], 0, 5); ?>
                </div>
            </div>

            <div class="col-md-12">
                <label class="text-muted small d-block mb-1">Aula / Tema</label>
                <div class="fw-bold"><?php echo htmlspecialchars($record['aula']); ?></div>
            </div>

            <div class="col-md-12">
                <label class="text-muted small d-block mb-1">Professores</label>
                <div class="fw-bold">
                    <?php
                    $profs = [];
                    if (!empty($record['professor_1_label']))
                        $profs[] = $record['professor_1_label'];
                    if (!empty($record['professor_2_label']))
                        $profs[] = $record['professor_2_label'];
                    if (!empty($record['professor_3_label']))
                        $profs[] = $record['professor_3_label'];
                    if (!empty($record['professor_4_label']))
                        $profs[] = $record['professor_4_label'];
                    echo htmlspecialchars(implode(', ', $profs)) ?: '—';
                    ?>
                </div>
            </div>


            <div class="col-md-12">
                <label class="text-muted small d-block mb-1">Vídeo aula</label>
                <div class="fw-bold">
                    <?php if ($record['link_para_aula']): ?>
                        <a href="<?php echo htmlspecialchars($record['link_para_aula']); ?>" target="_blank">
                            <?php echo htmlspecialchars($record['link_para_aula']); ?>
                        </a>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-md-12">
                <label class="text-muted small d-block mb-1">Arquivos</label>
                <div class="fw-bold"><?php echo cronograma_render_arquivos_html($arquivos); ?></div>
            </div>
        </div>
    </div>
</div>