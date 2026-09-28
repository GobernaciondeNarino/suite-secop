/**
 * SECOP Suite - Admin JavaScript
 *
 * @package SecopSuite
 * @version 4.1.0
 */

(function($) {
    'use strict';

    /**
     * Escapa HTML para prevenir XSS al insertar en el DOM.
     */
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(String(text)));
        return div.innerHTML;
    }

    /**
     * Import Manager
     */
    const ImportManager = {
        isRunning: false,
        checkInterval: null,

        init: function() {
            this.bindEvents();
            this.checkInitialState();
        },

        bindEvents: function() {
            $('#ss-start-import').on('click', this.startImport.bind(this));
            $('#ss-cancel-import').on('click', this.cancelImport.bind(this));
        },

        checkInitialState: function() {
            this.checkProgress();
        },

        startImport: function(e) {
            e.preventDefault();

            if (this.isRunning) {
                return;
            }

            var $button = $('#ss-start-import');
            var $cancelButton = $('#ss-cancel-import');
            var $progressContainer = $('#ss-progress-container');
            var $resultContainer = $('#ss-import-result');

            $button.prop('disabled', true).addClass('updating-message');
            $cancelButton.show();
            $progressContainer.show();
            $resultContainer.hide();

            this.updateProgress(0, 0, 'starting', secopSuiteAdmin.strings.importing);

            var self = this;
            $.ajax({
                url: secopSuiteAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'secop_suite_start_import',
                    nonce: secopSuiteAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.isRunning = true;
                        self.startProgressCheck();
                    } else {
                        self.showError(response.data.message);
                        self.resetUI();
                    }
                },
                error: function(xhr, status) {
                    var msg = 'Error de conexión';
                    if (status === 'timeout') {
                        msg = 'Tiempo de espera agotado. La importación puede seguir ejecutándose en segundo plano.';
                    } else if (xhr.status === 0) {
                        msg = 'No se pudo conectar al servidor. Verifique su conexión a internet.';
                    } else if (xhr.status >= 500) {
                        msg = 'Error interno del servidor (HTTP ' + xhr.status + '). Revise los logs del servidor.';
                    } else if (xhr.status === 403) {
                        msg = 'Acceso denegado. Su sesión puede haber expirado. Recargue la página.';
                    }
                    self.showError(msg);
                    self.resetUI();
                }
            });
        },

        cancelImport: function(e) {
            e.preventDefault();

            if (!confirm(secopSuiteAdmin.strings.confirm_cancel)) {
                return;
            }

            var self = this;
            $.ajax({
                url: secopSuiteAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'secop_suite_cancel_import',
                    nonce: secopSuiteAdmin.nonce
                },
                success: function(response) {
                    self.stopProgressCheck();
                    self.isRunning = false;
                    self.showMessage('warning', response.data.message);
                    self.resetUI();
                }
            });
        },

        startProgressCheck: function() {
            var self = this;
            this.checkInterval = setInterval(function() {
                self.checkProgress();
            }, 2000);
        },

        stopProgressCheck: function() {
            if (this.checkInterval) {
                clearInterval(this.checkInterval);
                this.checkInterval = null;
            }
        },

        checkProgress: function() {
            var self = this;
            $.ajax({
                url: secopSuiteAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'secop_suite_check_progress',
                    nonce: secopSuiteAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;

                        self.updateProgress(
                            data.processed,
                            data.total,
                            data.status,
                            data.message
                        );

                        if (data.status === 'complete') {
                            self.stopProgressCheck();
                            self.isRunning = false;
                            self.showMessage('success', data.message);
                            self.resetUI();
                            setTimeout(function() { location.reload(); }, 2000);
                        } else if (data.status === 'error' || data.status === 'cancelled') {
                            self.stopProgressCheck();
                            self.isRunning = false;
                            self.showMessage('error', data.message);
                            self.resetUI();
                        } else if (data.status === 'running') {
                            self.isRunning = true;
                            $('#ss-start-import').prop('disabled', true);
                            $('#ss-cancel-import').show();
                            $('#ss-progress-container').show();
                        }
                    }
                }
            });
        },

        updateProgress: function(processed, total, status, message) {
            var $fill = $('#ss-progress-fill');
            var $text = $('#ss-progress-text');

            var percentage = 0;
            if (total > 0) {
                percentage = Math.round((processed / total) * 100);
            }

            $fill.css('width', percentage + '%');

            var displayText = message;
            if (status === 'running' && total > 0) {
                displayText = message + ' (' + processed.toLocaleString() + ' / ' + total.toLocaleString() + ' - ' + percentage + '%)';
            }

            $text.text(displayText);
        },

        showMessage: function(type, message) {
            var $container = $('#ss-import-result');
            $container
                .removeClass('ss-notice-success ss-notice-error ss-notice-warning ss-notice-info')
                .addClass('ss-notice-' + type)
                .text(message)
                .show();
        },

        showError: function(message) {
            this.showMessage('error', message);
        },

        resetUI: function() {
            var $button = $('#ss-start-import');
            var $cancelButton = $('#ss-cancel-import');

            $button.prop('disabled', false).removeClass('updating-message');
            $cancelButton.hide();
        }
    };

    /**
     * Settings Manager
     */
    const SettingsManager = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $('#ss_auto_update_enabled, #secop_suite_auto_update_enabled').on('change', function() {
                var $options = $('.ss-auto-update-options');
                if ($(this).is(':checked')) {
                    $options.slideDown();
                } else {
                    $options.slideUp();
                }
            });
        }
    };

    /**
     * Records Manager
     */
    const RecordsManager = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $('.ss-view-details').on('click', this.viewDetails.bind(this));
            $('.ss-modal-close').on('click', this.closeModal);

            $(document).on('click', '.ss-modal', function(e) {
                if ($(e.target).hasClass('ss-modal')) {
                    RecordsManager.closeModal();
                }
            });

            $(document).on('keyup', function(e) {
                if (e.key === 'Escape') {
                    RecordsManager.closeModal();
                }
            });
        },

        viewDetails: function(e) {
            e.preventDefault();

            var $button = $(e.currentTarget);
            var contractId = $button.data('id');
            var $modal = $('#ss-detail-modal');
            var $content = $('#ss-detail-content');

            $content.html('<div class="ss-loading"><span class="spinner is-active"></span> Cargando...</div>');
            $modal.show();

            $.ajax({
                url: '/wp-json/secop-suite/v1/contracts/' + contractId,
                type: 'GET',
                success: function(data) {
                    $content.html(RecordsManager.renderDetails(data));
                },
                error: function(xhr) {
                    var msg = 'Error al cargar los detalles del contrato.';
                    if (xhr.status === 404) {
                        msg = 'Contrato no encontrado.';
                    } else if (xhr.status >= 500) {
                        msg = 'Error del servidor al cargar el contrato.';
                    }
                    $content.html('<div class="ss-error">' + escapeHtml(msg) + '</div>');
                }
            });
        },

        renderDetails: function(contract) {
            var formatCurrency = function(value) {
                if (!value) return '$0';
                return '$' + parseFloat(value).toLocaleString('es-CO', { minimumFractionDigits: 0 });
            };

            var formatDate = function(date) {
                if (!date) return '-';
                return new Date(date).toLocaleDateString('es-CO');
            };

            // Usar escapeHtml para prevenir XSS
            var e = escapeHtml;

            return '<div class="ss-detail-grid">' +
                '<div class="ss-detail-section">' +
                    '<h3>Información General</h3>' +
                    '<table class="ss-detail-table">' +
                        '<tr><th scope="row">Número Contrato</th><td>' + e(contract.numero_del_contrato) + '</td></tr>' +
                        '<tr><th scope="row">Número Proceso</th><td>' + e(contract.numero_de_proceso) + '</td></tr>' +
                        '<tr><th scope="row">Estado</th><td><span class="ss-estado">' + e(contract.estado_del_proceso) + '</span></td></tr>' +
                        '<tr><th scope="row">Tipo de Contrato</th><td>' + e(contract.tipo_de_contrato) + '</td></tr>' +
                        '<tr><th scope="row">Modalidad</th><td>' + e(contract.modalidad_de_contratacion) + '</td></tr>' +
                        '<tr><th scope="row">Origen</th><td>' + e(contract.origen) + '</td></tr>' +
                    '</table>' +
                '</div>' +
                '<div class="ss-detail-section">' +
                    '<h3>Entidad</h3>' +
                    '<table class="ss-detail-table">' +
                        '<tr><th scope="row">Nombre</th><td>' + e(contract.nombre_de_la_entidad) + '</td></tr>' +
                        '<tr><th scope="row">NIT</th><td>' + e(contract.nit_de_la_entidad) + '</td></tr>' +
                        '<tr><th scope="row">Nivel</th><td>' + e(contract.nivel_entidad) + '</td></tr>' +
                        '<tr><th scope="row">Departamento</th><td>' + e(contract.departamento_entidad) + '</td></tr>' +
                        '<tr><th scope="row">Municipio</th><td>' + e(contract.municipio_entidad) + '</td></tr>' +
                    '</table>' +
                '</div>' +
                '<div class="ss-detail-section">' +
                    '<h3>Contratista</h3>' +
                    '<table class="ss-detail-table">' +
                        '<tr><th scope="row">Nombre / Razón Social</th><td>' + e(contract.nom_raz_social_contratista) + '</td></tr>' +
                        '<tr><th scope="row">Documento</th><td>' + e(contract.tipo_documento_proveedor) + ' ' + e(contract.documento_proveedor) + '</td></tr>' +
                    '</table>' +
                '</div>' +
                '<div class="ss-detail-section">' +
                    '<h3>Fechas y Valor</h3>' +
                    '<table class="ss-detail-table">' +
                        '<tr><th scope="row">Fecha de Firma</th><td>' + formatDate(contract.fecha_de_firma_del_contrato) + '</td></tr>' +
                        '<tr><th scope="row">Inicio Ejecución</th><td>' + formatDate(contract.fecha_inicio_ejecucion) + '</td></tr>' +
                        '<tr><th scope="row">Fin Ejecución</th><td>' + formatDate(contract.fecha_fin_ejecucion) + '</td></tr>' +
                        '<tr><th scope="row">Valor del Contrato</th><td><strong>' + formatCurrency(contract.valor_contrato) + '</strong></td></tr>' +
                    '</table>' +
                '</div>' +
                '<div class="ss-detail-section ss-detail-full">' +
                    '<h3>Objeto</h3>' +
                    '<p><strong>Objeto a contratar:</strong> ' + e(contract.objeto_a_contratar || '-') + '</p>' +
                    '<p><strong>Objeto del proceso:</strong> ' + e(contract.objeto_del_proceso || '-') + '</p>' +
                '</div>' +
                (contract.url_contrato ?
                    '<div class="ss-detail-section ss-detail-full">' +
                        '<h3>Enlace SECOP</h3>' +
                        '<a href="' + e(contract.url_contrato) + '" target="_blank" rel="noopener noreferrer" class="button button-primary">' +
                            'Ver en SECOP <span class="dashicons dashicons-external"></span>' +
                        '</a>' +
                    '</div>'
                : '') +
            '</div>';
        },

        closeModal: function() {
            $('#ss-detail-modal').hide();
        }
    };

    /**
     * Truncate Manager
     */
    const TruncateManager = {
        init: function() {
            $('#ss-truncate-table').on('click', function() {
                $('#ss-truncate-confirm').slideDown(200);
            });

            $('#ss-truncate-cancel-btn').on('click', function() {
                $('#ss-truncate-confirm').slideUp(200);
            });

            $('#ss-truncate-confirm-btn').on('click', function() {
                var $btn = $(this);
                $btn.prop('disabled', true).text('Eliminando...');

                $.ajax({
                    url: secopSuiteAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'secop_suite_truncate_table',
                        nonce: secopSuiteAdmin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#ss-truncate-confirm').hide();
                            $('#ss-import-result')
                                .removeClass('ss-notice-error ss-notice-warning')
                                .addClass('ss-notice-success')
                                .text(response.data.message)
                                .show();
                            setTimeout(function() { location.reload(); }, 1500);
                        } else {
                            $btn.prop('disabled', false).text('Sí, eliminar todos los datos');
                            alert(response.data.message);
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).text('Sí, eliminar todos los datos');
                        alert('Error de conexión');
                    }
                });
            });
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        if ($('#ss-start-import').length) {
            ImportManager.init();
            SettingsManager.init();
            TruncateManager.init();
        }

        if ($('.ss-records-table').length) {
            RecordsManager.init();
        }
    });

})(jQuery);
