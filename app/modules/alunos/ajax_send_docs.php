<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/auth.php';

if (!current_user_is_at_least_admin()) {
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID não fornecido.']);
    exit;
}

try {
    // Buscar dados do aluno, curso e faculdade
    $stmt = $pdo->prepare("
        SELECT a.*, p.nome AS aluno_nome, f.email AS faculdade_email
        FROM 104_alunos a
        LEFT JOIN 101_pessoas p ON a.aluno = p.id
        LEFT JOIN 104_turmas t ON a.turma = t.id
        LEFT JOIN 104_faculdades f ON t.faculdade = f.id
        WHERE a.id = ?
    ");
    $stmt->execute([$id]);

    $aluno = $stmt->fetch();

    if (!$aluno) {
        throw new Exception("Aluno não encontrado.");
    }

    $destinatario = !empty($aluno['faculdade_email']) ? $aluno['faculdade_email'] : 'edsonconsultoria@gmail.com';

    $documentosJson = $aluno['documentos_recebidos'] ?? '';
    // Corrigir caso o JSON venha com aspas escapadas ou formato inválido
    $documentos = json_decode($documentosJson, true);

    if (!is_array($documentos) || empty($documentos)) {
        throw new Exception("Nenhum documento recebido encontrado para este aluno.");
    }

    $mail = new PHPMailer(true);

    // Configurações SMTP
    $mail->isSMTP();
    $mail->Host = 'mail.eadmetodogrisa.com.br';
    $mail->SMTPAuth = true;
    $mail->Username = 'contato@eadmetodogrisa.com.br';
    $mail->Password = 'D,EgACJ8UWNG';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8';

    // Destinatários
    $mail->setFrom('contato@eadmetodogrisa.com.br', 'Método Grisa - Documentação');
    $mail->addAddress($destinatario);
    $mail->addCC('metodogrisa@gmail.com');
    $mail->addCC('edsonconsultoria@gmail.com');
    $mail->addReplyTo('metodogrisa@gmail.com', 'Método Grisa');

    // Assunto
    $mail->Subject = "Envio de documentação para emissão de diploma " . $aluno['aluno_nome'];

    // Corpo do E-mail
    $message = "Prezados,\n\n";
    $message .= "Encaminho, em anexo, a documentação necessária para a emissão do diploma do curso de Pós-Graduação em
Parapsicologia Clínica e Hipnoterapia - Método Grisa.\n";
    $message .= "Permaneço à disposição caso seja necessário o envio de qualquer outro documento ou informação
complementar.\n\n";
    $message .= "Atenciosamente,\n\n";
    $message .= "Edson Marques\n";
    $message .= "+ 55 48 99680-4889\n";
    $message .= "metodogrisa@gmail.com";

    $mail->Body = $message;

    // Anexos
    $basePath = __DIR__ . '/../../../';
    foreach ($documentos as $doc) {
        if (!empty($doc['name'])) {
            $filePath = realpath($basePath . $doc['name']);
            if ($filePath && file_exists($filePath)) {
                $mail->addAttachment($filePath, $doc['usrName'] ?? basename($filePath));
            }
        }
    }

    $mail->send();

    // Atualizar data de alteração do aluno
    $stmtUpdate = $pdo->prepare("UPDATE 104_alunos SET data_alteracao = NOW() WHERE id = ?");
    $stmtUpdate->execute([$id]);

    ob_clean(); // Limpar qualquer aviso/saída acidental antes do JSON
    echo json_encode(['success' => true]);

} catch (PHPMailerException $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Erro PHPMailer: ' . $mail->ErrorInfo . ' (Exception: ' .
            $e->getMessage() . ')'
    ]);
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}