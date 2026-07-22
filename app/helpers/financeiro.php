<?php
// app/helpers/financeiro.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Transacao segura com rollback automatico em caso de erro.
 */
function financeiro_transaction(callable $callback)
{
    global $pdo;
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $ownTransaction = true;
    } else {
        $ownTransaction = false;
    }

    try {
        $result = $callback();
        if ($ownTransaction) {
            $pdo->commit();
        }
        return $result;
    } catch (Throwable $e) {
        if ($ownTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Exibe uma mensagem flash armazenada na sessao.
 */
function financeiro_render_flash(string $key, string $type = 'success'): void
{
    $message = financeiro_get_flash($key);
    if (!$message) {
        return;
    }
    $type = in_array($type, ['success', 'info', 'warning', 'danger'], true) ? $type : 'info';
    echo '<div class="alert alert-' . $type . ' mb-3" role="alert">' . htmlspecialchars($message) . '</div>';
}

/**
 * Armazena mensagem temporaria na sessao.
 */
function financeiro_set_flash(string $key, string $message): void
{
    $_SESSION['financeiro_flash'][$key] = $message;
}

/**
 * Recupera e remove uma mensagem flash.
 */
function financeiro_get_flash(string $key): ?string
{
    if (!isset($_SESSION['financeiro_flash'][$key])) {
        return null;
    }
    $message = $_SESSION['financeiro_flash'][$key];
    unset($_SESSION['financeiro_flash'][$key]);
    return $message;
}

/**
 * Parametros padrao de paginacao.
 */
function financeiro_pagination_params(int $perPage = 30): array
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $offset = ($page - 1) * $perPage;
    return [
        'page' => $page,
        'perPage' => $perPage,
        'offset' => $offset,
    ];
}

/**
 * Monta HTML de paginacao padrao.
 */
function financeiro_render_pagination(int $total, int $perPage, int $currentPage, string $baseUrl, array $extraParams = []): string
{
    $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
    if ($totalPages <= 1) {
        return '';
    }

    $buildUrl = static function (int $page) use ($baseUrl, $extraParams) {
        $separator = strpos($baseUrl, '?') === false ? '?' : '&';
        $params = array_merge($extraParams, ['page' => $page]);
        return $baseUrl . $separator . http_build_query($params);
    };

    $html = '<nav aria-label="Paginacao"><ul class="pagination pagination-sm justify-content-center mb-0">';
    $html .= '<li class="page-item' . ($currentPage <= 1 ? ' disabled' : '') . '">';
    $html .= '<a class="page-link" href="' . htmlspecialchars($buildUrl(1)) . '">&laquo;</a></li>';
    $html .= '<li class="page-item' . ($currentPage <= 1 ? ' disabled' : '') . '">';
    $prevPage = max(1, $currentPage - 1);
    $html .= '<a class="page-link" href="' . htmlspecialchars($buildUrl($prevPage)) . '">&lsaquo;</a></li>';

    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);
    for ($page = $start; $page <= $end; $page++) {
        $active = $page === $currentPage ? ' active' : '';
        $html .= '<li class="page-item' . $active . '">';
        $html .= '<a class="page-link" href="' . htmlspecialchars($buildUrl($page)) . '">' . $page . '</a></li>';
    }

    $nextPage = min($totalPages, $currentPage + 1);
    $html .= '<li class="page-item' . ($currentPage >= $totalPages ? ' disabled' : '') . '">';
    $html .= '<a class="page-link" href="' . htmlspecialchars($buildUrl($nextPage)) . '">&rsaquo;</a></li>';
    $html .= '<li class="page-item' . ($currentPage >= $totalPages ? ' disabled' : '') . '">';
    $html .= '<a class="page-link" href="' . htmlspecialchars($buildUrl($totalPages)) . '">&raquo;</a></li>';
    $html .= '</ul></nav>';

    return $html;
}

/**
 * Valida campos numericos positivos.
 */
function financeiro_validate_positive($value, string $label, array &$errors): ?float
{
    if ($value === '' || $value === null) {
        $errors[] = "{$label} deve ser informado.";
        return null;
    }
    $parsed = financeiro_parse_decimal($value);
    if ($parsed === null || (float) $parsed <= 0) {
        $errors[] = "{$label} deve ser maior que zero.";
        return null;
    }
    return (float) $parsed;
}

/**
 * Valida datas no formato YYYY-MM-DD.
 */
function financeiro_validate_date($value, string $label, array &$errors, bool $allowEmpty = false): ?string
{
    if ($value === '' || $value === null) {
        return $allowEmpty ? null : finan_401_invalid_date($label, $errors);
    }
    $date = DateTime::createFromFormat('Y-m-d', $value);
    if (!$date) {
        $errors[] = "{$label} invalida.";
        return null;
    }
    return $date->format('Y-m-d');
}

/**
 * Retorna uma query string preservando os filtros atuais, removendo chaves sensiveis.
 *
 * @param array $overrides Parametros a serem substituidos ou adicionados ao final.
 * @param array $excludeKeys Chaves adicionais a serem removidas de $_GET.
 */
function financeiro_ensure_account_option(PDO $pdo, array &$accounts, int $id): void
{
    if ($id <= 0) {
        return;
    }
    foreach ($accounts as $account) {
        if ((int) $account['id'] === $id) {
            return;
        }
    }
    $stmt = $pdo->prepare('SELECT id, codigo, nome FROM contas_contabeis WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $accounts[] = $row;
        return;
    }
    $stmt2 = $pdo->prepare('SELECT id, nome FROM contas_financeiras WHERE id = :id');
    $stmt2->execute([':id' => $id]);
    $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
    if ($row2) {
        $accounts[] = ['id' => $row2['id'], 'codigo' => '', 'nome' => $row2['nome'] . ' (financeira)'];
    }
}

/**
 * Helper interno que registra erro de data obrigatoria.
 */
function finan_401_invalid_date(string $label, array &$errors): ?string
{
    $errors[] = "{$label} e obrigatoria.";
    return null;
}

function financeiro_parse_decimal($value): ?float
{
    $clean = trim((string) $value);
    if ($clean === '') {
        return null;
    }
    $clean = str_replace(' ', '', $clean);
    $hasComma = strpos($clean, ',') !== false;
    $hasDot = strpos($clean, '.') !== false;
    if ($hasComma && $hasDot) {
        $clean = str_replace('.', '', $clean);
        $clean = str_replace(',', '.', $clean);
    } elseif ($hasComma) {
        $clean = str_replace(',', '.', $clean);
    }
    if ($clean === '') {
        return null;
    }
    if (!is_numeric($clean)) {
        return null;
    }
    return (float) $clean;
}

function financeiro_get_categoria_financeira(PDO $pdo, ?int $categoriaId): ?array
{
    if (!$categoriaId) {
        return null;
    }
    $stmt = $pdo->prepare('
        SELECT cf.*, cd.nome AS conta_debito_nome, cc.nome AS conta_credito_nome
        FROM categorias_financeiras cf
        LEFT JOIN contas_contabeis cd ON cd.id = cf.conta_debito_id
        LEFT JOIN contas_contabeis cc ON cc.id = cf.conta_credito_id
        WHERE cf.id = :id
        LIMIT 1
    ');
    $stmt->execute([':id' => $categoriaId]);
    $categoria = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$categoria) {
        return null;
    }
    return $categoria;
}

function financeiro_map_conta_financeira_para_conta_contabil(PDO $pdo, ?int $contaFinanceiraId): ?int
{
    if (!$contaFinanceiraId) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT nome, tipo FROM contas_financeiras WHERE id = :id');
    $stmt->execute([':id' => $contaFinanceiraId]);
    $conta = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$conta) {
        return null;
    }

    $normalize = static function (string $value): string {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower($value));
    };

    $normalized = $normalize($conta['nome']);

    if ($normalized !== '') {
        $stmt = $pdo->prepare('
            SELECT id
            FROM contas_contabeis
            WHERE REPLACE(REPLACE(REPLACE(LOWER(nome), " ", ""), ".", ""), "-", "") = :normalized
            LIMIT 1
        ');
        $stmt->execute([':normalized' => $normalized]);
        $match = $stmt->fetchColumn();
        if ($match) {
            return (int) $match;
        }
    }

    $like = '%' . mb_strtolower($conta['nome']) . '%';
    $stmt = $pdo->prepare('
        SELECT id
        FROM contas_contabeis
        WHERE LOWER(nome) LIKE :like
        ORDER BY id ASC
        LIMIT 1
    ');
    $stmt->execute([':like' => $like]);
    $match = $stmt->fetchColumn();
    if ($match) {
        return (int) $match;
    }

    $tipo = $conta['tipo'] === 'BANCO' ? 'ATIVO' : 'ATIVO';
    $stmt = $pdo->prepare('
        SELECT id
        FROM contas_contabeis
        WHERE tipo = :tipo
        ORDER BY id ASC
        LIMIT 1
    ');
    $stmt->execute([':tipo' => $tipo]);
    $match = $stmt->fetchColumn();
    return $match ? (int) $match : null;
}

function financeiro_create_lancamento(PDO $pdo, string $historico, string $dataCompetencia, array $items, ?string $origemTipo = null, ?int $origemId = null, string $status = 'POSTADO'): int
{
    if (empty($items)) {
        throw new RuntimeException('Nenhum item contabil informado para o lancamento.');
    }
    $totalDebito = 0;
    $totalCredito = 0;
    foreach ($items as $item) {
        if (empty($item['valor']) || !isset($item['conta_contabil_id'], $item['natureza'])) {
            throw new RuntimeException('Item contabil invalido.');
        }
        $valor = (float) $item['valor'];
        if ($valor <= 0) {
            throw new RuntimeException('Valor do item contabil deve ser maior que zero.');
        }
        $natureza = strtoupper($item['natureza']);
        if (!in_array($natureza, ['DEBITO', 'CREDITO'], true)) {
            throw new RuntimeException('Natureza do item contabil invalida.');
        }
        if ($natureza === 'DEBITO') {
            $totalDebito += $valor;
        } else {
            $totalCredito += $valor;
        }
    }

    if (abs($totalDebito - $totalCredito) > 0.01) {
        throw new RuntimeException('Partidas dobradas nao batem (Debito x Credito).');
    }

    $validateErrors = [];
    $competencia = financeiro_validate_date($dataCompetencia, 'Competencia', $validateErrors, true);
    if ($competencia === null) {
        $competencia = date('Y-m-d');
    }

    $stmt = $pdo->prepare('
        INSERT INTO lancamentos (data_lanc, data_competencia, historico, origem_tipo, origem_id, status, inclusao)
        VALUES (NOW(), :competencia, :historico, :origem_tipo, :origem_id, :status, NOW())
    ');
    $stmt->execute([
        ':competencia' => $competencia,
        ':historico' => $historico,
        ':origem_tipo' => $origemTipo,
        ':origem_id' => $origemId,
        ':status' => $status,
    ]);
    $lancamentoId = (int) $pdo->lastInsertId();

    $stmtItem = $pdo->prepare('
        INSERT INTO lancamentos_itens (lancamento_id, conta_contabil_id, pessoa_id, natureza, valor)
        VALUES (:lancamento_id, :conta_contabil_id, :pessoa_id, :natureza, :valor)
    ');

    foreach ($items as $item) {
        $stmtItem->execute([
            ':lancamento_id' => $lancamentoId,
            ':conta_contabil_id' => $item['conta_contabil_id'],
            ':pessoa_id' => $item['pessoa_id'] ?? null,
            ':natureza' => strtoupper($item['natureza']),
            ':valor' => (float) $item['valor'],
        ]);
    }

    log_event($pdo, 'lancamentos', 'INCLUSAO', 'ID ' . $lancamentoId . ' ' . $historico);
    return $lancamentoId;
}

/**
 * Calcula o valor efetivamente liquidado em uma parcela.
 */
function financeiro_calc_liquidado(float $valorPago, float $juros, float $multa, float $desconto): float
{
    $liquidado = $valorPago + $desconto - ($juros + $multa);
    return round($liquidado, 2);
}

/**
 * Determina o status mais adequado de um titulo com base nas parcelas.
 */
function financeiro_determina_status_titulo(PDO $pdo, int $tituloId): string
{
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(tp.status = 'BAIXADO') AS baixado,
            SUM(tp.status = 'CANCELADO') AS cancelado
        FROM titulos_parcelas tp
        WHERE tp.titulo_id = :titulo_id
    ");
    $stmt->execute([':titulo_id' => $tituloId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'baixado' => 0, 'cancelado' => 0];

    $total = (int) $row['total'];
    $baixado = (int) $row['baixado'];
    $cancelado = (int) $row['cancelado'];

    if ($total === 0) {
        return 'ABERTO';
    }
    if ($total === $cancelado && $cancelado > 0) {
        return 'CANCELADO';
    }
    if ($total === $baixado && $baixado > 0) {
        return 'BAIXADO';
    }
    if ($baixado > 0) {
        return 'PARCIAL';
    }
    return 'ABERTO';
}

/**
 * Atualiza o status do titulo apos alteracoes nas parcelas.
 */
function financeiro_update_status_titulo(PDO $pdo, int $tituloId): void
{
    $status = financeiro_determina_status_titulo($pdo, $tituloId);
    $stmt = $pdo->prepare("
        UPDATE titulos
        SET status = :status,
            alteracao = NOW()
        WHERE id = :titulo_id
    ");
    $stmt->execute([
        ':status' => $status,
        ':titulo_id' => $tituloId,
    ]);
}

/**
 * Verifica e processa lancamentos recorrentes pendentes.
 */
function financeiro_verificar_recorrentes(PDO $pdo): void
{
    // Verifica se a tabela de parametros existe (evita erro em setup inicial)
    if (!function_exists('tableExists') || !tableExists('parametros')) {
        return;
    }

    // Determina a coluna de identificacao (chave, nome ou parametro)
    $colKey = 'nome';
    if (function_exists('columnExists')) {
        if (columnExists('parametros', 'chave')) {
            $colKey = 'chave';
        } elseif (columnExists('parametros', 'parametro')) {
            $colKey = 'parametro';
        }
    }

    // 1. Busque em parametros pelo parametro = lancamentos_recorrentes
    $stmt = $pdo->prepare("SELECT valor FROM parametros WHERE $colKey = 'lancamentos_recorrentes'");
    $stmt->execute();
    $lastRun = $stmt->fetchColumn();

    // Se nao existir, inicializa com ontem
    if ($lastRun === false) {
        $lastRun = date('Y-m-d', strtotime('-1 day'));
        $stmt = $pdo->prepare("INSERT INTO parametros ($colKey, valor) VALUES ('lancamentos_recorrentes', :valor)");
        $stmt->execute([':valor' => $lastRun]);
    }

    $today = date('Y-m-d');

    // 2. Compare com a data atual do sistema
    if ($lastRun < $today) {
        // 3. Procure em parametros o parametro = lancamentos_recorrentes_meses
        $stmt = $pdo->prepare("SELECT valor FROM parametros WHERE $colKey = 'lancamentos_recorrentes_meses'");
        $stmt->execute();
        $meses = (int) $stmt->fetchColumn();

        // 4. Abra a tabela financeiro_recorrente
        if (tableExists('financeiro_recorrente')) {
            $stmt = $pdo->query("SELECT * FROM financeiro_recorrente");
            $recorrentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($recorrentes as $rec) {
                if (isset($rec['ativo']) && !$rec['ativo']) {
                    continue;
                }

                $diaVencimento = (int) ($rec['dia'] ?? $rec['dia_vencimento'] ?? 1);
                $start = new DateTime($lastRun);
                $end = new DateTime($today);
                
                // Comeca a verificar a partir do dia seguinte a ultima execucao
                $curr = clone $start;
                $curr->modify('+1 day');
                
                // Itera pelos meses no intervalo
                $iterMonth = clone $curr;
                $iterMonth->modify('first day of this month');

                while ($iterMonth <= $end) {
                    $year = $iterMonth->format('Y');
                    $month = $iterMonth->format('m');
                    $daysInMonth = (int) $iterMonth->format('t');
                    
                    // Ajusta dia se o mes tiver menos dias
                    $dayToCheck = min($diaVencimento, $daysInMonth);
                    
                    $dateCandidate = sprintf('%s-%s-%02d', $year, $month, $dayToCheck);
                    
                    // Verifica se a data esta no intervalo (lastRun, today]
                    if ($dateCandidate > $lastRun && $dateCandidate <= $today) {
                        // Verifique se estao lancados
                        $stmtCheck = $pdo->prepare("
                            SELECT COUNT(*) FROM lancamentos 
                            WHERE origem_tipo = 'RECORRENTE' 
                            AND origem_id = :id 
                            AND data_competencia = :data
                        ");
                        $stmtCheck->execute([
                            ':id' => $rec['id'],
                            ':data' => $dateCandidate
                        ]);

                        if ($stmtCheck->fetchColumn() == 0) {
                            // Faca os lancamentos necessarios
                            $items = [];
                            $contaDebito = $rec['conta_debito_id'] ?? null;
                            $contaCredito = $rec['conta_credito_id'] ?? null;
                            $valor = $rec['valor'] ?? 0;
                            $historico = $rec['historico'] ?? $rec['descricao'] ?? 'Lancamento Recorrente';
                            $pessoaId = $rec['pessoa_id'] ?? null;

                            if ($valor > 0 && ($contaDebito || $contaCredito)) {
                                if ($contaDebito) {
                                    $items[] = [
                                        'conta_contabil_id' => $contaDebito,
                                        'natureza' => 'DEBITO',
                                        'valor' => $valor,
                                        'pessoa_id' => $pessoaId
                                    ];
                                }
                                if ($contaCredito) {
                                    $items[] = [
                                        'conta_contabil_id' => $contaCredito,
                                        'natureza' => 'CREDITO',
                                        'valor' => $valor,
                                        'pessoa_id' => $pessoaId
                                    ];
                                }

                                try {
                                    financeiro_create_lancamento(
                                        $pdo,
                                        $historico,
                                        $dateCandidate,
                                        $items,
                                        'RECORRENTE',
                                        $rec['id']
                                    );
                                } catch (Exception $e) {
                                    // Silencia erro para nao travar login
                                }
                            }
                        }
                    }
                    $iterMonth->modify('+1 month');
                }
            }
        }

        // 5. Atualize o parametro lancamentos_recorrentes para a data de hoje
        $stmt = $pdo->prepare("UPDATE parametros SET valor = :today WHERE $colKey = 'lancamentos_recorrentes'");
        $stmt->execute([':today' => $today]);
    }
}
