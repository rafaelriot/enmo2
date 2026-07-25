-- Migración: Añadir campos de calificación de repartidor a cliente
ALTER TABLE pedidos 
ADD COLUMN IF NOT EXISTS calificacion_cliente_estrellas INT NULL,
ADD COLUMN IF NOT EXISTS calificacion_cliente_comentario TEXT NULL;
