<?php

/**
 * Class SubscriptionTest
 *
 * @package Subscription
 */

/**
 * Test our subscrpt_order post_type
 */
class SubscriptionTest extends WP_UnitTestCase
{

	public function test_subscription_post_type_exixts()
	{
		$this->assertTrue(post_type_exists('subscrpt_order'));
	}
}
