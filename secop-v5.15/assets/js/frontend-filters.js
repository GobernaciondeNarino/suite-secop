/**
 * SECOP Suite - Frontend Filters JavaScript
 *
 * @package SecopSuite
 * @version 4.1.0
 */

(function($) {
    'use strict';

    /**
     * Filter Manager - Maneja una instancia de filtro
     */
    class FilterManager {
        constructor(container) {
            this.$container = $(container);
            this.uniqueId = this.$container.attr('id');
            this.filterId = this.$container.data('filter-id');
            this.config = this.loadConfig();
            this.currentPage = 1;

            this.init();
        }

        loadConfig() {
            const configEl = document.getElementById(this.uniqueId + '-config');
            if (configEl) {
                try {
                    return JSON.parse(configEl.textContent);
                } catch (e) {
                    console.error('Error parsing filter config:', e);
                }
            }
            return {};
        }

        init() {
            this.bindEvents();
            this.loadSelectOptions();
            this.loadCheckboxOptions();
        }

        bindEvents() {
            const self = this;

            // Form submit
            this.$container.find('.ss-filter-form').on('submit', function(e) {
                e.preventDefault();
                self.currentPage = 1;
                self.search();
            });

            // Clear
            this.$container.find('.ss-filter-clear-btn').on('click', function() {
                self.$container.find('.ss-filter-results').hide();
                self.$container.find('.ss-filter-no-results').hide();
                self.currentPage = 1;
            });

            // Pagination
            this.$container.on('click', '.ss-filter-page-btn', function(e) {
                e.preventDefault();
                const page = $(this).data('page');
                if (page) {
                    self.currentPage = page;
                    self.search();
                }
            });
        }

        loadSelectOptions() {
            const self = this;
            this.$container.find('.ss-filter-select').each(function() {
                const $select = $(this);
                const column = $select.data('column');

                // Skip if options were already rendered server-side
                if ($select.find('option').length > 1) return;

                $.ajax({
                    url: secopSuiteFilter.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'secop_suite_filter_options',
                        nonce: self.config.filterNonce || secopSuiteFilter.nonce,
                        table: self.config.tableName,
                        column: column
                    },
                    success: function(response) {
                        if (response.success && response.data.options) {
                            response.data.options.forEach(function(opt) {
                                $select.append($('<option>').val(opt).text(opt));
                            });
                        }
                    }
                });
            });
        }

        loadCheckboxOptions() {
            const self = this;
            this.$container.find('.ss-filter-checkboxes').each(function() {
                const $container = $(this);
                const column = $container.data('column');

                // Skip if options were already rendered server-side (no loading spinner present)
                if ($container.find('.ss-filter-loading-options').length === 0) return;

                $.ajax({
                    url: secopSuiteFilter.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'secop_suite_filter_options',
                        nonce: self.config.filterNonce || secopSuiteFilter.nonce,
                        table: self.config.tableName,
                        column: column
                    },
                    success: function(response) {
                        $container.empty();
                        if (response.success && response.data.options) {
                            response.data.options.forEach(function(opt) {
                                $container.append(
                                    $('<label class="ss-filter-checkbox-label">').append(
                                        $('<input type="checkbox">').attr('name', column + '[]').val(opt),
                                        $('<span>').text(opt)
                                    )
                                );
                            });
                        }
                    }
                });
            });
        }

        search() {
            const self = this;
            const $form = this.$container.find('.ss-filter-form');
            const $loading = this.$container.find('.ss-filter-loading');
            const $results = this.$container.find('.ss-filter-results');
            const $noResults = this.$container.find('.ss-filter-no-results');

            // Collect filter values
            var filters = {};
            $form.find('input, select').each(function() {
                var name = $(this).attr('name');
                if (!name) return;

                if ($(this).is(':checkbox')) {
                    if ($(this).is(':checked')) {
                        // Remove [] from name for grouping
                        var baseName = name.replace('[]', '');
                        if (!filters[baseName]) filters[baseName] = [];
                        filters[baseName].push($(this).val());
                    }
                } else {
                    var val = $(this).val();
                    if (val) {
                        filters[name] = val;
                    }
                }
            });

            $loading.show();
            $results.hide();
            $noResults.hide();

            $.ajax({
                url: secopSuiteFilter.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'secop_suite_filter_search',
                    nonce: self.config.filterNonce || secopSuiteFilter.nonce,
                    filter_id: self.filterId,
                    filters: filters,
                    page: self.currentPage
                },
                success: function(response) {
                    $loading.hide();

                    if (response.success && response.data.data.length > 0) {
                        self.renderResults(response.data);
                        $results.show();
                    } else if (response.success) {
                        $noResults.show();
                    } else {
                        $noResults.find('p').text(response.data.message || secopSuiteFilter.strings.error);
                        $noResults.show();
                    }
                },
                error: function() {
                    $loading.hide();
                    $noResults.find('p').text(secopSuiteFilter.strings.error);
                    $noResults.show();
                }
            });
        }

        renderResults(responseData) {
            const data = responseData.data;
            const total = responseData.total;
            const page = responseData.page;
            const totalPages = responseData.total_pages;
            const perPage = responseData.per_page;
            const urlField = responseData.url_field;

            const $thead = this.$container.find('.ss-filter-results-table thead');
            const $tbody = this.$container.find('.ss-filter-results-table tbody');
            const $count = this.$container.find('.ss-filter-results-count');
            const $pagination = this.$container.find('.ss-filter-pagination');

            $thead.empty();
            $tbody.empty();

            // Count
            const from = (page - 1) * perPage + 1;
            const to = Math.min(page * perPage, total);
            $count.text(
                secopSuiteFilter.strings.showing + ' ' + from + '-' + to +
                ' ' + secopSuiteFilter.strings.of + ' ' + total +
                ' ' + secopSuiteFilter.strings.results
            );

            if (data.length === 0) return;

            // Determine columns to show
            var columns = this.config.resultColumns && this.config.resultColumns.length > 0
                ? this.config.resultColumns
                : Object.keys(data[0]).filter(function(k) { return k !== 'id' && k !== urlField; });

            // Headers
            var $headerRow = $('<tr>');
            columns.forEach(function(col) {
                $headerRow.append($('<th>').text(col.replace(/_/g, ' ')));
            });
            if (urlField) {
                $headerRow.append($('<th>').text('').css('width', '50px'));
            }
            $thead.append($headerRow);

            // Rows
            var self = this;
            data.forEach(function(row) {
                var $tr = $('<tr>');
                columns.forEach(function(col) {
                    var value = row[col] || '';
                    // Format large numbers
                    if (typeof value === 'string' && /^\d+\.?\d*$/.test(value) && parseFloat(value) > 1000) {
                        value = new Intl.NumberFormat('es-CO').format(parseFloat(value));
                    }
                    $tr.append($('<td>').text(value));
                });

                // URL link icon — only render an <a href> when the URL has a valid
                // http(s) scheme (bloquea javascript:/data: → anti-XSS). Mirror dep-*.
                if (urlField && typeof row[urlField] === 'string' && /^https?:\/\//i.test(row[urlField])) {
                    var url = row[urlField];
                    $tr.append(
                        $('<td>').addClass('ss-filter-url-cell').append(
                            $('<a>').attr({
                                href: url,
                                target: '_blank',
                                rel: 'noopener noreferrer',
                                title: secopSuiteFilter.strings.viewProcess
                            }).addClass('ss-filter-url-link').html(
                                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">' +
                                '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>' +
                                '<polyline points="15 3 21 3 21 9"></polyline>' +
                                '<line x1="10" y1="14" x2="21" y2="3"></line>' +
                                '</svg>'
                            )
                        )
                    );
                } else if (urlField) {
                    $tr.append($('<td>'));
                }

                $tbody.append($tr);
            });

            // Pagination
            $pagination.empty();
            if (totalPages > 1) {
                var $nav = $('<nav class="ss-filter-pagination-nav">');

                if (page > 1) {
                    $nav.append(
                        $('<a href="#" class="ss-filter-page-btn ss-btn ss-btn-secondary">').data('page', page - 1).text(secopSuiteFilter.strings.prev)
                    );
                }

                // Page numbers
                var startPage = Math.max(1, page - 2);
                var endPage = Math.min(totalPages, page + 2);

                for (var i = startPage; i <= endPage; i++) {
                    var $pageBtn = $('<a href="#" class="ss-filter-page-btn ss-filter-page-num">').data('page', i).text(i);
                    if (i === page) {
                        $pageBtn.addClass('ss-filter-page-active');
                    }
                    $nav.append($pageBtn);
                }

                if (page < totalPages) {
                    $nav.append(
                        $('<a href="#" class="ss-filter-page-btn ss-btn ss-btn-secondary">').data('page', page + 1).text(secopSuiteFilter.strings.next)
                    );
                }

                $pagination.append($nav);
            }
        }
    }

    /**
     * Initialize all filters on page load
     */
    $(document).ready(function() {
        $('.ss-filter-container').each(function() {
            new FilterManager(this);
        });
    });

    window.SSFilterManager = FilterManager;

})(jQuery);
