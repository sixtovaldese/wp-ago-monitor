/* aGo Monitor Admin JS */
(function () {
    'use strict';

    if (typeof agoMonitor === 'undefined') return;

    var REST = agoMonitor.restUrl;
    var NONCE = agoMonitor.nonce;
    var i18n = agoMonitor.i18n;
    var settings = agoMonitor.settings;
    var current404Page = 1;

    /* ───── Helpers ───── */

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

    /* ───── Load Checks ───── */

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
            '<div><strong>PHP:</strong> ' + esc(sys.php_version) + '</div>' +
            '<div><strong>WordPress:</strong> ' + esc(sys.wp_version) + '</div>' +
            '<div><strong>MySQL:</strong> ' + esc(sys.db_version) + '</div>';
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
            '<div class="ago-sub-value">' + db.table_count + ' tables</div>';
        el.querySelector('.ago-card-body').classList.remove('ago-loading');
    }

    function renderDisk(disk) {
        var el = $('card-disk');
        if (!el) return;
        el.querySelector('.ago-card-body').innerHTML =
            '<div><strong>Uploads:</strong> ' + esc(disk.uploads_size) + '</div>' +
            '<div><strong>Total WP:</strong> ' + esc(disk.total_size) + '</div>';
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
            statusBadge(al.status, al.status.toUpperCase());
        el.querySelector('.ago-card-body').classList.remove('ago-loading');
    }

    function renderUpdates(updates) {
        var el = $('card-updates');
        if (!el) return;
        var body = el.querySelector('.ago-card-body');
        body.classList.remove('ago-loading');

        if (!updates || updates.length === 0) {
            body.innerHTML = statusBadge('ok', 'All up to date');
            return;
        }

        var html = '<div class="ago-big-value">' + updates.length + '</div>' +
            '<div class="ago-sub-value" style="margin-bottom:8px">plugins need updating</div>' +
            '<ul class="ago-updates-list">';
        updates.forEach(function (p) {
            html += '<li>' + esc(p.name) + ' <span class="ago-version-arrow">' +
                esc(p.current) + ' &rarr; ' + esc(p.new_version) + '</span></li>';
        });
        html += '</ul>';
        body.innerHTML = html;
    }

    function renderCronSummary(cron) {
        var el = $('card-cron');
        if (!el) return;
        var h3 = el.querySelector('h3');
        var badge = cron.overdue > 0
            ? ' ' + statusBadge('warning', cron.overdue + ' overdue')
            : ' ' + statusBadge('ok', cron.total + ' events');

        // Preserve the caret
        var caret = h3.querySelector('.ago-caret');
        h3.innerHTML = 'Cron Jobs ' + badge + ' ';
        if (caret) h3.appendChild(caret);
    }

    /* ───── Load Cron Table ───── */

    function loadCronTable() {
        var container = $('cron-table');
        if (!container) return;

        api('/cron').then(function (events) {
            if (!events || events.length === 0) {
                container.innerHTML = '<p>No cron events found.</p>';
                return;
            }
            var html = '<table class="ago-table"><thead><tr>' +
                '<th>Hook</th><th>Schedule</th><th>Next Run</th><th>Status</th>' +
                '</tr></thead><tbody>';
            events.forEach(function (ev) {
                var cls = ev.status === 'overdue' ? ' class="ago-overdue"' : '';
                html += '<tr' + cls + '>' +
                    '<td>' + esc(ev.hook) + '</td>' +
                    '<td>' + esc(ev.schedule) + '</td>' +
                    '<td>' + esc(ev.next_run_human) + (ev.status === 'overdue' ? ' ago' : '') + '</td>' +
                    '<td>' + statusBadge(ev.status === 'overdue' ? 'error' : 'ok', ev.status) + '</td>' +
                    '</tr>';
            });
            html += '</tbody></table>';
            container.innerHTML = html;
        });
    }

    /* ───── Load 404 Log ───── */

    function load404s(page) {
        current404Page = page || 1;
        var container = $('ago-404-table');
        var pagination = $('ago-404-pagination');
        if (!container) return;

        container.innerHTML = '<div class="ago-loading">' + esc(i18n.loading) + '</div>';

        api('/404s?page=' + current404Page + '&per_page=20').then(function (data) {
            if (!data.entries || data.entries.length === 0) {
                container.innerHTML = '<p>' + esc(i18n.noEntries) + '</p>';
                pagination.innerHTML = '';
                return;
            }

            var html = '<table class="ago-table"><thead><tr>' +
                '<th>URL</th><th>Referrer</th><th>Date</th>' +
                '</tr></thead><tbody>';
            data.entries.forEach(function (entry) {
                html += '<tr>' +
                    '<td class="ago-url" title="' + esc(entry.url) + '">' + esc(entry.url) + '</td>' +
                    '<td class="ago-url" title="' + esc(entry.referrer) + '">' + esc(entry.referrer || ',') + '</td>' +
                    '<td style="white-space:nowrap">' + esc(entry.created_at) + '</td>' +
                    '</tr>';
            });
            html += '</tbody></table>';
            container.innerHTML = html;

            // Pagination.
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

    /* ───── Collapsible ───── */

    document.querySelectorAll('.ago-collapsible').forEach(function (header) {
        header.addEventListener('click', function () {
            var targetId = this.getAttribute('data-target');
            var target = $(targetId);
            var caret = this.querySelector('.ago-caret');
            if (!target) return;

            if (target.style.display === 'none') {
                target.style.display = 'block';
                if (caret) caret.classList.add('open');
                // Load cron table on first open.
                if (targetId === 'cron-table' && target.querySelector('.ago-loading')) {
                    loadCronTable();
                }
            } else {
                target.style.display = 'none';
                if (caret) caret.classList.remove('open');
            }
        });
    });

    /* ───── Settings ───── */

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

    /* ───── Send Report ───── */

    var reportBtn = $('ago-send-report');
    if (reportBtn) {
        reportBtn.addEventListener('click', function () {
            reportBtn.disabled = true;
            reportBtn.textContent = i18n.sending;
            api('/report/send', { method: 'POST' }).then(function (resp) {
                showStatus(resp.sent ? i18n.sent : i18n.error, resp.sent ? 'success' : 'error');
                reportBtn.disabled = false;
                reportBtn.textContent = 'Send Report Now';
            }).catch(function () {
                showStatus(i18n.error, 'error');
                reportBtn.disabled = false;
                reportBtn.textContent = 'Send Report Now';
            });
        });
    }

    /* ───── Clear 404 ───── */

    var clearBtn = $('ago-clear-404');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            if (!confirm('Clear all 404 log entries?')) return;
            api('/404s/clear', { method: 'POST' }).then(function (resp) {
                if (resp.cleared) {
                    showStatus(i18n.cleared, 'success');
                    load404s(1);
                }
            });
        });
    }

    /* ───── Init ───── */

    hydrateSettings();
    loadChecks();
    load404s(1);

})();
