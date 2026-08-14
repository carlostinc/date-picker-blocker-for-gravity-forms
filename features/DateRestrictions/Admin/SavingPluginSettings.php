<?php
/**
 * Admin form handlers (admin-post) for the global plugin settings.
 *
 * Covers the date format, enabled post types, and delete-on-uninstall toggle.
 * Each save follows the native POST → redirect → notice cycle.
 *
 * @package Paxrank\DateBlocker
 */

namespace Paxrank\DateBlocker\DateRestrictions\Admin;

use Paxrank\DateBlocker\Shared\DateFormat;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Persists the plugin's global settings via admin-post.
 */
class SavingPluginSettings {

    /**
     * Register the settings admin-post actions.
     *
     * @return void
     */
    public static function register(): void {
        add_action( 'admin_post_paxrank_gf_save_date_format', array( __CLASS__, 'save_date_format' ) );
        add_action( 'admin_post_paxrank_gf_save_post_types', array( __CLASS__, 'save_post_types' ) );
        add_action( 'admin_post_paxrank_gf_save_uninstall_option', array( __CLASS__, 'save_uninstall_option' ) );
    }

    /**
     * Require the settings capability, or die (native).
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
     * Stash a notice in core's settings-errors transient and redirect back.
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
     * Save the global date format.
     *
     * @return void
     */
    public static function save_date_format(): void {
        check_admin_referer( 'paxrank_gf_date_blocker_nonce' );
        self::require_manage_options();

        $format = isset( $_POST['date_format'] ) ? sanitize_text_field( wp_unslash( $_POST['date_format'] ) ) : '';

        if ( ! in_array( $format, DateFormat::allowed_formats(), true ) ) {
            self::redirect_with_notice( 'date-format-invalid', __( 'Invalid date format.', 'date-picker-blocker-for-gravity-forms' ), 'error' );
        }

        update_option( 'paxrank_gf_date_blocker_date_format', $format );

        self::redirect_with_notice( 'date-format-saved', __( 'Date format saved. All new validation will use this format.', 'date-picker-blocker-for-gravity-forms' ), 'success' );
    }

    /**
     * Save the enabled post types.
     *
     * @return void
     */
    public static function save_post_types(): void {
        check_admin_referer( 'paxrank_gf_date_blocker_nonce' );
        self::require_manage_options();

        $types = isset( $_POST['enabled_post_types'] )
            ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['enabled_post_types'] ) )
            : array();

        // Persist only registered public post types.
        $types = array_values( array_intersect( $types, get_post_types( array( 'public' => true ) ) ) );

        update_option( 'paxrank_gf_date_blocker_enabled_post_types', $types );

        self::redirect_with_notice( 'post-types-saved', __( 'Content type settings saved.', 'date-picker-blocker-for-gravity-forms' ), 'success' );
    }

    /**
     * Save the delete-data-on-uninstall toggle.
     *
     * An unchecked checkbox is simply absent from a native POST, so presence
     * is the whole signal.
     *
     * @return void
     */
    public static function save_uninstall_option(): void {
        check_admin_referer( 'paxrank_gf_date_blocker_nonce' );
        self::require_manage_options();

        $delete = ! empty( $_POST['delete_on_uninstall'] );

        update_option( 'paxrank_gf_date_blocker_delete_on_uninstall', $delete );

        self::redirect_with_notice(
            'uninstall-option-saved',
            $delete
                ? __( 'Settings saved: data will be deleted when the plugin is uninstalled.', 'date-picker-blocker-for-gravity-forms' )
                : __( 'Settings saved: data will be kept when the plugin is uninstalled.', 'date-picker-blocker-for-gravity-forms' ),
            'success'
        );
    }
}
