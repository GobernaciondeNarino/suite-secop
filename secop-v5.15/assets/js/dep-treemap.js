(function ($) {
  'use strict';

  // ── v5.10.0: Treemap composable de contratación [secop_dep_treemap] ─────────
  // Treemap independiente (d3plus.Treemap) que comparte el estado de filtro a
  // nivel de página con las listas [secop_dep_lista] y otros treemaps mediante el
  // coordinador window.SecopCoord (assets/js/dep-coord.js). Al hacer clic en una
  // celda se conmuta el filtro de la dimensión correspondiente (dependencia /
  // modalidad / tipo_contrato) y el bus dispara `secop:coord:refresh`, al que
  // están suscritos todos los elementos → filtrado cruzado. La dimensión
  // «contratistas» no participa como filtro (stateField vacío). TODAS las cadenas
  // de BD se insertan con .text()/escapadas — nunca innerHTML de datos.

  function money(v) {
    return '$' + Math.round(Number(v) || 0).toLocaleString('es-CO');
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }

  // d3plus envuelve cada dato como { __d3plus__: true, data: <nodo> }.
  function unwrap(d) {
    return (d && d.__d3plus__ && d.data) ? d.data : d;
  }

  function strings() {
    return (window.secopDep && secopDep.strings) || {};
  }

  function coord() { return window.SecopCoord; }

  function readConfig($wrap, uid) {
    try {
      var cfg = JSON.parse($wrap.find('#' + uid + '-cfg').text());
      return {
        uid: cfg.uid || uid,
        dimension: cfg.dimension || 'dependencias',
        dimColumn: cfg.dimColumn || 'nombredependencia',
        stateField: cfg.stateField || '',
        metric: cfg.metric === 'contratos' ? 'contratos' : 'valor_contrato',
        colors: (Array.isArray(cfg.colors) && cfg.colors.length)
          ? cfg.colors
          : ['#844e80', '#ff7300', '#ffc53b', '#3eba6a', '#0080c3', '#e74c3c', '#9b59b6', '#1abc9c'],
        legend: cfg.legend !== false,
        legendmode: cfg.legendmode === 'icono' ? 'icono' : 'texto',
        toolbar: cfg.toolbar !== false,
        csvUrl: cfg.csvUrl || '',
        limit: parseInt(cfg.limit, 10) || 0
      };
    } catch (e) {
      return {
        uid: uid, dimension: 'dependencias', dimColumn: 'nombredependencia', stateField: '',
        metric: 'valor_contrato', colors: ['#844e80', '#ff7300', '#ffc53b', '#3eba6a', '#0080c3', '#e74c3c', '#9b59b6', '#1abc9c'],
        legend: true, legendmode: 'texto', toolbar: true, csvUrl: '', limit: 0
      };
    }
  }

  // Escala ordinal de color sobre la paleta configurada (estable por id).
  function makeScale(colors) {
    if (window.d3 && typeof window.d3.scaleOrdinal === 'function') {
      return window.d3.scaleOrdinal().range(colors);
    }
    // Fallback determinista si d3 no expone scaleOrdinal.
    var assigned = {}, i = 0;
    return function (id) {
      var k = String(id);
      if (!(k in assigned)) { assigned[k] = colors[i % colors.length]; i++; }
      return assigned[k];
    };
  }

  // ── Render ──────────────────────────────────────────────────────────────────
  function render($wrap) {
    var cfg = $wrap.data('ssCtreeCfg');
    if (!cfg) return;
    var $chart = $wrap.find('.ss-ctree-chart');

    if (!window.d3plus || typeof window.d3plus.Treemap !== 'function') {
      $chart.empty().text('No se pudo cargar la librería de visualización (d3plus.Treemap).');
      return;
    }

    // DRIVER: el treemap siempre muestra TODAS sus celdas (no se filtra por las
    // selecciones de los demás elementos), de modo que clic en una lista no lo recarga.
    var payload = {
      action: 'secop_dep_treemap',
      nonce: secopDep.nonce,
      dimension: cfg.dimension,
      limit: cfg.limit
    };

    $.post(secopDep.ajaxUrl, payload).done(function (res) {
      if (!res || !res.success || !res.data || !res.data.rows) {
        $chart.empty().text(strings().noData || 'No hay datos.');
        clearLegend($wrap);
        return;
      }
      $wrap.data('ssCtreeRows', res.data.rows || []); // cache para re-dibujar el resalte sin AJAX.
      draw($wrap, cfg, res.data.rows || []);
    }).fail(function () {
      $chart.empty().text(strings().error || 'Error al cargar los datos.');
      clearLegend($wrap);
    });
  }

  function draw($wrap, cfg, rows) {
    var $chart = $wrap.find('.ss-ctree-chart');
    if (!rows.length) {
      $chart.empty().text(strings().noData || 'No hay datos.');
      clearLegend($wrap);
      return;
    }
    $chart.empty();

    var scale = makeScale(cfg.colors);
    var useCount = cfg.metric === 'contratos';

    var data = rows.map(function (r) {
      var valor = +r.valor || 0;
      var conteo = +r.conteo || 0;
      return { id: String(r.label == null ? 'N/D' : r.label), value: useCount ? conteo : valor, valor: valor, conteo: conteo };
    });

    // Resalte: si hay una selección activa en el propio campo, atenúa el resto.
    var st = coord() ? coord().get() : {};
    var active = cfg.stateField ? (st[cfg.stateField] || '') : '';

    new window.d3plus.Treemap()
      .select($chart.get(0))
      .data(data)
      .groupBy('id')
      .sum('value')
      .legend(false) // leyenda propia (HTML) en .ss-ctree-legend para control total.
      .label(function (d) { d = unwrap(d); return d.id; })
      .shapeConfig({
        fill: function (d) { d = unwrap(d); return scale(d.id); },
        fillOpacity: function (d) {
          d = unwrap(d);
          return (active !== '' && d.id !== active) ? 0.35 : 1;
        }
      })
      .tooltipConfig({
        title: function (d) { d = unwrap(d); return esc(d.id); },
        tbody: function (d) {
          d = unwrap(d);
          return [
            ['Valor', esc(money(d.valor))],
            ['Contratos', esc(d.conteo || 0)]
          ];
        }
      })
      .on('click', function (d) {
        d = unwrap(d);
        if (cfg.stateField && d && d.id && coord()) {
          coord().set(cfg.stateField, d.id); // toggle del filtro a nivel de página (filtra a los demás).
          // Re-dibuja SOLO este treemap con las filas cacheadas para reflejar el
          // resalte de la celda activa — sin AJAX y sin depender del bus.
          var rows = $wrap.data('ssCtreeRows') || [];
          setTimeout(function () { draw($wrap, cfg, rows); }, 0);
        }
      })
      .render();

    buildLegend($wrap, cfg, data, scale);
  }

  // ── Leyenda HTML (mostrar/ocultar + icono / icono+texto) ────────────────────
  function clearLegend($wrap) {
    $wrap.find('.ss-ctree-legend').empty();
  }

  function buildLegend($wrap, cfg, data, scale) {
    var $legend = $wrap.find('.ss-ctree-legend');
    if (!$legend.length || !cfg.legend) { return; }
    $legend.empty();
    data.forEach(function (d) {
      var $item = $('<span>', { 'class': 'ss-ctree-leg-item' });
      $('<span>', { 'class': 'ss-ctree-leg-dot' })
        .css('background-color', scale(d.id)).appendTo($item);
      if (cfg.legendmode !== 'icono') {
        $('<span>', { 'class': 'ss-ctree-leg-label', text: d.id }).appendTo($item);
      } else {
        $item.attr('title', d.id);
      }
      $legend.append($item);
    });
  }

  // ── Init + toolbar + suscripción al coordinador ─────────────────────────────
  function init(wrapper) {
    var $wrap = $(wrapper);
    if ($wrap.data('ssCtreeInit')) return;
    $wrap.data('ssCtreeInit', true);
    var uid = $wrap.attr('data-uid');
    var cfg = readConfig($wrap, uid);
    $wrap.data('ssCtreeCfg', cfg);

    // El treemap es un DRIVER unidireccional: filtra a los demás elementos al hacer
    // clic, pero NO reacciona a los cambios de los demás (NO se suscribe a
    // secop:coord:refresh) → no se recarga cuando cambian las listas u otros treemaps.
    render($wrap);
  }

  $(function () {
    var $trees = $('.ss-ctree-wrapper');
    if (!$trees.length) return;
    $trees.each(function () { init(this); });

    // Barra de herramientas (delegada): datos (CSV vista completa), imagen (PNG), limpiar.
    $(document).on('click', '.ss-ctree-wrapper .ss-toolbar-btn', function () {
      var $wrap = $(this).closest('.ss-ctree-wrapper');
      var cfg = $wrap.data('ssCtreeCfg') || {};
      var action = $(this).attr('data-action');

      if (action === 'download') {
        if (cfg.csvUrl) window.open(cfg.csvUrl, '_blank');
      } else if (action === 'image') {
        var el = $wrap.find('.ss-ctree-chart').get(0);
        if (el && typeof window.html2canvas === 'function') {
          window.html2canvas(el, { backgroundColor: '#ffffff', scale: 2, logging: false }).then(function (canvas) {
            var a = document.createElement('a');
            a.download = 'treemap-' + (cfg.dimension || 'contratacion') + '.png';
            a.href = canvas.toDataURL('image/png');
            a.click();
          });
        }
      } else if (action === 'clear') {
        if (coord()) coord().clear();
      }
    });
  });
})(jQuery);
