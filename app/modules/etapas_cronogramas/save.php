<?php
// app/modules/etapas_cronogramas/save.php

require_once __DIR__ . '/../../config/config.php';
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
    function cronograma_encode_arquivos(array $items)
    {
        if (empty($items)) {
            return '';
        }
        return json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    function cronograma_process_uploaded_files($fieldName = 'novos_arquivos')
    {
        if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName]['name'])) {
            return [];
        }

        $allowedExt = [
            'pdf',
            'doc',
            'docx',
            'xls',
            'xlsx',
            'ppt',
            'pptx',
            'txt',
            'zip',
            'rar',
            'jpg',
            'jpeg',
            'png',
            'gif',
            'webp'
        ];

        $targetDir = rtrim(UPLOADS_DIR, '/');
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new Exception('Não foi possível criar o diretório de uploads.');
        }

        $result = [];
        $count = count($_FILES[$fieldName]['name']);
        for ($i = 0; $i < $count; $i++) {
            $err = (int) ($_FILES[$fieldName]['error'][$i] ?? UPLOAD_ERR_NO_FILE);
            if ($err === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($err !== UPLOAD_ERR_OK) {
                throw new Exception('Erro no upload do arquivo #' . ($i + 1) . '.');
            }

            $tmp = (string) $_FILES[$fieldName]['tmp_name'][$i];
            $originalName = trim((string) $_FILES[$fieldName]['name'][$i]);
            if ($originalName === '') {
                continue;
            }

            $ext = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
            if ($ext === '' || !in_array($ext, $allowedExt, true)) {
                throw new Exception('Tipo de arquivo não permitido: ' . $originalName);
            }

            $safeBase = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
            $safeBase = trim((string) $safeBase, '._-');
            if ($safeBase === '') {
                $safeBase = 'arquivo';
            }

            $uniqueName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . $safeBase . '.' . $ext;
            $targetFile = $targetDir . '/' . $uniqueName;

            if (!move_uploaded_file($tmp, $targetFile)) {
                throw new Exception('Falha ao mover arquivo enviado: ' . $originalName);
            }

            $result[] = [
                'name' => $originalName,
                'file' => $uniqueName,
                'size' => (int) filesize($targetFile),
                'type' => ''
            ];
        }

        return $result;
    }
}

$tableName = '104_cronograma';
$engine = new CrudEngine($pdo, $tableName);
$primaryKey = $engine->getPrimaryKey();
$id = isset($_POST[$primaryKey]) ? (int) $_POST[$primaryKey] : null;

$data = $_POST;
unset($data[$primaryKey]);
// Ensure checkbox value is always set (unchecked = not sent in POST)
$data['sem_video'] = isset($_POST['sem_video']) && $_POST['sem_video'] == '1' ? '1' : '0';

// Return URL after save (e.g. back to etapas aulas tab)
$returnUrl = !empty($_POST['return']) ? $_POST['return'] : '';
unset($data['return']);

try {
    if (isset($_POST['arquivos_json'])) {
        // Enviado pelo uploader com arrastar/soltar: já reflete remoções/reordenação.
        $arquivosExistentes = cronograma_parse_arquivos($_POST['arquivos_json']);
    } else {
        $arquivosExistentes = [];
        if ($id) {
            $stmtAtual = $pdo->prepare("SELECT arquivos FROM 104_cronograma WHERE id = :id");
            $stmtAtual->execute([':id' => $id]);
            $rawAtual = $stmtAtual->fetchColumn();
            $arquivosExistentes = cronograma_parse_arquivos($rawAtual !== false ? $rawAtual : '');
        }
    }
    unset($data['arquivos_json']);

    $novosArquivos = cronograma_process_uploaded_files('novos_arquivos');
    $arquivosFinal = array_merge($arquivosExistentes, $novosArquivos);
    $data['arquivos'] = cronograma_encode_arquivos($arquivosFinal);

    $success = $engine->save($data, $id);
    if ($success) {
        $msgLog = ($id ? "Editado" : "Criado") . " registro de Cronograma";
        if (!empty($novosArquivos)) {
            $msgLog .= " com " . count($novosArquivos) . " arquivo(s) enviado(s).";
        }
        log_event($pdo, $tableName, $id ? 'UPDATE' : 'INSERT', $msgLog);
        $redirect = $returnUrl ? "index.php?" . $returnUrl . "&msg=success" : "index.php?m=etapas_cronogramas&a=list&msg=success";
        redirect($redirect);
    } else {
        echo "Erro ao salvar o registro de Cronograma.";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
