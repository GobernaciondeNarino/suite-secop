<?php
/**
 * Plugin Name: SECOP Suite
 * Plugin URI: https://github.com/GobernaciondeNarino/suite-secop
 * Description: Plugin integral para la importación, almacenamiento y visualización interactiva de datos contractuales del SECOP (Sistema Electrónico de Contratación Pública) de Colombia. Combina importación automatizada desde datos.gov.co con gráficas D3plus configurables mediante shortcodes.
 * Version: 5.19.2
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Jonnathan Bucheli Galindo - Gobernación de Nariño
 * Author URI: https://narino.gov.co
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: secop-suite
 * Domain Path: /languages
 *
 * @package SecopSuite
 */

declare(strict_types=1);

namespace SecopSuite;

if (!defined('ABSPATH')) {
    exit;
}

// ─── Guarda contra copias duplicadas ───────────────────────────
// WordPress reconoce un plugin por el nombre de su carpeta. Si esta versión se
// subió en una carpeta distinta a la instalada (p. ej. «suite-secop-main/» del
// «Download ZIP» de GitHub), quedan dos copias. El gestor de copias las reconoce
// por su encabezado y decide cuál se carga; al activar esta copia reemplaza a la
// anterior conservando los datos (ver includes/copy-manager.php). Este archivo no
// declara clases con nombre: la clase Plugin vive en includes/class-plugin.php
// para que una copia que decide no cargarse no la declare al compilarse.
if (defined('SECOP_SUITE_FILE') && SECOP_SUITE_FILE === __FILE__) {
    return; // Esta misma copia ya se cargó en esta petición.
}
$secop_suite_copias = require __DIR__ . '/includes/copy-manager.php';
if (!$secop_suite_copias->should_load()) {
    $secop_suite_copias->register_hooks(true);
    unset($secop_suite_copias);
    return;
}

// ─── Constantes ────────────────────────────────────────────────
define('SECOP_SUITE_VERSION', '5.19.2');
define('SECOP_SUITE_FILE', __FILE__);
define('SECOP_SUITE_DB_VERSION', '5.11.1');
define('SECOP_SUITE_DIR', plugin_dir_path(__FILE__));
define('SECOP_SUITE_URL', plugin_dir_url(__FILE__));
define('SECOP_SUITE_BASENAME', plugin_basename(__FILE__));
define('SECOP_SUITE_PREFIX', 'secop_suite_');

// ─── Autoload de clases ────────────────────────────────────────
spl_autoload_register(static function (string $class): void {
    $prefix = 'SecopSuite\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = SECOP_SUITE_DIR . 'includes/class-' . strtolower(str_replace('_', '-', $relative)) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// ─── Inicializar ───────────────────────────────────────────────
Plugin::get_instance();
$secop_suite_copias->register_hooks(false);
unset($secop_suite_copias);
