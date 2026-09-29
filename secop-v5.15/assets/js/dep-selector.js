(function ($) {
  'use strict';

  // ── v5.12.0: Selector de filtro composable [secop_dep_selector] ─────────────
  // Un <select> independiente que fija un campo del estado compartido
  // window.SecopCoord (dependencia / modalidad / tipo_contrato). Al cambiar, llama
  // a SecopCoord.setRaw(campo, valor) — un valor vacío limpia el filtro — lo que
  // dispara `secop:coord:refresh`. Cualquier elemento suscrito (la red ego
  // [secop_dep_rings], el treemap, las listas…) se re-renderiza en consecuencia.

  $(function () {
    $(document).on('change', '.ss-dep-selector-coord', function () {
      var $el = $(this);
      var campo = $el.data('campo');
      if (!campo || !window.SecopCoord) return;
      SecopCoord.setRaw(campo, $el.val()); // '' limpia el filtro.
    });
  });
})(jQuery);
