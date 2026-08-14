<?php
/**
 * Admin section: global date format setting.
 *
 * @package Paxrank\DateBlocker
 */

use Paxrank\DateBlocker\Shared\DateFormat;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$paxrank_current_format = DateFormat::get_display_format();
$paxrank_format_labels  = array(
    'DD/MM/YYYY' => __( 'DD/MM/YYYY (European: 25/12/2024)', 'date-picker-blocker-for-gravity-forms' ),
    'MM/DD/YYYY' => __( 'MM/DD/YYYY (US: 12/25/2024)', 'date-picker-blocker-for-gravity-forms' ),
    'YYYY-MM-DD' => __( 'YYYY-MM-DD (ISO: 2024-12-25)', 'date-picker-blocker-for-gravity-forms' ),
    'DD-MM-YYYY' => __( 'DD-MM-YYYY (European with dashes: 25-12-2024)', 'date-picker-blocker-for-gravity-forms' ),
    'MM-DD-YYYY' => __( 'MM-DD-YYYY (US with dashes: 12-25-2024)', 'date-picker-blocker-for-gravity-forms' ),
);
?>
<div class="paxrank-settings-section">
    <h2><span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span><?php esc_html_e( 'Global Date Format Settings', 'date-picker-blocker-for-gravity-forms' ); ?></h2>

    <div class="paxrank-add-date-form">
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="paxrank_gf_save_date_format">
            <?php wp_nonce_field( 'paxrank_gf_date_blocker_nonce' ); ?>
            <div class="paxrank-form-grid paxrank-form-grid--settings">
                <div class="paxrank-field">
                    <label for="global-date-format"><?php esc_html_e( 'Date Format', 'date-picker-blocker-for-gravity-forms' ); ?> *</label>
                    <select id="global-date-format" name="date_format">
                        <?php foreach ( $paxrank_format_labels as $paxrank_value => $paxrank_label ) : ?>
                            <option value="<?php echo esc_attr( $paxrank_value ); ?>" <?php selected( $paxrank_current_format, $paxrank_value ); ?>><?php echo esc_html( $paxrank_label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="paxrank-field">
                    <label><?php esc_html_e( 'Date Example', 'date-picker-blocker-for-gravity-forms' ); ?></label>
                    <div id="date-format-example" class="paxrank-date-example">
                        <?php echo esc_html( DateFormat::for_display( DateFormat::today() ) ); ?>
                    </div>
                </div>

                <div class="paxrank-field">
                    <button type="submit" class="button paxrank-btn-primary"><?php esc_html_e( 'Save Format', 'date-picker-blocker-for-gravity-forms' ); ?></button>
                </div>
            </div>
        </form>

        <div class="paxrank-help-box">
            <strong><span class="dashicons dashicons-lightbulb" aria-hidden="true"></span><?php esc_html_e( 'Important:', 'date-picker-blocker-for-gravity-forms' ); ?></strong><br>
            • <?php esc_html_e( 'This format applies to ALL plugin dates (blocks, restrictions, validation)', 'date-picker-blocker-for-gravity-forms' ); ?><br>
            • <?php esc_html_e( 'It must match the format your Gravity Forms use', 'date-picker-blocker-for-gravity-forms' ); ?><br>
            • <?php esc_html_e( 'Changing the format does NOT affect dates already stored in the database', 'date-picker-blocker-for-gravity-forms' ); ?><br>
            • <?php esc_html_e( 'If you are unsure, check how dates display in your forms before changing this', 'date-picker-blocker-for-gravity-forms' ); ?>
        </div>
    </div>
</div>
