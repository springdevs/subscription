<?php

namespace SpringDevs\Subscription\Utils;

class ProductFactory {

	public static function load( \WC_Product $product ): Product {
		$product_data_class = apply_filters( '_subscrpt_product_data_class', SubscriptionProduct::class, $product );

		return new $product_data_class( $product );
	}
}
