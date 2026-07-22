<?php
// app/modules/pessoas/save.php
// Version 1.0.5 - Cleaned up removed columns: ddi, ddd, foto, site, fones, web.

require_once __DIR__ . '/../../config/config.php';

if (!current_user_is_at_least_admin()) {
    die('Acesso negado.');
}

$redirectListUrl = list_state_return_destination('pessoas', 'list');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirectListUrl);
    exit;
}

$hasAlteracaoColumn = columnExists('101_pessoas', 'alteracao');
$hasDataAlteracaoColumn = columnExists('101_pessoas', 'data_alteracao');
$hasInclusaoColumn = columnExists('101_pessoas', 'inclusao');
$hasDataInclusaoColumn = columnExists('101_pessoas', 'data_inclusao');

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

$nome = trim($_POST['nome'] ?? '');
$razaoSocial = trim($_POST['razao_social'] ?? '');
$vendedor = trim($_POST['vendedor'] ?? '');
$nascimento = trim($_POST['nascimento'] ?? '');
if ($nascimento === '')
    $nascimento = null;

$cpf_cnpj = trim($_POST['cpf_cnpj'] ?? '');
$rg_ie = trim($_POST['rg_ie'] ?? '');
$nacionalidade = trim($_POST['nacionalidade'] ?? '');
$local_nascimento = trim($_POST['local_nascimento'] ?? '');
$estado_civil = trim($_POST['estado_civil'] ?? '');
$escolaridade = trim($_POST['escolaridade'] ?? '');
$religiao = trim($_POST['religiao'] ?? '');
$titulo = trim($_POST['titulo'] ?? '');
$profissao_atual = trim($_POST['profissao_atual'] ?? '');
$filiacao = trim($_POST['filiacao'] ?? '');

$email = trim($_POST['email'] ?? '');
$facebook = trim($_POST['facebook'] ?? '');
$twitter = trim($_POST['twitter'] ?? '');

$cep = trim($_POST['cep'] ?? '');
$endereco = trim($_POST['endereco'] ?? '');
$numero = trim($_POST['numero'] ?? '');
$complemento = trim($_POST['complemento'] ?? '');
$bairro = trim($_POST['bairro'] ?? '');
$cidade = trim($_POST['cidade'] ?? '');
$estado = trim($_POST['estado'] ?? '');

$obs = trim($_POST['obs'] ?? '');

$aluno_unidade_fpolis = ($_POST['aluno_unidade_fpolis'] ?? '0') === '1' ? '1' : '0';
$aluno_outras_unidades = ($_POST['aluno_outras_unidades'] ?? '0') === '1' ? '1' : '0';
$professor_fpolis = ($_POST['professor_fpolis'] ?? '0') === '1' ? '1' : '0';
$professor_unidades = ($_POST['professor_unidades'] ?? '0') === '1' ? '1' : '0';
$unidade = ($_POST['unidade'] ?? '0') === '1' ? '1' : '0';
$simposio_2021 = ($_POST['simposio_2021'] ?? '0') === '1' ? '1' : '0';
$tipo_simposio_2021 = trim($_POST['tipo_simposio_2021'] ?? '');
$interessado_no_curso = ($_POST['interessado_no_curso'] ?? '0') === '1' ? '1' : '0';
$vendedor_comissionado = trim($_POST['vendedor_comissionado'] ?? '');
$ciclo_de_palestras = ($_POST['ciclo_de_palestras'] ?? '0') === '1' ? '1' : '0';
$cliente = ($_POST['cliente'] ?? '0') === '1' ? '1' : '0';
$fornecedor = ($_POST['fornecedor'] ?? '0') === '1' ? '1' : '0';
$controle = ($_POST['controle'] ?? '0') === '1' ? '1' : '0';

$eh_usuario = isset($_POST['eh_usuario']) ? 1 : 0;
$login = trim($_POST['login'] ?? '');
$senha = $_POST['senha'] ?? '';
$perfil = $_POST['perfil'] ?? 'CLIENTE';


$nome = formatarNome($nome);
$razaoSocial = $razaoSocial !== '' ? formatarNome($razaoSocial) : '';
$endereco = formatarNome($endereco);
$complemento = formatarNome($complemento);
$bairro = formatarNome($bairro);
$cidade = formatarNome($cidade);

$perfisPermitidos = ['SUPERADMIN', 'ADMIN', 'PROFESSOR', 'ALUNO'];

if ($nome === '') {
    die('Nome é obrigatório.');
}
$cpf_cnpj = $cpf_cnpj === '' ? '' : $cpf_cnpj;

try {
    $fields = [
        'nome' => $nome,
        'razao_social' => $razaoSocial !== '' ? $razaoSocial : null,
        'vendedor' => $vendedor !== '' ? $vendedor : null,
        'nascimento' => $nascimento,
        'cpf_cnpj' => $cpf_cnpj !== '' ? $cpf_cnpj : null,
        'rg_ie' => $rg_ie !== '' ? $rg_ie : null,
        'nacionalidade' => $nacionalidade !== '' ? $nacionalidade : null,
        'local_nascimento' => $local_nascimento !== '' ? $local_nascimento : null,
        'estado_civil' => $estado_civil !== '' ? $estado_civil : null,
        'escolaridade' => $escolaridade !== '' ? $escolaridade : null,
        'religiao' => $religiao !== '' ? $religiao : null,
        'titulo' => $titulo !== '' ? $titulo : null,
        'profissao_atual' => $profissao_atual !== '' ? $profissao_atual : null,
        'filiacao' => $filiacao !== '' ? $filiacao : null,
        'email' => $email !== '' ? $email : null,
        'fone_1' => trim($_POST['fone_1'] ?? '') !== '' ? trim($_POST['fone_1'] ?? '') : null,
        'fone_2' => trim($_POST['fone_2'] ?? '') !== '' ? trim($_POST['fone_2'] ?? '') : null,
        'fone_3' => trim($_POST['fone_3'] ?? '') !== '' ? trim($_POST['fone_3'] ?? '') : null,
        'facebook' => $facebook !== '' ? $facebook : null,
        'twitter' => $twitter !== '' ? $twitter : null,
        'cep' => $cep !== '' ? $cep : null,
        'endereco' => $endereco !== '' ? $endereco : null,
        'numero' => $numero !== '' ? $numero : null,
        'complemento' => $complemento !== '' ? $complemento : null,
        'bairro' => $bairro !== '' ? $bairro : null,
        'cidade' => $cidade !== '' ? $cidade : null,
        'estado' => $estado !== '' ? $estado : null,
        'obs' => $obs !== '' ? $obs : null,
        'aluno_unidade_fpolis' => $aluno_unidade_fpolis,
        'aluno_outras_unidades' => $aluno_outras_unidades,
        'professor_fpolis' => $professor_fpolis,
        'professor_unidades' => $professor_unidades,
        'unidade' => $unidade !== '' ? $unidade : null,
        'simposio_2021' => $simposio_2021,
        'tipo_simposio_2021' => $tipo_simposio_2021 !== '' ? $tipo_simposio_2021 : null,
        'interessado_no_curso' => $interessado_no_curso,
        'vendedor_comissionado' => $vendedor_comissionado !== '' ? $vendedor_comissionado : null,
        'ciclo_de_palestras' => $ciclo_de_palestras,
        'cliente' => $cliente,
        'fornecedor' => $fornecedor,
        'controle' => $controle !== '' ? $controle : null,
        'eh_usuario' => $eh_usuario,
        'login' => $eh_usuario ? $login : null,
        'perfil' => $eh_usuario ? $perfil : null
    ];

    // DEBUG: Verify that no ddi/ddd/site/foto exists in $fields
    if (isset($fields['ddi']) || isset($fields['ddd']) || isset($fields['site']) || isset($fields['foto'])) {
        die('ERROR: Obsolote columns detected in $fields array!');
    }
    // die(print_r($fields, true)); // Uncomment this to see all fields being sent to DB



    $senhaHash = null;
    if ($eh_usuario && $senha !== '') {
        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
    }

    if ($id > 0) {
        // antes
        $beforeStmt = $pdo->prepare("SELECT * FROM 101_pessoas WHERE id = :id");
        $beforeStmt->execute([':id' => $id]);
        $beforeData = $beforeStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $setParts = [];
        $params = [];
        foreach ($fields as $column => $value) {
            $setParts[] = "{$column} = :{$column}";
            $params[":{$column}"] = $value;
        }
        if ($senhaHash !== null) {
            $params[':senha_hash'] = $senhaHash;
            $setParts[] = 'senha_hash = :senha_hash';
        }
        if ($hasAlteracaoColumn) {
            $setParts[] = 'alteracao = NOW()';
        }
        if ($hasDataAlteracaoColumn) {
            $setParts[] = 'data_alteracao = NOW()';
        }

        $params[':id'] = $id;
        $sql = 'UPDATE 101_pessoas SET ' . implode(",\n", $setParts) . ' WHERE id = :id';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $afterStmt = $pdo->prepare("SELECT * FROM 101_pessoas WHERE id = :id");
        $afterStmt->execute([':id' => $id]);
        $afterData = $afterStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $diff = log_diff($beforeData, $afterData);
        if ($diff && $diff !== '{}') {
            log_event($pdo, 'pessoas', 'ALTERACAO', 'ID ' . $id . ' alterações: ' . $diff);
        }

    } else {
        $insertColumns = array_keys($fields);
        $insertValues = array_map(function (string $col) {
            return ':' . $col;
        }, $insertColumns);
        $params = [];
        foreach ($fields as $column => $value) {
            $params[':' . $column] = $value;
        }

        $insertColumns[] = 'senha_hash';
        $insertValues[] = ':senha_hash';
        $params[':senha_hash'] = $senhaHash;

        if ($hasInclusaoColumn) {
            $insertColumns[] = 'inclusao';
            $insertValues[] = 'NOW()';
        }
        if ($hasDataInclusaoColumn) {
            $insertColumns[] = 'data_inclusao';
            $insertValues[] = 'NOW()';
        }
        if ($hasAlteracaoColumn) {
            $insertColumns[] = 'alteracao';
            $insertValues[] = 'NOW()';
        }
        if ($hasDataAlteracaoColumn) {
            $insertColumns[] = 'data_alteracao';
            $insertValues[] = 'NOW()';
        }

        $sql = 'INSERT INTO 101_pessoas (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $insertValues) . ')';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $id = (int) $pdo->lastInsertId();
        $afterStmt = $pdo->prepare("SELECT * FROM 101_pessoas WHERE id = :id");
        $afterStmt->execute([':id' => $id]);
        $inserted = $afterStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        log_event($pdo, 'pessoas', 'INCLUSAO', json_encode($inserted, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    header('Location: ' . $redirectListUrl);
    exit;

} catch (PDOException $e) {
    echo 'Erro ao salvar: ' . $e->getMessage();
}
