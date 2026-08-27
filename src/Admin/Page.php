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
                            <h3><?php esc_html_e( 'Disk Usage', 'ago-monitor' ); ?></h3>
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
                                    <span class="ago-toggle-desc"><?php esc_html_e( 'Log URLs that trigger 404 errors', 'ago-monitor' ); ?></span>
                                </span>
                                <span class="ago-switch">
                                    <input type="checkbox" data-setting="enable_404_logging">
                                    <span class="ago-slider"></span>
                                </span>
                            </label>

                            <label class="ago-toggle-row">
                                <span class="ago-toggle-label">
                                    <strong><?php esc_html_e( 'Weekly Report', 'ago-monitor' ); ?></strong>
                                    <span class="ago-toggle-desc"><?php esc_html_e( 'Send a health report email every week', 'ago-monitor' ); ?></span>
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
                        <h3><?php esc_html_e( 'About', 'ago-monitor' ); ?></h3>
                        <p style="font-size:13px;color:#666">
                            <?php esc_html_e( 'Site health monitoring dashboard. Check SSL, disk, database, cron jobs, autoloaded options, and track 404 errors.', 'ago-monitor' ); ?>
                        </p>
                        <ul class="ago-features">
                            <li><?php esc_html_e( 'SSL certificate expiry check', 'ago-monitor' ); ?></li>
                            <li><?php esc_html_e( 'Database & disk usage', 'ago-monitor' ); ?></li>
                            <li><?php esc_html_e( 'Cron job inspector', 'ago-monitor' ); ?></li>
                            <li><?php esc_html_e( 'Autoloaded options monitor', 'ago-monitor' ); ?></li>
                            <li><?php esc_html_e( '404 error logger', 'ago-monitor' ); ?></li>
                            <li><?php esc_html_e( 'Weekly email health report', 'ago-monitor' ); ?></li>
                        </ul>
                        <a href="https://ago.cl/herramientas/wordpress/ago-monitor/docs" class="button" target="_blank" rel="noopener" style="margin-top:8px">
                            <span class="dashicons dashicons-book" style="margin-right:4px;vertical-align:text-bottom"></span>
                            <?php esc_html_e( 'Documentation', 'ago-monitor' ); ?>
                        </a>
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
