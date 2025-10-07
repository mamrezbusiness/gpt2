<?php
/**
 * Helper utilities for Woo Order Hours.
 *
 * @package WooOrderHours
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get plugin default settings.
 *
 * @return array
 */
function woh_get_default_settings(): array {
    return [
        'weekly'        => [
            'mon' => [],
            'tue' => [],
            'wed' => [],
            'thu' => [],
            'fri' => [],
            'sat' => [],
            'sun' => [],
        ],
        'holidays'      => [],
        'special_hours' => [],
        'messages'      => [
            'closed_notice' => __( "We're currently closed. Ordering will reopen soon.", 'woo-order-hours' ),
            'reopen_prefix' => __( 'Ordering opens at', 'woo-order-hours' ),
        ],
        'toggles'       => [
            'allow_add_to_cart_outside_hours' => true,
            'block_checkout_server_side'      => true,
            'show_global_banner_when_closed'  => true,
            'show_countdown_to_next_open'     => false,
        ],
    ];
}

/**
 * Retrieve plugin settings merged with defaults.
 *
 * @return array
 */
function woh_get_settings(): array {
    $settings = get_option( 'woo_order_hours_settings', [] );

    return wp_parse_args( $settings, woh_get_default_settings() );
}

/**
 * Get the weekdays in order starting Monday.
 *
 * @return array<string, string>
 */
function woh_get_weekday_labels(): array {
    return [
        'mon' => _x( 'Monday', 'weekday', 'woo-order-hours' ),
        'tue' => _x( 'Tuesday', 'weekday', 'woo-order-hours' ),
        'wed' => _x( 'Wednesday', 'weekday', 'woo-order-hours' ),
        'thu' => _x( 'Thursday', 'weekday', 'woo-order-hours' ),
        'fri' => _x( 'Friday', 'weekday', 'woo-order-hours' ),
        'sat' => _x( 'Saturday', 'weekday', 'woo-order-hours' ),
        'sun' => _x( 'Sunday', 'weekday', 'woo-order-hours' ),
    ];
}

/**
 * Convert time string HH:MM to seconds from midnight.
 *
 * @param string $time Time.
 * @return int
 */
function woh_time_to_seconds( string $time ): int {
    [ $hour, $minute ] = array_map( 'intval', explode( ':', $time ) );

    return ( $hour * 3600 ) + ( $minute * 60 );
}

/**
 * Format seconds from midnight back to HH:MM.
 *
 * @param int $seconds Seconds.
 * @return string
 */
function woh_seconds_to_time( int $seconds ): string {
    $seconds = max( 0, min( 86399, $seconds ) );

    $hours   = floor( $seconds / 3600 );
    $minutes = floor( ( $seconds % 3600 ) / 60 );

    return sprintf( '%02d:%02d', $hours, $minutes );
}

/**
 * Get the plugin cache buster option value.
 *
 * @return string
 */
function woh_get_cache_buster(): string {
    $buster = get_option( 'woo_order_hours_cache_buster' );

    if ( ! $buster ) {
        $buster = (string) time();
        update_option( 'woo_order_hours_cache_buster', $buster, false );
    }

    return $buster;
}

/**
 * Touch the cache buster value.
 *
 * @return void
 */
function woh_touch_cache_buster(): void {
    update_option( 'woo_order_hours_cache_buster', (string) time(), false );
}

/**
 * Get the site timezone.
 *
 * @return DateTimeZone
 */
function woh_get_timezone(): DateTimeZone {
    return wp_timezone();
}
