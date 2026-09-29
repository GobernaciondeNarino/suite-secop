(function ($) {
  'use strict';

  // ── Task 12 (v2): interactividad del selector de dependencia ───────────────
  // Chart rendering is fully delegated to Visualizer's SSChartManager (frontend.js).
  // dep-tracking.js only manages:
  //   1. Updating the data-dependencia attribute on .ss-chart-container elements and
  //      re-initialising each SSChartManager so it re-fetches data with the new filter.
  //   2. Refreshing the contracts table via the secop_dep_contratos AJAX handler.

  $(function () {
    $('.ss-seguimiento').on('change', '.ss-dep-selector', function () {
      var dep   = $(this).val();
      var $root = $(this).closest('.ss-seguimiento');

      // ── Re-init Visualizer charts with new dependencia ──────────────────────
      $root.find('.ss-chart-container').each(function () {
        var $c = $(this);

        // 1. Update the data attribute so ChartManager.loadData() reads it via .attr().
        $c.attr('data-dependencia', dep);

        // 2. Reset visual state so the loading spinner reappears.
        $c.removeClass('ss-loaded')
          .find('.ss-error-message').hide().end()
          .find('[id$="-render"]').empty();

        // 3. Re-init a fresh ChartManager — it calls loadData() which picks up
        //    the new data-dependencia and sends it to the Visualizer AJAX handler.
        if (window.SSChartManager) {
          new window.SSChartManager(this);
        }
      });

      // ── Refresh contracts table ─────────────────────────────────────────────
      $.post(secopDep.ajaxUrl, {
        action: 'secop_dep_contratos',
        nonce: secopDep.nonce,
        dependencia: dep
      }).done(function (res) {
        if (!res.success) return;
        var $tbody = $root.find('.ss-seguimiento-contratos table tbody');
        var rows   = res.data.rows;

        // Empty state
        if (!rows || !rows.length) {
          $tbody.empty().append(
            $('<tr>').append(
              $('<td>', { colspan: 6 }).text(
                (secopDep.strings && secopDep.strings.noContracts) || 'Sin contratos.'
              )
            )
          );
          return;
        }

        // Safe DOM construction — no BD value is concatenated as HTML
        var $rows = rows.map(function (r) {
          var $tr = $('<tr>');

          // Contract number: link only when URL has a valid http(s) scheme
          var $tdNum = $('<td>');
          if (typeof r.url_contrato === 'string' && /^https?:\/\//i.test(r.url_contrato)) {
            $tdNum.append(
              $('<a>', { href: r.url_contrato, target: '_blank', rel: 'noopener' })
                .text(r.numero_del_contrato)
            );
          } else {
            $tdNum.text(r.numero_del_contrato);
          }
          $tr.append($tdNum);

          $tr.append($('<td>').text(r.nom_raz_social_contratista || ''));
          $tr.append($('<td>').text(String(r.fecha_inicio_ejecucion || '').slice(0, 10)));
          $tr.append($('<td>').text(String(r.fecha_fin_ejecucion || '').slice(0, 10)));
          $tr.append($('<td>').text('$' + Math.round(r.valor_contrato || 0).toLocaleString('es-CO')));
          $tr.append($('<td>').text(String(r.objeto_a_contratar || '').slice(0, 120)));
          return $tr;
        });
        $tbody.empty().append($rows);
      });
    });
  });
})(jQuery);
