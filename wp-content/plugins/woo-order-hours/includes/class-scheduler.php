<?php
/**
 * Scheduling engine.
 *
 * @package WooOrderHours
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WOH_Scheduler {
    private array $settings;

    private DateTimeZone $timezone;

    /**
     * Weekly intervals per day.
     *
     * @var array<string, array<int, array{start:int,end:int}>>
     */
    private array $weekly = [];

    /**
     * Holidays indexed by Y-m-d.
     *
     * @var array<string, bool>
     */
    private array $holidays = [];

    /**
     * Special hours by date.
     *
     * @var array<string, array<int, array{start:int,end:int}>>
     */
    private array $specials = [];

    /**
     * Carry-over segments from special hours into next day.
     *
     * @var array<string, array<int, array{start:int,end:int}>>
     */
    private array $special_carry = [];

    private array $day_keys;

    private array $date_interval_cache = [];

    private static ?self $instance = null;

    public function __construct( array $settings, ?DateTimeZone $tz = null ) {
        $this->settings = $settings;
        $this->timezone = $tz ?? woh_get_timezone();
        $this->day_keys = array_keys( woh_get_weekday_labels() );

        $this->normalize();
    }

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self( WOH_Settings::get_settings(), woh_get_timezone() );
        }

        return self::$instance;
    }

    public static function reset_instance(): void {
        self::$instance = null;
    }

    public function is_open( ?DateTimeInterface $now = null ): bool {
        $payload = $this->get_status_payload( $now );

        return (bool) $payload['is_open'];
    }

    public function next_opening( ?DateTimeInterface $now = null ): ?DateTimeImmutable {
        $payload = $this->get_status_payload( $now );

        if ( empty( $payload['next_opening_iso'] ) ) {
            return null;
        }

        try {
            return new DateTimeImmutable( $payload['next_opening_iso'], $this->timezone );
        } catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
        }

        return null;
    }

    public function get_status_payload( ?DateTimeInterface $now = null ): array {
        $now = $this->normalize_now( $now );
        $cache_key = $this->build_cache_key( $now );
        $cached    = get_transient( $cache_key );

        if ( false !== $cached && is_array( $cached ) ) {
            return $cached;
        }

        $status = $this->compute_status( $now );
        set_transient( $cache_key, $status, 60 );

        return $status;
    }

    private function compute_status( DateTimeImmutable $now ): array {
        $is_open     = $this->evaluate_is_open( $now );
        $next_open   = $this->determine_next_opening( $now );
        $human_next  = null;
        $next_open_iso = null;

        if ( $next_open instanceof DateTimeImmutable ) {
            $next_open_iso = $next_open->setTimezone( $this->timezone )->format( DATE_ATOM );
            $human_next    = $this->format_human_time( $now, $next_open );
        }

        return [
            'is_open'             => $is_open,
            'next_opening_iso'    => $next_open_iso,
            'human_next_opening'  => $human_next,
        ];
    }

    private function normalize(): void {
        $this->normalize_weekly();
        $this->normalize_holidays();
        $this->normalize_specials();
    }

    private function normalize_weekly(): void {
        foreach ( $this->day_keys as $day ) {
            $this->weekly[ $day ] = [];
        }

        foreach ( $this->settings['weekly'] as $day => $intervals ) {
            if ( ! isset( $this->weekly[ $day ] ) || ! is_array( $intervals ) ) {
                continue;
            }

            foreach ( $intervals as $interval ) {
                if ( empty( $interval['start'] ) || empty( $interval['end'] ) ) {
                    continue;
                }

                $this->add_weekly_interval( $day, $interval['start'], $interval['end'] );
            }
        }

        foreach ( $this->weekly as &$intervals ) {
            usort(
                $intervals,
                static function ( array $a, array $b ): int {
                    return $a['start'] <=> $b['start'];
                }
            );
        }
    }

    private function add_weekly_interval( string $day, string $start, string $end ): void {
        $start_seconds = woh_time_to_seconds( $start );
        $end_seconds   = woh_time_to_seconds( $end );

        if ( $start_seconds === $end_seconds ) {
            return;
        }

        if ( $start_seconds < $end_seconds ) {
            $this->weekly[ $day ][] = [
                'start' => $start_seconds,
                'end'   => $end_seconds,
            ];

            return;
        }

        $this->weekly[ $day ][] = [
            'start' => $start_seconds,
            'end'   => 86400,
        ];

        $next_day = $this->get_next_day_key( $day );
        if ( ! isset( $this->weekly[ $next_day ] ) ) {
            $this->weekly[ $next_day ] = [];
        }

        if ( $end_seconds > 0 ) {
            $this->weekly[ $next_day ][] = [
                'start' => 0,
                'end'   => $end_seconds,
            ];
        }
    }

    private function normalize_holidays(): void {
        $this->holidays = [];
        if ( empty( $this->settings['holidays'] ) || ! is_array( $this->settings['holidays'] ) ) {
            return;
        }

        foreach ( $this->settings['holidays'] as $date ) {
            $this->holidays[ $date ] = true;
        }
    }

    private function normalize_specials(): void {
        $this->specials      = [];
        $this->special_carry = [];

        if ( empty( $this->settings['special_hours'] ) || ! is_array( $this->settings['special_hours'] ) ) {
            return;
        }

        foreach ( $this->settings['special_hours'] as $entry ) {
            if ( empty( $entry['date'] ) || empty( $entry['intervals'] ) ) {
                continue;
            }

            $date = $entry['date'];
            $this->specials[ $date ] = [];

            foreach ( $entry['intervals'] as $interval ) {
                if ( empty( $interval['start'] ) || empty( $interval['end'] ) ) {
                    continue;
                }

                $start_seconds = woh_time_to_seconds( $interval['start'] );
                $end_seconds   = woh_time_to_seconds( $interval['end'] );

                if ( $start_seconds === $end_seconds ) {
                    continue;
                }

                if ( $start_seconds < $end_seconds ) {
                    $this->specials[ $date ][] = [
                        'start' => $start_seconds,
                        'end'   => $end_seconds,
                    ];
                } else {
                    $this->specials[ $date ][] = [
                        'start' => $start_seconds,
                        'end'   => 86400,
                    ];

                    $next_date = $this->increment_date( $date );
                    if ( ! isset( $this->special_carry[ $next_date ] ) ) {
                        $this->special_carry[ $next_date ] = [];
                    }

                    if ( $end_seconds > 0 ) {
                        $this->special_carry[ $next_date ][] = [
                            'start' => 0,
                            'end'   => $end_seconds,
                        ];
                    }
                }
            }

            usort(
                $this->specials[ $date ],
                static function ( array $a, array $b ): int {
                    return $a['start'] <=> $b['start'];
                }
            );
        }
    }

    private function evaluate_is_open( DateTimeImmutable $now ): bool {
        $seconds = (int) $now->format( 'H' ) * 3600 + (int) $now->format( 'i' ) * 60 + (int) $now->format( 's' );
        $intervals = $this->get_intervals_for_date( $now );

        foreach ( $intervals as $interval ) {
            if ( $interval['start'] <= $seconds && $seconds < $interval['end'] ) {
                return true;
            }
        }

        return false;
    }

    private function determine_next_opening( DateTimeImmutable $now ): ?DateTimeImmutable {
        $intervals = $this->generate_schedule( $now );
        $current_end = null;

        foreach ( $intervals as $interval ) {
            if ( $interval['end'] <= $now ) {
                continue;
            }

            if ( $interval['start'] <= $now && $interval['end'] > $now ) {
                $current_end = $interval['end'];
                continue;
            }

            if ( $current_end && $interval['start'] <= $current_end ) {
                if ( $interval['end'] > $current_end ) {
                    $current_end = $interval['end'];
                }
                continue;
            }

            if ( null === $current_end && $interval['start'] > $now ) {
                return $interval['start'];
            }

            if ( $current_end && $interval['start'] >= $current_end ) {
                return $interval['start'];
            }
        }

        return null;
    }

    private function generate_schedule( DateTimeImmutable $now ): array {
        $schedule = [];
        $base     = $now->setTime( 0, 0, 0 );

        for ( $offset = -1; $offset <= 7; $offset++ ) {
            $date = $offset < 0 ? $base->modify( '-1 day' ) : $base->modify( '+' . $offset . ' day' );
            $intervals = $this->get_intervals_for_date( $date );

            foreach ( $intervals as $interval ) {
                $start = $date->setTime( 0, 0, 0 )->modify( '+' . $interval['start'] . ' seconds' );
                $end   = $date->setTime( 0, 0, 0 )->modify( '+' . $interval['end'] . ' seconds' );
                $schedule[] = [
                    'start' => $start,
                    'end'   => $end,
                ];
            }
        }

        usort(
            $schedule,
            static function ( array $a, array $b ): int {
                return $a['start'] <=> $b['start'];
            }
        );

        return $schedule;
    }

    private function get_intervals_for_date( DateTimeImmutable $date ): array {
        $date_key = $date->format( 'Y-m-d' );
        if ( isset( $this->date_interval_cache[ $date_key ] ) ) {
            return $this->date_interval_cache[ $date_key ];
        }

        if ( isset( $this->holidays[ $date_key ] ) ) {
            $this->date_interval_cache[ $date_key ] = [];
            return [];
        }

        $day_key = strtolower( $date->format( 'D' ) );
        $intervals = $this->weekly[ $day_key ] ?? [];

        if ( isset( $this->specials[ $date_key ] ) ) {
            $intervals = $this->specials[ $date_key ];
        }

        if ( isset( $this->special_carry[ $date_key ] ) && is_array( $this->special_carry[ $date_key ] ) ) {
            $intervals = array_merge( $intervals, $this->special_carry[ $date_key ] );
        }

        usort(
            $intervals,
            static function ( array $a, array $b ): int {
                return $a['start'] <=> $b['start'];
            }
        );

        $this->date_interval_cache[ $date_key ] = $intervals;

        return $intervals;
    }

    private function normalize_now( ?DateTimeInterface $now ): DateTimeImmutable {
        if ( $now instanceof DateTimeImmutable ) {
            return $now->setTimezone( $this->timezone );
        }

        if ( $now instanceof DateTimeInterface ) {
            return ( new DateTimeImmutable( $now->format( DATE_ATOM ) ) )->setTimezone( $this->timezone );
        }

        return new DateTimeImmutable( 'now', $this->timezone );
    }

    private function build_cache_key( DateTimeImmutable $now ): string {
        $minute = $now->format( 'YmdHi' );

        return 'woh_status_' . woh_get_cache_buster() . '_' . $minute;
    }

    private function format_human_time( DateTimeImmutable $now, DateTimeImmutable $next ): string {
        $time_format = get_option( 'time_format', 'H:i' );
        $date_format = get_option( 'date_format', 'F j, Y' );

        $time_string = wp_date( $time_format, $next->getTimestamp(), $this->timezone );

        $now_day  = $now->format( 'Y-m-d' );
        $next_day = $next->format( 'Y-m-d' );

        if ( $now_day === $next_day ) {
            /* translators: %s: opening time. */
            return sprintf( __( 'Today at %s', 'woo-order-hours' ), $time_string );
        }

        $tomorrow = $now->modify( '+1 day' )->format( 'Y-m-d' );
        if ( $tomorrow === $next_day ) {
            /* translators: %s: opening time. */
            return sprintf( __( 'Tomorrow at %s', 'woo-order-hours' ), $time_string );
        }

        return wp_date( $date_format . ' ' . $time_format, $next->getTimestamp(), $this->timezone );
    }

    private function get_next_day_key( string $day ): string {
        $index = array_search( $day, $this->day_keys, true );
        if ( false === $index ) {
            return $this->day_keys[0];
        }

        $next = ( $index + 1 ) % count( $this->day_keys );

        return $this->day_keys[ $next ];
    }

    private function increment_date( string $date ): string {
        try {
            $dt = new DateTimeImmutable( $date, $this->timezone );
        } catch ( Exception $e ) {
            return $date;
        }

        return $dt->modify( '+1 day' )->format( 'Y-m-d' );
    }
}
