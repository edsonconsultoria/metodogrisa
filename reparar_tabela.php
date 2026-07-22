<?php
// reparar_tabela.php
// USO ÚNICO: suba para produção, acesse pelo navegador, confira o resultado e APAGUE o arquivo.
// Reparo NÃO DESTRUTIVO da tabela corrompida (erro 1034 / erro 176) e tentativa de conversão para InnoDB.

require_once __DIR__ . '/app/config/config.php';

header('Content-Type: text/plain; charset=utf-8');
@set_time_limit(0);

$tabela = '104_etapas_turmas';
$tabela = preg_replace('/[^a-z0-9_]/i', '', $tabela);

echo "Banco: " . DB_NAME . "\n";
echo "Tabela: {$tabela}\n";
echo "==================================================\n\n";

function contarLinhas(PDO $pdo, string $t) {
    try {
        return (int) $pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
    } catch (Throwable $e) {
        return 'indisponível (' . $e->getMessage() . ')';
    }
}

function repair(PDO $pdo, string $sql) {
    echo ">>> {$sql}\n";
    try {
        foreach ($pdo->query($sql) as $row) {
            echo "    {$row['Msg_type']}: {$row['Msg_text']}\n";
        }
    } catch (Throwable $e) {
        echo "    ERRO: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

// Motor atual da tabela
try {
    $eng = $pdo->prepare("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=:d AND TABLE_NAME=:t");
    $eng->execute([':d' => DB_NAME, ':t' => $tabela]);
    echo "Engine atual: " . ($eng->fetchColumn() ?: 'desconhecido') . "\n";
} catch (Throwable $e) {
    echo "Engine atual: erro ao consultar - " . $e->getMessage() . "\n";
}

echo "Linhas ANTES: " . contarLinhas($pdo, $tabela) . "\n\n";

// 1) Reparo completo linha a linha (NÃO destrutivo)
repair($pdo, "REPAIR TABLE `{$tabela}` EXTENDED");
echo "Linhas após EXTENDED: " . contarLinhas($pdo, $tabela) . "\n\n";

// 2) Verificação
repair($pdo, "CHECK TABLE `{$tabela}`");

// 3) Tenta converter para InnoDB (só se o reparo tiver dado certo)
echo ">>> ALTER TABLE `{$tabela}` ENGINE=InnoDB\n";
try {
    $pdo->exec("ALTER TABLE `{$tabela}` ENGINE=InnoDB");
    echo "    OK: convertida para InnoDB\n";
} catch (Throwable $e) {
    echo "    FALHA (tabela ainda corrompida): " . $e->getMessage() . "\n";
}
echo "\n";

echo "Linhas FINAL: " . contarLinhas($pdo, $tabela) . "\n";
echo "==================================================\n";
echo "Concluído. APAGUE este arquivo do servidor por segurança.\n";
