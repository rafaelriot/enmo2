<?php
namespace App;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class JwtAuthMiddleware {
    private $allowedRoles;

    public function __construct(array $allowedRoles = []) {
        $this->allowedRoles = $allowedRoles;
    }

    public function __invoke(Request $request, RequestHandler $handler): Response {
        $authHeader = $request->getHeaderLine('Authorization');
        
        if (empty($authHeader)) {
            return $this->unauthorizedResponse('Token de autenticación no proporcionado.');
        }

        $token = null;
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
        }

        if (!$token) {
            return $this->unauthorizedResponse('Formato de token inválido.');
        }

        try {
            $secretKey = getenv('JWT_SECRET') ?: ($_ENV['JWT_SECRET'] ?? 'super-secret-key-change-in-production-123456');
            $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));
            
            // Adjuntar datos del usuario decodificados al request
            $request = $request->withAttribute('token_decoded', $decoded);
            
            // Si hay roles configurados, verificar autorización
            if (!empty($this->allowedRoles)) {
                $userRol = $decoded->rol ?? '';
                if (!in_array($userRol, $this->allowedRoles)) {
                    $response = new SlimResponse();
                    $response->getBody()->write(json_encode([
                        "status" => "error",
                        "message" => "No tienes permisos para acceder a este recurso."
                    ]));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
                }
            }

            return $handler->handle($request);

        } catch (Exception $e) {
            return $this->unauthorizedResponse('Token inválido o expirado: ' . $e->getMessage());
        }
    }

    private function unauthorizedResponse(string $message): Response {
        $response = new SlimResponse();
        $response->getBody()->write(json_encode([
            "status" => "error",
            "message" => $message
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
    }
}
