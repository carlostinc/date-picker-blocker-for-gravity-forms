<?php
/**
 * Thin wrappers around the Gravity Forms API.
 *
 * Keeps every GFAPI call behind one place, with graceful fallbacks when
 * Gravity Forms is not active.
 *
 * @package Paxrank\DateBlocker
 */

namespace Paxrank\DateBlocker\Shared;

use GFAPI;
use GFForms;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Read-only helpers for enumerating Gravity Forms and their date fields.
 */
class GravityForms {

    /**
     * Gravity Forms date-field `dateFormat` token => PHP date() format for the
     * single-input (datepicker/text) value.
     *
     * @var array<string, string>
     */
    private const GF_PHP_FORMATS = array(
        'mdy'       => 'm/d/Y',
        'dmy'       => 'd/m/Y',
        'dmy_dash'  => 'd-m-Y',
        'dmy_dot'   => 'd.m.Y',
        'ymd_slash' => 'Y/m/d',
        'ymd_dash'  => 'Y-m-d',
        'ymd_dot'   => 'Y.m.d',
    );

    /**
     * Gravity Forms date-field `dateFormat` token => order of the three
     * sub-inputs (`_1`, `_2`, `_3`) for the dropdown/date-field date type.
     * Values are 'y' | 'm' | 'd'.
     *
     * @var array<string, array{0:string,1:string,2:string}>
     */
    private const GF_SUBINPUT_ORDER = array(
        'mdy'       => array( 'm', 'd', 'y' ),
        'dmy'       => array( 'd', 'm', 'y' ),
        'dmy_dash'  => array( 'd', 'm', 'y' ),
        'dmy_dot'   => array( 'd', 'm', 'y' ),
        'ymd_slash' => array( 'y', 'm', 'd' ),
        'ymd_dash'  => array( 'y', 'm', 'd' ),
        'ymd_dot'   => array( 'y', 'm', 'd' ),
    );

    /**
     * Whether Gravity Forms is available.
     *
     * @return bool
     */
    public static function is_available(): bool {
        return class_exists( 'GFForms' ) && class_exists( 'GFAPI' );
    }

    /**
     * The `dateFormat` token of a GF date field (defaults to GF's own 'mdy').
     *
     * @param object $field Gravity Forms field.
     * @return string
     */
    public static function field_date_format( $field ): string {
        $token = ( is_object( $field ) && ! empty( $field->dateFormat ) ) ? (string) $field->dateFormat : 'mdy';

        return isset( self::GF_PHP_FORMATS[ $token ] ) ? $token : 'mdy';
    }

    /**
     * PHP date() format for a field's single-input value.
     *
     * @param string $token GF dateFormat token.
     * @return string
     */
    public static function php_format_for( string $token ): string {
        return self::GF_PHP_FORMATS[ $token ] ?? 'm/d/Y';
    }

    /**
     * Sub-input order ('y'|'m'|'d') for a field's dropdown date type.
     *
     * @param string $token GF dateFormat token.
     * @return array{0:string,1:string,2:string}
     */
    public static function subinput_order_for( string $token ): array {
        return self::GF_SUBINPUT_ORDER[ $token ] ?? array( 'm', 'd', 'y' );
    }

    /**
     * Get all forms as an id => title map.
     *
     * @return array<int, string>
     */
    public static function get_forms(): array {
        if ( ! self::is_available() ) {
            return array();
        }

        $options = array();

        foreach ( GFAPI::get_forms() as $form ) {
            $options[ (int) $form['id'] ] = $form['title'];
        }

        return $options;
    }

    /**
     * Get the date fields of a form as an id => label map.
     *
     * @param int $form_id Gravity Forms form ID.
     * @return array<int, string>
     */
    public static function get_date_fields( int $form_id ): array {
        if ( ! self::is_available() ) {
            return array();
        }

        $form = GFAPI::get_form( $form_id );

        if ( ! $form ) {
            return array();
        }

        $date_fields = array();

        foreach ( $form['fields'] as $field ) {
            // get_input_type() falls back to type, so plain Date fields still
            // match; this additionally reaches fields whose inputType is date
            // (e.g. a Post Custom Field presented as a date picker).
            if ( 'date' === $field->get_input_type() ) {
                $date_fields[ (int) $field->id ] = ! empty( $field->label )
                    ? $field->label
                    /* translators: %d: date field ID. */
                    : sprintf( __( 'Date Field #%d', 'date-picker-blocker-for-gravity-forms' ), $field->id );
            }
        }

        return $date_fields;
    }
}
