<?php
/**
 * Página pública de inscrição em turma.
 * URL: /inscricao.php?turma=ID&key=HASH
 *
 * Não exige login. A chave criptográfica no parâmetro "key" é gerada
 * deterministicamente a partir do ID da turma + salt interno.
 */

ob_start();
require_once __DIR__ . '/app/config/config.php';
ob_clean();

define('INSCRICAO_SALT', 'mg_inscricao_turma_2024_grisa_secret');

function inscricao_key(int $turmaId): string {
    return hash('sha256', INSCRICAO_SALT . '_' . $turmaId);
}

$turmaId = isset($_GET['turma']) ? (int) $_GET['turma'] : 0;
$keyParam = trim($_GET['key'] ?? '');

// Validação da chave
$keyValida = $turmaId > 0 && hash_equals(inscricao_key($turmaId), $keyParam);

$turma = null;
if ($keyValida) {
    $stmt = $pdo->prepare("SELECT id, nome FROM 104_turmas WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $turmaId]);
    $turma = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$turma) $keyValida = false;
}

// ── PROCESSAMENTO DO FORMULÁRIO ──────────────────────────────────────────────
$msgSucesso = '';
$msgErro    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $keyValida) {
    $postTurmaId = (int) ($_POST['turma_id'] ?? 0);
    $postKey     = trim($_POST['key'] ?? '');

    if (!hash_equals(inscricao_key($postTurmaId), $postKey) || $postTurmaId !== $turmaId) {
        $msgErro = 'Chave de acesso inválida.';
    } else {
        $nome            = formatarNome(trim($_POST['nome'] ?? ''));
        $cpf_cnpj        = preg_replace('/\D/', '', trim($_POST['cpf_cnpj'] ?? ''));
        $nascimento      = trim($_POST['nascimento'] ?? '') ?: null;
        $email           = trim($_POST['email'] ?? '') ?: null;
        $fone_1          = trim($_POST['fone_1'] ?? '') ?: null;
        $fone_2          = trim($_POST['fone_2'] ?? '') ?: null;
        $cep             = trim($_POST['cep'] ?? '') ?: null;
        $endereco        = formatarNome(trim($_POST['endereco'] ?? ''));
        $numero          = trim($_POST['numero'] ?? '') ?: null;
        $complemento     = formatarNome(trim($_POST['complemento'] ?? ''));
        $bairro          = formatarNome(trim($_POST['bairro'] ?? ''));
        $cidade          = formatarNome(trim($_POST['cidade'] ?? ''));
        $estado          = strtoupper(trim($_POST['estado'] ?? '')) ?: null;
        $estado_civil    = trim($_POST['estado_civil'] ?? '') ?: null;
        $escolaridade    = trim($_POST['escolaridade'] ?? '') ?: null;
        $nacionalidade   = trim($_POST['nacionalidade'] ?? '') ?: null;
        $local_nascimento= trim($_POST['local_nascimento'] ?? '') ?: null;
        $religiao        = trim($_POST['religiao'] ?? '') ?: null;
        $profissao_atual = trim($_POST['profissao_atual'] ?? '') ?: null;
        $diploma         = trim($_POST['diploma'] ?? 'Pós Graduação');

        if ($nome === '') {
            $msgErro = 'O nome é obrigatório.';
        } elseif ($cpf_cnpj === '') {
            $msgErro = 'CPF/CNPJ é obrigatório.';
        } else {
            // Formata CPF/CNPJ para exibição
            $cpfFormatado = strlen($cpf_cnpj) === 14
                ? preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $cpf_cnpj)
                : preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $cpf_cnpj);

            // Verifica se pessoa já existe pelo CPF/CNPJ
            $stmtCheck = $pdo->prepare("SELECT id FROM 101_pessoas WHERE cpf_cnpj = :cpf LIMIT 1");
            $stmtCheck->execute([':cpf' => $cpfFormatado]);
            $pessoaExistente = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            // Detecta colunas opcionais
            $temAlteracao = columnExists('101_pessoas', 'alteracao');
            $temInclusao  = columnExists('101_pessoas', 'inclusao');
            $temInclusaoAluno = columnExists('104_alunos', 'inclusao');

            $params = [
                ':nome'             => $nome,
                ':nascimento'       => $nascimento,
                ':email'            => $email,
                ':fone_1'           => $fone_1,
                ':fone_2'           => $fone_2,
                ':cep'              => $cep,
                ':endereco'         => $endereco !== '' ? $endereco : null,
                ':numero'           => $numero,
                ':complemento'      => $complemento !== '' ? $complemento : null,
                ':bairro'           => $bairro !== '' ? $bairro : null,
                ':cidade'           => $cidade !== '' ? $cidade : null,
                ':estado'           => $estado,
                ':estado_civil'     => $estado_civil,
                ':escolaridade'     => $escolaridade,
                ':nacionalidade'    => $nacionalidade,
                ':local_nascimento' => $local_nascimento,
                ':religiao'         => $religiao,
                ':profissao_atual'  => $profissao_atual,
            ];

            try {
                if ($pessoaExistente) {
                    $pessoaId = $pessoaExistente['id'];
                    $alteracaoSql = $temAlteracao ? ', alteracao=NOW()' : '';
                    $upd = $pdo->prepare("
                        UPDATE 101_pessoas SET
                            nome=:nome, nascimento=:nascimento, email=:email,
                            fone_1=:fone_1, fone_2=:fone_2,
                            cep=:cep, endereco=:endereco, numero=:numero,
                            complemento=:complemento, bairro=:bairro,
                            cidade=:cidade, estado=:estado,
                            estado_civil=:estado_civil, escolaridade=:escolaridade,
                            nacionalidade=:nacionalidade, local_nascimento=:local_nascimento,
                            religiao=:religiao, profissao_atual=:profissao_atual
                            $alteracaoSql
                        WHERE id=:id
                    ");
                    $upd->execute($params + [':id' => $pessoaId]);
                } else {
                    $inclusaoCols = $temInclusao ? ', inclusao' : '';
                    $inclusaoVals = $temInclusao ? ', NOW()' : '';
                    $ins = $pdo->prepare("
                        INSERT INTO 101_pessoas (nome, cpf_cnpj, nascimento, email,
                            fone_1, fone_2, cep, endereco, numero, complemento,
                            bairro, cidade, estado,
                            estado_civil, escolaridade, nacionalidade,
                            local_nascimento, religiao, profissao_atual $inclusaoCols)
                        VALUES (:nome, :cpf, :nascimento, :email,
                            :fone_1, :fone_2, :cep, :endereco, :numero, :complemento,
                            :bairro, :cidade, :estado,
                            :estado_civil, :escolaridade, :nacionalidade,
                            :local_nascimento, :religiao, :profissao_atual $inclusaoVals)
                    ");
                    $ins->execute($params + [':cpf' => $cpfFormatado]);
                    $pessoaId = (int) $pdo->lastInsertId();
                }
            } catch (PDOException $e) {
                $msgErro = 'Erro ao salvar dados pessoais. Tente novamente. (' . $e->getMessage() . ')';
                error_log('[inscricao] PDO pessoa: ' . $e->getMessage());
            }

            if (!$msgErro) {
                // Vincula à turma
                $diplomasPermitidos = ['Pós Graduação', 'Curso Livre'];
                if (!in_array($diploma, $diplomasPermitidos, true)) $diploma = 'Pós Graduação';

                try {
                    $stmtAluno = $pdo->prepare("
                        SELECT id FROM 104_alunos WHERE turma = :turma AND aluno = :aluno LIMIT 1
                    ");
                    $stmtAluno->execute([':turma' => $turmaId, ':aluno' => $pessoaId]);
                    $jaInscrito = $stmtAluno->fetch(PDO::FETCH_ASSOC);

                    if (!$jaInscrito) {
                        $incAluno = $temInclusaoAluno ? ', inclusao' : '';
                        $incAlunoVal = $temInclusaoAluno ? ', NOW()' : '';
                        $pdo->prepare("
                            INSERT INTO 104_alunos (turma, aluno, diploma $incAluno)
                            VALUES (:turma, :aluno, :diploma $incAlunoVal)
                        ")->execute([':turma' => $turmaId, ':aluno' => $pessoaId, ':diploma' => $diploma]);
                        $msgSucesso = "Inscrição realizada com sucesso! Bem-vindo(a), <strong>" . htmlspecialchars($nome) . "</strong>.";
                    } else {
                        $msgSucesso = "Seus dados foram atualizados. Você já estava inscrito(a) nesta turma, <strong>" . htmlspecialchars($nome) . "</strong>.";
                    }
                } catch (PDOException $e) {
                    $msgErro = 'Erro ao vincular à turma. (' . $e->getMessage() . ')';
                    error_log('[inscricao] PDO aluno: ' . $e->getMessage());
                }
            }

            if ($msgSucesso) {
                log_event($pdo, '104_alunos', 'INSCRICAO_PUBLICA', json_encode([
                    'turma' => $turmaId, 'pessoa' => $pessoaId ?? null, 'nome' => $nome
                ], JSON_UNESCAPED_UNICODE));
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscrição<?php echo $turma ? ' — ' . htmlspecialchars($turma['nome']) : ''; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; }

        body {
            background: #f0f4f9;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            font-size: 15px;
        }

        .container { padding-left: 12px; padding-right: 12px; }

        .card-inscricao {
            max-width: 720px;
            margin: 24px auto;
            border: none;
            border-radius: 14px;
            box-shadow: 0 4px 28px rgba(0,0,0,.10);
            overflow: hidden;
        }

        .card-header-logo {
            background: linear-gradient(135deg, #1a3a5c 0%, #2d6a9f 100%);
            color: #fff;
            padding: 24px 28px;
        }
        .card-header-logo h5 { font-size: 1.15rem; font-weight: 700; margin-bottom: 4px; }
        .card-header-logo p  { font-size: .9rem; opacity: .82; margin: 0; }

        .card-body { padding: 24px 20px; }
        @media (min-width: 576px) { .card-body { padding: 28px 32px; } }

        /* Labels */
        .form-label { font-size: .85rem; font-weight: 600; margin-bottom: 4px; color: #374151; }

        /* Inputs */
        .form-control, .form-select {
            font-size: .92rem;
            padding: 9px 12px;
            border-radius: 8px;
            border-color: #d1d5db;
        }
        .form-control:focus, .form-select:focus {
            border-color: #2d6a9f;
            box-shadow: 0 0 0 3px rgba(45,106,159,.15);
        }
        .form-control[readonly] { background: #f3f4f6; color: #6b7280; }

        /* Input-group busca CPF */
        .input-group .form-control-lg { font-size: 1rem; }
        .input-group .btn { border-radius: 0 8px 8px 0 !important; padding: 0 18px; }

        /* Botões */
        .btn { border-radius: 8px; font-size: .9rem; font-weight: 500; }
        .btn-success  { background: #1a7a4a; border-color: #1a7a4a; }
        .btn-success:hover { background: #145f39; border-color: #145f39; }

        /* Seção de passos */
        #step-cpf  { display: block; }
        #step-form { display: none; }

        /* Divider com texto */
        .section-title {
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #9ca3af;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 6px;
            margin: 20px 0 4px;
        }

        /* Feedback de sucesso */
        .sucesso-icon { font-size: 3.5rem; color: #1a7a4a; }

        /* Mobile: stack todos col-md */
        @media (max-width: 575px) {
            .card-inscricao { margin: 12px auto; border-radius: 10px; }
            .card-header-logo { padding: 18px 16px; }
            [class*="col-md-"] { width: 100% !important; flex: 0 0 100% !important; max-width: 100% !important; }
        }
    </style>
</head>
<body>
<div class="container py-2 py-sm-4">
    <?php if (!$keyValida): ?>
        <div class="card card-inscricao">
            <div class="card-header-logo text-center">
                <h4 class="mb-0"><i class="bi bi-shield-lock me-2"></i>Acesso Inválido</h4>
            </div>
            <div class="card-body text-center py-5">
                <p class="text-muted">O link de inscrição é inválido ou expirou.<br>Solicite um novo link ao administrador.</p>
            </div>
        </div>

    <?php elseif ($msgSucesso): ?>
        <div class="card card-inscricao">
            <div class="card-header-logo text-center">
                <h4 class="mb-0"><i class="bi bi-check-circle me-2"></i>Inscrição Confirmada</h4>
            </div>
            <div class="card-body text-center py-5">
                <i class="bi bi-check-circle-fill sucesso-icon"></i>
                <p class="mt-3 fs-5"><?php echo $msgSucesso; ?></p>
                <p class="text-muted">Turma: <strong><?php echo htmlspecialchars($turma['nome']); ?></strong></p>
            </div>
        </div>

    <?php else: ?>
        <div class="card card-inscricao">
            <div class="card-header-logo">
                <h5 class="mb-1"><i class="bi bi-person-plus me-2"></i>Inscrição na Turma</h5>
                <p class="mb-0 opacity-75"><?php echo htmlspecialchars($turma['nome']); ?></p>
            </div>
            <div class="card-body p-4">
                <?php if ($msgErro): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($msgErro); ?></div>
                <?php endif; ?>

                <!-- ETAPA 1: CPF -->
                <div id="step-cpf">
                    <p class="text-muted mb-3">Digite seu CPF ou CNPJ para começar. Se você já estiver cadastrado, seus dados serão preenchidos automaticamente.</p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">CPF ou CNPJ <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" id="input-cpf-busca" class="form-control form-control-lg" placeholder="000.000.000-00" maxlength="18">
                            <button class="btn btn-primary" type="button" id="btn-buscar-cpf">
                                <span id="btn-buscar-cpf-spinner" class="spinner-border spinner-border-sm d-none" role="status"></span>
                                <span id="btn-buscar-cpf-text"><i class="bi bi-search me-1"></i>Buscar</span>
                            </button>
                        </div>
                        <div id="cpf-feedback" class="form-text text-danger d-none"></div>
                    </div>
                </div>

                <!-- ETAPA 2: FORMULÁRIO COMPLETO -->
                <div id="step-form">
                    <div class="alert alert-info d-none" id="alert-encontrado"></div>

                    <form method="POST" action="inscricao.php?turma=<?php echo $turmaId; ?>&key=<?php echo htmlspecialchars($keyParam); ?>">
                        <input type="hidden" name="turma_id" value="<?php echo $turmaId; ?>">
                        <input type="hidden" name="key" value="<?php echo htmlspecialchars($keyParam); ?>">

                        <div class="row g-3">

                            <div class="col-12"><p class="section-title">Dados Pessoais</p></div>

                            <div class="col-12">
                                <label class="form-label">Nome completo <span class="text-danger">*</span></label>
                                <input type="text" name="nome" id="campo-nome" class="form-control" required>
                            </div>

                            <div class="col-sm-6">
                                <label class="form-label">CPF / CNPJ <span class="text-danger">*</span></label>
                                <input type="text" name="cpf_cnpj" id="campo-cpf_cnpj" class="form-control" readonly>
                            </div>

                            <div class="col-sm-6">
                                <label class="form-label">Data de Nascimento <span class="text-danger">*</span></label>
                                <input type="date" name="nascimento" id="campo-nascimento" class="form-control" required>
                            </div>

                            <div class="col-sm-6">
                                <label class="form-label">Estado Civil <span class="text-danger">*</span></label>
                                <select name="estado_civil" id="campo-estado_civil" class="form-select" required>
                                    <option value="">Selecione...</option>
                                    <option value="Solteiro(a)">Solteiro(a)</option>
                                    <option value="Casado(a)">Casado(a)</option>
                                    <option value="União Estável">União Estável</option>
                                    <option value="Separado(a) Judicialmente">Separado(a) Judicialmente</option>
                                    <option value="Divorciado(a)">Divorciado(a)</option>
                                    <option value="Viúvo(a)">Viúvo(a)</option>
                                </select>
                            </div>

                            <div class="col-sm-6">
                                <label class="form-label">Nacionalidade <span class="text-danger">*</span></label>
                                <input type="text" name="nacionalidade" id="campo-nacionalidade" class="form-control" placeholder="Ex: Brasileira" required>
                            </div>

                            <div class="col-sm-6">
                                <label class="form-label">Local de Nascimento <span class="text-danger">*</span></label>
                                <input type="text" name="local_nascimento" id="campo-local_nascimento" class="form-control" placeholder="Ex: São Paulo - SP" required>
                            </div>

                            <div class="col-sm-6">
                                <label class="form-label">Religião <span class="text-danger">*</span></label>
                                <input type="text" name="religiao" id="campo-religiao" class="form-control" placeholder="Ex: Católica, Evangélica..." required>
                            </div>

                            <div class="col-sm-6">
                                <label class="form-label">Escolaridade <span class="text-danger">*</span></label>
                                <select name="escolaridade" id="campo-escolaridade" class="form-select" required>
                                    <option value="">Selecione...</option>
                                    <option value="Ensino Fundamental Incompleto">Ensino Fundamental Incompleto</option>
                                    <option value="Ensino Fundamental Completo">Ensino Fundamental Completo</option>
                                    <option value="Ensino Médio Incompleto">Ensino Médio Incompleto</option>
                                    <option value="Ensino Médio Completo">Ensino Médio Completo</option>
                                    <option value="Ensino Superior Incompleto">Ensino Superior Incompleto</option>
                                    <option value="Ensino Superior Completo">Ensino Superior Completo</option>
                                    <option value="Pós-Graduação / Especialização">Pós-Graduação / Especialização</option>
                                    <option value="Mestrado">Mestrado</option>
                                    <option value="Doutorado">Doutorado</option>
                                    <option value="Pós-Doutorado">Pós-Doutorado</option>
                                </select>
                            </div>

                            <div class="col-sm-6">
                                <label class="form-label">Profissão Atual <span class="text-danger">*</span></label>
                                <input type="text" name="profissao_atual" id="campo-profissao_atual" class="form-control" placeholder="Ex: Enfermeiro(a)" required>
                            </div>

                            <div class="col-12"><p class="section-title">Contato</p></div>

                            <div class="col-12">
                                <label class="form-label">E-mail <span class="text-danger">*</span></label>
                                <input type="email" name="email" id="campo-email" class="form-control" required>
                            </div>

                            <div class="col-sm-6">
                                <label class="form-label">Telefone / WhatsApp <span class="text-danger">*</span></label>
                                <input type="text" name="fone_1" id="campo-fone_1" class="form-control" placeholder="(00) 00000-0000" required>
                            </div>

                            <div class="col-sm-6">
                                <label class="form-label">Telefone 2</label>
                                <input type="text" name="fone_2" id="campo-fone_2" class="form-control" placeholder="(00) 00000-0000">
                            </div>

                            <div class="col-12"><p class="section-title">Endereço</p></div>

                            <div class="col-sm-4">
                                <label class="form-label">CEP <span class="text-danger">*</span></label>
                                <input type="text" name="cep" id="campo-cep" class="form-control" maxlength="9" placeholder="00000-000" required>
                            </div>

                            <div class="col-sm-8">
                                <label class="form-label">Endereço <span class="text-danger">*</span></label>
                                <input type="text" name="endereco" id="campo-endereco" class="form-control" required>
                            </div>

                            <div class="col-4 col-sm-3">
                                <label class="form-label">Número <span class="text-danger">*</span></label>
                                <input type="text" name="numero" id="campo-numero" class="form-control" required>
                            </div>

                            <div class="col-8 col-sm-5">
                                <label class="form-label">Complemento</label>
                                <input type="text" name="complemento" id="campo-complemento" class="form-control">
                            </div>

                            <div class="col-sm-4">
                                <label class="form-label">Bairro <span class="text-danger">*</span></label>
                                <input type="text" name="bairro" id="campo-bairro" class="form-control" required>
                            </div>

                            <div class="col-sm-8">
                                <label class="form-label">Cidade <span class="text-danger">*</span></label>
                                <input type="text" name="cidade" id="campo-cidade" class="form-control" required>
                            </div>

                            <div class="col-sm-4">
                                <label class="form-label">Estado (UF) <span class="text-danger">*</span></label>
                                <input type="text" name="estado" id="campo-estado" class="form-control" maxlength="2" placeholder="SP" required>
                            </div>

                            <div class="col-12"><p class="section-title">Inscrição</p></div>

                            <div class="col-12">
                                <label class="form-label">Diploma <span class="text-danger">*</span></label>
                                <select name="diploma" id="campo-diploma" class="form-select" required>
                                    <option value="Curso Livre">Curso Livre</option>
                                    <option value="Pós Graduação" selected>Pós Graduação</option>
                                </select>
                            </div>

                        </div>

                        <hr class="my-4">
                        <p class="text-muted" style="font-size:.8rem;">Campos com <span class="text-danger">*</span> são obrigatórios.</p>
                        <div class="d-flex flex-column flex-sm-row gap-2">
                            <button type="button" class="btn btn-outline-secondary" id="btn-voltar-cpf">
                                <i class="bi bi-arrow-left me-1"></i>Voltar
                            </button>
                            <button type="submit" class="btn btn-success flex-grow-1 py-2">
                                <i class="bi bi-person-check me-1"></i>Confirmar Inscrição
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const stepCpf  = document.getElementById('step-cpf');
    const stepForm = document.getElementById('step-form');
    if (!stepCpf || !stepForm) return;

    const inputCpf     = document.getElementById('input-cpf-busca');
    const btnBuscar    = document.getElementById('btn-buscar-cpf');
    const btnBuscarTxt = document.getElementById('btn-buscar-cpf-text');
    const btnBuscarSpin= document.getElementById('btn-buscar-cpf-spinner');
    const cpfFeedback  = document.getElementById('cpf-feedback');
    const alertEncontrado = document.getElementById('alert-encontrado');
    const btnVoltar    = document.getElementById('btn-voltar-cpf');

    // Máscara CPF/CNPJ
    inputCpf.addEventListener('input', function () {
        let v = this.value.replace(/\D/g, '');
        if (v.length <= 11) {
            v = v.replace(/(\d{3})(\d)/, '$1.$2')
                 .replace(/(\d{3})(\d)/, '$1.$2')
                 .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        } else {
            v = v.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2}).*/, '$1.$2.$3/$4-$5');
        }
        this.value = v;
    });

    function mostrarForm(dados, mensagem) {
        stepCpf.style.display  = 'none';
        stepForm.style.display = 'block';

        if (mensagem) {
            alertEncontrado.textContent = mensagem;
            alertEncontrado.classList.remove('d-none');
        }

        const cpfLimpo = inputCpf.value.replace(/\D/g,'');
        const cpfFmt   = cpfLimpo.length === 14
            ? cpfLimpo.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, '$1.$2.$3/$4-$5')
            : cpfLimpo.replace(/^(\d{3})(\d{3})(\d{3})(\d{2})$/, '$1.$2.$3-$4');

        document.getElementById('campo-cpf_cnpj').value        = dados.cpf_cnpj        || cpfFmt;
        document.getElementById('campo-nome').value             = dados.nome             || '';
        document.getElementById('campo-nascimento').value       = dados.nascimento       || '';
        document.getElementById('campo-email').value            = dados.email            || '';
        document.getElementById('campo-fone_1').value           = dados.fone_1           || '';
        document.getElementById('campo-fone_2').value           = dados.fone_2           || '';
        document.getElementById('campo-cep').value              = dados.cep              || '';
        document.getElementById('campo-endereco').value         = dados.endereco         || '';
        document.getElementById('campo-numero').value           = dados.numero           || '';
        document.getElementById('campo-complemento').value      = dados.complemento      || '';
        document.getElementById('campo-bairro').value           = dados.bairro           || '';
        document.getElementById('campo-cidade').value           = dados.cidade           || '';
        document.getElementById('campo-estado').value           = dados.estado           || '';
        document.getElementById('campo-nacionalidade').value    = dados.nacionalidade    || '';
        document.getElementById('campo-local_nascimento').value = dados.local_nascimento || '';
        document.getElementById('campo-religiao').value         = dados.religiao         || '';
        document.getElementById('campo-profissao_atual').value  = dados.profissao_atual  || '';
        // selects
        const selEstadoCivil  = document.getElementById('campo-estado_civil');
        const selEscolaridade = document.getElementById('campo-escolaridade');
        if (dados.estado_civil)  selEstadoCivil.value  = dados.estado_civil;
        if (dados.escolaridade)  selEscolaridade.value = dados.escolaridade;
    }

    btnBuscar.addEventListener('click', function () {
        const cpf = inputCpf.value.replace(/\D/g, '');
        cpfFeedback.classList.add('d-none');
        cpfFeedback.textContent = '';

        if (cpf.length !== 11 && cpf.length !== 14) {
            cpfFeedback.textContent = 'Digite um CPF (11 dígitos) ou CNPJ (14 dígitos) válido.';
            cpfFeedback.classList.remove('d-none');
            return;
        }

        btnBuscarSpin.classList.remove('d-none');
        btnBuscarTxt.classList.add('d-none');
        btnBuscar.disabled = true;

        fetch('app/modules/inscricao/check_cpf.php?cpf=' + encodeURIComponent(cpf))
            .then(r => r.json())
            .then(data => {
                if (data.encontrado) {
                    mostrarForm(data, '✅ Cadastro encontrado! Verifique e atualize seus dados se necessário.');
                } else if (data.receita) {
                    mostrarForm(data.receita, 'ℹ️ Dados encontrados na Receita Federal. Confira e complete o formulário.');
                } else {
                    mostrarForm({}, 'Nenhum cadastro encontrado. Preencha seus dados abaixo.');
                }
            })
            .catch(() => {
                mostrarForm({}, 'Preencha seus dados abaixo.');
            })
            .finally(() => {
                btnBuscarSpin.classList.add('d-none');
                btnBuscarTxt.classList.remove('d-none');
                btnBuscar.disabled = false;
            });
    });

    btnVoltar.addEventListener('click', function () {
        stepForm.style.display = 'none';
        stepCpf.style.display  = 'block';
        alertEncontrado.classList.add('d-none');
    });

    // CEP auto-preenchimento
    document.getElementById('campo-cep').addEventListener('blur', function () {
        const cep = this.value.replace(/\D/g, '');
        if (cep.length !== 8) return;
        fetch('https://brasilapi.com.br/api/cep/v1/' + cep)
            .then(r => r.ok ? r.json() : null)
            .then(d => {
                if (!d) return;
                document.getElementById('campo-endereco').value = d.street  || '';
                document.getElementById('campo-bairro').value   = d.neighborhood || '';
                document.getElementById('campo-cidade').value   = d.city    || '';
                document.getElementById('campo-estado').value   = d.state   || '';
            })
            .catch(() => {});
    });
})();
</script>
</body>
</html>
