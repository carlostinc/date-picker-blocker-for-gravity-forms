<?php
/**
 * Admin page header.
 *
 * The wp-header-end marker that anchors admin notices is emitted by
 * render() before this template, so notices stack above the header.
 *
 * @package Paxrank\DateBlocker
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$paxrank_utm = rawurlencode( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
?>
<div class="paxrank-settings-section">
    <div class="paxrank-admin-header">
        <a href="<?php echo esc_url( 'https://paxrank.com?utm_source=' . $paxrank_utm . '&utm_medium=referral' ); ?>" target="_blank" rel="noopener" class="paxrank-admin-header__logo-link">
            <img src="<?php echo esc_url( PAXRANK_GF_DATE_BLOCKER_PLUGIN_URL . 'assets/logo-pax-rank-web-2024.svg' ); ?>" alt="PaxRank" class="paxrank-admin-header__logo">
        </a>
        <h1 class="paxrank-admin-header__title"><?php esc_html_e( 'Date Picker Blocker for Gravity Forms', 'date-picker-blocker-for-gravity-forms' ); ?></h1>
    </div>
</div>
