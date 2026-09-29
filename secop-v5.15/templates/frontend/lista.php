<?php
/**
 * Template: Lista composable de contratación [secop_dep_lista] (v5.8.0).
 *
 * Renderiza UNA lista independiente (dependencias / modalidades / tipos /
 * contratistas). Varias listas en la misma página coordinan un estado de filtro
 * compartido (filtrado cruzado): al hacer clic en un elemento de una lista se
 * refiltran las demás. El cuerpo lo rellena assets/js/dep-lista.js, que lee la
 * config JSON inline. Para «contratistas» el cuerpo es un acordeón de contratos.
 *
 * Variables disponibles:
 * - $tipo          string  dependencias|modalidades|tipos|contratistas.
 * - $campos        array   Campos de fila 1 validados (acordeón de contratistas).
 * - $field_labels  array   [columna => etiqueta] de explora_fields().
 * - $titulo        string  Título de la lista.
 * - $height        int     Altura máxima (px) del cuerpo desplazable.
 * - $uid           string  ID único del contenedor.
 *
 * @package SecopSuite
 */
if (!defined('ABSPATH')) {
    exit;
}

$cfg = [
    'uid'         => $uid,
    'tipo'        => $tipo,
    'campos'      => array_values($campos),
    'fieldLabels' => $field_labels,
];
?>
<div id="<?php echo esc_attr($uid); ?>" class="ss-lista" data-uid="<?php echo esc_attr($uid); ?>"
     data-tipo="<?php echo esc_attr($tipo); ?>" data-campos="<?php echo esc_attr(implode(',', $campos)); ?>">
    <script type="application/json" id="<?php echo esc_attr($uid); ?>-cfg"><?php
        echo wp_json_encode($cfg, JSON_HEX_TAG | JSON_HEX_APOS);
    ?></script>
    <h4 class="ss-lista-title"><?php echo esc_html($titulo); ?></h4>
    <div class="ss-lista-body" style="max-height:<?php echo (int) $height; ?>px">
        <div class="ss-lista-loading"><?php esc_html_e('Cargando…', 'secop-suite'); ?></div>
    </div>
</div>
