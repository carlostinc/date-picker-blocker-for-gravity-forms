<?php
/**
 * Admin section: weekday restrictions (CRUD).
 *
 * @package Paxrank\DateBlocker
 * @var array<int,string> $forms   Forms id => title map.
 * @var object[]          $weekday Weekday restriction rows.
 */

use Paxrank\DateBlocker\DateRestrictions\Admin\DateRestrictionsAdminPage;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$paxrank_weekday_choices = array(
    1 => __( 'Mon', 'date-picker-blocker-for-gravity-forms' ),
    2 => __( 'Tue', 'date-picker-blocker-for-gravity-forms' ),
    3 => __( 'Wed', 'date-picker-blocker-for-gravity-forms' ),
    4 => __( 'Thu', 'date-picker-blocker-for-gravity-forms' ),
    5 => __( 'Fri', 'date-picker-blocker-for-gravity-forms' ),
    6 => __( 'Sat', 'date-picker-blocker-for-gravity-forms' ),
    0 => __( 'Sun', 'date-picker-blocker-for-gravity-forms' ),
);
$paxrank_weekday_names = DateRestrictionsAdminPage::weekday_short_names();
?>
<div class="paxrank-settings-section">
    <h2><span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span><?php esc_html_e( 'Weekday Restrictions', 'date-picker-blocker-for-gravity-forms' ); ?></h2>

    <div class="paxrank-add-date-form">
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="paxrank_gf_add_weekday_restriction">
            <?php wp_nonce_field( 'paxrank_gf_date_blocker_nonce' ); ?>
            <div class="paxrank-form-grid paxrank-form-grid--weekday">
                <div class="paxrank-field">
                    <label><?php esc_html_e( 'Blocked Days', 'date-picker-blocker-for-gravity-forms' ); ?> *</label>
                    <div class="paxrank-weekday-grid">
                        <?php foreach ( $paxrank_weekday_choices as $paxrank_value => $paxrank_label ) : ?>
                            <label class="paxrank-weekday-option">
                                <input type="checkbox" name="blocked_weekdays[]" value="<?php echo esc_attr( $paxrank_value ); ?>">
                                <span><?php echo esc_html( $paxrank_label ); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="paxrank-field">
                    <label for="weekday-gravity-form-id"><?php esc_html_e( 'Form (optional)', 'date-picker-blocker-for-gravity-forms' ); ?></label>
                    <select id="weekday-gravity-form-id" name="gravity_form_id">
                        <option value=""><?php esc_html_e( 'All forms', 'date-picker-blocker-for-gravity-forms' ); ?></option>
                        <?php foreach ( $forms as $paxrank_form_id => $paxrank_form_title ) : ?>
                            <option value="<?php echo esc_attr( $paxrank_form_id ); ?>"><?php echo esc_html( $paxrank_form_title ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="paxrank-field">
                    <label for="weekday-date-field-id"><?php esc_html_e( 'Field (optional)', 'date-picker-blocker-for-gravity-forms' ); ?></label>
                    <select id="weekday-date-field-id" name="date_field_id" disabled>
                        <option value=""><?php esc_html_e( 'All date fields', 'date-picker-blocker-for-gravity-forms' ); ?></option>
                    </select>
                </div>

                <div class="paxrank-field">
                    <label for="weekday-description"><?php esc_html_e( 'Description', 'date-picker-blocker-for-gravity-forms' ); ?></label>
                    <input type="text" id="weekday-description" name="description" placeholder="<?php esc_attr_e( 'Optional...', 'date-picker-blocker-for-gravity-forms' ); ?>">
                </div>

                <div class="paxrank-field">
                    <button type="submit" class="button paxrank-btn-primary"><?php esc_html_e( 'Set Restriction', 'date-picker-blocker-for-gravity-forms' ); ?></button>
                </div>
            </div>
        </form>

        <div class="paxrank-help-box">
            <strong><span class="dashicons dashicons-lightbulb" aria-hidden="true"></span><?php esc_html_e( 'Additive system:', 'date-picker-blocker-for-gravity-forms' ); ?></strong><br>
            • <strong><?php esc_html_e( 'No form or field:', 'date-picker-blocker-for-gravity-forms' ); ?></strong> <?php esc_html_e( 'Global restriction for ALL forms', 'date-picker-blocker-for-gravity-forms' ); ?><br>
            • <strong><?php esc_html_e( 'Form only:', 'date-picker-blocker-for-gravity-forms' ); ?></strong> <?php esc_html_e( 'Additional restriction for that specific form', 'date-picker-blocker-for-gravity-forms' ); ?><br>
            • <strong><?php esc_html_e( 'Form + field:', 'date-picker-blocker-for-gravity-forms' ); ?></strong> <?php esc_html_e( 'Additional restriction for that specific field', 'date-picker-blocker-for-gravity-forms' ); ?><br>
            <em><?php esc_html_e( 'All applicable restrictions are combined (blocked days add up).', 'date-picker-blocker-for-gravity-forms' ); ?></em>
        </div>
    </div>

    <h3><span class="dashicons dashicons-list-view" aria-hidden="true"></span><?php esc_html_e( 'Configured Weekday Restrictions', 'date-picker-blocker-for-gravity-forms' ); ?></h3>

    <?php if ( ! empty( $weekday ) ) : ?>
        <div class="paxrank-table-filters">
            <label>
                <span><?php esc_html_e( 'Form:', 'date-picker-blocker-for-gravity-forms' ); ?></span>
                <select id="weekday-restrictions-form-filter">
                    <option value=""><?php esc_html_e( 'All forms', 'date-picker-blocker-for-gravity-forms' ); ?></option>
                    <option value="global"><?php esc_html_e( 'Global restrictions only', 'date-picker-blocker-for-gravity-forms' ); ?></option>
                    <?php foreach ( $forms as $paxrank_form_id => $paxrank_form_title ) : ?>
                        <option value="<?php echo esc_attr( $paxrank_form_id ); ?>"><?php echo esc_html( $paxrank_form_title ); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span><?php esc_html_e( 'Search:', 'date-picker-blocker-for-gravity-forms' ); ?></span>
                <input type="text" id="weekday-restrictions-text-filter" placeholder="<?php esc_attr_e( 'Search by days or description...', 'date-picker-blocker-for-gravity-forms' ); ?>">
            </label>
            <button type="button" id="clear-weekday-restrictions-filters"><?php esc_html_e( 'Clear', 'date-picker-blocker-for-gravity-forms' ); ?></button>
        </div>

        <div class="paxrank-weekday-restrictions-list">
            <table class="wp-list-table widefat striped paxrank-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Blocked Days', 'date-picker-blocker-for-gravity-forms' ); ?></th>
                        <th><?php esc_html_e( 'Scope', 'date-picker-blocker-for-gravity-forms' ); ?></th>
                        <th><?php esc_html_e( 'Description', 'date-picker-blocker-for-gravity-forms' ); ?></th>
                        <th><?php esc_html_e( 'Created By', 'date-picker-blocker-for-gravity-forms' ); ?></th>
                        <th><?php esc_html_e( 'Created On', 'date-picker-blocker-for-gravity-forms' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'date-picker-blocker-for-gravity-forms' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ( $weekday as $paxrank_row ) :
                        $paxrank_days   = json_decode( $paxrank_row->blocked_weekdays, true ) ?: array();
                        $paxrank_labels = array();
                        foreach ( $paxrank_days as $paxrank_day ) {
                            if ( isset( $paxrank_weekday_names[ (int) $paxrank_day ] ) ) {
                                $paxrank_labels[] = $paxrank_weekday_names[ (int) $paxrank_day ];
                            }
                        }
                        $paxrank_days_text = implode( ', ', $paxrank_labels );
                        ?>
                        <tr>
                            <td class="paxrank-cell-strong"><?php echo esc_html( $paxrank_days_text ); ?></td>
                            <td><?php echo wp_kses_post( DateRestrictionsAdminPage::scope_badge( $paxrank_row->gravity_form_id, $paxrank_row->date_field_id, $forms ) ); ?></td>
                            <td><?php echo $paxrank_row->description ? esc_html( $paxrank_row->description ) : '<em class="paxrank-muted">' . esc_html__( 'No description', 'date-picker-blocker-for-gravity-forms' ) . '</em>'; ?></td>
                            <td><?php echo esc_html( $paxrank_row->user_name ?: __( 'Unknown user', 'date-picker-blocker-for-gravity-forms' ) ); ?></td>
                            <td><?php echo esc_html( mysql2date( 'd/m/Y H:i', $paxrank_row->created_at ) ); ?></td>
                            <td>
                                <a class="button button-small paxrank-delete-link"
                                    href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=paxrank_gf_delete_weekday_restriction&id=' . (int) $paxrank_row->id ), 'paxrank_gf_delete_weekday_restriction_' . (int) $paxrank_row->id ) ); ?>"
                                    data-confirm="<?php echo esc_attr( __( 'Delete this weekday restriction?', 'date-picker-blocker-for-gravity-forms' ) ); ?>">
                                    <span class="dashicons dashicons-trash" aria-hidden="true"></span><?php esc_html_e( 'Delete', 'date-picker-blocker-for-gravity-forms' ); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else : ?>
        <div class="paxrank-empty-state">
            <div class="paxrank-empty-state__icon"><span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span></div>
            <h4><?php esc_html_e( 'No weekday restrictions configured', 'date-picker-blocker-for-gravity-forms' ); ?></h4>
            <p><?php esc_html_e( 'Add your first restriction using the form above.', 'date-picker-blocker-for-gravity-forms' ); ?></p>
        </div>
    <?php endif; ?>
</div>
