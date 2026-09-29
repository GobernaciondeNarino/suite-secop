<?php
/**
 * Template: Seguimiento interactivo por dependencia.
 *
 * Variables disponibles:
 * - $deps  array     Lista de dependencias disponibles (vigencia actual).
 * - $sel   string    Dependencia seleccionada inicialmente (puede ser '').
 * - $cards array     IDs de los posts secop_dep_card a mostrar como gráficas.
 */
if (!defined('ABSPATH')) exit;
?>
<div class="ss-seguimiento" data-nonce="<?php echo esc_attr(wp_create_nonce('secop_dep_frontend')); ?>">

  <div class="ss-seguimiento-controls">
    <label><?php esc_html_e('Dependencia:', 'secop-suite'); ?>
      <select class="ss-dep-selector">
        <option value=""><?php esc_html_e('— Todas —', 'secop-suite'); ?></option>
        <?php foreach ($deps as $d) : ?>
          <option value="<?php echo esc_attr($d); ?>" <?php selected($sel, $d); ?>><?php echo esc_html($d); ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>

  <div class="ss-seguimiento-charts">
    <?php if (!empty($cards)) : ?>
      <?php foreach ($cards as $card_id) : ?>
        <?php echo do_shortcode('[secop_dep_chart card="' . (int) $card_id . '" dependencia="' . esc_attr($sel) . '"]'); ?>
      <?php endforeach; ?>
    <?php else : ?>
      <p class="ss-dep-no-cards"><?php esc_html_e('Cree tarjetas en el menú Contratación para mostrar gráficas aquí.', 'secop-suite'); ?></p>
    <?php endif; ?>
  </div>

  <h3><?php esc_html_e('Contratos de la dependencia', 'secop-suite'); ?></h3>
  <div class="ss-seguimiento-contratos">
    <?php echo do_shortcode('[secop_dep_contratos dependencia="' . esc_attr($sel) . '"]'); ?>
  </div>

</div>
