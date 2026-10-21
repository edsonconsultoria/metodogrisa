<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../core/CrudEngine.php';

$tableName = '104_professores';
$engine = new CrudEngine($pdo, $tableName);

$result = $engine->getList(1, 100);
echo "Total: " . $result['total'] . "\n";
echo "Data count: " . count($result['data']) . "\n";

$ids = array_column($result['data'], 'id');
$counts = array_count_values($ids);
foreach ($counts as $id => $count) {
    if ($count > 1) {
        echo "Duplicate ID: $id ($count times)\n";
    }
}
