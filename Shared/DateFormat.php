<?php
/**
 * Shared date-format and normalization helpers.
 *
 * Single source of truth for how the plugin reads the global date-format
 * setting, normalizes user-entered dates to Y-m-d, and computes "today"
 * in the site's timezone (not the server's).
 *
 * @package Paxrank\DateBlocker
 */

namespace Paxrank\DateBlocker\Shared;

use DateTimeImmutable;
use DateTimeInterface;
use DateTime;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Stateless helpers for date formatting/parsing.
 */
class DateFormat {

    /**
     * Option name that stores the admin-selected display format.
     */
    private const OPTION = 'paxrank_gf_date_blocker_date_format';

    /**
     * Supported display formats and their PHP format equivalents.
     *
     * @var array<string, string>
     */
    private const FORMAT_MAP = array(
        'DD/MM/YYYY' => 'd/m/Y',
        'MM/DD/YYYY' => 'm/d/Y',
        'YYYY-MM-DD' => 'Y-m-d',
        'DD-MM-YYYY' => 'd-m-Y',
        'MM-DD-YYYY' => 'm-d-Y',
    );

    /**
     * Get the list of allowed display formats (keys).
     *
     * @return string[]
     */
    public static function allowed_formats(): array {
        return array_keys( self::FORMAT_MAP );
    }

    /**
     * Get the admin-selected display format (e.g. "DD/MM/YYYY").
     *
     * @return string
     */
    public static function get_display_format(): string {
        $format = get_option( self::OPTION, 'DD/MM/YYYY' );

        return isset( self::FORMAT_MAP[ $format ] ) ? $format : 'DD/MM/YYYY';
    }

    /**
     * Get the PHP date() format string for the current display format.
     *
     * @return string
     */
    public static function get_php_format(): string {
        return self::FORMAT_MAP[ self::get_display_format() ];
    }

    /**
     * Convert a user-entered date string to canonical Y-m-d.
     *
     * Accepts a value already in Y-m-d, otherwise parses it using the
     * global display format. Returns false when the value is empty or not
     * a valid date.
     *
     * @param string $date_string Raw date value.
     * @return string|false Canonical Y-m-d date, or false on failure.
     */
    public static function normalize( string $date_string ) {
        if ( '' === trim( $date_string ) ) {
            return false;
        }

        $date_string = trim( $date_string );

        // Already canonical.
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_string ) ) {
            $date_obj = DateTime::createFromFormat( 'Y-m-d', $date_string );

            return ( $date_obj && $date_obj->format( 'Y-m-d' ) === $date_string ) ? $date_string : false;
        }

        $php_format = self::get_php_format();
        $date_obj   = DateTime::createFromFormat( $php_format, $date_string );

        if ( $date_obj && $date_obj->format( $php_format ) === $date_string ) {
            return $date_obj->format( 'Y-m-d' );
        }

        return false;
    }

    /**
     * Format a Y-m-d string (or DateTimeInterface) using the display format.
     *
     * @param string|DateTimeInterface $date Y-m-d string or date object.
     * @return string Formatted date for display.
     */
    public static function for_display( $date ): string {
        $date_obj = $date instanceof DateTimeInterface ? $date : new DateTime( (string) $date );

        return $date_obj->format( self::get_php_format() );
    }

    /**
     * Build a midnight DateTimeImmutable (site timezone) from a Y-m-d string.
     *
     * @param string $ymd Canonical Y-m-d date.
     * @return DateTimeImmutable|null Null when the value is not a valid Y-m-d.
     */
    public static function to_date( string $ymd ): ?DateTimeImmutable {
        $obj = DateTimeImmutable::createFromFormat( 'Y-m-d', $ymd, wp_timezone() );

        return ( $obj && $obj->format( 'Y-m-d' ) === $ymd ) ? $obj->setTime( 0, 0, 0 ) : null;
    }

    /**
     * Today's date at midnight, in the site's configured timezone.
     *
     * @return DateTimeImmutable
     */
    public static function today(): DateTimeImmutable {
        $now = new DateTimeImmutable( 'now', wp_timezone() );

        return $now->setTime( 0, 0, 0 );
    }

    /**
     * Today's date as a Y-m-d string in the site's timezone.
     *
     * @return string
     */
    public static function today_string(): string {
        return self::today()->format( 'Y-m-d' );
    }
}
