<?php
/**
 * Writes to the advance-booking restrictions table.
 *
 * Upserts by scope: one restriction per (form, field) combination.
 *
 * @package Paxrank\DateBlocker
 */

namespace Paxrank\DateBlocker\DateRestrictions\Database;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Insert/update/delete helpers for advance-booking restrictions.
 */
class SavingAdvanceRestrictions {

    /**
     * Add or update an advance-booking restriction for a scope.
     *
     * @param int      $advance_days Days of required advance notice.
     * @param int|null $form_id      Gravity Forms form ID (null = global).
     * @param int|null $field_id     Date field ID (null = all fields).
     * @param string   $description  Optional description.
     * @return int|WP_Error Row ID on success, WP_Error on failure.
     */
    public static function add( int $advance_days, ?int $form_id, ?int $field_id, string $description = '' ) {
        global $wpdb;

        $existing_id = self::existing_id( $form_id, $field_id );

        if ( $existing_id ) {
            return self::update_existing( $existing_id, $advance_days, $description );
        }

        // Atomic insert-unless-scope-exists: if a concurrent request created
        // the scope between the check above and here, zero rows are written
        // and we update that row instead — one-row-per-scope always holds.
        // NULLIF maps the 0 sentinel to SQL NULL; <=> is the NULL-safe equal.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name comes from $wpdb->prefix; every value is bound.
        $result = $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$wpdb->prefix}paxrank_gf_advance_restrictions
                    (gravity_form_id, date_field_id, advance_days, description, user_id)
                 SELECT NULLIF(%d, 0), NULLIF(%d, 0), %d, %s, %d
                 FROM DUAL
                 WHERE NOT EXISTS (
                     SELECT 1 FROM {$wpdb->prefix}paxrank_gf_advance_restrictions
                     WHERE gravity_form_id <=> NULLIF(%d, 0)
                       AND date_field_id <=> NULLIF(%d, 0)
                 )",
                (int) $form_id,
                (int) $field_id,
                $advance_days,
                $description,
                get_current_user_id(),
                (int) $form_id,
                (int) $field_id
            )
        );

        if ( false === $result ) {
            return new WP_Error(
                'database_error',
                sprintf(
                    /* translators: %s: error reported by the database. */
                    __( 'Database error: %s', 'date-picker-blocker-for-gravity-forms' ),
                    $wpdb->last_error
                )
            );
        }

        if ( 0 === $result ) {
            $existing_id = self::existing_id( $form_id, $field_id );

            if ( $existing_id ) {
                return self::update_existing( $existing_id, $advance_days, $description );
            }

            return new WP_Error( 'database_error', __( 'Could not update the restriction.', 'date-picker-blocker-for-gravity-forms' ) );
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Update an existing restriction row in place.
     *
     * @param int    $id           Row ID.
     * @param int    $advance_days Days of required advance notice.
     * @param string $description  Optional description.
     * @return int|WP_Error Row ID on success, WP_Error on failure.
     */
    private static function update_existing( int $id, int $advance_days, string $description ) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no core API covers it and nothing is cached.
        $result = $wpdb->update(
            RestrictionsTableSchema::advance_table(),
            array(
                'advance_days' => $advance_days,
                'description'  => $description,
                'user_id'      => get_current_user_id(),
            ),
            array( 'id' => $id ),
            array( '%d', '%s', '%d' ),
            array( '%d' )
        );

        return false !== $result
            ? $id
            : new WP_Error( 'database_error', __( 'Could not update the restriction.', 'date-picker-blocker-for-gravity-forms' ) );
    }

    /**
     * Delete an advance restriction by ID.
     *
     * @param int $id Row ID.
     * @return bool
     */
    public static function delete( int $id ): bool {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no core API covers it and nothing is cached.
        return false !== $wpdb->delete(
            RestrictionsTableSchema::advance_table(),
            array( 'id' => $id ),
            array( '%d' )
        );
    }

    /**
     * Find the existing restriction ID for a scope, if any.
     *
     * @param int|null $form_id  Gravity Forms form ID.
     * @param int|null $field_id Date field ID.
     * @return int|null
     */
    private static function existing_id( ?int $form_id, ?int $field_id ): ?int {
        global $wpdb;

        $table = RestrictionsTableSchema::advance_table();

        if ( $form_id && $field_id ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no core API covers it and nothing is cached.
            $id = $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT id FROM %i WHERE gravity_form_id = %d AND date_field_id = %d ORDER BY id LIMIT 1',
                    $table,
                    $form_id,
                    $field_id
                )
            );
        } elseif ( $form_id ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no core API covers it and nothing is cached.
            $id = $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT id FROM %i WHERE gravity_form_id = %d AND date_field_id IS NULL ORDER BY id LIMIT 1',
                    $table,
                    $form_id
                )
            );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no core API covers it and nothing is cached.
            $id = $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT id FROM %i WHERE gravity_form_id IS NULL AND date_field_id IS NULL ORDER BY id LIMIT 1',
                    $table
                )
            );
        }

        return $id ? (int) $id : null;
    }
}
