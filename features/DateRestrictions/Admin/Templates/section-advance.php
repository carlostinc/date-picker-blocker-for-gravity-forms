<?php
/**
 * Admin section: advance-booking restrictions (CRUD).
 *
 * @package Paxrank\DateBlocker
 * @var array<int,string> $forms   Forms id => title map.
 * @var object[]          $advance Advance restriction rows.
 */

use Paxrank\DateBlocker\DateRestrictions\Admin\DateRestrictionsAdminPage;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="paxrank-settings-section">
    <h2><span class="dashicons dashicons-clock" aria-hidden="true"></span><?php esc_html_e( 'Advance Notice Restrictions', 'date-picker-blocker-for-gravity-forms' ); ?></h2>

    <div class="paxrank-add-date-form">
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="paxrank_gf_add_advance_restriction">
            <?php wp_nonce_field( 'paxrank_gf_date_blocker_nonce' ); ?>
            <div class="paxrank-form-grid paxrank-form-grid--advance">
                <div class="paxrank-field">
                    <label for="advance-days"><?php esc_html_e( 'Days of Advance Notice', 'date-picker-blocker-for-gravity-forms' ); ?> *</label>
                    <input type="number" id="advance-days" name="advance_days" required min="0" max="365" placeholder="0">
                </div>

                <div class="paxrank-field">
                    <label for="advance-gravity-form-id"><?php esc_html_e( 'Form (optional)', 'date-picker-blocker-for-gravity-forms' ); ?></label>
                    <select id="advance-gravity-form-id" name="gravity_form_id">
                        <option value=""><?php esc_html_e( 'All forms', 'date-picker-blocker-for-gravity-forms' ); ?></option>
                        <?php foreach ( $forms as $paxrank_form_id => $paxrank_form_title ) : ?>
                            <option value="<?php echo esc_attr( $paxrank_form_id ); ?>"><?php echo esc_html( $paxrank_form_title ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="paxrank-field">
                    <label for="advance-date-field-id"><?php esc_html_e( 'Field (optional)', 'date-picker-blocker-for-gravity-forms' ); ?></label>
                    <select id="advance-date-field-id" name="date_field_id" disabled>
                        <option value=""><?php esc_html_e( 'All date fields', 'date-picker-blocker-for-gravity-forms' ); ?></option>
                    </select>
                </div>

                <div class="paxrank-field">
                    <label for="advance-description"><?php esc_html_e( 'Description', 'date-picker-blocker-for-gravity-forms' ); ?></label>
                    <input type="text" id="advance-description" name="description" placeholder="<?php esc_attr_e( 'Optional...', 'date-picker-blocker-for-gravity-forms' ); ?>">
                </div>

                <div class="paxrank-field">
                    <button type="submit" class="button paxrank-btn-primary"><?php esc_html_e( 'Set Restriction', 'date-picker-blocker-for-gravity-forms' ); ?></button>
                </div>
            </div>
        </form>

        <div class="paxrank-help-box">
            <strong><span class="dashicons dashicons-lightbulb" aria-hidden="true"></span><?php esc_html_e( 'Priority system:', 'date-picker-blocker-for-gravity-forms' ); ?></strong><br>
            • <strong><?php esc_html_e( 'No form or field:', 'date-picker-blocker-for-gravity-forms' ); ?></strong> <?php esc_html_e( 'Global restriction for ALL forms', 'date-picker-blocker-for-gravity-forms' ); ?><br>
            • <strong><?php esc_html_e( 'Form only:', 'date-picker-blocker-for-gravity-forms' ); ?></strong> <?php esc_html_e( 'Restriction for that specific form', 'date-picker-blocker-for-gravity-forms' ); ?><br>
            • <strong><?php esc_html_e( 'Form + field:', 'date-picker-blocker-for-gravity-forms' ); ?></strong> <?php esc_html_e( 'Restriction for that specific field', 'date-picker-blocker-for-gravity-forms' ); ?><br>
            <em><?php esc_html_e( 'The most specific restriction wins: field > form > global (they do not stack).', 'date-picker-blocker-for-gravity-forms' ); ?></em>
        </div>
    </div>

    <h3><span class="dashicons dashicons-list-view" aria-hidden="true"></span><?php esc_html_e( 'Configured Restrictions', 'date-picker-blocker-for-gravity-forms' ); ?></h3>

    <?php if ( ! empty( $advance ) ) : ?>
        <div class="paxrank-table-filters">
            <label>
                <span><?php esc_html_e( 'Form:', 'date-picker-blocker-for-gravity-forms' ); ?></span>
                <select id="advance-restrictions-form-filter">
                    <option value=""><?php esc_html_e( 'All forms', 'date-picker-blocker-for-gravity-forms' ); ?></option>
                    <option value="global"><?php esc_html_e( 'Global restrictions only', 'date-picker-blocker-for-gravity-forms' ); ?></option>
                    <?php foreach ( $forms as $paxrank_form_id => $paxrank_form_title ) : ?>
                        <option value="<?php echo esc_attr( $paxrank_form_id ); ?>"><?php echo esc_html( $paxrank_form_title ); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span><?php esc_html_e( 'Search:', 'date-picker-blocker-for-gravity-forms' ); ?></span>
                <input type="text" id="advance-restrictions-text-filter" placeholder="<?php esc_attr_e( 'Search by days or description...', 'date-picker-blocker-for-gravity-forms' ); ?>">
            </label>
            <button type="button" id="clear-advance-restrictions-filters"><?php esc_html_e( 'Clear', 'date-picker-blocker-for-gravity-forms' ); ?></button>
        </div>

        <div class="paxrank-advance-restrictions-list">
            <table class="wp-list-table widefat striped paxrank-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Days of Advance Notice', 'date-picker-blocker-for-gravity-forms' ); ?></th>
                        <th><?php esc_html_e( 'Scope', 'date-picker-blocker-for-gravity-forms' ); ?></th>
                        <th><?php esc_html_e( 'Description', 'date-picker-blocker-for-gravity-forms' ); ?></th>
                        <th><?php esc_html_e( 'Created By', 'date-picker-blocker-for-gravity-forms' ); ?></th>
                        <th><?php esc_html_e( 'Created On', 'date-picker-blocker-for-gravity-forms' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'date-picker-blocker-for-gravity-forms' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $advance as $paxrank_row ) : ?>
                        <tr>
                            <td class="paxrank-cell-strong">
                                <?php
                                /* translators: %d: number of advance days. */
                                printf( esc_html( _n( '%d day', '%d days', (int) $paxrank_row->advance_days, 'date-picker-blocker-for-gravity-forms' ) ), (int) $paxrank_row->advance_days );
                                ?>
                            </td>
                            <td><?php echo wp_kses_post( DateRestrictionsAdminPage::scope_badge( $paxrank_row->gravity_form_id, $paxrank_row->date_field_id, $forms ) ); ?></td>
                            <td><?php echo $paxrank_row->description ? esc_html( $paxrank_row->description ) : '<em class="paxrank-muted">' . esc_html__( 'No description', 'date-picker-blocker-for-gravity-forms' ) . '</em>'; ?></td>
                            <td><?php echo esc_html( $paxrank_row->user_name ?: __( 'Unknown user', 'date-picker-blocker-for-gravity-forms' ) ); ?></td>
                            <td><?php echo esc_html( mysql2date( 'd/m/Y H:i', $paxrank_row->created_at ) ); ?></td>
                            <td>
                                <?php
                                $paxrank_confirm = sprintf(
                                    /* translators: %d: number of days of advance notice. */
                                    _n(
                                        'Delete the %d day advance notice restriction?',
                                        'Delete the %d days advance notice restriction?',
                                        (int) $paxrank_row->advance_days,
                                        'date-picker-blocker-for-gravity-forms'
                                    ),
                                    (int) $paxrank_row->advance_days
                                );
                                ?>
                                <a class="button button-small paxrank-delete-link"
                                    href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=paxrank_gf_delete_advance_restriction&id=' . (int) $paxrank_row->id ), 'paxrank_gf_delete_advance_restriction_' . (int) $paxrank_row->id ) ); ?>"
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
            <div class="paxrank-empty-state__icon"><span class="dashicons dashicons-clock" aria-hidden="true"></span></div>
            <h4><?php esc_html_e( 'No advance notice restrictions configured', 'date-picker-blocker-for-gravity-forms' ); ?></h4>
            <p><?php esc_html_e( 'Add your first restriction using the form above.', 'date-picker-blocker-for-gravity-forms' ); ?></p>
        </div>
    <?php endif; ?>
</div>
