<?php
/**
 * Admin notice: a subscription was cancelled (free).
 *
 * Reports the reason and nothing identifying - no customer, no subscription
 * link. Those live in Pro, which the closing block points at.
 *
 * This template can be overridden by copying it to:
 * <your_theme>/subscription/emails/cancellation-admin-html.php
 *
 * @var string $email_heading Email heading.
 * @var string $intro         Opening sentence.
 * @var string $reason        Reason the customer gave, may be empty.
 * @var string $reported_at   When this was reported, site-formatted.
 * @var string $reports_url   Admin Reports page.
 * @var string $upgrade_url   Pro product page.
 * @var object $email         The WC_Email instance.
 *
 * @package SpringDevs\Subscription
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p style="margin:0 0 16px;"><?php echo esc_html( $intro ); ?></p>

<table cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:separate;border-spacing:0;border:1px solid #e3e3e8;border-radius:6px;overflow:hidden;font-family:'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif;font-size:14px;">
	<tbody>
		<tr>
			<th scope="row" style="width:40%;padding:10px 14px;text-align:left;vertical-align:top;font-weight:600;color:#50575e;background-color:#f6f7f7;"><?php esc_html_e( 'Reason given', 'subscription' ); ?></th>
			<td style="padding:10px 14px;text-align:left;vertical-align:top;color:#1d2327;">
				<?php echo esc_html( '' !== $reason ? $reason : __( 'No reason given', 'subscription' ) ); ?>
			</td>
		</tr>
		<tr>
			<th scope="row" style="width:40%;padding:10px 14px;text-align:left;vertical-align:top;font-weight:600;color:#50575e;background-color:#f6f7f7;border-top:1px solid #e3e3e8;"><?php esc_html_e( 'Reported', 'subscription' ); ?></th>
			<td style="padding:10px 14px;text-align:left;vertical-align:top;color:#1d2327;border-top:1px solid #e3e3e8;"><?php echo esc_html( $reported_at ); ?></td>
		</tr>
	</tbody>
</table>

<p style="margin:20px 0 0;font-size:13px;color:#50575e;">
	<?php esc_html_e( 'Which customer cancelled, what they wrote, and your full churn history are shown in WPSubscription Pro.', 'subscription' ); ?>
</p>

<table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin:16px 0;">
	<tr>
		<td align="center">
			<a href="<?php echo esc_url( $upgrade_url ); ?>"
				style="display:inline-block;padding:12px 24px;background-color:#7f54b3;color:#ffffff;text-decoration:none;font-size:14px;font-weight:600;border-radius:4px;">
				<?php esc_html_e( 'See it in Pro', 'subscription' ); ?>
			</a>
		</td>
	</tr>
</table>

<p style="margin:0;font-size:13px;color:#787c82;">
	<a href="<?php echo esc_url( $reports_url ); ?>" style="color:#2271b1;"><?php esc_html_e( 'Open the Reports page', 'subscription' ); ?></a>
</p>

<?php
do_action( 'woocommerce_email_footer', $email );
