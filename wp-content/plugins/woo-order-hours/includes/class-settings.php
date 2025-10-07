<?php
/**
 * Settings manager.
 *
 * @package WooOrderHours
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WOH_Settings {
    private const OPTION_KEY = 'woo_order_hours_settings';

    private static bool $bootstrapped = false;

    /**
     * Initialize hooks.
     */
    public static function init(): void {
        if ( self::$bootstrapped ) {
            return;
        }

        self::$bootstrapped = true;

        register_setting(
            self::OPTION_KEY,
            self::OPTION_KEY,
            [
                'type'              => 'array',
                'sanitize_callback' => [ self::class, 'sanitize_settings' ],
                'default'           => woh_get_default_settings(),
            ]
        );

        add_filter( 'woocommerce_settings_tabs_array', [ self::class, 'register_tab' ], 60 );
        add_action( 'woocommerce_settings_tabs_woo_order_hours', [ self::class, 'render_page' ] );
        add_action( 'woocommerce_settings_save_woo_order_hours', [ self::class, 'handle_save' ] );
        add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
    }

    /**
     * Register tab label.
     *
     * @param array $tabs Tabs.
     * @return array
     */
    public static function register_tab( array $tabs ): array {
        $tabs['woo_order_hours'] = __( 'Order Hours', 'woo-order-hours' );

        return $tabs;
    }

    /**
     * Render settings page.
     */
    public static function render_page(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to manage these settings.', 'woo-order-hours' ) );
        }

        settings_errors( self::OPTION_KEY );

        $settings       = self::get_settings();
        $weekday_labels = woh_get_weekday_labels();

        settings_fields( self::OPTION_KEY );
        ?>
        <h2><?php esc_html_e( 'Order Hours', 'woo-order-hours' ); ?></h2>
        <p><?php esc_html_e( 'Configure when your store accepts orders. Use multiple intervals per day and optional overrides.', 'woo-order-hours' ); ?></p>
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e( 'Weekly schedule', 'woo-order-hours' ); ?></label>
                    </th>
                    <td>
                        <p class="description"><?php esc_html_e( 'Add one or more time ranges per day in 24-hour format. Overnight intervals (e.g. 18:00-02:00) are supported.', 'woo-order-hours' ); ?></p>
                        <div class="woh-weekly" data-weekly="<?php echo esc_attr( wp_json_encode( $settings['weekly'] ) ); ?>">
                            <?php foreach ( $weekday_labels as $day_key => $label ) : ?>
                                <div class="woh-day" data-day="<?php echo esc_attr( $day_key ); ?>">
                                    <h4><?php echo esc_html( $label ); ?></h4>
                                    <div class="woh-interval-list"></div>
                                    <button type="button" class="button woh-add-interval"><?php esc_html_e( 'Add interval', 'woo-order-hours' ); ?></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[weekly]" value="<?php echo esc_attr( wp_json_encode( $settings['weekly'] ) ); ?>" class="woh-weekly-input" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="woh-holidays-input"><?php esc_html_e( 'Holidays', 'woo-order-hours' ); ?></label>
                    </th>
                    <td>
                        <p class="description"><?php esc_html_e( 'Store closed on these dates (YYYY-MM-DD).', 'woo-order-hours' ); ?></p>
                        <div class="woh-holidays" data-holidays="<?php echo esc_attr( wp_json_encode( $settings['holidays'] ) ); ?>">
                            <div class="woh-holiday-list"></div>
                            <button type="button" class="button woh-add-holiday"><?php esc_html_e( 'Add holiday', 'woo-order-hours' ); ?></button>
                        </div>
                        <input type="hidden" id="woh-holidays-input" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[holidays]" value="<?php echo esc_attr( wp_json_encode( $settings['holidays'] ) ); ?>" class="woh-holidays-input" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e( 'Special hours', 'woo-order-hours' ); ?></label>
                    </th>
                    <td>
                        <p class="description"><?php esc_html_e( 'Override a single date with custom intervals.', 'woo-order-hours' ); ?></p>
                        <div class="woh-special" data-special="<?php echo esc_attr( wp_json_encode( $settings['special_hours'] ) ); ?>">
                            <div class="woh-special-list"></div>
                            <button type="button" class="button woh-add-special"><?php esc_html_e( 'Add special hours', 'woo-order-hours' ); ?></button>
                        </div>
                        <input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[special_hours]" value="<?php echo esc_attr( wp_json_encode( $settings['special_hours'] ) ); ?>" class="woh-special-input" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="woh-closed-notice"><?php esc_html_e( 'Closed notice', 'woo-order-hours' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="woh-closed-notice" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[messages][closed_notice]" value="<?php echo esc_attr( $settings['messages']['closed_notice'] ); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="woh-reopen-prefix"><?php esc_html_e( 'Reopen prefix', 'woo-order-hours' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="woh-reopen-prefix" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[messages][reopen_prefix]" value="<?php echo esc_attr( $settings['messages']['reopen_prefix'] ); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Behavior', 'woo-order-hours' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" value="1" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[toggles][allow_add_to_cart_outside_hours]" <?php checked( $settings['toggles']['allow_add_to_cart_outside_hours'] ); ?> />
                            <?php esc_html_e( 'Allow add to cart outside hours', 'woo-order-hours' ); ?>
                        </label>
                        <br />
                        <label>
                            <input type="checkbox" value="1" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[toggles][block_checkout_server_side]" <?php checked( $settings['toggles']['block_checkout_server_side'] ); ?> />
                            <?php esc_html_e( 'Block checkout server-side when closed', 'woo-order-hours' ); ?>
                        </label>
                        <br />
                        <label>
                            <input type="checkbox" value="1" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[toggles][show_global_banner_when_closed]" <?php checked( $settings['toggles']['show_global_banner_when_closed'] ); ?> />
                            <?php esc_html_e( 'Show global closed banner', 'woo-order-hours' ); ?>
                        </label>
                        <br />
                        <label>
                            <input type="checkbox" value="1" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[toggles][show_countdown_to_next_open]" <?php checked( $settings['toggles']['show_countdown_to_next_open'] ); ?> />
                            <?php esc_html_e( 'Show countdown to next opening', 'woo-order-hours' ); ?>
                        </label>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
        submit_button();
    }

    /**
     * Enqueue scripts/styles.
     */
    public static function enqueue_assets( string $hook ): void {
        if ( 'woocommerce_page_wc-settings' !== $hook ) {
            return;
        }

        $current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 'woo_order_hours' !== $current_tab ) {
            return;
        }

        wp_enqueue_style( 'woh-admin', WOH_PLUGIN_URL . 'assets/admin.css', [], WOH_VERSION );
        wp_enqueue_script( 'woh-admin', WOH_PLUGIN_URL . 'assets/admin.js', [ 'jquery', 'wp-util' ], WOH_VERSION, true );

        wp_localize_script(
            'woh-admin',
            'wohAdmin',
            [
                'weekdays' => woh_get_weekday_labels(),
                'i18n'     => [
                    'start'          => __( 'Start time', 'woo-order-hours' ),
                    'end'            => __( 'End time', 'woo-order-hours' ),
                    'remove'         => __( 'Remove', 'woo-order-hours' ),
                    'holiday_date'   => __( 'Holiday date', 'woo-order-hours' ),
                    'special_date'   => __( 'Date', 'woo-order-hours' ),
                    'add_interval'   => __( 'Add interval', 'woo-order-hours' ),
                    'invalid_time'   => __( 'Please use the HH:MM 24-hour format.', 'woo-order-hours' ),
                    'duplicate'      => __( 'Intervals must not overlap.', 'woo-order-hours' ),
                    'special_heading' => __( 'Intervals', 'woo-order-hours' ),
                ],
            ]
        );
    }

    /**
     * Handle saving of settings.
     */
    public static function handle_save(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        if ( ! check_admin_referer( self::OPTION_KEY . '-options', '_wpnonce', false ) ) {
            add_settings_error( self::OPTION_KEY, 'woh_nonce', __( 'Security check failed. Settings not saved.', 'woo-order-hours' ), 'error' );

            return;
        }

        $raw = isset( $_POST[ self::OPTION_KEY ] ) ? wp_unslash( $_POST[ self::OPTION_KEY ] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        $sanitized = self::sanitize_settings( is_array( $raw ) ? $raw : [] );
        update_option( self::OPTION_KEY, $sanitized, false );
        WOH_Scheduler::reset_instance();
        woh_touch_cache_buster();

        add_settings_error( self::OPTION_KEY, 'woh_saved', __( 'Settings saved.', 'woo-order-hours' ), 'updated' );
    }

    /**
     * Get normalized settings.
     *
     * @return array
     */
    public static function get_settings(): array {
        $raw = get_option( self::OPTION_KEY, [] );

        return wp_parse_args( is_array( $raw ) ? $raw : [], woh_get_default_settings() );
    }

    /**
     * Sanitize settings array.
     *
     * @param array $raw Raw input.
     * @return array
     */
    public static function sanitize_settings( array $raw ): array {
        $defaults = woh_get_default_settings();

        $settings = [
            'weekly'        => self::sanitize_weekly( $raw['weekly'] ?? $defaults['weekly'] ),
            'holidays'      => self::sanitize_dates( $raw['holidays'] ?? $defaults['holidays'] ),
            'special_hours' => self::sanitize_special_hours( $raw['special_hours'] ?? $defaults['special_hours'] ),
            'messages'      => [
                'closed_notice' => isset( $raw['messages']['closed_notice'] ) ? sanitize_text_field( $raw['messages']['closed_notice'] ) : $defaults['messages']['closed_notice'],
                'reopen_prefix' => isset( $raw['messages']['reopen_prefix'] ) ? sanitize_text_field( $raw['messages']['reopen_prefix'] ) : $defaults['messages']['reopen_prefix'],
            ],
            'toggles'       => [
                'allow_add_to_cart_outside_hours' => ! empty( $raw['toggles']['allow_add_to_cart_outside_hours'] ),
                'block_checkout_server_side'      => ! empty( $raw['toggles']['block_checkout_server_side'] ),
                'show_global_banner_when_closed'  => ! empty( $raw['toggles']['show_global_banner_when_closed'] ),
                'show_countdown_to_next_open'     => ! empty( $raw['toggles']['show_countdown_to_next_open'] ),
            ],
        ];

        return $settings;
    }

    /**
     * Sanitize weekly intervals.
     *
     * @param mixed $raw Raw weekly value.
     * @return array
     */
    private static function sanitize_weekly( $raw ): array {
        $weekdays = array_keys( woh_get_weekday_labels() );
        $weekly   = [];

        $aggregated = [];
        foreach ( $weekdays as $day ) {
            $aggregated[ $day ] = [];
        }

        $raw_weekly = [];
        if ( is_string( $raw ) ) {
            $decoded = json_decode( wp_unslash( $raw ), true );
            if ( is_array( $decoded ) ) {
                $raw_weekly = $decoded;
            }
        } elseif ( is_array( $raw ) ) {
            $raw_weekly = $raw;
        }

        foreach ( $weekdays as $day_key ) {
            $day_intervals = [];
            if ( isset( $raw_weekly[ $day_key ] ) && is_array( $raw_weekly[ $day_key ] ) ) {
                foreach ( $raw_weekly[ $day_key ] as $interval ) {
                    if ( ! is_array( $interval ) ) {
                        continue;
                    }

                    $start = isset( $interval['start'] ) ? sanitize_text_field( $interval['start'] ) : '';
                    $end   = isset( $interval['end'] ) ? sanitize_text_field( $interval['end'] ) : '';

                    if ( ! self::is_valid_time( $start ) || ! self::is_valid_time( $end ) ) {
                        continue;
                    }

                    $day_intervals[] = [
                        'start' => $start,
                        'end'   => $end,
                    ];
                }
            }

            $weekly[ $day_key ] = self::validate_interval_overlaps( $day_key, $day_intervals, $aggregated );
        }

        return $weekly;
    }

    /**
     * Ensure intervals do not overlap.
     *
     * @param string $day_key Day key.
     * @param array  $intervals Intervals.
     * @param array  $aggregated Aggregated segments.
     * @return array
     */
    private static function validate_interval_overlaps( string $day_key, array $intervals, array &$aggregated ): array {
        $valid = [];

        foreach ( $intervals as $interval ) {
            $start_seconds = woh_time_to_seconds( $interval['start'] );
            $end_seconds   = woh_time_to_seconds( $interval['end'] );

            $segments = self::split_interval( $day_key, $start_seconds, $end_seconds );
            $conflict = false;

            foreach ( $segments as $target_day => $day_segments ) {
                foreach ( $day_segments as $segment ) {
                    foreach ( $aggregated[ $target_day ] as $existing ) {
                        if ( self::segments_overlap( $existing, $segment ) ) {
                            $conflict = true;
                            break 3;
                        }
                    }
                }
            }

            if ( ! $conflict ) {
                $valid[] = $interval;
                foreach ( $segments as $target_day => $day_segments ) {
                    $aggregated[ $target_day ] = array_merge( $aggregated[ $target_day ], $day_segments );
                }
            }
        }

        return $valid;
    }

    /**
     * Check overlapping segments.
     *
     * @param array $a Segment a.
     * @param array $b Segment b.
     * @return bool
     */
    private static function segments_overlap( array $a, array $b ): bool {
        return $a['start'] < $b['end'] && $b['start'] < $a['end'];
    }

    /**
     * Split an interval into day segments.
     *
     * @param string $day_key Day.
     * @param int    $start Start seconds.
     * @param int    $end End seconds.
     * @return array<string, array<int, array{start:int,end:int}>>
     */
    private static function split_interval( string $day_key, int $start, int $end ): array {
        $segments = [];
        $days     = array_keys( woh_get_weekday_labels() );
        $index    = array_search( $day_key, $days, true );
        if ( false === $index ) {
            return [];
        }

        if ( $start < 0 || $start > 86399 ) {
            $start = max( 0, min( 86399, $start ) );
        }

        if ( $end < 0 || $end > 86399 ) {
            $end = max( 0, min( 86399, $end ) );
        }

        if ( $start === $end ) {
            return [ $day_key => [] ];
        }

        if ( $start < $end ) {
            $segments[ $day_key ][] = [
                'start' => $start,
                'end'   => $end,
            ];
        } else {
            $segments[ $day_key ][] = [
                'start' => $start,
                'end'   => 86400,
            ];

            $next_index = ( $index + 1 ) % count( $days );
            $next_day   = $days[ $next_index ];
            if ( $end > 0 ) {
                $segments[ $next_day ][] = [
                    'start' => 0,
                    'end'   => $end,
                ];
            }
        }

        return $segments;
    }

    /**
     * Validate a HH:MM string.
     */
    private static function is_valid_time( string $time ): bool {
        return (bool) preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time );
    }

    /**
     * Sanitize date list.
     *
     * @param mixed $raw Raw.
     * @return array
     */
    private static function sanitize_dates( $raw ): array {
        $dates = [];

        if ( is_string( $raw ) ) {
            $decoded = json_decode( wp_unslash( $raw ), true );
            if ( is_array( $decoded ) ) {
                $raw = $decoded;
            } else {
                $raw = [];
            }
        }

        if ( is_array( $raw ) ) {
            foreach ( $raw as $date ) {
                $date = sanitize_text_field( (string) $date );
                if ( self::is_valid_date( $date ) ) {
                    $dates[] = $date;
                }
            }
        }

        return array_values( array_unique( $dates ) );
    }

    /**
     * Validate date string.
     */
    private static function is_valid_date( string $date ): bool {
        if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches ) ) {
            return false;
        }

        return checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] );
    }

    /**
     * Sanitize special hours.
     *
     * @param mixed $raw Raw.
     * @return array
     */
    private static function sanitize_special_hours( $raw ): array {
        if ( is_string( $raw ) ) {
            $decoded = json_decode( wp_unslash( $raw ), true );
            $raw     = is_array( $decoded ) ? $decoded : [];
        }

        if ( ! is_array( $raw ) ) {
            return [];
        }

        $sanitized = [];

        foreach ( $raw as $entry ) {
            if ( ! is_array( $entry ) || empty( $entry['date'] ) ) {
                continue;
            }

            $date = sanitize_text_field( (string) $entry['date'] );
            if ( ! self::is_valid_date( $date ) ) {
                continue;
            }

            $intervals_raw = isset( $entry['intervals'] ) && is_array( $entry['intervals'] ) ? $entry['intervals'] : [];
            if ( empty( $intervals_raw ) ) {
                continue;
            }

            $valid_intervals  = [];
            $segments_current = [];
            $segments_next    = [];

            foreach ( $intervals_raw as $interval ) {
                if ( ! is_array( $interval ) ) {
                    continue;
                }

                $start = isset( $interval['start'] ) ? sanitize_text_field( $interval['start'] ) : '';
                $end   = isset( $interval['end'] ) ? sanitize_text_field( $interval['end'] ) : '';

                if ( ! self::is_valid_time( $start ) || ! self::is_valid_time( $end ) ) {
                    continue;
                }

                $start_seconds = woh_time_to_seconds( $start );
                $end_seconds   = woh_time_to_seconds( $end );

                if ( $start_seconds === $end_seconds ) {
                    continue;
                }

                if ( $start_seconds < $end_seconds ) {
                    if ( self::has_overlap( $segments_current, $start_seconds, $end_seconds ) ) {
                        continue;
                    }

                    $segments_current[] = [
                        'start' => $start_seconds,
                        'end'   => $end_seconds,
                    ];
                } else {
                    if ( self::has_overlap( $segments_current, $start_seconds, 86400 ) || self::has_overlap( $segments_next, 0, $end_seconds ) ) {
                        continue;
                    }

                    $segments_current[] = [
                        'start' => $start_seconds,
                        'end'   => 86400,
                    ];
                    if ( $end_seconds > 0 ) {
                        $segments_next[] = [
                            'start' => 0,
                            'end'   => $end_seconds,
                        ];
                    }
                }

                $valid_intervals[] = [
                    'start' => $start,
                    'end'   => $end,
                ];
            }

            if ( ! empty( $valid_intervals ) ) {
                $sanitized[ $date ] = [
                    'date'      => $date,
                    'intervals' => array_values( $valid_intervals ),
                ];
            }
        }

        return array_values( $sanitized );
    }

    /**
     * Detect overlap inside a segment stack.
     *
     * @param array $segments Segments.
     * @param int   $start    Start seconds.
     * @param int   $end      End seconds.
     * @return bool
     */
    private static function has_overlap( array $segments, int $start, int $end ): bool {
        foreach ( $segments as $segment ) {
            if ( self::segments_overlap( $segment, [
                'start' => $start,
                'end'   => $end,
            ] ) ) {
                return true;
            }
        }

        return false;
    }
}
