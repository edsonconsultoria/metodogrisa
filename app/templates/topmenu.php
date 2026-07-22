<?php
// app/templates/topmenu.php (SIDEBAR VERSION)
$user = $_SESSION['user'] ?? null;
$nome_usuario_logado = $user['nome'] ?? 'Usuário';
$perfil_usuario = $user['perfil'] ?? 'OPERACIONAL';
$baseUrl = BASE_URL;
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
    <div class="accordion" id="sidebarAccordion">

      <!-- Dashboard (Single Link) -->
      <div class="nav-item">
        <a class="nav-link" href="<?php echo $baseUrl; ?>index.php">
          <i class="bi bi-speedometer2"></i>
          <span>Dashboard</span>
        </a>
      </div>

      <!-- Pessoas (Accordion) -->
      <?php if (current_user_is_at_least_admin()): ?>
        <div class="accordion-item">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
              data-bs-target="#collapsePessoas">
              <i class="bi bi-people me-2"></i> Pessoas
            </button>
          </h2>
          <div id="collapsePessoas" class="accordion-collapse collapse" data-bs-parent="#sidebarAccordion">
            <div class="accordion-body">
              <a class="submenu-link" href="<?php echo $baseUrl; ?>index.php?m=pessoas&a=list">Pessoas</a>
              <a class="submenu-link" href="<?php echo $baseUrl; ?>index.php?m=interessados&a=list">Interessados</a>
              <a class="submenu-link" href="<?php echo $baseUrl; ?>index.php?m=mailling&a=list">Mailling</a>
              <a class="submenu-link" href="<?php echo $baseUrl; ?>index.php?m=mailling_envios&a=list">Envia Mailling</a>
              <a class="submenu-link" href="<?php echo $baseUrl; ?>index.php?m=mailling_lista&a=list">Lista Mailling</a>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- Finanças (Accordion) -->
      <?php if (current_user_is_at_least_admin()): ?>
        <div class="accordion-item">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
              data-bs-target="#collapseFinancas">
              <i class="bi bi-wallet2 me-2"></i> Finanças
            </button>
          </h2>
          <div id="collapseFinancas" class="accordion-collapse collapse" data-bs-parent="#sidebarAccordion">
            <div class="accordion-body">
              <a class="submenu-link" href="<?php echo $baseUrl; ?>index.php?m=102_diario&a=list">Diário</a>
              <a class="submenu-link" href="<?php echo $baseUrl; ?>index.php?m=102_plano_de_contas&a=list">Ativo
                Disponível</a>
              <a class="submenu-link" href="<?php echo $baseUrl; ?>index.php?m=102_diario&a=list">Receber</a>
              <a class="submenu-link" href="<?php echo $baseUrl; ?>index.php?m=102_diario&a=list">Pagar</a>
              <a class="submenu-link" href="<?php echo $baseUrl; ?>index.php?m=102_diario&a=list">Lançamentos</a>
              <a class="submenu-link"
                href="<?php echo $baseUrl; ?>index.php?m=102_lancamentos_recorrentes&a=list">Lançamentos Recorrentes</a>
              <a class="submenu-link" href="<?php echo $baseUrl; ?>index.php?m=102_plano_de_contas&a=list">Plano de
                Contas</a>
              <a class="submenu-link" href="<?php echo $baseUrl; ?>index.php?m=102_centro_de_custos&a=list">Centro de
                Custos</a>
              <hr class="m-0 opacity-10">
              <a class="submenu-link" href="<?php echo $baseUrl; ?>index.php?m=102_plano_de_contas&a=list">Balanço
                Patrimon. Mensal</a>
              <a class="submenu-link" href="<?php echo $baseUrl; ?>index.php?m=102_plano_de_contas&a=list">Dre Mensal</a>
              <a class="submenu-link" href="<?php echo $baseUrl; ?>index.php?m=102_diario&a=list">Fechamento Caixa
                Mensal</a>
              <a class="submenu-link" href="<?php echo $baseUrl; ?>index.php?m=102_diario&a=list">Diário Mensal</a>
              <a class="submenu-link" href="<?php echo $baseUrl; ?>index.php?m=102_diario&a=list">Balancete Mensal</a>
              <a class="submenu-link" href="<?php echo $baseUrl; ?>index.php?m=102_plano_de_contas&a=list">Saldo no dia da
                conta</a>
              <a class="submenu-link" href="<?php echo $baseUrl; ?>index.php?m=102_plano_de_contas&a=list">Rel Saldo
                Conta</a>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- Cursos (Accordion) -->
      <div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
            data-bs-target="#collapseCursos">
            <i class="bi bi-mortarboard me-2"></i> Cursos
          </button>
        </h2>
        <div id="collapseCursos" class="accordion-collapse collapse" data-bs-parent="#sidebarAccordion">
          <div class="accordion-body">
            <!-- Etapas Subgroup -->
            <div class="nav-item">
              <a class="submenu-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse"
                href="#collapseEtapasInterno" role="button">
                <span><i class="bi bi-layers me-2"></i> Etapas</span>
                <i class="bi bi-chevron-down small"></i>
              </a>
              <div class="collapse" id="collapseEtapasInterno">
                <div class="ps-3 bg-white bg-opacity-10 border-start border-white border-opacity-10 ms-4">
                  <?php if (current_user_is_at_least_admin()): ?>
                    <a class="submenu-link py-1 ps-3" href="<?php echo $baseUrl; ?>index.php?m=etapas&a=list">Etapas</a>
                    <a class="submenu-link py-1 ps-3"
                      href="<?php echo $baseUrl; ?>index.php?m=etapas_turmas&a=list">Etapas turmas</a>
                  <?php endif; ?>
                  <a class="submenu-link py-1 ps-3" href="<?php echo $baseUrl; ?>index.php?m=aulas&a=list">Aulas</a>
                  <a class="submenu-link py-1 ps-3"
                    href="<?php echo $baseUrl; ?>index.php?m=etapas_cronogramas&a=list">Cronograma</a>
                  <?php if (current_user_is_at_least_admin()): ?>
                    <a class="submenu-link py-1 ps-3"
                      href="<?php echo $baseUrl; ?>index.php?m=horario_padrao&a=list">Horário padrão aulas</a>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- Turmas Subgroup -->
            <?php if (current_user_is_at_least_admin()): ?>
              <div class="nav-item">
                <a class="submenu-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse"
                  href="#collapseTurmasInterno" role="button">
                  <span><i class="bi bi-collection me-2"></i> Turmas</span>
                  <i class="bi bi-chevron-down small"></i>
                </a>
                <div class="collapse" id="collapseTurmasInterno">
                  <div class="ps-3 bg-white bg-opacity-10 border-start border-white border-opacity-10 ms-4">
                    <a class="submenu-link py-1 ps-3" href="<?php echo $baseUrl; ?>index.php?m=turmas&a=list">Turmas</a>
                    <a class="submenu-link py-1 ps-3"
                      href="<?php echo $baseUrl; ?>index.php?m=turmas_disciplinas&a=list">Turmas Disciplinas</a>
                    <a class="submenu-link py-1 ps-3" href="<?php echo $baseUrl; ?>index.php?m=turmas_notas&a=list">Turmas
                      Notas</a>
                    <a class="submenu-link py-1 ps-3" href="<?php echo $baseUrl; ?>index.php?m=alunos&a=list">Alunos</a>
                    <a class="submenu-link py-1 ps-3"
                      href="<?php echo $baseUrl; ?>index.php?m=rel_pendencias&a=relatorio">Rel Pendências</a>
                    <a class="submenu-link py-1 ps-3"
                      href="<?php echo $baseUrl; ?>index.php?m=disciplinas&a=list">Disciplinas</a>
                    <a class="submenu-link py-1 ps-3"
                      href="<?php echo $baseUrl; ?>index.php?m=faculdades&a=list">Faculdades</a>
                    <a class="submenu-link py-1 ps-3"
                      href="<?php echo $baseUrl; ?>index.php?m=coordenadores&a=list">Coordenadores</a>
                  </div>
                </div>
              </div>
            <?php endif; ?>

            <!-- Professores Subgroup -->
            <?php if (current_user_is_at_least_admin() || current_user_is_professor()): ?>
              <div class="nav-item">
                <a class="submenu-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse"
                  href="#collapseProfessoresInterno" role="button">
                  <span><i class="bi bi-person-badge me-2"></i> Professores</span>
                  <i class="bi bi-chevron-down small"></i>
                </a>
                <div class="collapse" id="collapseProfessoresInterno">
                  <div class="ps-3 bg-white bg-opacity-10 border-start border-white border-opacity-10 ms-4">
                    <?php if (current_user_is_at_least_admin()): ?>
                      <a class="submenu-link py-1 ps-3"
                        href="<?php echo $baseUrl; ?>index.php?m=professores&a=list">Professores</a>
                    <?php endif; ?>
                    <a class="submenu-link py-1 ps-3"
                      href="<?php echo $baseUrl; ?>index.php?m=rel_aulas_professores&a=relatorio">Aulas professores</a>
                  </div>
                </div>
              </div>
            <?php endif; ?>

            <!-- Documentos Subgroup -->
            <?php if (current_user_is_at_least_admin()): ?>
              <div class="nav-item">
                <a class="submenu-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse"
                  href="#collapseDocumentosInterno" role="button">
                  <span><i class="bi bi-file-earmark-text me-2"></i> Documentos</span>
                  <i class="bi bi-chevron-down small"></i>
                </a>
                <div class="collapse" id="collapseDocumentosInterno">
                  <div class="ps-3 bg-white bg-opacity-10 border-start border-white border-opacity-10 ms-4">
                    <a class="submenu-link py-1 ps-3"
                      href="<?php echo $baseUrl; ?>index.php?m=documentos_modelos&a=list">Modelo de documentos</a>
                    <a class="submenu-link py-1 ps-3"
                      href="<?php echo $baseUrl; ?>index.php?m=documentos_gerados&a=list">Documentos gerados</a>
                    <a class="submenu-link py-1 ps-3"
                      href="<?php echo $baseUrl; ?>index.php?m=documentos_gerados&a=gerar_lote">Gerar em lote</a>
                  </div>
                </div>
              </div>
            <?php endif; ?>

            <!-- Provas Subgroup -->
            <?php if (current_user_is_at_least_admin() || current_user_is_professor() || current_user_is_aluno()): ?>
              <div class="nav-item">
                <a class="submenu-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse"
                  href="#collapseProvasInterno" role="button">
                  <span><i class="bi bi-journal-check me-2"></i> Provas</span>
                  <i class="bi bi-chevron-down small"></i>
                </a>
                <div class="collapse" id="collapseProvasInterno">
                  <div class="ps-3 bg-white bg-opacity-10 border-start border-white border-opacity-10 ms-4">
                    <?php if (current_user_is_at_least_admin() || current_user_is_professor()): ?>
                      <a class="submenu-link py-1 ps-3" href="<?php echo $baseUrl; ?>index.php?m=104_questoes&a=list">Questões</a>
                      <a class="submenu-link py-1 ps-3" href="<?php echo $baseUrl; ?>index.php?m=104_provas&a=list">Provas</a>
                      <a class="submenu-link py-1 ps-3" href="<?php echo $baseUrl; ?>index.php?m=104_provas_alunos&a=list">Alunos
                        da Prova</a>
                    <?php endif; ?>
                    <a class="submenu-link py-1 ps-3" href="<?php echo $baseUrl; ?>index.php?m=aluno_provas&a=list">
                      <?php echo current_user_is_aluno() ? 'Minhas Provas' : 'Fazer a Prova'; ?>
                    </a>
                  </div>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>



      <!-- Vendas (Accordion) -->
      <?php if (current_user_is_at_least_admin()): ?>
        <div class="accordion-item">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
              data-bs-target="#collapseVendas">
              <i class="bi bi-cart3 me-2"></i> Vendas
            </button>
          </h2>
          <div id="collapseVendas" class="accordion-collapse collapse" data-bs-parent="#sidebarAccordion">
            <div class="accordion-body">
              <a class="submenu-link" href="<?php echo $baseUrl; ?>index.php?m=106_vendas&a=list">Vendas</a>
              <a class="submenu-link" href="<?php echo $baseUrl; ?>index.php?m=106_frases&a=list">Frases</a>
              <a class="submenu-link" href="<?php echo $baseUrl; ?>index.php?m=106_hashtag&a=list">Hashtag</a>
              <a class="submenu-link" href="<?php echo $baseUrl; ?>index.php?m=106_compras&a=list">Compras</a>
              <a class="submenu-link" href="<?php echo $baseUrl; ?>index.php?m=106_categorias&a=list">Categorias</a>
              <a class="submenu-link" href="<?php echo $baseUrl; ?>index.php?m=106_produtos&a=list">Produtos</a>
              <a class="submenu-link" href="<?php echo $baseUrl; ?>index.php?m=106_audios_venda&a=list">Áudios Venda</a>
              <a class="submenu-link" href="<?php echo $baseUrl; ?>index.php?m=106_enviar_audio&a=list">Enviar Áudio</a>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- Apoio (Accordion) -->
      <div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
            data-bs-target="#collapseApoio">
            <i class="bi bi-info-circle me-2"></i> Apoio
          </button>
        </h2>
        <div id="collapseApoio" class="accordion-collapse collapse" data-bs-parent="#sidebarAccordion">
          <div class="accordion-body">
            <a class="submenu-link fw-bold" href="<?php echo $baseUrl; ?>index.php?m=manual&a=list">Manuais</a>
            <?php if (current_user_is_at_least_admin()): ?>
              <hr class="m-0 opacity-10">
              <a class="submenu-link" href="<?php echo $baseUrl; ?>index.php?m=backup&a=index">Backup</a>
              <a class="submenu-link" href="<?php echo $baseUrl; ?>index.php?m=000_parametros&a=list">Parâmetros</a>
              <a class="submenu-link"
                href="<?php echo $baseUrl; ?>index.php?m=pessoas&a=list&marcador=usuario">Usuários</a>
              <a class="submenu-link" href="<?php echo $baseUrl; ?>index.php?m=000_audit&a=list">Auditoria</a>
              <a class="submenu-link" href="<?php echo $baseUrl; ?>index.php?m=000_locking&a=list">Bloqueios</a>
              <a class="submenu-link" href="<?php echo $baseUrl; ?>index.php?m=000_searches&a=list">Buscas</a>
              <a class="submenu-link" href="<?php echo $baseUrl; ?>index.php?m=000_estados&a=list">Estados</a>
              <a class="submenu-link" href="<?php echo $baseUrl; ?>index.php?m=000_videos_treinamento&a=list">Vídeos
                Treinamento</a>
              <a class="submenu-link" href="<?php echo $baseUrl; ?>index.php?m=apoio&a=login_aluno">Login Aluno</a>
            <?php endif; ?>
          </div>
        </div>
      </div>

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