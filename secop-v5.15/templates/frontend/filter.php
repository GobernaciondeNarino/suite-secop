<?php
/**
 * Template: Renderizado de filtro en el frontend
 *
 * @package SecopSuite
 *
 * Variables disponibles:
 * - $unique_id: ID único del contenedor
 * - $filter_id: ID del post del filtro
 * - $config: Configuración del filtro
 * - $extra_class: Clases CSS adicionales
 */

if (!defined('ABSPATH')) {
    exit;
}

$filter_title = get_the_title($filter_id);
$fields = $config['fields'] ?? [];
$show_url_link = !empty($config['show_url_link']);
$url_field = $config['url_field'] ?? 'url_contrato';
$result_columns = $config['result_columns'] ?? [];
?>

<div id="<?php echo esc_attr($unique_id); ?>"
     class="ss-filter-container<?php echo esc_attr($extra_class); ?>"
     data-filter-id="<?php echo esc_attr($filter_id); ?>">

    <!-- Formulario de Filtros -->
    <div class="ss-filter-form-wrapper">
        <form class="ss-filter-form" data-filter-id="<?php echo esc_attr($filter_id); ?>">
            <div class="ss-filter-fields">
                <?php foreach ($fields as $field): ?>
                    <div class="ss-filter-field-group" data-field-type="<?php echo esc_attr($field['type']); ?>">
                        <label class="ss-filter-label">
                            <?php echo esc_html($field['label'] ?: ucfirst(str_replace('_', ' ', $field['column']))); ?>
                        </label>

                        <?php if ($field['type'] === 'input'): ?>
                            <input type="text"
                                   name="<?php echo esc_attr($field['column']); ?>"
                                   class="ss-filter-input"
                                   placeholder="<?php echo esc_attr($field['placeholder'] ?: ''); ?>" />

                        <?php elseif ($field['type'] === 'select'): ?>
                            <select name="<?php echo esc_attr($field['column']); ?>" class="ss-filter-select" data-column="<?php echo esc_attr($field['column']); ?>">
                                <option value=""><?php echo esc_html($field['placeholder'] ?: __('-- Todos --', 'secop-suite')); ?></option>
                                <?php foreach ($field['options'] ?? [] as $opt): ?>
                                    <option value="<?php echo esc_attr($opt); ?>"><?php echo esc_html($opt); ?></option>
                                <?php endforeach; ?>
                            </select>

                        <?php elseif ($field['type'] === 'range'): ?>
                            <div class="ss-filter-range">
                                <input type="text"
                                       name="<?php echo esc_attr($field['column']); ?>_from"
                                       class="ss-filter-input ss-filter-range-from"
                                       placeholder="<?php esc_attr_e('Desde', 'secop-suite'); ?>" />
                                <span class="ss-filter-range-sep">&ndash;</span>
                                <input type="text"
                                       name="<?php echo esc_attr($field['column']); ?>_to"
                                       class="ss-filter-input ss-filter-range-to"
                                       placeholder="<?php esc_attr_e('Hasta', 'secop-suite'); ?>" />
                            </div>

                        <?php elseif ($field['type'] === 'checkbox'): ?>
                            <div class="ss-filter-checkboxes" data-column="<?php echo esc_attr($field['column']); ?>">
                                <?php if (!empty($field['options'])): ?>
                                    <?php foreach ($field['options'] as $opt): ?>
                                        <label class="ss-filter-checkbox-label">
                                            <input type="checkbox" name="<?php echo esc_attr($field['column']); ?>[]" value="<?php echo esc_attr($opt); ?>" />
                                            <span><?php echo esc_html($opt); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="ss-filter-loading-options"><?php esc_html_e('Cargando opciones...', 'secop-suite'); ?></span>
                                <?php endif; ?>
                            </div>

                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="ss-filter-actions">
                <button type="submit" class="ss-btn ss-btn-primary ss-filter-search-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <?php esc_html_e('Buscar', 'secop-suite'); ?>
                </button>
                <button type="reset" class="ss-btn ss-btn-secondary ss-filter-clear-btn">
                    <?php esc_html_e('Limpiar', 'secop-suite'); ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Resultados -->
    <div class="ss-filter-results" style="display: none;">
        <div class="ss-filter-results-header">
            <span class="ss-filter-results-count"></span>
        </div>

        <div class="ss-filter-results-table-wrapper">
            <table class="ss-filter-results-table">
                <thead></thead>
                <tbody></tbody>
            </table>
        </div>

        <div class="ss-filter-pagination"></div>
    </div>

    <!-- Loading -->
    <div class="ss-filter-loading" style="display: none;">
        <div class="ss-spinner"></div>
        <p><?php esc_html_e('Buscando...', 'secop-suite'); ?></p>
    </div>

    <!-- No Results -->
    <div class="ss-filter-no-results" style="display: none;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="48" height="48" style="color: #adb5bd;">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            <line x1="8" y1="11" x2="14" y2="11"></line>
        </svg>
        <p><?php esc_html_e('No se encontraron resultados', 'secop-suite'); ?></p>
    </div>
</div>

<!-- Configuración JSON para JavaScript -->
<script type="application/json" id="<?php echo esc_attr($unique_id); ?>-config">
<?php echo wp_json_encode([
    'filterId'      => $filter_id,
    'uniqueId'      => $unique_id,
    'tableName'     => $config['table_name'],
    'fields'        => $fields,
    'resultColumns' => $result_columns,
    'showUrlLink'   => $show_url_link,
    'urlField'      => $url_field,
    'filterNonce'   => wp_create_nonce('secop_suite_filter'),
], JSON_HEX_TAG | JSON_HEX_APOS); ?>
</script>
