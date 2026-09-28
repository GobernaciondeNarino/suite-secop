<?php
/**
 * Rate_Limiter — Limitador de peticiones por IP compartido por AJAX y REST.
 *
 * Ventana fija anclada a la primera petición: el contador guarda el inicio de
 * la ventana, de modo que renovar el transient no reinicia el periodo (el
 * limitador anterior reiniciaba el TTL en cada petición y podía bloquear
 * indefinidamente a un usuario que navegara de forma continua).
 *
 * @package SecopSuite
 */

declare(strict_types=1);

namespace SecopSuite;

if (!defined('ABSPATH')) {
    exit;
}

final class Rate_Limiter
{
    /**
     * Registra una petición en el cubo indicado y devuelve true si la IP
     * superó el máximo permitido dentro de la ventana.
     *
     * @param string $bucket  Nombre lógico del cubo (p. ej. 'consulta', 'filter').
     * @param int    $max     Máximo de peticiones por ventana.
     * @param int    $window  Duración de la ventana en segundos.
     */
    public static function limited(string $bucket, int $max = 30, int $window = MINUTE_IN_SECONDS): bool
    {
        $key  = 'secop_rl_' . $bucket . '_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
        $data = get_transient($key);

        if (!is_array($data) || !isset($data['t'], $data['n']) || (time() - (int) $data['t']) >= $window) {
            $data = ['t' => time(), 'n' => 0];
        }

        $data['n']++;

        if ($data['n'] > $max) {
            return true;
        }

        set_transient($key, $data, $window);
        return false;
    }
}
