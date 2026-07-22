<?php
require_once '/Applications/XAMPP/xamppfiles/htdocs/mg/app/config/config.php';
require_once '/Applications/XAMPP/xamppfiles/htdocs/mg/app/core/CrudEngine.php';
require_once '/Applications/XAMPP/xamppfiles/htdocs/mg/app/helpers/texto.php';

$tableName = '104_professores';
$engine = new CrudEngine($pdo, $tableName);

$page = 1;
$search = '';
$result = $engine->getList($page, 20, $search);
$data = $result['data'];

// Resolve names
if (!empty($data)) {
    $pessoaIds = array_unique(array_filter(array_column($data, 'pessoa')));
    if (!empty($pessoaIds)) {
        $placeholders = implode(',', array_fill(0, count($pessoaIds), '?'));
        $sqlNames = "SELECT id, nome FROM 101_pessoas WHERE id IN ($placeholders)";
        $stmtNames = $pdo->prepare($sqlNames);
        $stmtNames->execute(array_values($pessoaIds));
        $pessoaNames = $stmtNames->fetchAll(PDO::FETCH_KEY_PAIR);

        foreach ($data as &$row) {
            if (isset($pessoaNames[$row['pessoa']])) {
                $row['pessoa_label'] = $pessoaNames[$row['pessoa']];
            }
        }
        unset($row); // FIXED
    }
}

echo "Testing for the bug...\n";
$bugDetected = false;
foreach ($data as $i => $row) {
    if ($i == 19 && $row['id'] == $data[18]['id']) {
        echo "BUG DETECTED: Row 19 is a duplicate of row 18 (ID " . $row['id'] . ")\n";
        $bugDetected = true;
    }
}
if (!$bugDetected) {
    echo "NO BUG DETECTED. The list is correct.\n";
    echo "Row 18 ID: " . $data[18]['id'] . "\n";
    echo "Row 19 ID: " . $data[19]['id'] . "\n";
}
echo "Check complete.\n";
