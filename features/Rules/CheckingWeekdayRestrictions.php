<?php
/**
 * Rule: is a date's weekday blocked?
 *
 * @package Paxrank\DateBlocker
 */

namespace Paxrank\DateBlocker\Rules;

use Paxrank\DateBlocker\Database\ReadingWeekdayRestrictions;
use Paxrank\DateBlocker\Shared\DateFormat;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Weekday rule (check + user-facing message).
 */
class CheckingWeekdayRestrictions {

    /**
     * Whether the date's weekday is blocked for the scope.
     *
     * @param string   $date     Canonical Y-m-d date.
     * @param int|null $form_id  Gravity Forms form ID.
     * @param int|null $field_id Date field ID.
     * @return bool
     */
    public static function is_blocked( string $date, ?int $form_id = null, ?int $field_id = null ): bool {
        $blocked = ReadingWeekdayRestrictions::blocked_weekdays( $form_id, $field_id );

        if ( empty( $blocked ) ) {
            return false;
        }

        $selected = DateFormat::to_date( $date );

        if ( ! $selected ) {
            return false;
        }

        return in_array( (int) $selected->format( 'w' ), $blocked, true );
    }

    /**
     * Message shown when a weekday is blocked.
     *
     * @param string $date Canonical Y-m-d date.
     * @return string
     */
    public static function message( string $date ): string {
        $selected = DateFormat::to_date( $date );
        $weekday  = $selected ? (int) $selected->format( 'w' ) : 0;

        $names = array(
            __( 'Sunday', 'date-picker-blocker-for-gravity-forms' ),
            __( 'Monday', 'date-picker-blocker-for-gravity-forms' ),
            __( 'Tuesday', 'date-picker-blocker-for-gravity-forms' ),
            __( 'Wednesday', 'date-picker-blocker-for-gravity-forms' ),
            __( 'Thursday', 'date-picker-blocker-for-gravity-forms' ),
            __( 'Friday', 'date-picker-blocker-for-gravity-forms' ),
            __( 'Saturday', 'date-picker-blocker-for-gravity-forms' ),
        );

        return sprintf(
            /* translators: %s: weekday name (e.g. "Monday"). */
            __( '%s is not available for bookings. Please choose another day of the week.', 'date-picker-blocker-for-gravity-forms' ),
            $names[ $weekday ]
        );
    }
}
