<?php
/**
 * DateRestrictions feature handler.
 *
 * Wires the slice's hooks (classes load on demand via the PSR-4 autoloader).
 * Owns the blocked ranges, advance and weekday restrictions, Gravity Forms
 * enforcement, the frontend assets, the plugin settings, and the admin screen.
 *
 * @package Paxrank\DateBlocker
 */

namespace Paxrank\DateBlocker\DateRestrictions;

use Paxrank\DateBlocker\DateRestrictions\Admin\DateRestrictionsAdminPage;
use Paxrank\DateBlocker\DateRestrictions\Admin\HandlingRestrictionsAjax;
use Paxrank\DateBlocker\DateRestrictions\Admin\SavingPluginSettings;
use Paxrank\DateBlocker\DateRestrictions\Database\CleaningUpOnFormDelete;
use Paxrank\DateBlocker\DateRestrictions\Enforcement\ValidatingSubmittedDates;
use Paxrank\DateBlocker\DateRestrictions\Frontend\EnqueuingFrontendAssets;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Coordinator for the DateRestrictions slice.
 */
class DateRestrictionsHandler {

    /**
     * Wire hooks.
     */
    public function __construct() {
        $this->wire_hooks();
    }

    /**
     * Register the slice's hooks.
     *
     * @return void
     */
    private function wire_hooks(): void {
        EnqueuingFrontendAssets::register();
        ValidatingSubmittedDates::register();
        CleaningUpOnFormDelete::register();

        if ( is_admin() ) {
            DateRestrictionsAdminPage::register();
            HandlingRestrictionsAjax::register();
            SavingPluginSettings::register();
        }
    }
}
