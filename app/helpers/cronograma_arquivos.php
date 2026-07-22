<?php
// app/helpers/cronograma_arquivos.php

function cronograma_human_size($bytes)
{
    $size = (int) $bytes;
    if ($size <= 0) {
        return '0 B';
    }
    $units = ['B', 'KB', 'MB', 'GB'];
    $pow = (int) floor(log($size, 1024));
    $pow = min($pow, count($units) - 1);
    $value = $size / pow(1024, $pow);
    return number_format($value, $pow === 0 ? 0 : 1, ',', '.') . ' ' . $units[$pow];
}

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

    // Compatibilidade com formato legado (texto simples)
    return [[
        'name' => basename($raw),
        'file' => $raw,
        'size' => 0,
        'type' => ''
    ]];
}

function cronograma_encode_arquivos(array $items)
{
    $clean = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $file = trim((string) ($item['file'] ?? ''));
        if ($file === '') {
            continue;
        }
        $clean[] = [
            'name' => trim((string) ($item['name'] ?? basename($file))),
            'file' => $file,
            'size' => (int) ($item['size'] ?? 0),
            'type' => trim((string) ($item['type'] ?? ''))
        ];
    }

    if (empty($clean)) {
        return '';
    }
    return json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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

        $mime = '';
        if (function_exists('mime_content_type')) {
            $mime = (string) mime_content_type($targetFile);
        }

        $result[] = [
            'name' => $originalName,
            'file' => $uniqueName,
            'size' => (int) filesize($targetFile),
            'type' => $mime
        ];
    }

    return $result;
}
