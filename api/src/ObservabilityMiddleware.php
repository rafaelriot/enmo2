<?php
namespace App;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

require_once __DIR__ . '/config/Logger.php';

class ObservabilityMiddleware {

    public function __invoke(Request $request, RequestHandler $handler): Response {
        $startTime = microtime(true);

        // Obtener o generar Correlation ID (UUIDv4)
        $correlationId = $request->getHeaderLine('X-Correlation-ID');
        if (empty($correlationId)) {
            $correlationId = sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
        }

        // Inyectar correlation_id en los atributos del request
        $request = $request->withAttribute('correlation_id', $correlationId);

        // Procesar la solicitud
        $response = $handler->handle($request);

        $endTime = microtime(true);
        $durationMs = round(($endTime - $startTime) * 1000, 2);

        // Inyectar headers de observabilidad en la respuesta HTTP
        $response = $response
            ->withHeader('X-Correlation-ID', $correlationId)
            ->withHeader('X-Response-Time-Ms', (string)$durationMs);

        // Extraer info del token decodificado si existió auth
        $tokenDecoded = $request->getAttribute('token_decoded');
        $userId = $tokenDecoded->id ?? null;
        $userRole = $tokenDecoded->rol ?? null;

        $statusCode = $response->getStatusCode();
        $path = $request->getUri()->getPath();
        $method = $request->getMethod();

        $logLevel = 'info';
        if ($statusCode >= 500) {
            $logLevel = 'error';
        } elseif ($statusCode >= 400) {
            $logLevel = 'warning';
        }

        Logger::log($logLevel, "HTTP {$method} {$path} - {$statusCode} ({$durationMs}ms)", [
            'correlation_id' => $correlationId,
            'duration_ms'    => $durationMs,
            'status_code'    => $statusCode,
            'user_id'        => $userId,
            'user_role'      => $userRole
        ]);

        return $response;
    }
}
