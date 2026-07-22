<?php
// app/config/log.php

/**
 * Registra um evento na tabela log.
 *
 * @param PDO    $pdo
 * @param string $tabelaAfetada
 * @param string $acao (INCLUSAO, ALTERACAO, EXCLUSAO, ACESSO, LOGIN, LOGOUT, RELATORIO, GRAFICOS)
 * @param string $descricao
 * @return void
 */
function log_event(PDO $pdo, string $tabelaAfetada, string $acao, string $descricao): void
{
    static $tableChecked = false;
    $tryCreate = function () use ($pdo, &$tableChecked) {
        if ($tableChecked) {
            return;
        }
        $tableChecked = true;
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `log` (
              `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
              `data_hora` datetime NOT NULL DEFAULT current_timestamp(),
              `usuario_id` int(10) UNSIGNED DEFAULT NULL,
              `ip` varchar(45) DEFAULT NULL,
              `tabela_afetada` varchar(100) NOT NULL,
              `acao` enum('INCLUSAO','ALTERACAO','EXCLUSAO','ACESSO','LOGIN','LOGOUT','RELATORIO','GRAFICOS') NOT NULL,
              `descricao` text DEFAULT NULL,
              `usuario_inclusao_id` int(10) UNSIGNED DEFAULT NULL,
              `data_inclusao` datetime NOT NULL DEFAULT current_timestamp(),
              `usuario_alteracao_id` int(10) UNSIGNED DEFAULT NULL,
              `data_alteracao` datetime DEFAULT NULL ON UPDATE current_timestamp(),
              PRIMARY KEY (`id`),
              KEY `idx_log_data` (`data_hora`),
              KEY `idx_log_usuario` (`usuario_id`),
              KEY `idx_log_tabela` (`tabela_afetada`),
              KEY `idx_log_acao` (`acao`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");
    };

    try {
        $usuario = $_SESSION['user']['id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt = $pdo->prepare("
            INSERT INTO `log` (usuario_id, ip, tabela_afetada, acao, descricao, data_hora, usuario_inclusao_id, data_inclusao)
            VALUES (:usuario_id, :ip, :tabela, :acao, :descricao, NOW(), :usuario_inc, NOW())
        ");
        $stmt->execute([
            ':usuario_id' => $usuario,
            ':ip'         => $ip,
            ':tabela'     => $tabelaAfetada,
            ':acao'       => $acao,
            ':descricao'  => $descricao,
            ':usuario_inc'=> $usuario,
        ]);
    } catch (Throwable $e) {
        // tenta criar tabela e reenfileirar uma única vez
        $tryCreate();
        try {
            $usuario = $_SESSION['user']['id'] ?? null;
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $stmt = $pdo->prepare("
                INSERT INTO `log` (usuario_id, ip, tabela_afetada, acao, descricao, data_hora, usuario_inclusao_id, data_inclusao)
                VALUES (:usuario_id, :ip, :tabela, :acao, :descricao, NOW(), :usuario_inc, NOW())
            ");
            $stmt->execute([
                ':usuario_id' => $usuario,
                ':ip'         => $ip,
                ':tabela'     => $tabelaAfetada,
                ':acao'       => $acao,
                ':descricao'  => $descricao,
                ':usuario_inc'=> $usuario,
            ]);
        } catch (Throwable $e2) {
            // fallback para arquivo local, para não perder eventos
            $dir = __DIR__ . '/../../logs';
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
            $line = date('c') . ' ' . $acao . ' ' . $tabelaAfetada . ' ' . $descricao . ' ERR: ' . $e2->getMessage() . PHP_EOL;
            @file_put_contents($dir . '/app.log', $line, FILE_APPEND);
            error_log('[log_event] Falha ao gravar log: ' . $e2->getMessage());
        }
    }
}

/**
 * Helper para montar diff de alteração.
 */
function log_diff(array $before, array $after): string
{
    $changes = [];
    foreach ($after as $k => $v) {
        if (!array_key_exists($k, $before)) {
            continue;
        }
        if ((string) $before[$k] !== (string) $v) {
            $changes[$k] = ['antigo' => $before[$k], 'novo' => $v];
        }
    }
    return json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
