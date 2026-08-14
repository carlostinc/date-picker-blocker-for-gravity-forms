<?php
/**
 * Database schema + migrations for the date-restriction tables.
 *
 * Owns the three custom tables and upgrades them via a stored DB-version
 * option, so dbDelta only runs when the schema actually changes (instead
 * of SHOW TABLES on every request). install() runs on activation;
 * maybe_upgrade() covers automatic updates, which never fire that hook.
 *
 * @package Paxrank\DateBlocker
 */

namespace Paxrank\DateBlocker\DateRestrictions\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Creates and migrates the plugin's custom tables.
 */
class RestrictionsTableSchema {

    /**
     * Option that stores the installed schema version.
     */
    public const VERSION_OPTION = 'paxrank_gf_date_blocker_db_version';

    /**
     * Current schema version. Bump when the table structure changes.
     *
     * v1 = the three tables as shipped in 1.0.0.
     */
    public const DB_VERSION = '1';

    /**
     * Blocked dates / ranges table name.
     *
     * @return string
     */
    public static function blocked_dates_table(): string {
        global $wpdb;

        return $wpdb->prefix . 'paxrank_gf_blocked_dates';
    }

    /**
     * Advance-booking restrictions table name.
     *
     * @return string
     */
    public static function advance_table(): string {
        global $wpdb;

        return $wpdb->prefix . 'paxrank_gf_advance_restrictions';
    }

    /**
     * Weekday restrictions table name.
     *
     * @return string
     */
    public static function weekday_table(): string {
        global $wpdb;

        return $wpdb->prefix . 'paxrank_gf_weekday_restrictions';
    }

    /**
     * Every table owned by the plugin (used on uninstall).
     *
     * @return string[]
     */
    public static function all_tables(): array {
        return array(
            self::blocked_dates_table(),
            self::advance_table(),
            self::weekday_table(),
        );
    }

    /**
     * Form IDs as a CSV, for binding to a single FIND_IN_SET() placeholder.
     *
     * A variable-length IN() list would force the placeholders to be built into
     * the query string, which no longer prepares cleanly. One %s bound to a CSV
     * keeps every for_js() query fully literal instead. All three restriction
     * tables share the gravity_form_id column, so callers use it identically.
     *
     * Global rows (gravity_form_id IS NULL) are matched by the caller's own
     * IS NULL branch, not by this list. An empty list is therefore valid and
     * means "global rows only".
     *
     * @param int[]|null $form_ids Form IDs, or null for no filtering.
     * @return string|null Null when no filtering applies, else a CSV of IDs.
     */
    public static function form_scope_csv( ?array $form_ids ): ?string {
        if ( null === $form_ids ) {
            return null;
        }

        return implode( ',', array_values( array_filter( array_map( 'intval', $form_ids ) ) ) );
    }

    /**
     * Run pending migrations when the installed schema is behind.
     *
     * Plugin updates never fire the activation hook, so this is what keeps an
     * existing site's tables in sync after an automatic update.
     *
     * @return void
     */
    public static function maybe_upgrade(): void {
        if ( self::DB_VERSION === get_option( self::VERSION_OPTION ) ) {
            return;
        }

        self::install();
    }

    /**
     * Create the tables via dbDelta (full schema), then record the version.
     *
     * On a fresh site this creates the tables; on an existing site dbDelta adds
     * any missing column without touching existing rows.
     *
     * @return void
     */
    public static function install(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $blocked_dates   = self::blocked_dates_table();
        $advance         = self::advance_table();
        $weekday         = self::weekday_table();

        $blocked_sql = "CREATE TABLE $blocked_dates (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            blocked_date date NOT NULL,
            end_date date DEFAULT NULL,
            gravity_form_id int(11) DEFAULT NULL,
            date_field_id int(11) DEFAULT NULL,
            description text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            PRIMARY KEY  (id),
            KEY blocked_date_idx (blocked_date),
            KEY end_date_idx (end_date),
            KEY gravity_form_id_idx (gravity_form_id),
            KEY date_field_id_idx (date_field_id),
            KEY user_id_idx (user_id),
            KEY created_at_idx (created_at)
        ) $charset_collate;";

        $advance_sql = "CREATE TABLE $advance (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            gravity_form_id int(11) DEFAULT NULL,
            date_field_id int(11) DEFAULT NULL,
            advance_days int(11) NOT NULL DEFAULT 0,
            description text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            PRIMARY KEY  (id),
            KEY gravity_form_id_idx (gravity_form_id),
            KEY date_field_id_idx (date_field_id),
            KEY user_id_idx (user_id),
            KEY created_at_idx (created_at)
        ) $charset_collate;";

        $weekday_sql = "CREATE TABLE $weekday (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            gravity_form_id int(11) DEFAULT NULL,
            date_field_id int(11) DEFAULT NULL,
            blocked_weekdays text NOT NULL,
            description text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            PRIMARY KEY  (id),
            KEY gravity_form_id_idx (gravity_form_id),
            KEY date_field_id_idx (date_field_id),
            KEY user_id_idx (user_id),
            KEY created_at_idx (created_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta( $blocked_sql );
        dbDelta( $advance_sql );
        dbDelta( $weekday_sql );

        update_option( self::VERSION_OPTION, self::DB_VERSION );
    }
}
