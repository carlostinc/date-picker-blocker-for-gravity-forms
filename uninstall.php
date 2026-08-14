<?php
/**
 * Uninstall handler for Date Picker Blocker for Gravity Forms.
 *
 * Removes all custom tables and options, but only when the admin opted in
 * via the delete-on-uninstall setting.
 *
 * @package Paxrank\DateBlocker
 */

use Paxrank\DateBlocker\DateRestrictions\Database\RestrictionsTableSchema;

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

if ( ! get_option( 'paxrank_gf_date_blocker_delete_on_uninstall', false ) ) {
    return;
}

global $wpdb;

// The schema class is the single source of truth for the table names; the
// autoloader resolves it (WordPress runs uninstall.php without the plugin).
require_once __DIR__ . '/autoload.php';

foreach ( RestrictionsTableSchema::all_tables() as $paxrank_table ) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Dropping the plugin's own tables is the whole point of uninstall.
    $wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $paxrank_table ) );
}

$paxrank_options = array(
    'paxrank_gf_date_blocker_delete_on_uninstall',
    'paxrank_gf_date_blocker_enabled_post_types',
    'paxrank_gf_date_blocker_date_format',
    'paxrank_gf_date_blocker_db_version',
);

foreach ( $paxrank_options as $paxrank_option ) {
    delete_option( $paxrank_option );

    // Scoped to what this plugin owns, rather than flushing the whole cache.
    wp_cache_delete( $paxrank_option, 'options' );
}
