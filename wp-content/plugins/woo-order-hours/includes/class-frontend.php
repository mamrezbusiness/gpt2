<?php
/**
 * Frontend interactions.
 *
 * @package WooOrderHours
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WOH_Frontend {
    private static bool $bootstrapped = false;

    public static function init(): void {
        if ( self::$bootstrapped ) {
            return;
        }

        self::$bootstrapped = true;

        add_action( 'init', [ self::class, 'register_shortcode' ] );
        add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue' ] );
        add_action( 'wp_footer', [ self::class, 'footer_banner_placeholder' ] );
        add_action( 'wp_ajax_woh_status', [ self::class, 'ajax_status' ] );
        add_action( 'wp_ajax_nopriv_woh_status', [ self::class, 'ajax_status' ] );
        add_action( 'rest_api_init', [ self::class, 'register_rest_route' ] );
    }

    public static function register_shortcode(): void {
        add_shortcode( 'order_status', [ self::class, 'shortcode_order_status' ] );
    }

    public static function enqueue(): void {
        if ( is_admin() ) {
            return;
        }

        $settings = WOH_Settings::get_settings();

        wp_enqueue_style( 'woh-front', WOH_PLUGIN_URL . 'assets/front.css', [], WOH_VERSION );
        wp_enqueue_script( 'woh-front', WOH_PLUGIN_URL . 'assets/front.js', [], WOH_VERSION, true );

        wp_localize_script(
            'woh-front',
            'wohStatus',
            [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => is_user_logged_in() ? wp_create_nonce( 'woh_status' ) : '',
                'messages' => $settings['messages'],
                'toggles'  => $settings['toggles'],
                'i18n'     => [
                    'open_now'   => __( 'Open now', 'woo-order-hours' ),
                    'closed_now' => __( 'Closed now', 'woo-order-hours' ),
                    'refresh'    => __( 'Please refresh the page once ordering opens.', 'woo-order-hours' ),
                    'close'      => __( 'Dismiss notice', 'woo-order-hours' ),
                ],
            ]
        );
    }

    public static function footer_banner_placeholder(): void {
        echo '<div id="woh-status-banner" aria-live="polite"></div>';
    }

    public static function shortcode_order_status( $atts ): string {
        unset( $atts );

        $scheduler = WOH_Scheduler::instance();
        $payload   = $scheduler->get_status_payload();
        $output    = '';

        if ( ! empty( $payload['is_open'] ) ) {
            $output = '<span class="woh-status woh-status--open">' . esc_html__( 'Open now', 'woo-order-hours' ) . '</span>';
        } else {
            $output = '<span class="woh-status woh-status--closed">' . esc_html__( 'Closed now', 'woo-order-hours' ) . '</span>';
            if ( ! empty( $payload['human_next_opening'] ) ) {
                $output .= ' <span class="woh-next-opening">' . esc_html( $payload['human_next_opening'] ) . '</span>';
            }
        }

        return $output;
    }

    public static function ajax_status(): void {
        if ( is_user_logged_in() ) {
            check_ajax_referer( 'woh_status', 'nonce' );
        }

        nocache_headers();

        $scheduler = WOH_Scheduler::instance();
        $settings  = WOH_Settings::get_settings();
        $payload   = $scheduler->get_status_payload();

        wp_send_json_success(
            [
                'status'   => $payload,
                'messages' => $settings['messages'],
                'toggles'  => $settings['toggles'],
            ]
        );
    }

    public static function register_rest_route(): void {
        register_rest_route(
            'woh/v1',
            '/status',
            [
                'methods'             => WP_REST_Server::READABLE,
                'permission_callback' => '__return_true',
                'callback'            => [ self::class, 'rest_status' ],
            ]
        );
    }

    public static function rest_status( WP_REST_Request $request ): WP_REST_Response {
        unset( $request );

        $scheduler = WOH_Scheduler::instance();
        $settings  = WOH_Settings::get_settings();
        $payload   = $scheduler->get_status_payload();

        return new WP_REST_Response(
            [
                'status'   => $payload,
                'messages' => $settings['messages'],
                'toggles'  => $settings['toggles'],
            ]
        );
    }
}
