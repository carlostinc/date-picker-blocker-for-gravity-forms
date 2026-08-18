<?php
/**
 * Admin form handlers (admin-post) for creating/deleting date restrictions,
 * plus the field-selector AJAX endpoint.
 *
 * Mutations follow the native POST → redirect → notice cycle; feedback is
 * carried through core's settings-errors transient, which WordPress prints
 * itself from options-head.php because the screen lives under Settings.
 *
 * @package Paxrank\DateBlocker
 */

namespace Paxrank\DateBlocker\DateRestrictions\Admin;

use Paxrank\DateBlocker\DateRestrictions\Database\SavingAdvanceRestrictions;
use Paxrank\DateBlocker\DateRestrictions\Database\SavingBlockedRanges;
use Paxrank\DateBlocker\DateRestrictions\Database\SavingWeekdayRestrictions;
use Paxrank\DateBlocker\Shared\DateFormat;
use Paxrank\DateBlocker\Shared\GravityForms;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin-post endpoints for blocked ranges, advance and weekday restrictions.
 */
class HandlingRestrictionsAjax {

    /**
     * Register the admin-post actions and the one remaining AJAX endpoint.
     *
     * @return void
     */
    public static function register(): void {
        add_action( 'admin_post_paxrank_gf_add_blocked_date', array( __CLASS__, 'add_blocked_date' ) );
        add_action( 'admin_post_paxrank_gf_delete_blocked_date', array( __CLASS__, 'delete_blocked_date' ) );
        add_action( 'admin_post_paxrank_gf_add_advance_restriction', array( __CLASS__, 'add_advance_restriction' ) );
        add_action( 'admin_post_paxrank_gf_delete_advance_restriction', array( __CLASS__, 'delete_advance_restriction' ) );
        add_action( 'admin_post_paxrank_gf_add_weekday_restriction', array( __CLASS__, 'add_weekday_restriction' ) );
        add_action( 'admin_post_paxrank_gf_delete_weekday_restriction', array( __CLASS__, 'delete_weekday_restriction' ) );

        add_action( 'wp_ajax_paxrank_gf_get_form_fields', array( __CLASS__, 'get_form_fields' ) );
    }

    /**
     * Require the settings capability, or die (native admin-post screen).
     *
     * The nonce check lives in each handler rather than here: PHPCS only
     * recognizes it when it sits in the same function as the $_POST reads.
     *
     * @return void
     */
    private static function require_manage_options(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'date-picker-blocker-for-gravity-forms' ) );
        }
    }

    /**
     * Require the settings capability, or terminate with a JSON error.
     *
     * @return void
     */
    private static function require_manage_options_json(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'You do not have permission to perform this action.', 'date-picker-blocker-for-gravity-forms' ) );
        }
    }

    /**
     * Stash a notice in core's settings-errors transient and redirect back.
     *
     * Same mechanism options.php uses: core's options-head.php picks the
     * transient up on the next request (it requires settings-updated in the
     * URL) and prints a native dismissible notice above the page content.
     *
     * @param string $code    Slug-like code for the notice.
     * @param string $message Already-translated message.
     * @param string $type    'success' or 'error'.
     * @return void
     */
    private static function redirect_with_notice( string $code, string $message, string $type ): void {
        add_settings_error( 'paxrank_gf_date_blocker', $code, $message, $type );
        set_transient( 'settings_errors', get_settings_errors(), 30 );

        wp_safe_redirect(
            add_query_arg( 'settings-updated', '1', admin_url( 'options-general.php?page=paxrank-gf-date-blocker' ) )
        );
        exit;
    }

    /**
     * Add a blocked date or range.
     *
     * @return void
     */
    public static function add_blocked_date(): void {
        check_admin_referer( 'paxrank_gf_date_blocker_nonce' );
        self::require_manage_options();

        $start_raw   = isset( $_POST['blocked_date'] ) ? sanitize_text_field( wp_unslash( $_POST['blocked_date'] ) ) : '';
        $end_raw     = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '';
        $form_id     = ! empty( $_POST['gravity_form_id'] ) ? (int) $_POST['gravity_form_id'] : null;
        $field_id    = ! empty( $_POST['date_field_id'] ) ? (int) $_POST['date_field_id'] : null;
        $description = isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '';

        if ( '' === $start_raw ) {
            self::redirect_with_notice( 'blocked-date-missing', __( 'The date is required.', 'date-picker-blocker-for-gravity-forms' ), 'error' );
        }

        $start = DateFormat::normalize( $start_raw );
        if ( false === $start ) {
            self::redirect_with_notice( 'blocked-start-invalid', __( 'The start date is not valid.', 'date-picker-blocker-for-gravity-forms' ), 'error' );
        }

        $end = null;
        if ( '' !== $end_raw ) {
            $end = DateFormat::normalize( $end_raw );
            if ( false === $end ) {
                self::redirect_with_notice( 'blocked-end-invalid', __( 'The end date is not valid.', 'date-picker-blocker-for-gravity-forms' ), 'error' );
            }
            if ( $end < $start ) {
                self::redirect_with_notice( 'blocked-range-order', __( 'The end date must be the same as or later than the start date.', 'date-picker-blocker-for-gravity-forms' ), 'error' );
            }
        }

        $result = SavingBlockedRanges::add( $start, $end, $form_id, $field_id, $description );

        if ( is_wp_error( $result ) ) {
            self::redirect_with_notice( 'blocked-date-error', $result->get_error_message(), 'error' );
        }

        self::redirect_with_notice( 'blocked-date-added', __( 'Blocked date added.', 'date-picker-blocker-for-gravity-forms' ), 'success' );
    }

    /**
     * Delete a blocked date/range (nonced GET link).
     *
     * @return void
     */
    public static function delete_blocked_date(): void {
        $id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified right below; the id is part of the nonce action.
        check_admin_referer( 'paxrank_gf_delete_blocked_date_' . $id );
        self::require_manage_options();

        if ( $id && SavingBlockedRanges::delete( $id ) ) {
            self::redirect_with_notice( 'blocked-date-deleted', __( 'Blocked date deleted.', 'date-picker-blocker-for-gravity-forms' ), 'success' );
        }

        self::redirect_with_notice( 'blocked-date-delete-error', __( 'Could not delete the blocked date.', 'date-picker-blocker-for-gravity-forms' ), 'error' );
    }

    /**
     * Return a form's date fields (for the dependent field selector).
     *
     * @return void
     */
    public static function get_form_fields(): void {
        if ( ! check_ajax_referer( 'paxrank_gf_date_blocker_nonce', 'nonce', false ) ) {
            wp_send_json_error( __( 'Security check failed.', 'date-picker-blocker-for-gravity-forms' ) );
        }

        self::require_manage_options_json();

        $form_id = isset( $_POST['form_id'] ) ? (int) $_POST['form_id'] : 0;
        if ( ! $form_id ) {
            wp_send_json_error( __( 'Invalid form ID.', 'date-picker-blocker-for-gravity-forms' ) );
        }

        wp_send_json_success( GravityForms::get_date_fields( $form_id ) );
    }

    /**
     * Add or update an advance-booking restriction.
     *
     * @return void
     */
    public static function add_advance_restriction(): void {
        check_admin_referer( 'paxrank_gf_date_blocker_nonce' );
        self::require_manage_options();

        $advance_days = isset( $_POST['advance_days'] ) ? (int) $_POST['advance_days'] : 0;
        $form_id      = ! empty( $_POST['gravity_form_id'] ) ? (int) $_POST['gravity_form_id'] : null;
        $field_id     = ! empty( $_POST['date_field_id'] ) ? (int) $_POST['date_field_id'] : null;
        $description  = isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '';

        if ( $advance_days < 0 || $advance_days > 365 ) {
            self::redirect_with_notice( 'advance-days-range', __( 'Advance notice days must be between 0 and 365.', 'date-picker-blocker-for-gravity-forms' ), 'error' );
        }

        $result = SavingAdvanceRestrictions::add( $advance_days, $form_id, $field_id, $description );

        if ( is_wp_error( $result ) ) {
            self::redirect_with_notice( 'advance-error', $result->get_error_message(), 'error' );
        }

        self::redirect_with_notice( 'advance-saved', __( 'Advance notice restriction saved.', 'date-picker-blocker-for-gravity-forms' ), 'success' );
    }

    /**
     * Delete an advance-booking restriction (nonced GET link).
     *
     * @return void
     */
    public static function delete_advance_restriction(): void {
        $id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified right below; the id is part of the nonce action.
        check_admin_referer( 'paxrank_gf_delete_advance_restriction_' . $id );
        self::require_manage_options();

        if ( $id && SavingAdvanceRestrictions::delete( $id ) ) {
            self::redirect_with_notice( 'advance-deleted', __( 'Advance notice restriction deleted.', 'date-picker-blocker-for-gravity-forms' ), 'success' );
        }

        self::redirect_with_notice( 'advance-delete-error', __( 'Could not delete the advance notice restriction.', 'date-picker-blocker-for-gravity-forms' ), 'error' );
    }

    /**
     * Add or update a weekday restriction.
     *
     * @return void
     */
    public static function add_weekday_restriction(): void {
        check_admin_referer( 'paxrank_gf_date_blocker_nonce' );
        self::require_manage_options();

        $weekdays    = isset( $_POST['blocked_weekdays'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['blocked_weekdays'] ) ) : array();
        $weekdays    = array_values( array_unique( array_filter( $weekdays, static fn( $d ) => $d >= 0 && $d <= 6 ) ) );
        $form_id     = ! empty( $_POST['gravity_form_id'] ) ? (int) $_POST['gravity_form_id'] : null;
        $field_id    = ! empty( $_POST['date_field_id'] ) ? (int) $_POST['date_field_id'] : null;
        $description = isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '';

        if ( empty( $weekdays ) ) {
            self::redirect_with_notice( 'weekday-empty', __( 'You must select at least one weekday to block.', 'date-picker-blocker-for-gravity-forms' ), 'error' );
        }

        $result = SavingWeekdayRestrictions::add( $weekdays, $form_id, $field_id, $description );

        if ( is_wp_error( $result ) ) {
            self::redirect_with_notice( 'weekday-error', $result->get_error_message(), 'error' );
        }

        self::redirect_with_notice( 'weekday-saved', __( 'Weekday restriction saved.', 'date-picker-blocker-for-gravity-forms' ), 'success' );
    }

    /**
     * Delete a weekday restriction (nonced GET link).
     *
     * @return void
     */
    public static function delete_weekday_restriction(): void {
        $id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified right below; the id is part of the nonce action.
        check_admin_referer( 'paxrank_gf_delete_weekday_restriction_' . $id );
        self::require_manage_options();

        if ( $id && SavingWeekdayRestrictions::delete( $id ) ) {
            self::redirect_with_notice( 'weekday-deleted', __( 'Weekday restriction deleted.', 'date-picker-blocker-for-gravity-forms' ), 'success' );
        }

        self::redirect_with_notice( 'weekday-delete-error', __( 'Could not delete the weekday restriction.', 'date-picker-blocker-for-gravity-forms' ), 'error' );
    }
}
