<?php
namespace App;

use PDO;
use PDOException;

class Db {
    // Configuración para entorno local o Hostinger.
    // El usuario debe actualizar estos campos con los de su base de datos.
    private $host = 'localhost';
    private $user = 'u980038333_enmo2root';
    private $pass = 'S$vTGIfnu1';
    private $dbname = 'u980038333_enmo2';

    public function connect() {
        $mysql_connect_str = "mysql:host=$this->host;dbname=$this->dbname;charset=utf8mb4";
        
        try {
            $dbConnection = new PDO($mysql_connect_str, $this->user, $this->pass);
            $dbConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $dbConnection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            return $dbConnection;
        } catch (PDOException $e) {
            // Retorna un error controlado o lanza la excepción
            header('Content-Type: application/json', true, 500);
            echo json_encode([
                "status" => "error",
                "message" => "No se pudo conectar a la base de datos: " . $e->getMessage()
            ]);
            exit;
        }
    }
}
