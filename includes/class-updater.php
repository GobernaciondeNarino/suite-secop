<?php
/**
 * Updater — Notificaciones de actualización desde GitHub Releases.
 *
 * Consulta la API de GitHub para detectar nuevas versiones del plugin
 * y muestra la notificación nativa de WordPress para actualizar.
 *
 * El paquete preferido es el asset «secop-suite.zip» del release (generado por
 * bin/build-zip.sh / .github/workflows/release.yml), cuya carpeta raíz es
 * siempre «secop-suite/». Si el release no trae ese asset se usa el zipball de
 * GitHub y la carpeta extraída se renombra a la carpeta ya instalada.
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
    private const GITHUB_REPO   = 'GobernaciondeNarino/suite-secop';
    // v5.18: clave nueva para descartar el caché vacío que dejaba el repo antiguo inexistente.
    private const CACHE_KEY     = 'secop_suite_github_release';
    private const CACHE_EXPIRY  = 12 * HOUR_IN_SECONDS;
    private const ASSET_NAME    = 'secop-suite.zip';
    private const MAIN_FILE     = 'secop-suite.php';
    private const PLUGIN_NAME   = 'SECOP Suite';

    private string $plugin_file;
    private string $plugin_slug;
    private string $current_version;

    /** Estado de activación registrado antes de instalar la actualización. */
    private ?bool $was_active = null;
    private bool $was_network_active = false;

    public function __construct()
    {
        $this->plugin_file     = SECOP_SUITE_BASENAME;
        $this->plugin_slug     = dirname(SECOP_SUITE_BASENAME);
        $this->current_version = SECOP_SUITE_VERSION;

        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
        // Prioridad 5: antes de que WordPress desactive el plugin (prioridad 10).
        add_filter('upgrader_pre_install', [$this, 'before_install'], 5, 2);
        add_filter('upgrader_source_selection', [$this, 'fix_source_folder'], 10, 4);
        add_filter('upgrader_post_install', [$this, 'after_install'], 10, 3);
    }

    /**
     * Consultar GitHub por nuevas versiones e inyectar en el transient de updates.
     */
    public function check_for_update(mixed $transient): mixed
    {
        if (!is_object($transient) || empty($transient->checked)) {
            return $transient;
        }

        $release = $this->get_latest_release();

        if (!$release) {
            return $transient;
        }

        $latest_version = $this->release_version($release);
        $package        = $this->get_package_url($release);

        $item = (object) [
            'id'           => 'github.com/' . self::GITHUB_REPO,
            'slug'         => $this->plugin_slug,
            'plugin'       => $this->plugin_file,
            'new_version'  => $latest_version !== '' ? $latest_version : $this->current_version,
            'url'          => 'https://github.com/' . self::GITHUB_REPO,
            'package'      => $package,
            'icons'        => [],
            'banners'      => [],
            'tested'       => '',
            'requires'     => '6.0',
            'requires_php' => '8.1',
        ];

        if ($latest_version !== '' && $package !== '' && version_compare($this->current_version, $latest_version, '<')) {
            $transient->response[$this->plugin_file] = $item;
            unset($transient->no_update[$this->plugin_file]);
        } else {
            // Sin actualización: se informa igualmente para que WordPress muestre
            // el control de actualizaciones automáticas del plugin.
            $item->new_version = $this->current_version;
            $transient->no_update[$this->plugin_file] = $item;
        }

        return $transient;
    }

    /**
     * Proveer información del plugin para la ventana de detalles en WordPress.
     */
    public function plugin_info(mixed $result, mixed $action, mixed $args): mixed
    {
        if ($action !== 'plugin_information' || !is_object($args)) {
            return $result;
        }

        if (($args->slug ?? '') !== $this->plugin_slug) {
            return $result;
        }

        $release = $this->get_latest_release();

        if (!$release) {
            return $result;
        }

        return (object) [
            'name'            => 'SECOP Suite',
            'slug'            => $this->plugin_slug,
            'version'         => $this->release_version($release),
            'author'          => '<a href="https://narino.gov.co">Jonnathan Bucheli Galindo - Gobernación de Nariño</a>',
            'homepage'        => 'https://github.com/' . self::GITHUB_REPO,
            'download_link'   => $this->get_package_url($release),
            'requires'        => '6.0',
            'tested'          => '',
            'requires_php'    => '8.1',
            'last_updated'    => is_string($release['published_at'] ?? null) ? $release['published_at'] : '',
            'sections'        => [
                'description'  => 'Plugin integral para la importación, almacenamiento y visualización interactiva de datos contractuales del SECOP.',
                'changelog'    => nl2br(esc_html(is_string($release['body'] ?? null) && $release['body'] !== '' ? $release['body'] : 'Sin notas de versión.')),
            ],
        ];
    }

    /**
     * Recordar si el plugin estaba activo ANTES de que WordPress lo desactive
     * para actualizarlo, para reactivarlo solo en ese caso.
     */
    public function before_install(mixed $response, mixed $hook_extra): mixed
    {
        if (is_array($hook_extra) && ($hook_extra['plugin'] ?? '') === $this->plugin_file) {
            if (!function_exists('is_plugin_active')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $this->was_network_active = is_multisite() && is_plugin_active_for_network($this->plugin_file);
            $this->was_active         = $this->was_network_active || is_plugin_active($this->plugin_file);
        }

        return $response;
    }

    /**
     * Renombrar la carpeta extraída del paquete a la carpeta instalada
     * (p. ej. «GobernaciondeNarino-suite-secop-abc123» o «suite-secop-main» →
     * «secop-suite») ANTES de copiarla. Así WordPress reemplaza la copia
     * instalada en lugar de crear un plugin distinto, tanto al actualizar desde
     * el escritorio como al subir un ZIP (Plugins → Añadir nuevo → Subir).
     */
    public function fix_source_folder(mixed $source, mixed $remote_source, mixed $upgrader = null, mixed $hook_extra = []): mixed
    {
        if (!is_string($source) || !is_string($remote_source)) {
            return $source;
        }

        $hook_extra = is_array($hook_extra) ? $hook_extra : [];
        $is_update  = ($hook_extra['plugin'] ?? '') === $this->plugin_file;
        $is_install = ($hook_extra['type'] ?? '') === 'plugin' && ($hook_extra['action'] ?? '') === 'install';

        if (!$is_update && !$is_install) {
            return $source;
        }

        $source = trailingslashit($source);
        if (basename($source) === $this->plugin_slug) {
            return $source;
        }

        // Solo paquetes que contienen ESTE plugin.
        $main = $source . self::MAIN_FILE;
        if (!is_file($main)) {
            return $source;
        }
        $data = get_file_data($main, ['Name' => 'Plugin Name']);
        if (trim((string) ($data['Name'] ?? '')) !== self::PLUGIN_NAME) {
            return $source;
        }

        global $wp_filesystem;
        if (!$wp_filesystem) {
            return $source;
        }

        $new_source = trailingslashit($remote_source) . $this->plugin_slug . '/';
        if (untrailingslashit($new_source) === untrailingslashit($source)) {
            return $source;
        }
        if ($wp_filesystem->exists($new_source)) {
            $wp_filesystem->delete($new_source, true);
        }
        if ($wp_filesystem->move(untrailingslashit($source), untrailingslashit($new_source))) {
            return $new_source;
        }

        return $source;
    }

    /**
     * Respaldo por si el paquete se instaló en otra carpeta: moverlo a la
     * carpeta ya instalada (WP_PLUGIN_DIR/<carpeta actual>) sin importar el
     * nombre de carpeta del ZIP, y reactivar el plugin solo si estaba activo.
     *
     * @param mixed                $response   true o WP_Error de filtros previos.
     * @param array<string, mixed> $hook_extra
     * @param array<string, mixed> $result
     */
    public function after_install(mixed $response, mixed $hook_extra, mixed $result): mixed
    {
        if (is_wp_error($response) || !is_array($hook_extra) || !is_array($result)
            || ($hook_extra['plugin'] ?? '') !== $this->plugin_file) {
            return $response;
        }

        global $wp_filesystem;

        $proper_destination = trailingslashit(WP_PLUGIN_DIR) . $this->plugin_slug;
        $destination        = untrailingslashit((string) ($result['destination'] ?? ''));

        if ($wp_filesystem && $destination !== '' && $destination !== untrailingslashit($proper_destination)
            && is_file(trailingslashit($destination) . self::MAIN_FILE)) {
            // overwrite = true: la carpeta anterior ya fue retirada por WordPress;
            // si quedara algún resto, se reemplaza por la versión nueva.
            $wp_filesystem->move($destination, $proper_destination, true);
        }

        // Reactivar solo si estaba activo antes de actualizar y WordPress lo
        // desactivó. En modo silencioso: sin hooks de activación a mitad de la
        // actualización (las migraciones corren en admin_init con la nueva versión).
        if ($this->was_active === true && is_file(trailingslashit(WP_PLUGIN_DIR) . $this->plugin_file)) {
            $still_active = $this->was_network_active
                ? is_plugin_active_for_network($this->plugin_file)
                : is_plugin_active($this->plugin_file);
            if (!$still_active) {
                activate_plugin($this->plugin_file, '', $this->was_network_active, true);
            }
        }
        $this->was_active = null;

        // Limpiar cache
        delete_transient(self::CACHE_KEY);

        return $response;
    }

    /**
     * Versión del release a partir del tag (v5.18.0 → 5.18.0). Vacía si no es válida.
     *
     * @param array<string, mixed> $release
     */
    private function release_version(array $release): string
    {
        $tag     = is_string($release['tag_name'] ?? null) ? $release['tag_name'] : '';
        $version = ltrim($tag, 'vV');

        return preg_match('/^\d+(\.\d+){0,3}$/', $version) ? $version : '';
    }

    /**
     * URL del paquete: prefiere el asset «secop-suite.zip» (o
     * «secop-suite-<versión>.zip») del release; si no existe, el zipball.
     * Solo se aceptan URLs https de github.com / api.github.com de este repo.
     *
     * @param array<string, mixed> $release
     */
    private function get_package_url(array $release): string
    {
        $assets    = is_array($release['assets'] ?? null) ? $release['assets'] : [];
        $exact     = '';
        $versioned = '';

        foreach ($assets as $asset) {
            if (!is_array($asset)) {
                continue;
            }
            $name = is_string($asset['name'] ?? null) ? $asset['name'] : '';
            $url  = is_string($asset['browser_download_url'] ?? null) ? $asset['browser_download_url'] : '';
            $state = is_string($asset['state'] ?? null) ? $asset['state'] : '';
            if (($state !== '' && $state !== 'uploaded') || !$this->is_trusted_url($url)) {
                continue;
            }
            if ($name === self::ASSET_NAME && $exact === '') {
                $exact = $url;
            } elseif ($versioned === '' && preg_match('/^secop-suite-\d+(\.\d+){0,3}\.zip$/', $name)) {
                $versioned = $url;
            }
        }

        if ($exact !== '') {
            return $exact;
        }
        if ($versioned !== '') {
            return $versioned;
        }

        $zipball = is_string($release['zipball_url'] ?? null) ? $release['zipball_url'] : '';

        return $this->is_trusted_url($zipball) ? $zipball : '';
    }

    /**
     * Solo https://github.com/<repo>/releases/download/… y
     * https://api.github.com/repos/<repo>/zipball/….
     */
    private function is_trusted_url(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        $parts = wp_parse_url($url);
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || isset($parts['port']) || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = strtolower((string) ($parts['path'] ?? ''));
        $repo = strtolower(self::GITHUB_REPO);

        if ($host === 'github.com') {
            return str_starts_with($path, '/' . $repo . '/releases/download/');
        }
        if ($host === 'api.github.com') {
            return str_starts_with($path, '/repos/' . $repo . '/zipball/');
        }

        return false;
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
            return is_array($cached) && $cached !== [] ? $cached : null;
        }

        $url = 'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest';

        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'SECOP-Suite-WordPress-Plugin/' . $this->current_version,
            ],
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            // Cache vacío para no reintentar inmediatamente
            set_transient(self::CACHE_KEY, [], HOUR_IN_SECONDS);
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($body) || empty($body['tag_name'])) {
            set_transient(self::CACHE_KEY, [], HOUR_IN_SECONDS);
            return null;
        }

        // Guardar solo los campos usados (el cuerpo completo puede ser grande).
        $release = [
            'tag_name'     => (string) $body['tag_name'],
            'zipball_url'  => is_string($body['zipball_url'] ?? null) ? $body['zipball_url'] : '',
            'published_at' => is_string($body['published_at'] ?? null) ? $body['published_at'] : '',
            'body'         => is_string($body['body'] ?? null) ? $body['body'] : '',
            'assets'       => array_values(array_map(
                static fn($a): array => [
                    'name'                 => is_array($a) && is_string($a['name'] ?? null) ? $a['name'] : '',
                    'state'                => is_array($a) && is_string($a['state'] ?? null) ? $a['state'] : '',
                    'browser_download_url' => is_array($a) && is_string($a['browser_download_url'] ?? null) ? $a['browser_download_url'] : '',
                ],
                is_array($body['assets'] ?? null) ? $body['assets'] : []
            )),
        ];

        set_transient(self::CACHE_KEY, $release, self::CACHE_EXPIRY);

        return $release;
    }
}
