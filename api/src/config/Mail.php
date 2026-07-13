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
            $mail->Host       = 'smtp.hostinger.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'recuperacion@enmo2.com'; // REEMPLAZAR CON TU CORREO DE HOSTINGER
            $mail->Password   = 'S$vTGIfnu1';           // REEMPLAZAR CON TU CONTRASEÑA DE CORREO DE HOSTINGER
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
            $mail->Port       = 465;
            $mail->CharSet    = 'UTF-8';

            // Destinatarios
            $mail->setFrom('recuperacion@enmo2.com', 'enMo2 Logística');
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
