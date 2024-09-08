<?php
/**
 * Mail template for Subscription status changed (Admin).
 *
 * @var string $email_heading Email Heading.
 * @var string $old_status Readable Old Status of Subscription.
 * @var string $new_status Readable New Status of Subscription.
 * @var int $id Subscription id.
 * @var string $product_name Product name.
 * @var int $qty Subscription Quantity.
 * @var string $amount Subscription Amount with price format.
 * @var string $next_date Next payment date.
 */

// translators: first is older status and last is newly updated status.
$opening_paragraph = __( 'Subscription status changed from %1$s to %2$s', 'sdevs_subscrpt' );
?>

<?php do_action( 'woocommerce_email_header', $email_heading ); ?>


<p><?php printf( esc_html( $opening_paragraph ), wp_kses_post( '<b>' . $old_status . '</b>' ), wp_kses_post( '<b>' . $new_status . '</b>' ) ); ?></p>

<table class="td" cellspacing="0" cellpadding="6" style="width: 100%; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;" border="1">
	<tbody>
		<tr>
			<th scope="row" style="text-align:left; border: 1px solid #eee;"><?php esc_html_e( 'Subscription Id', 'sdevs_subscrpt' ); ?></th>
			<td style="text-align:left; border: 1px solid #eee;"><?php echo esc_html( $id ); ?></td>
		</tr>
		<tr>
			<th scope="row" style="text-align:left; border: 1px solid #eee;"><?php esc_html_e( 'Product', 'sdevs_subscrpt' ); ?></th>
			<td style="text-align:left; border: 1px solid #eee;"><?php echo esc_html( $product_name ); ?></td>
		</tr>
		<tr>
			<th scope="row" style="text-align:left; border: 1px solid #eee;"><?php esc_html_e( 'Qty', 'sdevs_subscrpt' ); ?></th>
			<td style="text-align:left; border: 1px solid #eee;"><?php echo esc_html( $qty ); ?></td>
		</tr>
		<tr>
			<th scope="row" style="text-align:left; border: 1px solid #eee;"><?php esc_html_e( 'Amount', 'sdevs_subscrpt' ); ?></th>
			<td style="text-align:left; border: 1px solid #eee;"><?php echo wp_kses_post( $amount ); ?></td>
		</tr>
</tbody>
</table>
<br>
<p>
<?php
echo wp_kses_post(
	make_clickable(
		sprintf(
			// translators: subscription id.
			__( 'You can view and edit this subscription in the dashboard here: %s', 'sdevs_subscrpt' ),
			admin_url( 'post.php?post=' . $id . '&action=edit' )
		)
	)
);
?>
	</p>

<?php do_action( 'woocommerce_email_footer' ); ?>
