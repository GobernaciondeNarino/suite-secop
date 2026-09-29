(function ($) {
  'use strict';

  // ── v5.12.0: Red ego de contratación [secop_dep_rings] ─────────────────────
  // DOS modos, gobernados por el coordinador de filtros window.SecopCoord:
  //   • Sin dependencia seleccionada (estado vacío) → red COMPLETA de TODOS los
  //     nodos (dependencias + contratistas + tipos + modalidades) con un grafo de
  //     fuerza d3 acotado (ticks limitados) — el mismo enfoque que dep-network.js,
  //     responsivo incluso con ~1700 nodos.
  //   • Con una dependencia seleccionada → layout radial EGO centrado en
  //     dep::{dependencia} (anillos concéntricos).
  // El centro YA NO se autoselecciona por mayor valor: proviene de
  // SecopCoord.get().dependencia (con data-dependencia del wrapper como único
  // fallback inicial). Un selector separado [secop_dep_selector] fija ese estado y
  // dispara `secop:coord:refresh`, al que este script está suscrito → re-render.
  // TODAS las cadenas de BD se insertan con d3 .text() — nunca innerHTML de datos.

  var COLORS = {
    dependencia: '#0080c3',
    contratista: '#844e80',
    tipo:        '#3eba6a',
    modalidad:   '#ff7300'
  };

  var LEGEND = [
    ['dependencia', 'Dependencia'],
    ['contratista', 'Contratista'],
    ['tipo',        'Tipo de contrato'],
    ['modalidad',   'Modalidad']
  ];

  // Radio de cada anillo por tipo (modalidades dentro, contratistas fuera).
  var RING_ORDER = ['modalidad', 'tipo', 'contratista'];

  // Umbral a partir del cual el grafo de fuerza se considera "grande" y se acota
  // la magnitud de las fuerzas para abaratar cada tick.
  var BIG_GRAPH = 400;

  function colorByType(t) { return COLORS[t] || '#999999'; }
  function money(v) { return '$' + Math.round(Number(v) || 0).toLocaleString('es-CO'); }

  function strings() { return (window.secopDep && secopDep.strings) || {}; }

  // Dependencia central activa: el coordinador es la fuente de verdad; el atributo
  // data-dependencia del wrapper sólo actúa como fallback inicial (ver init()).
  function currentDep($wrap) {
    if (window.SecopCoord) return SecopCoord.get().dependencia || '';
    return $wrap.attr('data-dependencia') || '';
  }

  // ── Tooltip (contenido vía nodos de texto, seguro frente a XSS) ──────────────
  function tooltipLines(n) {
    var lines = [];
    if (n.type === 'contratista') {
      lines.push('Contratista: ' + n.label);
      lines.push('Contratos: ' + (n.count || 0));
      lines.push('Valor: ' + money(n.value));
      lines.push('Dependencia: ' + (n.dependencia || '—'));
    } else if (n.type === 'dependencia') {
      lines.push('Dependencia: ' + n.label);
      lines.push('Contratos: ' + (n.count || 0));
      lines.push('Valor: ' + money(n.value));
    } else if (n.type === 'tipo') {
      lines.push('Tipo de contrato: ' + n.label);
    } else if (n.type === 'modalidad') {
      lines.push('Modalidad: ' + n.label);
    } else {
      lines.push(String(n.label == null ? '' : n.label));
    }
    return lines;
  }
  function fillTip(tip, n) {
    tip.selectAll('*').remove();
    tooltipLines(n).forEach(function (t) { tip.append('div').text(t); });
  }
  function moveTip(tip, $chart, ev) {
    var off = $chart.offset();
    tip.style('display', 'block')
      .style('left', (ev.pageX - off.left + 12) + 'px')
      .style('top', (ev.pageY - off.top + 12) + 'px');
  }

  // Leyenda compartida: devuelve (creando si hace falta) el contenedor vacío.
  function legendBox($wrap) {
    var $leg = $wrap.find('.ss-rings-legend');
    if (!$leg.length) { $leg = $('<div class="ss-rings-legend"></div>').appendTo($wrap); }
    $leg.empty();
    return $leg;
  }

  // ── Carga de datos + despacho al modo correspondiente ───────────────────────
  function renderRings(wrapper) {
    var $wrap = $(wrapper);
    var $chart = $wrap.find('.ss-rings-chart');
    if (!window.d3) { $chart.empty().text('No se pudo cargar la librería de visualización (d3).'); return; }

    var dependencia = currentDep($wrap);
    $chart.empty().append($('<div>', { 'class': 'ss-rings-loading', text: 'Cargando red…' }));
    $wrap.find('.ss-rings-legend').empty();

    $.post(secopDep.ajaxUrl, {
      action: 'secop_dep_network',
      nonce: secopDep.nonce,
      dependencia: dependencia,
      limit: 0
    }).done(function (res) {
      if (!res || !res.success || !res.data) {
        $chart.empty().text(strings().noData || 'No hay datos.');
        return;
      }
      if (dependencia) {
        drawEgo($wrap, $chart, res.data, dependencia);
      } else {
        drawNetwork($wrap, $chart, res.data);
      }
    }).fail(function () {
      $chart.empty().text(strings().error || 'Error al cargar los datos.');
    });
  }

  // ── Modo "todos": red completa con grafo de fuerza acotado (como dep-network) ─
  function drawNetwork($wrap, $chart, data) {
    var d3 = window.d3;
    var nodes = (data.nodes || []).map(function (n) { return Object.assign({}, n); });
    var links = (data.links || []).map(function (l) { return { source: l.source, target: l.target }; });

    $chart.empty();
    var $leg = legendBox($wrap);

    if (!nodes.length) { $chart.text(strings().noData || 'No hay datos.'); return; }

    var bigGraph = nodes.length > BIG_GRAPH;
    var width = Math.max(320, $chart.width() || $wrap.width() || 800);
    var height = Math.max(360, parseInt($wrap.css('min-height'), 10) || 560);

    var maxVal = d3.max(nodes, function (d) { return +d.value || 0; }) || 1;
    var rScale = d3.scaleSqrt().domain([0, maxVal]).range([3, 18]);
    function radius(d) {
      if (d.type === 'dependencia') return Math.max(12, rScale(+d.value || 0) + 6);
      if (d.type === 'contratista') return Math.max(4, rScale(+d.value || 0));
      return 5;
    }

    var svg = d3.select($chart.get(0)).append('svg')
      .attr('width', '100%')
      .attr('viewBox', '0 0 ' + width + ' ' + height)
      .attr('preserveAspectRatio', 'xMidYMid meet');

    var tip = d3.select($chart.get(0)).append('div').attr('class', 'ss-rings-tooltip').style('display', 'none');

    var link = svg.append('g').attr('class', 'ss-red-links')
      .selectAll('line').data(links).enter().append('line')
      .attr('class', 'ss-red-link').attr('stroke', '#d0d0d0').attr('stroke-width', 1);

    var node = svg.append('g').attr('class', 'ss-red-nodes')
      .selectAll('circle').data(nodes).enter().append('circle')
      .attr('class', 'ss-red-node')
      .attr('r', radius)
      .attr('fill', function (d) { return d.color || colorByType(d.type); })
      .attr('stroke', '#fff').attr('stroke-width', 1)
      .style('cursor', 'pointer')
      .call(d3.drag().on('start', dragstart).on('drag', dragged).on('end', dragend));

    // Etiquetas sólo para dependencias (evita saturar el grafo).
    var label = svg.append('g').attr('class', 'ss-red-labels')
      .selectAll('text')
      .data(nodes.filter(function (d) { return d.type === 'dependencia'; }))
      .enter().append('text')
      .attr('class', 'ss-red-label')
      .attr('dy', -14).attr('text-anchor', 'middle')
      .text(function (d) { return d.label; });

    node.on('mouseover', function (d) {
      fillTip(tip, d);
      tip.style('display', 'block');
    }).on('mousemove', function () {
      moveTip(tip, $chart, d3.event);
    }).on('mouseout', function () {
      tip.style('display', 'none');
    });

    function ticked() {
      link
        .attr('x1', function (d) { return d.source.x; })
        .attr('y1', function (d) { return d.source.y; })
        .attr('x2', function (d) { return d.target.x; })
        .attr('y2', function (d) { return d.target.y; });
      node.attr('cx', function (d) { return d.x; }).attr('cy', function (d) { return d.y; });
      label.attr('x', function (d) { return d.x; }).attr('y', function (d) { return d.y; });
    }

    var sim = d3.forceSimulation(nodes)
      .force('link', d3.forceLink(links).id(function (d) { return d.id; }).distance(bigGraph ? 36 : 60))
      .force('charge', d3.forceManyBody().strength(bigGraph ? -40 : -120))
      .force('center', d3.forceCenter(width / 2, height / 2))
      .force('collide', d3.forceCollide().radius(function (d) { return radius(d) + 2; }));

    // El handler queda registrado para que el arrastre (que reinicia la sim) siga
    // redibujando aunque el layout inicial sea acotado (síncrono) y detenido.
    sim.on('tick', ticked);
    // Layout acotado: nº fijo de ticks (síncrono) y stop — sin animación abierta.
    sim.stop();
    for (var i = 0; i < 120; i++) sim.tick();
    ticked();

    function dragstart(d) { if (!d3.event.active) sim.alphaTarget(0.3).restart(); d.fx = d.x; d.fy = d.y; }
    function dragged(d) { d.fx = d3.event.x; d.fy = d3.event.y; }
    function dragend(d) { if (!d3.event.active) sim.alphaTarget(0); d.fx = null; d.fy = null; }

    // Leyenda por tipo + caption "N nodos".
    LEGEND.forEach(function (item) {
      $leg.append($('<span class="ss-rings-leg-item"></span>').append(
        $('<span class="ss-rings-leg-dot"></span>').css('background', COLORS[item[0]]),
        document.createTextNode(' ' + item[1])
      ));
    });
    $leg.append($('<span class="ss-rings-caption"></span>')
      .text(nodes.length.toLocaleString('es-CO') + ' nodos'));
  }

  // ── Modo "ego": layout radial concéntrico centrado en una dependencia ───────
  function drawEgo($wrap, $chart, data, dependencia) {
    var d3 = window.d3;
    var nodes = data.nodes || [];
    var links = data.links || [];
    var byId = {};
    nodes.forEach(function (n) { byId[n.id] = n; });

    var centerId = 'dep::' + dependencia;
    if (!byId[centerId]) {
      $chart.empty().text(strings().noData || 'No hay datos.');
      legendBox($wrap);
      return;
    }
    var center = byId[centerId];

    // Vecinos del centro: nodos enlazados a la dependencia central.
    var neighborIds = {};
    links.forEach(function (l) {
      if (l.target === centerId && byId[l.source]) neighborIds[l.source] = true;
      if (l.source === centerId && byId[l.target]) neighborIds[l.target] = true;
    });
    var neighbors = Object.keys(neighborIds).map(function (id) { return byId[id]; });
    if (!neighbors.length) {
      $chart.empty().text('La dependencia seleccionada no tiene conexiones.');
      legendBox($wrap);
      return;
    }

    // Agrupar vecinos por tipo y asignar anillos.
    var groups = {};
    neighbors.forEach(function (n) { (groups[n.type] = groups[n.type] || []).push(n); });
    var ringsPresent = RING_ORDER.filter(function (t) { return groups[t] && groups[t].length; });

    $chart.empty();
    var width = Math.max(320, $chart.width() || $wrap.width() || 600);
    var height = Math.max(360, parseInt($wrap.css('min-height'), 10) || 560);
    var cx = width / 2, cy = height / 2;
    var maxR = Math.min(width, height) / 2 - 40;
    var ringGap = ringsPresent.length ? maxR / (ringsPresent.length + 0.5) : maxR;

    var svg = d3.select($chart.get(0)).append('svg')
      .attr('width', '100%')
      .attr('viewBox', '0 0 ' + width + ' ' + height)
      .attr('preserveAspectRatio', 'xMidYMid meet');

    // Posicionar vecinos.
    var positioned = [];
    ringsPresent.forEach(function (type, ri) {
      var r = ringGap * (ri + 1);
      var arr = groups[type];
      arr.forEach(function (n, i) {
        var ang = (i / arr.length) * 2 * Math.PI - Math.PI / 2;
        positioned.push({ node: n, x: cx + r * Math.cos(ang), y: cy + r * Math.sin(ang) });
      });
    });

    // Escala de tamaño por valor (para contratistas/dependencia).
    var maxVal = d3.max(positioned.concat([{ node: center }]).map(function (p) { return +p.node.value || 0; })) || 1;
    var rScale = d3.scaleSqrt().domain([0, maxVal]).range([4, 16]);

    // Enlaces centro → vecino.
    svg.append('g').selectAll('line').data(positioned).enter().append('line')
      .attr('x1', cx).attr('y1', cy)
      .attr('x2', function (p) { return p.x; }).attr('y2', function (p) { return p.y; })
      .attr('stroke', '#d0d0d0').attr('stroke-width', 1);

    var tip = d3.select($chart.get(0)).append('div').attr('class', 'ss-rings-tooltip').style('display', 'none');

    // Nodos vecinos.
    var g = svg.append('g');
    g.selectAll('circle').data(positioned).enter().append('circle')
      .attr('cx', function (p) { return p.x; }).attr('cy', function (p) { return p.y; })
      .attr('r', function (p) { return (p.node.type === 'contratista') ? rScale(+p.node.value || 0) : 7; })
      .attr('fill', function (p) { return p.node.color || colorByType(p.node.type); })
      .attr('stroke', '#fff').attr('stroke-width', 1)
      .style('cursor', 'pointer')
      .on('mouseover', function (p) { fillTip(tip, p.node); tip.style('display', 'block'); })
      .on('mousemove', function () { moveTip(tip, $chart, d3.event); })
      .on('mouseout', function () { tip.style('display', 'none'); });

    // Nodo central (la dependencia).
    svg.append('circle').attr('cx', cx).attr('cy', cy).attr('r', 18)
      .attr('fill', center.color || colorByType('dependencia')).attr('stroke', '#fff').attr('stroke-width', 2)
      .style('cursor', 'pointer')
      .on('mouseover', function () { fillTip(tip, center); tip.style('display', 'block'); })
      .on('mousemove', function () { moveTip(tip, $chart, d3.event); })
      .on('mouseout', function () { tip.style('display', 'none'); });
    svg.append('text').attr('x', cx).attr('y', cy + 34).attr('text-anchor', 'middle')
      .attr('font-size', '12px').attr('font-weight', 'bold').text(center.label);

    // Leyenda + conteo de conexiones.
    var $leg = legendBox($wrap);
    var labels = { dependencia: 'Dependencia (centro)', modalidad: 'Modalidad', tipo: 'Tipo de contrato', contratista: 'Contratista' };
    ['dependencia'].concat(ringsPresent).forEach(function (t) {
      $leg.append($('<span class="ss-rings-leg-item"></span>').append(
        $('<span class="ss-rings-leg-dot"></span>').css('background', colorByType(t)),
        document.createTextNode(' ' + (labels[t] || t))
      ));
    });
    $leg.append($('<span class="ss-rings-caption"></span>').text(neighbors.length + ' conexiones'));
  }

  $(function () {
    $('.ss-rings-wrapper').each(function () {
      var $wrap = $(this);
      // Fallback inicial: si el shortcode fijó data-dependencia y el coordinador
      // aún no tiene dependencia, se siembra una vez y se descarta el atributo
      // (a partir de ahí el coordinador es la única fuente de verdad, de modo que
      // limpiar el selector vuelva correctamente a la red completa).
      var seed = $wrap.attr('data-dependencia') || '';
      if (window.SecopCoord) {
        if (seed && !SecopCoord.get().dependencia) SecopCoord.setRaw('dependencia', seed);
        $wrap.removeAttr('data-dependencia');
      }
      renderRings(this);
    });

    // Re-render ante cualquier cambio del estado compartido (selector separado,
    // listas, treemap…). El centro se recalcula desde SecopCoord.dependencia.
    if (window.SecopCoord) {
      SecopCoord.onRefresh(function () {
        $('.ss-rings-wrapper').each(function () { renderRings(this); });
      });
    }

    // Selector inline opcional (retrocompatibilidad): ahora fija el coordinador.
    $(document).on('change', '.ss-rings-selector', function () {
      var dep = $(this).val();
      if (window.SecopCoord) {
        SecopCoord.setRaw('dependencia', dep);
      } else {
        var $wrap = $(this).closest('.ss-rings-module, .ss-rings-wrapper').find('.ss-rings-wrapper');
        if (!$wrap.length) $wrap = $(this).closest('.ss-rings-wrapper');
        if (!$wrap.length) $wrap = $('.ss-rings-wrapper').first();
        $wrap.attr('data-dependencia', dep);
        renderRings($wrap.get(0));
      }
    });
  });
})(jQuery);
