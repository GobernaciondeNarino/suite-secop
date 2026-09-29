<?php
/**
 * Template: Configuración — contenedor con pestañas.
 *
 * Agrupa en una sola página los antiguos submenús Importar Datos, Registros,
 * Depuración BD y Logs, más la pestaña Respaldos. Cada pestaña imprime su propio contenido (sin .wrap ni h1).
 *
 * Variables inyectadas por Plugin::render_config_page():
 * - $tabs       : array<string,string> slug => etiqueta, en orden
 * - $active_tab : string   pestaña activa (validada contra $tabs)
 * - $render_tab : callable imprime el contenido de la pestaña activa
 *
 * @package SecopSuite
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap ss-admin-wrap ss-config">
    <h1>
        <span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
        <?php esc_html_e('Configuración', 'secop-suite'); ?>
        <span class="ss-version">v<?php echo esc_html(SECOP_SUITE_VERSION); ?></span>
    </h1>
    <hr class="wp-header-end">

    <nav class="nav-tab-wrapper ss-config-tabs" aria-label="<?php esc_attr_e('Secciones de configuración', 'secop-suite'); ?>">
        <?php foreach ($tabs as $slug => $label) : ?>
            <?php $is_active = ($slug === $active_tab); ?>
            <a href="<?php echo esc_url(\SecopSuite\Plugin::config_url($slug)); ?>"
               class="nav-tab<?php echo $is_active ? ' nav-tab-active' : ''; ?>"<?php echo $is_active ? ' aria-current="page"' : ''; ?>>
                <?php echo esc_html($label); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="ss-config-panel ss-config-panel-<?php echo esc_attr($active_tab); ?>">
        <?php $render_tab(); ?>
    </div>
</div>
