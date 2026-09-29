/**
 * SECOP Suite - Admin Filters JavaScript
 *
 * @package SecopSuite
 * @version 4.1.0
 */

(function($) {
    'use strict';

    const FilterConfigManager = {
        columns: [],

        init: function() {
            this.bindEvents();

            const selectedTable = $('#ss_filter_table_name').val();
            if (selectedTable) {
                this.loadColumns(selectedTable);
            }
        },

        bindEvents: function() {
            const self = this;

            // Table selection
            $('#ss_filter_table_name').on('change', function() {
                const table = $(this).val();
                if (table) {
                    self.loadColumns(table);
                    $('#ss-filter-fields-section, #ss-result-columns-section').show();
                } else {
                    $('#ss-filter-fields-section, #ss-result-columns-section').hide();
                }
            });

            // Add filter field
            $('#ss-add-filter-field').on('click', function() {
                self.addFilterFieldRow();
            });

            // Remove filter field
            $(document).on('click', '.ss-remove-filter-field', function() {
                $(this).closest('.ss-filter-field-row').remove();
                self.reindexFilterFields();
            });

            // Preview DISTINCT values when type or column changes in a field row
            $('#ss-filter-fields-container').on(
                'change',
                '.ss-filter-column-select, select[name$="[type]"]',
                function() {
                    var $row = $(this).closest('.ss-filter-field-row');
                    if ($row.length) {
                        self.loadFieldPreview($row);
                    }
                }
            );

            // Copy shortcode
            $('.ss-copy-shortcode').on('click', function() {
                const text = $('#ss-filter-shortcode-display').text();
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

        loadColumns: function(table) {
            const self = this;

            $.ajax({
                url: secopSuiteFilterAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'secop_suite_get_table_columns',
                    nonce: secopSuiteFilterAdmin.nonce,
                    table: table
                },
                success: function(response) {
                    if (response.success) {
                        self.columns = response.data.columns;
                        self.populateColumnSelects();
                        self.renderResultColumnsCheckboxes();
                        // Auto-show preview for rows that already have a column+type saved
                        $('#ss-filter-fields-container .ss-filter-field-row').each(function() {
                            self.loadFieldPreview($(this));
                        });
                    }
                }
            });
        },

        populateColumnSelects: function() {
            const self = this;

            $('.ss-filter-column-select').each(function() {
                const $select = $(this);
                const savedValue = $select.data('saved-value') || $select.val();
                const $firstOption = $select.find('option:first');

                $select.empty().append($firstOption);

                self.columns.forEach(function(col) {
                    const isNumeric = col.type.includes('int') || col.type.includes('decimal');
                    const isDate = col.type.includes('date') || col.type.includes('time');

                    let label = col.name;
                    if (isNumeric) label += ' (numérico)';
                    if (isDate) label += ' (fecha)';

                    $select.append(
                        $('<option>').val(col.name).text(label)
                    );
                });

                if (savedValue) {
                    $select.val(savedValue);
                }
            });
        },

        renderResultColumnsCheckboxes: function() {
            const $container = $('#ss-result-columns-container');
            $container.empty();

            const savedColumns = (typeof ssFilterSavedConfig !== 'undefined' && ssFilterSavedConfig.result_columns) || [];

            const $grid = $('<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px;">');

            this.columns.forEach(function(col) {
                const checked = savedColumns.indexOf(col.name) !== -1 ? 'checked' : '';
                $grid.append(
                    $('<label style="display: flex; align-items: center; gap: 5px; padding: 4px 8px; background: #fff; border: 1px solid #dcdcde; border-radius: 3px; font-size: 12px;">').append(
                        $('<input type="checkbox" name="ss_result_columns[]">').val(col.name).prop('checked', !!checked),
                        $('<span>').text(col.name)
                    )
                );
            });

            $container.append($grid);
        },

        addFilterFieldRow: function() {
            const index = $('#ss-filter-fields-container .ss-filter-field-row').length;
            const template = $('#ss-filter-field-template').html().replace(/{{index}}/g, index);
            const $row = $(template);

            $('#ss-filter-fields-container').append($row);

            // Populate column selects in new row
            this.populateNewRowSelects($row);
        },

        populateNewRowSelects: function($row) {
            const self = this;
            $row.find('.ss-filter-column-select').each(function() {
                const $select = $(this);
                const $firstOption = $select.find('option:first');
                $select.empty().append($firstOption);

                self.columns.forEach(function(col) {
                    $select.append(
                        $('<option>').val(col.name).text(col.name)
                    );
                });
            });
        },

        reindexFilterFields: function() {
            $('#ss-filter-fields-container .ss-filter-field-row').each(function(index) {
                $(this).find('[name]').each(function() {
                    const name = $(this).attr('name');
                    const newName = name.replace(/\[\d+\]/, '[' + index + ']');
                    $(this).attr('name', newName);
                });
            });
        },

        /**
         * Fetch and display DISTINCT column values as a preview inside a field row.
         * Uses jQuery .text() exclusively — never .html() of DB values — to avoid XSS.
         */
        loadFieldPreview: function($row) {
            const self = this;
            const table = $('#ss_filter_table_name').val();
            const $colSelect = $row.find('.ss-filter-column-select');
            const $typeSelect = $row.find('select[name$="[type]"]');
            const column = $colSelect.val();
            const type = $typeSelect.val();
            const $preview = $row.find('.ss-field-values-preview');

            const previewTypes = ['select', 'range', 'checkbox'];
            if (!table || !column || previewTypes.indexOf(type) === -1) {
                $preview.empty().hide();
                return;
            }

            $preview.show().empty().append(
                $('<em>').text('Cargando valores...')
            );

            $.ajax({
                url: secopSuiteFilterAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'secop_suite_filter_options',
                    nonce: secopSuiteFilterAdmin.nonce,
                    table: table,
                    column: column
                },
                success: function(response) {
                    $preview.empty();
                    if (response.success && response.data.options && response.data.options.length) {
                        const opts = response.data.options;
                        const $heading = $('<strong>').text(
                            'Valores en la BD (sin repetir): ' + opts.length
                        );
                        const $chips = $('<div>').css({
                            marginTop: '4px',
                            display: 'flex',
                            flexWrap: 'wrap',
                            gap: '4px'
                        });
                        const limit = Math.min(opts.length, 30);
                        for (var i = 0; i < limit; i++) {
                            $chips.append(
                                $('<span>').css({
                                    background: '#e0e0e0',
                                    borderRadius: '3px',
                                    padding: '2px 6px'
                                }).text(opts[i])   // .text() is XSS-safe
                            );
                        }
                        if (opts.length > 30) {
                            $chips.append(
                                $('<span>').text('… y ' + (opts.length - 30) + ' más')
                            );
                        }
                        $preview.append($heading, $chips).show();
                    } else {
                        $preview.hide();
                    }
                },
                error: function() {
                    $preview.text('Error al cargar valores.').show();
                }
            });
        }
    };

    $(document).ready(function() {
        if ($('#ss_filter_table_name').length) {
            FilterConfigManager.init();
        }
    });

})(jQuery);
