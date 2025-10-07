<?php
/**
 * Checkout and cart guards.
 *
 * @package WooOrderHours
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WOH_Checkout_Guard {
    private static bool $bootstrapped = false;

    public static function init(): void {
        if ( self::$bootstrapped ) {
            return;
        }

        self::$bootstrapped = true;

        add_action( 'woocommerce_before_checkout_process', [ self::class, 'enforce_checkout' ] );
        add_filter( 'woocommerce_get_checkout_url', [ self::class, 'filter_checkout_url' ] );
        add_filter( 'woocommerce_proceed_to_checkout', [ self::class, 'filter_proceed_button' ], 10, 1 );
        add_filter( 'woocommerce_add_to_cart_validation', [ self::class, 'validate_add_to_cart' ], 10, 3 );
    }

    public static function enforce_checkout(): void {
        $settings = WOH_Settings::get_settings();

        if ( empty( $settings['toggles']['block_checkout_server_side'] ) ) {
            return;
        }

        $scheduler = WOH_Scheduler::instance();
        if ( $scheduler->is_open() ) {
            return;
        }

        $payload = $scheduler->get_status_payload();
        $message = $settings['messages']['closed_notice'];
        if ( ! empty( $payload['human_next_opening'] ) ) {
            $message .= ' ' . $settings['messages']['reopen_prefix'] . ' ' . $payload['human_next_opening'];
        }

        if ( function_exists( 'wc_add_notice' ) ) {
            wc_add_notice( wp_kses_post( $message ), 'error' );
        }

        throw new Exception( __( 'Checkout is currently disabled.', 'woo-order-hours' ) );
    }

    public static function filter_checkout_url( string $url ): string {
        $settings = WOH_Settings::get_settings();
        if ( empty( $settings['toggles']['block_checkout_server_side'] ) ) {
            return $url;
        }

        $scheduler = WOH_Scheduler::instance();
        if ( $scheduler->is_open() ) {
            return $url;
        }

        return '#';
    }

    public static function filter_proceed_button( string $html ): string {
        $settings = WOH_Settings::get_settings();
        if ( empty( $settings['toggles']['block_checkout_server_side'] ) ) {
            return $html;
        }

        $scheduler = WOH_Scheduler::instance();
        if ( $scheduler->is_open() ) {
            return $html;
        }

        $payload = $scheduler->get_status_payload();
        $message = $settings['messages']['closed_notice'];
        if ( ! empty( $payload['human_next_opening'] ) ) {
            $message .= ' ' . $settings['messages']['reopen_prefix'] . ' ' . $payload['human_next_opening'];
        }

        $button  = '<a class="button disabled woh-proceed-disabled" href="#" aria-disabled="true">' . esc_html__( 'Checkout unavailable', 'woo-order-hours' ) . '</a>';
        $button .= '<p class="woh-proceed-message">' . esc_html( $message ) . '</p>';

        return $button;
    }

    public static function validate_add_to_cart( bool $passed, $product_id, $quantity ): bool {
        unset( $product_id, $quantity );

        $settings = WOH_Settings::get_settings();
        if ( ! empty( $settings['toggles']['allow_add_to_cart_outside_hours'] ) ) {
            return $passed;
        }

        $scheduler = WOH_Scheduler::instance();
        if ( $scheduler->is_open() ) {
            return $passed;
        }

        if ( function_exists( 'wc_add_notice' ) ) {
            $payload = $scheduler->get_status_payload();
            $message = $settings['messages']['closed_notice'];
            if ( ! empty( $payload['human_next_opening'] ) ) {
                $message .= ' ' . $settings['messages']['reopen_prefix'] . ' ' . $payload['human_next_opening'];
            }
            wc_add_notice( wp_kses_post( $message ), 'error' );
        }

        return false;
    }
}
