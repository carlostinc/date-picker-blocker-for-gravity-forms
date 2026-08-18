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
     * Whether Gravity Forms is available.
     *
     * @return bool
     */
    public static function is_available(): bool {
        return class_exists( 'GFForms' ) && class_exists( 'GFAPI' );
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
