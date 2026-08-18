<?php
/**
 * Decides whether the frontend assets should load on the current request.
 *
 * @package Paxrank\DateBlocker
 */

namespace Paxrank\DateBlocker\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Performance gate for frontend asset loading.
 */
class DeterminingWhereToLoad {

    /**
     * Whether the frontend script/style should load here.
     *
     * Filterable via `paxrank_gf_date_blocker_should_load` so forms embedded
     * on the remaining non-singular contexts (archives, search) can opt in
     * without code edits.
     *
     * @return bool
     */
    public static function should_load(): bool {
        return (bool) apply_filters( 'paxrank_gf_date_blocker_should_load', self::evaluate() );
    }

    /**
     * Default gating: the front page, plus singular views of the enabled
     * post types.
     *
     * @return bool
     */
    private static function evaluate(): bool {
        if ( is_admin() ) {
            return false;
        }

        $enabled = get_option( 'paxrank_gf_date_blocker_enabled_post_types', array( 'post', 'page' ) );

        if ( empty( $enabled ) ) {
            return false;
        }

        /*
         * The front page is checked before is_singular() because it comes in
         * two shapes. A static front page IS a singular 'page', so this branch
         * returns exactly what the singular branch below would. A blog-index
         * front page is not singular at all and has no post type to test, yet
         * a form can still live there (widget, template, page builder) — so it
         * is covered whenever pages are.
         */
        if ( is_front_page() ) {
            return in_array( 'page', (array) $enabled, true );
        }

        if ( is_singular() ) {
            return in_array( get_post_type(), (array) $enabled, true );
        }

        return false;
    }
}
