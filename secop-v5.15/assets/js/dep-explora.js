(function ($) {
  'use strict';

  // ── v5.6.0: Explorador interactivo de contratación [secop_dep_explora] ──────
  // Treemap de dependencias (d3plus.Treemap). Al hacer clic en una celda se
  // despliega un panel inferior con dos columnas: lista de modalidades (clicable)
  // y un acordeón de contratistas cuyos elementos se expanden mostrando los
  // contratos. Todo por AJAX (secop_dep_explora_*). TODAS las cadenas de BD se
  // insertan con .text()/createTextNode — nunca innerHTML de datos de la BD.

  function money(v) {
    return '$' + Math.round(Number(v) || 0).toLocaleString('es-CO');
  }

  // d3plus envuelve cada dato como { __d3plus__: true, data: <nodo> }.
  function unwrap(d) {
    return (d && d.__d3plus__ && d.data) ? d.data : d;
  }

  function isUrl(s) {
    return /^https?:\/\//i.test(String(s || ''));
  }

  // Lee la config JSON inline del wrapper.
  function readConfig($wrap, uid) {
    try {
      var raw = $wrap.find('#' + uid + '-cfg').text();
      var cfg = JSON.parse(raw);
      return {
        uid: cfg.uid || uid,
        campos: Array.isArray(cfg.campos) ? cfg.campos : [],
        fieldLabels: cfg.fieldLabels || {}
      };
    } catch (e) {
      return { uid: uid, campos: [], fieldLabels: {} };
    }
  }

  function strings() {
    return (window.secopDep && secopDep.strings) || {};
  }

  // ── Treemap ────────────────────────────────────────────────────────────────
  function renderTree($wrap, state) {
    var $tree = $wrap.find('.ss-explora-tree');

    if (!window.d3plus || typeof window.d3plus.Treemap !== 'function') {
      $tree.empty().text('No se pudo cargar la librería de visualización (d3plus.Treemap).');
      return;
    }

    $.post(secopDep.ajaxUrl, {
      action: 'secop_dep_explora_tree',
      nonce: secopDep.nonce
    }).done(function (res) {
      if (!res || !res.success || !res.data || !res.data.nodes) {
        $tree.empty().text(strings().noData || 'No hay datos.');
        return;
      }
      drawTree($wrap, $tree, res.data.nodes, state);
    }).fail(function () {
      $tree.empty().text(strings().error || 'Error al cargar los datos.');
    });
  }

  function drawTree($wrap, $tree, nodes, state) {
    nodes = nodes || [];
    if (!nodes.length) {
      $tree.empty().text(strings().noData || 'No hay datos.');
      return;
    }
    $tree.empty();

    var data = nodes.map(function (n) {
      return { id: String(n.label), value: +n.valor || 0, conteo: +n.conteo || 0 };
    });

    function esc(s) {
      return String(s == null ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    new window.d3plus.Treemap()
      .select($tree.get(0))
      .data(data)
      .groupBy('id')
      .sum('value')
      .label(function (d) { d = unwrap(d); return d.id; })
      .tooltipConfig({
        title: function (d) { d = unwrap(d); return esc(d.id); },
        tbody: function (d) {
          d = unwrap(d);
          return [
            ['Valor', esc(money(d.value))],
            ['Contratos', esc(d.conteo || 0)]
          ];
        }
      })
      .on('click', function (d) {
        d = unwrap(d);
        if (d && d.id) openDep($wrap, state, d.id);
      })
      .render();
  }

  // ── Panel: abrir dependencia ────────────────────────────────────────────────
  function openDep($wrap, state, dep) {
    state.dep = dep;
    state.modalidad = null;

    var $panel = $wrap.find('.ss-explora-panel');
    $panel.show();
    $wrap.find('.ss-explora-dep-title').text(dep);

    loadModalidades($wrap, state);
    loadContratistas($wrap, state);
  }

  // ── Modalidades ─────────────────────────────────────────────────────────────
  function loadModalidades($wrap, state) {
    var $list = $wrap.find('.ss-explora-mod-list');
    $list.empty().append($('<li>', { 'class': 'ss-explora-mod-loading', text: 'Cargando…' }));

    $.post(secopDep.ajaxUrl, {
      action: 'secop_dep_explora_modalidades',
      nonce: secopDep.nonce,
      dependencia: state.dep
    }).done(function (res) {
      if (!res || !res.success || !res.data) {
        $list.empty().append($('<li>').text(strings().noData || 'No hay datos.'));
        return;
      }
      renderModalidades($wrap, state, res.data.rows || []);
    }).fail(function () {
      $list.empty().append($('<li>').text(strings().error || 'Error al cargar los datos.'));
    });
  }

  function renderModalidades($wrap, state, rows) {
    var $list = $wrap.find('.ss-explora-mod-list');
    $list.empty();

    // Item "Todas" para limpiar el filtro de modalidad.
    var $all = $('<li>', { 'class': 'ss-explora-mod-item active' })
      .attr('data-modalidad', '');
    $('<span>', { 'class': 'ss-explora-mod-name', text: 'Todas' }).appendTo($all);
    $list.append($all);

    rows.forEach(function (r) {
      var $li = $('<li>', { 'class': 'ss-explora-mod-item' })
        .attr('data-modalidad', String(r.label == null ? '' : r.label));
      $('<span>', { 'class': 'ss-explora-mod-name', text: String(r.label || 'N/D') }).appendTo($li);
      $('<span>', { 'class': 'ss-explora-mod-meta', text: (r.conteo || 0) + ' · ' + money(r.valor) }).appendTo($li);
      $list.append($li);
    });
  }

  // ── Contratistas (acordeón) ─────────────────────────────────────────────────
  function loadContratistas($wrap, state) {
    var $acc = $wrap.find('.ss-explora-acc');
    $acc.empty().append($('<div>', { 'class': 'ss-explora-acc-loading', text: 'Cargando…' }));

    $.post(secopDep.ajaxUrl, {
      action: 'secop_dep_explora_contratistas',
      nonce: secopDep.nonce,
      dependencia: state.dep,
      modalidad: state.modalidad || '',
      campos: (state.campos || []).join(',')
    }).done(function (res) {
      if (!res || !res.success || !res.data) {
        $acc.empty().text(strings().noData || 'No hay datos.');
        return;
      }
      // El servidor devuelve los campos efectivos (validados).
      if (Array.isArray(res.data.campos) && res.data.campos.length) {
        state.campos = res.data.campos;
      }
      renderAccordion($wrap, state, res.data.rows || []);
    }).fail(function () {
      $acc.empty().text(strings().error || 'Error al cargar los datos.');
    });
  }

  function renderAccordion($wrap, state, rows) {
    var $acc = $wrap.find('.ss-explora-acc');
    $acc.empty();

    if (!rows.length) {
      $acc.append($('<p>', { 'class': 'ss-explora-empty', text: strings().noContracts || 'Sin contratos.' }));
      return;
    }

    rows.forEach(function (c) {
      var $item = $('<div>', { 'class': 'ss-explora-acc-item' });

      var $head = $('<button>', { type: 'button', 'class': 'ss-explora-acc-head' });
      $('<span>', { 'class': 'ss-explora-acc-name', text: String(c.contratista || 'N/D') }).appendTo($head);
      $('<span>', { 'class': 'ss-explora-acc-meta',
        text: (c.conteo || 0) + ' contratos · ' + money(c.valor) }).appendTo($head);
      $item.append($head);

      var $body = $('<div>', { 'class': 'ss-explora-acc-body' });
      $body.append(buildContractsTable(state, c.contratos || []));
      $item.append($body);

      $acc.append($item);
    });
  }

  function buildContractsTable(state, contratos) {
    var campos = state.campos || [];
    var labels = state.fieldLabels || {};
    var colCount = Math.max(1, campos.length);

    var $table = $('<table>', { 'class': 'ss-explora-table' });

    // Encabezados (etiquetas de los campos elegidos).
    var $thead = $('<thead>');
    var $htr = $('<tr>');
    campos.forEach(function (col) {
      $('<th>', { text: labels[col] || col }).appendTo($htr);
    });
    $thead.append($htr);
    $table.append($thead);

    var $tbody = $('<tbody>');
    contratos.forEach(function (ct) {
      // Fila 1: los campos configurables.
      var $r1 = $('<tr>', { 'class': 'ss-explora-row1' });
      campos.forEach(function (col) {
        var $td = $('<td>', { 'class': 'ss-explora-cell-' + col });
        var val = ct[col];

        if (col === 'valor_contrato') {
          $td.text(money(val));
        } else if (col === 'fecha_inicio_ejecucion' || col === 'fecha_fin_ejecucion') {
          $td.text(String(val || '').slice(0, 10));
        } else if (col === 'numero_del_contrato') {
          if (isUrl(ct.url_contrato)) {
            var $a = $('<a>', { target: '_blank', rel: 'noopener' })
              .attr('href', String(ct.url_contrato))
              .text(String(val || ''));
            $td.append($a);
          } else {
            $td.text(String(val == null ? '' : val));
          }
        } else {
          $td.text(String(val == null ? '' : val));
        }
        $r1.append($td);
      });
      $tbody.append($r1);

      // Fila 2: objeto_a_contratar a ancho completo.
      var $r2 = $('<tr>', { 'class': 'ss-explora-row2' });
      $('<td>', { 'class': 'ss-explora-objeto' })
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
    var uid = $wrap.attr('data-uid');
    var cfg = readConfig($wrap, uid);
    var state = {
      uid: uid,
      campos: cfg.campos,
      fieldLabels: cfg.fieldLabels,
      dep: null,
      modalidad: null
    };
    $wrap.data('ssExploraState', state);
    renderTree($wrap, state);
  }

  $(function () {
    $('.ss-explora-wrapper').each(function () { init(this); });

    // Click en una modalidad → fija el filtro y recarga SÓLO los contratistas.
    $(document).on('click', '.ss-explora-mod-item', function () {
      var $li = $(this);
      var $wrap = $li.closest('.ss-explora-wrapper');
      var state = $wrap.data('ssExploraState');
      if (!state) return;
      $wrap.find('.ss-explora-mod-item').removeClass('active');
      $li.addClass('active');
      var mod = $li.attr('data-modalidad') || '';
      state.modalidad = mod !== '' ? mod : null;
      loadContratistas($wrap, state);
    });

    // Click en la cabecera de un contratista → expande/colapsa su cuerpo.
    $(document).on('click', '.ss-explora-acc-head', function () {
      $(this).closest('.ss-explora-acc-item').toggleClass('open');
    });
  });
})(jQuery);
