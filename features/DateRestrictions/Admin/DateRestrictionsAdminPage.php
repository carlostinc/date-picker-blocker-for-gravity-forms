<?php
/**
 * Admin menu page for managing date restrictions.
 *
 * Registers the menu + assets and renders the page from section templates
 * (no inline HTML/CSS beyond the small view helpers below).
 *
 * @package Paxrank\DateBlocker
 */

namespace Paxrank\DateBlocker\DateRestrictions\Admin;

use Paxrank\DateBlocker\DateRestrictions\Database\ReadingAdvanceRestrictions;
use Paxrank\DateBlocker\DateRestrictions\Database\ReadingBlockedRanges;
use Paxrank\DateBlocker\DateRestrictions\Database\ReadingWeekdayRestrictions;
use Paxrank\DateBlocker\Shared\DateFormat;
use Paxrank\DateBlocker\Shared\GravityForms;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The "Date Blocker" admin screen.
 */
class DateRestrictionsAdminPage {

    /**
     * Menu slug (stable — also used to gate asset loading).
     */
    public const SLUG = 'paxrank-gf-date-blocker';

    /**
     * Nonce action (stable — shared with the AJAX handlers).
     */
    public const NONCE = 'paxrank_gf_date_blocker_nonce';

    /**
     * Register admin hooks.
     *
     * @return void
     */
    public static function register(): void {
        add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
        add_filter(
            'plugin_action_links_' . PAXRANK_GF_DATE_BLOCKER_PLUGIN_BASENAME,
            array( __CLASS__, 'action_links' )
        );
    }

    /**
     * Register the screen as a submenu of the native Settings menu.
     *
     * No position argument: appended to the end of the Settings submenu,
     * where plugin screens conventionally sit.
     *
     * @return void
     */
    public static function add_menu(): void {
        add_submenu_page(
            'options-general.php',
            __( 'Date Picker Blocker for Gravity Forms', 'date-picker-blocker-for-gravity-forms' ),
            __( 'Date Blocker', 'date-picker-blocker-for-gravity-forms' ),
            'manage_options',
            self::SLUG,
            array( __CLASS__, 'render' )
        );
    }

    /**
     * Add a Settings shortcut to the plugin's row on the Plugins screen.
     *
     * Compensates for the screen no longer having a top-level menu entry.
     *
     * @param string[] $links Existing action links.
     * @return string[]
     */
    public static function action_links( array $links ): array {
        $settings = sprintf(
            '<a href="%s">%s</a>',
            esc_url( admin_url( 'options-general.php?page=' . self::SLUG ) ),
            esc_html__( 'Settings', 'date-picker-blocker-for-gravity-forms' )
        );

        array_unshift( $links, $settings );

        return $links;
    }

    /**
     * Enqueue admin assets on this plugin's screen only.
     *
     * @param string $hook_suffix Current admin page hook.
     * @return void
     */
    public static function enqueue( $hook_suffix ): void {
        // Substring match, not equality: the hook is settings_page_<slug> now
        // that the screen is a Settings submenu.
        if ( false === strpos( (string) $hook_suffix, self::SLUG ) ) {
            return;
        }

        $base = PAXRANK_GF_DATE_BLOCKER_PLUGIN_URL . 'features/DateRestrictions/Admin/assets/';

        // wp-admin already loads dashicons, but the screen's icons depend on
        // it, so declare it rather than rely on that.
        wp_enqueue_style( 'paxrank-gf-date-blocker-admin', $base . 'css/admin.css', array( 'dashicons' ), PAXRANK_GF_DATE_BLOCKER_VERSION );

        wp_enqueue_script( 'paxrank-gf-date-blocker-admin', $base . 'js/admin.js', array( 'jquery' ), PAXRANK_GF_DATE_BLOCKER_VERSION, true );

        wp_localize_script(
            'paxrank-gf-date-blocker-admin',
            'paxrankGFAdmin',
            array(
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( self::NONCE ),
                'i18n'    => self::messages(),
            )
        );
    }

    /**
     * Translated strings for the field-selector AJAX in the admin script.
     *
     * Everything else on the screen is native forms now, so this is the
     * only client-side text left.
     *
     * @return array<string, string>
     */
    private static function messages(): array {
        return array(
            'allDateFields'   => __( 'All date fields', 'date-picker-blocker-for-gravity-forms' ),
            'loadingFields'   => __( 'Loading fields...', 'date-picker-blocker-for-gravity-forms' ),
            'noDateFields'    => __( 'There are no date fields on this form', 'date-picker-blocker-for-gravity-forms' ),
            'loadFieldsError' => __( 'Could not load fields', 'date-picker-blocker-for-gravity-forms' ),
            'connectionError' => __( 'Connection error', 'date-picker-blocker-for-gravity-forms' ),
        );
    }

    /**
     * Render the settings page from section templates.
     *
     * @return void
     */
    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'date-picker-blocker-for-gravity-forms' ) );
        }

        $forms        = GravityForms::get_forms();
        $gf_available = GravityForms::is_available();
        $blocked      = ReadingBlockedRanges::all();
        $advance      = ReadingAdvanceRestrictions::all();
        $weekday      = ReadingWeekdayRestrictions::all();

        $templates = __DIR__ . '/Templates/';

        echo '<div class="wrap"><div class="paxrank-dashboard">';

        /*
         * Anchor for WordPress's notice relocation (common.js moves every
         * div.notice to just after .wp-header-end on DOM ready). Kept above the
         * header so notices land where PHP already painted them — otherwise
         * they visibly jump down past the header once the script runs.
         *
         * It must stay inside .paxrank-dashboard: as a direct child of .wrap a
         * notice would lose the container's max-width and render wider than the
         * sections below it. It also cannot be dropped, or the relocation falls
         * back to `.wrap h1` and lands notices inside the header's flex row.
         *
         * No settings_errors() call here on purpose: this screen hangs off
         * options-general.php, so core already prints the PRG feedback for us
         * from wp-admin/options-head.php. Calling it again would print every
         * message twice. Restore the call if this menu ever moves out of
         * Settings.
         */
        echo '<hr class="wp-header-end">';

        require $templates . 'page-header.php';
        require $templates . 'section-settings.php';
        require $templates . 'section-blocked-ranges.php';
        require $templates . 'section-advance.php';
        require $templates . 'section-weekday.php';
        require $templates . 'section-advanced-options.php';
        require $templates . 'section-uninstall.php';
        echo '</div></div>';
    }

    /**
     * Render a scope badge (global / per-form / per-field).
     *
     * @param int|string|null $form_id  Form ID or null.
     * @param int|string|null $field_id Field ID or null.
     * @param array<int,string> $forms  Forms id => title map.
     * @return string HTML.
     */
    public static function scope_badge( $form_id, $field_id, array $forms ): string {
        if ( is_null( $form_id ) && is_null( $field_id ) ) {
            return '<span class="paxrank-badge paxrank-badge--global"><span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span>'
                . esc_html__( 'Global', 'date-picker-blocker-for-gravity-forms' ) . '</span>';
        }

        $title = $forms[ (int) $form_id ] ?? sprintf(
            /* translators: %d: Gravity Forms form ID, shown when the form title is unavailable. */
            __( 'Form ID %d', 'date-picker-blocker-for-gravity-forms' ),
            (int) $form_id
        );

        if ( is_null( $field_id ) ) {
            return '<span class="paxrank-badge paxrank-badge--form"><span class="dashicons dashicons-feedback" aria-hidden="true"></span>'
                . esc_html( $title ) . '</span>';
        }

        /* translators: %d: Gravity Forms date field ID. */
        $field_label = sprintf( esc_html__( 'Field %d', 'date-picker-blocker-for-gravity-forms' ), (int) $field_id );

        return '<span class="paxrank-badge paxrank-badge--field"><span class="dashicons dashicons-location" aria-hidden="true"></span>'
            . esc_html( $title ) . ' — ' . $field_label . '</span>';
    }

    /**
     * Human-readable label for a blocked date/range row.
     *
     * @param object $row Row with blocked_date and end_date.
     * @return string
     */
    public static function range_label( object $row ): string {
        $start = DateFormat::for_display( $row->blocked_date );

        if ( empty( $row->end_date ) || $row->end_date === $row->blocked_date ) {
            return $start;
        }

        return $start . ' → ' . DateFormat::for_display( $row->end_date );
    }

    /**
     * Localized short weekday names, indexed 0 (Sun) .. 6 (Sat).
     *
     * @return string[]
     */
    public static function weekday_short_names(): array {
        return array(
            __( 'Sun', 'date-picker-blocker-for-gravity-forms' ),
            __( 'Mon', 'date-picker-blocker-for-gravity-forms' ),
            __( 'Tue', 'date-picker-blocker-for-gravity-forms' ),
            __( 'Wed', 'date-picker-blocker-for-gravity-forms' ),
            __( 'Thu', 'date-picker-blocker-for-gravity-forms' ),
            __( 'Fri', 'date-picker-blocker-for-gravity-forms' ),
            __( 'Sat', 'date-picker-blocker-for-gravity-forms' ),
        );
    }
}
