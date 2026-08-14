<?php
/**
 * Rule: is a date inside an explicitly blocked date/range?
 *
 * @package Paxrank\DateBlocker
 */

namespace Paxrank\DateBlocker\DateRestrictions\Rules;

use Paxrank\DateBlocker\DateRestrictions\Database\ReadingBlockedRanges;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Blocked date/range rule (check + user-facing message).
 */
class CheckingBlockedRanges {

    /**
     * Whether the date falls inside a blocked range for the scope.
     *
     * @param string   $date     Canonical Y-m-d date.
     * @param int|null $form_id  Gravity Forms form ID.
     * @param int|null $field_id Date field ID.
     * @return bool
     */
    public static function is_blocked( string $date, ?int $form_id = null, ?int $field_id = null ): bool {
        return ReadingBlockedRanges::is_blocked( $date, $form_id, $field_id );
    }

    /**
     * Message shown when a date is explicitly blocked.
     *
     * @return string
     */
    public static function message(): string {
        return __( 'The selected date is unavailable. Please choose another date.', 'date-picker-blocker-for-gravity-forms' );
    }
}
