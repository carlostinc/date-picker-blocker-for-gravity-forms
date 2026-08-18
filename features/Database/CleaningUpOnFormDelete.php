<?php
/**
 * Deletes a form's (or field's) restrictions when it is permanently deleted.
 *
 * Both Gravity Forms hooks fire only on permanent deletion, so trashing and
 * restoring a form keeps its restrictions — which is the desired behavior.
 *
 * @package Paxrank\DateBlocker
 */

namespace Paxrank\DateBlocker\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Orphan-row cleanup for deleted Gravity Forms forms and fields.
 */
class CleaningUpOnFormDelete {

    /**
     * Register the Gravity Forms deletion hooks.
     *
     * @return void
     */
    public static function register(): void {
        add_action( 'gform_after_delete_form', array( __CLASS__, 'on_form_deleted' ) );
        add_action( 'gform_after_delete_field', array( __CLASS__, 'on_field_deleted' ), 10, 2 );
    }

    /**
     * Remove every restriction scoped to a deleted form.
     *
     * @param int $form_id Gravity Forms form ID.
     * @return void
     */
    public static function on_form_deleted( $form_id ): void {
        global $wpdb;

        $form_id = (int) $form_id;

        if ( ! $form_id ) {
            return;
        }

        foreach ( RestrictionsTableSchema::all_tables() as $table ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no core API covers it and nothing is cached.
            $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE gravity_form_id = %d', $table, $form_id ) );
        }
    }

    /**
     * Remove every restriction scoped to a deleted field.
     *
     * @param int $form_id  Gravity Forms form ID.
     * @param int $field_id Deleted field ID.
     * @return void
     */
    public static function on_field_deleted( $form_id, $field_id ): void {
        global $wpdb;

        $form_id  = (int) $form_id;
        $field_id = (int) $field_id;

        if ( ! $form_id || ! $field_id ) {
            return;
        }

        foreach ( RestrictionsTableSchema::all_tables() as $table ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no core API covers it and nothing is cached.
            $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE gravity_form_id = %d AND date_field_id = %d', $table, $form_id, $field_id ) );
        }
    }
}
