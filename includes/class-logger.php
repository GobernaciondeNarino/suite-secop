<?php
/**
 * Logger — Escritura y lectura de logs del plugin con niveles y rotación.
 *
 * v5.16.0: los logs viven en wp-content/uploads/secop-suite-logs-{sufijo aleatorio}/
 * en lugar de la ruta pública y predecible del plugin. El sufijo aleatorio evita
 * la descarga por URL en servidores que no procesan .htaccess (nginx, Apache con
 * AllowOverride None) y los logs sobreviven a las actualizaciones del plugin.
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
    private const LOG_FILE     = '.secop-import.log';
    private const MAX_LOG_SIZE = 5 * 1024 * 1024; // 5 MB
    private const MAX_ARCHIVES = 3;

    // Niveles de log
    public const DEBUG   = 'DEBUG';
    public const INFO    = 'INFO';
    public const WARNING = 'WARNING';
    public const ERROR   = 'ERROR';

    private static ?string $dir = null;

    /**
     * Directorio de logs dentro de uploads, con sufijo aleatorio persistente.
     */
    private static function dir(): string
    {
        if (self::$dir !== null) {
            return self::$dir;
        }

        $suffix = get_option(SECOP_SUITE_PREFIX . 'log_dir_suffix');
        if (!is_string($suffix) || $suffix === '') {
            $suffix = wp_generate_password(16, false, false);
            update_option(SECOP_SUITE_PREFIX . 'log_dir_suffix', $suffix, false);
        }

        $uploads   = wp_upload_dir(null, false);
        self::$dir = trailingslashit($uploads['basedir']) . 'secop-suite-logs-' . $suffix;
        return self::$dir;
    }

    /**
     * Crea el directorio (con denegación .htaccess e index.php) si no existe y
     * migra el log del directorio antiguo del plugin la primera vez.
     */
    private static function ensure_dir(): string
    {
        $dir = self::dir();

        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }

        if (!file_exists($dir . '/.htaccess')) {
            $htaccess = "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n"
                      . "<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n";
            file_put_contents($dir . '/.htaccess', $htaccess, LOCK_EX);
        }
        if (!file_exists($dir . '/index.php')) {
            file_put_contents($dir . '/index.php', '<?php // Silence is golden.', LOCK_EX);
        }

        // Migración desde el directorio antiguo dentro del plugin (pre-5.16.0).
        $legacy = SECOP_SUITE_DIR . 'logs/' . self::LOG_FILE;
        $file   = $dir . '/' . self::LOG_FILE;
        if (!file_exists($file) && file_exists($legacy) && is_readable($legacy)) {
            @copy($legacy, $file);
            @unlink($legacy);
        }

        return $dir;
    }

    /**
     * Registrar un mensaje con nivel.
     */
    public static function log(string $message, string $level = self::INFO): void
    {
        $dir  = self::ensure_dir();
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
        $file = self::ensure_dir() . '/' . self::LOG_FILE;
        return file_exists($file) ? (string) file_get_contents($file) : '';
    }

    /**
     * Limpiar el log actual.
     */
    public static function clear(): void
    {
        $file = self::dir() . '/' . self::LOG_FILE;
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
