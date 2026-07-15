<?php
namespace App;

class ImageHelper {
    /**
     * Guarda una imagen en base64 o binario, convirtiéndola a WebP y almacenándola
     * en una subcarpeta estructurada por Año y Mes.
     *
     * @param string $base64Data Datos de la imagen en base64 (data:image/...)
     * @param string $baseUploadPath Ruta base física de subidas
     * @param string $subFolder Subcarpeta específica (ej: 'evidencias')
     * @return string|false Ruta relativa de la imagen guardada o false en caso de fallo
     */
    public static function uploadBase64WebP($base64Data, $baseUploadPath, $subFolder = 'evidencias') {
        try {
            // Validar formato base64
            if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
                $data = substr($base64Data, strpos($base64Data, ',') + 1);
                $data = base64_decode($data);
                if ($data === false) {
                    return false;
                }
            } else {
                return false;
            }

            // Crear estructura de directorios YYYY/MM
            $year = date('Y');
            $month = date('m');
            $relativeDir = "uploads/{$subFolder}/{$year}/{$month}";
            $fullDir = rtrim($baseUploadPath, '/') . '/' . $relativeDir;

            if (!file_exists($fullDir)) {
                mkdir($fullDir, 0755, true);
            }

            // Nombre de archivo único y extensión WebP
            $fileName = uniqid('img_', true) . '.webp';
            $fullPath = $fullDir . '/' . $fileName;
            $relativePath = $relativeDir . '/' . $fileName;

            // Procesar la imagen con las funciones GD de PHP para guardarla como WebP nativo
            $image = imagecreatefromstring($data);
            if ($image !== false) {
                // Guardar como WebP real con calidad óptima 80%
                imagewebp($image, $fullPath, 80);
                imagedestroy($image);
                return $relativePath;
            }

            // Fallback: guardar binario plano si GD falla
            if (file_put_contents($fullPath, $data)) {
                return $relativePath;
            }

            return false;
        } catch (\Exception $e) {
            error_log("Error en ImageHelper: " . $e->getMessage());
            return false;
        }
    }
}
