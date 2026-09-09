<?php
/**
 * Admin notice: a subscription was cancelled (free), plain text.
 *
 * @var string $email_heading Email heading.
 * @var string $intro         Opening sentence.
 * @var string $reason        Reason the customer gave, may be empty.
 * @var string $reported_at   When this was reported.
 * @var string $reports_url   Admin Reports page.
 * @var string $upgrade_url   Pro product page.
 *
 * @package SpringDevs\Subscription
 */

defined( 'ABSPATH' ) || exit;

echo '= ' . esc_html( $email_heading ) . " =\n\n";
echo esc_html( $intro ) . "\n\n";
echo esc_html__( 'Reason given:', 'subscription' ) . ' ' . esc_html( '' !== $reason ? $reason : __( 'No reason given', 'subscription' ) ) . "\n";
echo esc_html__( 'Reported:', 'subscription' ) . ' ' . esc_html( $reported_at ) . "\n\n";
echo esc_html__( 'Which customer cancelled, what they wrote, and your full churn history are shown in WPSubscription Pro.', 'subscription' ) . "\n\n";
echo esc_html__( 'See it in Pro:', 'subscription' ) . ' ' . esc_url_raw( $upgrade_url ) . "\n";
echo esc_html__( 'Reports:', 'subscription' ) . ' ' . esc_url_raw( $reports_url ) . "\n";
