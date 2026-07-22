<?php
// scratch/test_parse.php
$raw = '[{"name":"4.1 LAIR .ALUNOS Organização dos Trabalhos acadêmicos.pptx","file":"20260308_113623_c8dafd19_4.1_LAIR_.ALUNOS_Organizac__a__o_dos_Trabalhos_acade__micos.pptx","size":9993406,"type":""},{"name":"Manual de Metodologia Científica e de Pesquisa.pdf","file":"20260308_113720_5bad3c95_Manual_';

$raw = trim((string) $raw);
echo "Raw input: " . $raw . "\n\n";

if ($raw === '') {
    echo "Empty\n";
    exit;
}

$decoded = json_decode($raw, true);
if (is_array($decoded)) {
    echo "Decoded as JSON array successfully!\n";
    print_r($decoded);
    exit;
}

echo "json_decode failed. Starting regex extraction:\n";

if (strpos($raw, '[') === 0 || strpos($raw, '{') === 0) {
    $result = [];
    preg_match_all('/\{"name"\s*:\s*"(.*?)"\s*,\s*"file"\s*:\s*"(.*?)"(.*?)\}/i', $raw, $matches, PREG_SET_ORDER);
    echo "Matches found: " . count($matches) . "\n";
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
    
    if (empty($result)) {
        echo "No full objects matched. Checking partial match...\n";
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
        echo "Result from regex:\n";
        print_r($result);
        exit;
    }
}

echo "Fallback to legacy:\n";
$legacy = [[
    'name' => basename($raw),
    'file' => $raw,
    'size' => 0,
    'type' => ''
]];
print_r($legacy);
