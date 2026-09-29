(function ($) {
  'use strict';

  // ── v5.7.0: Predicción de contratación — d3plus.LinePlot ───────────────────
  // Evolución mensual del valor contratado (según el MES DEL CONTRATO, columna
  // `fecha` DD/MM/YYYY) con una línea de proyección PUNTEADA a fin de vigencia.
  // Datos vía AJAX (secop_dep_prediccion). La serie "Proyectado" se dibuja con
  // strokeDasharray; la serie "Observado" con línea continua. Los textos de la
  // metainformación se insertan con .text() (nunca innerHTML).

  var MESES = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

  function monthName(m) {
    m = parseInt(m, 10);
    return (m >= 1 && m <= 12) ? MESES[m - 1] : String(m);
  }

  function fmtMoney(v) {
    return '$' + Math.round(Number(v) || 0).toLocaleString('es-CO');
  }

  // Eje Y abreviado (millones / miles) para no saturar las marcas.
  function fmtY(v) {
    v = Number(v) || 0;
    var abs = Math.abs(v);
    if (abs >= 1e9) return '$' + (v / 1e9).toLocaleString('es-CO', { maximumFractionDigits: 1 }) + ' mil M';
    if (abs >= 1e6) return '$' + (v / 1e6).toLocaleString('es-CO', { maximumFractionDigits: 1 }) + ' M';
    if (abs >= 1e3) return '$' + Math.round(v / 1e3).toLocaleString('es-CO') + ' mil';
    return fmtMoney(v);
  }

  function fmtR2(r2) {
    if (r2 === null || r2 === undefined) return '—';
    return Number(r2).toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function render(wrapper) {
    var $wrap = $(wrapper);
    var $chart = $wrap.find('.ss-pred-chart');
    var $meta = $wrap.find('.ss-pred-meta');

    if (!window.d3plus || typeof window.d3plus.LinePlot !== 'function') {
      $chart.empty().text('No se pudo cargar la librería de visualización (d3plus.LinePlot).');
      $meta.empty();
      return;
    }

    var dependencia = $wrap.attr('data-dependencia') || '';

    $chart.empty().append(
      $('<div>', { 'class': 'ss-pred-loading', text: 'Cargando proyección…' })
    );
    $meta.empty();

    $.post(secopDep.ajaxUrl, {
      action: 'secop_dep_prediccion',
      nonce: secopDep.nonce,
      dependencia: dependencia
    }).done(function (res) {
      if (!res || !res.success || !res.data) {
        $chart.empty().text((secopDep.strings && secopDep.strings.noData) || 'No hay datos.');
        return;
      }
      draw($wrap, $chart, $meta, res.data);
    }).fail(function () {
      $chart.empty().text((secopDep.strings && secopDep.strings.error) || 'Error al cargar los datos.');
    });
  }

  function draw($wrap, $chart, $meta, payload) {
    var points = payload.points || [];
    var meta = payload.meta || {};

    if (!points.length) {
      $chart.empty().text((secopDep.strings && secopDep.strings.noData) || 'No hay datos.');
    } else {
      var data = points.map(function (p) {
        return { x: monthName(p.mes), y: +p.valor, serie: p.serie };
      });

      $chart.empty();
      var target = $chart.get(0);

      new window.d3plus.LinePlot()
        .select(target)
        .data(data)
        .groupBy('serie')
        .x('x')
        .y('y')
        .xConfig({ title: 'Mes' })
        .yConfig({ title: 'Valor contratado', tickFormat: fmtY })
        .color(function (d) { return d.serie === 'Proyectado' ? '#ff7300' : '#0080c3'; })
        .shapeConfig({
          Line: {
            strokeDasharray: function (d) { return d.serie === 'Proyectado' ? '10' : '0'; }
          }
        })
        .tooltipConfig({
          title: function (d) { return d.serie; },
          tbody: function (d) { return [['Mes', d.x], ['Valor', fmtMoney(d.y)]]; }
        })
        .legend(true)
        .render();
    }

    // Metainformación de la regresión (texto seguro vía .text()).
    if (meta.insufficient) {
      $meta.text('Datos insuficientes para proyectar (se requieren ≥2 meses con datos).');
    } else {
      $meta.text(
        'Proyección de cierre de vigencia ' + meta.vigencia + ': ' +
        fmtMoney(meta.cierre) + ' · R²=' + fmtR2(meta.r2)
      );
    }
  }

  $(function () {
    $('.ss-pred-wrapper').each(function () { render(this); });

    // Selector de dependencia: actualiza el wrapper y vuelve a cargar.
    $(document).on('change', '.ss-pred-selector', function () {
      var dep = $(this).val();
      var $wrap = $(this).closest('.ss-pred-module').find('.ss-pred-wrapper');
      $wrap.attr('data-dependencia', dep);
      render($wrap.get(0));
    });
  });
})(jQuery);
