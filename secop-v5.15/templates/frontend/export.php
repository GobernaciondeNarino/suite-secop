<?php
/**
 * Template: Shortcode de exportación de datos
 *
 * @package SecopSuite
 *
 * Variables:
 * - $atts: Shortcode attributes (title, class)
 * - $total: Total records in database
 * - $rest_url: REST API base URL
 * - $extra_class: Extra CSS classes
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="ss-export-container<?php echo esc_attr($extra_class); ?>">
    <div class="ss-export-header">
        <div class="ss-export-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="28" height="28">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="7 10 12 15 17 10"></polyline>
                <line x1="12" y1="15" x2="12" y2="3"></line>
            </svg>
        </div>
        <div class="ss-export-info">
            <h3><?php echo esc_html($atts['title']); ?></h3>
            <p><?php printf(esc_html__('%s contratos disponibles para descarga', 'secop-suite'), '<strong>' . esc_html(number_format($total)) . '</strong>'); ?></p>
        </div>
    </div>

    <div class="ss-export-buttons">
        <!-- CSV -->
        <a href="<?php echo esc_url($rest_url . 'export/csv'); ?>" class="ss-export-btn ss-export-csv" download>
            <span class="ss-export-btn-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                </svg>
            </span>
            <span class="ss-export-btn-text">
                <strong>CSV</strong>
                <small><?php esc_html_e('Hoja de cálculo', 'secop-suite'); ?></small>
            </span>
        </a>

        <!-- TXT -->
        <a href="<?php echo esc_url($rest_url . 'export/txt'); ?>" class="ss-export-btn ss-export-txt" download>
            <span class="ss-export-btn-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                </svg>
            </span>
            <span class="ss-export-btn-text">
                <strong>TXT</strong>
                <small><?php esc_html_e('Texto plano', 'secop-suite'); ?></small>
            </span>
        </a>

        <!-- JSON API -->
        <a href="<?php echo esc_url($rest_url . 'contracts?per_page=100'); ?>" class="ss-export-btn ss-export-json" target="_blank" rel="noopener noreferrer">
            <span class="ss-export-btn-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                    <polyline points="16 18 22 12 16 6"></polyline>
                    <polyline points="8 6 2 12 8 18"></polyline>
                </svg>
            </span>
            <span class="ss-export-btn-text">
                <strong>JSON</strong>
                <small><?php esc_html_e('API en línea', 'secop-suite'); ?></small>
            </span>
        </a>
    </div>

    <div class="ss-export-api">
        <p class="ss-export-api-label"><?php esc_html_e('Endpoint API REST (JSON):', 'secop-suite'); ?></p>
        <div class="ss-export-api-url">
            <code id="ss-export-api-url"><?php echo esc_url($rest_url . 'contracts'); ?></code>
            <button type="button" class="ss-export-copy-btn" onclick="navigator.clipboard.writeText(document.getElementById('ss-export-api-url').textContent).then(function(){var b=event.target.closest('.ss-export-copy-btn');b.textContent='✓';setTimeout(function(){b.innerHTML='<svg viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\' width=\'14\' height=\'14\'><rect x=\'9\' y=\'9\' width=\'13\' height=\'13\' rx=\'2\' ry=\'2\'></rect><path d=\'M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1\'></path></svg>';},1500);})">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                </svg>
            </button>
        </div>
        <p class="ss-export-api-params">
            <?php esc_html_e('Parámetros:', 'secop-suite'); ?>
            <code>?per_page=100</code>
            <code>&page=2</code>
            <code>&anno=2024</code>
            <code>&search=texto</code>
        </p>
    </div>
</div>
