<?php
/**
 * PSR-4-style autoloader for the plugin's classes.
 *
 * Two namespace roots so the namespace stays free of the features/ path
 * segment. The rule is PSR-4: strip the matching prefix, turn the remaining
 * sub-namespace into a path (\ becomes /), append .php. File name equals
 * class name, so every class loads lazily on first use and no require list
 * has to be maintained by hand.
 *
 * The roots overlap — Shared\ sits inside the plugin root namespace — so a
 * prefix matching is not proof the class lives there. Hence the loop only
 * stops once a file is actually found, and keeps trying the remaining roots
 * otherwise. That makes resolution independent of the array's order.
 *
 * @package Paxrank\DateBlocker
 */

// ABSPATH is defined on every WordPress-initiated load, uninstall included
// (WordPress boots fully before running a plugin's uninstall.php).
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

spl_autoload_register(
    static function ( string $class ): void {
        static $roots = null;

        if ( null === $roots ) {
            // Most specific prefix first, so the common case resolves on the
            // first pass; correctness no longer depends on this order.
            $roots = array(
                'Paxrank\\DateBlocker\\Shared\\' => __DIR__ . '/Shared/',
                'Paxrank\\DateBlocker\\'         => __DIR__ . '/features/',
            );
        }

        foreach ( $roots as $prefix => $base_dir ) {
            if ( str_starts_with( $class, $prefix ) ) {
                $relative = substr( $class, strlen( $prefix ) );
                $file     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';

                if ( is_file( $file ) ) {
                    require $file;

                    return;
                }
            }
        }
    }
);
