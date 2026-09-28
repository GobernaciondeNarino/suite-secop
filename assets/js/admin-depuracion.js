/**
 * SECOP Suite - Depuración de base de datos (admin).
 *
 * Todos los datos del servidor se insertan con .text() / nodos de texto.
 *
 * @package SecopSuite
 * @version 5.17.0
 */
(function ($) {
    'use strict';

    if (typeof secopDedup === 'undefined') {
        return;
    }

    var cfg = secopDedup;
    var S = cfg.strings;
    var lastAnalysis = null;

    function post(action, data) {
        return $.post(cfg.ajaxUrl, $.extend({ action: 'secop_dedup_' + action, nonce: cfg.nonce }, data || {}));
    }

    function fmt(n) {
        return Number(n || 0).toLocaleString('es-CO');
    }

    function status(msg, type) {
        var $s = $('#ss-dedup-status');
        $s.removeClass('is-error is-success is-info').addClass('is-' + (type || 'info'));
        $s.text(msg || '');
    }

    function failMessage(xhr) {
        var r = xhr && xhr.responseJSON;
        return (r && r.data && r.data.message) ? r.data.message : S.error;
    }

    function currentTable() {
        return cfg.tables[$('#ss-dedup-table').val()] || null;
    }

    function selectedColumns() {
        return $('#ss-dedup-columns input:checked').map(function () { return this.value; }).get();
    }

    function resetResult() {
        lastAnalysis = null;
        $('#ss-dedup-result').prop('hidden', true);
        $('#ss-dedup-confirm').prop('checked', false);
        $('#ss-dedup-run').prop('disabled', true);
    }

    function applyPreset(key) {
        var t = currentTable();
        if (!t) return;
        var preset = t.presets[key];
        if (!preset) return;
        $('#ss-dedup-columns input').each(function () {
            this.checked = preset.columns.indexOf(this.value) !== -1;
        });
        $('#ss-dedup-skip-empty').prop('checked', !!preset.skip_empty);
        resetResult();
    }

    function renderTable() {
        var t = currentTable();
        var $presets = $('#ss-dedup-presets').empty();
        var $cols = $('#ss-dedup-columns').empty();
        if (!t) return;

        $presets.append($('<legend class="screen-reader-text"></legend>').text(S.criterion));

        var first = true;
        $.each(t.presets, function (key, preset) {
            var id = 'ss-dedup-preset-' + key;
            var $label = $('<label class="ss-dedup-preset"></label>').attr('for', id);
            var $radio = $('<input type="radio" name="ss-dedup-preset">').attr({ id: id, value: key });
            if (first) { $radio.prop('checked', true); first = false; }
            $label.append($radio, document.createTextNode(' ' + preset.label));
            $presets.append($label);
        });
        var $custom = $('<label class="ss-dedup-preset" for="ss-dedup-preset-custom"></label>');
        $custom.append(
            $('<input type="radio" name="ss-dedup-preset" id="ss-dedup-preset-custom" value="custom">'),
            document.createTextNode(' ' + S.custom)
        );
        $presets.append($custom);

        $.each(t.columns, function (_, c) {
            var id = 'ss-dedup-col-' + c.name;
            var $l = $('<label class="ss-dedup-col"></label>').attr('for', id);
            $l.append(
                $('<input type="checkbox">').attr({ id: id, value: c.name }),
                document.createTextNode(' '),
                $('<code></code>').text(c.name),
                $('<span class="ss-dedup-coltype"></span>').text(' ' + c.type)
            );
            $cols.append($l);
        });

        applyPreset($presets.find('input:checked').val());
    }

    function renderAnalysis(a) {
        lastAnalysis = a;
        var $sum = $('#ss-dedup-summary').empty();
        var items = [
            [fmt(a.total), 'registros en la tabla'],
            [fmt(a.groups), 'grupos de duplicados'],
            [fmt(a.redundant), 'filas a eliminar']
        ];
        $.each(items, function (_, it) {
            $sum.append($('<div class="ss-dedup-stat"></div>').append(
                $('<strong></strong>').text(it[0]),
                $('<span></span>').text(it[1])
            ));
        });
        if (a.redundant > a.max_run) {
            $sum.append($('<p class="description"></p>').text(
                'Se eliminarán como máximo ' + fmt(a.max_run) + ' filas por ejecución; repita la operación hasta terminar.'
            ));
        }

        var $tbl = $('#ss-dedup-sample').empty();
        if (!a.redundant) {
            $('#ss-dedup-danger').prop('hidden', true);
            status(S.noDups, 'success');
            $('#ss-dedup-result').prop('hidden', false);
            return;
        }

        var shown = a.sample.length ? Object.keys(a.sample[0].values) : [];
        var $head = $('<tr></tr>');
        $.each(['Copias', 'Se conserva (ID)', 'Se eliminan (IDs)'].concat(shown), function (_, h) {
            $head.append($('<th scope="col"></th>').text(h));
        });
        $tbl.append($('<thead></thead>').append($head));
        var $body = $('<tbody></tbody>');
        $.each(a.sample, function (_, g) {
            var $tr = $('<tr></tr>');
            $tr.append($('<td></td>').text(g.count));
            $tr.append($('<td></td>').text(g.keep));
            $tr.append($('<td></td>').text(g.delete.join(', ') + (g.count - 1 > g.delete.length ? ' …' : '')));
            $.each(shown, function (_, c) {
                var v = g.values[c];
                $tr.append($('<td></td>').text(v === null ? '(vacío)' : v));
            });
            $body.append($tr);
        });
        $tbl.append($body);
        if (a.groups > a.sample.length) {
            $tbl.append($('<caption class="ss-dedup-caption"></caption>').text(
                'Muestra de los ' + a.sample.length + ' grupos más grandes de ' + fmt(a.groups) + '.'
            ));
        }

        $('#ss-dedup-danger').prop('hidden', false);
        status('', 'info');
        $('#ss-dedup-result').prop('hidden', false);
    }

    function analyze() {
        var cols = selectedColumns();
        if (!cols.length) { status(S.pickColumns, 'error'); return; }
        resetResult();
        status(S.analyzing, 'info');
        $('#ss-dedup-analyze').prop('disabled', true);
        post('analyze', {
            table: $('#ss-dedup-table').val(),
            columns: cols,
            keep: $('#ss-dedup-keep').val(),
            skip_empty: $('#ss-dedup-skip-empty').is(':checked') ? '1' : '0'
        }).done(function (r) {
            if (r && r.success) { renderAnalysis(r.data); } else { status((r && r.data && r.data.message) || S.error, 'error'); }
        }).fail(function (xhr) {
            status(failMessage(xhr), 'error');
        }).always(function () {
            $('#ss-dedup-analyze').prop('disabled', false);
        });
    }

    function run() {
        if (!lastAnalysis || !$('#ss-dedup-confirm').is(':checked')) return;
        if (!window.confirm(S.confirmRun)) return;
        $('#ss-dedup-run').prop('disabled', true);
        status(S.deleting, 'info');
        post('run', {
            table: lastAnalysis.table,
            columns: lastAnalysis.criteria,
            keep: lastAnalysis.keep,
            skip_empty: lastAnalysis.skip_empty ? '1' : '0',
            confirm: '1'
        }).done(function (r) {
            var d = (r && r.data) || {};
            if (r && r.success) {
                var msg = fmt(d.deleted) + ' filas duplicadas eliminadas y respaldadas.';
                if (d.remaining > 0) msg += ' Quedan ' + fmt(d.remaining) + ': ejecute de nuevo el análisis.';
                status(msg, 'success');
                setTimeout(function () { window.location.reload(); }, 2500);
            } else {
                status((d.message || S.error) + (d.deleted ? ' (' + fmt(d.deleted) + ' filas ya eliminadas y respaldadas)' : ''), 'error');
                $('#ss-dedup-run').prop('disabled', false);
            }
        }).fail(function (xhr) {
            status(failMessage(xhr), 'error');
            $('#ss-dedup-run').prop('disabled', false);
        });
    }

    function batchAction(action, batch, question) {
        if (!window.confirm(question)) return;
        post(action, { batch: batch }).done(function (r) {
            var d = (r && r.data) || {};
            if (r && r.success) {
                var msg = action === 'restore'
                    ? fmt(d.restored) + ' filas restauradas' + (d.failed ? '; ' + fmt(d.failed) + ' no se pudieron restaurar (siguen en el respaldo)' : '') + '.'
                    : 'Respaldo eliminado.';
                status(msg, 'success');
                setTimeout(function () { window.location.reload(); }, 1500);
            } else {
                status(d.message || S.error, 'error');
            }
        }).fail(function (xhr) { status(failMessage(xhr), 'error'); });
    }

    $(function () {
        if (!$('#ss-dedup-form').length) return;
        renderTable();

        $('#ss-dedup-table').on('change', renderTable);
        $('#ss-dedup-presets').on('change', 'input[type=radio]', function () {
            if (this.value === 'custom') {
                $('#ss-dedup-custom').prop('open', true);
                resetResult();
            } else {
                applyPreset(this.value);
            }
        });
        $('#ss-dedup-columns').on('change', 'input', function () {
            $('#ss-dedup-preset-custom').prop('checked', true);
            resetResult();
        });
        $('#ss-dedup-keep, #ss-dedup-skip-empty').on('change', resetResult);
        $('#ss-dedup-analyze').on('click', analyze);
        $('#ss-dedup-confirm').on('change', function () {
            $('#ss-dedup-run').prop('disabled', !this.checked || !lastAnalysis || !lastAnalysis.redundant);
        });
        $('#ss-dedup-run').on('click', run);
        $(document).on('click', '.ss-dedup-restore', function () {
            batchAction('restore', $(this).data('batch'), S.confirmRestore);
        });
        $(document).on('click', '.ss-dedup-purge', function () {
            batchAction('purge', $(this).data('batch'), S.confirmPurge);
        });
        $('#ss-dedup-unique').on('click', function () {
            var $b = $(this).prop('disabled', true);
            post('unique_index').done(function (r) {
                var d = (r && r.data) || {};
                status(d.message || S.error, r && r.success ? 'success' : 'error');
                if (r && r.success) setTimeout(function () { window.location.reload(); }, 1500);
                else $b.prop('disabled', false);
            }).fail(function (xhr) { status(failMessage(xhr), 'error'); $b.prop('disabled', false); });
        });
    });
})(jQuery);
