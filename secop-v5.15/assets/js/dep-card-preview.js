/**
 * SECOP Suite — Vista previa en vivo del editor de cards de Contratación.
 *
 * Recolecta los valores actuales del formulario de configuración, solicita por
 * AJAX (secop_dep_preview) los datos + el SQL generado, y renderiza la gráfica
 * reutilizando el motor compartido window.SSChartRender (frontend.js).
 *
 * @package SecopSuite
 */
(function($) {
    'use strict';

    if (typeof secopDepPreview === 'undefined') {
        return;
    }

    var SEL = {
        dimension:   '#dep_dimension',
        chartType:   '[name="dep_chart_type"]',
        metric:      '[name="dep_metric"]',
        dependencia: '[name="dep_dependencia"]',
        order:       '[name="dep_order"]',
        orderDir:    '[name="dep_order_dir"]',
        colors:      '[name="dep_colors"]',
        limit:       '[name="dep_limit"]',
        // v5.3.0: personalización del gráfico.
        numberFormat:   '[name="dep_number_format"]',
        chartHeight:    '[name="dep_chart_height"]',
        xTitle:         '[name="dep_x_title"]',
        yTitle:         '[name="dep_y_title"]',
        showLegend:     '[name="dep_show_legend"]',
        legendMode:     '[name="dep_legend_mode"]',
        legendPosition: '[name="dep_legend_position"]',
        showToolbar:    '[name="dep_show_toolbar"]',
        toolbarOptions: '[name="dep_toolbar_options"]',
        // v5.3.2: campos del tooltip.
        tooltipFields:  '[name="dep_tooltip_fields"]'
    };

    /**
     * Traduce la config (snake_case) que devuelve card_to_chart_config a las
     * claves camelCase que espera SSChartRender (igual que chart.php).
     */
    function toRenderConfig(cfg) {
        cfg = cfg || {};
        return {
            colors:         cfg.colors || undefined,
            showLegend:     !!cfg.show_legend,
            legendMode:     cfg.legend_mode || 'text',
            legendPosition: cfg.legend_position || 'bottom',
            showTimeline:   !!cfg.show_timeline,
            yAxisTitle:     cfg.y_axis_title || '',
            xAxisTitle:     cfg.x_axis_title || '',
            numberFormat:   cfg.number_format || 'colombiano',
            // v5.3.2: campos del tooltip (array desde PHP) → se pasa tal cual.
            tooltipFields:  (cfg.tooltip_fields && cfg.tooltip_fields.length) ? cfg.tooltip_fields : ['categoria', 'valor'],
            multiY:         !!(cfg.y_fields && cfg.y_fields.length)
        };
    }

    function collect() {
        return {
            dimension:   $(SEL.dimension).val()   || 'dependencia',
            chart_type:  $(SEL.chartType).val()    || '',
            metric:      $(SEL.metric).val()       || 'valor_contrato',
            dependencia: $(SEL.dependencia).val()  || '',
            order:       $(SEL.order).val()        || 'valor',
            order_dir:   $(SEL.orderDir).val()     || 'DESC',
            limit:       parseInt($(SEL.limit).val(), 10) || 0,
            colors:      $(SEL.colors).val()       || '',
            // v5.3.0: personalización del gráfico.
            number_format:   $(SEL.numberFormat).val() || 'colombiano',
            chart_height:    parseInt($(SEL.chartHeight).val(), 10) || 400,
            x_axis_title:    $(SEL.xTitle).val() || '',
            y_axis_title:    $(SEL.yTitle).val() || '',
            show_legend:     $(SEL.showLegend).is(':checked') ? '1' : '0',
            legend_mode:     $(SEL.legendMode).val() || 'text',
            legend_position: $(SEL.legendPosition).val() || 'bottom',
            show_toolbar:    $(SEL.showToolbar).is(':checked') ? '1' : '0',
            toolbar_options: $(SEL.toolbarOptions + ':checked').map(function () {
                return this.value;
            }).get().join(','),
            // v5.3.2: campos del tooltip (lista separada por comas).
            tooltip_fields: $(SEL.tooltipFields + ':checked').map(function () {
                return this.value;
            }).get().join(','),
            // v5.3.1: filtros configurables (columna/operador/valor). Se envían como
            // un array que jQuery serializa a filters[i][field|operator|value].
            filters: collectFilters()
        };
    }

    /**
     * Recolecta las filas de filtros del editor en un array de objetos. Descarta
     * filas sin columna o sin valor (el servidor revalida igualmente).
     */
    function collectFilters() {
        var out = [];
        $('#dep-filters-rows .dep-filter-row').each(function () {
            var $row = $(this);
            var field = ($row.find('.dep-filter-field').val() || '').trim();
            var operator = $row.find('.dep-filter-operator').val() || '=';
            var value = ($row.find('.dep-filter-value').val() || '').trim();
            if (field === '' || value === '') {
                return;
            }
            out.push({ field: field, operator: operator, value: value });
        });
        return out;
    }

    function renderDataTable(rows) {
        var $wrap = $('#ss-dep-preview-data');
        $wrap.empty();
        if (!rows || rows.length === 0) {
            $wrap.append($('<p>').text(secopDepPreview.strings.noData));
            return;
        }
        var headers = Object.keys(rows[0]);
        var $table = $('<table class="widefat striped">');
        var $thead = $('<thead>');
        var $hr = $('<tr>');
        headers.forEach(function(h) { $hr.append($('<th>').text(h.replace(/_/g, ' '))); });
        $thead.append($hr);
        $table.append($thead);
        var $tbody = $('<tbody>');
        rows.forEach(function(row) {
            var $tr = $('<tr>');
            headers.forEach(function(h) {
                // Siempre .text() — nunca innerHTML de valores de BD.
                $tr.append($('<td>').text(row[h] === null || row[h] === undefined ? '' : row[h]));
            });
            $tbody.append($tr);
        });
        $table.append($tbody);
        $wrap.append($table);
    }

    function renderAnalisis(analisis) {
        var $wrap = $('#ss-dep-preview-analisis');
        $wrap.empty();
        if (!analisis) {
            return;
        }
        var blocks = [
            ['descripcion',  'Descripción'],
            ['cualitativo',  'Análisis cualitativo'],
            ['cuantitativo', 'Análisis cuantitativo'],
            ['prediccion',   'Predicción']
        ];
        blocks.forEach(function(b) {
            var key = b[0];
            var label = b[1];
            var $item = $('<div class="ss-dep-analisis-item" style="margin-bottom:10px;">');
            $item.append($('<h5 style="margin:0 0 2px;">').text(label));
            // Siempre .text() — nunca innerHTML.
            $item.append($('<p style="margin:0;color:#50575e;">').text(analisis[key] || ''));
            $wrap.append($item);
        });
    }

    function refresh() {
        var payload = collect();
        payload.action = 'secop_dep_preview';
        payload.nonce = secopDepPreview.nonce;

        var $sql = $('#ss-dep-preview-sql');
        $sql.text(secopDepPreview.strings.loading);

        $.ajax({
            url: secopDepPreview.ajaxUrl,
            type: 'POST',
            data: payload,
            success: function(response) {
                if (!response || !response.success) {
                    $sql.text((response && response.data && response.data.message) || secopDepPreview.strings.error);
                    return;
                }
                var res = response.data;
                var chartType = payload.chart_type || (res.config && res.config.chart_type) || 'bar';

                // Gráfica
                var $render = $('#ss-dep-preview-render');
                $render.empty();
                // v5.3.0: reflejar la altura configurada en el contenedor de la vista previa.
                var h = parseInt(res.config && res.config.chart_height, 10);
                if (h && h > 0) {
                    $render.css({ height: h + 'px', minHeight: h + 'px' });
                }
                if (typeof window.SSChartRender !== 'function' || typeof window.d3plus === 'undefined') {
                    $render.append($('<p>').text(
                        'No se cargó el motor de gráficas (d3plus). Revise que el plugin esté actualizado y recargue con Ctrl+F5. Los datos y la consulta SQL se muestran abajo.'
                    ));
                } else {
                    try {
                        window.SSChartRender('#ss-dep-preview-render', chartType, res.data, toRenderConfig(res.config));
                    } catch (e) {
                        $render.append($('<p>').text('Error al renderizar la gráfica: ' + (e && e.message ? e.message : e)));
                        /* eslint-disable-next-line no-console */
                        console.error('SECOP dep preview render error:', e);
                    }
                }

                // Datos
                renderDataTable(res.data);

                // SQL (escapado vía .text())
                $sql.text(res.sql || '');

                // Análisis en vivo (escapado vía .text())
                renderAnalisis(res.analisis);
            },
            error: function() {
                $sql.text(secopDepPreview.strings.error);
            }
        });
    }

    /**
     * Reconstruye el desplegable «Tipo de gráfica» con SOLO los tipos compatibles
     * con la dimensión seleccionada (sin duplicados, con etiquetas amigables).
     * Conserva la selección previa si sigue siendo válida; si no, usa la primera.
     */
    function rebuildChartTypes() {
        var map = (secopDepPreview && secopDepPreview.chartTypes) || {};
        var dim = $(SEL.dimension).val() || 'dependencia';
        var types = map[dim] || [];
        if (!types.length) {
            return;
        }
        var $sel = $(SEL.chartType);
        var prev = $sel.val();
        var keep = false;
        $sel.empty();
        types.forEach(function (t) {
            if (t.value === prev) {
                keep = true;
            }
            // .text() para la etiqueta: nunca innerHTML.
            $sel.append($('<option>', { value: t.value, text: t.label }));
        });
        $sel.val(keep ? prev : types[0].value);
    }

    var debounceTimer = null;
    function debouncedRefresh() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(refresh, 400);
    }

    $(function() {
        $('#ss-dep-refresh-preview').on('click', refresh);

        // Al cambiar la dimensión, primero se reconstruye el desplegable de tipos
        // (síncrono) y luego se refresca la vista previa con el valor ya válido.
        $(document).on('change', SEL.dimension, rebuildChartTypes);

        var watch = [
            SEL.dimension, SEL.chartType, SEL.metric, SEL.dependencia,
            SEL.order, SEL.orderDir, SEL.colors, SEL.limit,
            SEL.numberFormat, SEL.chartHeight, SEL.xTitle, SEL.yTitle,
            SEL.showLegend, SEL.legendMode, SEL.legendPosition,
            SEL.showToolbar, SEL.toolbarOptions, SEL.tooltipFields
        ].join(', ');
        $(document).on('change', watch, debouncedRefresh);

        // v5.3.1: filtros configurables — añadir/quitar filas y refrescar.
        var filterIndex = $('#dep-filters-rows .dep-filter-row').length;
        $(document).on('click', '#dep-filter-add', function () {
            var tpl = $('#dep-filter-row-tpl').html() || '';
            $('#dep-filters-rows').append(tpl.replace(/\{\{i\}\}/g, filterIndex));
            filterIndex++;
            debouncedRefresh();
        });
        $(document).on('click', '.dep-filter-remove', function () {
            $(this).closest('.dep-filter-row').remove();
            debouncedRefresh();
        });
        // Editar columna/operador/valor de un filtro refresca la vista previa.
        $(document).on('change keyup', '#dep-filters-rows .dep-filter-field, #dep-filters-rows .dep-filter-operator, #dep-filters-rows .dep-filter-value', debouncedRefresh);

        // Refresco inicial.
        refresh();
    });

})(jQuery);
