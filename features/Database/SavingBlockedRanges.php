<?php
/**
 * Writes to the blocked dates/ranges table.
 *
 * @package Paxrank\DateBlocker
 */

namespace Paxrank\DateBlocker\Database;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Insert/delete helpers for blocked dates/ranges.
 */
class SavingBlockedRanges {

    /**
     * Add a blocked date or range.
     *
     * A single date is stored with end_date NULL. When $end equals $start
     * (or is empty), it is normalized to a single date.
     *
     * @param string      $start       Canonical Y-m-d start date.
     * @param string|null $end         Canonical Y-m-d end date, or null/empty for a single day.
     * @param int|null    $form_id     Gravity Forms form ID (null = global).
     * @param int|null    $field_id    Date field ID (null = all fields).
     * @param string      $description Optional description.
     * @return int|WP_Error Insert ID on success, WP_Error on failure/duplicate.
     */
    public static function add( string $start, ?string $end, ?int $form_id, ?int $field_id, string $description = '' ) {
        global $wpdb;

        if ( '' === (string) $end ) {
            $end = null;
        }

        $store_end = ( null === $end || $end === $start ) ? null : $end;

        // Single atomic statement: the duplicate check and the insert cannot be
        // separated by a concurrent request (the old check-then-insert could
        // double-insert on a double-click). NULLIF maps the plugin's 0/'' null
        // sentinels to SQL NULL, and the NULL-safe <=> makes one WHERE cover
        // all three scopes, mirroring ReadingBlockedRanges::exists().
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name comes from $wpdb->prefix; every value is bound.
        $result = $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$wpdb->prefix}paxrank_gf_blocked_dates
                    (blocked_date, end_date, gravity_form_id, date_field_id, description, user_id)
                 SELECT %s, NULLIF(%s, ''), NULLIF(%d, 0), NULLIF(%d, 0), %s, %d
                 FROM DUAL
                 WHERE NOT EXISTS (
                     SELECT 1 FROM {$wpdb->prefix}paxrank_gf_blocked_dates
                     WHERE blocked_date = %s
                       AND COALESCE(end_date, blocked_date) = %s
                       AND gravity_form_id <=> NULLIF(%d, 0)
                       AND date_field_id <=> NULLIF(%d, 0)
                 )",
                $start,
                (string) $store_end,
                (int) $form_id,
                (int) $field_id,
                $description,
                get_current_user_id(),
                $start,
                $store_end ?? $start,
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
            return new WP_Error(
                'duplicate_entry',
                __( 'This date or range is already blocked for this scope.', 'date-picker-blocker-for-gravity-forms' )
            );
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Delete a blocked date/range by ID.
     *
     * @param int $id Row ID.
     * @return bool
     */
    public static function delete( int $id ): bool {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no core API covers it and nothing is cached.
        return false !== $wpdb->delete(
            RestrictionsTableSchema::blocked_dates_table(),
            array( 'id' => $id ),
            array( '%d' )
        );
    }
}
