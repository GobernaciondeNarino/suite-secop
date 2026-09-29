(function ($) {
  'use strict';

  // ── v5.1.9: click-to-drill ────────────────────────────────────────────────
  // window.SSChartDrill(column, value) → consulta los contratos asociados al
  // valor de una dimensión (click en barra/elemento) y los muestra en un popup.
  // Reutiliza el objeto localizado secopDep (ajaxUrl + nonce secop_dep_frontend).

  function strings() {
    return (typeof secopDep !== 'undefined' && secopDep.strings) || {};
  }

  function closeModal() {
    $('.ss-drill-modal').remove();
    $(document).off('keyup.ssdrill');
  }

  function buildTable(rows) {
    var $table = $('<table>', { 'class': 'ss-data-table' });
    var $thead = $('<thead>');
    var $htr = $('<tr>');
    ['Nº contrato', 'Contratista', 'Inicio', 'Fin', 'Valor', 'Objeto'].forEach(function (h) {
      $htr.append($('<th>').text(h));
    });
    $thead.append($htr);
    $table.append($thead);

    var $tbody = $('<tbody>');
    rows.forEach(function (r) {
      var $tr = $('<tr>');

      // Nº de contrato: enlace solo si la URL tiene esquema http(s) válido.
      var $tdNum = $('<td>');
      if (typeof r.url_contrato === 'string' && /^https?:\/\//i.test(r.url_contrato)) {
        $tdNum.append(
          $('<a>', { href: r.url_contrato, target: '_blank', rel: 'noopener' })
            .text(r.numero_del_contrato)
        );
      } else {
        $tdNum.text(r.numero_del_contrato || '');
      }
      $tr.append($tdNum);

      $tr.append($('<td>').text(r.nom_raz_social_contratista || ''));
      $tr.append($('<td>').text(String(r.fecha_inicio_ejecucion || '').slice(0, 10)));
      $tr.append($('<td>').text(String(r.fecha_fin_ejecucion || '').slice(0, 10)));
      $tr.append($('<td>').text('$' + Math.round(r.valor_contrato || 0).toLocaleString('es-CO')));
      $tr.append($('<td>').text(String(r.objeto_a_contratar || '').slice(0, 160)));
      $tbody.append($tr);
    });
    $table.append($tbody);
    return $table;
  }

  function openModal(value, rows) {
    closeModal();

    var $overlay = $('<div>', { 'class': 'ss-modal-overlay' });
    var $content = $('<div>', { 'class': 'ss-modal-content' });

    var $header = $('<div>', { 'class': 'ss-modal-header' });
    $header.append($('<h3>').text(String(value)));
    var $close = $('<button>', { type: 'button', 'class': 'ss-modal-close' }).html('&times;');
    $header.append($close);
    $content.append($header);

    var $body = $('<div>', { 'class': 'ss-modal-body' });
    if (!rows || !rows.length) {
      $body.append(
        $('<p>', { 'class': 'ss-drill-empty' }).text(
          strings().noContracts || 'Sin contratos asociados'
        )
      );
    } else {
      var $wrap = $('<div>', { 'class': 'ss-data-table-wrapper' });
      $wrap.append(buildTable(rows));
      $body.append($wrap);
    }
    $content.append($body);

    var $modal = $('<div>', {
      'class': 'ss-modal ss-drill-modal',
      role: 'dialog',
      'aria-modal': 'true'
    });
    $modal.append($overlay).append($content);
    $('body').append($modal);

    $close.on('click', closeModal);
    $overlay.on('click', closeModal);
    $(document).off('keyup.ssdrill').on('keyup.ssdrill', function (e) {
      if (e.key === 'Escape') closeModal();
    });
  }

  window.SSChartDrill = function (column, value) {
    if (!column || value === undefined || value === null) return;
    if (typeof secopDep === 'undefined') return;

    $.post(secopDep.ajaxUrl, {
      action: 'secop_dep_drill',
      nonce: secopDep.nonce,
      column: column,
      value: value
    }).done(function (res) {
      if (!res || !res.success) {
        openModal(value, []);
        return;
      }
      openModal(value, (res.data && res.data.rows) || []);
    }).fail(function () {
      openModal(value, []);
    });
  };
})(jQuery);
