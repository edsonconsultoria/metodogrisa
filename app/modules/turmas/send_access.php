<?php
// app/modules/turmas/send_access.php
// Gera/atualiza login+senha de aluno e redireciona para WhatsApp com boas-vindas.

require_once __DIR__ . '/../../config/config.php';

if (!current_user_is_at_least_admin()) {
    die('Acesso negado.');
}

$pessoaId = isset($_GET['pessoa_id']) ? (int) $_GET['pessoa_id'] : 0;
$turmaId  = isset($_GET['turma_id'])  ? (int) $_GET['turma_id']  : 0;
$returnUrl = isset($_GET['return']) ? $_GET['return'] : (BASE_URL . 'index.php?m=turmas&a=form&id=' . $turmaId . '&tab=alunos');

function erro_redirect(string $msg, string $url): void {
    header('Location: ' . $url . '&msg_erro=' . urlencode($msg));
    exit;
}

if ($pessoaId <= 0) {
    erro_redirect('ID inválido.', $returnUrl);
}

// ── Busca dados da pessoa ─────────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT id, nome, fone_1, login FROM 101_pessoas WHERE id = :id");
$stmt->execute([':id' => $pessoaId]);
$pessoa = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pessoa) {
    erro_redirect('Pessoa não encontrada.', $returnUrl);
}

if (!$pessoa['fone_1']) {
    erro_redirect('Aluno sem telefone cadastrado. Cadastre um telefone antes de enviar.', $returnUrl);
}

// ── Gera login único baseado no nome ─────────────────────────────────────────
function gerar_login_base(string $nome): string {
    $nome   = mb_strtolower(trim($nome));
    $nome   = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nome);
    $nome   = preg_replace('/[^a-z0-9 ]/', '', $nome);
    $partes = array_filter(explode(' ', $nome));
    if (count($partes) >= 2) {
        $base = reset($partes) . '.' . end($partes);
    } else {
        $base = implode('', $partes);
    }
    return substr($base, 0, 30);
}

if ($pessoa['login']) {
    $login = $pessoa['login'];
} else {
    global $pdo;
    $base   = gerar_login_base($pessoa['nome']);
    $login  = $base;
    $sufixo = 2;
    while (true) {
        $chk = $pdo->prepare("SELECT id FROM 101_pessoas WHERE login = :l AND id != :id LIMIT 1");
        $chk->execute([':l' => $login, ':id' => $pessoaId]);
        if (!$chk->fetch()) break;
        $login = $base . $sufixo++;
    }
}

// ── Gera senha: 6 chars — maiúscula + minúscula + números + especial ─────────
function gerar_senha(): string {
    $letras   = 'abcdefghjkmnpqrstuvwxyz';
    $maiusc   = 'ABCDEFGHJKMNPQRSTUVWXYZ';
    $numeros  = '23456789';
    $especiais = '@#!$%&';

    $senha = [
        $letras[random_int(0, strlen($letras) - 1)],
        $maiusc[random_int(0, strlen($maiusc) - 1)],
        $numeros[random_int(0, strlen($numeros) - 1)],
        $numeros[random_int(0, strlen($numeros) - 1)],
        $especiais[random_int(0, strlen($especiais) - 1)],
        $letras[random_int(0, strlen($letras) - 1)],
    ];
    shuffle($senha);
    return implode('', $senha);
}

$novaSenha = gerar_senha();
$senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);

// ── Salva no banco ────────────────────────────────────────────────────────────
try {
    $temAlteracao = columnExists('101_pessoas', 'alteracao');
    $alteracaoSql = $temAlteracao ? ', alteracao = NOW()' : '';

    $pdo->prepare("
        UPDATE 101_pessoas
        SET eh_usuario  = 1,
            login       = :login,
            senha_hash  = :hash,
            perfil      = 'ALUNO'
            $alteracaoSql
        WHERE id = :id
    ")->execute([':login' => $login, ':hash' => $senhaHash, ':id' => $pessoaId]);

    log_event($pdo, '101_pessoas', 'ACESSO_ENVIADO',
        "Login '$login' e nova senha enviados via WhatsApp para ID $pessoaId ({$pessoa['nome']})");

} catch (PDOException $e) {
    erro_redirect('Erro ao salvar: ' . $e->getMessage(), $returnUrl);
}

// ── Monta mensagem WhatsApp ───────────────────────────────────────────────────
$proto  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$portal = $proto . '://' . $_SERVER['HTTP_HOST'] . rtrim(BASE_URL, '/') . '/login.php';

$nomeFirst = explode(' ', trim($pessoa['nome']))[0];

$turmaInfo = '';
if ($turmaId > 0) {
    $stmtT = $pdo->prepare("SELECT nome FROM 104_turmas WHERE id = :id");
    $stmtT->execute([':id' => $turmaId]);
    $turma = $stmtT->fetch(PDO::FETCH_ASSOC);
    if ($turma) $turmaInfo = "\n*Turma:* " . $turma['nome'];
}

$mensagem = "Olá, *{$nomeFirst}*!\n\n"
    . "Seja bem-vindo(a) à plataforma *Método Grisa*!\n"
    . "Seu acesso ao portal foi criado com sucesso."
    . "{$turmaInfo}\n\n"
    . "*Seus dados de acesso:*\n"
    . "• *Login:* {$login}\n"
    . "• *Senha:* {$novaSenha}\n\n"
    . "*Acesse o portal:*\n"
    . "{$portal}\n\n"
    . "_Recomendamos que altere sua senha no primeiro acesso._";

$fone = preg_replace('/\D/', '', $pessoa['fone_1']);
if (strlen($fone) === 10 || strlen($fone) === 11) {
    $fone = '55' . $fone;
}

$waUrl = 'https://wa.me/' . $fone . '?text=' . rawurlencode($mensagem);

header('Location: ' . $waUrl);
exit;
