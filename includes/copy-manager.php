<?php
/**
 * Gestor de copias de SECOP Suite.
 *
 * WordPress reconoce un plugin por el nombre de su carpeta: si la versión nueva
 * se sube en una carpeta distinta (p. ej. el «Download ZIP» de GitHub trae
 * «suite-secop-main/»), la instala como un plugin aparte. Este gestor reconoce
 * las demás copias de SECOP Suite por su encabezado, sin importar la carpeta, y:
 *  - decide qué copia se carga (nunca una nueva antes que una antigua sin guarda,
 *    que fallaría con «Cannot declare class SecopSuite\Plugin»);
 *  - al activar la copia nueva, reemplaza a la anterior: la desactiva sin
 *    ejecutar sus hooks y retira su carpeta SIN ejecutar su uninstall.php, así
 *    que los datos y la configuración se conservan;
 *  - neutraliza el uninstall.php de las demás copias antes de que «Plugins →
 *    Eliminar» lo ejecute (el de las versiones 5.15.0–5.17.0 borraba los datos).
 *
 * Este archivo no declara clases ni funciones con nombre: devuelve una clase
 * anónima, de modo que varias copias del plugin pueden incluirlo a la vez.
 *
 * @package SecopSuite
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

return new class (dirname(__DIR__) . '/secop-suite.php') {
    /** Texto presente en el archivo principal de las copias que ya tienen guarda. */
    private const GUARD_MARKER = 'Guarda contra copias duplicadas';
    private const MAIN_FILE    = 'secop-suite.php';
    private const NOTICE_KEY   = 'secop_suite_copies_notice';
    private const ACTION       = 'secop_suite_takeover';

    private string $main;
    private string $self;
    private string $version;

    public function __construct(string $main)
    {
        $this->main    = $main;
        $this->self    = plugin_basename($main);
        $this->version = (string) (get_file_data($main, ['Version' => 'Version'])['Version'] ?? '0');
    }

    public function self_basename(): string
    {
        return $this->self;
    }

    // ── Detección ──────────────────────────────────────────────

    /**
     * Datos de otra copia a partir de su archivo principal, o null si el archivo
     * no es de SECOP Suite.
     *
     * @return array{version:string,guarded:bool,dir:string}|null
     */
    private function inspect(string $basename): ?array
    {
        if ($basename === $this->self || basename($basename) !== self::MAIN_FILE || dirname($basename) === '.') {
            return null;
        }
        $file = WP_PLUGIN_DIR . '/' . $basename;
        if (!is_file($file) || !is_readable($file)) {
            return null;
        }
        $head = get_file_data($file, ['Name' => 'Plugin Name', 'Version' => 'Version']);
        if (trim((string) ($head['Name'] ?? '')) !== 'SECOP Suite') {
            return null;
        }
        return [
            'version' => (string) ($head['Version'] ?? '0'),
            'guarded' => str_contains((string) file_get_contents($file), self::GUARD_MARKER),
            'dir'     => dirname($basename),
        ];
    }

    /** @return array<int,string> Plugins activos (del sitio y de la red). */
    private function active_plugins(): array
    {
        $active = (array) get_option('active_plugins', []);
        if (is_multisite()) {
            $active = array_merge($active, array_keys((array) get_site_option('active_sitewide_plugins', [])));
        }
        return array_values(array_filter($active, 'is_string'));
    }

    /** @return array<string,array{version:string,guarded:bool,dir:string}> Otras copias ACTIVAS. */
    public function active_copies(): array
    {
        $copies = [];
        foreach ($this->active_plugins() as $basename) {
            $info = $this->inspect($basename);
            if ($info !== null) {
                $copies[$basename] = $info;
            }
        }
        return $copies;
    }

    /** @return array<string,array{version:string,guarded:bool,dir:string,active:bool}> Todas las otras copias instaladas. */
    public function all_copies(): array
    {
        $active = $this->active_plugins();
        $copies = [];
        foreach ((array) glob(WP_PLUGIN_DIR . '/*/' . self::MAIN_FILE) as $file) {
            $basename = plugin_basename((string) $file);
            $info     = $this->inspect($basename);
            if ($info !== null) {
                $copies[$basename] = $info + ['active' => in_array($basename, $active, true)];
            }
        }
        return $copies;
    }

    /**
     * ¿Debe cargarse esta copia en esta petición?
     *  - No, si otra copia ya se cargó (SECOP_SUITE_VERSION definida).
     *  - No, si hay otra copia activa SIN guarda (5.17.0 o anterior): cargar esta
     *    primero haría que aquella fallara con un error fatal al cargarse después.
     *  - Entre copias con guarda, se carga la de versión mayor; si empatan, la
     *    primera por orden alfabético de carpeta.
     */
    public function should_load(): bool
    {
        if (defined('SECOP_SUITE_VERSION')) {
            return false;
        }
        foreach ($this->active_copies() as $basename => $info) {
            if (!$info['guarded']) {
                return false;
            }
            $cmp = version_compare($info['version'], $this->version);
            if ($cmp > 0 || ($cmp === 0 && strcmp($basename, $this->self) < 0)) {
                return false;
            }
        }
        return true;
    }

    // ── Reemplazo de la copia anterior ─────────────────────────

    /**
     * Deja activa SOLO esta copia y retira las demás sin ejecutar su
     * desinstalador. No toca la base de datos: contratos, gráficas, filtros,
     * cards y configuración se conservan.
     *
     * @return array{ok:bool,removed:array<int,string>,failed:array<int,string>,message:string}
     */
    public function takeover(): array
    {
        $result = ['ok' => false, 'removed' => [], 'failed' => [], 'message' => ''];
        if (!current_user_can('activate_plugins') || !current_user_can('delete_plugins')) {
            $result['message'] = __('Permisos insuficientes para reemplazar la copia anterior.', 'secop-suite');
            return $result;
        }

        $copies = $this->all_copies();
        if (is_multisite()) {
            $network = array_keys((array) get_site_option('active_sitewide_plugins', []));
            if (array_intersect(array_keys($copies), $network)) {
                $result['message'] = __('Hay una copia de SECOP Suite activada en toda la red: reemplácela desde la administración de la red.', 'secop-suite');
                return $result;
            }
        }

        // 1) Esta copia queda activa y las demás dejan de estarlo, sin ejecutar sus
        //    hooks de desactivación (el de las versiones antiguas borraba el cron).
        $active = array_values(array_diff((array) get_option('active_plugins', []), array_keys($copies)));
        if (!in_array($this->self, $active, true)) {
            $active[] = $this->self;
        }
        sort($active);
        update_option('active_plugins', $active);

        // 2) Retirar las carpetas de las demás copias sin ejecutar su uninstall.php.
        foreach ($copies as $basename => $info) {
            if ($this->remove_copy($basename)) {
                $result['removed'][] = $info['dir'] . ' (v' . $info['version'] . ')';
            } else {
                $result['failed'][] = $info['dir'] . ' (v' . $info['version'] . ')';
            }
        }

        $result['ok'] = empty($result['failed']);
        set_transient(self::NOTICE_KEY . '_' . get_current_user_id(), $result, 10 * MINUTE_IN_SECONDS);
        return $result;
    }

    /** Neutraliza el uninstall.php de otra copia (lo renombra) para que no borre datos. */
    private function neutralize_uninstall(string $dir): void
    {
        $uninstall = $dir . '/uninstall.php';
        if (is_file($uninstall)) {
            if (!@rename($uninstall, $uninstall . '.desactivado')) {
                @unlink($uninstall);
            }
        }
    }

    /** Borra la carpeta de otra copia (validada) sin ejecutar ninguno de sus archivos. */
    private function remove_copy(string $basename): bool
    {
        $root = realpath(WP_PLUGIN_DIR);
        $dir  = realpath(WP_PLUGIN_DIR . '/' . dirname($basename));
        $mine = realpath(dirname($this->main));
        if (!$root || !$dir || !$mine || $dir === $mine || $dir === $root
            || !str_starts_with($dir, $root . DIRECTORY_SEPARATOR)
            || $this->inspect($basename) === null) {
            return false;
        }

        // Primero se desactiva el desinstalador: aunque el borrado fallara a medias,
        // «Eliminar» ya no podría borrar los datos.
        $this->neutralize_uninstall($dir);

        require_once ABSPATH . 'wp-admin/includes/file.php';
        global $wp_filesystem;
        if (WP_Filesystem() && $wp_filesystem && $wp_filesystem->delete($dir, true)) {
            return true;
        }

        // Respaldo: borrado directo con PHP si el sistema de archivos lo permite.
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $path = $item->getPathname();
            $item->isDir() && !$item->isLink() ? @rmdir($path) : @unlink($path);
        }
        return @rmdir($dir) || !is_dir($dir);
    }

    // ── Hooks ──────────────────────────────────────────────────

    /**
     * @param bool $inactive_mode true si esta copia NO se cargó porque otra está en uso.
     */
    public function register_hooks(bool $inactive_mode): void
    {
        // «Plugins → Eliminar» sobre otra copia: desactivar antes su desinstalador.
        add_action('pre_uninstall_plugin', function ($plugin): void {
            $basename = plugin_basename((string) $plugin);
            if ($this->inspect($basename) !== null) {
                $this->neutralize_uninstall(WP_PLUGIN_DIR . '/' . dirname($basename));
            }
        });

        if ($inactive_mode) {
            // Al activar esta copia mientras otra está en uso, reemplazarla en el acto.
            add_action('activated_plugin', function ($plugin, $network_wide = false): void {
                if ($plugin === $this->self && !$network_wide) {
                    $this->takeover();
                }
            }, 10, 2);
        }

        add_action('admin_init', function (): void {
            if (($_GET[self::ACTION] ?? '') !== $this->self) {
                return;
            }
            check_admin_referer(self::ACTION);
            $this->takeover();
            wp_safe_redirect(admin_url('plugins.php'));
            exit;
        });

        add_action('admin_notices', function () use ($inactive_mode): void {
            $this->render_notices($inactive_mode);
        });
    }

    private function render_notices(bool $inactive_mode): void
    {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        $key    = self::NOTICE_KEY . '_' . get_current_user_id();
        $result = get_transient($key);
        if (is_array($result)) {
            delete_transient($key);
            if ($result['removed']) {
                echo '<div class="notice notice-success is-dismissible"><p><strong>'
                   . esc_html__('SECOP Suite: se reemplazó la copia anterior.', 'secop-suite') . '</strong> '
                   . esc_html(sprintf(__('Se retiró: %s. Los datos y la configuración se conservaron.', 'secop-suite'), implode(', ', $result['removed'])))
                   . '</p></div>';
            }
            if ($result['failed'] || (!$result['removed'] && $result['message'] !== '')) {
                echo '<div class="notice notice-error"><p><strong>SECOP Suite:</strong> '
                   . esc_html($result['message'] !== '' ? $result['message'] : sprintf(
                       __('No se pudo borrar la carpeta de: %s. Ya no está activa y su desinstalador quedó desactivado; bórrela por FTP o con el administrador de archivos del hosting.', 'secop-suite'),
                       implode(', ', $result['failed'])
                   ))
                   . '</p></div>';
            }
        }

        // Solo en la pantalla de plugins y en las del propio plugin (evita leer
        // carpetas en cada página del administrador).
        $page = sanitize_key((string) ($_GET['page'] ?? ''));
        if (($GLOBALS['pagenow'] ?? '') !== 'plugins.php' && !str_starts_with($page, 'secop-suite')) {
            return;
        }
        $copies = $this->all_copies();
        if (!$copies) {
            return;
        }

        $list = [];
        foreach ($copies as $info) {
            $list[] = 'wp-content/plugins/' . $info['dir'] . '/ (v' . $info['version'] . ($info['active'] ? ', ' . __('activa', 'secop-suite') : '') . ')';
        }
        $url = wp_nonce_url(add_query_arg(self::ACTION, rawurlencode($this->self), admin_url('plugins.php')), self::ACTION);

        echo '<div class="notice notice-warning"><p><strong>'
           . esc_html__('SECOP Suite: hay más de una copia del plugin instalada.', 'secop-suite') . '</strong> '
           . esc_html(sprintf(
               $inactive_mode
                   ? __('La versión %1$s de la carpeta %2$s está instalada, pero se sigue usando: %3$s.', 'secop-suite')
                   : __('Se está usando la versión %1$s de la carpeta %2$s. Otras copias: %3$s.', 'secop-suite'),
               $this->version,
               'wp-content/plugins/' . dirname($this->self) . '/',
               implode('; ', $list)
           ))
           . '</p><p><a class="button button-primary" href="' . esc_url($url) . '">'
           . esc_html(sprintf(__('Usar la versión %s y retirar las demás copias', 'secop-suite'), $this->version))
           . '</a> '
           . esc_html__('Los contratos, las gráficas, los filtros, las cards y la configuración se conservan: las otras copias se retiran sin ejecutar su desinstalador.', 'secop-suite')
           . '</p></div>';
    }
};
