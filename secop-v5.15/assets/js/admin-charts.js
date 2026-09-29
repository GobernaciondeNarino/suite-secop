/**
 * SECOP Data Visualizer - Admin JavaScript
 * 
 * @package SecopDataVisualizer
 * @version 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Chart Config Manager
     */
    const ChartConfigManager = {
        columns: [],

        init: function() {
            this.bindEvents();
            this.initColorPreview();
            
            // Load columns if table is already selected
            const selectedTable = $('#ss_table_name').val();
            if (selectedTable) {
                this.loadColumns(selectedTable);
            }

            // Restore saved values
            this.restoreSavedConfig();
        },

        bindEvents: function() {
            const self = this;

            // Chart type selection
            $('.ss-chart-type-option').on('click', function() {
                $('.ss-chart-type-option').removeClass('selected');
                $(this).addClass('selected');
                $(this).find('input').prop('checked', true);
                var chartType = $(this).find('input').val();
                self.updateChartGuide(chartType);
                self.updateFieldRequirements(chartType);
                self.updateMultiYVisibility(chartType);
            });

            // Initial guide render
            const initialType = $('input[name="ss_chart_type"]:checked').val();
            if (initialType) {
                self.updateChartGuide(initialType);
                self.updateFieldRequirements(initialType);
                self.updateMultiYVisibility(initialType);
            }

            // Table selection
            $('#ss_table_name').on('change', function() {
                const table = $(this).val();
                if (table) {
                    self.loadColumns(table);
                    $('#ss-fields-section, #ss-filters-section').show();
                } else {
                    $('#ss-fields-section, #ss-filters-section').hide();
                }
            });

            // X Field selection - show date grouping if date field
            $('#ss_x_field').on('change', function() {
                self.checkDateGroupingVisibility();
            });

            // Color preview
            $('#ss_colors').on('input', function() {
                self.updateColorPreview($(this).val());
            });

            // Legend checkbox toggle
            $('#ss_show_legend_check').on('change', function() {
                if ($(this).is(':checked')) {
                    $('.ss-legend-options').slideDown(200);
                } else {
                    $('.ss-legend-options').slideUp(200);
                }
            });

            // Toolbar toggle
            $('#ss_show_toolbar').on('change', function() {
                if ($(this).is(':checked')) {
                    $('.ss-toolbar-options').show();
                } else {
                    $('.ss-toolbar-options').hide();
                }
            });

            // Guide accordion toggle
            $('#ss-guide-toggle').on('click', function() {
                var $content = $('#ss-guide-content');
                var $arrow = $(this).find('.ss-guide-arrow');
                $content.slideToggle(200);
                $arrow.toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
            });

            // Multi-Y enable/disable toggle
            $('#ss-enable-multi-y').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#ss-multi-y-content').slideDown(200);
                } else {
                    $('#ss-multi-y-content').slideUp(200);
                    // Clear y_fields when disabled
                    $('#ss-y-fields-container').empty();
                }
            });

            // Add Y field
            $('#ss-add-y-field').on('click', function() {
                self.addYFieldRow();
            });

            // Remove Y field
            $(document).on('click', '.ss-remove-y-field', function() {
                $(this).closest('.ss-y-field-row').remove();
                self.reindexYFields();
            });

            // Custom query toggle
            $('#ss_use_custom_query').on('change', function() {
                if ($(this).is(':checked')) {
                    $('.ss-custom-query-row').show();
                } else {
                    $('.ss-custom-query-row').hide();
                }
            });

            // Advanced section toggle
            $('.ss-toggle-advanced').on('click', function() {
                const $content = $(this).closest('.ss-config-section').find('.ss-advanced-content');
                $content.slideToggle();
                $(this).text($content.is(':visible') ? secopSuiteChartAdmin.strings.collapse || 'Colapsar' : secopSuiteChartAdmin.strings.expand || 'Expandir');
            });

            // Add filter
            $('#ss-add-filter').on('click', function() {
                self.addFilterRow();
            });

            // Remove filter
            $(document).on('click', '.ss-remove-filter', function() {
                $(this).closest('.ss-filter-row').remove();
                self.reindexFilters();
            });

            // Preview button
            $('#ss-refresh-preview').on('click', function() {
                self.refreshPreview();
            });

            // Copy shortcode
            $('.ss-copy-shortcode').on('click', function() {
                const text = $('#ss-shortcode-display').text();
                navigator.clipboard.writeText(text).then(function() {
                    const $btn = $('.ss-copy-shortcode');
                    const originalText = $btn.html();
                    $btn.html('<span class="dashicons dashicons-yes"></span> Copiado');
                    setTimeout(function() {
                        $btn.html(originalText);
                    }, 2000);
                });
            });
        },

        checkDateGroupingVisibility: function() {
            const $xField = $('#ss_x_field');
            const selectedOption = $xField.find('option:selected');
            const fieldType = selectedOption.data('type') || '';
            
            // Check if it's a date/datetime field
            const isDateField = fieldType.includes('date') || fieldType.includes('time');
            
            if (isDateField) {
                $('.ss-date-grouping-row').slideDown();
            } else {
                $('.ss-date-grouping-row').slideUp();
                $('#ss_x_date_grouping').val('');
            }
        },

        loadColumns: function(table) {
            const self = this;
            const $selects = $('.ss-column-select');
            
            $selects.prop('disabled', true);
            
            $.ajax({
                url: secopSuiteChartAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'secop_suite_get_table_columns',
                    nonce: secopSuiteChartAdmin.nonce,
                    table: table
                },
                success: function(response) {
                    if (response.success) {
                        self.columns = response.data.columns;
                        self.populateColumnSelects();
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert(secopSuiteChartAdmin.strings.selectTable);
                },
                complete: function() {
                    $selects.prop('disabled', false);
                }
            });
        },

        populateColumnSelects: function() {
            const self = this;
            const $selects = $('.ss-column-select');
            
            $selects.each(function() {
                const $select = $(this);
                const currentValue = $select.val() || $select.data('saved-value');
                const $firstOption = $select.find('option:first');
                
                $select.empty().append($firstOption);
                
                self.columns.forEach(function(col) {
                    const isNumeric = col.type.includes('int') || col.type.includes('decimal') || col.type.includes('float') || col.type.includes('double');
                    const isDate = col.type.includes('date') || col.type.includes('time');
                    
                    let label = col.name;
                    if (isNumeric) label += ' (numérico)';
                    if (isDate) label += ' (fecha)';
                    
                    $select.append(
                        $('<option>')
                            .val(col.name)
                            .text(label)
                            .data('type', col.type)
                    );
                });
                
                // Restore value
                if (currentValue) {
                    $select.val(currentValue);
                }
            });

            // Check date grouping visibility after populating
            this.checkDateGroupingVisibility();
        },

        addFilterRow: function() {
            const index = $('#ss-filters-container .ss-filter-row').length;
            const template = $('#ss-filter-template').html().replace(/{{index}}/g, index);
            const $row = $(template);
            
            $('#ss-filters-container').append($row);
            
            // Populate the new select
            this.populateFilterSelect($row.find('.ss-column-select'));
        },

        populateFilterSelect: function($select) {
            const self = this;
            const $firstOption = $select.find('option:first');
            
            $select.empty().append($firstOption);
            
            self.columns.forEach(function(col) {
                $select.append(
                    $('<option>')
                        .val(col.name)
                        .text(col.name)
                );
            });
        },

        reindexFilters: function() {
            $('#ss-filters-container .ss-filter-row').each(function(index) {
                $(this).find('[name]').each(function() {
                    const name = $(this).attr('name');
                    const newName = name.replace(/\[\d+\]/, '[' + index + ']');
                    $(this).attr('name', newName);
                });
            });
        },

        initColorPreview: function() {
            const colors = $('#ss_colors').val();
            if (colors) {
                this.updateColorPreview(colors);
            }
        },

        updateColorPreview: function(colorsStr) {
            const $preview = $('#ss-color-preview');
            $preview.empty();
            
            const colors = colorsStr.split(',').map(c => c.trim()).filter(c => c);
            
            colors.forEach(function(color) {
                if (/^#[0-9A-Fa-f]{6}$/.test(color)) {
                    $preview.append(
                        $('<div class="ss-color-swatch">').css('background-color', color)
                    );
                }
            });
        },

        refreshPreview: function() {
            const self = this;
            const $preview = $('#ss-chart-preview');
            const $button = $('#ss-refresh-preview');
            
            // Collect form data
            const formData = {
                action: 'secop_suite_preview_data',
                nonce: secopSuiteChartAdmin.nonce,
                chart_type: $('input[name="ss_chart_type"]:checked').val(),
                table_name: $('#ss_table_name').val(),
                x_field: $('#ss_x_field').val(),
                x_date_grouping: $('#ss_x_date_grouping').val(),
                y_field: $('#ss_y_field').val(),
                group_by: $('#ss_group_by').val(),
                aggregate: $('#ss_aggregate').val(),
                color_field: $('#ss_color_field').val(),
                date_field: $('#ss_date_field').val(),
                date_from: $('#ss_date_from').val(),
                date_to: $('#ss_date_to').val(),
                limit: $('#ss_limit').val() || 100,
                filters: [],
                y_fields: []
            };

            // Collect y_fields
            $('#ss-y-fields-container .ss-y-field-row').each(function() {
                var col = $(this).find('.ss-y-field-select').val();
                if (col) {
                    formData.y_fields.push({
                        column: col,
                        label: $(this).find('.ss-y-field-label').val() || ''
                    });
                }
            });

            // Collect filters
            $('#ss-filters-container .ss-filter-row').each(function() {
                const field = $(this).find('.ss-filter-field').val();
                if (field) {
                    formData.filters.push({
                        field: field,
                        operator: $(this).find('.ss-filter-operator').val(),
                        value: $(this).find('.ss-filter-value').val()
                    });
                }
            });
            
            var hasYData = formData.y_field || (formData.y_fields && formData.y_fields.length > 0);
            if (!formData.table_name || !formData.x_field || !hasYData) {
                $preview.html('<p class="ss-preview-placeholder">Por favor configure la tabla, eje X y al menos un eje Y</p>');
                return;
            }
            
            $button.prop('disabled', true).addClass('updating-message');
            $preview.html('<p class="ss-preview-placeholder">Cargando vista previa...</p>');
            
            $.ajax({
                url: secopSuiteChartAdmin.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success && response.data.data.length > 0) {
                        self.renderPreview(response.data.data, formData);
                    } else {
                        $preview.html('<p class="ss-preview-placeholder">No hay datos para mostrar</p>');
                    }
                },
                error: function() {
                    $preview.html('<p class="ss-preview-placeholder">' + secopSuiteChartAdmin.strings.previewError + '</p>');
                },
                complete: function() {
                    $button.prop('disabled', false).removeClass('updating-message');
                }
            });
        },

        renderPreview: function(data, config) {
            const $preview = $('#ss-chart-preview');
            $preview.empty();
            
            const chartData = data.map(function(d) {
                return {
                    x: d.x_value,
                    y: parseFloat(d.y_value) || 0,
                    group: d.group_value || d.x_value
                };
            });
            
            const colorsStr = $('#ss_colors').val() || '#844e80,#ff7300,#ffc53b,#3eba6a,#0080c3';
            const colors = colorsStr.split(',').map(c => c.trim());
            const groups = [...new Set(chartData.map(d => d.group))];
            const colorScale = d3.scaleOrdinal().domain(groups).range(colors);
            
            const chartType = config.chart_type || 'bar';
            let chart;
            
            switch (chartType) {
                case 'bar':
                    chart = new d3plus.BarChart()
                        .data(chartData)
                        .groupBy('group')
                        .x('x')
                        .y('y')
                        .select('#ss-chart-preview')
                        .color(function(d) { return colorScale(d.group || d.x); })
                        .yConfig({
                            title: $('#ss_y_axis_title').val() || 'Valor',
                            tickFormat: function(d) {
                                if (d >= 1e9) return (d / 1e9).toFixed(1) + 'MMll';
                                if (d >= 1e6) return (d / 1e6).toFixed(1) + 'M';
                                if (d >= 1e3) return (d / 1e3).toFixed(1) + 'K';
                                return d;
                            }
                        })
                        .legend(false)
                        .locale('es_ES');
                    break;
                    
                case 'line':
                    chart = new d3plus.LinePlot()
                        .data(chartData)
                        .groupBy('group')
                        .x('x')
                        .y('y')
                        .select('#ss-chart-preview')
                        .color(function(d) { return colorScale(d.group); })
                        .legend(false)
                        .locale('es_ES');
                    break;
                    
                case 'pie':
                    chart = new d3plus.Pie()
                        .data(chartData)
                        .groupBy('x')
                        .value('y')
                        .select('#ss-chart-preview')
                        .color(function(d) { return colorScale(d.x); })
                        .legend(true)
                        .locale('es_ES');
                    break;
                    
                case 'treemap':
                    chart = new d3plus.Treemap()
                        .data(chartData)
                        .groupBy('x')
                        .sum('y')
                        .select('#ss-chart-preview')
                        .color(function(d) { return colorScale(d.x); })
                        .locale('es_ES');
                    break;

                case 'tree':
                    var treeData = chartData.map(function(d) {
                        return { id: d.x, value: d.y, parent: d.group !== d.x ? d.group : null };
                    });
                    var existingIds = treeData.map(function(d) { return d.id; });
                    var parentIds = [...new Set(treeData.filter(function(d) { return d.parent; }).map(function(d) { return d.parent; }))];
                    parentIds.forEach(function(p) {
                        if (existingIds.indexOf(p) === -1) {
                            treeData.push({ id: p, value: 0, parent: null });
                        }
                    });
                    chart = new d3plus.Tree()
                        .data(treeData)
                        .groupBy('id')
                        .layoutPadding(2)
                        .select('#ss-chart-preview')
                        .color(function(d) { return colorScale(d.id); })
                        .locale('es_ES');
                    break;

                case 'pack':
                    chart = new d3plus.Pack()
                        .data(chartData.map(function(d) { return { id: d.x, value: d.y, group: d.group }; }))
                        .groupBy('id')
                        .sum('value')
                        .select('#ss-chart-preview')
                        .color(function(d) { return colorScale(d.group || d.id); })
                        .locale('es_ES');
                    break;

                case 'network':
                    var netNodes = [];
                    var netLinks = [];
                    var netNodeIds = {};
                    chartData.forEach(function(d) {
                        if (!netNodeIds[d.x]) {
                            netNodeIds[d.x] = true;
                            netNodes.push({ id: d.x, value: d.y });
                        }
                        if (d.group && d.group !== d.x) {
                            if (!netNodeIds[d.group]) {
                                netNodeIds[d.group] = true;
                                netNodes.push({ id: d.group, value: 0 });
                            }
                            netLinks.push({ source: d.group, target: d.x });
                        }
                    });
                    chart = new d3plus.Network()
                        .data(netNodes)
                        .links(netLinks)
                        .groupBy('id')
                        .select('#ss-chart-preview')
                        .color(function(d) { return colorScale(d.id); })
                        .locale('es_ES');
                    break;

                case 'stacked_bar':
                    chart = new d3plus.BarChart()
                        .data(chartData)
                        .groupBy('group')
                        .x('x')
                        .y('y')
                        .stacked(true)
                        .select('#ss-chart-preview')
                        .color(function(d) { return colorScale(d.group); })
                        .legend(true)
                        .locale('es_ES');
                    break;

                case 'grouped_bar':
                    chart = new d3plus.BarChart()
                        .data(chartData)
                        .groupBy('group')
                        .x('x')
                        .y('y')
                        .stacked(false)
                        .select('#ss-chart-preview')
                        .color(function(d) { return colorScale(d.group); })
                        .barPadding(2)
                        .groupPadding(10)
                        .legend(true)
                        .locale('es_ES');
                    break;

                case 'area':
                    chart = new d3plus.AreaPlot()
                        .data(chartData)
                        .groupBy('group')
                        .x('x')
                        .y('y')
                        .select('#ss-chart-preview')
                        .color(function(d) { return colorScale(d.group); })
                        .legend(false)
                        .locale('es_ES');
                    break;

                case 'donut':
                    chart = new d3plus.Donut()
                        .data(chartData)
                        .groupBy('x')
                        .value('y')
                        .select('#ss-chart-preview')
                        .color(function(d) { return colorScale(d.x); })
                        .legend(true)
                        .locale('es_ES');
                    break;

                default:
                    chart = new d3plus.BarChart()
                        .data(chartData)
                        .groupBy('x')
                        .x('x')
                        .y('y')
                        .select('#ss-chart-preview')
                        .color(function(d) { return colorScale(d.x); })
                        .legend(false)
                        .locale('es_ES');
            }

            chart.render();
        },

        // ── Multi-Y field methods ──────────────────────────────────

        addYFieldRow: function() {
            var index = $('#ss-y-fields-container .ss-y-field-row').length;
            var template = $('#ss-y-field-template').html().replace(/{{index}}/g, index);
            var $row = $(template);
            $('#ss-y-fields-container').append($row);
            this.populateFilterSelect($row.find('.ss-y-field-select'));
        },

        reindexYFields: function() {
            $('#ss-y-fields-container .ss-y-field-row').each(function(index) {
                $(this).find('[name]').each(function() {
                    var name = $(this).attr('name');
                    var newName = name.replace(/\[\d+\]/, '[' + index + ']');
                    $(this).attr('name', newName);
                });
            });
        },

        /**
         * Show/hide the multi-Y section based on chart type.
         * Bar-type charts support multi-Y, others don't.
         */
        updateMultiYVisibility: function(chartType) {
            var multiYTypes = ['bar', 'stacked_bar', 'grouped_bar', 'line', 'area'];
            if (multiYTypes.indexOf(chartType) !== -1) {
                $('#ss-multi-y-row').show();
            } else {
                $('#ss-multi-y-row').hide();
            }
        },

        /**
         * Show simple * marker on required fields per chart type.
         * Replaces verbose badges with a clean asterisk.
         */
        updateFieldRequirements: function(chartType) {
            var reqs = {
                bar:         { x_field: true, y_field: true, group_by: false, aggregate: true },
                line:        { x_field: true, y_field: true, group_by: false, aggregate: true },
                area:        { x_field: true, y_field: true, group_by: false, aggregate: true },
                pie:         { x_field: true, y_field: true, group_by: false, aggregate: true },
                donut:       { x_field: true, y_field: true, group_by: false, aggregate: true },
                treemap:     { x_field: true, y_field: true, group_by: false, aggregate: true },
                tree:        { x_field: true, y_field: false, group_by: true, aggregate: false },
                pack:        { x_field: true, y_field: true, group_by: false, aggregate: true },
                network:     { x_field: true, y_field: false, group_by: true, aggregate: false },
                stacked_bar: { x_field: true, y_field: true, group_by: true, aggregate: true },
                grouped_bar: { x_field: true, y_field: true, group_by: true, aggregate: true }
            };

            var fieldMap = {
                x_field: '#ss_x_field',
                y_field: '#ss_y_field',
                group_by: '#ss_group_by',
                aggregate: '#ss_aggregate'
            };

            var chartReqs = reqs[chartType] || reqs.bar;

            // Reset all field states
            Object.keys(fieldMap).forEach(function(key) {
                var $select = $(fieldMap[key]);
                var $row = $select.closest('tr');
                var $label = $row.find('th label');
                var isRequired = chartReqs[key];

                // Remove old asterisks
                $label.find('.ss-req-mark').remove();
                // Reset select style
                $select.css({ border: '', background: '' });
                $row.css('opacity', '1');

                if (isRequired) {
                    $label.append(' <span class="ss-req-mark" style="color:#e74c3c;font-weight:700;">*</span>');
                }
            });

            // Note for group_by when required
            var $groupNote = $('#ss-group-by-note');
            if (!$groupNote.length) {
                $('#ss_group_by').closest('td').append('<p class="description" id="ss-group-by-note" style="display:none;margin-top:8px;"></p>');
                $groupNote = $('#ss-group-by-note');
            }
            if (chartType === 'stacked_bar' || chartType === 'grouped_bar') {
                $groupNote.html('<span style="color:#e74c3c;">* Requerido para crear las series apiladas/agrupadas.</span>').show();
            } else if (chartType === 'tree' || chartType === 'network') {
                $groupNote.html('<span style="color:#e74c3c;">* Requerido para definir las relaciones padre-hijo.</span>').show();
            } else {
                $groupNote.hide();
            }
        },

        /**
         * Chart Guide — Heat map of recommended variables per chart type
         */
        updateChartGuide: function(chartType) {
            var $matrix = $('#ss-guide-matrix');
            var $name = $('#ss-guide-chart-name');
            $matrix.empty();

            // Chart type display names
            var chartNames = {
                bar: 'Barras', line: 'Líneas', area: 'Área', pie: 'Pie/Torta',
                donut: 'Donut', treemap: 'Treemap', tree: 'Árbol (Tree)',
                pack: 'Burbujas (Pack)', network: 'Red (Network)',
                stacked_bar: 'Barras Apiladas', grouped_bar: 'Barras Agrupadas'
            };
            $name.text('— ' + (chartNames[chartType] || chartType));

            // Variable fields and their relevance per chart type
            // 'high' = optimal, 'medium' = compatible, 'low' = possible, 'none' = not recommended
            var fields = [
                { label: 'Eje X / Categoría', key: 'x_field' },
                { label: 'Eje Y / Valor', key: 'y_field' },
                { label: 'Agrupar Por', key: 'group_by' },
                { label: 'Color Por', key: 'color_field' },
                { label: 'Agregación', key: 'aggregate' },
                { label: 'Fecha Agrupada', key: 'date_grouping' },
                { label: 'Límite', key: 'limit' },
                { label: 'Timeline', key: 'timeline' }
            ];

            var guide = {
                bar:         { x_field: 'high', y_field: 'high', group_by: 'medium', color_field: 'medium', aggregate: 'high', date_grouping: 'medium', limit: 'medium', timeline: 'medium' },
                line:        { x_field: 'high', y_field: 'high', group_by: 'high', color_field: 'medium', aggregate: 'high', date_grouping: 'high', limit: 'low', timeline: 'high' },
                area:        { x_field: 'high', y_field: 'high', group_by: 'high', color_field: 'medium', aggregate: 'high', date_grouping: 'high', limit: 'low', timeline: 'medium' },
                pie:         { x_field: 'high', y_field: 'high', group_by: 'none', color_field: 'high', aggregate: 'high', date_grouping: 'none', limit: 'high', timeline: 'none' },
                donut:       { x_field: 'high', y_field: 'high', group_by: 'none', color_field: 'high', aggregate: 'high', date_grouping: 'none', limit: 'high', timeline: 'none' },
                treemap:     { x_field: 'high', y_field: 'high', group_by: 'low', color_field: 'high', aggregate: 'high', date_grouping: 'none', limit: 'high', timeline: 'none' },
                tree:        { x_field: 'high', y_field: 'medium', group_by: 'high', color_field: 'medium', aggregate: 'low', date_grouping: 'none', limit: 'high', timeline: 'none' },
                pack:        { x_field: 'high', y_field: 'high', group_by: 'medium', color_field: 'high', aggregate: 'high', date_grouping: 'none', limit: 'high', timeline: 'none' },
                network:     { x_field: 'high', y_field: 'medium', group_by: 'high', color_field: 'medium', aggregate: 'low', date_grouping: 'none', limit: 'high', timeline: 'none' },
                stacked_bar: { x_field: 'high', y_field: 'high', group_by: 'high', color_field: 'medium', aggregate: 'high', date_grouping: 'high', limit: 'medium', timeline: 'medium' },
                grouped_bar: { x_field: 'high', y_field: 'high', group_by: 'high', color_field: 'medium', aggregate: 'high', date_grouping: 'medium', limit: 'medium', timeline: 'low' }
            };

            var heatLabels = { high: '●●●', medium: '●●', low: '●', none: '✗' };
            var heatTips = { high: 'Óptimo', medium: 'Compatible', low: 'Posible', none: 'No recomendado' };
            var chartGuide = guide[chartType] || guide.bar;

            // Build header row
            $matrix.append($('<div class="ss-guide-header">').text('Variable'));
            $matrix.append($('<div class="ss-guide-header">').text('Relevancia'));

            // Build data rows
            fields.forEach(function(f) {
                var level = chartGuide[f.key] || 'low';
                $matrix.append($('<div class="ss-guide-field">').text(f.label));
                $matrix.append(
                    $('<div class="ss-guide-cell ss-heat-' + level + '">').text(heatLabels[level]).attr('title', heatTips[level])
                );
            });

            // Set grid to 2 columns for this layout
            $matrix.css('grid-template-columns', '1fr 100px');
        },

        restoreSavedConfig: function() {
            if (typeof ssSavedConfig !== 'undefined' && ssSavedConfig) {
                // Store saved values for later restoration
                if (ssSavedConfig.x_field) {
                    $('#ss_x_field').data('saved-value', ssSavedConfig.x_field);
                }
                if (ssSavedConfig.x_date_grouping) {
                    $('#ss_x_date_grouping').data('saved-value', ssSavedConfig.x_date_grouping);
                }
                if (ssSavedConfig.y_field) {
                    $('#ss_y_field').data('saved-value', ssSavedConfig.y_field);
                }
                if (ssSavedConfig.group_by) {
                    $('#ss_group_by').data('saved-value', ssSavedConfig.group_by);
                }
                if (ssSavedConfig.color_field) {
                    $('#ss_color_field').data('saved-value', ssSavedConfig.color_field);
                }
                if (ssSavedConfig.date_field) {
                    $('#ss_date_field').data('saved-value', ssSavedConfig.date_field);
                }
                if (ssSavedConfig.order_by) {
                    $('#ss_order_by').data('saved-value', ssSavedConfig.order_by);
                }

                // Restore filter field values
                if (ssSavedConfig.filters && ssSavedConfig.filters.length > 0) {
                    ssSavedConfig.filters.forEach(function(filter, index) {
                        const $row = $('#ss-filters-container .ss-filter-row').eq(index);
                        if ($row.length) {
                            $row.find('.ss-filter-field').data('saved-value', filter.field);
                        }
                    });
                }

                // Restore y_fields saved values
                if (ssSavedConfig.y_fields && ssSavedConfig.y_fields.length > 0) {
                    ssSavedConfig.y_fields.forEach(function(yf, index) {
                        var $row = $('#ss-y-fields-container .ss-y-field-row').eq(index);
                        if ($row.length) {
                            $row.find('.ss-y-field-select').data('saved-value', yf.column);
                        }
                    });
                }

                // Restore x_date_grouping value after columns load
                if (ssSavedConfig.x_date_grouping) {
                    $('#ss_x_date_grouping').val(ssSavedConfig.x_date_grouping);
                    if (ssSavedConfig.x_date_grouping !== '') {
                        $('.ss-date-grouping-row').show();
                    }
                }
            }
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        if ($('.ss-config-wrapper').length) {
            ChartConfigManager.init();
        }
    });

})(jQuery);
