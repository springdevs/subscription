<?php
/**
 * Mail template for Subscription status changed (Admin).
 *
 * @var string $email_heading Email Heading.
 * @var int $id Subscription id.
 * @var string $product_name Product name.
 * @var int $qty Subscription Quantity.
 * @var string $amount Subscription Amount with price format.
 */

echo esc_html( '= ' . $email_heading . " =\n\n" );

// translators: first is older status and last is newly updated status.
$opening_paragraph = __( 'Your subscription is %1$s Expired! %2$s', 'sdevs_subscrpt' );

echo wp_kses_post( sprintf( $opening_paragraph, '<b>', '</b>' ) . "\n\n" );

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

// translators: Subscription id.
echo esc_html( sprintf( __( 'Subscription Id: %s', 'sdevs_subscrpt' ), $id ) . "\n" );

// translators: Product name.
echo esc_html( sprintf( __( 'Product: %s', 'sdevs_subscrpt' ), $product_name ) . "\n" );

// translators: Subscription quantity.
echo esc_html( sprintf( __( 'Qty: %s', 'sdevs_subscrpt' ), $qty ) . "\n" );

// translators: Subscription amount.
echo wp_kses_post( sprintf( __( 'Amount: %s', 'sdevs_subscrpt' ), $amount ) . "\n" );


echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo wp_kses_post(
	make_clickable(
		sprintf(
		// translators: subscription id.
			__( 'You can view and edit this subscription in the dashboard here: %s', 'sdevs_subscrpt' ),
			admin_url( 'post.php?post=' . $id . '&action=edit' )
		)
	)
);
echo esc_html( "\n\n" );

echo esc_html( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
