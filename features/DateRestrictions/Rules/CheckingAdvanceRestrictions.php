<?php
/**
 * Rule: is a date in the past or inside the advance-booking window?
 *
 * @package Paxrank\DateBlocker
 */

namespace Paxrank\DateBlocker\DateRestrictions\Rules;

use Paxrank\DateBlocker\DateRestrictions\Database\ReadingAdvanceRestrictions;
use Paxrank\DateBlocker\Shared\DateFormat;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Advance-booking / past-date rule (check + user-facing message).
 */
class CheckingAdvanceRestrictions {

    /**
     * Whether the date is in the past or violates the advance-notice window.
     *
     * @param string   $date     Canonical Y-m-d date.
     * @param int|null $form_id  Gravity Forms form ID.
     * @param int|null $field_id Date field ID.
     * @return bool
     */
    public static function is_too_soon( string $date, ?int $form_id = null, ?int $field_id = null ): bool {
        $selected = DateFormat::to_date( $date );

        if ( ! $selected ) {
            return false;
        }

        $today = DateFormat::today();

        if ( $selected < $today ) {
            return true;
        }

        $advance = ReadingAdvanceRestrictions::effective_days( $form_id, $field_id );

        if ( 0 === $advance ) {
            return false;
        }

        return $selected < $today->add( new DateInterval( 'P' . $advance . 'D' ) );
    }

    /**
     * Message shown when a date is in the past or too soon.
     *
     * @param string   $date     Canonical Y-m-d date.
     * @param int|null $form_id  Gravity Forms form ID.
     * @param int|null $field_id Date field ID.
     * @return string
     */
    public static function message( string $date, ?int $form_id = null, ?int $field_id = null ): string {
        $selected = DateFormat::to_date( $date );

        if ( $selected && $selected < DateFormat::today() ) {
            return __( 'You cannot select dates in the past. Please choose a future date.', 'date-picker-blocker-for-gravity-forms' );
        }

        $advance = ReadingAdvanceRestrictions::effective_days( $form_id, $field_id );

        return sprintf(
            /* translators: %d: number of days of required advance notice. */
            _n(
                'You must book at least %d day in advance. Please choose a later date.',
                'You must book at least %d days in advance. Please choose a later date.',
                $advance,
                'date-picker-blocker-for-gravity-forms'
            ),
            $advance
        );
    }
}
