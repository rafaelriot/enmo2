<?php
namespace App;

class Logger {
    private static $logDir = __DIR__ . '/../../logs';
    private static $logFile = __DIR__ . '/../../logs/app.log';

    public static function log(string $level, string $message, array $context = []) {
        try {
            if (!is_dir(self::$logDir)) {
                @mkdir(self::$logDir, 0755, true);
            }

            // Rotación por tamaño (si supera los 5 MB, renombrar a app_prev.log)
            if (file_exists(self::$logFile) && filesize(self::$logFile) > 5 * 1024 * 1024) {
                @rename(self::$logFile, self::$logDir . '/app_' . date('Y-m-d_H-i-s') . '.log');
            }

            $logEntry = [
                'timestamp'      => date('Y-m-d\TH:i:s.vP'),
                'level'          => strtoupper($level),
                'message'        => $message,
                'correlation_id' => $context['correlation_id'] ?? ($_SERVER['HTTP_X_CORRELATION_ID'] ?? 'N/A'),
                'method'         => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
                'uri'            => $_SERVER['REQUEST_URI'] ?? 'N/A',
                'ip'             => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'user_id'        => $context['user_id'] ?? null,
                'user_role'      => $context['user_role'] ?? null,
                'context'        => $context
            ];

            $jsonString = json_encode($logEntry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
            @file_put_contents(self::$logFile, $jsonString, FILE_APPEND | LOCK_EX);

        } catch (\Exception $e) {
            // Silencioso para evitar romper el flujo principal
            error_log("Error en Logger: " . $e->getMessage());
        }
    }

    public static function info(string $message, array $context = []) {
        self::log('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []) {
        self::log('WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []) {
        self::log('ERROR', $message, $context);
    }

    public static function critical(string $message, array $context = []) {
        self::log('CRITICAL', $message, $context);
    }

    public static function getRecentLogs(int $limit = 100): array {
        if (!file_exists(self::$logFile)) {
            return [];
        }

        $lines = file(self::$logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) return [];

        $lines = array_slice(array_reverse($lines), 0, $limit);
        $parsed = [];

        foreach ($lines as $line) {
            $data = json_decode($line, true);
            if ($data) {
                $parsed[] = $data;
            }
        }

        return $parsed;
    }
}
