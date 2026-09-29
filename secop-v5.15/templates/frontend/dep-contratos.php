<?php if (!defined('ABSPATH')) exit; ?>
<table class="ss-dep-contratos widefat">
  <thead><tr>
    <th><?php esc_html_e('N° Contrato', 'secop-suite'); ?></th>
    <th><?php esc_html_e('Proveedor', 'secop-suite'); ?></th>
    <th><?php esc_html_e('Inicio', 'secop-suite'); ?></th>
    <th><?php esc_html_e('Fin', 'secop-suite'); ?></th>
    <th><?php esc_html_e('Valor', 'secop-suite'); ?></th>
    <th><?php esc_html_e('Descripción', 'secop-suite'); ?></th>
  </tr></thead>
  <tbody>
  <?php if (!$rows) : ?>
    <tr><td colspan="6"><?php esc_html_e('Seleccione una dependencia.', 'secop-suite'); ?></td></tr>
  <?php else : foreach ($rows as $r) : ?>
    <tr>
      <td><?php if (!empty($r['url_contrato'])) : ?>
        <a href="<?php echo esc_url($r['url_contrato']); ?>" target="_blank" rel="noopener"><?php echo esc_html($r['numero_del_contrato']); ?></a>
      <?php else : echo esc_html($r['numero_del_contrato']); endif; ?></td>
      <td><?php echo esc_html($r['nom_raz_social_contratista']); ?></td>
      <td><?php echo esc_html(substr((string) $r['fecha_inicio_ejecucion'], 0, 10)); ?></td>
      <td><?php echo esc_html(substr((string) $r['fecha_fin_ejecucion'], 0, 10)); ?></td>
      <td><?php echo esc_html(\SecopSuite\Stats::money((float) $r['valor_contrato'])); ?></td>
      <td><?php echo esc_html(wp_trim_words((string) $r['objeto_a_contratar'], 20)); ?></td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody>
</table>
