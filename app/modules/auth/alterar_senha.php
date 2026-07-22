<?php
// app/modules/auth/alterar_senha.php

require_once __DIR__ . '/../../config/auth.php';
require_login();

$user = $_SESSION['user'] ?? null;
$feedback = ['type' => null, 'msg' => null];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $senhaAtual = $_POST['senha_atual'] ?? '';
    $novaSenha = $_POST['nova_senha'] ?? '';
    $confirmaSenha = $_POST['confirma_senha'] ?? '';

    if ($senhaAtual === '' || $novaSenha === '' || $confirmaSenha === '') {
        $feedback = ['type' => 'danger', 'msg' => 'Preencha todos os campos.'];
    } elseif ($novaSenha !== $confirmaSenha) {
        $feedback = ['type' => 'danger', 'msg' => 'Nova senha e confirmação não conferem.'];
    } else {
        // Busca hash atual
        $stmt = $pdo->prepare("SELECT senha_hash FROM 101_pessoas WHERE id = :id AND eh_usuario = 1");
        $stmt->execute([':id' => (int) $user['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || empty($row['senha_hash']) || !password_verify($senhaAtual, $row['senha_hash'])) {
            $feedback = ['type' => 'danger', 'msg' => 'Senha atual incorreta.'];
        } else {
            $newHash = password_hash($novaSenha, PASSWORD_DEFAULT);
            $setParts = ['senha_hash = :hash'];
            $params = [
                ':hash' => $newHash,
                ':id'   => (int) $user['id'],
            ];
            if (columnExists('101_pessoas', 'usuario_alteracao_id')) {
                $setParts[] = 'usuario_alteracao_id = :uid';
                $params[':uid'] = (int) $user['id'];
            }
            if (columnExists('101_pessoas', 'data_alteracao')) {
                $setParts[] = 'data_alteracao = NOW()';
            }
            $setClause = implode(', ', $setParts);
            $up = $pdo->prepare("UPDATE 101_pessoas SET {$setClause} WHERE id = :id");
            $up->execute($params);
            if (function_exists('tableExists') && tableExists('auth_remember_tokens')) {
                $pdo->prepare("DELETE FROM auth_remember_tokens WHERE user_id = :id")
                    ->execute([':id' => (int) $user['id']]);
            }
            $feedback = ['type' => 'success', 'msg' => 'Senha alterada com sucesso.'];
        }
    }
}
?>

<h4 class="mb-3">Alterar senha</h4>

<?php if ($feedback['type']): ?>
  <div class="alert alert-<?php echo $feedback['type']; ?> py-2">
    <?php echo htmlspecialchars($feedback['msg']); ?>
  </div>
<?php endif; ?>

<form method="post" class="card shadow-sm" style="max-width: 520px;">
  <div class="card-body">
    <div class="mb-3">
      <label class="form-label">Senha atual</label>
      <input type="password" name="senha_atual" class="form-control" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Nova senha</label>
      <input type="password" name="nova_senha" class="form-control" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Confirmar nova senha</label>
      <input type="password" name="confirma_senha" class="form-control" required>
    </div>
    <div class="d-flex justify-content-between">
      <a href="<?php echo BASE_URL; ?>index.php" class="btn btn-outline-secondary">Cancelar</a>
      <button type="submit" class="btn btn-primary">Salvar nova senha</button>
    </div>
  </div>
</form>
