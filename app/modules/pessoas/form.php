<?php
// app/modules/pessoas/form.php

require_once __DIR__ . '/../../config/config.php';

if (!current_user_is_at_least_admin()) {
  echo '<div class="alert alert-danger mt-3">Acesso negado. Apenas administradores podem acessar este módulo.</div>';
  include __DIR__ . '/../../templates/footer.php';
  exit;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$copyFrom = isset($_GET['copy_from']) ? (int) $_GET['copy_from'] : 0;

$baseHref = BASE_URL . 'index.php';
$returnEncoded = $_GET['return'] ?? '';
$returnQuery = trim(urldecode($returnEncoded));
$returnQuery = $returnQuery === '' ? 'm=pessoas&a=list' : $returnQuery;
$returnUrl = BASE_URL . 'index.php?' . $returnQuery;

$pessoa = [
  'id' => 0,
  'nome' => '',
  'razao_social' => '',
  'vendedor' => '',
  'nascimento' => null,
  'cpf_cnpj' => '',
  'rg_ie' => '',
  'nacionalidade' => '',
  'local_nascimento' => '',
  'estado_civil' => '',
  'escolaridade' => '',
  'religiao' => '',
  'titulo' => '',
  'profissao_atual' => '',
  'filiacao' => '',
  'email' => '',
  'facebook' => '',
  'twitter' => '',
  'cep' => '',
  'endereco' => '',
  'numero' => '',
  'complemento' => '',
  'bairro' => '',
  'cidade' => '',
  'estado' => '',
  'obs' => '',
  'aluno_unidade_fpolis' => '0',
  'aluno_outras_unidades' => '0',
  'professor_fpolis' => '0',
  'professor_unidades' => '0',
  'unidade' => '0',
  'simposio_2021' => '0',
  'tipo_simposio_2021' => '',
  'interessado_no_curso' => '0',
  'vendedor_comissionado' => '',
  'ciclo_de_palestras' => '0',
  'cliente' => '0',
  'fornecedor' => '0',
  'controle' => '0',
  'eh_usuario' => 0,
  'login' => '',
  'perfil' => 'CLIENTE'
];

if ($id > 0 || $copyFrom > 0) {
  $lookupId = $id > 0 ? $id : $copyFrom;
  $sql = "SELECT * FROM 101_pessoas WHERE id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':id' => $lookupId]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($row) {
    $pessoa = $row;
    if ($id === 0 && $copyFrom > 0) {
      $pessoa['id'] = 0;
    }
  } else {
    echo '<div class="alert alert-danger">Pessoa não encontrada.</div>';
  }
}


$perfis = [
  'SUPERADMIN',
  'ADMIN',
  'PROFESSOR',
  'ALUNO'
];

// Log de acesso ao formulário
$acaoForm = $id > 0 ? 'Editar' : ($copyFrom > 0 ? 'Copiar' : 'Novo');
log_event($pdo, 'pessoas', 'ACESSO', $acaoForm . ' formulário pessoa' . ($id > 0 ? ' ID ' . $id : ($copyFrom > 0 ? ' a partir de ' . $copyFrom : '')));

// Fetch Related Data for Tabs (only if Editing)
$financeiroList = [];
$recorrenteList = [];
$servicosList = [];
$anotacoesList = [];
$tarefasList = [];
$emailsAdicionais = [];
$fonesAdicionais = [];
$hasPessoasFoneTable = tableExists('pessoas_fone');


$activeTab = $_GET['tab'] ?? 'dados';

// Fetch Aluno data
$isAluno = false;
$virculosAlunos = [];
if ($id > 0) {
  $stmtAlunos = $pdo->prepare("
        SELECT a.*, t.nome as turma_nome 
        FROM 104_alunos a 
        JOIN 104_turmas t ON a.turma = t.id 
        WHERE a.aluno = :id
    ");
  $stmtAlunos->execute([':id' => $id]);
  $virculosAlunos = $stmtAlunos->fetchAll(PDO::FETCH_ASSOC);
  $isAluno = count($virculosAlunos) > 0 || ($pessoa['perfil'] ?? '') === 'ALUNO';
}

$turmasAtivas = [];
if (current_user_is_at_least_admin()) {
  $turmasAtivas = $pdo->query("SELECT id, nome FROM 104_turmas ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
}
?>
<?php
$isContextoUsuario = !empty($pessoa['login']) || strpos($returnQuery, 'marcador=usuario') !== false;
$termoPrincipal = $isContextoUsuario ? 'usuário' : 'pessoa';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">
    <?php echo $id > 0 ? 'Editar ' . $termoPrincipal . ': ' . htmlspecialchars($pessoa['nome'] ?? '') : 'Novo ' . $termoPrincipal; ?>
  </h4>
  <a href="<?php echo $returnUrl; ?>" class="btn btn-outline-secondary btn-sm">Voltar</a>
</div>

<!-- Modais -->
<div class="modal fade" id="modalEmailAdicional" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalEmailTitle">Gerenciar E-mail</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="email_id" value="0">
        <div class="mb-3">
          <label class="form-label">E-mail</label>
          <input type="email" id="email_campo" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Descrição (ex: Trabalho, Pessoal)</label>
          <input type="text" id="email_descricao" class="form-control">
        </div>
        <div class="mb-3 form-check">
          <input type="checkbox" id="email_is_padrao" class="form-check-input">
          <label class="form-check-label" for="email_is_padrao">E-mail Principal</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" onclick="salvarEmailAdicional()">Salvar</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalEnroll" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalEnrollTitle">Nova Matrícula</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="enroll_id" value="0">
        <div class="mb-3">
          <label class="form-label">Turma</label>
          <select id="enroll_turma" class="form-select select2-basic">
            <option value="">Selecione...</option>
            <?php foreach ($turmasAtivas as $t): ?>
              <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['nome']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Status</label>
            <input type="text" id="enroll_status" class="form-control" value="Ativo">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Tipo</label>
            <input type="text" id="enroll_tipo" class="form-control">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="btn-do-enroll">Efetivar Matrícula</button>
      </div>
    </div>
  </div>
</div>

<form method="post" action="<?php echo $baseHref; ?>?m=pessoas&a=save" enctype="multipart/form-data">
  <input type="hidden" name="return" value="<?php echo htmlspecialchars($returnEncoded ?? ''); ?>">
  <input type="hidden" name="id" value="<?php echo (int) $pessoa['id']; ?>">

  <?php if ($id > 0): ?>
    <ul class="nav nav-tabs mb-3" id="pessoaFormTabs" role="tablist">
      <li class="nav-item">
        <button class="nav-link <?php echo $activeTab === 'dados' ? 'active' : ''; ?>" id="dados-tab" data-bs-toggle="tab"
          data-bs-target="#dados" type="button" role="tab">
          Dados Cadastrais
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link <?php echo $activeTab === 'aluno' ? 'active' : ''; ?>" id="aluno-tab" data-bs-toggle="tab"
          data-bs-target="#aluno" type="button" role="tab">
          Aluno <?php echo count($virculosAlunos) > 0 ? '(' . count($virculosAlunos) . ')' : ''; ?>
        </button>
      </li>
    </ul>

    <div class="tab-content" id="pessoaFormContent">
      <div class="tab-pane fade <?php echo $activeTab === 'dados' ? 'show active' : ''; ?>" id="dados" role="tabpanel">
      <?php else: ?>
        <div id="pessoaFormContent">
          <div id="dados">
          <?php endif; ?>
          <!-- Seção: Cadastro -->
          <div class="card shadow-sm mb-3">
            <div class="card-header bg-light fw-bold">Dados de Cadastro</div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Nome*</label>
                  <input type="text" name="nome" class="form-control" required
                    value="<?php echo htmlspecialchars($pessoa['nome'] ?? ''); ?>" autocomplete="new-nome">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Razão Social</label>
                  <input type="text" name="razao_social" class="form-control"
                    value="<?php echo htmlspecialchars($pessoa['razao_social'] ?? ''); ?>">
                </div>

                <div class="col-md-6">
                  <label class="form-label">Data de Nascimento</label>
                  <input type="date" name="nascimento" class="form-control"
                    value="<?php echo htmlspecialchars($pessoa['nascimento'] ?? ''); ?>">
                </div>

                <div class="col-md-4">
                  <label class="form-label">Telefone 1</label>
                  <input type="text" name="fone_1" class="form-control"
                    value="<?php echo htmlspecialchars($pessoa['fone_1'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Telefone 2</label>
                  <input type="text" name="fone_2" class="form-control"
                    value="<?php echo htmlspecialchars($pessoa['fone_2'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Telefone 3 (WhatsApp)</label>
                  <input type="text" name="fone_3" class="form-control"
                    value="<?php echo htmlspecialchars($pessoa['fone_3'] ?? ''); ?>">
                </div>
              </div>
            </div>
          </div>

          <!-- Seção: Documentação -->
          <div class="card shadow-sm mb-3">
            <div class="card-header bg-light fw-bold">Documentação e Pessoal</div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-3">
                  <label class="form-label">CPF/CNPJ</label>
                  <input type="text" name="cpf_cnpj" class="form-control cpf-cnpj-mask"
                    value="<?php echo htmlspecialchars($pessoa['cpf_cnpj'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                  <label class="form-label">RG/IE</label>
                  <input type="text" name="rg_ie" class="form-control"
                    value="<?php echo htmlspecialchars($pessoa['rg_ie'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                  <label class="form-label">Estado Civil</label>
                  <input type="text" name="estado_civil" class="form-control"
                    value="<?php echo htmlspecialchars($pessoa['estado_civil'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                  <label class="form-label">Escolaridade</label>
                  <input type="text" name="escolaridade" class="form-control"
                    value="<?php echo htmlspecialchars($pessoa['escolaridade'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                  <label class="form-label">Nacionalidade</label>
                  <input type="text" name="nacionalidade" class="form-control"
                    value="<?php echo htmlspecialchars($pessoa['nacionalidade'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                  <label class="form-label">Local Nascimento</label>
                  <input type="text" name="local_nascimento" class="form-control"
                    value="<?php echo htmlspecialchars($pessoa['local_nascimento'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                  <label class="form-label">Religião</label>
                  <input type="text" name="religiao" class="form-control"
                    value="<?php echo htmlspecialchars($pessoa['religiao'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                  <label class="form-label">Título</label>
                  <input type="text" name="titulo" class="form-control"
                    value="<?php echo htmlspecialchars($pessoa['titulo'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Profissão Atual</label>
                  <input type="text" name="profissao_atual" class="form-control"
                    value="<?php echo htmlspecialchars($pessoa['profissao_atual'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Filiação</label>
                  <input type="text" name="filiacao" class="form-control"
                    value="<?php echo htmlspecialchars($pessoa['filiacao'] ?? ''); ?>">
                </div>
              </div>
            </div>
          </div>

          <!-- Seção: Contato e Social -->
          <div class="card shadow-sm mb-3">
            <div class="card-header bg-light fw-bold">Contato e Redes Sociais</div>
            <div class="card-body">
              <div class="row g-3">

                <div class="col-md-4">
                  <label class="form-label">E-mail</label>
                  <input type="email" name="email" class="form-control"
                    value="<?php echo htmlspecialchars($pessoa['email'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Facebook</label>
                  <input type="text" name="facebook" class="form-control"
                    value="<?php echo htmlspecialchars($pessoa['facebook'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Twitter</label>
                  <input type="text" name="twitter" class="form-control"
                    value="<?php echo htmlspecialchars($pessoa['twitter'] ?? ''); ?>">
                </div>
              </div>
            </div>
          </div>

          <!-- Seção: Endereço -->
          <div class="card shadow-sm mb-3">
            <div class="card-header bg-light fw-bold">Endereço</div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-2">
                  <label class="form-label">CEP</label>
                  <input type="text" name="cep" class="form-control" maxlength="9"
                    value="<?php echo htmlspecialchars($pessoa['cep'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Endereço</label>
                  <input type="text" name="endereco" class="form-control"
                    value="<?php echo htmlspecialchars($pessoa['endereco'] ?? ''); ?>">
                </div>
                <div class="col-md-2">
                  <label class="form-label">Número</label>
                  <input type="text" name="numero" class="form-control"
                    value="<?php echo htmlspecialchars($pessoa['numero'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Complemento</label>
                  <input type="text" name="complemento" class="form-control"
                    value="<?php echo htmlspecialchars($pessoa['complemento'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Bairro</label>
                  <input type="text" name="bairro" class="form-control"
                    value="<?php echo htmlspecialchars($pessoa['bairro'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Cidade</label>
                  <input type="text" name="cidade" class="form-control"
                    value="<?php echo htmlspecialchars($pessoa['cidade'] ?? ''); ?>">
                </div>
                <div class="col-md-2">
                  <label class="form-label">Estado</label>
                  <input type="text" name="estado" class="form-control"
                    value="<?php echo htmlspecialchars($pessoa['estado'] ?? ''); ?>">
                </div>
              </div>
            </div>
          </div>

          <!-- Seção: Etiquetas e Auditoria -->
          <div class="card shadow-sm mb-3">
            <div class="card-header bg-light fw-bold">Etiquetas e Auditoria</div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-12">
                  <label class="form-label">Observações</label>
                  <textarea name="obs" class="form-control"
                    rows="2"><?php echo htmlspecialchars($pessoa['obs'] ?? ''); ?></textarea>
                </div>

                <?php if ($id > 0 && (!empty($pessoa['inclusao']) || !empty($pessoa['alteracao']))): ?>
                  <div class="col-md-6 mt-3">
                    <label class="form-label small text-muted">Data de Inclusão</label>
                    <input type="text" class="form-control form-control-sm" disabled
                      value="<?php echo !empty($pessoa['inclusao']) ? date('d/m/Y H:i', strtotime($pessoa['inclusao'])) : ''; ?>">
                  </div>
                  <div class="col-md-6 mt-3">
                    <label class="form-label small text-muted">Última Alteração</label>
                    <input type="text" class="form-control form-control-sm" disabled
                      value="<?php echo !empty($pessoa['alteracao']) ? date('d/m/Y H:i', strtotime($pessoa['alteracao'])) : ''; ?>">
                  </div>
                  <?php
                endif; ?>

                <div class="col-md-6">
                  <div class="fw-bold mb-2">Relacionamentos</div>
                  <div class="form-check">
                    <input type="hidden" name="cliente" value="0">
                    <input class="form-check-input" type="checkbox" id="check_cliente" name="cliente" value="1" <?php echo ($pessoa['cliente'] ?? '') === '1' ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="check_cliente">Cliente</label>
                  </div>
                  <div class="form-check">
                    <input type="hidden" name="fornecedor" value="0">
                    <input class="form-check-input" type="checkbox" id="check_fornecedor" name="fornecedor" value="1"
                      <?php echo ($pessoa['fornecedor'] ?? '') === '1' ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="check_fornecedor">Fornecedor</label>
                  </div>

                </div>

                <div class="col-md-6">
                  <div class="fw-bold mb-2">Eventos e Controle</div>
                  <div class="form-check">
                    <input type="hidden" name="interessado_no_curso" value="0">
                    <input class="form-check-input" type="checkbox" id="int_curso" name="interessado_no_curso" value="1"
                      <?php echo ($pessoa['interessado_no_curso'] ?? '') === '1' ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="int_curso">Interessado no Curso</label>
                  </div>

                </div>

                <div class="col-md-12 border-top pt-3">
                  <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="eh_usuario" name="eh_usuario" value="1" <?php echo !empty($pessoa['login']) ? 'checked' : ''; ?>>
                    <label class="form-check-label fw-bold" for="eh_usuario">Acesso ao Sistema</label>
                  </div>
                  <div id="area_acesso_sistema" class="row g-2"
                    style="<?php echo !empty($pessoa['login']) ? '' : 'display:none;'; ?>">
                    <div class="col-md-4">
                      <label class="form-label small">Login</label>
                      <input type="text" name="login" class="form-control"
                        value="<?php echo htmlspecialchars($pessoa['login'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                      <label class="form-label small">Senha <?php echo $id > 0 ? '(vazio p/ manter)' : ''; ?></label>
                      <input type="password" name="senha" class="form-control">
                    </div>
                    <div class="col-md-4">
                      <label class="form-label small">Perfil</label>
                      <select name="perfil" class="form-select">
                        <?php foreach ($perfis as $pf): ?>
                          <option value="<?php echo $pf; ?>" <?php echo ($pessoa['perfil'] ?? '') === $pf ? 'selected' : ''; ?>>
                            <?php echo $pf; ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <?php if ($id > 0): ?>
          <div class="tab-pane fade <?php echo $activeTab === 'aluno' ? 'show active' : ''; ?>" id="aluno"
            role="tabpanel">
            <div class="card shadow-sm">
              <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <span class="fw-bold">Matrículas em Turmas</span>
                <button type="button" class="btn btn-primary btn-sm" onclick="abrirModalMatricula()">
                  <i class="bi bi-plus-lg"></i> Matricular em Turma
                </button>
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-hover mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>Turma</th>
                        <th>Status</th>
                        <th>Tipo</th>
                        <th>Ações</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (empty($virculosAlunos)): ?>
                        <tr>
                          <td colspan="4" class="text-center py-4 text-muted">
                            Nenhuma matrícula encontrada para esta pessoa.
                          </td>
                        </tr>
                      <?php else: ?>
                        <?php foreach ($virculosAlunos as $v): ?>
                          <tr>
                            <td class="fw-bold"><?php echo htmlspecialchars($v['turma_nome']); ?></td>
                            <td>
                              <span
                                class="badge <?php echo ($v['status'] ?? '') === 'Ativo' ? 'bg-success' : 'bg-secondary'; ?>">
                                <?php echo htmlspecialchars($v['status'] ?? 'N/A'); ?>
                              </span>
                            </td>
                            <td><?php echo htmlspecialchars($v['tipo'] ?? '—'); ?></td>
                            <td>
                              <button type="button" class="btn btn-sm btn-outline-primary py-0"
                                onclick="editarMatricula(<?php echo $v['id']; ?>, <?php echo $v['turma']; ?>, '<?php echo $v['status']; ?>', '<?php echo $v['tipo']; ?>')">
                                <i class="bi bi-pencil"></i>
                              </button>
                              <button type="button" class="btn btn-sm btn-outline-danger py-0 ms-1"
                                onclick="excluirMatricula(<?php echo $v['id']; ?>)">
                                <i class="bi bi-trash"></i>
                              </button>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        <?php endif; ?>

      </div> <!-- Closing the tab-content div -->

      <div class="text-end mt-3">
        <button type="submit" class="btn btn-primary">
          Salvar
        </button>
      </div>
</form>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const cepInput = document.querySelector('input[name="cep"]');
    const enderecoInput = document.querySelector('input[name="endereco"]');
    const bairroInput = document.querySelector('input[name="bairro"]');
    const cidadeInput = document.querySelector('input[name="cidade"]');
    const estadoInput = document.querySelector('input[name="estado"]');

    if (!cepInput) return;

    const maskCep = (value) => {
      const digits = value.replace(/\D/g, '').slice(0, 8);
      if (digits.length > 5) {
        return digits.slice(0, 5) + '-' + digits.slice(5);
      }
      return digits;
    };

    cepInput.addEventListener('input', (e) => {
      const masked = maskCep(e.target.value);
      if (e.target.value !== masked) {
        e.target.value = masked;
      }
    });

    cepInput.addEventListener('blur', () => {
      const cepDigits = (cepInput.value || '').replace(/\D/g, '');
      if (cepDigits.length !== 8) {
        return;
      }
      fetch(`https://viacep.com.br/ws/${cepDigits}/json/`)
        .then((resp) => resp.ok ? resp.json() : Promise.reject())
        .then((data) => {
          if (!data || data.erro) return;
          if (enderecoInput) enderecoInput.value = data.logradouro || '';
          if (bairroInput) bairroInput.value = data.bairro || '';
          if (cidadeInput) cidadeInput.value = data.localidade || '';
          if (estadoInput) estadoInput.value = data.uf || '';
        })
        .catch(() => {
          // Silencia erros de rede/CEP inválido
        });
    });

    // Máscara de telefone removida: os campos aceitam números puros.
  });
</script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const chk = document.getElementById('eh_usuario');
    const area = document.getElementById('area_acesso_sistema');

    if (chk && area) {
      chk.addEventListener('change', function () {
        area.style.display = this.checked ? '' : 'none';
      });
    }
  });
</script>



<script>
  document.addEventListener('DOMContentLoaded', function () {
    // Tab persistence
    var tabButtons = document.querySelectorAll('#pessoaFormTabs button[data-bs-toggle="tab"]');
    tabButtons.forEach(function (button) {
      button.addEventListener('shown.bs.tab', function (event) {
        var tabId = event.target.getAttribute('data-bs-target').replace('#', '');
        var url = new URL(window.location);
        url.searchParams.set('tab', tabId);
        window.history.replaceState({}, '', url);
      });
    });

    // Select2 basic
    $('.select2-basic').select2({
      theme: 'bootstrap-5',
      dropdownParent: $('#modalEnroll'),
      width: '100%'
    });
  });

  let modalEnroll;
  document.addEventListener('DOMContentLoaded', function () {
    modalEnroll = new bootstrap.Modal(document.getElementById('modalEnroll'));
  });

  function abrirModalMatricula() {
    document.getElementById('enroll_id').value = '0';
    $('#enroll_turma').val('').trigger('change');
    document.getElementById('enroll_status').value = 'Ativo';
    document.getElementById('enroll_tipo').value = '';
    document.getElementById('modalEnrollTitle').innerText = 'Nova Matrícula';
    modalEnroll.show();
  }

  function editarMatricula(id, turma, status, tipo) {
    document.getElementById('enroll_id').value = id;
    $('#enroll_turma').val(turma).trigger('change');
    document.getElementById('enroll_status').value = status;
    document.getElementById('enroll_tipo').value = tipo;
    document.getElementById('modalEnrollTitle').innerText = 'Editar Matrícula';
    modalEnroll.show();
  }

  function excluirMatricula(id) {
    if (!confirm('Deseja realmente excluir esta matrícula?')) return;

    $.ajax({
      url: 'index.php?m=alunos&a=ajax_delete',
      method: 'POST',
      dataType: 'json',
      data: { id: id },
      success: function (response) {
        if (response.success) {
          window.location.reload();
        } else {
          alert('Erro: ' + (response.error || 'Erro desconhecido.'));
        }
      },
      error: function () {
        alert('Erro ao excluir matrícula.');
      }
    });
  }

  // Handle Enrollment
  $('#btn-do-enroll').on('click', function () {
    const turmaId = $('#enroll_turma').val();
    const status = $('#enroll_status').val();
    const tipo = $('#enroll_tipo').val();
    const enrollId = $('#enroll_id').val();
    const pessoaId = <?php echo $id; ?>;

    if (!turmaId) {
      alert('Por favor, selecione uma turma.');
      return;
    }

    const btn = $(this);
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

    $.ajax({
      url: 'index.php?m=alunos&a=ajax_link',
      method: 'POST',
      dataType: 'json',
      data: {
        id: enrollId,
        aluno: pessoaId,
        turma: turmaId,
        status: status,
        tipo: tipo
      },
      success: function (response) {
        if (response.success) {
          window.location.href = window.location.pathname + window.location.search + '&tab=aluno';
        } else {
          alert('Erro: ' + (response.error || 'Erro desconhecido.'));
          btn.prop('disabled', false).text('Efetivar Matrícula');
        }
      },
      error: function (xhr) {
        let error = 'Erro ao processar matrícula.';
        if (xhr.status === 409) error = 'Esta pessoa já está matriculada nesta turma.';
        alert(error);
        btn.prop('disabled', false).text('Efetivar Matrícula');
      }
    });
  });
  });
</script>

<script>
  let modalEmail;
  document.addEventListener('DOMContentLoaded', function () {
    modalEmail = new bootstrap.Modal(document.getElementById('modalEmailAdicional'));
  });

  function abrirModalEmail() {
    document.getElementById('email_id').value = '0';
    document.getElementById('email_campo').value = '';
    document.getElementById('email_descricao').value = '';
    document.getElementById('email_is_padrao').checked = false;
    document.getElementById('modalEmailTitle').innerText = 'Adicionar E-mail';
    modalEmail.show();
  }

  function editarEmailAdicional(id, email, desc, isPadrao) {
    document.getElementById('email_id').value = id;
    document.getElementById('email_campo').value = email;
    document.getElementById('email_descricao').value = desc;
    document.getElementById('email_is_padrao').checked = !!isPadrao;
    document.getElementById('modalEmailTitle').innerText = 'Editar E-mail';
    modalEmail.show();
  }

</script>

<!-- Lembre-se de ajustar o save.php para limpar os dados de login se 'eh_usuario' não estiver marcado. -->