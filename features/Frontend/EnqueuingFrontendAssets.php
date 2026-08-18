<?php
/**
 * Enqueues the frontend script/style and ships the restriction payload.
 *
 * Everything happens once, in the footer, and only when the page actually
 * rendered a Gravity Form with date fields — assets included. A page without
 * such a form loads no CSS, no JS and runs no restriction queries. The
 * stylesheet is therefore never render-blocking. Advance notice travels
 * already resolved per field, so the client executes the server's decision
 * instead of re-deriving it.
 *
 * @package Paxrank\DateBlocker
 */

namespace Paxrank\DateBlocker\Frontend;

use Paxrank\DateBlocker\Database\ReadingAdvanceRestrictions;
use Paxrank\DateBlocker\Database\ReadingBlockedRanges;
use Paxrank\DateBlocker\Database\ReadingWeekdayRestrictions;
use Paxrank\DateBlocker\Shared\DateFormat;
use Paxrank\DateBlocker\Shared\GravityForms;
use DateTimeImmutable;
use DateTimeZone;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Frontend asset loader.
 */
class EnqueuingFrontendAssets {

    /**
     * Script/style handle.
     */
    private const HANDLE = 'paxrank-gf-date-blocker';

    /**
     * IDs of the Gravity Forms rendered during this request.
     *
     * @var array<int, int>
     */
    private static array $rendered_forms = array();

    /**
     * Register the enqueue, collector and payload hooks.
     *
     * @return void
     */
    public static function register(): void {
        // Fires once per rendered form, before the footer — that timing is what
        // lets everything below ship only when this page actually needs it.
        add_filter( 'gform_form_tag', array( __CLASS__, 'collect_form' ), 10, 2 );

        // Everything is enqueued from the footer, not wp_enqueue_scripts.
        // Priority 10: wp_print_footer_scripts runs at wp_footer 20, which
        // calls print_late_styles() then print_footer_scripts(), so both the
        // stylesheet and the inline-before payload still make it out.
        add_action( 'wp_footer', array( __CLASS__, 'attach_assets' ), 10 );
    }

    /**
     * Enqueue the assets, from the footer, only when this page rendered a
     * Gravity Form with recognized date fields.
     *
     * Deferring the enqueue to the footer is deliberate. At
     * wp_enqueue_scripts the content has not rendered yet, so there is no way
     * to know whether the page contains a form — every singular page would
     * pay for the assets. By the footer we know, and the stylesheet stops
     * being render-blocking as a bonus: none of its rules target anything
     * that exists at first paint (blocked-day cells, the inline message and
     * the jQuery UI calendar are all created by JS after interaction), so
     * loading it late causes no flash of unstyled content.
     *
     * @return void
     */
    public static function attach_assets(): void {
        if ( ! DeterminingWhereToLoad::should_load() ) {
            return;
        }

        if ( empty( self::$rendered_forms ) || ! GravityForms::is_available() ) {
            return;
        }

        $form_ids = array_values( self::$rendered_forms );
        $forms    = self::date_field_map( $form_ids );

        if ( empty( $forms ) ) {
            return; // Forms on the page, but none with date fields.
        }

        $base    = PAXRANK_GF_DATE_BLOCKER_PLUGIN_URL . 'features/Frontend/assets/';
        $version = PAXRANK_GF_DATE_BLOCKER_VERSION;

        wp_enqueue_style( self::HANDLE, $base . 'css/date-blocker.css', array(), $version );

        wp_enqueue_script(
            self::HANDLE,
            $base . 'js/date-blocker.js',
            array( 'jquery' ),
            $version,
            array( 'in_footer' => true, 'strategy' => 'defer' )
        );

        self::attach_payload( $form_ids, $forms );
    }

    /**
     * Recognized date fields per form, with advance notice already resolved.
     *
     * @param int[] $form_ids Rendered form IDs.
     * @return array<int, array{fields:int[], advance:array<int,int>}>
     */
    private static function date_field_map( array $form_ids ): array {
        $forms = array();

        foreach ( $form_ids as $form_id ) {
            $field_ids = array_keys( GravityForms::get_date_fields( $form_id ) );

            if ( empty( $field_ids ) ) {
                continue;
            }

            $advance = array();
            foreach ( $field_ids as $field_id ) {
                $advance[ $field_id ] = ReadingAdvanceRestrictions::effective_days( $form_id, $field_id );
            }

            $forms[ $form_id ] = array(
                'fields'  => $field_ids,
                'advance' => $advance,
            );
        }

        return $forms;
    }

    /**
     * Record a rendered form's ID; the tag itself is returned untouched.
     *
     * @param string $form_tag The <form> tag markup.
     * @param array  $form     Gravity Forms form array.
     * @return string
     */
    public static function collect_form( $form_tag, $form ) {
        if ( is_array( $form ) && ! empty( $form['id'] ) ) {
            self::$rendered_forms[ (int) $form['id'] ] = (int) $form['id'];
        }

        return $form_tag;
    }

    /**
     * Build and attach the payload for the forms this page rendered.
     *
     * Called only once the caller has confirmed there is something to ship,
     * so the restriction queries never run on a page without a date field.
     *
     * @param int[]                                                     $form_ids Rendered form IDs.
     * @param array<int, array{fields:int[], advance:array<int,int>}>   $forms    Field map per form.
     * @return void
     */
    private static function attach_payload( array $form_ids, array $forms ): void {
        $payload = array(
            // Site-timezone offset in seconds: the client derives the site's
            // "today" from its own UTC clock, so business hours stay the
            // reference no matter where the visitor is.
            'siteOffset'          => wp_timezone()->getOffset( new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) ),
            'forms'               => $forms,
            'blockedRanges'       => ReadingBlockedRanges::for_js( $form_ids ),
            'weekdayRestrictions' => ReadingWeekdayRestrictions::for_js( $form_ids ),
            'dateFormat'          => DateFormat::get_display_format(),
            'i18n'                => self::messages(),
        );

        // Single emission. The JSON_HEX_* flags escape <, >, &, ', " so the
        // payload can never break out of the inline <script>.
        wp_add_inline_script(
            self::HANDLE,
            'window.paxrankGFBlocker = ' . wp_json_encode( $payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';',
            'before'
        );
    }

    /**
     * Translated strings for the frontend script.
     *
     * Same msgids the Rules classes use, so the client-side hint and the
     * authoritative server-side message always read identically. The plural
     * pair is resolved here for both counts, because the actual number is
     * only known in the browser.
     *
     * @return array<string, string|string[]>
     */
    private static function messages(): array {
        return array(
            'unavailable'    => __( 'The selected date is unavailable. Please choose another date.', 'date-picker-blocker-for-gravity-forms' ),
            'pastDate'       => __( 'You cannot select dates in the past. Please choose a future date.', 'date-picker-blocker-for-gravity-forms' ),
            /* translators: %s: name of a day of the week. */
            'weekdayBlocked' => __( '%s is not available for bookings. Please choose another day of the week.', 'date-picker-blocker-for-gravity-forms' ),
            'weekdays'       => array(
                __( 'Sunday', 'date-picker-blocker-for-gravity-forms' ),
                __( 'Monday', 'date-picker-blocker-for-gravity-forms' ),
                __( 'Tuesday', 'date-picker-blocker-for-gravity-forms' ),
                __( 'Wednesday', 'date-picker-blocker-for-gravity-forms' ),
                __( 'Thursday', 'date-picker-blocker-for-gravity-forms' ),
                __( 'Friday', 'date-picker-blocker-for-gravity-forms' ),
                __( 'Saturday', 'date-picker-blocker-for-gravity-forms' ),
            ),
            /* translators: %d: number of days of advance notice required. */
            'advanceOne'     => _n(
                'You must book at least %d day in advance. Please choose a later date.',
                'You must book at least %d days in advance. Please choose a later date.',
                1,
                'date-picker-blocker-for-gravity-forms'
            ),
            /* translators: %d: number of days of advance notice required. */
            'advanceMany'    => _n(
                'You must book at least %d day in advance. Please choose a later date.',
                'You must book at least %d days in advance. Please choose a later date.',
                2,
                'date-picker-blocker-for-gravity-forms'
            ),
        );
    }
}
