<?php
/**
 * Server-side enforcement: reject submissions with blocked dates.
 *
 * This is the authoritative blocking mechanism (the frontend JS is only a
 * UX enhancement). Hooks gform_field_validation, Gravity Forms' per-field
 * validation filter: GF hands this class the submitted value already
 * composed by GFFormsModel::get_field_value() — a string for single-input
 * date pickers, a positional array for the three-input and dropdown
 * variants — so no superglobal is ever read here. The filter also runs
 * after GF's own field validation and is never fired for fields hidden by
 * conditional logic or sitting on other pages of a multi-page form, which
 * this class inherits for free.
 *
 * @package Paxrank\DateBlocker
 */

namespace Paxrank\DateBlocker\Enforcement;

use Paxrank\DateBlocker\Rules\CheckingAdvanceRestrictions;
use Paxrank\DateBlocker\Rules\CheckingBlockedRanges;
use Paxrank\DateBlocker\Rules\CheckingWeekdayRestrictions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Validates submitted Gravity Forms dates against all restriction rules.
 */
class ValidatingSubmittedDates {

    /**
     * Form IDs where a date restriction rejected at least one field.
     *
     * Keyed by form ID so two forms on the same page cannot affect each other.
     *
     * @var array<int, bool>
     */
    private static array $failed_forms = array();

    /**
     * Register Gravity Forms validation hooks.
     *
     * @return void
     */
    public static function register(): void {
        add_filter( 'gform_field_validation', array( __CLASS__, 'validate_field' ), 10, 4 );
        add_filter( 'gform_pre_validation', array( __CLASS__, 'reset_form_flag' ) );
        add_filter( 'gform_validation_message', array( __CLASS__, 'message' ), 10, 2 );
    }

    /**
     * Clear the failed flag before a form's validation run starts.
     *
     * A stale flag from an earlier validation of this same form (long-lived
     * processes, tests) must not leak into the next run.
     *
     * @param array $form Gravity Forms form.
     * @return array
     */
    public static function reset_form_flag( $form ) {
        if ( isset( $form['id'] ) ) {
            unset( self::$failed_forms[ (int) $form['id'] ] );
        }

        return $form;
    }

    /**
     * Validate one date field's submitted value against the restriction rules.
     *
     * @param array  $result Validation result: is_valid + message.
     * @param mixed  $value  Submitted value, composed by GF (string or array).
     * @param array  $form   Gravity Forms form.
     * @param object $field  Gravity Forms field.
     * @return array
     */
    public static function validate_field( $result, $value, $form, $field ) {
        // GF already rejected this field (required-empty, malformed date…):
        // keep its verdict and its message.
        if ( ! is_array( $result ) || empty( $result['is_valid'] ) ) {
            return $result;
        }

        if ( ! is_object( $field ) || 'date' !== $field->get_input_type() ) {
            return $result;
        }

        $date = self::to_ymd( $value, $field );

        // Empty or not a real calendar date: nothing to check. An empty
        // optional field is fine, and GF's own field validation owns the
        // malformed-input message.
        if ( '' === $date ) {
            return $result;
        }

        $form_id = isset( $form['id'] ) ? (int) $form['id'] : 0;
        $failure = self::first_failing_message( $date, $form_id, (int) $field->id );

        if ( null !== $failure ) {
            $result['is_valid'] = false;
            $result['message']  = $failure;

            self::$failed_forms[ $form_id ] = true;
        }

        return $result;
    }

    /**
     * Replace the form-level message when a date restriction failed.
     *
     * Reads the flag recorded during validate_field() instead of matching on
     * the message text, which would break under translation and only ever
     * caught two of the four rule messages.
     *
     * @param string $message Current validation message.
     * @param array  $form    Gravity Forms form.
     * @return string
     */
    public static function message( $message, $form ) {
        $form_id = isset( $form['id'] ) ? (int) $form['id'] : 0;

        if ( empty( self::$failed_forms[ $form_id ] ) ) {
            return $message;
        }

        return '<div class="validation_error paxrank-validation-error">'
            . esc_html__( 'Some dates in your selection are unavailable. Please check the highlighted fields.', 'date-picker-blocker-for-gravity-forms' )
            . '</div>';
    }

    /**
     * Return the first applicable failure message for a date, or null.
     *
     * @param string $date     Canonical Y-m-d date.
     * @param int    $form_id  Gravity Forms form ID.
     * @param int    $field_id Date field ID.
     * @return string|null
     */
    private static function first_failing_message( string $date, int $form_id, int $field_id ): ?string {
        if ( CheckingBlockedRanges::is_blocked( $date, $form_id, $field_id ) ) {
            return CheckingBlockedRanges::message();
        }

        if ( CheckingAdvanceRestrictions::is_too_soon( $date, $form_id, $field_id ) ) {
            return CheckingAdvanceRestrictions::message( $date, $form_id, $field_id );
        }

        if ( CheckingWeekdayRestrictions::is_blocked( $date, $form_id, $field_id ) ) {
            return CheckingWeekdayRestrictions::message( $date );
        }

        return null;
    }

    /**
     * Canonical Y-m-d from a GF-composed submission value, or '' when
     * empty or not a real date.
     *
     * GFCommon::parse_date() understands both shapes GF submits: the
     * single-input string in the field's own format, and the positional
     * array of the three-input/dropdown variants (ordered by the field's
     * format). The guards mirror GF_Field_Date::checkdate(), so this accepts
     * exactly the dates GF itself considers valid.
     *
     * @param mixed  $value Submitted value (string or array).
     * @param object $field Gravity Forms date field.
     * @return string Y-m-d, or '' when absent/invalid.
     */
    private static function to_ymd( $value, $field ): string {
        $format = ! empty( $field->dateFormat ) ? (string) $field->dateFormat : 'mdy';
        $date   = \GFCommon::parse_date( $value, $format );

        if ( empty( $date ) ) {
            return '';
        }

        $month = $date['month'] ?? '';
        $day   = $date['day'] ?? '';
        $year  = $date['year'] ?? '';

        // Same guards as GF_Field_Date::checkdate(): numeric parts, 4-digit year.
        if ( ! is_numeric( $month ) || ! is_numeric( $day ) || ! is_numeric( $year ) || 4 !== strlen( (string) $year ) ) {
            return '';
        }

        if ( ! checkdate( (int) $month, (int) $day, (int) $year ) ) {
            return '';
        }

        return sprintf( '%04d-%02d-%02d', (int) $year, (int) $month, (int) $day );
    }
}
