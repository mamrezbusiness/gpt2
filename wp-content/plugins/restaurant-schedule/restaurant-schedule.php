<?php
/**
 * Plugin Name:       Restaurant Schedule Manager
 * Description:       Manage restaurant opening hours and holidays with WooCommerce checkout validation.
 * Version:           1.0.0
 * Author:            OpenAI ChatGPT
 * Text Domain:       restaurant-schedule
 * Domain Path:       /languages
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Restaurant_Schedule_Manager' ) ) {

    class Restaurant_Schedule_Manager {

        const OPTION_KEY = 'restaurant_schedule_settings';

        /**
         * Constructor.
         */
        public function __construct() {
            add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
            add_action( 'admin_menu', array( $this, 'register_menu' ) );
            add_action( 'admin_init', array( $this, 'handle_form_submission' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
            add_action( 'wp_footer', array( $this, 'render_closed_notice' ) );

            if ( class_exists( 'WooCommerce' ) ) {
                add_action( 'woocommerce_before_checkout_process', array( $this, 'validate_checkout_open_status' ) );
                add_action( 'woocommerce_checkout_process', array( $this, 'validate_checkout_open_status' ) );
            }
        }

        /**
         * Load translations.
         */
        public function load_textdomain() {
            load_plugin_textdomain( 'restaurant-schedule', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
        }

        /**
         * Register admin menu.
         */
        public function register_menu() {
            add_menu_page(
                __( 'Restaurant Schedule', 'restaurant-schedule' ),
                __( 'Restaurant Schedule', 'restaurant-schedule' ),
                'manage_options',
                'restaurant-schedule',
                array( $this, 'render_settings_page' ),
                'dashicons-calendar-alt',
                56
            );
        }

        /**
         * Handle admin form submission.
         */
        public function handle_form_submission() {
            if ( ! isset( $_POST['restaurant_schedule_nonce'] ) ) {
                return;
            }

            if ( ! wp_verify_nonce( sanitize_key( $_POST['restaurant_schedule_nonce'] ), 'save_restaurant_schedule' ) ) {
                return;
            }

            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }

            $raw_schedule = isset( $_POST['schedule'] ) ? wp_unslash( $_POST['schedule'] ) : array();
            $sanitized    = $this->sanitize_schedule_data( $raw_schedule );
            update_option( self::OPTION_KEY, $sanitized );

            add_settings_error( 'restaurant_schedule_messages', 'restaurant_schedule_updated', __( 'Schedule saved.', 'restaurant-schedule' ), 'updated' );
        }

        /**
         * Sanitize schedule input data.
         *
         * @param array $data Raw schedule data.
         *
         * @return array
         */
        private function sanitize_schedule_data( $data ) {
            $days      = $this->get_week_days();
            $sanitized = array();

            foreach ( $days as $day_key => $label ) {
                $sanitized[ $day_key ] = array();
                if ( empty( $data[ $day_key ] ) || ! is_array( $data[ $day_key ] ) ) {
                    continue;
                }

                foreach ( $data[ $day_key ] as $range ) {
                    if ( empty( $range['start'] ) || empty( $range['end'] ) ) {
                        continue;
                    }

                    $start = $this->normalize_time( $range['start'] );
                    $end   = $this->normalize_time( $range['end'] );

                    if ( ! $start || ! $end ) {
                        continue;
                    }

                    $sanitized[ $day_key ][] = array(
                        'start' => $start,
                        'end'   => $end,
                    );
                }
            }

            $sanitized['holidays'] = array();

            if ( ! empty( $data['holidays'] ) && is_array( $data['holidays'] ) ) {
                foreach ( $data['holidays'] as $holiday ) {
                    $holiday = sanitize_text_field( $holiday );
                    if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $holiday ) ) {
                        $sanitized['holidays'][] = $holiday;
                    }
                }
                $sanitized['holidays'] = array_values( array_unique( $sanitized['holidays'] ) );
            }

            return $sanitized;
        }

        /**
         * Normalize time string to HH:MM format.
         *
         * @param string $value Time input.
         *
         * @return string|false
         */
        private function normalize_time( $value ) {
            $value = sanitize_text_field( $value );

            if ( preg_match( '/^(\d{1,2}):(\d{2})$/', $value, $matches ) ) {
                $hour   = max( 0, min( 23, (int) $matches[1] ) );
                $minute = max( 0, min( 59, (int) $matches[2] ) );

                return sprintf( '%02d:%02d', $hour, $minute );
            }

            return false;
        }

        /**
         * Get stored schedule.
         *
         * @return array
         */
        private function get_schedule() {
            $schedule = get_option( self::OPTION_KEY, array() );
            if ( empty( $schedule ) ) {
                $schedule = array();
            }

            foreach ( $this->get_week_days() as $day_key => $label ) {
                if ( ! isset( $schedule[ $day_key ] ) ) {
                    $schedule[ $day_key ] = array();
                }
            }

            if ( ! isset( $schedule['holidays'] ) || ! is_array( $schedule['holidays'] ) ) {
                $schedule['holidays'] = array();
            }

            return $schedule;
        }

        /**
         * Render settings page.
         */
        public function render_settings_page() {
            $schedule = $this->get_schedule();
            $days     = $this->get_week_days();

            settings_errors( 'restaurant_schedule_messages' );
            ?>
            <div class="wrap restaurant-schedule-wrap">
                <h1><?php esc_html_e( 'Restaurant Schedule', 'restaurant-schedule' ); ?></h1>
                <form method="post">
                    <?php wp_nonce_field( 'save_restaurant_schedule', 'restaurant_schedule_nonce' ); ?>
                    <h2><?php esc_html_e( 'Weekly Opening Hours', 'restaurant-schedule' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Add one or more time ranges for each day.', 'restaurant-schedule' ); ?></p>
                    <div class="restaurant-schedule-days">
                        <?php foreach ( $days as $day_key => $label ) : ?>
                            <?php
                            $ranges = ! empty( $schedule[ $day_key ] ) ? $schedule[ $day_key ] : array();
                            if ( empty( $ranges ) ) {
                                $ranges[] = array( 'start' => '', 'end' => '' );
                            }
                            $next_index = count( $ranges );
                            ?>
                            <div class="restaurant-schedule-day" data-day="<?php echo esc_attr( $day_key ); ?>" data-next-index="<?php echo esc_attr( $next_index ); ?>">
                                <h3><?php echo esc_html( $label ); ?></h3>
                                <div class="restaurant-schedule-ranges">
                                    <?php foreach ( $ranges as $index => $range ) :
                                        ?>
                                        <div class="restaurant-schedule-range">
                                            <label>
                                                <span class="screen-reader-text"><?php esc_html_e( 'Start time', 'restaurant-schedule' ); ?></span>
                                                <input type="time" class="restaurant-timepicker" name="schedule[<?php echo esc_attr( $day_key ); ?>][<?php echo esc_attr( $index ); ?>][start]" value="<?php echo esc_attr( $range['start'] ); ?>" />
                                            </label>
                                            <span class="dashicons dashicons-arrow-right-alt"></span>
                                            <label>
                                                <span class="screen-reader-text"><?php esc_html_e( 'End time', 'restaurant-schedule' ); ?></span>
                                                <input type="time" class="restaurant-timepicker" name="schedule[<?php echo esc_attr( $day_key ); ?>][<?php echo esc_attr( $index ); ?>][end]" value="<?php echo esc_attr( $range['end'] ); ?>" />
                                            </label>
                                            <button type="button" class="button button-link-delete restaurant-remove-range" aria-label="<?php esc_attr_e( 'Remove time range', 'restaurant-schedule' ); ?>">
                                                <?php esc_html_e( 'Remove', 'restaurant-schedule' ); ?>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <p>
                                    <button type="button" class="button restaurant-add-range" data-day="<?php echo esc_attr( $day_key ); ?>">
                                        <?php esc_html_e( 'Add time range', 'restaurant-schedule' ); ?>
                                    </button>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <h2><?php esc_html_e( 'Holidays', 'restaurant-schedule' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Add specific dates when the restaurant is closed.', 'restaurant-schedule' ); ?></p>
                    <div class="restaurant-holidays">
                        <div class="restaurant-holidays-list" data-next-index="<?php echo esc_attr( count( $schedule['holidays'] ) ); ?>">
                            <?php
                            if ( empty( $schedule['holidays'] ) ) {
                                $schedule['holidays'][] = '';
                            }
                            foreach ( $schedule['holidays'] as $index => $holiday ) :
                                ?>
                                <div class="restaurant-holiday">
                                    <input type="text" class="restaurant-datepicker" name="schedule[holidays][<?php echo esc_attr( $index ); ?>]" value="<?php echo esc_attr( $holiday ); ?>" placeholder="<?php esc_attr_e( 'YYYY-MM-DD', 'restaurant-schedule' ); ?>" />
                                    <button type="button" class="button button-link-delete restaurant-remove-holiday" aria-label="<?php esc_attr_e( 'Remove holiday', 'restaurant-schedule' ); ?>">
                                        <?php esc_html_e( 'Remove', 'restaurant-schedule' ); ?>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <p>
                            <button type="button" class="button restaurant-add-holiday">
                                <?php esc_html_e( 'Add holiday', 'restaurant-schedule' ); ?>
                            </button>
                        </p>
                    </div>

                    <?php submit_button( __( 'Save Schedule', 'restaurant-schedule' ) ); ?>
                </form>
            </div>
            <?php
        }

        /**
         * Enqueue admin assets.
         *
         * @param string $hook Current admin page.
         */
        public function enqueue_admin_assets( $hook ) {
            if ( 'toplevel_page_restaurant-schedule' !== $hook ) {
                return;
            }

            wp_enqueue_script( 'jquery-ui-datepicker' );
            wp_enqueue_script( 'jquery-ui-core' );

            wp_enqueue_style( 'restaurant-schedule-admin', plugin_dir_url( __FILE__ ) . 'assets/css/admin.css', array(), '1.0.0' );
            wp_enqueue_script( 'restaurant-schedule-admin', plugin_dir_url( __FILE__ ) . 'assets/js/admin.js', array( 'jquery', 'jquery-ui-datepicker' ), '1.0.0', true );

            wp_localize_script(
                'restaurant-schedule-admin',
                'RestaurantScheduleAdmin',
                array(
                    'timepickerFormat' => 'HH:mm',
                    'dateFormat'       => 'yy-mm-dd',
                    'i18n'             => array(
                        'startPlaceholder'   => __( 'Start time', 'restaurant-schedule' ),
                        'endPlaceholder'     => __( 'End time', 'restaurant-schedule' ),
                        'holidayPlaceholder' => __( 'YYYY-MM-DD', 'restaurant-schedule' ),
                        'removeRange'        => __( 'Remove', 'restaurant-schedule' ),
                        'removeHoliday'      => __( 'Remove', 'restaurant-schedule' ),
                    ),
                )
            );
        }

        /**
         * Enqueue frontend assets.
         */
        public function enqueue_frontend_assets() {
            wp_enqueue_style( 'restaurant-schedule-frontend', plugin_dir_url( __FILE__ ) . 'assets/css/frontend.css', array(), '1.0.0' );
            wp_enqueue_script( 'restaurant-schedule-frontend', plugin_dir_url( __FILE__ ) . 'assets/js/frontend.js', array( 'jquery' ), '1.0.0', true );

            wp_localize_script(
                'restaurant-schedule-frontend',
                'RestaurantScheduleFrontend',
                array(
                    'dismissKey' => 'restaurantScheduleNoticeDismissed',
                )
            );
        }

        /**
         * Render closed notice on frontend when applicable.
         */
        public function render_closed_notice() {
            if ( $this->is_restaurant_open() ) {
                return;
            }

            $message = __( '⚠️ The restaurant is currently closed. Orders are only accepted during opening hours.', 'restaurant-schedule' );
            ?>
            <div class="restaurant-schedule-notice" role="status" data-dismiss-key="restaurantScheduleNoticeDismissed">
                <span class="restaurant-schedule-notice__message"><?php echo esc_html( $message ); ?></span>
                <button type="button" class="restaurant-schedule-notice__dismiss" aria-label="<?php esc_attr_e( 'Dismiss notice', 'restaurant-schedule' ); ?>">
                    &times;
                </button>
            </div>
            <?php
        }

        /**
         * Validate checkout availability.
         */
        public function validate_checkout_open_status() {
            if ( $this->is_restaurant_open() ) {
                return;
            }

            if ( function_exists( 'wc_add_notice' ) ) {
                wc_add_notice( __( '❌ Sorry, the restaurant is closed. Please place your order during opening hours.', 'restaurant-schedule' ), 'error' );
            }
        }

        /**
         * Check if the restaurant is currently open.
         *
         * @return bool
         */
        public function is_restaurant_open() {
            $schedule = $this->get_schedule();
            $now      = current_time( 'timestamp' );

            $current_date = wp_date( 'Y-m-d', $now );
            if ( ! empty( $schedule['holidays'] ) && in_array( $current_date, $schedule['holidays'], true ) ) {
                return false;
            }

            $current_day_key = strtolower( wp_date( 'l', $now ) );
            $minutes         = (int) wp_date( 'G', $now ) * 60 + (int) wp_date( 'i', $now );

            $ranges = isset( $schedule[ $current_day_key ] ) ? $schedule[ $current_day_key ] : array();
            foreach ( $ranges as $range ) {
                if ( $this->is_time_in_range( $minutes, $range['start'], $range['end'] ) ) {
                    return true;
                }
            }

            // Handle overnight ranges from previous day.
            $previous_timestamp = $now - DAY_IN_SECONDS;
            $previous_day_key   = strtolower( wp_date( 'l', $previous_timestamp ) );
            $prev_ranges        = isset( $schedule[ $previous_day_key ] ) ? $schedule[ $previous_day_key ] : array();
            foreach ( $prev_ranges as $range ) {
                if ( $this->is_overnight_range( $range['start'], $range['end'] ) ) {
                    if ( $this->is_time_in_overnight_range( $minutes, $range['start'], $range['end'] ) ) {
                        return true;
                    }
                }
            }

            return false;
        }

        /**
         * Determine if a range crosses midnight.
         *
         * @param string $start Start time.
         * @param string $end   End time.
         *
         * @return bool
         */
        private function is_overnight_range( $start, $end ) {
            return $this->time_to_minutes( $end ) < $this->time_to_minutes( $start );
        }

        /**
         * Check if the given minutes fall within a range, including overnight logic.
         *
         * @param int    $minutes Current minutes from midnight.
         * @param string $start   Start time (HH:MM).
         * @param string $end     End time (HH:MM).
         *
         * @return bool
         */
        private function is_time_in_range( $minutes, $start, $end ) {
            $start_minutes = $this->time_to_minutes( $start );
            $end_minutes   = $this->time_to_minutes( $end );

            if ( $start_minutes <= $end_minutes ) {
                return ( $minutes >= $start_minutes && $minutes <= $end_minutes );
            }

            // Overnight range, current day portion.
            return ( $minutes >= $start_minutes );
        }

        /**
         * Check if minutes fall within overnight portion for the following day.
         *
         * @param int    $minutes Minutes from midnight.
         * @param string $start   Start time.
         * @param string $end     End time.
         *
         * @return bool
         */
        private function is_time_in_overnight_range( $minutes, $start, $end ) {
            $start_minutes = $this->time_to_minutes( $start );
            $end_minutes   = $this->time_to_minutes( $end );

            if ( $start_minutes <= $end_minutes ) {
                return false;
            }

            return ( $minutes <= $end_minutes );
        }

        /**
         * Convert HH:MM to minutes from midnight.
         *
         * @param string $time Time string.
         *
         * @return int
         */
        private function time_to_minutes( $time ) {
            list( $hour, $minute ) = array_map( 'intval', explode( ':', $time ) );
            return ( $hour * 60 ) + $minute;
        }

        /**
         * Return week days mapping.
         *
         * @return array
         */
        private function get_week_days() {
            return array(
                'monday'    => __( 'Monday', 'restaurant-schedule' ),
                'tuesday'   => __( 'Tuesday', 'restaurant-schedule' ),
                'wednesday' => __( 'Wednesday', 'restaurant-schedule' ),
                'thursday'  => __( 'Thursday', 'restaurant-schedule' ),
                'friday'    => __( 'Friday', 'restaurant-schedule' ),
                'saturday'  => __( 'Saturday', 'restaurant-schedule' ),
                'sunday'    => __( 'Sunday', 'restaurant-schedule' ),
            );
        }
    }
}

new Restaurant_Schedule_Manager();
