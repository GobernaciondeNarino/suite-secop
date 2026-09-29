/**
 * SECOP Suite - Frontend JavaScript
 *
 * @package SecopSuite
 * @version 5.2.3
 */

(function($) {
    'use strict';

    /**
     * Formateador de números estilo colombiano
     */
    const NumberFormatter = {
        colombiano: function(value) {
            if (value >= 1e12) return (value / 1e12).toFixed(1).replace('.', ',') + ' Billones';
            if (value >= 1e9) return (value / 1e9).toFixed(1).replace('.', ',') + ' MMll';
            if (value >= 1e6) return (value / 1e6).toFixed(1).replace('.', ',') + ' Millones';
            if (value >= 1e3) return (value / 1e3).toFixed(1).replace('.', ',') + ' Mil';
            return value.toLocaleString('es-CO');
        },
        millones: function(value) {
            if (value >= 1e12) return (value / 1e12).toFixed(2) + 'B';
            if (value >= 1e9) return (value / 1e9).toFixed(2) + 'MMll';
            if (value >= 1e6) return (value / 1e6).toFixed(2) + 'M';
            if (value >= 1e3) return (value / 1e3).toFixed(2) + 'K';
            return value.toString();
        },
        internacional: function(value) {
            return new Intl.NumberFormat('en-US').format(value);
        },
        sin_formato: function(value) {
            return value.toString();
        },
        format: function(value, format) {
            return (this[format] || this.colombiano)(value);
        },
        fullFormat: function(value) {
            return new Intl.NumberFormat('es-CO', { style: 'decimal', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(value);
        }
    };

    /**
     * Safely resolve a d3plus constructor by name.
     * Tries d3plus.Name first, then d3plus_hierarchy.Name, etc.
     */
    function getD3PlusClass(name) {
        if (typeof d3plus !== 'undefined' && d3plus[name]) return d3plus[name];
        // Fallback to global window in case of separate package loads
        if (typeof window['d3plus_hierarchy'] !== 'undefined' && window['d3plus_hierarchy'][name]) return window['d3plus_hierarchy'][name];
        if (typeof window['d3plus_plot'] !== 'undefined' && window['d3plus_plot'][name]) return window['d3plus_plot'][name];
        if (typeof window['d3plus_network'] !== 'undefined' && window['d3plus_network'][name]) return window['d3plus_network'][name];
        return null;
    }

    /**
     * Standalone chart renderer (module-level, reusable).
     *
     * Contains the data-mapping logic plus the d3plus chart-creation switch,
     * so the same engine can render both the AJAX-by-id frontend charts and
     * inline-data previews (e.g. the Contratación card editor live preview).
     *
     * @param {string} renderTarget CSS selector of the container to render into.
     * @param {string} chartType    Chart type (bar, line, pie, …).
     * @param {Array}  rows         Array of {x_value, y_value, group_value} objects.
     * @param {Object} config       Chart config (colors, numberFormat, showLegend, …).
     * @returns {Object|null} The d3plus chart instance, or null if there is no data.
     */
    function renderChartInto(renderTarget, chartType, rows, config) {
        config = config || {};
        if (!rows || rows.length === 0) {
            return null;
        }

        const colors = config.colors || ['#844e80', '#ff7300', '#ffc53b', '#3eba6a', '#0080c3'];
        const numberFormat = config.numberFormat || 'colombiano';
        const isMultiY = config.multiY || false;

        // v5.13.2: ¿hay una agrupación REAL (multi-serie)? Si group_value viene vacío
        // o coincide con la X (caso típico de la evolución mensual, sin group_by), NO
        // hay serie real. Para line/area todos los puntos deben formar UNA sola serie
        // conectada; si cada punto fuese su propia serie, d3plus no dibuja la línea
        // (verificado: la gráfica sale vacía). Las gráficas de categoría (bar, pie…)
        // siguen coloreando por 'group'.
        var hasRealGroup = rows.some(function(d) {
            return d.group_value !== null && d.group_value !== undefined &&
                   d.group_value !== '' && String(d.group_value) !== String(d.x_value);
        });
        var singleSeriesLabel = config.yAxisTitle || 'Total';

        // Prepare data — force x to string so d3plus won't parse years as dates
        const chartData = rows.map(function(d) {
            var xVal = (d.x_value !== null && d.x_value !== undefined) ? String(d.x_value) : '';
            return {
                x: xVal,
                y: parseFloat(d.y_value) || 0,
                group: d.group_value || xVal,
                // Serie para line/área: única (conectada) cuando no hay agrupación real.
                series: hasRealGroup ? (d.group_value || xVal) : singleSeriesLabel,
                // v5.3.2: conteo de contratos por categoría (solo si la query lo devolvió).
                count: (d.y_count !== undefined && d.y_count !== null) ? (parseFloat(d.y_count) || 0) : null
            };
        });

        if (isMultiY) config.showLegend = true;

        const groups = [...new Set(chartData.map(function(d) { return d.group; }))];
        const colorScale = d3.scaleOrdinal().domain(groups).range(colors);

        // v5.3.2: tooltip configurable. Por defecto (sin tooltipFields) muestra
        // categoría + valor → [secop_chart] queda byte-a-byte igual.
        var tooltipFields = (Array.isArray(config.tooltipFields) && config.tooltipFields.length)
            ? config.tooltipFields
            : ['categoria', 'valor'];
        var tooltipRowBuilders = {
            categoria: [config.xAxisTitle || 'Categoría', function(d) { return d.x; }],
            valor:     [config.yAxisTitle || 'Valor', function(d) { return NumberFormatter.fullFormat(d.y); }],
            conteo:    ['Contratos', function(d) { return (d.count !== null && d.count !== undefined) ? d.count : ''; }]
        };
        var tooltipBody = [];
        tooltipFields.forEach(function(f) {
            if (tooltipRowBuilders[f]) tooltipBody.push(tooltipRowBuilders[f]);
        });
        if (!tooltipBody.length) {
            tooltipBody = [tooltipRowBuilders.categoria, tooltipRowBuilders.valor];
        }
        const tooltipConfig = { tbody: tooltipBody };
        const yConfig = {
            title: config.yAxisTitle || 'Valor',
            tickFormat: function(d) { return NumberFormatter.format(d, numberFormat); }
        };
        const showLegend = config.showLegend || false;
        const legendPosition = config.legendPosition || 'bottom';
        const legendMode = config.legendMode || 'text';

        const chart = createChartInstance(chartType, chartData, renderTarget, colorScale, tooltipConfig, yConfig, showLegend, config);

        if (chart) {
            // Apply legend position and mode
            if (showLegend && typeof chart.legendPosition === 'function') {
                chart.legendPosition(legendPosition);
            }
            if (showLegend && legendMode === 'icon' && typeof chart.legendConfig === 'function') {
                chart.legendConfig({ label: function() { return ''; } });
            }
            // v5.1.9: click-to-drill. Solo cuando la config lo activa (no afecta a
            // [secop_chart], cuya config no define drill). No-op si el tipo no emite clicks.
            if (config.drill && config.drillColumn && typeof chart.on === 'function') {
                chart.on('click', function(d) {
                    var val = (d && d.x !== undefined && d.x !== null) ? d.x : (d && d.id);
                    if (val !== undefined && val !== null && typeof window.SSChartDrill === 'function') {
                        window.SSChartDrill(String(config.drillColumn), String(val));
                    }
                });
            }
            chart.render();
        }
        return chart;
    }

    /**
     * d3plus chart-creation switch (module-level).
     * Moved verbatim from ChartManager._createChart so it can be reused by
     * renderChartInto() and by the ChartManager instance method.
     */
    function createChartInstance(type, data, target, colorScale, tooltipConfig, yConfig, legend, config) {
        var Cls;

        switch (type) {
            case 'bar':
            case 'stacked_bar':
            case 'grouped_bar':
                Cls = getD3PlusClass('BarChart');
                if (!Cls) throw new Error('d3plus.BarChart not available');
                var chart = new Cls()
                    .data(data).groupBy('group').x('x').y('y')
                    .select(target)
                    .color(function(d) { return colorScale(d.group || d.x); })
                    .tooltipConfig(tooltipConfig).yConfig(yConfig)
                    .legend(legend).locale('es_ES');
                if (type === 'stacked_bar') chart.stacked(true);
                if (type === 'grouped_bar') chart.stacked(false).barPadding(2).groupPadding(10);
                if (config.showTimeline && typeof chart.time === 'function') {
                    chart.time('x');
                    if (typeof chart.timeline === 'function') chart.timeline(true);
                }
                return chart;

            case 'line':
                Cls = getD3PlusClass('LinePlot');
                if (!Cls) throw new Error('d3plus.LinePlot not available');
                var lc = new Cls()
                    .data(data).groupBy('series').x('x').y('y')
                    .select(target)
                    .color(function(d) { return colorScale(d.series); })
                    .tooltipConfig(tooltipConfig).yConfig(yConfig)
                    .legend(legend).locale('es_ES');
                if (config.showTimeline && typeof lc.time === 'function') {
                    lc.time('x');
                    if (typeof lc.timeline === 'function') lc.timeline(true);
                }
                return lc;

            case 'area':
                Cls = getD3PlusClass('AreaPlot') || getD3PlusClass('StackedArea');
                if (!Cls) throw new Error('d3plus.AreaPlot not available');
                return new Cls()
                    .data(data).groupBy('series').x('x').y('y')
                    .select(target)
                    .color(function(d) { return colorScale(d.series); })
                    .tooltipConfig(tooltipConfig).yConfig(yConfig)
                    .legend(legend).locale('es_ES');

            case 'pie':
                Cls = getD3PlusClass('Pie');
                if (!Cls) throw new Error('d3plus.Pie not available');
                return new Cls()
                    .data(data).groupBy('x').value('y')
                    .select(target)
                    .color(function(d) { return colorScale(d.x); })
                    .tooltipConfig(tooltipConfig)
                    .legend(true).locale('es_ES');

            case 'donut':
                Cls = getD3PlusClass('Donut');
                if (!Cls) throw new Error('d3plus.Donut not available');
                return new Cls()
                    .data(data).groupBy('x').value('y')
                    .select(target)
                    .color(function(d) { return colorScale(d.x); })
                    .tooltipConfig(tooltipConfig)
                    .legend(true).locale('es_ES');

            case 'treemap':
                Cls = getD3PlusClass('Treemap');
                if (!Cls) throw new Error('d3plus.Treemap not available');
                return new Cls()
                    .data(data).groupBy('x').sum('y')
                    .select(target)
                    .color(function(d) { return colorScale(d.x); })
                    .tooltipConfig(tooltipConfig).locale('es_ES');

            case 'tree':
                Cls = getD3PlusClass('Tree');
                if (!Cls) throw new Error('d3plus.Tree not available');
                var treeData = data.map(function(d) { return { id: d.x, value: d.y, parent: d.group !== d.x ? d.group : null }; });
                var ids = treeData.map(function(d) { return d.id; });
                var parents = [...new Set(treeData.filter(function(d) { return d.parent; }).map(function(d) { return d.parent; }))];
                parents.forEach(function(p) { if (ids.indexOf(p) === -1) treeData.push({ id: p, value: 0, parent: null }); });
                return new Cls().data(treeData).groupBy('id').select(target)
                    .color(function(d) { return colorScale(d.id); })
                    .legend(legend).locale('es_ES');

            case 'pack':
                Cls = getD3PlusClass('Pack');
                if (!Cls) throw new Error('d3plus.Pack not available');
                // Se conservan los campos canónicos x/y/count (no se remapea a
                // {id,value}), igual que Treemap, para que el tooltip compartido
                // (categoría, valor, Nº de contratos) funcione. v5.12.2.
                return new Cls()
                    .data(data).groupBy('x').sum('y')
                    .select(target)
                    .color(function(d) { return colorScale(d.x); })
                    .tooltipConfig(tooltipConfig)
                    .legend(legend).locale('es_ES');

            case 'network':
                Cls = getD3PlusClass('Network');
                if (!Cls) throw new Error('d3plus.Network not available');
                var nodes = [], links = [], nodeIds = {};
                data.forEach(function(d) {
                    if (!nodeIds[d.x]) { nodeIds[d.x] = true; nodes.push({ id: d.x, value: d.y }); }
                    if (d.group && d.group !== d.x) {
                        if (!nodeIds[d.group]) { nodeIds[d.group] = true; nodes.push({ id: d.group, value: 0 }); }
                        links.push({ source: d.group, target: d.x });
                    }
                });
                return new Cls().data(nodes).links(links).groupBy('id')
                    .select(target)
                    .color(function(d) { return colorScale(d.id); })
                    .locale('es_ES');

            default:
                Cls = getD3PlusClass('BarChart');
                if (!Cls) throw new Error('d3plus library not loaded');
                return new Cls().data(data).groupBy('x').x('x').y('y')
                    .select(target)
                    .color(function(d) { return colorScale(d.x); })
                    .tooltipConfig(tooltipConfig).yConfig(yConfig).locale('es_ES');
        }
    }

    /**
     * Chart Manager
     */
    class ChartManager {
        constructor(container) {
            this.$container = $(container);
            this.uniqueId = this.$container.attr('id');
            this.chartId = this.$container.data('chart-id');
            this.chartType = this.$container.data('chart-type');
            this.config = this.loadConfig();
            this.data = [];
            this.chart = null;
            this.init();
        }

        loadConfig() {
            const configEl = document.getElementById(this.uniqueId + '-config');
            if (configEl) {
                try { return JSON.parse(configEl.textContent); } catch (e) { console.error('Config parse error:', e); }
            }
            return {};
        }

        init() {
            this.bindToolbarEvents();
            this.bindModalEvents();
            this.loadData();
        }

        bindToolbarEvents() {
            const self = this;
            this.$container.find('.ss-toolbar-btn').off('click.ssc').on('click.ssc', function() {
                const action = $(this).data('action');
                switch (action) {
                    case 'detail': self.showDetailModal(); break;
                    case 'share': self.showShareModal(); break;
                    case 'data': self.showDataModal(); break;
                    case 'image': self.downloadImage(); break;
                    case 'download': self.downloadCSV(); break;
                }
            });
        }

        bindModalEvents() {
            const self = this;
            ['data-modal', 'share-modal', 'detail-modal'].forEach(function(suffix) {
                const $modal = $('#' + self.uniqueId + '-' + suffix);
                $modal.find('.ss-modal-close, .ss-modal-close-btn').on('click', function() { $modal.fadeOut(200); });
                $modal.find('.ss-modal-overlay').on('click', function() { $modal.fadeOut(200); });
            });
            $(document)
                .off('keyup.ssc' + this.uniqueId)
                .on('keyup.ssc' + this.uniqueId, function(e) {
                    if (e.key === 'Escape') { $('.ss-modal:visible').fadeOut(200); }
                });
            var $shareModal = $('#' + this.uniqueId + '-share-modal');
            $shareModal.find('.ss-share-btn[data-network]').on('click', function(e) { e.preventDefault(); self.shareToNetwork($(this).data('network')); });
            $shareModal.find('[data-action="copy-link"]').on('click', function() { self.copyLink(); });
            $('#' + this.uniqueId + '-data-modal').find('[data-action="download-from-modal"]').on('click', function() { self.downloadCSV(); });
        }

        loadData() {
            const self = this;
            $.ajax({
                url: secopSuiteChart.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'secop_suite_get_chart_data',
                    nonce: this.config.chartNonce || secopSuiteChart.nonce,
                    chart_id: this.chartId,
                    dependencia: this.$container.attr('data-dependencia') || ''
                },
                success: function(response) {
                    if (response.success) { self.data = response.data.data; self.renderChart(); }
                    else { self.showError(response.data.message); }
                },
                error: function() { self.showError(secopSuiteChart.strings.error); }
            });
        }

        renderChart() {
            if (!this.data || this.data.length === 0) {
                this.showError(secopSuiteChart.strings.noData);
                return;
            }

            const renderTarget = '#' + this.uniqueId + '-render';

            try {
                this.chart = renderChartInto(renderTarget, this.chartType, this.data, this.config);
            } catch (e) {
                console.error('SECOP Chart render error:', e);
                this.showError('Error: ' + e.message);
                return;
            }

            if (this.chart) {
                this._waitForRender(renderTarget);
            }
        }

        _waitForRender(renderTarget) {
            const self = this;
            let attempts = 0;
            const check = setInterval(function() {
                attempts++;
                if (document.querySelector(renderTarget + ' svg')) {
                    self.$container.addClass('ss-loaded');
                    clearInterval(check);
                } else if (attempts >= 300) {
                    clearInterval(check);
                    self.showError(secopSuiteChart.strings.error || 'Error al renderizar');
                }
            }, 100);
        }

        showError(message) {
            this.$container.find('.ss-loading').hide();
            this.$container.find('.ss-error-message').show().find('p').text(message);
        }

        showDetailModal() { $('#' + this.uniqueId + '-detail-modal').fadeIn(200); }
        showShareModal() { $('#' + this.uniqueId + '-share-modal').fadeIn(200); }

        showDataModal() {
            const $modal = $('#' + this.uniqueId + '-data-modal');
            const $thead = $modal.find('thead');
            const $tbody = $modal.find('tbody');
            $thead.empty(); $tbody.empty();
            if (this.data && this.data.length > 0) {
                const headers = Object.keys(this.data[0]);
                const $hr = $('<tr>');
                headers.forEach(function(h) { $hr.append($('<th>').text(h.replace(/_/g, ' '))); });
                $thead.append($hr);
                this.data.forEach(function(row) {
                    const $dr = $('<tr>');
                    headers.forEach(function(h) {
                        var v = row[h];
                        if (!isNaN(parseFloat(v)) && h.includes('value')) v = NumberFormatter.fullFormat(parseFloat(v));
                        $dr.append($('<td>').text(v));
                    });
                    $tbody.append($dr);
                });
            }
            $modal.fadeIn(200);
        }

        shareToNetwork(network) {
            var url = encodeURIComponent(window.location.href + '#' + this.uniqueId);
            var title = encodeURIComponent(this.config.title || 'Gráfica SECOP');
            var shareUrl = '';
            switch (network) {
                case 'facebook': shareUrl = 'https://www.facebook.com/sharer/sharer.php?u=' + url; break;
                case 'twitter': shareUrl = 'https://twitter.com/intent/tweet?url=' + url + '&text=' + title; break;
                case 'linkedin': shareUrl = 'https://www.linkedin.com/sharing/share-offsite/?url=' + url; break;
                case 'whatsapp': shareUrl = 'https://api.whatsapp.com/send?text=' + title + '%20' + url; break;
            }
            if (shareUrl) window.open(shareUrl, '_blank', 'width=600,height=400');
        }

        copyLink() {
            var link = window.location.href + '#' + this.uniqueId;
            navigator.clipboard.writeText(link).then(() => { this.showToast(secopSuiteChart.strings.copied); });
        }

        downloadImage() {
            var self = this;
            var el = document.querySelector('#' + this.uniqueId + '-render');
            if (!el) return;
            this.showToast('Generando imagen...');
            html2canvas(el, { backgroundColor: '#ffffff', scale: 2, logging: false }).then(function(canvas) {
                var a = document.createElement('a'); a.download = 'grafica-' + self.chartId + '.png'; a.href = canvas.toDataURL('image/png'); a.click();
            }).catch(function() { self.showToast('Error al generar imagen'); });
        }

        downloadCSV() {
            var a = document.createElement('a'); a.href = secopSuiteChart.restUrl + 'chart/' + this.chartId + '/csv'; a.download = 'datos-' + this.chartId + '.csv'; a.click();
        }

        showToast(message) {
            $('.ss-toast').remove();
            var $t = $('<div class="ss-toast">').text(message);
            $('body').append($t);
            setTimeout(function() { $t.fadeOut(300, function() { $(this).remove(); }); }, 3000);
        }
    }

    // Initialize with lazy loading
    $(document).ready(function() {
        if ('IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) { new ChartManager(entry.target); observer.unobserve(entry.target); }
                });
            }, { rootMargin: '200px' });
            $('.ss-chart-container').each(function() { observer.observe(this); });
        } else {
            $('.ss-chart-container').each(function() { new ChartManager(this); });
        }
    });

    window.SSChartManager = ChartManager;
    window.SSNumberFormatter = NumberFormatter;
    window.SSChartRender = renderChartInto;

})(jQuery);
