<?php
// app/modules/aulas/save.php

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

// Apenas ADMIN ou SUPERADMIN podem salvar
$userPerfil = $_SESSION['user']['perfil'] ?? 'OPERACIONAL';
if (!in_array(strtolower($userPerfil), ['admin', 'superadmin'])) {
    die("Acesso negado.");
}

$data = [
    'aula' => $_POST['aula'] ?? '',
    'link_para_aula' => $_POST['link_para_aula'] ?? '',
    'data' => $_POST['data'] ?? '',
    'inicio' => $_POST['inicio'] ?? '',
    'termino' => $_POST['termino'] ?? '',
    'professor_1' => $_POST['professor_1'] ?: null,
    'professor_2' => $_POST['professor_2'] ?: null,
    'professor_3' => $_POST['professor_3'] ?: null,
    'professor_4' => $_POST['professor_4'] ?: null,
    'sem_video' => ($_POST['sem_video'] ?? '0') == '1' ? '1' : '0',
];

try {
    $arquivosJSON = $_POST['arquivos_json'] ?? '';
    $arquivosBase = cronograma_parse_arquivos($arquivosJSON);
    $novosArquivos = cronograma_process_uploaded_files('novos_arquivos');
    $arquivosFinal = array_merge($arquivosBase, $novosArquivos);
    $data['arquivos'] = cronograma_encode_arquivos($arquivosFinal);

    $success = $engine->save($data, $id);
    if ($success) {
        log_event($pdo, $tableName, $id ? 'UPDATE' : 'INSERT', ($id ? "Editado" : "Criado") . " aula via módulo Aulas");

        $p = $_POST['redirect_page'] ?? '1';
        $s = $_POST['redirect_search'] ?? '';
        $fe = $_POST['redirect_filtro_etapa'] ?? '';
        $t = $_POST['redirect_filtro_turma'] ?? '';
        $fp = $_POST['redirect_filtro_professor'] ?? '';
        $fpe = $_POST['redirect_filtro_pendente'] ?? '';
        $tv = $_POST['redirect_turma_view'] ?? '0';
        $ret = $_POST['redirect_etapa_id'] ?? '';

        $url = "index.php?m=aulas&a=list&page=$p&search=$s&filtro_etapa=$fe&filtro_turma=$t&filtro_professor=$fp&filtro_pendente=$fpe";
        if ((int)$tv > 0) {
            $url .= "&turma_view=" . (int)$tv;
        }
        $url .= "&msg=success";
        if ($ret !== '') {
            $url .= "#etapa-block-" . (int)$ret;
        }
        redirect($url);
    } else {
        echo "Erro ao salvar a aula.";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
