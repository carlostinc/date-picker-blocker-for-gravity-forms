<?php
/**
 * Server-side enforcement: reject submissions with blocked dates.
 *
 * This is the authoritative blocking mechanism (the frontend JS is only a
 * UX enhancement). Runs on gform_validation across every date field.
 *
 * @package Paxrank\DateBlocker
 */

namespace Paxrank\DateBlocker\DateRestrictions\Enforcement;

use Paxrank\DateBlocker\DateRestrictions\Rules\CheckingAdvanceRestrictions;
use Paxrank\DateBlocker\DateRestrictions\Rules\CheckingBlockedRanges;
use Paxrank\DateBlocker\DateRestrictions\Rules\CheckingWeekdayRestrictions;
use Paxrank\DateBlocker\Shared\GravityForms;
use DateTime;

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
        add_filter( 'gform_validation', array( __CLASS__, 'validate' ), 10, 1 );
        add_filter( 'gform_validation_message', array( __CLASS__, 'message' ), 10, 2 );
    }

    /**
     * Validate all date fields of the submitted form.
     *
     * @param array $validation_result Gravity Forms validation result.
     * @return array
     */
    public static function validate( $validation_result ) {
        $form = $validation_result['form'];

        // A stale flag from an earlier validation of this same form (long-lived
        // processes, tests) must not leak into this run.
        unset( self::$failed_forms[ (int) $form['id'] ] );

        // Gravity Forms fields are objects, so mutating $field->* updates the
        // same instance held in $form['fields'] (no by-reference loop needed).
        foreach ( $form['fields'] as $field ) {
            if ( 'date' !== $field->get_input_type() ) {
                continue;
            }

            // Parse using the field's OWN date format (not the plugin's global
            // setting) so a per-field format or a dmy dropdown cannot slip a
            // blocked date past validation. Genuinely unparseable/empty values
            // are skipped — those cannot be a real calendar date, and Gravity
            // Forms' own field validation rejects malformed input.
            $date = self::read_posted_date( $field );

            if ( '' === $date ) {
                continue;
            }

            $failure = self::first_failing_message( $date, (int) $form['id'], (int) $field->id );

            if ( null !== $failure ) {
                $validation_result['is_valid'] = false;
                $field->failed_validation      = true;
                $field->validation_message     = $failure;

                self::$failed_forms[ (int) $form['id'] ] = true;
            }
        }

        $validation_result['form'] = $form;

        return $validation_result;
    }

    /**
     * Replace the form-level message when a date restriction failed.
     *
     * Reads the flag recorded during validate() instead of matching on the
     * message text, which would break under translation and only ever caught
     * two of the four rule messages.
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
     * Read the submitted date for a field and return canonical Y-m-d (or '').
     *
     * Parses using the field's OWN Gravity Forms date format: the single-input
     * value with the field's PHP format, and the `_1/_2/_3` sub-inputs in the
     * field's own order. Gravity Forms owns the submission nonce; this only
     * reads the field value.
     *
     * @param object $field Gravity Forms date field.
     * @return string Canonical Y-m-d, or '' when absent/invalid.
     */
    private static function read_posted_date( $field ): string {
        $id    = (int) $field->id;
        $token = GravityForms::field_date_format( $field );

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Gravity Forms owns the submission nonce and has already verified it before firing gform_validation; this only reads the field value, unslashed and sanitized.
        $single = 'input_' . $id;
        if ( ! empty( $_POST[ $single ] ) ) {
            $raw = sanitize_text_field( wp_unslash( $_POST[ $single ] ) );

            return self::single_to_ymd( $raw, $token );
        }

        $p1 = 'input_' . $id . '_1';
        $p2 = 'input_' . $id . '_2';
        $p3 = 'input_' . $id . '_3';

        if ( ! empty( $_POST[ $p1 ] ) && ! empty( $_POST[ $p2 ] ) && ! empty( $_POST[ $p3 ] ) ) {
            $order = GravityForms::subinput_order_for( $token );
            $parts = array(
                $order[0] => (int) sanitize_text_field( wp_unslash( $_POST[ $p1 ] ) ),
                $order[1] => (int) sanitize_text_field( wp_unslash( $_POST[ $p2 ] ) ),
                $order[2] => (int) sanitize_text_field( wp_unslash( $_POST[ $p3 ] ) ),
            );

            return self::parts_to_ymd( $parts['y'] ?? 0, $parts['m'] ?? 0, $parts['d'] ?? 0 );
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        return '';
    }

    /**
     * Parse a single-input value (field format, then ISO fallback) to Y-m-d.
     *
     * @param string $raw   Raw submitted value.
     * @param string $token GF dateFormat token.
     * @return string Y-m-d, or '' when invalid.
     */
    private static function single_to_ymd( string $raw, string $token ): string {
        $raw = trim( $raw );
        if ( '' === $raw ) {
            return '';
        }

        foreach ( array( GravityForms::php_format_for( $token ), 'Y-m-d' ) as $format ) {
            $date = DateTime::createFromFormat( $format, $raw );
            if ( $date && $date->format( $format ) === $raw ) {
                return $date->format( 'Y-m-d' );
            }
        }

        return '';
    }

    /**
     * Build a canonical Y-m-d from integer parts, or '' when not a real date.
     *
     * @param int $year  Year.
     * @param int $month Month.
     * @param int $day   Day.
     * @return string
     */
    private static function parts_to_ymd( int $year, int $month, int $day ): string {
        if ( ! checkdate( $month, $day, $year ) ) {
            return '';
        }

        return sprintf( '%04d-%02d-%02d', $year, $month, $day );
    }
}
