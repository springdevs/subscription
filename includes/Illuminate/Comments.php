<?php

namespace SpringDevs\Subscription\Illuminate;

/**
 * Class Comments
 *
 * @package SpringDevs\Subscription\Illuminate
 */
class Comments {

	public function __construct() {
		add_action( 'admin_init', array( $this, 'remove_comments_metabox' ) );
	}

	public function remove_comments_metabox() {
		remove_meta_box( 'commentsdiv', 'subscrpt_order', 'normal' );
		remove_meta_box( 'commentstatusdiv', 'subscrpt_order', 'side' );
		remove_meta_box( 'commentstatusdiv', 'subscrpt_order', 'normal' );
		remove_meta_box( 'commentstatusdiv', 'subscrpt_order', 'normal' );
	}
}
