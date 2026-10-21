<?php
// app/templates/topmenu.php (SIDEBAR VERSION)
$user = $_SESSION['user'] ?? null;
$nome_usuario_logado = $user['nome'] ?? 'Usuário';
$perfil_usuario = $user['perfil'] ?? 'OPERACIONAL';
$baseUrl = BASE_URL;
$currentModule = strtolower((string) ($_GET['m'] ?? 'dashboard'));
$currentAction = strtolower((string) ($_GET['a'] ?? 'index'));

function sidebar_item_active(string $module, ?string $action = null): string
{
  global $currentModule, $currentAction;
  $active = $currentModule === $module && ($action === null || $currentAction === $action);
  return $active ? ' active' : '';
}

function sidebar_group_open(array $modules): string
{
  global $currentModule;
  return in_array($currentModule, $modules, true) ? ' open' : '';
}
?>

<div id="sidebar-wrapper" class="sidebar shadow-sm"
  style="min-width: 260px !important; max-width: 260px !important; width: 260px !important; background-color: #0d0c31 !important; color: #fff !important; flex-shrink: 0 !important; display: flex !important; flex-direction: column !important; height: 100vh !important; position: sticky !important; top: 0 !important; min-height: 100vh !important;">
  <!-- Logo Section -->
  <div class="sidebar-logo d-flex align-items-center">
    <img src="<?php echo $baseUrl; ?>assets/img/logo.png" alt="Logo">
    <span class="text-white">Método Grisa</span>
  </div>

  <!-- Navigation Section -->
  <div class="sidebar-nav overflow-auto">
    <div class="sidebar-menu">

      <!-- Dashboard (Single Link) -->
      <div class="nav-item">
        <a class="nav-link sidebar-home<?php echo sidebar_item_active('dashboard', 'index'); ?>" href="<?php echo $baseUrl; ?>index.php">
          <i class="bi bi-speedometer2"></i>
          <span>Visão geral</span>
        </a>
      </div>

      <?php if (current_user_is_at_least_admin()): ?>
        <details class="sidebar-menu-group"<?php echo sidebar_group_open(['pessoas', 'interessados', 'mailling', 'mailling_envios', 'mailling_lista', 'mailling_recebidos', 'mailling_contas']); ?>>
          <summary><i class="bi bi-people"></i><span>Pessoas</span></summary>
          <div class="sidebar-menu-items">
            <a class="submenu-link<?php echo sidebar_item_active('pessoas', 'dashboard'); ?>" href="<?php echo $baseUrl; ?>index.php?m=pessoas&a=dashboard"><i class="bi bi-grid"></i><span>Dashboard</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('pessoas', 'list'); ?>" href="<?php echo $baseUrl; ?>index.php?m=pessoas&a=list"><i class="bi bi-person-lines-fill"></i><span>Cadastros</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('interessados'); ?>" href="<?php echo $baseUrl; ?>index.php?m=interessados&a=list"><i class="bi bi-person-plus"></i><span>Interessados</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('mailling'); ?>" href="<?php echo $baseUrl; ?>index.php?m=mailling&a=list"><i class="bi bi-envelope"></i><span>Mailing</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('mailling_lista'); ?>" href="<?php echo $baseUrl; ?>index.php?m=mailling_lista&a=list"><i class="bi bi-card-list"></i><span>Listas de mailing</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('mailling_envios'); ?>" href="<?php echo $baseUrl; ?>index.php?m=mailling_envios&a=list"><i class="bi bi-send"></i><span>Envios de mailing</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('mailling_recebidos'); ?>" href="<?php echo $baseUrl; ?>index.php?m=mailling_recebidos&a=list"><i class="bi bi-inbox"></i><span>Mensagens recebidas</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('mailling_contas'); ?>" href="<?php echo $baseUrl; ?>index.php?m=mailling_contas&a=list"><i class="bi bi-at"></i><span>Contas de e-mail</span></a>
          </div>
        </details>

        <details class="sidebar-menu-group"<?php echo sidebar_group_open(['financeiro', 'fin_contas', 'fin_categorias', 'fin_lancamentos_caixa']); ?>>
          <summary><i class="bi bi-wallet2"></i><span>Financeiro</span></summary>
          <div class="sidebar-menu-items">
            <a class="submenu-link<?php echo sidebar_item_active('financeiro', 'dashboard'); ?>" href="<?php echo $baseUrl; ?>index.php?m=financeiro&a=dashboard"><i class="bi bi-grid"></i><span>Dashboard</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('fin_contas'); ?>" href="<?php echo $baseUrl; ?>index.php?m=fin_contas&a=list"><i class="bi bi-bank"></i><span>Contas bancárias e caixas</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('fin_categorias'); ?>" href="<?php echo $baseUrl; ?>index.php?m=fin_categorias&a=list"><i class="bi bi-tags"></i><span>Categorias</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('fin_lancamentos_caixa'); ?>" href="<?php echo $baseUrl; ?>index.php?m=fin_lancamentos_caixa&a=list"><i class="bi bi-cash-stack"></i><span>Lançamentos de caixa</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('financeiro', 'relatorios'); ?>" href="<?php echo $baseUrl; ?>index.php?m=financeiro&a=relatorios"><i class="bi bi-bar-chart"></i><span>Relatórios</span></a>
          </div>
        </details>
      <?php endif; ?>

      <details class="sidebar-menu-group"<?php echo sidebar_group_open(['cursos', 'etapas', 'etapas_turmas', 'etapas_cronogramas', 'turmas', 'turmas_disciplinas', 'turmas_notas', 'aulas', 'alunos', 'disciplinas', 'professores', 'faculdades', 'coordenadores', 'horario_padrao', 'matriculas', 'documentos_modelos', 'documentos_gerados', '104_provas', '104_provas_questoes', '104_provas_alunos', '104_questoes', '104_questoes_opcoes', 'aluno_provas']); ?>>
        <summary><i class="bi bi-mortarboard"></i><span>Cursos</span></summary>
        <div class="sidebar-menu-items">
          <a class="submenu-link<?php echo sidebar_item_active('cursos', 'dashboard'); ?>" href="<?php echo $baseUrl; ?>index.php?m=cursos&a=dashboard"><i class="bi bi-grid"></i><span>Dashboard</span></a>
          <a class="submenu-link<?php echo sidebar_item_active('aulas'); ?>" href="<?php echo $baseUrl; ?>index.php?m=aulas&a=list"><i class="bi bi-play-btn"></i><span>Aulas</span></a>
          <?php if (current_user_is_at_least_admin()): ?>
            <a class="submenu-link<?php echo sidebar_item_active('etapas'); ?>" href="<?php echo $baseUrl; ?>index.php?m=etapas&a=list"><i class="bi bi-layers"></i><span>Etapas</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('etapas_turmas'); ?>" href="<?php echo $baseUrl; ?>index.php?m=etapas_turmas&a=list"><i class="bi bi-diagram-3"></i><span>Etapas por turma</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('etapas_cronogramas'); ?>" href="<?php echo $baseUrl; ?>index.php?m=etapas_cronogramas&a=list"><i class="bi bi-calendar3"></i><span>Cronogramas das etapas</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('turmas'); ?>" href="<?php echo $baseUrl; ?>index.php?m=turmas&a=list"><i class="bi bi-collection"></i><span>Turmas</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('turmas_disciplinas'); ?>" href="<?php echo $baseUrl; ?>index.php?m=turmas_disciplinas&a=list"><i class="bi bi-journal-bookmark"></i><span>Disciplinas por turma</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('turmas_notas'); ?>" href="<?php echo $baseUrl; ?>index.php?m=turmas_notas&a=list"><i class="bi bi-clipboard-data"></i><span>Notas das turmas</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('alunos'); ?>" href="<?php echo $baseUrl; ?>index.php?m=alunos&a=list"><i class="bi bi-people"></i><span>Alunos</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('matriculas'); ?>" href="<?php echo $baseUrl; ?>index.php?m=matriculas&a=list"><i class="bi bi-person-check"></i><span>Matrículas</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('disciplinas'); ?>" href="<?php echo $baseUrl; ?>index.php?m=disciplinas&a=list"><i class="bi bi-journal-bookmark-fill"></i><span>Disciplinas</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('professores'); ?>" href="<?php echo $baseUrl; ?>index.php?m=professores&a=list"><i class="bi bi-person-badge"></i><span>Professores</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('coordenadores'); ?>" href="<?php echo $baseUrl; ?>index.php?m=coordenadores&a=list"><i class="bi bi-person-workspace"></i><span>Coordenadores</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('faculdades'); ?>" href="<?php echo $baseUrl; ?>index.php?m=faculdades&a=list"><i class="bi bi-building"></i><span>Faculdades</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('horario_padrao'); ?>" href="<?php echo $baseUrl; ?>index.php?m=horario_padrao&a=list"><i class="bi bi-clock"></i><span>Horários padrão</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('documentos_modelos'); ?>" href="<?php echo $baseUrl; ?>index.php?m=documentos_modelos&a=list"><i class="bi bi-file-earmark-text"></i><span>Modelos de documentos</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('documentos_gerados'); ?>" href="<?php echo $baseUrl; ?>index.php?m=documentos_gerados&a=list"><i class="bi bi-files"></i><span>Documentos gerados</span></a>
          <?php endif; ?>
          <?php if (current_user_is_aluno() && !current_user_is_at_least_admin()): ?>
            <a class="submenu-link<?php echo sidebar_item_active('aluno_provas'); ?>" href="<?php echo $baseUrl; ?>index.php?m=aluno_provas&a=list"><i class="bi bi-journal-check"></i><span>Minhas provas</span></a>
          <?php else: ?>
            <a class="submenu-link<?php echo sidebar_item_active('104_provas'); ?>" href="<?php echo $baseUrl; ?>index.php?m=104_provas&a=list"><i class="bi bi-journal-check"></i><span>Provas</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('104_questoes'); ?>" href="<?php echo $baseUrl; ?>index.php?m=104_questoes&a=list"><i class="bi bi-question-circle"></i><span>Questões</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('104_questoes_opcoes'); ?>" href="<?php echo $baseUrl; ?>index.php?m=104_questoes_opcoes&a=list"><i class="bi bi-ui-radios"></i><span>Opções das questões</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('104_provas_questoes'); ?>" href="<?php echo $baseUrl; ?>index.php?m=104_provas_questoes&a=list"><i class="bi bi-link-45deg"></i><span>Questões por prova</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('104_provas_alunos'); ?>" href="<?php echo $baseUrl; ?>index.php?m=104_provas_alunos&a=list"><i class="bi bi-clipboard-check"></i><span>Provas dos alunos</span></a>
          <?php endif; ?>
        </div>
      </details>

      <?php if (current_user_is_at_least_admin()): ?>
        <details class="sidebar-menu-group"<?php echo sidebar_group_open(['vendas', '106_vendas', '106_produtos', '106_compras', '106_categorias', '106_frases', '106_hashtag', '106_audios_venda', '106_enviar_audio']); ?>>
          <summary><i class="bi bi-cart3"></i><span>Vendas</span></summary>
          <div class="sidebar-menu-items">
            <a class="submenu-link<?php echo sidebar_item_active('vendas', 'dashboard'); ?>" href="<?php echo $baseUrl; ?>index.php?m=vendas&a=dashboard"><i class="bi bi-grid"></i><span>Dashboard</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('106_vendas'); ?>" href="<?php echo $baseUrl; ?>index.php?m=106_vendas&a=list"><i class="bi bi-receipt"></i><span>Vendas</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('106_produtos'); ?>" href="<?php echo $baseUrl; ?>index.php?m=106_produtos&a=list"><i class="bi bi-box-seam"></i><span>Produtos</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('106_compras'); ?>" href="<?php echo $baseUrl; ?>index.php?m=106_compras&a=list"><i class="bi bi-bag"></i><span>Compras</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('106_categorias'); ?>" href="<?php echo $baseUrl; ?>index.php?m=106_categorias&a=list"><i class="bi bi-tags"></i><span>Categorias</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('106_frases'); ?>" href="<?php echo $baseUrl; ?>index.php?m=106_frases&a=list"><i class="bi bi-chat-quote"></i><span>Frases</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('106_hashtag'); ?>" href="<?php echo $baseUrl; ?>index.php?m=106_hashtag&a=list"><i class="bi bi-hash"></i><span>Hashtags</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('106_audios_venda'); ?>" href="<?php echo $baseUrl; ?>index.php?m=106_audios_venda&a=list"><i class="bi bi-volume-up"></i><span>Áudios de vendas</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('106_enviar_audio'); ?>" href="<?php echo $baseUrl; ?>index.php?m=106_enviar_audio&a=list"><i class="bi bi-send"></i><span>Envio de áudios</span></a>
          </div>
        </details>
      <?php endif; ?>

      <!-- Suporte (Auto-login iControle) -->
      <?php
      $chaveSecreta_icontrole = 'icontrole_sso_secret_key_2026';
      $clienteIdentificador_icontrole = '3';
      $timestamp_icontrole = time();
      $assinatura_icontrole = hash_hmac('sha256', "$clienteIdentificador_icontrole:$timestamp_icontrole", $chaveSecreta_icontrole);
      $payload_icontrole = "$clienteIdentificador_icontrole:$timestamp_icontrole:$assinatura_icontrole";
      $tokenSSO_icontrole = rtrim(strtr(base64_encode($payload_icontrole), '+/', '-_'), '=');
      $linkAcessoiControle = "https://icontrole.com.br/clientes/autologin.php?token=" . $tokenSSO_icontrole;
      ?>
      <details class="sidebar-menu-group"<?php echo sidebar_group_open(['apoio', '000_parametros', '000_audit', '000_videos_treinamento', 'backup', 'log', 'manual']); ?>>
        <summary><i class="bi bi-info-circle"></i><span>Apoio</span></summary>
        <div class="sidebar-menu-items">
          <?php if (current_user_is_at_least_admin()): ?>
            <a class="submenu-link<?php echo sidebar_item_active('apoio', 'dashboard'); ?>" href="<?php echo $baseUrl; ?>index.php?m=apoio&a=dashboard"><i class="bi bi-grid"></i><span>Dashboard</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('000_parametros'); ?>" href="<?php echo $baseUrl; ?>index.php?m=000_parametros&a=list"><i class="bi bi-sliders"></i><span>Parâmetros</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('000_audit'); ?>" href="<?php echo $baseUrl; ?>index.php?m=000_audit&a=list"><i class="bi bi-shield-check"></i><span>Auditoria</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('000_videos_treinamento'); ?>" href="<?php echo $baseUrl; ?>index.php?m=000_videos_treinamento&a=list"><i class="bi bi-camera-video"></i><span>Vídeos de treinamento</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('log'); ?>" href="<?php echo $baseUrl; ?>index.php?m=log&a=list"><i class="bi bi-card-text"></i><span>Logs</span></a>
            <a class="submenu-link<?php echo sidebar_item_active('backup'); ?>" href="<?php echo $baseUrl; ?>index.php?m=backup&a=index"><i class="bi bi-database-check"></i><span>Backup</span></a>
          <?php endif; ?>
          <a class="submenu-link<?php echo sidebar_item_active('manual'); ?>" href="<?php echo $baseUrl; ?>index.php?m=manual&a=list"><i class="bi bi-book"></i><span>Manual</span></a>
          <a class="submenu-link" href="<?php echo $linkAcessoiControle; ?>" target="_blank" rel="noopener"><i class="bi bi-headset"></i><span>Suporte</span></a>
        </div>
      </details>

    </div>
  </div>

  <!-- User Section (Fixed at bottom) -->
  <div class="sidebar-user">
    <div class="sidebar-user-name text-white"><?php echo htmlspecialchars($nome_usuario_logado); ?></div>
    <div class="sidebar-user-role mb-3"><span
        class="badge bg-secondary"><?php echo htmlspecialchars($perfil_usuario); ?></span></div>

    <div class="d-grid gap-2">
      <a href="<?php echo $baseUrl; ?>index.php?m=auth&a=alterar_senha"
        class="btn btn-sm btn-outline-light d-flex align-items-center justify-content-center">
        <i class="bi bi-key me-2"></i>Senha
      </a>
      <a href="<?php echo $baseUrl; ?>login.php?logout=1"
        class="btn btn-sm btn-danger d-flex align-items-center justify-content-center">
        <i class="bi bi-box-arrow-right me-2"></i>Sair
      </a>
    </div>
  </div>
</div>
