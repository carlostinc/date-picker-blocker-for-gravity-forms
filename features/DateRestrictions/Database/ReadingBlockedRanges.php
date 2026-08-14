<?php
/**
 * Reads from the blocked dates/ranges table.
 *
 * A row is a single date when end_date IS NULL, or a closed range
 * [blocked_date, end_date] otherwise.
 *
 * @package Paxrank\DateBlocker
 */

namespace Paxrank\DateBlocker\DateRestrictions\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Query helpers for blocked dates/ranges.
 */
class ReadingBlockedRanges {

    /**
     * Get blocked ranges for the admin list (joined with the author name).
     *
     * @param int $limit  Max rows.
     * @param int $offset  Offset.
     * @return object[]
     */
    public static function all( int $limit = 50, int $offset = 0 ): array {
        global $wpdb;

        $table = RestrictionsTableSchema::blocked_dates_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no core API covers it and nothing is cached.
        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT bd.*, u.display_name AS user_name
                 FROM %i bd
                 LEFT JOIN %i u ON bd.user_id = u.ID
                 ORDER BY bd.blocked_date DESC
                 LIMIT %d OFFSET %d',
                $table,
                $wpdb->users,
                $limit,
                $offset
            )
        );
    }

    /**
     * Total number of blocked date/range rows.
     *
     * @return int
     */
    public static function count(): int {
        global $wpdb;

        $table = RestrictionsTableSchema::blocked_dates_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no core API covers it and nothing is cached.
        return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
    }

    /**
     * Get every range as a flat array for the frontend payload.
     *
     * @param int[]|null $form_ids Limit form-scoped rows to these forms
     *                             (global rows always ship). Null = no filter.
     * @return array<int, array{start:string,end:string,form_id:?int,field_id:?int}>
     */
    public static function for_js( ?array $form_ids = null ): array {
        global $wpdb;

        $table = RestrictionsTableSchema::blocked_dates_table();
        $scope = RestrictionsTableSchema::form_scope_csv( $form_ids );

        if ( null === $scope ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no core API covers it and nothing is cached.
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT blocked_date, end_date, gravity_form_id, date_field_id
                     FROM %i
                     ORDER BY blocked_date ASC',
                    $table
                )
            );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no core API covers it and nothing is cached.
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT blocked_date, end_date, gravity_form_id, date_field_id
                     FROM %i
                     WHERE gravity_form_id IS NULL OR FIND_IN_SET( gravity_form_id, %s )
                     ORDER BY blocked_date ASC',
                    $table,
                    $scope
                )
            );
        }

        $ranges = array();

        foreach ( $rows as $row ) {
            $ranges[] = array(
                'start'    => $row->blocked_date,
                'end'      => $row->end_date ?: $row->blocked_date,
                'form_id'  => null !== $row->gravity_form_id ? (int) $row->gravity_form_id : null,
                'field_id' => null !== $row->date_field_id ? (int) $row->date_field_id : null,
            );
        }

        return $ranges;
    }

    /**
     * Whether a given date falls within a blocked range for the scope.
     *
     * @param string   $date     Canonical Y-m-d date.
     * @param int|null $form_id  Gravity Forms form ID (null = unknown context).
     * @param int|null $field_id Date field ID.
     * @return bool
     */
    public static function is_blocked( string $date, ?int $form_id = null, ?int $field_id = null ): bool {
        global $wpdb;

        // Each scope gets its own literal query so every placeholder is visible
        // to static analysis. The narrower scopes are cumulative: a field-scoped
        // lookup also matches its form's and the global rows.
        if ( $form_id && $field_id ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name comes from $wpdb->prefix, not input.
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}paxrank_gf_blocked_dates
                     WHERE %s BETWEEN blocked_date AND COALESCE(end_date, blocked_date)
                     AND ( (gravity_form_id IS NULL AND date_field_id IS NULL)
                        OR (gravity_form_id = %d AND date_field_id IS NULL)
                        OR (gravity_form_id = %d AND date_field_id = %d) )",
                    $date,
                    $form_id,
                    $form_id,
                    $field_id
                )
            );
        } elseif ( $form_id ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name comes from $wpdb->prefix, not input.
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}paxrank_gf_blocked_dates
                     WHERE %s BETWEEN blocked_date AND COALESCE(end_date, blocked_date)
                     AND ( (gravity_form_id IS NULL AND date_field_id IS NULL)
                        OR (gravity_form_id = %d AND date_field_id IS NULL) )",
                    $date,
                    $form_id
                )
            );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name comes from $wpdb->prefix, not input.
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}paxrank_gf_blocked_dates
                     WHERE %s BETWEEN blocked_date AND COALESCE(end_date, blocked_date)
                     AND ( gravity_form_id IS NULL AND date_field_id IS NULL )",
                    $date
                )
            );
        }

        return (int) $count > 0;
    }

    /**
     * Whether an identical date/range already exists for the same scope.
     *
     * @param string      $start    Canonical Y-m-d start date.
     * @param string|null $end      Canonical Y-m-d end date, or null for single day.
     * @param int|null    $form_id  Gravity Forms form ID.
     * @param int|null    $field_id Date field ID.
     * @return bool
     */
    public static function exists( string $start, ?string $end, ?int $form_id, ?int $field_id ): bool {
        global $wpdb;

        // A null end matches a single-day row through COALESCE, so it collapses
        // to the start date. One literal query per scope, mirroring the shape of
        // existing_id() in the Saving* classes.
        $stored_end = $end ?? $start;

        if ( $form_id && $field_id ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name comes from $wpdb->prefix, not input.
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}paxrank_gf_blocked_dates
                     WHERE blocked_date = %s AND COALESCE(end_date, blocked_date) = %s
                     AND gravity_form_id = %d AND date_field_id = %d",
                    $start,
                    $stored_end,
                    $form_id,
                    $field_id
                )
            );
        } elseif ( $form_id ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name comes from $wpdb->prefix, not input.
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}paxrank_gf_blocked_dates
                     WHERE blocked_date = %s AND COALESCE(end_date, blocked_date) = %s
                     AND gravity_form_id = %d AND date_field_id IS NULL",
                    $start,
                    $stored_end,
                    $form_id
                )
            );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name comes from $wpdb->prefix, not input.
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}paxrank_gf_blocked_dates
                     WHERE blocked_date = %s AND COALESCE(end_date, blocked_date) = %s
                     AND gravity_form_id IS NULL AND date_field_id IS NULL",
                    $start,
                    $stored_end
                )
            );
        }

        return (int) $count > 0;
    }
}
