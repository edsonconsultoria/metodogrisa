<?php
// prevenção a erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// public/index.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/app/config/config.php';
require __DIR__ . '/app/config/auth.php';



$module = strtolower(trim($_GET['m'] ?? 'dashboard'));
$action = strtolower(trim($_GET['a'] ?? 'index'));
$publicAccess = $module === 'financeiro' && $action === 'pix';
if (!$publicAccess) {
    require_login();
}

// Rotas permitidas
$routes = [
    'dashboard' => ['index'],
    'pessoas' => ['list', 'form', 'form_copy', 'save', 'delete', 'copy', 'view', 'relatorio', 'relatorio_pdf', 'relatorio_aniversariantes_pdf', 'relatorio_completo', 'relatorio_completo_pdf', 'relatorio_aniversariantes', 'ajax_search', 'ajax_save', 'whatsapp_login', 'send_password'],
    'anotacoes' => ['list', 'form', 'save', 'delete', 'copy', 'view'],
    'mensalistas' => ['list'],
    'servicos' => ['list', 'form', 'form_copy', 'save', 'delete', 'view', 'copy', 'inicio_termino', 'inicio_termino_action', 'grafico_semana', 'grafico_clientes', 'relatorio_agenda', 'servicos_calendario'],
    'tarefas' => ['list', 'form', 'form_copy', 'save', 'delete', 'view', 'complete', 'reschedule'],
    'pessoas_backup' => ['list', 'form', 'save', 'delete', 'view', 'check'],
    'balanco' => ['list'],
    'financeiro' => [
        'list',
        'list_pix',
        'calendario',
        'relatorio_receber',
        'relatorio_receber_pdf',
        'relatorio_receber_pagar',
        'relatorio_receber_pagar_pdf',
        'relatorio_pagar',
        'relatorio_pagar_pdf',
        'relatorio_caixa',
        'relatorio_caixa_pdf',
        'relatorio_caixa_conta',
        'relatorio_caixa_conta_pdf',
        'relatorio_fluxo_caixa',
        'relatorio_fluxo_caixa_pdf',
        'relatorio_categoria',
        'relatorio_categoria_pdf',
        'relatorio_pessoa',
        'relatorio_pessoa_pdf',
        'relatorio_pessoa_prevista',
        'relatorio_descricao',
        'relatorio_descricao_pdf',
        'relatorio_descricao_prevista',
        'relatorio_categoria_prevista',
        'grafico_mes',
        'grafico_categoria_mes',
        'grafico_categoria_conta',
        'receber_proximos',
        'recibo_pdf',
        'pix',
        'grafico_categoria_mes',
        'form',
        'form_copy',
        'save',
        'delete',
        'view',
        'import', // Rota de importacao
    ],
    'financeiro_recorrente' => [
        'list',
        'form',
        'form_copy',
        'save',
        'delete',
        'view',
    ],
    'financeiro_transfencias' => ['list', 'form', 'save', 'delete'],
    'contas' => ['list', 'form', 'form_copy', 'save', 'delete'],
    'categoria_financeira' => ['list', 'form', 'save', 'delete'],
    'caixa' => ['list'],
    'abastecimentos' => ['list', 'form', 'save', 'delete'],
    'parametros' => ['list', 'form', 'form_copy', 'save', 'delete', 'copy'],
    'log' => ['list', 'view'],
    'backup' => ['index'],
    'manual' => ['list', 'superadmin', 'admin', 'professor', 'aluno'],
    'auth' => ['alterar_senha'],
    'checagem_site' => ['list', 'form', 'save', 'delete', 'view', 'check'],
    'produtos' => ['list', 'form', 'save', 'delete', 'comprar', 'copy'],
    'unidades' => ['list', 'form', 'save', 'delete'],
    'compras' => ['list', 'form', 'save', 'delete', 'comprar'],
    'sites' => ['list', 'form', 'save', 'delete', 'visit'],
    'videos' => ['list', 'form', 'save', 'delete', 'visit'],
    'servicos_site' => ['list', 'form', 'save', 'delete', 'view'],
    'portfolio' => ['list', 'form', 'save', 'delete'],
    'depoimentos_site' => ['list', 'form', 'save', 'delete'],
    'clientes_site' => ['list', 'form', 'save', 'delete'],
    'mailling_recebidos' => ['list', 'compose', 'view', 'view_sent', 'send', 'delete', 'archive', 'cron_send', 'process_send', 'sync_service', 'receive', 'ajax_search_pessoas', 'ajax_view'],
    'mailling_contas' => ['list', 'form', 'save', 'delete', 'copy', 'test_ports'],
    'mailing_recebidos' => ['list', 'compose', 'view', 'view_sent', 'send', 'delete', 'archive', 'cron_send', 'process_send', 'sync_service', 'receive'],
    'mailing_contas' => ['list', 'form', 'save', 'delete', 'copy'],
    'mailling' => ['list', 'form', 'save', 'delete', 'view'],
    'interessados' => ['list', 'form', 'save', 'delete', 'view'],
    'mailling_envios' => ['list', 'form', 'save', 'view', 'delete'],
    'mailling_lista' => ['list', 'form', 'save', 'delete', 'view'],
    'etapas' => ['list', 'form', 'form_copy', 'save', 'delete', 'view', 'copy', 'cronograma', 'cronograma_save'],
    'documentos_modelos' => ['list', 'form', 'form_copy', 'save', 'delete', 'view'],
    'documentos_modelos_variaveis' => ['list', 'form', 'save', 'delete'],
    'documentos_gerados' => ['list', 'form', 'save', 'delete', 'view', 'gerar_lote', 'baixar_lote'],
    'etapas_turmas' => ['list', 'form', 'form_copy', 'save', 'delete', 'view', 'copy', 'generate_lessons'],
    'etapas_cronogramas' => ['list', 'form', 'save', 'delete', 'view'],
    'turmas' => ['list', 'form', 'save', 'delete', 'view', 'ajax_link_all_disciplinas', 'send_access'],
    'turmas_disciplinas' => ['list', 'form', 'save', 'delete', 'view'],
    'turmas_notas' => ['list', 'form', 'save', 'delete', 'view'],
    'alunos' => ['list', 'form', 'save', 'delete', 'view', 'ajax_link', 'ajax_bulk_link', 'ajax_toggle', 'ajax_upload', 'ajax_send_docs'],


    'disciplinas' => ['list', 'form', 'save', 'delete', 'view'],
    'professores' => ['list', 'form', 'save', 'delete', 'view', 'ajax_send_docs'],
    'faculdades' => ['list', 'form', 'save', 'delete', 'view'],
    'coordenadores' => ['list', 'form', 'save', 'delete', 'view'],
    'horario_padrao' => ['list', 'form', 'save', 'delete', 'view'],
    'matriculas' => ['list', 'form', 'save', 'delete', 'view'],
    'pessoas_emails' => ['list', 'save', 'delete'],
    'pessoas_fones' => ['list', 'save', 'delete'],
    'aulas' => ['list', 'save', 'delete'],
    'apoio' => ['login_aluno'],
    'generic' => ['batch_action'],
    '104_questoes' => ['list', 'form', 'save', 'delete', 'view'],
    '104_questoes_opcoes' => ['list', 'form', 'save', 'delete', 'view'],
    '104_provas' => ['list', 'form', 'save', 'delete', 'view', 'relatorio_pdf'],
    '104_provas_questoes' => ['list', 'form', 'save', 'delete', 'view'],
    '104_provas_alunos' => ['list', 'form', 'save', 'delete', 'view', 'ajax_alunos', 'relatorio_notas_pdf', 'bulk'],
    'questoes' => ['list', 'form', 'save', 'delete', 'view'],
    'questoes_opcoes' => ['list', 'form', 'save', 'delete', 'view'],
    'provas' => ['list', 'form', 'save', 'delete', 'view', 'relatorio_pdf'],
    'provas_questoes' => ['list', 'form', 'save', 'delete', 'view'],
    'provas_alunos' => ['list', 'form', 'save', 'delete', 'view', 'bulk'],
    'aluno_provas' => ['list', 'view', 'finish', 'start_test'],
];

$contentFile = null;

$contentFile = null;

if (isset($routes[$module]) && in_array($action, $routes[$module], true)) {
    // Access control check
    $isAdminOnly = in_array($module, [
        'pessoas',
        'interessados',
        'mailling',
        'mailling_envios',
        'mailling_lista',
        '102_diario',
        '102_plano_de_contas',
        '102_lancamentos_recorrentes',
        '102_centro_de_custos',
        'etapas',
        'etapas_turmas',
        'turmas',
        'turmas_disciplinas',
        'turmas_notas',
        'alunos',
        'disciplinas',
        'faculdades',
        'professores',
        'coordenadores',
        'horario_padrao',
        'matriculas',
        'documentos_modelos',
        'documentos_gerados',
        '104_questoes',
        '104_questoes_opcoes',
        '104_provas',
        '104_provas_questoes',
        '104_provas_alunos',
        'questoes',
        'questoes_opcoes',
        'provas',
        'provas_questoes',
        'provas_alunos',
        '106_vendas',
        '106_frases',
        '106_hashtag',
        '106_compras',
        '106_categorias',
        '106_produtos',
        '106_audios_venda',
        '106_enviar_audio',
        'backup',
        '000_parametros',
        '000_audit',
        '000_locking',
        '000_searches',
        '000_estados',
        '000_videos_treinamento',
        'apoio'
    ]);

    $isProvasModule = str_contains($module, 'provas') || str_contains($module, 'questoes');
    if ($isAdminOnly && !current_user_is_at_least_admin() && !($isProvasModule && current_user_is_professor())) {
        $module = 'dashboard';
        $action = 'index';
        $contentFile = __DIR__ . '/app/templates/dashboard.php';
        $_SESSION['flash_error'] = 'Acesso negado para este módulo.';
    } elseif ($module === 'rel_aulas_professores' && !current_user_is_at_least_admin() && !current_user_is_professor()) {
        $module = 'dashboard';
        $action = 'index';
        $contentFile = __DIR__ . '/app/templates/dashboard.php';
        $_SESSION['flash_error'] = 'Acesso negado.';
    } else {
        $moduleDir = $module;
        // Mapeamento automático para módulos com prefixo 104_ para pastas sem prefixo
        if (str_starts_with($module, '104_')) {
            $shortModule = substr($module, 4);
            if (is_dir(__DIR__ . "/app/modules/{$shortModule}")) {
                $moduleDir = $shortModule;
            } else {
                // Fallback robusto para os módulos de provas/questões
                $map = [
                    '104_questoes' => 'questoes',
                    '104_questoes_opcoes' => 'questoes_opcoes',
                    '104_provas' => 'provas',
                    '104_provas_questoes' => 'provas_questoes',
                    '104_provas_alunos' => 'provas_alunos',
                ];
                if (isset($map[$module])) {
                    $moduleDir = $map[$module];
                }
            }
        }

        $contentFile = $module === 'dashboard'
            ? __DIR__ . '/app/templates/dashboard.php'
            : __DIR__ . "/app/modules/{$moduleDir}/{$action}.php";
    }
} elseif (str_starts_with($module, '000_') || str_starts_with($module, '101_') || str_starts_with($module, '102_') || str_starts_with($module, '104_') || str_starts_with($module, '105_') || str_starts_with($module, '106_') || str_starts_with($module, '110_')) {
    // Centralized administrative check for generic catch-all
    if (!current_user_is_at_least_admin()) {
        $module = 'dashboard';
        $action = 'index';
        $contentFile = __DIR__ . '/app/templates/dashboard.php';
        $_SESSION['flash_error'] = 'Acesso negado.';
    } else {
        // Catch-all for administrative tables
        $genericActions = ['list', 'form', 'save', 'delete', 'view', 'bulk'];
        if (in_array($action, $genericActions, true)) {
            $contentFile = __DIR__ . "/app/modules/generic/{$action}.php";
            // Export variables for the generic module
            $_TABLE = $module;
        }
    }
}

$layout = $_GET['layout'] ?? 'standard';

// Ações de controle (POST/DELETE) devem rodar antes de enviar HTML/headers
$controllerActions = ['save', 'delete', 'copy', 'history', 'relatorio_pdf', 'relatorio_notas_pdf', 'relatorio_completo_pdf', 'recibo_pdf', 'process', 'inicio_termino_action', 'complete', 'check', 'visit', 'reschedule', 'import', 'ajax_search', 'ajax_save', 'ajax_link', 'ajax_bulk_link', 'ajax_link_all_disciplinas', 'ajax_search_pessoas', 'ajax_view', 'ajax_alunos', 'test_ports', 'batch_action', 'ajax_toggle', 'ajax_upload', 'ajax_send_docs', 'generate_lessons', 'bulk', 'send_password', 'baixar_lote', 'cronograma_save', 'send_access'];

if ($contentFile && in_array($action, $controllerActions, true)) {
    if (file_exists($contentFile)) {
        // Validação de segurança CSRF para ações POST e GET críticas
        if (function_exists('csrf_verify')) {
            csrf_verify();
        }
        include $contentFile;
    } else {
        http_response_code(404);
        echo 'Rota não encontrada.';
    }
    exit;
}

// Log de acesso a listas
if ($action === 'list' && isset($routes[$module])) {
    log_event($pdo, $module, 'ACESSO', 'Acesso à tabela ' . $module);
}

require __DIR__ . '/app/templates/header.php';
?>

<div class="<?php echo $layout === 'clean' ? 'container-fluid px-0' : 'container-fluid mt-3'; ?>">
    <div class="row g-0">
        <div class="col-12 <?php echo $layout === 'clean' ? '' : 'mb-3'; ?>">

            <?php
            // DEBUG
            // error_log("Module: $module, Action: $action, ContentFile: $contentFile");
            if ($contentFile && file_exists($contentFile)) {
                try {
                    include $contentFile;
                } catch (Throwable $e) {
                    echo '<div class="alert alert-danger">
                            <h4>Erro Crítico</h4>
                            <p>' . htmlspecialchars($e->getMessage()) . '</p>
                            <hr>
                            <small>' . htmlspecialchars($e->getFile()) . ' na linha ' . $e->getLine() . '</small>
                            <pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>
                          </div>';
                }
            } else {
                ?>
                <div class="alert alert-warning">
                    <?php
                    $debugModuleDir = $moduleDir ?? $module;
                    $moduleDirFullPath = __DIR__ . "/app/modules/{$debugModuleDir}";
                    $dirExists = is_dir($moduleDirFullPath) ? "SIM" : "NÃO";
                    $fileExists = file_exists($contentFile) ? "SIM" : "NÃO";
                    $dirFiles = "N/A";
                    if (is_dir($moduleDirFullPath)) {
                        $scan = @scandir($moduleDirFullPath);
                        $dirFiles = $scan ? implode(', ', array_diff($scan, ['.', '..'])) : "Erro ao ler diretório";
                    }

                    echo "<h4>DEBUG: Conteúdo não encontrado ou rota não permitida.</h4>";
                    echo "Módulo: $module | Ação: $action | Pasta: $debugModuleDir<br>";
                    echo "Arquivo Esperado: $contentFile ($fileExists)<br>";
                    echo "Pasta do Módulo: $moduleDirFullPath ($dirExists)<br>";
                    echo "Arquivos Encontrados na Pasta: $dirFiles<br>";
                    echo "<hr>";
                    echo "Base URL: " . (defined('BASE_URL') ? BASE_URL : 'N/A') . "<br>";
                    echo "Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "<br>";
                    echo "PHP Version: " . phpversion() . "<br>";
                    ?>
                </div>
                <?php
            }
            ?>
        </div>
    </div>
</div>

<?php
if ($layout !== 'clean') {
    require __DIR__ . '/app/templates/footer.php';
} else {
    echo '</body></html>';
}
?>