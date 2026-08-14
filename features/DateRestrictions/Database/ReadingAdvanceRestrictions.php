<?php
/**
 * Reads from the advance-booking restrictions table.
 *
 * Resolution is priority-based: field > form > global (first match wins).
 *
 * @package Paxrank\DateBlocker
 */

namespace Paxrank\DateBlocker\DateRestrictions\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Query helpers for advance-booking restrictions.
 */
class ReadingAdvanceRestrictions {

    /**
     * Get advance restrictions for the admin list.
     *
     * @param int $limit  Max rows.
     * @param int $offset  Offset.
     * @return object[]
     */
    public static function all( int $limit = 50, int $offset = 0 ): array {
        global $wpdb;

        $table = RestrictionsTableSchema::advance_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no core API covers it and nothing is cached.
        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT ar.*, u.display_name AS user_name
                 FROM %i ar
                 LEFT JOIN %i u ON ar.user_id = u.ID
                 ORDER BY ar.created_at DESC
                 LIMIT %d OFFSET %d',
                $table,
                $wpdb->users,
                $limit,
                $offset
            )
        );
    }

    /**
     * Total number of advance restrictions.
     *
     * @return int
     */
    public static function count(): int {
        global $wpdb;

        $table = RestrictionsTableSchema::advance_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no core API covers it and nothing is cached.
        return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
    }

    /**
     * Get advance restrictions as a flat array for the frontend payload.
     *
     * @param int[]|null $form_ids Limit form-scoped rows to these forms
     *                             (global rows always ship). Null = no filter.
     * @return array<int, array{advance_days:int,form_id:?int,field_id:?int}>
     */
    public static function for_js( ?array $form_ids = null ): array {
        global $wpdb;

        $table = RestrictionsTableSchema::advance_table();
        $scope = RestrictionsTableSchema::form_scope_csv( $form_ids );

        if ( null === $scope ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no core API covers it and nothing is cached.
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT advance_days, gravity_form_id, date_field_id
                     FROM %i
                     ORDER BY advance_days DESC',
                    $table
                )
            );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no core API covers it and nothing is cached.
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT advance_days, gravity_form_id, date_field_id
                     FROM %i
                     WHERE gravity_form_id IS NULL OR FIND_IN_SET( gravity_form_id, %s )
                     ORDER BY advance_days DESC',
                    $table,
                    $scope
                )
            );
        }

        $out = array();

        foreach ( $rows as $row ) {
            $out[] = array(
                'advance_days' => (int) $row->advance_days,
                'form_id'      => $row->gravity_form_id ? (int) $row->gravity_form_id : null,
                'field_id'     => $row->date_field_id ? (int) $row->date_field_id : null,
            );
        }

        return $out;
    }

    /**
     * Effective advance days for a form/field (priority: field > form > global).
     *
     * @param int|null $form_id  Gravity Forms form ID.
     * @param int|null $field_id Date field ID.
     * @return int Days of required advance notice (0 when none configured).
     */
    public static function effective_days( ?int $form_id = null, ?int $field_id = null ): int {
        global $wpdb;

        $table = RestrictionsTableSchema::advance_table();

        if ( $form_id && $field_id ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no core API covers it and nothing is cached.
            $field = $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT advance_days FROM %i
                     WHERE gravity_form_id = %d AND date_field_id = %d
                     ORDER BY created_at DESC LIMIT 1',
                    $table,
                    $form_id,
                    $field_id
                )
            );

            if ( null !== $field ) {
                return (int) $field;
            }
        }

        if ( $form_id ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no core API covers it and nothing is cached.
            $form = $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT advance_days FROM %i
                     WHERE gravity_form_id = %d AND date_field_id IS NULL
                     ORDER BY created_at DESC LIMIT 1',
                    $table,
                    $form_id
                )
            );

            if ( null !== $form ) {
                return (int) $form;
            }
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no core API covers it and nothing is cached.
        $global = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT advance_days FROM %i
                 WHERE gravity_form_id IS NULL AND date_field_id IS NULL
                 ORDER BY created_at DESC LIMIT 1',
                $table
            )
        );

        return null !== $global ? (int) $global : 0;
    }
}
