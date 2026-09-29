<?php
if (!defined('ABSPATH')) exit;
?>
<div class="wrap ss-admin-wrap">
    <h1><span class="dashicons dashicons-chart-area"></span> <?php esc_html_e('SECOP Suite', 'secop-suite'); ?> <span class="ss-version">v<?php echo esc_html(SECOP_SUITE_VERSION); ?></span></h1>
    <div class="ss-dashboard">
        <div class="ss-cards">
            <div class="ss-card"><div class="ss-card-icon"><span class="dashicons dashicons-media-text"></span></div><div class="ss-card-content"><h3><?php echo esc_html(number_format($total_records)); ?></h3><p><?php esc_html_e('Contratos Registrados', 'secop-suite'); ?></p></div></div>
            <div class="ss-card"><div class="ss-card-icon"><span class="dashicons dashicons-money-alt"></span></div><div class="ss-card-content"><h3>$<?php echo esc_html(number_format($total_value, 0, ',', '.')); ?></h3><p><?php esc_html_e('Valor Total Contratos', 'secop-suite'); ?></p></div></div>
            <div class="ss-card"><div class="ss-card-icon"><span class="dashicons dashicons-clock"></span></div><div class="ss-card-content"><h3><?php echo $last_import ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_import))) : esc_html__('Nunca', 'secop-suite'); ?></h3><p><?php esc_html_e('Última Importación', 'secop-suite'); ?></p></div></div>
            <div class="ss-card <?php echo $is_importing ? 'ss-card-warning' : 'ss-card-success'; ?>"><div class="ss-card-icon"><span class="dashicons dashicons-<?php echo $is_importing ? 'update' : 'yes-alt'; ?>"></span></div><div class="ss-card-content"><h3><?php echo $is_importing ? esc_html__('En Proceso', 'secop-suite') : esc_html__('Listo', 'secop-suite'); ?></h3><p><?php esc_html_e('Estado del Sistema', 'secop-suite'); ?></p></div></div>
        </div>
        <div class="ss-panel">
            <h2><span class="dashicons dashicons-update"></span> <?php esc_html_e('Importar Datos', 'secop-suite'); ?></h2>
            <div id="ss-import-section">
                <p class="description"><?php esc_html_e('Inicia una importación manual de datos desde la API del SECOP.', 'secop-suite'); ?></p>
                <div id="ss-progress-container" style="display:none;"><div class="ss-progress-bar"><div class="ss-progress-fill" id="ss-progress-fill"></div></div><p id="ss-progress-text" class="ss-progress-text"></p></div>
                <div id="ss-import-result" class="ss-notice" style="display:none;"></div>
                <div class="ss-button-group">
                    <button type="button" id="ss-start-import" class="button button-primary button-large" <?php echo $is_importing ? 'disabled' : ''; ?>><span class="dashicons dashicons-download"></span> <?php esc_html_e('Iniciar Importación', 'secop-suite'); ?></button>
                    <button type="button" id="ss-cancel-import" class="button button-secondary button-large" style="display:none;"><span class="dashicons dashicons-no"></span> <?php esc_html_e('Cancelar', 'secop-suite'); ?></button>
                    <button type="button" id="ss-truncate-table" class="button button-link-delete button-large" style="margin-left:auto;"><span class="dashicons dashicons-trash"></span> <?php esc_html_e('Limpiar Tabla', 'secop-suite'); ?></button>
                </div>
                <!-- Truncate confirmation -->
                <div id="ss-truncate-confirm" class="ss-notice ss-notice-error" style="display:none; margin-top:15px;">
                    <p><strong><?php esc_html_e('¿Está seguro de eliminar TODOS los datos importados?', 'secop-suite'); ?></strong></p>
                    <p><?php esc_html_e('Esta acción eliminará todos los registros de la tabla de contratos. No se puede deshacer.', 'secop-suite'); ?></p>
                    <p>
                        <strong><?php esc_html_e('Total de registros:', 'secop-suite'); ?></strong> <?php echo esc_html(number_format($total_records)); ?>
                    </p>
                    <div class="ss-button-group" style="margin-top:10px;">
                        <button type="button" id="ss-truncate-confirm-btn" class="button button-primary" style="background:#d63638; border-color:#d63638;"><span class="dashicons dashicons-warning"></span> <?php esc_html_e('Sí, eliminar todos los datos', 'secop-suite'); ?></button>
                        <button type="button" id="ss-truncate-cancel-btn" class="button button-secondary"><?php esc_html_e('Cancelar', 'secop-suite'); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <div class="ss-panel">
            <h2><span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e('Configuración', 'secop-suite'); ?></h2>
            <form method="post" action="options.php" class="ss-settings-form">
                <?php settings_fields('secop_suite_settings'); ?>
                <table class="form-table">
                    <tr><th><label for="secop_suite_api_url"><?php esc_html_e('URL de la API', 'secop-suite'); ?></label></th><td><input type="url" id="secop_suite_api_url" name="secop_suite_api_url" value="<?php echo esc_attr(get_option('secop_suite_api_url')); ?>" class="large-text code" /><p class="description"><?php esc_html_e('URL base del endpoint JSON de la API del SECOP (datos.gov.co)', 'secop-suite'); ?></p></td></tr>
                    <tr><th><label for="secop_suite_nit_entidad"><?php esc_html_e('NIT de la Entidad', 'secop-suite'); ?></label></th><td><input type="text" id="secop_suite_nit_entidad" name="secop_suite_nit_entidad" value="<?php echo esc_attr(get_option('secop_suite_nit_entidad', '800103923')); ?>" class="regular-text" /><p class="description"><?php esc_html_e('NIT de la entidad para filtrar contratos', 'secop-suite'); ?></p></td></tr>
                    <tr><th><label><?php esc_html_e('Rango de Fechas', 'secop-suite'); ?></label></th><td><div class="ss-date-range"><input type="date" name="secop_suite_fecha_inicio" value="<?php echo esc_attr(get_option('secop_suite_fecha_inicio', '2016-01-01')); ?>" /><span><?php esc_html_e('hasta', 'secop-suite'); ?></span><input type="date" name="secop_suite_fecha_fin" value="<?php echo esc_attr(get_option('secop_suite_fecha_fin', date('Y-12-31'))); ?>" /></div></td></tr>
                    <tr><th><label><?php esc_html_e('Actualización Automática', 'secop-suite'); ?></label></th><td><label class="ss-toggle"><input type="checkbox" id="ss_auto_update_enabled" name="secop_suite_auto_update_enabled" value="1" <?php checked(get_option('secop_suite_auto_update_enabled'), true); ?> /><span class="ss-toggle-slider"></span></label><span class="description"><?php esc_html_e('Habilitar actualizaciones automáticas programadas', 'secop-suite'); ?></span></td></tr>
                    <tr class="ss-auto-update-options" style="<?php echo get_option('secop_suite_auto_update_enabled') ? '' : 'display:none;'; ?>"><th><label><?php esc_html_e('Frecuencia', 'secop-suite'); ?></label></th><td><select name="secop_suite_auto_update_frequency"><option value="daily" <?php selected(get_option('secop_suite_auto_update_frequency'), 'daily'); ?>><?php esc_html_e('Diario', 'secop-suite'); ?></option><option value="weekly" <?php selected(get_option('secop_suite_auto_update_frequency'), 'weekly'); ?>><?php esc_html_e('Semanal', 'secop-suite'); ?></option><option value="monthly" <?php selected(get_option('secop_suite_auto_update_frequency'), 'monthly'); ?>><?php esc_html_e('Mensual', 'secop-suite'); ?></option></select></td></tr>
                </table>
                <?php submit_button(__('Guardar Configuración', 'secop-suite')); ?>
            </form>
        </div>
        <div class="ss-panel">
            <h2><span class="dashicons dashicons-rest-api"></span> <?php esc_html_e('API REST', 'secop-suite'); ?></h2>
            <div class="ss-api-docs">
                <div class="ss-api-endpoint"><code>GET /wp-json/secop-suite/v1/contracts</code> <span class="description"><?php esc_html_e('Lista de contratos con paginación', 'secop-suite'); ?></span></div>
                <div class="ss-api-endpoint"><code>GET /wp-json/secop-suite/v1/contracts/{id}</code> <span class="description"><?php esc_html_e('Detalle de un contrato', 'secop-suite'); ?></span></div>
                <div class="ss-api-endpoint"><code>GET /wp-json/secop-suite/v1/stats</code> <span class="description"><?php esc_html_e('Estadísticas generales', 'secop-suite'); ?></span></div>
                <div class="ss-api-endpoint"><code>GET /wp-json/secop-suite/v1/chart/{id}/data</code> <span class="description"><?php esc_html_e('Datos de una gráfica', 'secop-suite'); ?></span></div>
                <div class="ss-api-endpoint"><code>GET /wp-json/secop-suite/v1/chart/{id}/csv</code> <span class="description"><?php esc_html_e('Descargar CSV', 'secop-suite'); ?></span></div>
            </div>
        </div>
        <div class="ss-panel">
            <h2><span class="dashicons dashicons-editor-code"></span> <?php esc_html_e('Comandos WP-CLI', 'secop-suite'); ?></h2>
            <div class="ss-cli-commands">
                <div class="ss-cli-command"><code>wp secop import</code> <span class="description"><?php esc_html_e('Ejecutar importación', 'secop-suite'); ?></span></div>
                <div class="ss-cli-command"><code>wp secop import --nit=800103923 --desde=2020-01-01 --hasta=2024-12-31</code></div>
                <div class="ss-cli-command"><code>wp secop stats</code> <span class="description"><?php esc_html_e('Estadísticas', 'secop-suite'); ?></span></div>
                <div class="ss-cli-command"><code>wp secop truncate --yes</code> <span class="description"><?php esc_html_e('Eliminar datos', 'secop-suite'); ?></span></div>
            </div>
        </div>
    </div>
</div>
