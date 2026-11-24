<?php

namespace AgoLab\Monitor\Admin;

defined( 'ABSPATH' ) || exit;

class Page {

    public static function render(): void {
        ?>
        <div class="wrap">
            <h1>
                <img src="<?php echo esc_url( AGOMONITOR_URL . 'assets/img/agolab.webp' ); ?>" alt="aGo Lab" style="height:28px;width:auto;vertical-align:middle;margin-right:8px">
                <?php esc_html_e( 'aGo Monitor', 'ago-monitor' ); ?>
                <span style="font-size:12px;color:#999;margin-left:8px">v<?php echo esc_html( AGOMONITOR_VERSION ); ?></span>
            </h1>

            <p class="description">
                <?php esc_html_e( 'Everything on this screen is measured by this site, on its own server, each time the screen loads. It cannot tell you when the site is down: for that, use an uptime service that checks it from outside.', 'ago-monitor' ); ?>
            </p>

            <div id="ago-monitor-status" style="display:none"></div>

            <div class="ago-layout">
                <div class="ago-main">

                    <div class="ago-grid">
                        <div class="card ago-card ago-check-card" id="card-system">
                            <h3><?php esc_html_e( 'System Info', 'ago-monitor' ); ?></h3>
                            <div class="ago-card-body ago-loading"><?php esc_html_e( 'Loading...', 'ago-monitor' ); ?></div>
                        </div>

                        <div class="card ago-card ago-check-card" id="card-ssl">
                            <h3><?php esc_html_e( 'SSL Certificate', 'ago-monitor' ); ?></h3>
                            <div class="ago-card-body ago-loading"><?php esc_html_e( 'Loading...', 'ago-monitor' ); ?></div>
                        </div>

                        <div class="card ago-card ago-check-card" id="card-database">
                            <h3><?php esc_html_e( 'Database', 'ago-monitor' ); ?></h3>
                            <div class="ago-card-body ago-loading"><?php esc_html_e( 'Loading...', 'ago-monitor' ); ?></div>
                        </div>

                        <div class="card ago-card ago-check-card" id="card-disk">
                            <h3><?php esc_html_e( 'Folder Sizes', 'ago-monitor' ); ?></h3>
                            <div class="ago-card-body ago-loading"><?php esc_html_e( 'Loading...', 'ago-monitor' ); ?></div>
                        </div>

                        <div class="card ago-card ago-check-card" id="card-autoload">
                            <h3><?php esc_html_e( 'Autoloaded Options', 'ago-monitor' ); ?></h3>
                            <div class="ago-card-body ago-loading"><?php esc_html_e( 'Loading...', 'ago-monitor' ); ?></div>
                        </div>

                        <div class="card ago-card ago-check-card" id="card-updates">
                            <h3><?php esc_html_e( 'Pending Updates', 'ago-monitor' ); ?></h3>
                            <div class="ago-card-body ago-loading"><?php esc_html_e( 'Loading...', 'ago-monitor' ); ?></div>
                        </div>
                    </div>

                    <div class="card ago-card" id="card-cron">
                        <h3 class="ago-collapsible" data-target="cron-table">
                            <?php esc_html_e( 'Cron Jobs', 'ago-monitor' ); ?>
                            <span class="ago-caret dashicons dashicons-arrow-down-alt2"></span>
                        </h3>
                        <div id="cron-table" class="ago-card-body ago-collapsible-body" style="display:none">
                            <div class="ago-loading"><?php esc_html_e( 'Loading...', 'ago-monitor' ); ?></div>
                        </div>
                    </div>

                    <div class="card ago-card" id="card-404">
                        <h3>
                            <?php esc_html_e( '404 Log', 'ago-monitor' ); ?>
                            <button id="ago-clear-404" class="button button-small" style="margin-left:12px" type="button">
                                <?php esc_html_e( 'Clear Log', 'ago-monitor' ); ?>
                            </button>
                        </h3>
                        <div class="ago-card-body">
                            <div id="ago-404-table" class="ago-loading"><?php esc_html_e( 'Loading...', 'ago-monitor' ); ?></div>
                            <div id="ago-404-pagination" class="ago-pagination"></div>
                        </div>
                    </div>

                    <div class="card ago-card" id="card-settings">
                        <h3><?php esc_html_e( 'Settings', 'ago-monitor' ); ?></h3>
                        <div class="ago-card-body">

                            <label class="ago-toggle-row">
                                <span class="ago-toggle-label">
                                    <strong><?php esc_html_e( '404 Logging', 'ago-monitor' ); ?></strong>
                                    <span class="ago-toggle-desc"><?php esc_html_e( 'Keep the address, referrer and time of each 404 for 30 days. No IP address or browser data is stored.', 'ago-monitor' ); ?></span>
                                </span>
                                <span class="ago-switch">
                                    <input type="checkbox" data-setting="enable_404_logging">
                                    <span class="ago-slider"></span>
                                </span>
                            </label>

                            <label class="ago-toggle-row">
                                <span class="ago-toggle-label">
                                    <strong><?php esc_html_e( 'Weekly Report', 'ago-monitor' ); ?></strong>
                                    <span class="ago-toggle-desc"><?php esc_html_e( 'Email a health summary once a week. It is sent by WP-Cron, so it needs the site to be up and receiving visits or a server cron.', 'ago-monitor' ); ?></span>
                                </span>
                                <span class="ago-switch">
                                    <input type="checkbox" data-setting="enable_weekly_report">
                                    <span class="ago-slider"></span>
                                </span>
                            </label>

                            <div class="ago-field-row">
                                <label for="ago-report-email">
                                    <strong><?php esc_html_e( 'Report Email', 'ago-monitor' ); ?></strong>
                                    <span class="ago-toggle-desc"><?php esc_html_e( 'Leave empty to use the admin email', 'ago-monitor' ); ?></span>
                                </label>
                                <input type="email" id="ago-report-email" data-setting="report_email" class="regular-text" placeholder="<?php esc_attr_e( 'admin email', 'ago-monitor' ); ?>">
                            </div>

                            <div class="ago-field-row">
                                <label for="ago-autoload-warn">
                                    <strong><?php esc_html_e( 'Autoload Warning (KB)', 'ago-monitor' ); ?></strong>
                                    <span class="ago-toggle-desc"><?php esc_html_e( 'Warn when autoloaded options exceed this size', 'ago-monitor' ); ?></span>
                                </label>
                                <input type="number" id="ago-autoload-warn" data-setting="autoload_warn_kb" class="small-text" min="100" step="50">
                            </div>

                            <div class="ago-actions">
                                <button id="ago-save-settings" class="button button-primary" type="button">
                                    <?php esc_html_e( 'Save Settings', 'ago-monitor' ); ?>
                                </button>
                                <button id="ago-send-report" class="button" type="button">
                                    <?php esc_html_e( 'Send Report Now', 'ago-monitor' ); ?>
                                </button>
                            </div>

                        </div>
                    </div>

                </div>

                <div class="ago-sidebar">

                    <div class="card ago-card">
                        <h3><?php esc_html_e( 'Quick links', 'ago-monitor' ); ?></h3>
                        <ul class="ago-features ago-links">
                            <li><a href="https://ago.cl/herramientas/wordpress/ago-monitor/docs/" target="_blank" rel="noopener"><?php esc_html_e( 'Documentation', 'ago-monitor' ); ?></a></li>
                            <li><a href="<?php echo esc_url( admin_url( 'site-health.php' ) ); ?>"><?php esc_html_e( 'WordPress Site Health', 'ago-monitor' ); ?></a></li>
                            <li><a href="https://www.ssllabs.com/ssltest/" target="_blank" rel="noopener"><?php esc_html_e( 'SSL test (SSL Labs)', 'ago-monitor' ); ?></a></li>
                            <li><a href="https://developer.wordpress.org/plugins/cron/hooking-wp-cron-into-the-system-task-scheduler/" target="_blank" rel="noopener"><?php esc_html_e( 'Run WP-Cron from a server cron', 'ago-monitor' ); ?></a></li>
                        </ul>
                    </div>

                    <div class="card ago-card">
                        <h3><?php esc_html_e( 'About', 'ago-monitor' ); ?></h3>
                        <p style="font-size:13px;color:#666">
                            <?php esc_html_e( 'A health dashboard measured from inside your site, plus an optional weekly email with the same figures. No account and no external service.', 'ago-monitor' ); ?>
                        </p>
                        <ul class="ago-features">
                            <li><?php esc_html_e( 'Days left on the SSL certificate', 'ago-monitor' ); ?></li>
                            <li><?php esc_html_e( 'Overdue cron events', 'ago-monitor' ); ?></li>
                            <li><?php esc_html_e( 'Autoloaded options weight', 'ago-monitor' ); ?></li>
                            <li><?php esc_html_e( 'Database and folder sizes', 'ago-monitor' ); ?></li>
                            <li><?php esc_html_e( '404 log without visitor data', 'ago-monitor' ); ?></li>
                            <li><?php esc_html_e( 'Weekly email, opt-in', 'ago-monitor' ); ?></li>
                            <li><?php esc_html_e( 'Completely free, no Pro tier.', 'ago-monitor' ); ?></li>
                        </ul>
                    </div>

                    <div class="card ago-card ago-donation">
                        <h3><?php esc_html_e( 'Support Open Source', 'ago-monitor' ); ?></h3>
                        <p style="font-size:13px;color:#666">
                            <?php esc_html_e( 'If this plugin saves you time, consider supporting our open-source work.', 'ago-monitor' ); ?>
                        </p>
                        <div class="ago-donation-amounts">
                            <a href="https://paypal.me/sixtovaldes/3" class="ago-amount" target="_blank" rel="noopener">$3</a>
                            <a href="https://paypal.me/sixtovaldes/5" class="ago-amount" target="_blank" rel="noopener">$5</a>
                            <a href="https://paypal.me/sixtovaldes/10" class="ago-amount" target="_blank" rel="noopener">$10</a>
                        </div>
                        <a href="https://paypal.me/sixtovaldes" class="ago-coffee-btn" target="_blank" rel="noopener">
                            <span class="dashicons dashicons-coffee" style="margin-right:6px"></span>
                            <?php esc_html_e( 'Buy us a coffee', 'ago-monitor' ); ?>
                        </a>
                        <p class="ago-donation-note">
                            <?php esc_html_e( 'Voluntary donation. Thank you!', 'ago-monitor' ); ?>
                        </p>
                    </div>

                    <div class="ago-footer">
                        <a href="https://ago.cl" target="_blank" rel="noopener" class="ago-footer-logo">
                            <img src="<?php echo esc_url( AGOMONITOR_URL . 'assets/img/agolab.webp' ); ?>" alt="aGo Lab" style="height:40px;width:auto">
                        </a>
                        <p>
                            <?php
                            echo wp_kses_post(
                                sprintf(
                                    /* translators: 1: heart icon HTML, 2: aGo Lab link HTML */
                                    __( 'Developed with %1$s by %2$s', 'ago-monitor' ),
                                    '<span style="color:#e25555">&#10084;</span>',
                                    '<a href="https://ago.cl" target="_blank" rel="noopener"><strong>aGo Lab</strong></a>'
                                )
                            );
                            ?>
                        </p>
                        <p style="font-size:11px;color:#999">
                            <?php esc_html_e( 'Building tools for the web, one plugin at a time.', 'ago-monitor' ); ?>
                        </p>
                    </div>

                </div>
            </div>

        </div>
        <?php
    }
}
