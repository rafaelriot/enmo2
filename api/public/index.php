<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use Slim\Factory\AppFactory;

// Cargar el autoloader de Composer
require __DIR__ . '/../vendor/autoload.php';

// Crear la aplicación de Slim
$app = AppFactory::create();

// Detectar y configurar el Base Path de forma dinámica para XAMPP y Hostinger
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$basePath = str_replace('/api/public/index.php', '', $scriptName);
$app->setBasePath($basePath);

// Agregar Middleware de Enrutamiento
$app->addRoutingMiddleware();

// Middleware para manejo de errores (útil para desarrollo)
// Cambia a (false, false, false) en producción en Hostinger
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

// Habilitar CORS para permitir peticiones desde la app móvil en otros subdominios/puertos
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});

// Cargar las rutas de la aplicación
require __DIR__ . '/../src/routes/usuarios.php';
require __DIR__ . '/../src/routes/pedidos.php';
require __DIR__ . '/../src/routes/admin.php';

// Ruta de bienvenida / Test de estado
$app->get('/api[/]', function ($request, $response, $args) {
    $response->getBody()->write(json_encode([
        "status" => "success",
        "message" => "¡Bienvenido a la API de enMo2 Logística de Velocidad!"
    ]));
    return $response->withHeader('Content-Type', 'application/json');
});

// Ejecutar la aplicación
$app->run();
