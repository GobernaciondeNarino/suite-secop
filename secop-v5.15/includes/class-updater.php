<?php
/**
 * Updater — Notificaciones de actualización desde GitHub Releases.
 *
 * Consulta la API de GitHub para detectar nuevas versiones del plugin
 * y muestra la notificación nativa de WordPress para actualizar.
 *
 * @package SecopSuite
 */

declare(strict_types=1);

namespace SecopSuite;

if (!defined('ABSPATH')) {
    exit;
}

final class Updater
{
    private const GITHUB_REPO   = 'GobernaciondeNarino/secop-suite';
    private const CACHE_KEY     = 'secop_suite_github_update';
    private const CACHE_EXPIRY  = 12 * HOUR_IN_SECONDS;

    private string $plugin_file;
    private string $plugin_slug;
    private string $current_version;

    public function __construct()
    {
        $this->plugin_file     = SECOP_SUITE_BASENAME;
        $this->plugin_slug     = dirname(SECOP_SUITE_BASENAME);
        $this->current_version = SECOP_SUITE_VERSION;

        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
        add_filter('upgrader_post_install', [$this, 'after_install'], 10, 3);
    }

    /**
     * Consultar GitHub por nuevas versiones e inyectar en el transient de updates.
     */
    public function check_for_update(object $transient): object
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        $release = $this->get_latest_release();

        if (!$release) {
            return $transient;
        }

        $latest_version = ltrim($release['tag_name'] ?? '', 'vV');

        if (version_compare($this->current_version, $latest_version, '<')) {
            $download_url = $release['zipball_url'] ?? '';

            $transient->response[$this->plugin_file] = (object) [
                'slug'        => $this->plugin_slug,
                'plugin'      => $this->plugin_file,
                'new_version' => $latest_version,
                'url'         => 'https://github.com/' . self::GITHUB_REPO,
                'package'     => $download_url,
                'icons'       => [],
                'banners'     => [],
                'tested'      => '',
                'requires'    => '6.0',
                'requires_php'=> '8.1',
            ];
        }

        return $transient;
    }

    /**
     * Proveer información del plugin para la ventana de detalles en WordPress.
     */
    public function plugin_info(mixed $result, string $action, object $args): mixed
    {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (($args->slug ?? '') !== $this->plugin_slug) {
            return $result;
        }

        $release = $this->get_latest_release();

        if (!$release) {
            return $result;
        }

        $latest_version = ltrim($release['tag_name'] ?? '', 'vV');

        return (object) [
            'name'            => 'SECOP Suite',
            'slug'            => $this->plugin_slug,
            'version'         => $latest_version,
            'author'          => '<a href="https://narino.gov.co">Jonnathan Bucheli Galindo - Gobernación de Nariño</a>',
            'homepage'        => 'https://github.com/' . self::GITHUB_REPO,
            'download_link'   => $release['zipball_url'] ?? '',
            'requires'        => '6.0',
            'tested'          => '',
            'requires_php'    => '8.1',
            'last_updated'    => $release['published_at'] ?? '',
            'sections'        => [
                'description'  => 'Plugin integral para la importación, almacenamiento y visualización interactiva de datos contractuales del SECOP.',
                'changelog'    => nl2br(esc_html($release['body'] ?? 'Sin notas de versión.')),
            ],
        ];
    }

    /**
     * Renombrar carpeta después de la instalación para mantener el slug correcto.
     */
    public function after_install(bool $response, array $hook_extra, array $result): array
    {
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_file) {
            return $result;
        }

        global $wp_filesystem;

        if (!$wp_filesystem) {
            return $result;
        }

        $proper_destination = WP_PLUGIN_DIR . '/' . $this->plugin_slug;

        if ($wp_filesystem->move($result['destination'], $proper_destination)) {
            $result['destination'] = $proper_destination;

            // Reactivar el plugin solo si el move fue exitoso
            activate_plugin($this->plugin_file);
        }

        // Limpiar cache
        delete_transient(self::CACHE_KEY);

        return $result;
    }

    /**
     * Obtener datos del último release de GitHub (con cache).
     *
     * @return array<string, mixed>|null
     */
    private function get_latest_release(): ?array
    {
        $cached = get_transient(self::CACHE_KEY);

        if ($cached !== false) {
            return $cached ?: null;
        }

        $url = 'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest';

        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'Accept'     => 'application/vnd.github.v3+json',
                'User-Agent' => 'SECOP-Suite-WordPress-Plugin/' . $this->current_version,
            ],
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            // Cache vacío para no reintentar inmediatamente
            set_transient(self::CACHE_KEY, [], self::CACHE_EXPIRY);
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($body) || empty($body['tag_name'])) {
            set_transient(self::CACHE_KEY, [], self::CACHE_EXPIRY);
            return null;
        }

        set_transient(self::CACHE_KEY, $body, self::CACHE_EXPIRY);

        return $body;
    }
}
