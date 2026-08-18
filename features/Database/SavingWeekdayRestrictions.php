<?php
/**
 * Writes to the weekday restrictions table.
 *
 * Upserts by scope: one restriction per (form, field) combination.
 *
 * @package Paxrank\DateBlocker
 */

namespace Paxrank\DateBlocker\Database;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Insert/update/delete helpers for weekday restrictions.
 */
class SavingWeekdayRestrictions {

    /**
     * Add or update a weekday restriction for a scope.
     *
     * @param int[]    $weekdays    Weekday numbers (0 = Sunday .. 6 = Saturday).
     * @param int|null $form_id     Gravity Forms form ID (null = global).
     * @param int|null $field_id    Date field ID (null = all fields).
     * @param string   $description Optional description.
     * @return int|WP_Error Row ID on success, WP_Error on failure.
     */
    public static function add( array $weekdays, ?int $form_id, ?int $field_id, string $description = '' ) {
        global $wpdb;

        $weekdays_json = wp_json_encode( array_map( 'intval', $weekdays ) );
        $existing_id   = self::existing_id( $form_id, $field_id );

        if ( $existing_id ) {
            return self::update_existing( $existing_id, $weekdays_json, $description );
        }

        // Atomic insert-unless-scope-exists: if a concurrent request created
        // the scope between the check above and here, zero rows are written
        // and we update that row instead — one-row-per-scope always holds.
        // NULLIF maps the 0 sentinel to SQL NULL; <=> is the NULL-safe equal.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name comes from $wpdb->prefix; every value is bound.
        $result = $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$wpdb->prefix}paxrank_gf_weekday_restrictions
                    (gravity_form_id, date_field_id, blocked_weekdays, description, user_id)
                 SELECT NULLIF(%d, 0), NULLIF(%d, 0), %s, %s, %d
                 FROM DUAL
                 WHERE NOT EXISTS (
                     SELECT 1 FROM {$wpdb->prefix}paxrank_gf_weekday_restrictions
                     WHERE gravity_form_id <=> NULLIF(%d, 0)
                       AND date_field_id <=> NULLIF(%d, 0)
                 )",
                (int) $form_id,
                (int) $field_id,
                $weekdays_json,
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
                return self::update_existing( $existing_id, $weekdays_json, $description );
            }

            return new WP_Error( 'database_error', __( 'Could not update the weekday restriction.', 'date-picker-blocker-for-gravity-forms' ) );
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Update an existing restriction row in place.
     *
     * @param int    $id            Row ID.
     * @param string $weekdays_json JSON-encoded weekday numbers.
     * @param string $description   Optional description.
     * @return int|WP_Error Row ID on success, WP_Error on failure.
     */
    private static function update_existing( int $id, string $weekdays_json, string $description ) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no core API covers it and nothing is cached.
        $result = $wpdb->update(
            RestrictionsTableSchema::weekday_table(),
            array(
                'blocked_weekdays' => $weekdays_json,
                'description'      => $description,
                'user_id'          => get_current_user_id(),
            ),
            array( 'id' => $id ),
            array( '%s', '%s', '%d' ),
            array( '%d' )
        );

        return false !== $result
            ? $id
            : new WP_Error( 'database_error', __( 'Could not update the weekday restriction.', 'date-picker-blocker-for-gravity-forms' ) );
    }

    /**
     * Delete a weekday restriction by ID.
     *
     * @param int $id Row ID.
     * @return bool
     */
    public static function delete( int $id ): bool {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no core API covers it and nothing is cached.
        return false !== $wpdb->delete(
            RestrictionsTableSchema::weekday_table(),
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

        $table = RestrictionsTableSchema::weekday_table();

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
