<?php
/**
 * Plugin Name: Date Picker Blocker for Gravity Forms
 * Plugin URI: https://paxrank.com/
 * Description: Block dates, date ranges, weekdays and minimum advance notice in Gravity Forms date fields, globally or per form and field.
 * Version: 1.0.1
 * Author: Carlos Tinca
 * Author URI: https://paxrank.com
 * Text Domain: date-picker-blocker-for-gravity-forms
 * Requires at least: 6.2
 * Tested up to: 7.0
 * Requires PHP: 8.0
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Paxrank\DateBlocker
 */

namespace Paxrank\DateBlocker;

use Paxrank\DateBlocker\DateRestrictions\Database\RestrictionsTableSchema;
use Paxrank\DateBlocker\DateRestrictions\DateRestrictionsHandler;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PAXRANK_GF_DATE_BLOCKER_VERSION', '1.0.1' );
define( 'PAXRANK_GF_DATE_BLOCKER_PLUGIN_FILE', __FILE__ );
define( 'PAXRANK_GF_DATE_BLOCKER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PAXRANK_GF_DATE_BLOCKER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PAXRANK_GF_DATE_BLOCKER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// PSR-4 autoloader: every plugin class is loaded on first use, so nothing
// below needs a require_once.
require_once PAXRANK_GF_DATE_BLOCKER_PLUGIN_DIR . 'autoload.php';

/**
 * Plugin bootstrap: instantiates the feature slice and wires lifecycle hooks.
 */
final class Plugin {

    /**
     * Singleton instance.
     *
     * @var Plugin|null
     */
    private static ?Plugin $instance = null;

    /**
     * Get (and lazily create) the singleton.
     *
     * @return Plugin
     */
    public static function get_instance(): Plugin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Boot the plugin.
     */
    private function __construct() {
        new DateRestrictionsHandler();

        $this->init_hooks();
    }

    /**
     * Register lifecycle hooks.
     *
     * @return void
     */
    private function init_hooks(): void {
        register_activation_hook( PAXRANK_GF_DATE_BLOCKER_PLUGIN_FILE, array( __CLASS__, 'activate' ) );

        // admin_init, not plugins_loaded: DDL must never be triggered by
        // anonymous front-end traffic.
        add_action( 'admin_init', array( RestrictionsTableSchema::class, 'maybe_upgrade' ) );

        add_action( 'init', array( $this, 'fire_init' ) );
        add_action( 'admin_notices', array( $this, 'gravity_forms_notice' ) );
    }

    /**
     * Activation: create the tables (full schema, incl. end_date).
     *
     * Covers fresh installs. Updates are handled separately by
     * RestrictionsTableSchema::maybe_upgrade() on admin_init, because
     * automatic plugin updates never fire the activation hook.
     *
     * @return void
     */
    public static function activate(): void {
        RestrictionsTableSchema::install();
    }

    /**
     * Fire the plugin's init action for extensibility.
     *
     * @return void
     */
    public function fire_init(): void {
        do_action( 'paxrank_gf_date_blocker_init' );
    }

    /**
     * Admin notice when Gravity Forms is missing.
     *
     * @return void
     */
    public function gravity_forms_notice(): void {
        if ( class_exists( 'GFForms' ) ) {
            return;
        }

        echo '<div class="notice notice-error"><p><strong>';
        echo esc_html__( 'Date Picker Blocker for Gravity Forms', 'date-picker-blocker-for-gravity-forms' );
        echo ':</strong> ';
        echo esc_html__( 'This plugin requires Gravity Forms to be installed and active.', 'date-picker-blocker-for-gravity-forms' );
        echo '</p></div>';
    }
}

Plugin::get_instance();
