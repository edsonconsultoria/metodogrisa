<?php
// app/modules/professores/ajax_send_docs.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../helpers/arquivos.php';

// Apenas ADMIN ou SUPERADMIN podem enviar
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
    // Buscar dados do professor
    $stmt = $pdo->prepare("
        SELECT pf.*, ps.nome AS professor_nome
        FROM 104_professores pf
        LEFT JOIN 101_pessoas ps ON pf.pessoa = ps.id
        WHERE pf.id = ?
    ");
    $stmt->execute([$id]);
    $professor = $stmt->fetch();

    if (!$professor) {
        throw new Exception("Professor não encontrado.");
    }

    $destinatario = 'secretariageral@faculdadefge.com.br'; // FGE
    
    $documentosJson = $professor['documentos'] ?? '';
    $documentos = parse_arquivos_json($documentosJson);

    if (empty($documentos)) {
        throw new Exception("Nenhum documento encontrado para este professor.");
    }

    // Configurar PHPMailer (usando as mesmas configurações do módulo alunos)
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
    $mail->setFrom('contato@eadmetodogrisa.com.br', 'Método Grisa - Documentação Professor');
    $mail->addAddress($destinatario);
    $mail->addCC('metodogrisa@gmail.com');
    $mail->addReplyTo('metodogrisa@gmail.com', 'Método Grisa');

    // Assunto
    $mail->Subject = "Documentação de Professor: " . $professor['professor_nome'];

    // Corpo do E-mail
    $message = "Prezados,\n\n";
    $message .= "Encaminho, em anexo, a documentação do professor " . $professor['professor_nome'] . ".\n";
    $message .= "Permaneço à disposição caso seja necessário o envio de qualquer outro documento ou informação complementar.\n\n";
    $message .= "Atenciosamente,\n\n";
    $message .= "Edson Marques\n";
    $message .= "+ 55 48 99680-4889\n";
    $message .= "metodogrisa@gmail.com";

    $mail->Body = $message;

    // Anexos
    $targetDir = rtrim(UPLOADS_DIR, '/') . '/';
    foreach ($documentos as $doc) {
        if (!empty($doc['file'])) {
            $filePath = $targetDir . $doc['file'];
            if (file_exists($filePath)) {
                $mail->addAttachment($filePath, $doc['name'] ?? basename($filePath));
            }
        }
    }

    $mail->send();

    // Log do evento
    log_event($pdo, '104_professores', 'EMAIL', "Documentos do professor ID $id enviados para FGE");

    echo json_encode(['success' => true]);

} catch (PHPMailerException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro PHPMailer: ' . $mail->ErrorInfo . ' (Exception: ' . $e->getMessage() . ')'
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
