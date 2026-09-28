<?php
/**
 * Template: Página de logs
 * 
 * @package SecopSuite
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap ss-admin-wrap">
    <h1>
        <span class="dashicons dashicons-text-page"></span>
        <?php esc_html_e('Logs de Importación', 'secop-suite'); ?>
    </h1>

    <div class="ss-panel">
        <div class="ss-logs-header">
            <h2><?php esc_html_e('Registro de Actividad', 'secop-suite'); ?></h2>
            
            <?php if (!empty($logs)): ?>
                <form method="post" action="" style="display: inline;">
                    <?php wp_nonce_field('secop_suite_clear_logs', 'secop_suite_logs_nonce'); ?>
                    <input type="hidden" name="secop_suite_action" value="clear_logs" />
                    <button type="submit" class="button" onclick="return confirm('<?php esc_attr_e('¿Está seguro de limpiar los logs?', 'secop-suite'); ?>');">
                        <span class="dashicons dashicons-trash"></span>
                        <?php esc_html_e('Limpiar Logs', 'secop-suite'); ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <div class="ss-logs-container">
            <?php if (!empty($logs)): ?>
                <pre class="ss-logs-content"><?php echo esc_html($logs); ?></pre>
            <?php else: ?>
                <div class="ss-no-logs">
                    <span class="dashicons dashicons-info"></span>
                    <p><?php esc_html_e('No hay registros de actividad disponibles.', 'secop-suite'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="ss-panel">
        <h2><?php esc_html_e('Información del Sistema', 'secop-suite'); ?></h2>
        
        <table class="ss-system-info">
            <tr>
                <th><?php esc_html_e('Versión del Plugin', 'secop-suite'); ?></th>
                <td><?php echo esc_html(SECOP_SUITE_VERSION); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Versión de WordPress', 'secop-suite'); ?></th>
                <td><?php echo esc_html(get_bloginfo('version')); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Versión de PHP', 'secop-suite'); ?></th>
                <td><?php echo esc_html(PHP_VERSION); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Límite de Memoria PHP', 'secop-suite'); ?></th>
                <td><?php echo esc_html(ini_get('memory_limit')); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Tiempo Máximo de Ejecución', 'secop-suite'); ?></th>
                <td><?php echo esc_html(ini_get('max_execution_time')); ?> <?php esc_html_e('segundos', 'secop-suite'); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('WP-CLI Disponible', 'secop-suite'); ?></th>
                <td><?php echo defined('WP_CLI') ? '<span class="dashicons dashicons-yes"></span>' : '<span class="dashicons dashicons-no-alt"></span>'; ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Cron Activo', 'secop-suite'); ?></th>
                <td>
                    <?php 
                    $next_scheduled = wp_next_scheduled('ss_scheduled_import');
                    if ($next_scheduled) {
                        echo '<span class="dashicons dashicons-yes"></span> ';
                        printf(
                            esc_html__('Próxima ejecución: %s', 'secop-suite'),
                            date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_scheduled)
                        );
                    } else {
                        echo '<span class="dashicons dashicons-no-alt"></span> ';
                        esc_html_e('No programado', 'secop-suite');
                    }
                    ?>
                </td>
            </tr>
        </table>
    </div>
</div>

<?php
