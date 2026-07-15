<?php
use App\Mail;

require_once __DIR__ . '/api/src/config/Mail.php';

// Cambia este correo por uno tuyo para recibir la prueba
$emailDestinatario = 'rafael.riot.o@gmail.com'; 

echo "<h3>Iniciando prueba de conexión SMTP de Hostinger...</h3>";

$asunto = "Prueba de Conexión SMTP - enMo2";
$cuerpo = "<h1>Prueba Exitosa</h1><p>Si estás leyendo esto, el servidor SMTP de Hostinger está enviando correos correctamente.</p>";
$altBody = "Prueba exitosa de conexión SMTP.";

$enviado = Mail::send($emailDestinatario, 'Rafael Test', $asunto, $cuerpo, $altBody);

if ($enviado) {
    echo "<p style='color: green; font-weight: bold;'>¡Éxito! El correo de prueba fue enviado correctamente a $emailDestinatario.</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>Error: No se pudo enviar el correo de prueba. Revisa el archivo de log en api/error_log para ver los detalles.</p>";
}
