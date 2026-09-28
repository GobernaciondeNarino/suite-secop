<?php
/**
 * Logger — Escritura y lectura de logs del plugin con niveles y rotación.
 *
 * @package SecopSuite
 */

declare(strict_types=1);

namespace SecopSuite;

if (!defined('ABSPATH')) {
    exit;
}

final class Logger
{
    private const LOG_DIR      = 'logs';
    private const LOG_FILE     = '.secop-import.log';
    private const MAX_LOG_SIZE = 5 * 1024 * 1024; // 5 MB
    private const MAX_ARCHIVES = 3;

    // Niveles de log
    public const DEBUG   = 'DEBUG';
    public const INFO    = 'INFO';
    public const WARNING = 'WARNING';
    public const ERROR   = 'ERROR';

    /**
     * Registrar un mensaje con nivel.
     */
    public static function log(string $message, string $level = self::INFO): void
    {
        $dir = SECOP_SUITE_DIR . self::LOG_DIR;

        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
            file_put_contents($dir . '/.htaccess', "Order deny,allow\nDeny from all", LOCK_EX);
            file_put_contents($dir . '/index.php', '<?php // Silence is golden.', LOCK_EX);
        }

        if (!file_exists($dir . '/.htaccess')) {
            file_put_contents($dir . '/.htaccess', "Order deny,allow\nDeny from all", LOCK_EX);
        }

        $file = $dir . '/' . self::LOG_FILE;

        // Rotación de log si supera el tamaño máximo
        if (file_exists($file) && filesize($file) > self::MAX_LOG_SIZE) {
            self::rotate($dir, $file);
        }

        $timestamp = wp_date('Y-m-d H:i:s');
        $entry     = "[{$timestamp}] [{$level}] {$message}\n";

        file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[SECOP Suite] [{$level}] {$message}");
        }
    }

    /**
     * Atajos por nivel.
     */
    public static function debug(string $message): void   { self::log($message, self::DEBUG); }
    public static function info(string $message): void    { self::log($message, self::INFO); }
    public static function warning(string $message): void { self::log($message, self::WARNING); }
    public static function error(string $message): void   { self::log($message, self::ERROR); }

    /**
     * Leer el contenido del log actual.
     */
    public static function read(): string
    {
        $file = SECOP_SUITE_DIR . self::LOG_DIR . '/' . self::LOG_FILE;
        return file_exists($file) ? (string) file_get_contents($file) : '';
    }

    /**
     * Limpiar el log actual.
     */
    public static function clear(): void
    {
        $file = SECOP_SUITE_DIR . self::LOG_DIR . '/' . self::LOG_FILE;
        if (file_exists($file)) {
            file_put_contents($file, '', LOCK_EX);
        }
    }

    /**
     * Rotar archivos de log.
     */
    private static function rotate(string $dir, string $file): void
    {
        // Eliminar el archivo más antiguo
        $oldest = $dir . '/' . self::LOG_FILE . '.' . self::MAX_ARCHIVES;
        if (file_exists($oldest)) {
            @unlink($oldest);
        }

        // Rotar archivos existentes
        for ($i = self::MAX_ARCHIVES - 1; $i >= 1; $i--) {
            $current = $dir . '/' . self::LOG_FILE . '.' . $i;
            $next    = $dir . '/' . self::LOG_FILE . '.' . ($i + 1);
            if (file_exists($current)) {
                @rename($current, $next);
            }
        }

        // Mover el actual a .1
        @rename($file, $dir . '/' . self::LOG_FILE . '.1');
    }
}
