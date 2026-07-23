-- Migración: Crear tabla documentos_repartidor
-- Ejecutar en phpMyAdmin o consola MySQL

CREATE TABLE IF NOT EXISTS documentos_repartidor (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo_documento ENUM('ine', 'licencia', 'seguro') NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    ruta_archivo VARCHAR(500) NOT NULL,
    estado ENUM('pendiente', 'aprobado', 'rechazado') DEFAULT 'pendiente',
    motivo_rechazo TEXT NULL,
    revisado_por INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY unique_doc_per_user (usuario_id, tipo_documento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
