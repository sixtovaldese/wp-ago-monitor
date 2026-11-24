(function () {
    'use strict';

    if (typeof agomonitorData === 'undefined') return;

    var REST = agomonitorData.restUrl;
    var NONCE = agomonitorData.nonce;
    var i18n = agomonitorData.i18n;
    var settings = agomonitorData.settings;
    var current404Page = 1;

    function api(endpoint, opts) {
        opts = opts || {};
        var method = opts.method || 'GET';
        var fetchOpts = {
            method: method,
            headers: {
                'X-WP-Nonce': NONCE,
                'Content-Type': 'application/json',
            },
        };
        if (opts.body) {
            fetchOpts.body = JSON.stringify(opts.body);
        }
        return fetch(REST + endpoint, fetchOpts).then(function (r) { return r.json(); });
    }

    function $(id) { return document.getElementById(id); }

    function fmt(template, value) {
        return String(template).replace(/%[ds]/, String(value));
    }

    function statusLabel(status) {
        if (status === 'ok') return i18n.ok;
        if (status === 'warning') return i18n.warning;
        return i18n.errorStatus;
    }

    function statusBadge(status, text) {
        return '<span class="ago-status-badge ago-status-' + status + '">' + esc(text) + '</span>';
    }

    function esc(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str || ''));
        return div.innerHTML;
    }

    function showStatus(msg, type) {
        var el = $('ago-monitor-status');
        if (!el) return;
        el.style.display = 'block';
        el.className = type || 'success';
        el.textContent = msg;
        setTimeout(function () { el.style.display = 'none'; }, 4000);
    }

    function loadChecks() {
        api('/checks').then(function (data) {
            renderSystem(data.system);
            renderSsl(data.ssl);
            renderDatabase(data.database);
            renderDisk(data.disk);
            renderAutoload(data.autoload);
            renderUpdates(data.updates);
            renderCronSummary(data.cron);
        }).catch(function () {
            document.querySelectorAll('.ago-loading').forEach(function (el) {
                el.textContent = i18n.error;
                el.classList.remove('ago-loading');
            });
        });
    }

    function renderSystem(sys) {
        var el = $('card-system');
        if (!el) return;
        el.querySelector('.ago-card-body').innerHTML =
            '<div><strong>' + esc(i18n.php) + '</strong> ' + esc(sys.php_version) + '</div>' +
            '<div><strong>' + esc(i18n.wordpress) + '</strong> ' + esc(sys.wp_version) + '</div>' +
            '<div><strong>' + esc(i18n.database) + '</strong> ' + esc(sys.db_version) + '</div>';
        el.querySelector('.ago-card-body').classList.remove('ago-loading');
    }

    function renderSsl(ssl) {
        var el = $('card-ssl');
        if (!el) return;
        el.querySelector('.ago-card-body').innerHTML =
            statusBadge(ssl.status, ssl.value);
        el.querySelector('.ago-card-body').classList.remove('ago-loading');
    }

    function renderDatabase(db) {
        var el = $('card-database');
        if (!el) return;
        el.querySelector('.ago-card-body').innerHTML =
            '<div class="ago-big-value">' + esc(db.size) + '</div>' +
            '<div class="ago-sub-value">' + esc(fmt(i18n.tables, parseInt(db.table_count, 10) || 0)) + '</div>';
        el.querySelector('.ago-card-body').classList.remove('ago-loading');
    }

    function renderDisk(disk) {
        var el = $('card-disk');
        if (!el) return;
        el.querySelector('.ago-card-body').innerHTML =
            '<div><strong>' + esc(i18n.uploads) + '</strong> ' + esc(disk.uploads_size) + '</div>' +
            '<div><strong>' + esc(i18n.wpFolder) + '</strong> ' + esc(disk.total_size) + '</div>' +
            '<div class="ago-sub-value">' + esc(i18n.sizesCached) + '</div>';
        el.querySelector('.ago-card-body').classList.remove('ago-loading');
    }

    function renderAutoload(al) {
        var el = $('card-autoload');
        if (!el) return;
        var pct = Math.min(100, Math.round((al.size_kb / 1500) * 100));
        var barClass = al.status === 'ok' ? 'ago-progress-ok' : (al.status === 'warning' ? 'ago-progress-warning' : 'ago-progress-error');
        el.querySelector('.ago-card-body').innerHTML =
            '<div class="ago-big-value">' + esc(al.size_kb + ' KB') + '</div>' +
            '<div class="ago-progress"><div class="ago-progress-bar ' + barClass + '" style="width:' + pct + '%"></div></div>' +
            statusBadge(al.status, statusLabel(al.status));
        el.querySelector('.ago-card-body').classList.remove('ago-loading');
    }

    function renderUpdates(updates) {
        var el = $('card-updates');
        if (!el) return;
        var body = el.querySelector('.ago-card-body');
        body.classList.remove('ago-loading');

        if (!updates || updates.length === 0) {
            body.innerHTML = statusBadge('ok', i18n.upToDate) +
                '<div class="ago-sub-value">' + esc(i18n.lastChecked) + '</div>';
            return;
        }

        var html = '<div class="ago-big-value">' + updates.length + '</div>' +
            '<div class="ago-sub-value" style="margin-bottom:8px">' + esc(i18n.needUpdate) + '</div>' +
            '<ul class="ago-updates-list">';
        updates.forEach(function (p) {
            html += '<li>' + esc(p.name) + ' <span class="ago-version-arrow">' +
                esc(p.current) + ' &rarr; ' + esc(p.new_version) + '</span></li>';
        });
        html += '</ul><div class="ago-sub-value">' + esc(i18n.lastChecked) + '</div>';
        body.innerHTML = html;
    }

    function renderCronSummary(cron) {
        var el = $('card-cron');
        if (!el) return;
        var h3 = el.querySelector('h3');
        var badge = cron.overdue > 0
            ? ' ' + statusBadge('warning', fmt(i18n.overdueCount, cron.overdue))
            : ' ' + statusBadge('ok', fmt(i18n.eventCount, cron.total));

        var caret = h3.querySelector('.ago-caret');
        h3.innerHTML = esc(i18n.cronJobs) + badge + ' ';
        if (caret) h3.appendChild(caret);
    }

    function loadCronTable() {
        var container = $('cron-table');
        if (!container) return;

        api('/cron').then(function (events) {
            if (!events || events.length === 0) {
                container.innerHTML = '<p>' + esc(i18n.noCron) + '</p>';
                return;
            }
            var html = '<table class="ago-table"><thead><tr>' +
                '<th>' + esc(i18n.hook) + '</th><th>' + esc(i18n.schedule) + '</th><th>' + esc(i18n.nextRun) + '</th><th>' + esc(i18n.status) + '</th>' +
                '</tr></thead><tbody>';
            events.forEach(function (ev) {
                var late = ev.status === 'overdue';
                var cls = late ? ' class="ago-overdue"' : '';
                var when = ev.next_run * 1000 < Date.now() ? i18n.agoTime : i18n.inTime;
                html += '<tr' + cls + '>' +
                    '<td>' + esc(ev.hook) + '</td>' +
                    '<td>' + esc(ev.schedule) + '</td>' +
                    '<td>' + esc(fmt(when, ev.next_run_human)) + '</td>' +
                    '<td>' + statusBadge(late ? 'error' : 'ok', late ? i18n.overdue : i18n.onTime) + '</td>' +
                    '</tr>';
            });
            html += '</tbody></table>';
            container.innerHTML = html;
        });
    }

    function load404s(page) {
        current404Page = page || 1;
        var container = $('ago-404-table');
        var pagination = $('ago-404-pagination');
        if (!container) return;

        container.innerHTML = '<div class="ago-loading">' + esc(i18n.loading) + '</div>';

        api('/404s?page=' + current404Page + '&per_page=20').then(function (data) {
            container.classList.remove('ago-loading');
            if (!data.entries || data.entries.length === 0) {
                container.innerHTML = '<p>' + esc(i18n.noEntries) + '</p>';
                pagination.innerHTML = '';
                return;
            }

            var html = '<table class="ago-table"><thead><tr>' +
                '<th>' + esc(i18n.url) + '</th><th>' + esc(i18n.referrer) + '</th><th>' + esc(i18n.date) + '</th>' +
                '</tr></thead><tbody>';
            data.entries.forEach(function (entry) {
                html += '<tr>' +
                    '<td class="ago-url" title="' + esc(entry.url) + '">' + esc(entry.url) + '</td>' +
                    '<td class="ago-url" title="' + esc(entry.referrer) + '">' + esc(entry.referrer) + '</td>' +
                    '<td style="white-space:nowrap">' + esc(entry.created_at) + '</td>' +
                    '</tr>';
            });
            html += '</tbody></table>';
            container.innerHTML = html;

            if (data.pages > 1) {
                var pHtml = '';
                for (var p = 1; p <= data.pages; p++) {
                    if (p === current404Page) {
                        pHtml += '<span class="button current">' + p + '</span>';
                    } else {
                        pHtml += '<button class="button ago-404-page" data-page="' + p + '">' + p + '</button>';
                    }
                }
                pagination.innerHTML = pHtml;

                pagination.querySelectorAll('.ago-404-page').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        load404s(parseInt(this.getAttribute('data-page'), 10));
                    });
                });
            } else {
                pagination.innerHTML = '';
            }
        });
    }

    document.querySelectorAll('.ago-collapsible').forEach(function (header) {
        header.addEventListener('click', function () {
            var targetId = this.getAttribute('data-target');
            var target = $(targetId);
            var caret = this.querySelector('.ago-caret');
            if (!target) return;

            if (target.style.display === 'none') {
                target.style.display = 'block';
                if (caret) caret.classList.add('open');
                if (targetId === 'cron-table' && target.querySelector('.ago-loading')) {
                    loadCronTable();
                }
            } else {
                target.style.display = 'none';
                if (caret) caret.classList.remove('open');
            }
        });
    });

    function hydrateSettings() {
        document.querySelectorAll('[data-setting]').forEach(function (el) {
            var key = el.getAttribute('data-setting');
            if (el.type === 'checkbox') {
                el.checked = !!settings[key];
            } else {
                el.value = settings[key] || '';
            }
        });
    }

    function collectSettings() {
        var data = {};
        document.querySelectorAll('[data-setting]').forEach(function (el) {
            var key = el.getAttribute('data-setting');
            if (el.type === 'checkbox') {
                data[key] = el.checked;
            } else if (el.type === 'number') {
                data[key] = parseInt(el.value, 10) || 800;
            } else {
                data[key] = el.value;
            }
        });
        return data;
    }

    var saveBtn = $('ago-save-settings');
    if (saveBtn) {
        saveBtn.addEventListener('click', function () {
            api('/settings', { method: 'POST', body: collectSettings() }).then(function (resp) {
                if (resp.saved) {
                    showStatus(i18n.saved, 'success');
                    settings = resp.settings;
                } else {
                    showStatus(i18n.error, 'error');
                }
            }).catch(function () {
                showStatus(i18n.error, 'error');
            });
        });
    }

    var reportBtn = $('ago-send-report');
    if (reportBtn) {
        reportBtn.addEventListener('click', function () {
            reportBtn.disabled = true;
            reportBtn.textContent = i18n.sending;
            api('/report/send', { method: 'POST' }).then(function (resp) {
                showStatus(resp.sent ? i18n.sent : i18n.notSent, resp.sent ? 'success' : 'error');
                reportBtn.disabled = false;
                reportBtn.textContent = i18n.sendNow;
            }).catch(function () {
                showStatus(i18n.error, 'error');
                reportBtn.disabled = false;
                reportBtn.textContent = i18n.sendNow;
            });
        });
    }

    var clearBtn = $('ago-clear-404');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            if (!window.confirm(i18n.confirmClear)) return;
            api('/404s/clear', { method: 'POST' }).then(function (resp) {
                if (resp.cleared) {
                    showStatus(i18n.cleared, 'success');
                    load404s(1);
                }
            });
        });
    }

    hydrateSettings();
    loadChecks();
    load404s(1);

})();
