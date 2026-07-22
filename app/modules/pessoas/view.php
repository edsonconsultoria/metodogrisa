<?php
// app/modules/pessoas/view.php

require_once __DIR__ . '/../../config/config.php';

if (!current_user_is_at_least_admin()) {
  echo '<div class="alert alert-danger mt-3">Acesso negado. Apenas administradores podem acessar este módulo.</div>';
  include __DIR__ . '/../../templates/footer.php';
  exit;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$baseHref = BASE_URL . 'index.php';
$encodedReturn = $_GET['return'] ?? '';
$returnQuery = trim(urldecode($encodedReturn));
$returnQuery = $returnQuery === '' ? 'm=pessoas&a=list' : $returnQuery;
$returnUrl = BASE_URL . 'index.php?' . $returnQuery;
$editReturnQuery = 'm=pessoas&a=view&id=' . ((int) $id);
$encodedEditReturn = rawurlencode($editReturnQuery);

if ($id <= 0) {
  echo '<div class="alert alert-danger">ID inválido.</div>';
  return;
}

$stmt = $pdo->prepare("SELECT * FROM 101_pessoas WHERE id = :id");
$stmt->execute([':id' => $id]);
$pessoa = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pessoa) {
  echo '<div class="alert alert-danger">Pessoa não encontrada.</div>';
  return;
}

$formatCpfCnpj = function (string $doc): string {
  $digits = preg_replace('/\D+/', '', $doc);
  if (strlen($digits) === 11) {
    return substr($digits, 0, 3) . '.' . substr($digits, 3, 3) . '.' . substr($digits, 6, 3) . '-' . substr($digits, 9, 2);
  }
  if (strlen($digits) === 14) {
    return substr($digits, 0, 2) . '.' . substr($digits, 2, 3) . '.' . substr($digits, 5, 3) . '/' . substr($digits, 8, 4) . '-' . substr($digits, 12, 2);
  }
  return $doc;
};



$buscarNomePessoa = function (?int $id) use ($pdo): ?string {
  if (!$id) {
    return null;
  }
  $stmt = $pdo->prepare("SELECT nome FROM 101_pessoas WHERE id = :id");
  $stmt->execute([':id' => $id]);
  $nome = $stmt->fetchColumn();
  return $nome !== false ? $nome : null;
};

// Junction tables are gone
$emailsAdicionais = [];
$fonesAdicionais = [];

$funcoes = [];
if (!empty($pessoa['cliente'])) {
  $funcoes[] = 'Cliente';
}
if (!empty($pessoa['fornecedor'])) {
  $funcoes[] = 'Fornecedor';
}

$isUsuario = !empty($pessoa['login']);
// Log de visualização
log_event($pdo, 'pessoas', 'ACESSO', 'Visualização pessoa ID ' . $id);
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><?php echo $isUsuario ? 'Usuário' : 'Pessoa'; ?>
    <?php echo htmlspecialchars($pessoa['nome'] ?? ''); ?></h4>
  <div class="d-flex gap-2">
    <a href="<?php echo $baseHref; ?>?m=pessoas&a=form&id=<?php echo (int) $pessoa['id']; ?>&return=<?php echo $encodedEditReturn; ?>"
      class="btn btn-primary btn-sm">Editar</a>
    <a href="<?php echo $returnUrl; ?>" class="btn btn-outline-secondary btn-sm">Voltar</a>
  </div>
</div>

<div id="pessoaViewContent">
  <!-- Seção: Cadastro -->
  <div class="card shadow-sm mb-4">
    <div class="card-header bg-light fw-bold">Dados de Cadastro</div>
    <div class="card-body">
      <div class="d-flex flex-column flex-md-row gap-4 align-items-center align-items-md-start">
        <div class="flex-shrink-0">
          <div class="flex-shrink-0">
            <div
              class="d-flex align-items-center justify-content-center bg-light border text-secondary img-thumbnail shadow-sm"
              style="width: 120px; height: 120px; font-size: 3rem;">
              <i class="bi bi-person"></i>
            </div>
          </div>
        </div>
        <div class="flex-grow-1 w-100">
          <div class="row row-cols-1 row-cols-md-3 g-3">
            <div class="col"><span class="fw-bold">Nome:</span> <?php echo htmlspecialchars($pessoa['nome'] ?? '—'); ?>
            </div>
            <div class="col"><span class="fw-bold">Razão Social:</span>
              <?php echo htmlspecialchars($pessoa['razao_social'] ?? '—'); ?></div>
            <div class="col"><span class="fw-bold">Vendedor:</span>
              <?php echo htmlspecialchars($pessoa['vendedor'] ?? '—'); ?></div>
            <div class="col"><span class="fw-bold">Nascimento:</span>
              <?php echo !empty($pessoa['nascimento']) ? date('d/m/Y', strtotime($pessoa['nascimento'])) : '—'; ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Seção: Documentação -->
  <div class="card shadow-sm mb-4">
    <div class="card-header bg-light fw-bold">Documentação e Pessoal</div>
    <div class="card-body">
      <div class="row row-cols-1 row-cols-md-3 g-3">
        <div class="col"><span class="fw-bold">CPF/CNPJ:</span>
          <?php echo $formatCpfCnpj((string) ($pessoa['cpf_cnpj'] ?? '')); ?></div>
        <div class="col"><span class="fw-bold">RG/IE:</span> <?php echo htmlspecialchars($pessoa['rg_ie'] ?? '—'); ?>
        </div>
        <div class="col"><span class="fw-bold">Estado Civil:</span>
          <?php echo htmlspecialchars($pessoa['estado_civil'] ?? '—'); ?></div>
        <div class="col"><span class="fw-bold">Escolaridade:</span>
          <?php echo htmlspecialchars($pessoa['escolaridade'] ?? '—'); ?></div>
        <div class="col"><span class="fw-bold">Nacionalidade:</span>
          <?php echo htmlspecialchars($pessoa['nacionalidade'] ?? '—'); ?></div>
        <div class="col"><span class="fw-bold">Local Nascimento:</span>
          <?php echo htmlspecialchars($pessoa['local_nascimento'] ?? '—'); ?></div>
        <div class="col"><span class="fw-bold">Religião:</span>
          <?php echo htmlspecialchars($pessoa['religiao'] ?? '—'); ?></div>
        <div class="col"><span class="fw-bold">Título:</span> <?php echo htmlspecialchars($pessoa['titulo'] ?? '—'); ?>
        </div>
        <div class="col"><span class="fw-bold">Profissão:</span>
          <?php echo htmlspecialchars($pessoa['profissao_atual'] ?? '—'); ?></div>
        <div class="col-md-12"><span class="fw-bold">Filiação:</span>
          <?php echo htmlspecialchars($pessoa['filiacao'] ?? '—'); ?></div>
      </div>
    </div>
  </div>

  <!-- Seção: Contato e Social -->
  <div class="card shadow-sm mb-4">
    <div class="card-header bg-light fw-bold">Contato e Redes Sociais</div>
    <div class="card-body">
      <div class="row row-cols-1 row-cols-md-3 g-3">
        <div class="col"><span class="fw-bold">E-mail:</span>
          <?php echo !empty($pessoa['email']) ? htmlspecialchars($pessoa['email']) : '—'; ?></div>
        <div class="col"><span class="fw-bold">Telefones:</span>
          <?php
          $fones = [];
          if (!empty($pessoa['fone_1']))
            $fones[] = $pessoa['fone_1'];
          if (!empty($pessoa['fone_2']))
            $fones[] = $pessoa['fone_2'];
          if (!empty($pessoa['fone_3']))
            $fones[] = $pessoa['fone_3'];
          echo !empty($fones) ? htmlspecialchars(implode(' ', $fones)) : '—';
          ?>
        </div>
        <div class="col"><span class="fw-bold">Facebook:</span>
          <?php echo htmlspecialchars($pessoa['facebook'] ?? '—'); ?></div>
        <div class="col"><span class="fw-bold">Twitter:</span>
          <?php echo htmlspecialchars($pessoa['twitter'] ?? '—'); ?></div>
      </div>
    </div>
  </div>

  <!-- Seção: Endereço -->
  <div class="card shadow-sm mb-4">
    <div class="card-header bg-light fw-bold">Endereço</div>
    <div class="card-body">
      <div class="row row-cols-1 row-cols-md-3 g-3">
        <div class="col"><span class="fw-bold">CEP:</span> <?php echo htmlspecialchars($pessoa['cep'] ?? '—'); ?></div>
        <div class="col-md-9"><span class="fw-bold">Endereço:</span>
          <?php echo htmlspecialchars($pessoa['endereco'] ?? '—'); ?><?php echo !empty($pessoa['numero']) ? ', ' . $pessoa['numero'] : ''; ?>
        </div>
        <div class="col"><span class="fw-bold">Complemento:</span>
          <?php echo htmlspecialchars($pessoa['complemento'] ?? '—'); ?></div>
        <div class="col"><span class="fw-bold">Bairro:</span> <?php echo htmlspecialchars($pessoa['bairro'] ?? '—'); ?>
        </div>
        <div class="col"><span class="fw-bold">Cidade/UF:</span>
          <?php echo htmlspecialchars($pessoa['cidade'] ?? '—'); ?>/<?php echo htmlspecialchars($pessoa['estado'] ?? '—'); ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Seção: Etiquetas e Controle -->
  <div class="card shadow-sm mb-4">
    <div class="card-header bg-light fw-bold">Etiquetas e Auditoria</div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-12"><span
            class="fw-bold">Observações:</span><br><?php echo nl2br(htmlspecialchars($pessoa['obs'] ?? '—')); ?></div>
        <div class="col-md-6">
          <span class="fw-bold d-block mb-1">Vínculos:</span>
          <?php
          $vinculos = [];
          if (($pessoa['cliente'] ?? '') === '1')
            $vinculos[] = 'Cliente';
          if (($pessoa['fornecedor'] ?? '') === '1')
            $vinculos[] = 'Fornecedor';
          if (($pessoa['aluno_unidade_fpolis'] ?? '') === '1')
            $vinculos[] = 'Aluno Fpolis';
          if (($pessoa['aluno_outras_unidades'] ?? '') === '1')
            $vinculos[] = 'Aluno Outras';
          if (($pessoa['professor_fpolis'] ?? '') === '1')
            $vinculos[] = 'Prof. Fpolis';
          if (($pessoa['professor_unidades'] ?? '') === '1')
            $vinculos[] = 'Prof. Outras';
          if (($pessoa['unidade'] ?? '') === '1')
            $vinculos[] = 'Unidade';
          if (($pessoa['simposio_2021'] ?? '') === '1')
            $vinculos[] = "Simpósio 2021 (" . ($pessoa['tipo_simposio_2021'] ?? '') . ")";
          if (($pessoa['ciclo_de_palestras'] ?? '') === '1')
            $vinculos[] = 'Ciclo Palestras';
          if (($pessoa['interessado_no_curso'] ?? '') === '1')
            $vinculos[] = 'Int. Curso';
          if (($pessoa['controle'] ?? '') === '1')
            $vinculos[] = 'Controle';

          if (empty($vinculos))
            echo '<span class="text-muted">Nenhum vínculo.</span>';
          else
            foreach ($vinculos as $v)
              echo '<span class="badge bg-info me-1">' . htmlspecialchars($v) . '</span>';
          ?>
        </div>
        <div class="col-md-6">
          <span class="fw-bold d-block mb-1">Sistema:</span>
          <?php if (!empty($pessoa['login'])): ?>
            <span class="badge bg-success">Acesso Ativo</span> (<?php echo htmlspecialchars($pessoa['perfil'] ?? ''); ?>)
          <?php else: ?>
            <span class="text-muted">Sem acesso.</span>
          <?php endif; ?>
        </div>
        <div class="col-md-12 border-top pt-2 small text-muted">
          Inclusão: <?php echo !empty($pessoa['inclusao']) ? date('d/m/Y H:i', strtotime($pessoa['inclusao'])) : '—'; ?>
          |
          Alteração:
          <?php echo !empty($pessoa['alteracao']) ? date('d/m/Y H:i', strtotime($pessoa['alteracao'])) : '—'; ?>
        </div>
      </div>
    </div>
  </div>
</div>