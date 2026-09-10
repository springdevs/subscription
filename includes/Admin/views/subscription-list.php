<?php
/**
 * Subscription admin list view.
 *
 * @package SpringDevs\Subscription\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $date_filter ) ) {
	$date_filter = '';
}

$filters_active = ! empty( $status ) || ! empty( $date_filter ) || ! empty( $search );
$months         = array();
for ( $i = 0; $i < 12; $i++ ) {
	$month                           = strtotime( "-$i month" );
	$months[ date( 'Y-m', $month ) ] = date( 'F Y', $month );
}
?>
<div class="wp-subscription-admin-content list-page">

	<?php
		// Getting started ("Welcome to WPSubscription") card hidden for now — re-enable later.
		// require __DIR__ . '/subscription-gsc.php';
	?>

	<!-- Page header -->
	<div style="margin-bottom:20px;">
		<h1 style="font-size:1.375rem;font-weight:700;color:var(--wpsubs-text);margin:0 0 6px;line-height:1.2;"><?php esc_html_e( 'Subscriptions', 'subscription' ); ?></h1>
		<p style="font-size:13px;color:var(--wpsubs-text-muted);margin:0 0 12px;line-height:1.5;"><?php esc_html_e( 'Manage all your WooCommerce subscriptions.', 'subscription' ); ?></p>
		<div style="border-top:1px dashed #d0d3d7;"></div>
	</div>

	<form method="post" id="subscriptions-form">
		<?php wp_nonce_field( 'subscrpt_list_action' ); ?>
		<input type="hidden" name="page" value="wp-subscription" />

		<!-- Toolbar -->
		<div class="wpsubs-toolbar">

			<?php
			wpsubs_render_adv_select(
				array(
					'name'        => 'subscrpt_status',
					'placeholder' => __( 'All Status', 'subscription' ),
					'value'       => $status,
					'options'     => array(
						array(
							'value' => 'active',
							'label' => __( 'Active', 'subscription' ),
						),
						array(
							'value' => 'pending',
							'label' => __( 'Pending', 'subscription' ),
						),
						array(
							'value' => 'pe_cancelled',
							'label' => __( 'Pending Cancellation', 'subscription' ),
						),
						array(
							'value' => 'cancelled',
							'label' => __( 'Cancelled', 'subscription' ),
						),
						array(
							'value' => 'expired',
							'label' => __( 'Expired', 'subscription' ),
						),
						array(
							'value' => 'completed',
							'label' => __( 'Completed', 'subscription' ),
						),
						array(
							'value' => 'trash',
							'label' => __( 'Trash', 'subscription' ),
						),
					),
				)
			);
			?>

			<?php
			$date_options = array();
			foreach ( $months as $val => $label ) {
				$date_options[] = array(
					'value' => $val,
					'label' => $label,
				);
			}
			wpsubs_render_adv_select(
				array(
					'name'        => 'date_filter',
					'placeholder' => __( 'All Dates', 'subscription' ),
					'value'       => $date_filter,
					'options'     => $date_options,
				)
			);
			?>

			<div class="wpsubs-search">
				<div class="wpsubs-input-wrap wpsubs-input-wrap--icon-l">
					<svg class="wpsubs-input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/></svg>
					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search subscriptions...', 'subscription' ); ?>" class="wpsubs-input" />
				</div>
			</div>

			<button type="submit" name="filter_action" value="filter" class="wpsubs-btn wpsubs-btn--outline">
				<?php esc_html_e( 'Filter', 'subscription' ); ?>
			</button>

			<?php if ( $filters_active ) : ?>
				<a href="<?php echo esc_url( remove_query_arg( array( 'subscrpt_status', 'date_filter', 's', 'title', 'paged' ) ) ); ?>" class="wpsubs-btn wpsubs-btn--outline">
					<?php esc_html_e( 'Clear', 'subscription' ); ?>
				</a>
			<?php endif; ?>

			<div class="wpsubs-toolbar__spacer"></div>

			<?php
			$current_per_page = isset( $_GET['per_page'] ) ? intval( wp_unslash( $_GET['per_page'] ) ) : 20;
			wpsubs_render_per_page_select(
				array(
					'name'  => 'per_page',
					'value' => (string) $current_per_page,
					'align' => 'right',
					'id'    => 'wpsubs-per-page-select',
				)
			);
			?>

			<?php
			// Bulk action advanced-select + hidden submit.
			$bulk_options = 'trash' === $status
				? array(
					array(
						'value' => 'restore',
						'label' => __( 'Restore selected', 'subscription' ),
					),
					array(
						'value'   => 'delete',
						'label'   => __( 'Delete permanently', 'subscription' ),
						'danger'  => true,
						'confirm' => __( 'Delete selected subscriptions permanently? This cannot be undone.', 'subscription' ),
					),
				)
				: array(
					array(
						'value'   => 'trash',
						'label'   => __( 'Move to trash', 'subscription' ),
						'danger'  => true,
						'confirm' => __( 'Move selected subscriptions to trash?', 'subscription' ),
					),
				);

			wpsubs_render_adv_select(
				array(
					'name'        => 'action',
					'placeholder' => __( 'Select Action', 'subscription' ),
					'value'       => '-1',
					'options'     => $bulk_options,
					'id'          => 'wpsubs-bulk-action-select',
					'align'       => 'right',
				)
			);
			?>
			<button type="submit" name="bulk_action" value="1" id="wpsubs-bulk-action-submit" style="display:none;" aria-hidden="true"></button>

			<?php
			if ( 'trash' === $status && ! empty( $subscriptions ) ) :
				$empty_trash_url = wp_nonce_url( admin_url( 'admin.php?page=wp-subscription&action=clean_trash&sub_id=all' ), 'wpsubs_action_clean_trash' );
				?>
				<a href="<?php echo esc_url( $empty_trash_url ); ?>" class="wpsubs-btn wpsubs-btn--danger" onclick="return confirm('<?php esc_attr_e( 'Permanently delete all trash items? This cannot be undone.', 'subscription' ); ?>')">
					<?php esc_html_e( 'Empty Trash', 'subscription' ); ?>
				</a>
			<?php endif; ?>

		</div><!-- /.wpsubs-toolbar -->

		<!-- Table card -->
		<h2 class="screen-reader-text"><?php esc_html_e( 'Subscriptions list', 'subscription' ); ?></h2>
		<div class="wpsubs-table-card">
			<table class="wpsubs-table">
				<thead>
					<tr>
						<th class="wpsubs-col--check">
							<input type="checkbox" id="cb-select-all" class="wpsubs-checkbox">
						</th>
						<th style="width:1%;white-space:nowrap;"><?php esc_html_e( 'Status', 'subscription' ); ?></th>
						<th><?php esc_html_e( 'Subscription', 'subscription' ); ?></th>
						<th><?php esc_html_e( 'Customer', 'subscription' ); ?></th>
						<th><?php esc_html_e( 'Amount', 'subscription' ); ?></th>
						<th><?php esc_html_e( 'Started', 'subscription' ); ?></th>
						<th><?php esc_html_e( 'Next Renewal', 'subscription' ); ?></th>
						<th class="wpsubs-col--actions"><?php esc_html_e( 'Actions', 'subscription' ); ?></th>
					</tr>
				</thead>
				<tbody>

				<?php if ( ! empty( $subscriptions ) ) : ?>
					<?php
					foreach ( $subscriptions as $subscription ) :
						$subscription_id   = $subscription->ID;
						$subscription_data = SpringDevs\Subscription\Illuminate\Helper::get_subscription_data( $subscription_id );

						$subscrpt_status = $subscription_data['status'] ?? '';
						$subscrpt_status = empty( $subscrpt_status ) ? get_post_status( $subscription_id ) : $subscrpt_status;

						$order_id      = $subscription_data['order']['order_id'] ?? 0;
						$order_item_id = $subscription_data['order']['order_item_id'] ?? 0;
						$order         = $order_id ? wc_get_order( $order_id ) : null;
						$order_item    = $order ? $order->get_item( $order_item_id ) : null;
						$product_name  = $order_item ? $order_item->get_name() : '-';

						$product_id  = $subscription_data['product']['variation_id'] ?: ( $subscription_data['product']['product_id'] ?? 0 );
						$product_url = $product_id ? get_edit_post_link( $product_id ) : '';

						$customer       = $order ? $order->get_formatted_billing_full_name() : '-';
						$customer_id    = $order ? $order->get_customer_id() : 0;
						$customer_url   = $customer_id ? admin_url( 'user-edit.php?user_id=' . $customer_id ) : '';
						$customer_email = $order ? $order->get_billing_email() : '';

						$start_date   = $subscription_data['start_date'] ? strtotime( $subscription_data['start_date'] ) : 0;
						$renewal_date = $subscription_data['next_date'] ? strtotime( $subscription_data['next_date'] ) : 0;

						$is_trash        = 'trash' === $subscription->post_status;
						$is_grace_period = isset( $subscription_data['grace_period'] );
						$grace_remaining = $subscription_data['grace_period']['remaining_days'] ?? 0;

						// Amount + timing. Net of any discount that carries into renewals.
						$quantity       = $order_item ? (int) $order_item->get_quantity() : 1;
						$amount_totals  = SpringDevs\Subscription\Illuminate\Helper::get_subscription_display_totals( $subscription_id, $order_item );
						$price          = '' !== ( $subscription_data['price'] ?? '' ) ? (float) $amount_totals['total'] : '';
						$price_full     = (float) $amount_totals['full_excl'] + $amount_totals['tax'] + $amount_totals['discount_tax'];
						$has_discount   = (bool) $amount_totals['has_discount'];
						$price_per_unit = '' !== $price ? $price / max( 1, $quantity ) : '';
						$timing_per     = $subscription_data['schedule']['timing_per'] ?? '';
						$timing_option  = $subscription_data['schedule']['timing_option'] ?? '';
						$timing_label   = '';
						if ( $timing_per && $timing_option ) {
							$timing_unit  = \SpringDevs\Subscription\Illuminate\Helper::get_typos( $timing_per, $timing_option );
							$timing_label = ( (int) $timing_per > 1 )
								? $timing_per . ' ' . $timing_unit
								: $timing_unit;
						}

						// Avatar
						$name_parts = array_values( array_filter( explode( ' ', trim( $customer ) ) ) );
						$initials   = '?';
						if ( $name_parts ) {
							$initials = '';
							foreach ( array_slice( $name_parts, 0, 2 ) as $part ) {
								$initials .= strtoupper( $part[0] );
							}
						}
						$color_slot = ord( strtolower( $initials[0] ?? 'a' ) ) % 8;

						// Action URLs
						$nonce_action = 'wpsubs_action_' . $subscription->ID;
						$view_url     = admin_url( 'admin.php?page=wp-subscription-details&id=' . $subscription->ID );
						$trash_url    = wp_nonce_url( admin_url( 'admin.php?page=wp-subscription&action=trash&sub_id=' . $subscription->ID ), $nonce_action );
						$delete_url   = wp_nonce_url( admin_url( 'admin.php?page=wp-subscription&action=delete&sub_id=' . $subscription->ID ), $nonce_action );
						$restore_url  = wp_nonce_url( admin_url( 'admin.php?page=wp-subscription&action=restore&sub_id=' . $subscription->ID ), $nonce_action );

						// Status badge
						$badge_mod_map  = array(
							'active'       => 'active',
							'pending'      => 'pending',
							'pe_cancelled' => 'pending-cancel',
							'cancelled'    => 'cancelled',
							'expired'      => 'expired',
							'completed'    => 'completed',
							'draft'        => 'draft',
							'trash'        => 'trash',
						);
						$badge_mod      = $badge_mod_map[ $subscrpt_status ] ?? 'expired';
						$verbose_status = SpringDevs\Subscription\Illuminate\Helper::get_verbose_status( $subscrpt_status );

						if ( $is_grace_period && $grace_remaining > 0 ) {
							$badge_mod      = 'active';
							$verbose_status = __( 'Active', 'subscription' );
						}
						?>
					<tr>
						<!-- Checkbox -->
						<td class="wpsubs-col--check">
							<input type="checkbox" name="subscription_ids[]" value="<?php echo esc_attr( $subscription->ID ); ?>" class="wpsubs-checkbox wpsubs-row-check">
						</td>

						<!-- Status badge -->
						<td style="white-space:nowrap;">
							<span class="wpsubs-badge wpsubs-badge--<?php echo esc_attr( $badge_mod ); ?>">
								<?php echo esc_html( $verbose_status ); ?>
								<?php if ( $is_grace_period && $grace_remaining > 0 ) : ?>
									<span class="dashicons dashicons-warning" style="font-size:11px;width:11px;height:11px;color:#d97706;" title="<?php echo esc_attr( sprintf( __( '%d days remaining in grace period', 'subscription' ), $grace_remaining ) ); ?>"></span>
								<?php endif; ?>
							</span>
						</td>

						<!-- Subscription: product name + subscription ID -->
						<td>
							<a href="<?php echo esc_url( $view_url ); ?>" class="wpsubs-cell-title" style="font-weight:600;"><?php echo esc_html( $product_name ); ?></a>
							<span class="wpsubs-cell-id">#<?php echo (int) $subscription->ID; ?></span>
						</td>

						<!-- Customer avatar + name + email -->
						<td>
							<div class="wpsubs-customer">
								<div class="wpsubs-avatar" data-color="<?php echo (int) $color_slot; ?>">
									<?php echo esc_html( $initials ); ?>
								</div>
								<div class="wpsubs-customer__info">
									<?php if ( $customer_url ) : ?>
										<a href="<?php echo esc_url( $customer_url ); ?>" class="wpsubs-customer__name"><?php echo esc_html( $customer ); ?></a>
									<?php else : ?>
										<span class="wpsubs-customer__name"><?php echo esc_html( $customer ); ?></span>
									<?php endif; ?>
									<?php if ( $customer_email ) : ?>
										<span class="wpsubs-customer__sub"><?php echo esc_html( $customer_email ); ?></span>
									<?php endif; ?>
								</div>
							</div>
						</td>

						<!-- Subscription amount + timing -->
						<td style="white-space:nowrap;">
							<?php if ( '' !== $price ) : ?>
								<span class="wpsubs-cell-title" style="font-variant-numeric:tabular-nums;"><?php echo wp_kses_post( wc_price( $price ) ); ?></span>
								<?php if ( $has_discount ) : ?>
									<span class="wpsubs-cell-id"><del aria-hidden="true"><?php echo wp_kses_post( wc_price( $price_full ) ); ?></del></span>
								<?php endif; ?>
								<?php if ( $quantity > 1 ) : ?>
									<span class="wpsubs-cell-id"><?php echo wp_kses_post( wc_price( (float) $price_per_unit ) ); ?> &times; <?php echo (int) $quantity; ?></span>
								<?php endif; ?>
								<?php if ( $timing_label ) : ?>
									<span class="wpsubs-cell-id">/ <?php echo esc_html( $timing_label ); ?></span>
								<?php endif; ?>
							<?php else : ?>
								<span class="wpsubs-cell--muted">&#8212;</span>
							<?php endif; ?>
						</td>

						<!-- Start date -->
						<td class="wpsubs-col--nowrap">
							<?php if ( $start_date ) : ?>
								<span class="wpsubs-cell-title"><?php echo esc_html( wp_date( get_option( 'date_format' ), $start_date ) ); ?></span>
								<span class="wpsubs-cell-id"><?php echo esc_html( wp_date( get_option( 'time_format' ), $start_date ) ); ?></span>
							<?php else : ?>
								<span class="wpsubs-cell--muted">&#8212;</span>
							<?php endif; ?>
						</td>

						<!-- Renewal date -->
						<td class="wpsubs-col--nowrap">
							<?php if ( $renewal_date ) : ?>
								<span class="wpsubs-cell-title"><?php echo esc_html( wp_date( get_option( 'date_format' ), $renewal_date ) ); ?></span>
								<span class="wpsubs-cell-id"><?php echo esc_html( wp_date( get_option( 'time_format' ), $renewal_date ) ); ?></span>
							<?php else : ?>
								<span class="wpsubs-cell--muted">&#8212;</span>
							<?php endif; ?>
						</td>

						<!-- Actions -->
						<td class="wpsubs-cell--actions">
							<div class="wpsubs-row-actions">
								<button type="button" class="wpsubs-row-actions__trigger" aria-label="<?php esc_attr_e( 'Row actions', 'subscription' ); ?>">···</button>
								<div class="wpsubs-dropdown">
									<a href="<?php echo esc_url( $view_url ); ?>" class="wpsubs-dropdown__item"><?php esc_html_e( 'View / Edit', 'subscription' ); ?></a>
									<div class="wpsubs-dropdown__divider"></div>
									<?php if ( ! $is_trash ) : ?>
										<a href="<?php echo esc_url( $trash_url ); ?>" class="wpsubs-dropdown__item wpsubs-dropdown__item--danger" onclick="return confirm('<?php esc_attr_e( 'Move this subscription to trash?', 'subscription' ); ?>')"><?php esc_html_e( 'Trash', 'subscription' ); ?></a>
									<?php else : ?>
										<a href="<?php echo esc_url( $restore_url ); ?>" class="wpsubs-dropdown__item"><?php esc_html_e( 'Restore', 'subscription' ); ?></a>
										<a href="<?php echo esc_url( $delete_url ); ?>" class="wpsubs-dropdown__item wpsubs-dropdown__item--danger" onclick="return confirm('<?php esc_attr_e( 'Delete permanently? This cannot be undone.', 'subscription' ); ?>')"><?php esc_html_e( 'Delete Permanently', 'subscription' ); ?></a>
									<?php endif; ?>
								</div>
							</div>
						</td>
					</tr>
					<?php endforeach; ?>

				<?php else : ?>
					<tr>
						<td colspan="8">
							<div class="wpsubs-empty">
								<div class="wpsubs-empty__icon">📋</div>
								<div class="wpsubs-empty__title"><?php esc_html_e( 'No subscriptions found', 'subscription' ); ?></div>
								<div class="wpsubs-empty__desc"><?php esc_html_e( 'Subscriptions will appear here once customers complete checkout.', 'subscription' ); ?></div>
							</div>
						</td>
					</tr>
				<?php endif; ?>

				</tbody>
			</table>

			<!-- Pagination inside table card -->
			<?php
			wpsubs_render_pager(
				array(
					'current'     => (int) $paged,
					'total'       => max( 1, (int) $max_num_pages ),
					'info'        => true,
					'per_page'    => (int) $per_page,
					'item_count'  => (int) $total,
					'base_url'    => remove_query_arg( 'paged' ),
					'link_mode'   => 'url',
					// translators: %1$s = first item number, %2$s = last item number, %3$s = total items.
					'info_format' => __( 'Showing %1$s–%2$s of %3$s subscriptions', 'subscription' ),
					'context'     => 'subscription-list',
				)
			);
			?>

		</div><!-- /.wpsubs-table-card -->

	</form>

</div>

<script>
( function () {
	var selectAll = document.getElementById( 'cb-select-all' );
	var form      = document.getElementById( 'subscriptions-form' );

	function rowChecks() {
		return form ? Array.from( form.querySelectorAll( '.wpsubs-row-check' ) ) : [];
	}

	function syncSelectAll() {
		if ( ! selectAll ) return;
		var checks  = rowChecks();
		var checked = checks.filter( function ( cb ) { return cb.checked; } );
		checks.forEach( function ( cb ) {
			var row = cb.closest( 'tr' );
			if ( row ) row.classList.toggle( 'wpsubs-row--selected', cb.checked );
		} );
		selectAll.indeterminate = checked.length > 0 && checked.length < checks.length;
		selectAll.checked       = checks.length > 0 && checked.length === checks.length;
	}

	if ( selectAll ) {
		selectAll.addEventListener( 'change', function () {
			rowChecks().forEach( function ( cb ) { cb.checked = selectAll.checked; } );
			syncSelectAll();
		} );
	}

	if ( form ) {
		form.addEventListener( 'change', function ( e ) {
			if ( e.target.classList.contains( 'wpsubs-row-check' ) ) syncSelectAll();
		} );
	}

	// ── Advanced-select event handlers ──────────────────────────
	var bulkSubmit = document.getElementById( 'wpsubs-bulk-action-submit' );
	document.addEventListener( 'wpsubs:select', function ( e ) {
		var id = e.target && e.target.id;

		// Bulk action: submit via hidden button
		if ( id === 'wpsubs-bulk-action-select' && bulkSubmit ) {
			bulkSubmit.click();
			return;
		}

		// Per-page: auto-submit as a filter action
		if ( id === 'wpsubs-per-page-select' && form ) {
			var fi = document.createElement( 'input' );
			fi.type  = 'hidden';
			fi.name  = 'filter_action';
			fi.value = 'filter';
			form.appendChild( fi );
			form.submit();
		}
	} );

	// ── Row action dropdowns ─────────────────────────────────────
	function closeAllRowMenus() {
		document.querySelectorAll( '.wpsubs-row-actions--open' ).forEach( function ( el ) {
			el.classList.remove( 'wpsubs-row-actions--open' );
			var d = el.querySelector( '.wpsubs-dropdown' );
			if ( d ) d.classList.remove( 'wpsubs-dropdown--open' );
		} );
	}

	document.addEventListener( 'click', function ( e ) {
		var trigger = e.target.closest( '.wpsubs-row-actions__trigger' );

		document.querySelectorAll( '.wpsubs-row-actions--open' ).forEach( function ( el ) {
			if ( el !== ( trigger && trigger.closest( '.wpsubs-row-actions' ) ) ) {
				el.classList.remove( 'wpsubs-row-actions--open' );
				var d = el.querySelector( '.wpsubs-dropdown' );
				if ( d ) d.classList.remove( 'wpsubs-dropdown--open' );
			}
		} );

		if ( ! trigger ) return;

		e.stopPropagation();
		var wrapper  = trigger.closest( '.wpsubs-row-actions' );
		var dropdown = wrapper && wrapper.querySelector( '.wpsubs-dropdown' );
		if ( ! wrapper || ! dropdown ) return;

		var isOpen = wrapper.classList.contains( 'wpsubs-row-actions--open' );
		wrapper.classList.toggle( 'wpsubs-row-actions--open', ! isOpen );
		dropdown.classList.toggle( 'wpsubs-dropdown--open', ! isOpen );
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Escape' ) closeAllRowMenus();
	} );

	syncSelectAll();
}() );
</script>
