<?php
namespace App;

use PDO;
use PDOException;

class Db {
    // Configuración para entorno local o Hostinger.
    // El usuario debe actualizar estos campos con los de su base de datos.
    private $host;
    private $user;
    private $pass;
    private $dbname;

    public function __construct() {
        // Auto-detectar entorno Hostinger si no hay .env cargado
        $isHostinger = strpos($_SERVER['DOCUMENT_ROOT'] ?? '', 'hostinger') !== false 
                    || strpos($_SERVER['SERVER_NAME'] ?? '', 'hostingersite.com') !== false;
        
        if ($isHostinger && empty(getenv('DB_HOST')) && empty($_ENV['DB_HOST'])) {
            // Credenciales de producción en Hostinger
            $this->host = 'localhost';
            $this->user = 'u980038333_enmo2root';
            $this->pass = 'S$vTGIfnu1';
            $this->dbname = 'u980038333_enmo2';
        } else {
            $this->host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? 'localhost');
            $this->user = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'root');
            $this->pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : ($_ENV['DB_PASS'] ?? '');
            $this->dbname = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? 'enmo2');
        }
    }

    public function connect() {
        $mysql_connect_str = "mysql:host=$this->host;dbname=$this->dbname;charset=utf8mb4";
        
        try {
            $dbConnection = new PDO($mysql_connect_str, $this->user, $this->pass);
            $dbConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $dbConnection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Auto-migración silenciosa de columnas críticas si faltan en producción
            $this->ensureSchemaUpdated($dbConnection);

            return $dbConnection;
        } catch (PDOException $e) {
            throw new \PDOException("No se pudo conectar a la base de datos: " . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    private function ensureSchemaUpdated(PDO $dbConnection) {
        try {
            // Verificar si la tabla usuarios existe
            $checkTable = $dbConnection->query("SHOW TABLES LIKE 'usuarios'");
            if ($checkTable && $checkTable->rowCount() > 0) {
                // Verificar foto_url
                $checkFoto = $dbConnection->query("SHOW COLUMNS FROM usuarios LIKE 'foto_url'");
                if ($checkFoto && $checkFoto->rowCount() === 0) {
                    $dbConnection->exec("ALTER TABLE usuarios ADD COLUMN foto_url TEXT NULL AFTER email");
                }
                // Verificar google_id
                $checkGoogleId = $dbConnection->query("SHOW COLUMNS FROM usuarios LIKE 'google_id'");
                if ($checkGoogleId && $checkGoogleId->rowCount() === 0) {
                    $dbConnection->exec("ALTER TABLE usuarios ADD COLUMN google_id VARCHAR(255) NULL AFTER foto_url");
                }
            }
        } catch (\Throwable $t) {
            // Ignorar errores de alter si carece de permisos de DDL, permitiendo continuar la conexión
        }
    }
}
