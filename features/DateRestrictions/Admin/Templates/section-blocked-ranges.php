<?php
/**
 * Admin section: blocked dates and ranges (CRUD).
 *
 * @package Paxrank\DateBlocker
 * @var array<int,string> $forms   Forms id => title map.
 * @var object[]          $blocked Blocked date/range rows.
 */

use Paxrank\DateBlocker\DateRestrictions\Admin\DateRestrictionsAdminPage;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="paxrank-settings-section">
    <h2><span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span><?php esc_html_e( 'Blocked Dates and Ranges', 'date-picker-blocker-for-gravity-forms' ); ?></h2>

    <h3><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span><?php esc_html_e( 'Add Blocked Date or Range', 'date-picker-blocker-for-gravity-forms' ); ?></h3>

    <div class="paxrank-add-date-form">
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="paxrank_gf_add_blocked_date">
            <?php wp_nonce_field( 'paxrank_gf_date_blocker_nonce' ); ?>
            <div class="paxrank-form-grid paxrank-form-grid--dates">
                <div class="paxrank-field">
                    <label for="blocked-date"><?php esc_html_e( 'Date (or start)', 'date-picker-blocker-for-gravity-forms' ); ?> *</label>
                    <input type="date" id="blocked-date" name="blocked_date" required>
                </div>

                <div class="paxrank-field">
                    <label for="blocked-end-date"><?php esc_html_e( 'End date (optional)', 'date-picker-blocker-for-gravity-forms' ); ?></label>
                    <input type="date" id="blocked-end-date" name="end_date">
                </div>

                <div class="paxrank-field">
                    <label for="gravity-form-id"><?php esc_html_e( 'Form (optional)', 'date-picker-blocker-for-gravity-forms' ); ?></label>
                    <select id="gravity-form-id" name="gravity_form_id">
                        <option value=""><?php esc_html_e( 'All forms', 'date-picker-blocker-for-gravity-forms' ); ?></option>
                        <?php foreach ( $forms as $paxrank_form_id => $paxrank_form_title ) : ?>
                            <option value="<?php echo esc_attr( $paxrank_form_id ); ?>"><?php echo esc_html( $paxrank_form_title ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="paxrank-field">
                    <label for="date-field-id"><?php esc_html_e( 'Field (optional)', 'date-picker-blocker-for-gravity-forms' ); ?></label>
                    <select id="date-field-id" name="date_field_id" disabled>
                        <option value=""><?php esc_html_e( 'All date fields', 'date-picker-blocker-for-gravity-forms' ); ?></option>
                    </select>
                </div>

                <div class="paxrank-field">
                    <label for="description"><?php esc_html_e( 'Description', 'date-picker-blocker-for-gravity-forms' ); ?></label>
                    <input type="text" id="description" name="description" placeholder="<?php esc_attr_e( 'Optional...', 'date-picker-blocker-for-gravity-forms' ); ?>">
                </div>

                <div class="paxrank-field">
                    <button type="submit" class="button paxrank-btn-primary"><?php esc_html_e( 'Block', 'date-picker-blocker-for-gravity-forms' ); ?></button>
                </div>
            </div>
        </form>

        <div class="paxrank-help-box">
            <strong><span class="dashicons dashicons-lightbulb" aria-hidden="true"></span><?php esc_html_e( 'How it works:', 'date-picker-blocker-for-gravity-forms' ); ?></strong><br>
            • <?php esc_html_e( 'Leave "End date" empty to block a single day, or fill it in to block a whole range.', 'date-picker-blocker-for-gravity-forms' ); ?><br>
            • <strong><?php esc_html_e( 'No form or field:', 'date-picker-blocker-for-gravity-forms' ); ?></strong> <?php esc_html_e( 'Blocks ALL forms and date fields', 'date-picker-blocker-for-gravity-forms' ); ?><br>
            • <strong><?php esc_html_e( 'Form only:', 'date-picker-blocker-for-gravity-forms' ); ?></strong> <?php esc_html_e( 'Blocks ALL date fields on that form', 'date-picker-blocker-for-gravity-forms' ); ?><br>
            • <strong><?php esc_html_e( 'Form + field:', 'date-picker-blocker-for-gravity-forms' ); ?></strong> <?php esc_html_e( 'Blocks only that specific field', 'date-picker-blocker-for-gravity-forms' ); ?>
        </div>
    </div>

    <h3><span class="dashicons dashicons-list-view" aria-hidden="true"></span><?php esc_html_e( 'Configured Dates and Ranges', 'date-picker-blocker-for-gravity-forms' ); ?></h3>

    <?php if ( ! empty( $blocked ) ) : ?>
        <div class="paxrank-table-filters">
            <label>
                <span><?php esc_html_e( 'Form:', 'date-picker-blocker-for-gravity-forms' ); ?></span>
                <select id="blocked-dates-form-filter">
                    <option value=""><?php esc_html_e( 'All forms', 'date-picker-blocker-for-gravity-forms' ); ?></option>
                    <option value="global"><?php esc_html_e( 'Global restrictions only', 'date-picker-blocker-for-gravity-forms' ); ?></option>
                    <?php foreach ( $forms as $paxrank_form_id => $paxrank_form_title ) : ?>
                        <option value="<?php echo esc_attr( $paxrank_form_id ); ?>"><?php echo esc_html( $paxrank_form_title ); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span><?php esc_html_e( 'Search:', 'date-picker-blocker-for-gravity-forms' ); ?></span>
                <input type="text" id="blocked-dates-text-filter" placeholder="<?php esc_attr_e( 'Search by date or description...', 'date-picker-blocker-for-gravity-forms' ); ?>">
            </label>
            <button type="button" id="clear-blocked-dates-filters"><?php esc_html_e( 'Clear', 'date-picker-blocker-for-gravity-forms' ); ?></button>
        </div>

        <div class="paxrank-blocked-dates-list">
            <table class="wp-list-table widefat striped paxrank-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Date / Range', 'date-picker-blocker-for-gravity-forms' ); ?></th>
                        <th><?php esc_html_e( 'Scope', 'date-picker-blocker-for-gravity-forms' ); ?></th>
                        <th><?php esc_html_e( 'Description', 'date-picker-blocker-for-gravity-forms' ); ?></th>
                        <th><?php esc_html_e( 'Created By', 'date-picker-blocker-for-gravity-forms' ); ?></th>
                        <th><?php esc_html_e( 'Created On', 'date-picker-blocker-for-gravity-forms' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'date-picker-blocker-for-gravity-forms' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $blocked as $paxrank_row ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html( DateRestrictionsAdminPage::range_label( $paxrank_row ) ); ?></strong></td>
                            <td><?php echo wp_kses_post( DateRestrictionsAdminPage::scope_badge( $paxrank_row->gravity_form_id, $paxrank_row->date_field_id, $forms ) ); ?></td>
                            <td><?php echo esc_html( $paxrank_row->description ); ?></td>
                            <td><?php echo esc_html( $paxrank_row->user_name ?: __( 'Unknown', 'date-picker-blocker-for-gravity-forms' ) ); ?></td>
                            <td><?php echo esc_html( mysql2date( 'd/m/Y H:i', $paxrank_row->created_at ) ); ?></td>
                            <td>
                                <?php
                                $paxrank_confirm = sprintf(
                                    /* translators: %s: the blocked date or range. */
                                    __( 'Delete the block for "%s"?', 'date-picker-blocker-for-gravity-forms' ),
                                    DateRestrictionsAdminPage::range_label( $paxrank_row )
                                );
                                ?>
                                <a class="button button-small paxrank-delete-link"
                                    href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=paxrank_gf_delete_blocked_date&id=' . (int) $paxrank_row->id ), 'paxrank_gf_delete_blocked_date_' . (int) $paxrank_row->id ) ); ?>"
                                    data-confirm="<?php echo esc_attr( $paxrank_confirm ); ?>">
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
            <h4><?php esc_html_e( 'No blocked dates', 'date-picker-blocker-for-gravity-forms' ); ?></h4>
            <p><?php esc_html_e( 'Add your first date or range using the form above.', 'date-picker-blocker-for-gravity-forms' ); ?></p>
        </div>
    <?php endif; ?>
</div>
