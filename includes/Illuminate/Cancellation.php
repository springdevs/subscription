<?php
/**
 * Subscription cancellation handler.
 *
 * Owns the conversion of a pending-cancellation (`pe_cancelled`) subscription
 * into a fully `cancelled` one. This is intentionally kept separate from the
 * hourly *expiry* check in {@see Cron} so cancellation-related behaviour can grow
 * here independently.
 *
 * Default flow (free): when a subscription enters `pe_cancelled` it is scheduled
 * to be cancelled 24 hours later. The exact moment is filterable via
 * `subscrpt_cancellation_time`, which the Pro plugin uses to offer "immediately",
 * "after 24 hours", or "at the end of the billing period".
 *
 * @package SpringDevs\Subscription\Illuminate
 */

namespace SpringDevs\Subscription\Illuminate;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Class Cancellation
 *
 * @package SpringDevs\Subscription\Illuminate
 */
class Cancellation {

	/**
	 * Post meta storing the timestamp at which a pending cancellation becomes final.
	 *
	 * @var string
	 */
	const CANCEL_AT_META = '_subscrpt_cancel_at';

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		add_action( 'subscrpt_subscription_pending_cancellation', [ $this, 'schedule_cancellation' ] );
		add_action( 'subscrpt_hourly_cron', [ $this, 'process_due_cancellations' ] );
		add_action( 'subscrpt_subscription_resumed', [ $this, 'clear_scheduled_cancellation' ] );
		add_action( 'before_single_subscrpt_content', [ $this, 'display_pending_cancellation_notice' ] );
		add_action( 'before_single_subscrpt_content', [ $this, 'maybe_render_feedback_modal' ] );
		add_action( 'wp_ajax_subscrpt_record_cancellation_feedback', [ $this, 'record_feedback' ] );
		add_action( 'wp_ajax_subscrpt_record_cancellation_save', [ $this, 'record_save' ] );
		add_action( 'wp_ajax_subscrpt_claim_cancellation_offer', [ $this, 'claim_offer' ] );
		add_action( 'subscrpt_details_side_bottom', [ $this, 'render_admin_feedback_card' ] );
	}

	/**
	 * Fetch the most recent cancellation-feedback row for a subscription.
	 *
	 * @param int $subscription_id Subscription ID.
	 * @return array|null Associative row, or null when none exists.
	 */
	public static function get_feedback( $subscription_id ) {
		global $wpdb;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT reason_label, comment, created_at FROM {$wpdb->prefix}subscrpt_cancellation_feedback WHERE subscription_id = %d ORDER BY id DESC LIMIT 1",
				(int) $subscription_id
			),
			ARRAY_A
		);

		return $row ? $row : null;
	}

	/**
	 * Show the customer's cancellation reason on the admin subscription details page
	 * (bottom of the right-hand column).
	 *
	 * Renders only for cancelled / pending-cancellation subscriptions that have a
	 * recorded feedback row.
	 *
	 * @param array $ctx Subscription details context.
	 * @return void
	 */
	public function render_admin_feedback_card( $ctx ) {
		$subscription_id = (int) ( $ctx['subscription_id'] ?? 0 );
		$status          = $ctx['status'] ?? '';

		if ( ! in_array( $status, [ 'cancelled', 'pe_cancelled' ], true ) ) {
			return;
		}

		$feedback = self::get_feedback( $subscription_id );
		if ( ! $feedback ) {
			return;
		}

		$reason  = (string) ( $feedback['reason_label'] ?? '' );
		$comment = (string) ( $feedback['comment'] ?? '' );
		$when    = '';
		if ( ! empty( $feedback['created_at'] ) ) {
			$when = date_i18n(
				get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
				strtotime( get_date_from_gmt( $feedback['created_at'] ) )
			);
		}
		?>
		<div class="subscrpt-card">
			<div class="subscrpt-card__head"><?php esc_html_e( 'Cancellation Reason', 'subscription' ); ?></div>
			<div class="subscrpt-card__body">
				<div style="display:flex;gap:10px;align-items:flex-start;">
					<span style="flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;background:var(--wpsubs-brand-light);color:var(--wpsubs-brand);">
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
					</span>
					<div style="flex:1 1 auto;min-width:0;">
						<p style="margin:0;font-weight:600;color:var(--wpsubs-text);word-break:break-word;">
							<?php echo '' !== $reason ? esc_html( $reason ) : esc_html__( 'No reason selected', 'subscription' ); ?>
						</p>
						<?php if ( '' !== $when ) : ?>
							<p style="margin:2px 0 0;font-size:12px;color:var(--wpsubs-text-subtle);">
								<?php
								printf(
									/* translators: %s: date and time the feedback was submitted. */
									esc_html__( 'Submitted %s', 'subscription' ),
									esc_html( $when )
								);
								?>
							</p>
						<?php endif; ?>
					</div>
				</div>
				<?php if ( '' !== $comment ) : ?>
					<blockquote style="margin:12px 0 0;padding:8px 12px;border-left:3px solid var(--wpsubs-border-strong);background:var(--wpsubs-surface-muted);border-radius:6px;color:var(--wpsubs-text-muted);font-size:13px;line-height:1.5;word-break:break-word;">
						<?php echo wp_kses( nl2br( esc_html( $comment ) ), [ 'br' => [] ] ); ?>
					</blockquote>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the cancellation-feedback modal on the single-subscription page.
	 *
	 * Only renders when the feature is enabled and the subscription is in a state
	 * that shows a Cancel button (pending/active/on_hold with user cancellation
	 * allowed). The markup is hidden by default; the frontend script intercepts the
	 * Cancel link click, opens it, and on confirm records feedback before following
	 * the original secure cancel URL.
	 *
	 * @param int $subscription_id Subscription ID.
	 * @return void
	 */
	public function maybe_render_feedback_modal( $subscription_id ) {
		if ( ! self::is_feedback_enabled() ) {
			return;
		}

		$subscription_id = (int) $subscription_id;
		$status          = get_post_status( $subscription_id );

		if ( ! in_array( $status, [ 'pending', 'active', 'on_hold' ], true ) ) {
			return;
		}

		if ( 'no' === get_post_meta( $subscription_id, '_subscrpt_user_cancel', true ) ) {
			return;
		}

		$reasons = self::get_reasons();
		if ( empty( $reasons ) ) {
			return;
		}

		wp_enqueue_style( 'subscrpt_cancellation_css', SUBSCRPT_ASSETS . '/css/cancellation.css', [], SUBSCRPT_VERSION );
		wp_enqueue_script( 'subscrpt_cancellation_feedback', SUBSCRPT_ASSETS . '/js/frontend/cancellation-feedback.js', [], SUBSCRPT_VERSION, true );
		wp_localize_script(
			'subscrpt_cancellation_feedback',
			'subscrptCancellationFeedback',
			[
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'subscrpt_cancellation_feedback' ),
				'doneLabel' => __( 'Done', 'subscription' ),
			]
		);
		?>
		<div class="subscrpt-feedback-modal" id="subscrpt-feedback-modal" data-subscription="<?php echo esc_attr( $subscription_id ); ?>" hidden>
			<div class="subscrpt-feedback-modal__overlay" data-subscrpt-feedback-dismiss></div>
			<div class="subscrpt-feedback-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="subscrpt-feedback-title" aria-describedby="subscrpt-feedback-intro">
				<div class="subscrpt-feedback-modal__header">
					<h3 class="subscrpt-feedback-modal__title" id="subscrpt-feedback-title"><?php esc_html_e( 'Before you go', 'subscription' ); ?></h3>
					<button type="button" class="subscrpt-feedback-modal__close" data-subscrpt-feedback-dismiss aria-label="<?php esc_attr_e( 'Close', 'subscription' ); ?>">
						<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
					</button>
				</div>
				<?php if ( \SpringDevs\Subscription\Admin\CancellationFlow::offer_enabled() ) : ?>
					<?php $subscrpt_offer_percent = \SpringDevs\Subscription\Admin\CancellationFlow::offer_percent(); ?>
					<div class="subscrpt-feedback-modal__body" data-subscrpt-offer-step>
						<p class="subscrpt-feedback-modal__intro">
							<?php
							printf(
								/* translators: %s: discount percentage. */
								esc_html__( 'Stay with us and take %s%% off your next order.', 'subscription' ),
								esc_html( (string) $subscrpt_offer_percent )
							);
							?>
						</p>
						<p class="subscrpt-feedback-modal__offer-note" data-subscrpt-offer-result hidden></p>
					</div>
					<div class="subscrpt-feedback-modal__footer" data-subscrpt-offer-step>
						<button type="button" class="subscrpt-feedback-modal__btn subscrpt-feedback-modal__offer-decline" data-subscrpt-offer-decline><?php esc_html_e( 'No thanks, continue', 'subscription' ); ?></button>
						<button type="button" class="subscrpt-feedback-modal__btn subscrpt-feedback-modal__offer-claim" data-subscrpt-offer-claim>
							<?php esc_html_e( 'Claim discount', 'subscription' ); ?>
						</button>
					</div>
				<?php endif; ?>

				<div class="subscrpt-feedback-modal__body"<?php echo \SpringDevs\Subscription\Admin\CancellationFlow::offer_enabled() ? ' data-subscrpt-reason-step hidden' : ''; ?>>
					<p class="subscrpt-feedback-modal__intro" id="subscrpt-feedback-intro"><?php esc_html_e( 'Please let us know why you are cancelling. Your feedback helps us improve.', 'subscription' ); ?></p>
					<ul class="subscrpt-feedback-modal__reasons">
						<?php foreach ( $reasons as $index => $reason ) : ?>
							<?php
							$reason_key   = isset( $reason['key'] ) ? (string) $reason['key'] : '';
							$reason_label = isset( $reason['label'] ) ? (string) $reason['label'] : '';
							if ( '' === $reason_key || '' === $reason_label ) {
								continue;
							}
							$input_id = 'subscrpt-feedback-reason-' . $index;
							?>
							<li class="subscrpt-feedback-modal__reason">
								<label class="subscrpt-feedback-modal__reason-label" for="<?php echo esc_attr( $input_id ); ?>">
									<input type="radio" class="subscrpt-feedback-modal__radio" id="<?php echo esc_attr( $input_id ); ?>" name="subscrpt_feedback_reason" value="<?php echo esc_attr( $reason_key ); ?>" data-label="<?php echo esc_attr( $reason_label ); ?>" />
									<span class="subscrpt-feedback-modal__reason-text"><?php echo esc_html( $reason_label ); ?></span>
								</label>
							</li>
						<?php endforeach; ?>
					</ul>
					<?php if ( self::is_feedback_comment_enabled() ) : ?>
						<textarea class="subscrpt-feedback-modal__comment" id="subscrpt-feedback-comment" rows="3" placeholder="<?php esc_attr_e( 'Additional comments (optional)', 'subscription' ); ?>"></textarea>
					<?php endif; ?>
				</div>
				<div class="subscrpt-feedback-modal__footer"<?php echo \SpringDevs\Subscription\Admin\CancellationFlow::offer_enabled() ? ' data-subscrpt-reason-step hidden' : ''; ?>>
					<button type="button" class="subscrpt-feedback-modal__btn subscrpt-feedback-modal__confirm" id="subscrpt-feedback-confirm"><?php esc_html_e( 'Confirm cancellation', 'subscription' ); ?></button>
					<button type="button" class="subscrpt-feedback-modal__btn subscrpt-feedback-modal__keep" data-subscrpt-feedback-dismiss><?php esc_html_e( 'Keep subscription', 'subscription' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX: record a cancellation-feedback submission.
	 *
	 * Best-effort — the frontend proceeds with cancellation regardless of the
	 * outcome. Verifies the nonce and that the current user owns the subscription
	 * (or is an admin), snapshots the reason label so historic rows survive later
	 * reason edits, stores the row, and fires `subscrpt_cancellation_feedback_recorded`.
	 *
	 * @return void
	 */
	public function record_feedback() {
		check_ajax_referer( 'subscrpt_cancellation_feedback', 'nonce' );

		$subscription_id = isset( $_POST['subscription_id'] ) ? absint( wp_unslash( $_POST['subscription_id'] ) ) : 0;
		if ( $subscription_id <= 0 ) {
			wp_send_json_error( [ 'message' => 'invalid_subscription' ] );
		}

		$subs_post = get_post( $subscription_id );
		if ( ! $subs_post || 'subscrpt_order' !== $subs_post->post_type ) {
			wp_send_json_error( [ 'message' => 'invalid_subscription' ] );
		}

		$author_id = (int) $subs_post->post_author;
		if ( ! current_user_can( 'manage_options' ) && $author_id !== get_current_user_id() ) {
			wp_send_json_error( [ 'message' => 'forbidden' ] );
		}

		$reason_key = isset( $_POST['reason_key'] ) ? sanitize_key( wp_unslash( $_POST['reason_key'] ) ) : '';
		$comment    = isset( $_POST['comment'] ) ? sanitize_textarea_field( wp_unslash( $_POST['comment'] ) ) : '';

		// Snapshot the label from the current reason set so it survives later edits.
		$reason_label = '';
		foreach ( self::get_reasons() as $reason ) {
			if ( isset( $reason['key'] ) && (string) $reason['key'] === $reason_key ) {
				$reason_label = isset( $reason['label'] ) ? (string) $reason['label'] : '';
				break;
			}
		}

		global $wpdb;
		$table = $wpdb->prefix . 'subscrpt_cancellation_feedback';
		$data  = [
			'subscription_id' => $subscription_id,
			'customer_id'     => $author_id,
			'reason_key'      => $reason_key,
			'reason_label'    => $reason_label,
			'comment'         => $comment,
			'created_at'      => current_time( 'mysql', true ),
		];

		// One row per subscription: overwrite any previous feedback (e.g. after a
		// reactivate → cancel-again cycle) rather than accumulating a log, so the
		// row always reflects the latest cancellation reason.
		$existing_id = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}subscrpt_cancellation_feedback WHERE subscription_id = %d ORDER BY id DESC LIMIT 1",
				$subscription_id
			)
		);

		if ( $existing_id ) {
			// Clear any stray duplicates from earlier writes, keep the one we update.
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}subscrpt_cancellation_feedback WHERE subscription_id = %d AND id <> %d",
					$subscription_id,
					$existing_id
				)
			);
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				$data,
				[ 'id' => $existing_id ],
				[ '%d', '%d', '%s', '%s', '%s', '%s' ],
				[ '%d' ]
			);
			$data['id'] = $existing_id;
		} else {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				$data,
				[ '%d', '%d', '%s', '%s', '%s', '%s' ]
			);
			$data['id'] = (int) $wpdb->insert_id;
		}

		/**
		 * Fires after a cancellation-feedback row is stored.
		 *
		 * @param int   $subscription_id Subscription ID.
		 * @param array $data            Stored feedback row.
		 */
		do_action( 'subscrpt_cancellation_feedback_recorded', $subscription_id, $data );

		wp_send_json_success( [ 'id' => $data['id'] ] );
	}

	/**
	 * AJAX: the customer accepted the retention offer.
	 *
	 * Free owns the request - nonce, ownership, throttle - and asks for an offer
	 * through `subscrpt_cancellation_offer`. Free itself has nothing to give: the
	 * coupon is Pro's, so without a listener this reports no offer rather than
	 * promising a discount that never arrives.
	 *
	 * @return void
	 */
	public function claim_offer() {
		check_ajax_referer( 'subscrpt_cancellation_feedback', 'nonce' );

		$subscription_id = isset( $_POST['subscription_id'] ) ? absint( wp_unslash( $_POST['subscription_id'] ) ) : 0;
		if ( $subscription_id <= 0 ) {
			wp_send_json_error( [ 'message' => 'invalid_subscription' ] );
		}

		$subs_post = get_post( $subscription_id );
		if ( ! $subs_post || 'subscrpt_order' !== $subs_post->post_type ) {
			wp_send_json_error( [ 'message' => 'invalid_subscription' ] );
		}

		$author_id = (int) $subs_post->post_author;
		if ( ! current_user_can( 'manage_options' ) && $author_id !== get_current_user_id() ) {
			wp_send_json_error( [ 'message' => 'forbidden' ] );
		}

		/**
		 * Filters the retention offer handed to a cancelling customer.
		 *
		 * Return an array with a `code` to make the offer; anything falsy means no
		 * offer was issued and the customer continues to the reasons step.
		 *
		 * @param array|null $offer           The offer, or null when none is issued.
		 * @param int        $subscription_id Subscription ID.
		 * @param int        $customer_id     Subscription owner.
		 */
		$offer = apply_filters( 'subscrpt_cancellation_offer', null, $subscription_id, $author_id );

		if ( empty( $offer['code'] ) ) {
			wp_send_json_error( [ 'message' => 'no_offer' ] );
		}

		// Accepting the offer is a save, and the strongest kind - report it even
		// if the customer already dismissed the modal once today.
		delete_transient( self::save_throttle_key( $subscription_id ) );
		set_transient( self::save_throttle_key( $subscription_id ), 1, DAY_IN_SECONDS );

		$reason_key = isset( $_POST['reason_key'] ) ? sanitize_key( wp_unslash( $_POST['reason_key'] ) ) : '';

		$reason_label = '';
		foreach ( self::get_reasons() as $reason ) {
			if ( isset( $reason['key'] ) && (string) $reason['key'] === $reason_key ) {
				$reason_label = isset( $reason['label'] ) ? (string) $reason['label'] : '';
				break;
			}
		}

		do_action(
			'subscrpt_subscription_saved',
			$subscription_id,
			[
				'subscription_id' => $subscription_id,
				'customer_id'     => $author_id,
				'reason_key'      => $reason_key,
				'reason_label'    => $reason_label,
				'offer_accepted'  => true,
				'offer_code'      => (string) $offer['code'],
			]
		);

		wp_send_json_success(
			[
				'code'    => (string) $offer['code'],
				'message' => isset( $offer['message'] ) ? (string) $offer['message'] : '',
			]
		);
	}

	/**
	 * Transient guarding one save report per subscription per day.
	 *
	 * Every way out of the modal counts as a save - Keep subscription, the X, the
	 * overlay, Escape - so without this a customer idly opening and closing it
	 * would mail the store owner each time.
	 *
	 * @param int $subscription_id Subscription post ID.
	 * @return string
	 */
	protected static function save_throttle_key( $subscription_id ) {
		return 'subscrpt_save_reported_' . (int) $subscription_id;
	}

	/**
	 * AJAX: the customer backed out of cancelling.
	 *
	 * Records nothing - the reason list is only meaningful for an actual
	 * cancellation - but fires `subscrpt_subscription_saved` so the retention can
	 * be reported. Throttled to once a day per subscription.
	 *
	 * @return void
	 */
	public function record_save() {
		check_ajax_referer( 'subscrpt_cancellation_feedback', 'nonce' );

		$subscription_id = isset( $_POST['subscription_id'] ) ? absint( wp_unslash( $_POST['subscription_id'] ) ) : 0;
		if ( $subscription_id <= 0 ) {
			wp_send_json_error( [ 'message' => 'invalid_subscription' ] );
		}

		$subs_post = get_post( $subscription_id );
		if ( ! $subs_post || 'subscrpt_order' !== $subs_post->post_type ) {
			wp_send_json_error( [ 'message' => 'invalid_subscription' ] );
		}

		$author_id = (int) $subs_post->post_author;
		if ( ! current_user_can( 'manage_options' ) && $author_id !== get_current_user_id() ) {
			wp_send_json_error( [ 'message' => 'forbidden' ] );
		}

		$throttle = self::save_throttle_key( $subscription_id );
		if ( get_transient( $throttle ) ) {
			wp_send_json_success( [ 'throttled' => true ] );
		}
		set_transient( $throttle, 1, DAY_IN_SECONDS );

		$reason_key = isset( $_POST['reason_key'] ) ? sanitize_key( wp_unslash( $_POST['reason_key'] ) ) : '';

		$reason_label = '';
		foreach ( self::get_reasons() as $reason ) {
			if ( isset( $reason['key'] ) && (string) $reason['key'] === $reason_key ) {
				$reason_label = isset( $reason['label'] ) ? (string) $reason['label'] : '';
				break;
			}
		}

		$data = [
			'subscription_id' => $subscription_id,
			'customer_id'     => $author_id,
			'reason_key'      => $reason_key,
			'reason_label'    => $reason_label,
			'offer_accepted'  => false,
		];

		/**
		 * Fires when a customer opens the cancellation modal and backs out.
		 *
		 * Throttled to once a day per subscription, so a listener may treat each
		 * call as a distinct retention event.
		 *
		 * @param int   $subscription_id Subscription ID.
		 * @param array $data            Save context: the reason that had been
		 *                               selected (may be empty) and whether a
		 *                               retention offer was accepted.
		 */
		do_action( 'subscrpt_subscription_saved', $subscription_id, $data );

		wp_send_json_success( [ 'saved' => true ] );
	}

	/**
	 * Get Settings
	 *
	 * @param string $id Setting ID.
	 */
	public static function get_settings( $id = '' ) {
		$settings = [
			'subscrpt_cancellation_delay'            => subscrpt_pro_activated() ? get_option( 'subscrpt_cancellation_delay', '24h' ) : '24h',
			'subscrpt_cancellation_feedback_enabled' => get_option( 'subscrpt_cancellation_feedback_enabled', '1' ),
			'subscrpt_cancellation_feedback_comment' => get_option( 'subscrpt_cancellation_feedback_comment', '1' ),
		];
		return ! empty( $id ) ? $settings[ $id ] ?? false : $settings;
	}

	/**
	 * Whether the cancellation-feedback prompt is enabled.
	 *
	 * A free feature — works with or without Pro.
	 *
	 * @return bool
	 */
	public static function is_feedback_enabled() {
		return '1' === self::get_settings( 'subscrpt_cancellation_feedback_enabled' );
	}

	/**
	 * Whether the optional comment box is shown in the feedback form. Defaults to on.
	 *
	 * @return bool
	 */
	public static function is_feedback_comment_enabled() {
		return '1' === self::get_settings( 'subscrpt_cancellation_feedback_comment' );
	}

	/**
	 * Built-in default cancellation reasons.
	 *
	 * Used when no Pro-managed reason list is set. Filterable so Pro and
	 * integrations can adjust the defaults.
	 *
	 * @return array<int,array{key:string,label:string}>
	 */
	public static function default_reasons() {
		$reasons = [
			[
				'key'   => 'too_expensive',
				'label' => __( 'Too expensive', 'subscription' ),
			],
			[
				'key'   => 'missing_features',
				'label' => __( 'Missing features I need', 'subscription' ),
			],
			[
				'key'   => 'found_alternative',
				'label' => __( 'Found a better alternative', 'subscription' ),
			],
			[
				'key'   => 'no_longer_needed',
				'label' => __( 'No longer needed', 'subscription' ),
			],
			[
				'key'   => 'technical_issues',
				'label' => __( 'Technical issues', 'subscription' ),
			],
			[
				'key'   => 'other',
				'label' => __( 'Other', 'subscription' ),
			],
		];

		/**
		 * Filter the built-in default cancellation reasons.
		 *
		 * @param array $reasons List of { key, label } reason entries.
		 */
		return apply_filters( 'subscrpt_cancellation_default_reasons', $reasons );
	}

	/**
	 * Resolve the active cancellation reason list.
	 *
	 * Uses the Pro-managed `subscrpt_cancellation_reasons` option when Pro is
	 * active and the option is non-empty; otherwise falls back to the built-in
	 * defaults so the feature works without Pro.
	 *
	 * @return array<int,array{key:string,label:string}>
	 */
	public static function get_reasons() {
		if ( subscrpt_pro_activated() ) {
			$reasons = get_option( 'subscrpt_cancellation_reasons', [] );
			if ( ! empty( $reasons ) && is_array( $reasons ) ) {
				return $reasons;
			}
		}

		return self::default_reasons();
	}

	/**
	 * Record when a pending cancellation should become final.
	 *
	 * Runs whenever a subscription enters `pe_cancelled` (frontend, admin, or REST).
	 * If the resolved time is already due, the subscription is cancelled immediately.
	 *
	 * @param int $subscription_id Subscription ID.
	 * @return void
	 */
	public function schedule_cancellation( $subscription_id ) {
		$subscription_id = (int) $subscription_id;

		/**
		 * Filter the timestamp at which a pending cancellation becomes a full cancellation.
		 *
		 * Return a Unix timestamp. A value at or before the current time cancels the
		 * subscription immediately. Defaults to 24 hours from now.
		 *
		 * @param int $cancel_at       Unix timestamp for final cancellation.
		 * @param int $subscription_id Subscription ID.
		 */
		$cancel_at = (int) apply_filters( 'subscrpt_cancellation_time', time() + DAY_IN_SECONDS, $subscription_id );

		if ( $cancel_at <= time() ) {
			$this->cancel( $subscription_id );
			return;
		}

		update_post_meta( $subscription_id, self::CANCEL_AT_META, $cancel_at );
	}

	/**
	 * Hourly sweep: finalise any pending cancellations whose time has come.
	 *
	 * Picks up subscriptions whose `_subscrpt_cancel_at` is due, plus legacy
	 * `pe_cancelled` subscriptions (created before this meta existed) whose billing
	 * period has ended.
	 *
	 * @return void
	 */
	public function process_due_cancellations() {
		$subscriptions = get_posts(
			[
				'post_type'   => 'subscrpt_order',
				'post_status' => [ 'pe_cancelled' ],
				'fields'      => 'ids',
				'numberposts' => -1,
				'meta_query'  => [
					'relation' => 'OR',
					[
						'key'     => self::CANCEL_AT_META,
						'value'   => time(),
						'compare' => '<=',
						'type'    => 'NUMERIC',
					],
					[
						'relation' => 'AND',
						[
							'key'     => self::CANCEL_AT_META,
							'compare' => 'NOT EXISTS',
						],
						[
							'key'     => '_subscrpt_next_date',
							'value'   => time(),
							'compare' => '<=',
							'type'    => 'NUMERIC',
						],
					],
				],
			]
		);

		if ( empty( $subscriptions ) ) {
			return;
		}

		// Ensure the mailer is ready so the cancellation email can be sent.
		if ( function_exists( 'WC' ) && WC()->mailer() ) {
			foreach ( $subscriptions as $subscription_id ) {
				$this->cancel( (int) $subscription_id );
			}
		}
	}

	/**
	 * Finalise the cancellation of a single subscription.
	 *
	 * Guards against subscriptions that are no longer pending (e.g. reactivated).
	 *
	 * @param int $subscription_id Subscription ID.
	 * @return void
	 */
	public function cancel( $subscription_id ) {
		$subscription_id = (int) $subscription_id;

		if ( 'pe_cancelled' === get_post_status( $subscription_id ) ) {
			Action::status( 'cancelled', $subscription_id );
		}

		delete_post_meta( $subscription_id, self::CANCEL_AT_META );
	}

	/**
	 * Drop a scheduled cancellation when a subscription is reactivated.
	 *
	 * @param int $subscription_id Subscription ID.
	 * @return void
	 */
	public function clear_scheduled_cancellation( $subscription_id ) {
		delete_post_meta( (int) $subscription_id, self::CANCEL_AT_META );
	}

	/**
	 * Show a notice on the subscription details page when a cancellation is pending.
	 *
	 * Uses `_subscrpt_cancel_at` (the resolved final-cancellation time), falling back
	 * to the next renewal date for legacy subscriptions.
	 *
	 * @param int $subscription_id Subscription ID.
	 * @return void
	 */
	public function display_pending_cancellation_notice( $subscription_id ) {
		if ( 'pe_cancelled' !== get_post_status( $subscription_id ) ) {
			return;
		}

		$cancel_at = (int) get_post_meta( $subscription_id, self::CANCEL_AT_META, true );
		if ( ! $cancel_at ) {
			$cancel_at = (int) get_post_meta( $subscription_id, '_subscrpt_next_date', true );
		}

		wp_enqueue_style( 'subscrpt_cancellation_css', SUBSCRPT_ASSETS . '/css/cancellation.css', [], SUBSCRPT_VERSION );
		?>
		<div class="subscrpt-pending-cancel-notice" role="status">
			<span class="subscrpt-pending-cancel-notice__icon" aria-hidden="true">
				<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
			</span>
			<div class="subscrpt-pending-cancel-notice__body">
				<p class="subscrpt-pending-cancel-notice__text">
					<?php
					if ( $cancel_at ) {
						$effective = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $cancel_at );
						printf(
							/* translators: %s: cancellation date and time. */
							esc_html__( 'This subscription is scheduled to be cancelled on %s. You can continue accessing it until then.', 'subscription' ),
							'<strong>' . esc_html( $effective ) . '</strong>'
						);
					} else {
						esc_html_e( 'This subscription is scheduled to be cancelled at the end of the current billing period. You can continue accessing it until then.', 'subscription' );
					}
					?>
				</p>
			</div>
		</div>
		<?php
	}
}
