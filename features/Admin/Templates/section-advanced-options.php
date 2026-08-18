<?php
/**
 * Admin section: advanced options (enabled post types + debug panel).
 *
 * @package Paxrank\DateBlocker
 * @var array<int,string> $forms        Forms id => title map.
 * @var bool              $gf_available Whether Gravity Forms is active.
 */

use Paxrank\DateBlocker\Database\ReadingAdvanceRestrictions;
use Paxrank\DateBlocker\Database\ReadingBlockedRanges;
use Paxrank\DateBlocker\Database\ReadingWeekdayRestrictions;
use Paxrank\DateBlocker\Shared\DateFormat;
use Paxrank\DateBlocker\Shared\GravityForms;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$paxrank_post_types    = get_post_types( array( 'public' => true ), 'objects' );
$paxrank_enabled_types = get_option( 'paxrank_gf_date_blocker_enabled_post_types', array( 'post', 'page' ) );
?>
<div class="paxrank-settings-section">
    <h2><span class="dashicons dashicons-admin-settings" aria-hidden="true"></span><?php esc_html_e( 'Advanced Options', 'date-picker-blocker-for-gravity-forms' ); ?></h2>

    <div class="paxrank-subpanel">
        <h3><span class="dashicons dashicons-media-text" aria-hidden="true"></span><?php esc_html_e( 'Active Content Types', 'date-picker-blocker-for-gravity-forms' ); ?></h3>

        <p class="paxrank-muted"><?php esc_html_e( 'Choose which content types the plugin should load on, to optimize performance:', 'date-picker-blocker-for-gravity-forms' ); ?></p>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="paxrank_gf_save_post_types">
            <?php wp_nonce_field( 'paxrank_gf_date_blocker_nonce' ); ?>

            <div class="paxrank-posttypes-grid">
                <?php foreach ( $paxrank_post_types as $paxrank_type ) : ?>
                    <label class="paxrank-posttype-option">
                        <input type="checkbox" name="enabled_post_types[]" value="<?php echo esc_attr( $paxrank_type->name ); ?>" <?php checked( in_array( $paxrank_type->name, (array) $paxrank_enabled_types, true ) ); ?>>
                        <span class="paxrank-posttype-option__label"><?php echo esc_html( $paxrank_type->label ); ?></span>
                        <span class="paxrank-posttype-option__slug">(<?php echo esc_html( $paxrank_type->name ); ?>)</span>
                    </label>
                <?php endforeach; ?>
            </div>

            <p>
                <button type="submit" class="button paxrank-btn-primary"><?php esc_html_e( 'Save Settings', 'date-picker-blocker-for-gravity-forms' ); ?></button>
            </p>
        </form>

        <div class="paxrank-help-box">
            <strong><span class="dashicons dashicons-lightbulb" aria-hidden="true"></span><?php esc_html_e( 'Optimization:', 'date-picker-blocker-for-gravity-forms' ); ?></strong> <?php esc_html_e( 'The plugin will only load on single pages of the selected content types.', 'date-picker-blocker-for-gravity-forms' ); ?>
        </div>
    </div>

    <div class="paxrank-debug-toggle">
        <label>
            <input type="checkbox" id="enable-debug">
            <strong><?php esc_html_e( 'Enable debug', 'date-picker-blocker-for-gravity-forms' ); ?></strong>
        </label>
        <span class="paxrank-muted"><?php esc_html_e( 'Shows detailed information for troubleshooting', 'date-picker-blocker-for-gravity-forms' ); ?></span>
    </div>

    <div id="debug-information" class="paxrank-debug-panel">
        <h3><span class="dashicons dashicons-chart-bar" aria-hidden="true"></span><?php esc_html_e( 'System Information', 'date-picker-blocker-for-gravity-forms' ); ?></h3>
        <div class="paxrank-debug-grid">
            <strong>WordPress:</strong> <span><?php echo esc_html( get_bloginfo( 'version' ) ); ?></span>
            <strong>PHP:</strong> <span><?php echo esc_html( phpversion() ); ?></span>
            <strong>Gravity Forms:</strong> <span><?php echo $gf_available ? esc_html( GFForms::$version ) : esc_html__( 'Not installed', 'date-picker-blocker-for-gravity-forms' ); ?></span>
            <strong><?php esc_html_e( 'Plugin version:', 'date-picker-blocker-for-gravity-forms' ); ?></strong> <span><?php echo esc_html( PAXRANK_GF_DATE_BLOCKER_VERSION ); ?></span>
            <strong>Timezone:</strong> <span><?php echo esc_html( wp_timezone_string() ); ?></span>
            <strong><?php esc_html_e( 'Current time:', 'date-picker-blocker-for-gravity-forms' ); ?></strong> <span><?php echo esc_html( current_time( 'Y-m-d H:i:s' ) ); ?></span>
        </div>

        <h4><?php esc_html_e( 'Data sent to the frontend (JavaScript)', 'date-picker-blocker-for-gravity-forms' ); ?></h4>
        <pre class="paxrank-debug-pre"><?php
        echo esc_html(
            wp_json_encode(
                array(
                    '_note'               => 'Debug view: unfiltered data. The actual page payload ships only the rows of the forms rendered on that page, with advance days already resolved per field under "forms".',
                    'siteOffset'          => wp_timezone()->getOffset( new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) ),
                    'dateFormat'          => DateFormat::get_display_format(),
                    'blockedRanges'       => ReadingBlockedRanges::for_js(),
                    'advanceRestrictions' => ReadingAdvanceRestrictions::for_js(),
                    'weekdayRestrictions' => ReadingWeekdayRestrictions::for_js(),
                ),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            )
        );
        ?></pre>

        <h4><?php esc_html_e( 'Gravity Forms detected', 'date-picker-blocker-for-gravity-forms' ); ?></h4>
        <?php if ( $gf_available && ! empty( $forms ) ) : ?>
            <table class="paxrank-debug-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th><?php esc_html_e( 'Title', 'date-picker-blocker-for-gravity-forms' ); ?></th>
                        <th><?php esc_html_e( 'Date Fields', 'date-picker-blocker-for-gravity-forms' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $forms as $paxrank_form_id => $paxrank_form_title ) : ?>
                        <tr>
                            <td><?php echo (int) $paxrank_form_id; ?></td>
                            <td><?php echo esc_html( $paxrank_form_title ); ?></td>
                            <td>
                                <?php
                                $paxrank_fields = GravityForms::get_date_fields( (int) $paxrank_form_id );
                                if ( ! empty( $paxrank_fields ) ) {
                                    $paxrank_pairs = array();
                                    foreach ( $paxrank_fields as $paxrank_fid => $paxrank_flabel ) {
                                        $paxrank_pairs[] = 'ID ' . (int) $paxrank_fid . ' (' . $paxrank_flabel . ')';
                                    }
                                    echo esc_html( implode( ' · ', $paxrank_pairs ) );
                                } else {
                                    echo '<em class="paxrank-muted">' . esc_html__( 'No date fields', 'date-picker-blocker-for-gravity-forms' ) . '</em>';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p class="paxrank-muted"><?php esc_html_e( 'Gravity Forms is not installed or has no forms.', 'date-picker-blocker-for-gravity-forms' ); ?></p>
        <?php endif; ?>
    </div>
</div>
