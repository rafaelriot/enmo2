<?php
namespace App;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mail {
    public static function send($toEmail, $toName, $subject, $bodyHtml, $altBodyText = '') {
        $mail = new PHPMailer(true);

        try {
            // Configuración del Servidor SMTP (Hostinger)
            $mail->isSMTP();
            $mail->Host       = $_ENV['SMTP_HOST'] ?? 'smtp.hostinger.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['SMTP_USER'] ?? 'recuperacion@enmo2.com'; 
            $mail->Password   = $_ENV['SMTP_PASS'] ?? '';           
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
            $mail->Port       = (int)($_ENV['SMTP_PORT'] ?? 465);
            $mail->CharSet    = 'UTF-8';

            // Destinatarios
            $mail->setFrom($_ENV['SMTP_USER'] ?? 'recuperacion@enmo2.com', 'enMo2 Logística');
            $mail->addAddress($toEmail, $toName);

            // Contenido del Correo
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $bodyHtml;
            $mail->AltBody = $altBodyText;

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Error al enviar correo: {$mail->ErrorInfo}");
            return false;
        }
    }
}
