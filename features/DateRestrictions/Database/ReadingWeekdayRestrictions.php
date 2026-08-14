<?php
/**
 * Reads from the weekday restrictions table.
 *
 * Resolution is additive: global + form + field restrictions are merged.
 * Weekdays are stored as a JSON array of ints (0 = Sunday .. 6 = Saturday).
 *
 * @package Paxrank\DateBlocker
 */

namespace Paxrank\DateBlocker\DateRestrictions\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Query helpers for weekday restrictions.
 */
class ReadingWeekdayRestrictions {

    /**
     * Get weekday restrictions for the admin list.
     *
     * @param int $limit  Max rows.
     * @param int $offset  Offset.
     * @return object[]
     */
    public static function all( int $limit = 50, int $offset = 0 ): array {
        global $wpdb;

        $table = RestrictionsTableSchema::weekday_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no core API covers it and nothing is cached.
        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT wr.*, u.display_name AS user_name
                 FROM %i wr
                 LEFT JOIN %i u ON wr.user_id = u.ID
                 ORDER BY wr.created_at DESC
                 LIMIT %d OFFSET %d',
                $table,
                $wpdb->users,
                $limit,
                $offset
            )
        );
    }

    /**
     * Total number of weekday restrictions.
     *
     * @return int
     */
    public static function count(): int {
        global $wpdb;

        $table = RestrictionsTableSchema::weekday_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no core API covers it and nothing is cached.
        return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
    }

    /**
     * Get weekday restrictions as a flat array for the frontend payload.
     *
     * The blocked_weekdays value is kept as a JSON string (the JS parses it).
     *
     * @param int[]|null $form_ids Limit form-scoped rows to these forms
     *                             (global rows always ship). Null = no filter.
     * @return array<int, array{blocked_weekdays:string,form_id:?int,field_id:?int}>
     */
    public static function for_js( ?array $form_ids = null ): array {
        global $wpdb;

        $table = RestrictionsTableSchema::weekday_table();
        $scope = RestrictionsTableSchema::form_scope_csv( $form_ids );

        if ( null === $scope ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no core API covers it and nothing is cached.
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT blocked_weekdays, gravity_form_id, date_field_id
                     FROM %i
                     ORDER BY gravity_form_id, date_field_id',
                    $table
                )
            );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no core API covers it and nothing is cached.
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT blocked_weekdays, gravity_form_id, date_field_id
                     FROM %i
                     WHERE gravity_form_id IS NULL OR FIND_IN_SET( gravity_form_id, %s )
                     ORDER BY gravity_form_id, date_field_id',
                    $table,
                    $scope
                )
            );
        }

        $out = array();

        foreach ( $rows as $row ) {
            $out[] = array(
                'blocked_weekdays' => $row->blocked_weekdays,
                'form_id'          => $row->gravity_form_id ? (int) $row->gravity_form_id : null,
                'field_id'         => $row->date_field_id ? (int) $row->date_field_id : null,
            );
        }

        return $out;
    }

    /**
     * Effective blocked weekdays for a form/field (additive: global + form + field).
     *
     * @param int|null $form_id  Gravity Forms form ID.
     * @param int|null $field_id Date field ID.
     * @return int[] Unique weekday numbers (0 = Sunday .. 6 = Saturday).
     */
    public static function blocked_weekdays( ?int $form_id = null, ?int $field_id = null ): array {
        global $wpdb;

        $table = RestrictionsTableSchema::weekday_table();
        $all   = array();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no core API covers it and nothing is cached.
        $global = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT blocked_weekdays FROM %i
                 WHERE gravity_form_id IS NULL AND date_field_id IS NULL
                 ORDER BY created_at DESC LIMIT 1',
                $table
            )
        );

        if ( null !== $global ) {
            $all = array_merge( $all, json_decode( $global, true ) ?: array() );
        }

        if ( $form_id ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no core API covers it and nothing is cached.
            $form = $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT blocked_weekdays FROM %i
                     WHERE gravity_form_id = %d AND date_field_id IS NULL
                     ORDER BY created_at DESC LIMIT 1',
                    $table,
                    $form_id
                )
            );

            if ( null !== $form ) {
                $all = array_merge( $all, json_decode( $form, true ) ?: array() );
            }
        }

        if ( $form_id && $field_id ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no core API covers it and nothing is cached.
            $field = $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT blocked_weekdays FROM %i
                     WHERE gravity_form_id = %d AND date_field_id = %d
                     ORDER BY created_at DESC LIMIT 1',
                    $table,
                    $form_id,
                    $field_id
                )
            );

            if ( null !== $field ) {
                $all = array_merge( $all, json_decode( $field, true ) ?: array() );
            }
        }

        return array_values( array_unique( array_map( 'intval', $all ) ) );
    }
}
