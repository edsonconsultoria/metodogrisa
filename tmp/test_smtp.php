<?php
require_once __DIR__ . '/../app/config/config.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = 'mail.eadmetodogrisa.com.br';
    $mail->SMTPAuth = true;
    $mail->Username = 'contato@eadmetodogrisa.com.br';
    $mail->Password = 'D,EgACJ8UWNG';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->SMTPDebug = 2; // ENORMOUS DEBUG OUTPUT
    $mail->Debugoutput = 'echo';

    $mail->setFrom('contato@eadmetodogrisa.com.br', 'Teste SMTP');
    $mail->addAddress('edsonconsultoria@gmail.com');
    $mail->Subject = 'Teste de Conexão SMTP';
    $mail->Body = 'Este é um teste de conexão SMTP.';

    $mail->send();
    echo "\n\nSUCCESS! Email sent.";
} catch (Exception $e) {
    echo "\n\nERROR: " . $e->getMessage();
}
