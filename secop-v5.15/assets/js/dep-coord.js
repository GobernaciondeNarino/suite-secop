/**
 * v5.10.0 — Coordinador de filtros a nivel de página para los elementos
 * composables de Contratación (listas [secop_dep_lista] + treemap
 * [secop_dep_treemap]). Expone un único estado de filtro compartido
 * (dependencia / modalidad / tipo_contrato) y un bus de eventos sobre `document`
 * para que scripts separados se coordinen sin acoplarse: cualquier elemento que
 * cambie el estado dispara `secop:coord:refresh`, al que se suscriben todos los
 * demás. `set()` conmuta (toggle); `setRaw()` fija el valor tal cual.
 *
 * @package SecopSuite
 */
window.SecopCoord = window.SecopCoord || (function () {
  var state = { dependencia: '', modalidad: '', tipo_contrato: '' };
  var bus = window.jQuery(document);
  return {
    get: function () { return state; },
    // Conmuta: si el valor ya está activo lo limpia; si no, lo fija.
    set: function (key, val) {
      if (key in state) {
        state[key] = (state[key] === val) ? '' : val;
        bus.trigger('secop:coord:refresh');
      }
    },
    setRaw: function (key, val) {
      if (key in state) { state[key] = val || ''; bus.trigger('secop:coord:refresh'); }
    },
    clear: function () {
      state = { dependencia: '', modalidad: '', tipo_contrato: '' };
      bus.trigger('secop:coord:refresh');
    },
    onRefresh: function (cb) { bus.on('secop:coord:refresh', cb); },
    refresh: function () { bus.trigger('secop:coord:refresh'); }
  };
})();
