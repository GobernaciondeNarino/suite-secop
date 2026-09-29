(function ($) {
  'use strict';

  // ── v5.4.0/5.4.1: Red de contratación — grafo de fuerza (d3 v5) ────────────
  // Dependencias = nodos centrales, conectadas a contratistas, tipos de contrato
  // y modalidades de contratación. Datos vía AJAX (secop_dep_network). Todos los
  // textos de BD se insertan con d3 .text()/nodos de texto (nunca innerHTML).
  // v5.4.1: muestra TODOS los contratistas por defecto (limit=0). Para grafos
  // grandes (>400 nodos) la simulación se ejecuta un número acotado de ticks y
  // luego se detiene (no animación abierta) para mantener la página responsiva.

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

  // Umbral a partir del cual se considera "grafo grande" y se acota la simulación.
  var BIG_GRAPH = 400;

  function money(v) {
    return '$' + Math.round(Number(v) || 0).toLocaleString('es-CO');
  }

  function render(wrapper) {
    var d3 = window.d3;
    var $wrap = $(wrapper);
    var $svgBox = $wrap.find('.ss-red-svg');
    var $legend = $wrap.find('.ss-red-legend');

    if (typeof d3 === 'undefined' || !d3.forceSimulation) {
      $svgBox.text('No se pudo cargar la librería de visualización (d3).');
      return;
    }

    var dependencia = $wrap.attr('data-dependencia') || '';
    // limit puede ser 0 (= todos los contratistas). No usar `|| 80`, que
    // convertiría 0 en 80 — ese era el bug que impedía ver la magnitud completa.
    var limitAttr = $wrap.attr('data-limit');
    var limit = (limitAttr === undefined || limitAttr === '') ? 0 : parseInt(limitAttr, 10);
    if (isNaN(limit) || limit < 0) limit = 0;

    // Indicador de carga mientras se obtienen y disponen los datos.
    $svgBox.empty().append(
      $('<div>', { 'class': 'ss-red-loading', text: 'Cargando red…' })
    );
    $legend.empty();
    $wrap.find('.ss-red-caption').remove();

    $.post(secopDep.ajaxUrl, {
      action: 'secop_dep_network',
      nonce: secopDep.nonce,
      dependencia: dependencia,
      limit: limit
    }).done(function (res) {
      if (!res || !res.success || !res.data) {
        $svgBox.empty().text((secopDep.strings && secopDep.strings.noData) || 'No hay datos.');
        return;
      }
      draw(d3, $wrap, $svgBox, $legend, res.data);
    }).fail(function () {
      $svgBox.empty().text((secopDep.strings && secopDep.strings.error) || 'Error al cargar los datos.');
    });
  }

  function draw(d3, $wrap, $svgBox, $legend, data) {
    var nodes = (data.nodes || []).map(function (n) { return Object.assign({}, n); });
    var links = (data.links || []).map(function (l) { return { source: l.source, target: l.target }; });

    $svgBox.empty();
    $legend.empty();
    $wrap.find('.ss-red-caption').remove();

    if (!nodes.length) {
      $svgBox.text((secopDep.strings && secopDep.strings.noData) || 'No hay datos.');
      return;
    }

    var bigGraph = nodes.length > BIG_GRAPH;

    var width = $svgBox.width() || $wrap.width() || 800;
    var height = Math.max(360, parseInt($wrap.css('min-height'), 10) || 560);

    // Escala de radio por valor (sqrt). Las dependencias son mayores.
    var maxVal = d3.max(nodes, function (d) { return +d.value || 0; }) || 1;
    var rScale = d3.scaleSqrt().domain([0, maxVal]).range([3, 18]);
    function radius(d) {
      if (d.type === 'dependencia') return Math.max(12, rScale(+d.value || 0) + 6);
      if (d.type === 'contratista') return Math.max(4, rScale(+d.value || 0));
      return 5;
    }

    var svg = d3.select($svgBox.get(0)).append('svg')
      .attr('width', width)
      .attr('height', height)
      .attr('viewBox', '0 0 ' + width + ' ' + height);

    // Tooltip (div HTML; contenido vía nodos de texto, nunca innerHTML de BD).
    var tooltip = d3.select($wrap.get(0)).append('div')
      .attr('class', 'ss-red-tooltip')
      .style('display', 'none');

    var link = svg.append('g').attr('class', 'ss-red-links')
      .selectAll('line').data(links).enter().append('line')
      .attr('class', 'ss-red-link');

    var node = svg.append('g').attr('class', 'ss-red-nodes')
      .selectAll('circle').data(nodes).enter().append('circle')
      .attr('class', 'ss-red-node')
      .attr('r', radius)
      .attr('fill', function (d) { return d.color || COLORS[d.type] || '#999'; })
      .call(d3.drag()
        .on('start', dragstart)
        .on('drag', dragged)
        .on('end', dragend));

    // Etiquetas sólo para dependencias (evita saturar el grafo).
    var label = svg.append('g').attr('class', 'ss-red-labels')
      .selectAll('text')
      .data(nodes.filter(function (d) { return d.type === 'dependencia'; }))
      .enter().append('text')
      .attr('class', 'ss-red-label')
      .attr('dy', -14)
      .attr('text-anchor', 'middle')
      .text(function (d) { return d.label; });

    node.on('mouseover', function (d) {
      tooltipContent(tooltip, d);
      tooltip.style('display', 'block');
      // Etiqueta temporal al pasar por encima (para nodos sin label fijo).
      if (d.type !== 'dependencia') {
        svg.append('text')
          .attr('class', 'ss-red-hover-label')
          .attr('x', d.x).attr('y', d.y - radius(d) - 4)
          .attr('text-anchor', 'middle')
          .text(d.label);
      }
    }).on('mousemove', function () {
      var off = $wrap.offset();
      var ev = d3.event;
      tooltip
        .style('left', (ev.pageX - off.left + 12) + 'px')
        .style('top', (ev.pageY - off.top + 12) + 'px');
    }).on('mouseout', function () {
      tooltip.style('display', 'none');
      svg.selectAll('.ss-red-hover-label').remove();
    });

    function ticked() {
      link
        .attr('x1', function (d) { return d.source.x; })
        .attr('y1', function (d) { return d.source.y; })
        .attr('x2', function (d) { return d.target.x; })
        .attr('y2', function (d) { return d.target.y; });
      node
        .attr('cx', function (d) { return d.x; })
        .attr('cy', function (d) { return d.y; });
      label
        .attr('x', function (d) { return d.x; })
        .attr('y', function (d) { return d.y; });
    }

    // En grafos grandes se reduce la magnitud de la repulsión y el alcance de los
    // enlaces para abaratar cada tick.
    var sim = d3.forceSimulation(nodes)
      .force('link', d3.forceLink(links).id(function (d) { return d.id; }).distance(bigGraph ? 36 : 60))
      .force('charge', d3.forceManyBody().strength(bigGraph ? -40 : -120))
      .force('center', d3.forceCenter(width / 2, height / 2))
      .force('collide', d3.forceCollide().radius(function (d) { return radius(d) + 2; }));

    // El handler queda registrado para que el arrastre (que reinicia la sim)
    // siga redibujando aunque para grafos grandes la detengamos a continuación.
    sim.on('tick', ticked);

    if (bigGraph) {
      // Layout acotado: ejecutar un nº fijo de ticks (síncrono) y detener — sin
      // animación abierta. Mantiene responsivo incluso ~1700 nodos.
      sim.stop();
      for (var i = 0; i < 120; i++) sim.tick();
      ticked();
    }

    function dragstart(d) {
      if (!d3.event.active) sim.alphaTarget(0.3).restart();
      d.fx = d.x; d.fy = d.y;
    }
    function dragged(d) { d.fx = d3.event.x; d.fy = d3.event.y; }
    function dragend(d) {
      if (!d3.event.active) sim.alphaTarget(0);
      d.fx = null; d.fy = null;
    }

    // Leyenda.
    LEGEND.forEach(function (item) {
      var $row = $('<span>', { 'class': 'ss-red-legend-item' });
      $('<span>', { 'class': 'ss-red-legend-dot' }).css('background', COLORS[item[0]]).appendTo($row);
      $('<span>').text(item[1]).appendTo($row);
      $legend.append($row);
    });

    // Leyenda de magnitud: nº de contratistas y dependencias presentes.
    var nCon = 0, nDep = 0;
    nodes.forEach(function (d) {
      if (d.type === 'contratista') nCon++;
      else if (d.type === 'dependencia') nDep++;
    });
    $('<div>', { 'class': 'ss-red-caption' })
      .text(nCon.toLocaleString('es-CO') + ' contratistas, ' + nDep.toLocaleString('es-CO') + ' dependencias')
      .insertAfter($legend);
  }

  // Construye el tooltip con nodos de texto (seguro frente a XSS).
  function tooltipContent(tooltip, d) {
    tooltip.selectAll('*').remove();
    var lines = [];
    if (d.type === 'contratista') {
      lines.push('Contratista: ' + d.label);
      lines.push('Contratos: ' + (d.count || 0));
      lines.push('Valor: ' + money(d.value));
      lines.push('Dependencia: ' + (d.dependencia || '—'));
    } else if (d.type === 'dependencia') {
      lines.push('Dependencia: ' + d.label);
      lines.push('Contratos: ' + (d.count || 0));
      lines.push('Valor: ' + money(d.value));
    } else if (d.type === 'tipo') {
      lines.push('Tipo de contrato: ' + d.label);
    } else if (d.type === 'modalidad') {
      lines.push('Modalidad: ' + d.label);
    } else {
      lines.push(d.label);
    }
    lines.forEach(function (text) {
      tooltip.append('div').text(text);
    });
  }

  $(function () {
    $('.ss-red-wrapper').each(function () { render(this); });

    // Selector de dependencia: actualiza el wrapper y vuelve a cargar.
    $(document).on('change', '.ss-red-selector', function () {
      var dep = $(this).val();
      var $wrap = $(this).closest('.ss-red-module').find('.ss-red-wrapper');
      $wrap.attr('data-dependencia', dep);
      render($wrap.get(0));
    });
  });
})(jQuery);
