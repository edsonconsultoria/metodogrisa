<?php
// app/modules/apoio/login_aluno.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/texto.php'; // Para remover_acentos (se existir, ou crio a lógica aqui)

$baseHref = BASE_URL . 'index.php';

// Função auxiliar para remover acentos e caracteres especiais
function criarTextoBaseLogin($string)
{
    if (!$string)
        return '';
    $string = trim(mb_strtolower($string, 'UTF-8'));
    $string = str_replace(
        ['á', 'à', 'â', 'ã', 'ä', 'é', 'è', 'ê', 'ë', 'í', 'ì', 'î', 'ï', 'ó', 'ò', 'ô', 'õ', 'ö', 'ú', 'ù', 'û', 'ü', 'ç', 'ñ'],
        ['a', 'a', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'c', 'n'],
        $string
    );
    // Remove tudo que não for letra ou numero
    $string = preg_replace('/[^a-z0-9]/', '', $string);
    return $string;
}

$actionRun = isset($_POST['action']) && $_POST['action'] === 'run';
$results = [];
$totalAtualizados = 0;

if ($actionRun) {
    // Buscar todos os alunos que não têm acesso ao sistema configurado
    $sql = "
        SELECT p.id, p.nome, p.login 
        FROM 104_alunos a
        JOIN 101_pessoas p ON a.aluno = p.id
        WHERE p.eh_usuario = 0 
           OR p.login IS NULL 
           OR p.login = ''
    ";
    $stmt = $pdo->query($sql);
    $alunos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($alunos as $aluno) {
        $nomePartes = explode(' ', trim($aluno['nome']));
        $primeiroNome = $nomePartes[0];

        $baseLogin = criarTextoBaseLogin($primeiroNome);
        if (empty($baseLogin)) {
            $baseLogin = 'aluno' . $aluno['id']; // Fallback
        }

        $loginTentativa = $baseLogin;
        $contador = 1;

        // Verificar se login já existe. Se sim, incrementa.
        while (true) {
            $checkStmt = $pdo->prepare("SELECT id FROM 101_pessoas WHERE login = :login AND id != :id");
            $checkStmt->execute([':login' => $loginTentativa, ':id' => $aluno['id']]);
            if (!$checkStmt->fetch()) {
                break; // Login disponível
            }
            $loginTentativa = $baseLogin . $contador;
            $contador++;
        }

        $senhaPlana = $baseLogin . '@123';
        $senhaHash = password_hash($senhaPlana, PASSWORD_DEFAULT);

        $updateStmt = $pdo->prepare("
            UPDATE 101_pessoas 
            SET eh_usuario = 1, 
                login = :login, 
                senha_hash = :senha, 
                perfil = 'ALUNO' 
            WHERE id = :id
        ");
        $updateStmt->execute([
            ':login' => $loginTentativa,
            ':senha' => $senhaHash,
            ':id' => $aluno['id']
        ]);

        $totalAtualizados++;
        $results[] = [
            'nome' => $aluno['nome'],
            'login' => $loginTentativa,
            'senha' => $senhaPlana
        ];

        log_event($pdo, '101_pessoas', 'UPDATE', "Login automático gerado para aluno ID {$aluno['id']} ({$loginTentativa}) via rotina de apoio.");
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Criar Login para Alunos</h2>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <p>Esta rotina verifica todos os alunos cadastrados (na tabela <code>104_alunos</code>) e, para aqueles que não
            possuem acesso ao sistema (ou não têm login), cria o acesso automaticamente com as seguintes regras:</p>
        <ul>
            <li><strong>Login:</strong> Primeiro nome do aluno (em minúsculas e sem acentos). Se o login já existir,
                adiciona um número sequencial (ex: <code>alvineia1</code>, <code>alvineia2</code>).</li>
            <li><strong>Senha:</strong> O mesmo base do login seguido de <code>@123</code> (ex:
                <code>alvineia@123</code>).</li>
            <li><strong>Perfil:</strong> Será definido como <code>ALUNO</code> e a opção "Acesso ao Sistema" será
                marcada.</li>
        </ul>

        <?php if (!$actionRun): ?>
            <form method="POST">
                <input type="hidden" name="action" value="run">
                <button type="submit" class="btn btn-primary"
                    onclick="return confirm('Deseja realmente gerar os logins para todos os alunos sem acesso? Esta ação não pode ser desfeita.')">
                    <i class="bi bi-play-circle me-1"></i> Iniciar Processo
                </button>
            </form>
        <?php else: ?>
            <div class="alert alert-success">
                <strong>Processo concluído!</strong>
                <?php echo $totalAtualizados; ?> alunos foram atualizados.
            </div>

            <?php if ($totalAtualizados > 0): ?>
                <div class="table-responsive mt-4">
                    <table class="table table-bordered table-striped table-sm">
                        <thead class="table-dark">
                            <tr>
                                <th>Nome do Aluno</th>
                                <th>Login Gerado</th>
                                <th>Senha Gerada</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $res): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($res['nome']); ?>
                                    </td>
                                    <td><strong>
                                            <?php echo htmlspecialchars($res['login']); ?>
                                        </strong></td>
                                    <td><code><?php echo htmlspecialchars($res['senha']); ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <a href="<?php echo $baseHref; ?>?m=apoio&a=login_aluno" class="btn btn-outline-secondary mt-3">Voltar</a>
        <?php endif; ?>
    </div>
</div>