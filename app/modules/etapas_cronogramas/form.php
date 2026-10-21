<?php
// app/modules/etapas_cronogramas/form.php

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
$dupFrom = isset($_GET['dup_from']) ? (int) $_GET['dup_from'] : 0;
$preEtapa = isset($_GET['etapa']) ? (int) $_GET['etapa'] : 0;
$preTurma = isset($_GET['turma']) ? (int) $_GET['turma'] : 0;
$returnUrl = isset($_GET['return']) ? $_GET['return'] : '';
$engine = new CrudEngine($pdo, $tableName);

// Lookups
$etapasOptions = $pdo->query("SELECT id, nome FROM 104_etapas ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$turmasOptions = $pdo->query("SELECT id, nome FROM 104_turmas ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$professoresOptions = $pdo->query("SELECT id, apelido FROM 104_professores ORDER BY apelido ASC")->fetchAll(PDO::FETCH_ASSOC);

$schema = $engine->getSchema();
$primaryKey = $engine->getPrimaryKey();

$record = null;
$isCopy = false;
if ($id) {
    $record = $engine->getRecord($id);
} elseif ($dupFrom) {
    $record = $engine->getRecord($dupFrom);
    if ($record) {
        unset($record[$primaryKey]);
        $isCopy = true;
    }
}
// Pre-fill etapa from GET if creating new
if (!$record && $preEtapa) {
    $record = ['etapa' => $preEtapa];
}
if (!$record && $preTurma) {
    $record = ['turma' => $preTurma];
}
if ($record && !$id && !$dupFrom) {
    if ($preEtapa && empty($record['etapa'])) {
        $record['etapa'] = $preEtapa;
    }
    if ($preTurma && empty($record['turma'])) {
        $record['turma'] = $preTurma;
    }
}
$baseHref = BASE_URL . 'index.php';

// Build back URL
$backUrl = $returnUrl ? $baseHref . '?' . htmlspecialchars($returnUrl) : $baseHref . '?m=etapas_cronogramas&a=list';
$arquivosAtual = cronograma_parse_arquivos($record['arquivos'] ?? '');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <?php echo ($id ? 'Editar' : ($isCopy ? 'Duplicar' : 'Novo')) . ' ' . get_table_label($tableName); ?>
    </h4>
    <a href="<?php echo $backUrl; ?>" class="btn btn-outline-secondary btn-sm">Voltar</a>
</div>

<?php if ($isCopy): ?>
<div class="alert alert-info d-flex align-items-center gap-2 mb-3 py-2">
    <i class="bi bi-copy"></i>
    <span>Você está criando uma <strong>cópia</strong> do registro original. Ajuste os dados e salve para criar o novo registro.</span>
</div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="<?php echo $baseHref; ?>?m=etapas_cronogramas&a=save" method="post" enctype="multipart/form-data">
            <?php if ($id): ?>
                <input type="hidden" name="<?php echo $primaryKey; ?>" value="<?php echo htmlspecialchars($id); ?>">
            <?php endif; ?>
            <?php if ($returnUrl): ?>
                <input type="hidden" name="return" value="<?php echo htmlspecialchars($returnUrl); ?>">
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Etapa <span class="text-danger">*</span></label>
                    <select name="etapa" class="form-select" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($etapasOptions as $opt): ?>
                            <option value="<?php echo $opt['id']; ?>" <?php echo ((string) ($record['etapa'] ?? '') === (string) $opt['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($opt['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Turma <span class="text-danger">*</span></label>
                    <select name="turma" class="form-select" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($turmasOptions as $opt): ?>
                            <option value="<?php echo $opt['id']; ?>" <?php echo ((string) ($record['turma'] ?? '') === (string) $opt['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($opt['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Data <span class="text-danger">*</span></label>
                    <input type="date" name="data" class="form-control"
                        value="<?php echo htmlspecialchars($record['data'] ?? ''); ?>" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Início <span class="text-danger">*</span></label>
                    <input type="time" name="inicio" class="form-control"
                        value="<?php echo htmlspecialchars($record['inicio'] ?? ''); ?>" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Término <span class="text-danger">*</span></label>
                    <input type="time" name="termino" class="form-control"
                        value="<?php echo htmlspecialchars($record['termino'] ?? ''); ?>" required>
                </div>

                <div class="col-md-12">
                    <label class="form-label">Aula / Tema <span class="text-danger">*</span></label>
                    <input type="text" name="aula" class="form-control"
                        value="<?php echo htmlspecialchars($record['aula'] ?? ''); ?>" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Professor 1 <span class="text-danger">*</span></label>
                    <select name="professor_1" class="form-select" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($professoresOptions as $opt): ?>
                            <option value="<?php echo $opt['id']; ?>" <?php echo ((string) ($record['professor_1'] ?? '') === (string) $opt['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($opt['apelido']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Professor 2</label>
                    <select name="professor_2" class="form-select">
                        <option value="">Selecione...</option>
                        <?php foreach ($professoresOptions as $opt): ?>
                            <option value="<?php echo $opt['id']; ?>" <?php echo ((string) ($record['professor_2'] ?? '') === (string) $opt['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($opt['apelido']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Professor 3</label>
                    <select name="professor_3" class="form-select">
                        <option value="">Selecione...</option>
                        <?php foreach ($professoresOptions as $opt): ?>
                            <option value="<?php echo $opt['id']; ?>" <?php echo ((string) ($record['professor_3'] ?? '') === (string) $opt['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($opt['apelido']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Professor 4</label>
                    <select name="professor_4" class="form-select">
                        <option value="">Selecione...</option>
                        <?php foreach ($professoresOptions as $opt): ?>
                            <option value="<?php echo $opt['id']; ?>" <?php echo ((string) ($record['professor_4'] ?? '') === (string) $opt['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($opt['apelido']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>


                <div class="col-md-6">
                    <label class="form-label">Arquivos da Aula</label>
                    <input type="file" name="novos_arquivos[]" class="form-control" multiple
                        accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar,.jpg,.jpeg,.png,.gif,.webp">
                    <div class="form-text text-muted small">Você pode enviar vários arquivos. Eles serão salvos em
                        <code>uploads</code>.</div>
                    <?php if (!empty($arquivosAtual)): ?>
                        <div class="mt-2">
                            <small class="text-muted d-block mb-1">Arquivos atuais:</small>
                            <?php echo cronograma_render_arquivos_html($arquivosAtual); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Vídeo Aula (Somente YouTube)</label>
                    <input type="url" name="link_para_aula" class="form-control"
                        placeholder="https://www.youtube.com/watch?v=..."
                        pattern="^(https?:\/\/)?(www\.|m\.)?(youtube\.com|youtu\.be)\/.*$"
                        title="Por favor, insira um link válido do YouTube."
                        value="<?php echo htmlspecialchars($record['link_para_aula'] ?? ''); ?>">
                    <div class="form-text text-muted small">Aceita apenas links do tipo youtube.com ou youtu.be</div>
                </div>

                <div class="col-md-6 d-flex align-items-end pb-1">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="sem_video" id="sem_video" value="1"
                            <?php echo ($record['sem_video'] ?? '0') == '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="sem_video">
                            <i class="bi bi-shield-lock me-1 text-warning"></i>
                            Sem vídeo <small class="text-muted">(privacidade do aluno)</small>
                        </label>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-top">
                <button type="submit" class="btn btn-primary">Salvar
                    <?php echo get_table_label($tableName); ?>
                </button>
                <a href="<?php echo $baseHref; ?>?m=etapas_cronogramas&a=list"
                    class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
