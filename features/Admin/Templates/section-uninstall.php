<?php
/**
 * Admin section: uninstall data-cleanup option.
 *
 * @package Paxrank\DateBlocker
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="paxrank-settings-section">
    <h2><span class="dashicons dashicons-trash" aria-hidden="true"></span><?php esc_html_e( 'Uninstall Options', 'date-picker-blocker-for-gravity-forms' ); ?></h2>

    <div class="paxrank-danger-box">
        <div class="paxrank-danger-box__icon"><span class="dashicons dashicons-warning" aria-hidden="true"></span></div>
        <div class="paxrank-danger-box__body">
            <h4><?php esc_html_e( 'Database Cleanup Settings', 'date-picker-blocker-for-gravity-forms' ); ?></h4>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="paxrank_gf_save_uninstall_option">
                <?php wp_nonce_field( 'paxrank_gf_date_blocker_nonce' ); ?>

                <label class="paxrank-checkbox-row">
                    <input type="checkbox" name="delete_on_uninstall" value="1" <?php checked( get_option( 'paxrank_gf_date_blocker_delete_on_uninstall', false ) ); ?>>
                    <strong><?php esc_html_e( 'Delete all data when the plugin is uninstalled', 'date-picker-blocker-for-gravity-forms' ); ?></strong>
                </label>

                <p class="paxrank-muted">
                    <strong><?php esc_html_e( 'What does this include?', 'date-picker-blocker-for-gravity-forms' ); ?></strong><br>
                    • <?php esc_html_e( 'All blocked dates and ranges', 'date-picker-blocker-for-gravity-forms' ); ?><br>
                    • <?php esc_html_e( 'All advance notice and weekday restrictions', 'date-picker-blocker-for-gravity-forms' ); ?><br>
                    • <?php esc_html_e( 'Plugin settings', 'date-picker-blocker-for-gravity-forms' ); ?><br>
                    • <?php esc_html_e( 'Database tables', 'date-picker-blocker-for-gravity-forms' ); ?>
                </p>

                <p class="paxrank-danger-text">
                    <span class="dashicons dashicons-warning" aria-hidden="true"></span><strong><?php esc_html_e( 'WARNING:', 'date-picker-blocker-for-gravity-forms' ); ?></strong> <?php esc_html_e( 'This action cannot be undone. If you leave this option unchecked, your data is kept even after uninstalling the plugin.', 'date-picker-blocker-for-gravity-forms' ); ?>
                </p>

                <p>
                    <button type="submit" class="button paxrank-btn-danger"><?php esc_html_e( 'Save Uninstall Settings', 'date-picker-blocker-for-gravity-forms' ); ?></button>
                </p>
            </form>
        </div>
    </div>
</div>
