(function ($) {
  'use strict';

  // ── v5.8.0: Listas composables de contratación [secop_dep_lista] ────────────
  // Cada shortcode renderiza UNA lista independiente (dependencias / modalidades
  // / tipos / contratistas). Las listas presentes en la MISMA página comparten un
  // estado de filtro a nivel de página (STATE). Al hacer clic en un elemento de
  // una lista de tipo agregado se conmuta el campo de STATE correspondiente y se
  // re-consultan TODAS las listas visibles (filtrado cruzado). Para una lista de
  // tipo T no se filtra por el propio campo de T (eso lo resuelve el servidor).
  // TODAS las cadenas de BD se insertan con .text() — nunca innerHTML de datos.

  // v5.10.0: el estado de filtro a nivel de página ya NO vive aquí. Se delega en
  // el coordinador compartido window.SecopCoord (assets/js/dep-coord.js), de modo
  // que las listas y el treemap [secop_dep_treemap] de la misma página compartan
  // un único estado y se refresquen entre sí por el bus de eventos.
  function coord() { return window.SecopCoord; }
  function STATE_GET() {
    var c = coord();
    return c ? c.get() : { dependencia: '', modalidad: '', tipo_contrato: '' };
  }

  // tipo de lista → campo de estado que conmuta / por el que se marca activo.
  var TIPO_FIELD = {
    dependencias: 'dependencia',
    modalidades:  'modalidad',
    tipos:        'tipo_contrato'
  };

  function money(v) {
    return '$' + Math.round(Number(v) || 0).toLocaleString('es-CO');
  }

  function isUrl(s) {
    return /^https?:\/\//i.test(String(s || ''));
  }

  function strings() {
    return (window.secopDep && secopDep.strings) || {};
  }

  function readConfig($wrap, uid) {
    try {
      var cfg = JSON.parse($wrap.find('#' + uid + '-cfg').text());
      return {
        uid: cfg.uid || uid,
        tipo: cfg.tipo || 'dependencias',
        campos: Array.isArray(cfg.campos) ? cfg.campos : [],
        fieldLabels: cfg.fieldLabels || {}
      };
    } catch (e) {
      return { uid: uid, tipo: 'dependencias', campos: [], fieldLabels: {} };
    }
  }

  // ── Re-consulta de TODAS las listas presentes con el STATE actual ───────────
  function refreshAll() {
    $('.ss-lista').each(function () {
      fetchList($(this));
    });
  }

  function fetchList($wrap) {
    var cfg = $wrap.data('ssListaCfg');
    if (!cfg) return;
    var $body = $wrap.find('.ss-lista-body');
    var STATE = STATE_GET();

    $.post(secopDep.ajaxUrl, {
      action: 'secop_dep_lista',
      nonce: secopDep.nonce,
      tipo: cfg.tipo,
      dependencia: STATE.dependencia,
      modalidad: STATE.modalidad,
      tipo_contrato: STATE.tipo_contrato,
      campos: (cfg.campos || []).join(',')
    }).done(function (res) {
      if (!res || !res.success || !res.data) {
        $body.empty().append($('<p>', { 'class': 'ss-lista-empty', text: strings().noData || 'No hay datos.' }));
        return;
      }
      // El servidor devuelve los campos efectivos (validados).
      if (Array.isArray(res.data.campos) && res.data.campos.length) {
        cfg.campos = res.data.campos;
        $wrap.data('ssListaCfg', cfg);
      }
      if (cfg.tipo === 'contratistas') {
        renderAccordion($wrap, cfg, res.data.rows || []);
      } else {
        renderAggregate($wrap, cfg, res.data.rows || []);
      }
    }).fail(function () {
      $body.empty().append($('<p>', { 'class': 'ss-lista-empty', text: strings().error || 'Error al cargar los datos.' }));
    });
  }

  // ── Listas agregadas (dependencias / modalidades / tipos) ───────────────────
  function renderAggregate($wrap, cfg, rows) {
    var $body = $wrap.find('.ss-lista-body');
    $body.empty();

    var field = TIPO_FIELD[cfg.tipo];
    var activeVal = field ? STATE_GET()[field] : '';

    if (!rows.length) {
      $body.append($('<p>', { 'class': 'ss-lista-empty', text: strings().noData || 'No hay datos.' }));
      return;
    }

    var $ul = $('<ul>', { 'class': 'ss-lista-ul' });
    rows.forEach(function (r) {
      var label = String(r.label == null ? 'N/D' : r.label);
      var $li = $('<li>', { 'class': 'ss-lista-item' }).attr('data-label', label);
      if (activeVal !== '' && label === activeVal) $li.addClass('active');
      $('<span>', { 'class': 'ss-lista-name', text: label }).appendTo($li);
      $('<span>', { 'class': 'ss-lista-meta',
        text: (r.conteo || 0) + ' · ' + money(r.valor) }).appendTo($li);
      $ul.append($li);
    });
    $body.append($ul);
  }

  // ── Lista contratistas (acordeón con contratos) ─────────────────────────────
  function renderAccordion($wrap, cfg, rows) {
    var $body = $wrap.find('.ss-lista-body');
    $body.empty();

    if (!rows.length) {
      $body.append($('<p>', { 'class': 'ss-lista-empty', text: strings().noContracts || 'Sin contratos.' }));
      return;
    }

    var $acc = $('<div>', { 'class': 'ss-lista-acc' });
    rows.forEach(function (c) {
      var $item = $('<div>', { 'class': 'ss-lista-acc-item' });

      var $head = $('<button>', { type: 'button', 'class': 'ss-lista-acc-head' });
      $('<span>', { 'class': 'ss-lista-acc-name', text: String(c.contratista || 'N/D') }).appendTo($head);
      $('<span>', { 'class': 'ss-lista-acc-meta',
        text: (c.conteo || 0) + ' contratos · ' + money(c.valor) }).appendTo($head);
      $item.append($head);

      var $accBody = $('<div>', { 'class': 'ss-lista-acc-body' });
      $accBody.append(buildContractsTable(cfg, c.contratos || []));
      $item.append($accBody);

      $acc.append($item);
    });
    $body.append($acc);
  }

  function buildContractsTable(cfg, contratos) {
    var campos = cfg.campos || [];
    var labels = cfg.fieldLabels || {};
    var colCount = Math.max(1, campos.length);

    var $table = $('<table>', { 'class': 'ss-lista-table' });

    var $thead = $('<thead>');
    var $htr = $('<tr>');
    campos.forEach(function (col) {
      $('<th>', { text: labels[col] || col }).appendTo($htr);
    });
    $thead.append($htr);
    $table.append($thead);

    var $tbody = $('<tbody>');
    contratos.forEach(function (ct) {
      var $r1 = $('<tr>', { 'class': 'ss-lista-row1' });
      campos.forEach(function (col) {
        var $td = $('<td>', { 'class': 'ss-lista-cell-' + col });
        var val = ct[col];
        if (col === 'valor_contrato') {
          $td.text(money(val));
        } else if (col === 'fecha_inicio_ejecucion' || col === 'fecha_fin_ejecucion') {
          $td.text(String(val || '').slice(0, 10));
        } else if (col === 'numero_del_contrato') {
          if (isUrl(ct.url_contrato)) {
            $('<a>', { target: '_blank', rel: 'noopener' })
              .attr('href', String(ct.url_contrato))
              .text(String(val || ''))
              .appendTo($td);
          } else {
            $td.text(String(val == null ? '' : val));
          }
        } else {
          $td.text(String(val == null ? '' : val));
        }
        $r1.append($td);
      });
      $tbody.append($r1);

      var $r2 = $('<tr>', { 'class': 'ss-lista-row2' });
      $('<td>', { 'class': 'ss-lista-objeto' })
        .attr('colspan', colCount)
        .text(String(ct.objeto_a_contratar || ''))
        .appendTo($r2);
      $tbody.append($r2);
    });
    $table.append($tbody);

    return $table;
  }

  // ── Init + delegación de eventos ────────────────────────────────────────────
  function init(wrapper) {
    var $wrap = $(wrapper);
    if ($wrap.data('ssListaInit')) return;
    $wrap.data('ssListaInit', true);
    var uid = $wrap.attr('data-uid');
    $wrap.data('ssListaCfg', readConfig($wrap, uid));
  }

  $(function () {
    var $listas = $('.ss-lista');
    if (!$listas.length) return;
    $listas.each(function () { init(this); });

    // Click en un elemento agregado → conmuta el campo del coordinador compartido.
    // El propio coordinador dispara `secop:coord:refresh`, al que están suscritas
    // TODAS las listas (y el treemap), por lo que el filtrado cruzado es automático.
    $(document).on('click', '.ss-lista-item', function () {
      var $li = $(this);
      var $wrap = $li.closest('.ss-lista');
      var cfg = $wrap.data('ssListaCfg');
      if (!cfg) return;
      var field = TIPO_FIELD[cfg.tipo];
      if (!field) return;
      var label = $li.attr('data-label') || '';
      var c = coord();
      if (c) { c.set(field, label); } // toggle a nivel de página.
    });

    // Click en la cabecera de un contratista → expande/colapsa (sin tocar el estado).
    $(document).on('click', '.ss-lista-acc-head', function () {
      $(this).closest('.ss-lista-acc-item').toggleClass('open');
    });

    // Cada cambio de estado (de cualquier elemento de la página) re-consulta las listas.
    var c = coord();
    if (c) { c.onRefresh(refreshAll); }
    refreshAll();
  });
})(jQuery);
