<?php

/**
 * Compute the visible page list for a paginator (current ± 1 window with
 * ellipsis for wider gaps, page 1 and the last page always pinned).
 *
 * Mirrors the JS algorithm used by WPSubsPager so server- and client-side
 * markup agree on what to render.
 *
 * @param int $current Current page (1-indexed). Clamped to [1, total].
 * @param int $total   Total number of pages. Floored at 1.
 * @return array<int|null> Page numbers in display order; `null` is an ellipsis.
 */
function wpsubs_pager_page_range( int $current, int $total ): array {
	$total   = max( 1, $total );
	$first   = 1;
	$last    = $total;
	$current = max( 1, min( $total, $current ) );

	// Slide a 3-number window centred on the current page, excluding the pinned
	// first/last pages so they don't show twice.
	$near_start = max( 2, $current - 1 );
	$near_end   = min( $last - 1, $current + 1 );

	$nearby = array();
	for ( $i = $near_start; $i <= $near_end; $i++ ) {
		$nearby[] = $i;
	}

	// Dedupe while preserving order (first/last may already be in $nearby).
	$parts = array();
	$seen  = array();
	$push  = function ( int $n ) use ( &$parts, &$seen ) {
		if ( ! isset( $seen[ $n ] ) ) {
			$seen[ $n ] = true;
			$parts[]    = $n;
		}
	};
	$push( $first );
	foreach ( $nearby as $n ) {
		$push( $n );
	}
	if ( $last > $first ) {
		$push( $last );
	}

	// Emit ellipsis for gaps > 1 between consecutive visible numbers.
	$range = array();
	foreach ( $parts as $j => $p ) {
		if ( $j > 0 ) {
			$gap = $p - $parts[ $j - 1 ];
			if ( 2 === $gap ) {
				$range[] = $parts[ $j - 1 ] + 1; // single hidden page — surface it
			} elseif ( $gap > 2 ) {
				$range[] = null; // ellipsis
			}
		}
		$range[] = $p;
	}
	return $range;
}

/**
 * Render a WPSubscription paginator footer (info text + prev / next / numbered
 * buttons + ellipsis). Single source of truth used by the subscriptions list
 * (server-side) and the subscription details cards (hydrated by JS).
 *
 * Pair with `WPSubsPager` in `assets/js/admin-components.js` for auto-init on
 * `.wpsubs-pager[data-wpsubs-pager]` elements. Pass `link_mode => 'cb'` from
 * the details page so buttons trigger the JS controller instead of navigating.
 *
 * Markup follows the BEM classes defined in `admin-components.css`:
 *   .wpsubs-pagination
 *     .wpsubs-pagination__info
 *     .wpsubs-pagination__nav
 *       .wpsubs-pagination__btn  (mods: --active | --disabled | --ellipsis)
 *
 * @param array $args {
 *     @type int    $current       Current page (1-indexed). Default 1.
 *     @type int    $total         Total number of pages. Default 1.
 *     @type bool   $info          Whether to render the "Showing X–Y of Z" info
 *                                 text alongside the buttons. Default false (only
 *                                 buttons are rendered).
 *     @type int    $per_page      Items per page (used when $info is true).
 *     @type int    $item_count    Total items (defaults to total * per_page).
 *     @type string $base_url      Base URL for server-side links (link_mode=url).
 *                                 The component appends ?paged= and ?per_page= query args.
 *                                 Ignored if `link_url_callback` is provided.
 *     @type callable $link_url_callback Optional callable `function( int $page ): string`
 *                                 that returns the URL for a given page. When supplied,
 *                                 it replaces the default `add_query_arg( $base_url )` builder
 *                                 and lets callers (e.g. Pro templates) build URLs with
 *                                 admin_url() and arbitrary query args. The returned URL is
 *                                 not re-escaped — callers must escape with esc_url() before
 *                                 returning.
 *     @type string $link_mode     'url' (default, server <a href>) or 'cb' (<button data-page>).
 *     @type string $info_format   sprintf-style override for the info text. Default
 *                                 'Showing %1$s–%2$s of %3$s'. Pass '%3$s subscriptions'
 *                                 etc. for richer copy.
 *     @type string $aria_label    ARIA label on the root. Default 'Pagination'.
 *     @type string $class         Extra classes on the root element.
 *     @type string $id            Optional id on the root.
 *     @type array  $attrs         Extra HTML attributes (key => value) on the root.
 *     @type string $context       Free-form hint passed to filters. Default ''.
 * }
 */
function wpsubs_render_pager( array $args ): void {
	$args = wp_parse_args(
		$args,
		array(
			'current'           => 1,
			'total'             => 1,
			'info'              => false,
			'per_page'          => 10,
			'item_count'        => 0,
			'base_url'          => '',
			'link_url_callback' => null,
			'link_mode'         => 'url',
			'info_format'       => '',
			'aria_label'        => __( 'Pagination', 'subscription' ),
			'class'             => '',
			'id'                => '',
			'attrs'             => array(),
			'context'           => '',
		)
	);

	$current   = max( 1, (int) $args['current'] );
	$total     = max( 1, (int) $args['total'] );
	$per_page  = max( 1, (int) $args['per_page'] );
	$show_info = (bool) $args['info'];

	// Item window for "Showing X–Y of Z" (only used when $info is true).
	if ( $show_info ) {
		// Callers that know the real item_count pass it; for cards where the
		// row count isn't known at render time (Pro activities), item_count=0
		// keeps the info text minimal ("0–0 of 0") and the JS rehydrates it
		// once rows are visible.
		if ( $args['item_count'] > 0 ) {
			$item_total = (int) $args['item_count'];
		} else {
			$item_total = 0;
		}
		$start_item = $item_total > 0 ? ( ( $current - 1 ) * $per_page ) + 1 : 0;
		$end_item   = $item_total > 0 ? min( $current * $per_page, $item_total ) : 0;
	} else {
		$start_item = 0;
		$end_item   = 0;
		$item_total = 0;
	}

	$root_classes = 'wpsubs-pager wpsubs-pagination';
	if ( 'cb' !== $args['link_mode'] ) {
		$root_classes .= ' wpsubs-pager--links';
	}
	if ( $args['class'] ) {
		$root_classes .= ' ' . $args['class'];
	}

	$attrs_out = '';
	foreach ( $args['attrs'] as $name => $value ) {
		$attrs_out .= ' ' . esc_attr( $name ) . '="' . esc_attr( $value ) . '"';
	}

	$page_range = wpsubs_pager_page_range( $current, $total );
	$has_prev   = $current > 1;
	$has_next   = $current < $total;

	// Filterable info string (only composed when $info is true). The default
	// keeps the previous hard-coded copy so the existing POT entry stays the
	// source of truth.
	$info_text = '';
	if ( $show_info ) {
		// translators: Pagination: %1$s: first item, %2$s: last item, %3$s: total items
		$info_format = '' !== $args['info_format'] ? $args['info_format'] : __( 'Showing %1$s–%2$s of %3$s', 'subscription' );
		$info_text   = sprintf(
			$info_format,
			number_format_i18n( $start_item ),
			number_format_i18n( $end_item ),
			number_format_i18n( $item_total )
		);
		/**
		 * Filter the paginator info text (right-hand label, e.g. "Showing 1–10 of 96").
		 *
		 * @param string $info_text  Composed info text.
		 * @param int    $start_item First item on the current page (1-indexed, 0 if empty).
		 * @param int    $end_item   Last item on the current page (1-indexed, 0 if empty).
		 * @param int    $item_total Total number of items across all pages.
		 * @param string $context    Caller-supplied hint from `$args['context']`.
		 */
		$info_text = apply_filters( 'wpsubs_pager_info_text', $info_text, $start_item, $end_item, $item_total, $args['context'] );
	}

	$build_link = function ( int $page ) use ( $args ): string {
		/**
		 * Filter the URL built for a paginator page link (server mode only).
		 *
		 * @param string $url      Computed URL (may be empty).
		 * @param int    $page     Target page number (1-indexed).
		 * @param array  $args     Pager args passed to wpsubs_render_pager().
		 */
		$url = '';
		if ( is_callable( $args['link_url_callback'] ) ) {
			// Caller-supplied builder takes over entirely; it must escape itself.
			$url = (string) call_user_func( $args['link_url_callback'], $page );
		} elseif ( $args['base_url'] ) {
			$url = add_query_arg(
				array(
					'paged'    => $page,
					'per_page' => max( 1, (int) $args['per_page'] ),
				),
				$args['base_url']
			);
			$url = esc_url( $url );
		}
		return apply_filters( 'wpsubs_pager_link_url', $url, $page, $args );
	};

	$render_page_btn = function ( int $p ) use ( $current, $build_link, $args ): string {
		$is_active = $p === $current;
		$classes   = 'wpsubs-pagination__btn';
		if ( $is_active ) {
			$classes .= ' wpsubs-pagination__btn--active';
		}
		$label = (string) $p;
		/**
		 * Filter the label rendered for a paginator page button.
		 *
		 * @param string $label  Default label (the page number as a string).
		 * @param int    $p      Page number being rendered.
		 * @param bool   $is_active Whether this is the current page.
		 */
		$label = apply_filters( 'wpsubs_pager_page_label', $label, $p, $is_active );

		if ( 'cb' === $args['link_mode'] ) {
			return '<button type="button" class="' . esc_attr( $classes )
				. '" data-page="' . esc_attr( (string) $p ) . '"'
				. ( $is_active ? ' aria-current="page"' : '' )
				. '>' . esc_html( $label ) . '</button>';
		}
		return '<a href="' . $build_link( $p )
			. '" class="' . esc_attr( $classes ) . '"'
			. ( $is_active ? ' aria-current="page"' : '' )
			. '>' . esc_html( $label ) . '</a>';
	};

	$render_ellipsis = function (): string {
		return '<span class="wpsubs-pagination__btn wpsubs-pagination__btn--ellipsis" aria-hidden="true">…</span>';
	};

	$render_prev = function () use ( $has_prev, $current, $build_link, $args ): string {
		$page  = $current - 1;
		$label = '&#8249;';
		$attrs = ' aria-label="' . esc_attr__( 'Previous page', 'subscription' ) . '"';
		if ( $has_prev ) {
			if ( 'cb' === $args['link_mode'] ) {
				return '<button type="button" class="wpsubs-pagination__btn" data-page="' . esc_attr( (string) $page ) . '"' . $attrs . '>' . $label . '</button>';
			}
			return '<a href="' . $build_link( $page ) . '" class="wpsubs-pagination__btn"' . $attrs . '>' . $label . '</a>';
		}
		return '<span class="wpsubs-pagination__btn wpsubs-pagination__btn--disabled" aria-hidden="true">' . $label . '</span>';
	};

	$render_next = function () use ( $has_next, $current, $build_link, $args ): string {
		$page  = $current + 1;
		$label = '&#8250;';
		$attrs = ' aria-label="' . esc_attr__( 'Next page', 'subscription' ) . '"';
		if ( $has_next ) {
			if ( 'cb' === $args['link_mode'] ) {
				return '<button type="button" class="wpsubs-pagination__btn" data-page="' . esc_attr( (string) $page ) . '"' . $attrs . '>' . $label . '</button>';
			}
			return '<a href="' . $build_link( $page ) . '" class="wpsubs-pagination__btn"' . $attrs . '>' . $label . '</a>';
		}
		return '<span class="wpsubs-pagination__btn wpsubs-pagination__btn--disabled" aria-hidden="true">' . $label . '</span>';
	};
	?>
	<div class="<?php echo esc_attr( $root_classes ); ?>"
		<?php
		if ( $args['id'] ) :
			?>
			id="<?php echo esc_attr( $args['id'] ); ?>"<?php endif; ?>
		role="navigation"
		aria-label="<?php echo esc_attr( $args['aria_label'] ); ?>"
		data-wpsubs-pager
		data-current="<?php echo esc_attr( (string) $current ); ?>"
		data-total="<?php echo esc_attr( (string) $total ); ?>"
		data-per-page="<?php echo esc_attr( (string) $per_page ); ?>"
		<?php
		if ( 'cb' === $args['link_mode'] ) :
			?>
			data-link-mode="cb"<?php endif; ?>
		<?php
		if ( $args['info_format'] ) :
			?>
			data-info-format="<?php echo esc_attr( $args['info_format'] ); ?>"<?php endif; ?>
		<?php echo $attrs_out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from esc_attr() parts above. ?>
	>
		<?php if ( $show_info ) : ?>
			<span class="wpsubs-pagination__info"><?php echo esc_html( $info_text ); ?></span>
		<?php endif; ?>
		<div class="wpsubs-pagination__nav">
			<?php echo $render_prev(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output built from safe esc_*() helpers. ?>
			<?php
			foreach ( $page_range as $p ) :
				if ( null === $p ) {
					echo $render_ellipsis(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup is fully escaped.
				} else {
					echo $render_page_btn( (int) $p ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup is fully escaped.
				}
			endforeach;
			?>
			<?php echo $render_next(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output built from safe esc_*() helpers. ?>
		</div>
	</div>
	<?php
}


/**
 * Render a WooCommerce-style multiselect field.
 *
 * @param array $field {
 *     Field arguments.
 *
 *     @type string       $id                Required. Meta key / input ID.
 *     @type string       $label             Field label.
 *     @type array        $options           Key => Label pairs for options.
 *     @type array|string $selected          Optional. Selected value(s). Array, JSON, or CSV.
 *     @type string       $desc_tip          Optional. Description tooltip text.
 *     @type string       $description       Optional. Field description text.
 *     @type string       $wrapper_class     Optional. Extra wrapper classes.
 *     @type string       $class             Optional. Extra <select> classes.
 *     @type string       $name              Optional. Input name. Defaults to $id.'[]'.
 * }
 */
function subscrpt_multiselect_field( $field ) {
	$defaults = [
		'id'            => '',
		'label'         => '',
		'options'       => [],
		'selected'      => [],
		'desc_tip'      => false,
		'description'   => '',
		'wrapper_class' => '',
		'wrapper_style' => '',
		'class'         => 'wc-enhanced-select',
		'style'         => '',
		'name'          => '',
	];

	$field = wp_parse_args( $field, $defaults );

	if ( empty( $field['id'] ) ) {
		return;
	}

	$id          = esc_attr( $field['id'] );
	$name        = $field['name'] ? $field['name'] : $id . '[]';
	$label       = esc_html( $field['label'] );
	$description = $field['description'];
	$desc_tip    = $field['desc_tip'];

	// Normalize selected values into array.
	$selected = [];
	if ( is_array( $field['selected'] ) ) {
		$selected = $field['selected'];
	} elseif ( is_string( $field['selected'] ) && $field['selected'] !== '' ) {
		if ( false !== strpos( $field['selected'], '[' ) ) {
			$tmp      = json_decode( $field['selected'], true );
			$selected = is_array( $tmp ) ? $tmp : [];
		} else {
			$selected = array_filter( array_map( 'trim', explode( ',', $field['selected'] ) ) );
		}
	}

	// Build <option> list.
	$options_html = '';
	foreach ( $field['options'] as $key => $text ) {
		$is_selected   = in_array( (string) $key, (array) $selected, true ) ? ' selected="selected"' : '';
		$options_html .= sprintf(
			'<option value="%s"%s>%s</option>',
			esc_attr( $key ),
			$is_selected,
			esc_html( $text )
		);
	}

	$tooltip_html = '';
	if ( $desc_tip && $description ) {
		$tooltip_html = wc_help_tip( $description );
	}

	$description_html = '';
	if ( $description && ! $desc_tip ) {
		$description_html = '<span class="description">' . wp_kses_post( $description ) . '</span>';
	}

	// ? Escaped intentionally.
	// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
	?>
	<p 
		class="form-field <?php echo esc_attr( $id . '_field ' . ( $field['wrapper_class'] ) ); ?>" 
		style="<?php echo esc_attr( $field['wrapper_style'] ); ?>"
	>
		<label for="<?php echo esc_attr( $id ); ?>">
			<?php echo esc_html( $label ); ?>
		</label>

		<?php echo $tooltip_html; ?>

		<select 
			multiple="multiple" 
			id="<?php echo esc_attr( $id ); ?>" 
			name="<?php echo esc_attr( $name ); ?>" 
			class="<?php echo esc_attr( $field['class'] ); ?>" 
			style="<?php echo esc_attr( $field['style'] ); ?>" 
		>
			<?php echo $options_html; ?>
		</select>
		
		<?php echo $description_html; ?>
	</p>
	<?php
	// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Render a preview for pages that require WPSubscription Pro, with a blurred background image and a call-to-action overlay.
 *
 * @param array $args Preview arguments.
 */
function subscrpt_render_page_preview( array $args = [] ) {
	$defaults = [
		'preview_image_url' => SUBSCRPT_ASSETS . '/images/previews/subscrpt-health-preview.png',
		'cta_title'         => __( 'Upgrade to WPSubscription Pro', 'subscription' ),
		'cta_description'   => __( 'This page requires WPSubscription Pro. Unlock advanced features, priority support, and more with WPSubscription Pro.', 'subscription' ),
		'cta_button_text'   => __( '⚡ Upgrade to Pro', 'subscription' ),
		'cta_button_url'    => 'https://wpsubscription.co/?utm_source=plugin&utm_medium=admin&utm_campaign=upgrade_pro',
	];

	$args = wp_parse_args( $args, $defaults );

	ob_start();
	?>
		<div style="position: relative;">
			<div style="filter:blur(4px);pointer-events:none;">
				<div style="max-width:1240px;margin:32px auto 0 auto;">
					<img
						src="<?php echo esc_url( $args['preview_image_url'] ); ?>"
						alt="<?php esc_attr_e( 'page preview', 'subscription' ); ?>"
						style="width:100%;display:block;"
					/>
				</div>
			</div>
			<div style="position:absolute;inset:0;display:flex;align-items:top;justify-content:center;padding:100px 32px 32px;">
				<div style="height:fit-content;background:#fff;border-radius:12px;padding:28px 32px;text-align:center;max-width:440px;box-shadow:0 8px 48px rgba(0,0,0,0.22);">

					<!-- Lock icon with radial glow -->
					<div style="position:relative;display:flex;align-items:center;justify-content:center;margin-bottom:20px;">
						<div style="position:absolute;width:100px;height:100px;background:radial-gradient(circle,var(--wpsubs-brand-ring) 0%,transparent 70%);border-radius:50%;"></div>
						<div style="position:relative;width:56px;height:56px;border:1.5px solid var(--wpsubs-brand);border-radius:14px;display:flex;align-items:center;justify-content:center;background:#fff;">
							<svg width="24" height="24" fill="none" viewBox="0 0 24 24" style="stroke:var(--wpsubs-brand);" stroke-width="2" aria-hidden="true">
								<rect x="5" y="11" width="14" height="10" rx="2"/>
								<path stroke-linecap="round" d="M8 11V7a4 4 0 018 0v4"/>
							</svg>
						</div>
					</div>

					<!-- Title -->
					<div style="font-size:22px;font-weight:700;color:#111;margin-bottom:10px;line-height:1.3;">
						<?php echo esc_html( $args['cta_title'] ); ?>
					</div>

					<!-- Subtitle -->
					<div style="font-size:14px;color:#6b7280;margin-bottom:20px;line-height:1.6;">
						<?php echo esc_html( $args['cta_description'] ); ?>
					</div>

					<!-- CTA button -->
					<a href="<?php echo esc_url( $args['cta_button_url'] ); ?>" target="_blank" style="display:flex;align-items:center;justify-content:center;gap:8px;background:var(--wpsubs-brand);color:#fff;font-size:15px;font-weight:600;padding:14px 28px;border-radius:8px;text-decoration:none;">
						<?php echo esc_html( $args['cta_button_text'] ); ?>
					</a>
				</div>
			</div>
		</div>
	<?php
	return ob_get_clean();
}

/**
 * Render an Advanced Select component.
 *
 * Outputs a styled trigger-button + dropdown that replaces a native <select>.
 * A hidden <input> carries the selected value for form submission.
 * JS (admin-components.js WPSubsAdvSelect) handles open/close and selection.
 *
 * @param array $args {
 *   @type string   $name          Hidden input name attribute.  Required.
 *   @type string   $placeholder   Trigger label when nothing is selected.
 *   @type string   $value         Initial hidden-input value (default: '').
 *   @type array    $options       Each item: {
 *                                   string  value      Value submitted on selection.
 *                                   string  label      Display text.
 *                                   bool    danger     Red destructive style.
 *                                   string  confirm    JS confirm() message before selecting.
 *                                   bool    divider    Render a divider BEFORE this item.
 *                                   bool    disabled   Non-selectable item.
 *                                 }
 *   @type string   $align         Menu alignment: 'left' (default) or 'right'.
 *   @type string   $id            Optional id on the root element.
 *   @type string   $class         Extra classes on the root element.
 * }
 */
function wpsubs_render_adv_select( array $args ): void {
	$args = wp_parse_args(
		$args,
		array(
			'name'        => '',
			'placeholder' => __( 'Select', 'subscription' ),
			'value'       => '',
			'options'     => array(),
			'align'       => 'left',
			'id'          => '',
			'class'       => '',
			'attrs'       => array(),
		)
	);

	$root_classes = 'wpsubs-adv-select wpsubs-adv-select--' . ( 'right' === $args['align'] ? 'right' : 'left' );
	if ( $args['class'] ) {
		$root_classes .= ' ' . $args['class'];
	}

	// Resolve trigger label: use matching option's label when a value is already set.
	$trigger_label = $args['placeholder'];
	$current_value = (string) $args['value'];
	if ( '' !== $current_value && '-1' !== $current_value ) {
		foreach ( $args['options'] as $opt ) {
			if ( (string) ( $opt['value'] ?? '' ) === $current_value ) {
				$trigger_label = $opt['label'] ?? $args['placeholder'];
				break;
			}
		}
	}

	$chevron_svg = '<svg class="wpsubs-adv-select__chevron" xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 9l6 6 6-6"/></svg>';
	?>
	<div class="<?php echo esc_attr( $root_classes ); ?>"
		<?php
		if ( $args['id'] ) :
			?>
			id="<?php echo esc_attr( $args['id'] ); ?>"<?php endif; ?>
		data-placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>"
		data-default-value="<?php echo esc_attr( $args['value'] ); ?>"
		<?php
		foreach ( $args['attrs'] as $attr_name => $attr_value ) :
			echo esc_attr( $attr_name ) . '="' . esc_attr( $attr_value ) . '" '; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Both parts escaped.
		endforeach;
		?>
	>
		<button type="button" class="wpsubs-adv-select__trigger" aria-haspopup="listbox" aria-expanded="false">
			<span class="wpsubs-adv-select__label"><?php echo esc_html( $trigger_label ); ?></span>
			<?php echo $chevron_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</button>

		<div class="wpsubs-adv-select__menu" role="listbox">
			<?php
			foreach ( $args['options'] as $option ) :
				$option = wp_parse_args(
					$option,
					array(
						'value'    => '',
						'label'    => '',
						'danger'   => false,
						'confirm'  => '',
						'divider'  => false,
						'disabled' => false,
					)
				);
				if ( $option['divider'] ) :
					?>
					<div class="wpsubs-adv-select__divider"></div>
					<?php
					continue;
				endif;
				?>
				<button
					type="button"
					class="wpsubs-adv-select__item<?php echo $option['danger'] ? ' wpsubs-adv-select__item--danger' : ''; ?>"
					data-value="<?php echo esc_attr( $option['value'] ); ?>"
					<?php
					if ( $option['confirm'] ) :
						?>
						data-confirm="<?php echo esc_attr( $option['confirm'] ); ?>"<?php endif; ?>
					<?php
					if ( $option['disabled'] ) :
						?>
						data-disabled<?php endif; ?>
					role="option"
				>
					<span class="wpsubs-adv-select__item-label"><?php echo esc_html( $option['label'] ); ?></span>
				</button>
			<?php endforeach; ?>
		</div>

		<?php if ( $args['name'] ) : ?>
			<input type="hidden" name="<?php echo esc_attr( $args['name'] ); ?>" value="<?php echo esc_attr( $args['value'] ); ?>">
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Render a "per page" selector as an Advanced Select.
 *
 * Wraps wpsubs_render_adv_select() with the project-standard page-size options
 * (10 / 20 / 50 / 100) so every list uses the same choices. Everything is
 * overridable: pass `options` (an array of ints, or full adv-select option
 * arrays) to change the choices, `value` for the initial selection, and any
 * other wpsubs_render_adv_select() arg (name, align, id, class, attrs) — they
 * pass straight through.
 *
 * @param array $args {
 *   @type string $name    Hidden input name.
 *   @type string $value   Initial value. Default '10'.
 *   @type array  $options Page sizes (ints) or option arrays. Default 10/20/50/100.
 *   @type mixed  ...       Any other wpsubs_render_adv_select() arg.
 * }
 *
 * @return void
 */
function wpsubs_render_per_page_select( array $args = array() ): void {
	$args = array_merge(
		array(
			'value'   => '10',
			'options' => array( 10, 20, 50, 100 ),
		),
		$args
	);

	// Expand plain int page sizes into adv-select option arrays.
	$options = array();
	foreach ( $args['options'] as $option ) {
		if ( is_array( $option ) ) {
			$options[] = $option;
			continue;
		}
		$options[] = array(
			'value' => (string) $option,
			/* translators: %d: number of items shown per page. */
			'label' => sprintf( __( '%d / page', 'subscription' ), (int) $option ),
		);
	}

	$args['options'] = $options;
	$args['value']   = (string) $args['value'];

	wpsubs_render_adv_select( $args );
}

/**
 * Render a tag/pill select input with an inline filter and filterable dropdown.
 * Supports single and multiple selection. No external dependencies.
 *
 * JS: WPSubsTagSelect (admin-components.js) auto-inits elements.
 * Event fired on root: `wpsubs:select` — detail: { value, label, selected }
 *
 * @param array $args {
 *   string       $name        Form field name (base name, without [] suffix).
 *   string       $placeholder Input placeholder shown when nothing is selected.
 *   string|array $value       Current value(s). Array for multiple, string for single.
 *   array        $options     Options: array of { value, label, disabled? }.
 *   bool         $multiple    Enable multi-select mode.
 *   string       $id          Optional root element id.
 *   string       $class       Extra CSS classes for the root element.
 *   array        $attrs       Extra HTML attributes for the root element.
 * }
 */
function wpsubs_render_tag_select( array $args ): void {
	$args = wp_parse_args(
		$args,
		array(
			'name'        => '',
			'placeholder' => __( 'Select...', 'subscription' ),
			'value'       => '',
			'options'     => array(),
			'multiple'    => false,
			'id'          => '',
			'class'       => '',
			'attrs'       => array(),
		)
	);

	$multiple      = (bool) $args['multiple'];
	$current_value = $multiple ? (array) $args['value'] : (string) $args['value'];

	if ( $multiple ) {
		$selected_values = array_filter( array_map( 'strval', $current_value ), fn( $v ) => '' !== $v );
	} else {
		$selected_values = ( '' !== $current_value ) ? array( $current_value ) : array();
	}

	// Map selected values to their labels for pill rendering.
	$selected_labels = array();
	foreach ( $args['options'] as $opt ) {
		$opt_val = (string) ( $opt['value'] ?? '' );
		if ( in_array( $opt_val, $selected_values, true ) ) {
			$selected_labels[ $opt_val ] = $opt['label'] ?? $opt_val;
		}
	}

	$root_classes = 'wpsubs-tag-select';
	if ( $multiple ) {
		$root_classes .= ' wpsubs-tag-select--multi';
	}
	if ( $args['class'] ) {
		$root_classes .= ' ' . $args['class'];
	}

	$has_pills   = ! empty( $selected_values );
	$placeholder = $has_pills ? '' : esc_attr( $args['placeholder'] );

	$chevron_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 9l6 6 6-6"/></svg>';
	?>
	<div
		class="<?php echo esc_attr( $root_classes ); ?>"
		<?php
		if ( $args['id'] ) :
			?>
			id="<?php echo esc_attr( $args['id'] ); ?>"<?php endif; ?>
		data-placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>"
		data-name="<?php echo esc_attr( $args['name'] ); ?>"
		<?php
		if ( $multiple ) :
			?>
			data-multiple="1"<?php endif; ?>
		<?php
		foreach ( $args['attrs'] as $attr_name => $attr_value ) :
			echo esc_attr( $attr_name ) . '="' . esc_attr( $attr_value ) . '" '; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Both parts escaped.
		endforeach;
		?>
	>
		<div class="wpsubs-tag-select__field">
			<?php foreach ( $selected_labels as $val => $lbl ) : ?>
				<span class="wpsubs-tag-select__pill" data-value="<?php echo esc_attr( $val ); ?>">
					<span class="wpsubs-tag-select__pill-label"><?php echo esc_html( $lbl ); ?></span>
					<button type="button" class="wpsubs-tag-select__pill-remove" aria-label="<?php esc_attr_e( 'Remove', 'subscription' ); ?>">&#x2715;</button>
				</span>
			<?php endforeach; ?>
			<input
				type="text"
				class="wpsubs-tag-select__input"
				placeholder="<?php echo $placeholder; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already esc_attr'd above. ?>"
				autocomplete="off"
				aria-label="<?php esc_attr_e( 'Filter options', 'subscription' ); ?>"
			/>
			<span class="wpsubs-tag-select__chevron" aria-hidden="true">
				<?php echo $chevron_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</span>
		</div>

		<div class="wpsubs-tag-select__dropdown">
			<div class="wpsubs-tag-select__list" role="listbox"
				<?php
				if ( $multiple ) :
					?>
					aria-multiselectable="true"<?php endif; ?>>
				<?php
				foreach ( $args['options'] as $option ) :
					$option      = wp_parse_args(
						$option,
						array(
							'value'    => '',
							'label'    => '',
							'disabled' => false,
						)
					);
					$opt_value   = (string) $option['value'];
					$is_selected = in_array( $opt_value, $selected_values, true );
					?>
					<button
						type="button"
						class="wpsubs-tag-select__item"
						data-value="<?php echo esc_attr( $opt_value ); ?>"
						role="option"
						aria-selected="<?php echo $is_selected ? 'true' : 'false'; ?>"
						<?php
						if ( $is_selected ) :
							?>
							data-selected<?php endif; ?>
						<?php
						if ( $option['disabled'] ) :
							?>
							data-disabled<?php endif; ?>
						style="<?php echo $is_selected ? 'display:none;' : ''; ?>"
					><?php echo esc_html( $option['label'] ); ?></button>
				<?php endforeach; ?>
			</div>
			<div class="wpsubs-tag-select__empty"><?php esc_html_e( 'No results found.', 'subscription' ); ?></div>
		</div>

		<?php if ( $args['name'] ) : ?>
			<?php if ( $multiple ) : ?>
				<?php if ( empty( $selected_values ) ) : ?>
					<input type="hidden" name="<?php echo esc_attr( $args['name'] ); ?>[]" value="" data-ts-val />
				<?php else : ?>
					<?php foreach ( $selected_values as $val ) : ?>
						<input type="hidden" name="<?php echo esc_attr( $args['name'] ); ?>[]" value="<?php echo esc_attr( $val ); ?>" data-ts-val />
					<?php endforeach; ?>
				<?php endif; ?>
			<?php else : ?>
				<input type="hidden" name="<?php echo esc_attr( $args['name'] ); ?>" value="<?php echo esc_attr( $current_value ); ?>" data-ts-val />
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Render a modal dialog (admin-components `wpsubs-modal`).
 *
 * Behaviour is handled by WPSubsModal (admin-components.js): open it from any
 * control with `data-wpsubs-modal-open="<id>"`; the backdrop, header close, and
 * footer buttons close it; Escape closes it. The dialog is hidden until opened.
 *
 * @param array $args Modal arguments: `id` (required, matches the opener's target),
 *                    `title` (header title), `body` (pre-escaped body HTML), `footer`
 *                    (pre-escaped footer HTML, optional), `class` (extra root class,
 *                    optional).
 * @return void
 */
function wpsubs_render_modal( array $args ): void {
	$id = $args['id'] ?? '';
	if ( empty( $id ) ) {
		return;
	}

	$title       = $args['title'] ?? '';
	$body        = $args['body'] ?? '';
	$footer      = $args['footer'] ?? '';
	$extra_class = $args['class'] ?? '';
	?>
	<div class="wpsubs-modal <?php echo esc_attr( $extra_class ); ?>" id="<?php echo esc_attr( $id ); ?>" hidden>
		<div class="wpsubs-modal__backdrop" data-wpsubs-modal-close></div>
		<div class="wpsubs-modal__dialog" role="dialog" aria-modal="true"<?php echo $title ? ' aria-label="' . esc_attr( $title ) . '"' : ''; ?>>
			<div class="wpsubs-modal__head">
				<span class="wpsubs-modal__title"><?php echo esc_html( $title ); ?></span>
				<button type="button" class="wpsubs-modal__close" data-wpsubs-modal-close aria-label="<?php esc_attr_e( 'Close', 'subscription' ); ?>">&times;</button>
			</div>
			<div class="wpsubs-modal__body">
				<?php
				// Body is pre-escaped by the caller; re-escaping would break markup.
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $body;
				?>
			</div>
			<?php if ( '' !== $footer ) : ?>
				<div class="wpsubs-modal__footer">
					<?php
					// Footer is pre-escaped by the caller.
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo $footer;
					?>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/**
 * Render an inline help hint: a help icon that reveals text on hover.
 *
 * Thin wrapper over the canonical `wpsubs-tooltip` component
 * (admin-components/tooltip.css): a help dashicon wrapped in a data-tip span.
 * Returns the markup so it can be concatenated into a label; place it inside an
 * overflow:visible container so the bubble is not clipped.
 *
 * @param string $text Hint text.
 * @param array  $args Optional. 'placement' => 'top' (default)|'bottom'|'left'|'right';
 *                     'align' => 'center' (default)|'start'|'end'; 'class' => extra trigger classes.
 *
 * @return string Escaped markup.
 */
function wpsubs_render_hint( string $text, array $args = array() ): string {
	$args = wp_parse_args(
		$args,
		array(
			'placement' => 'top',
			'align'     => 'center',
			'class'     => '',
		)
	);

	$classes = 'wpsubs-tooltip wpsubs-tooltip--hint';
	if ( in_array( $args['placement'], array( 'bottom', 'left', 'right' ), true ) ) {
		$classes .= ' wpsubs-tooltip--' . $args['placement'];
	}
	if ( in_array( $args['align'], array( 'start', 'end' ), true ) ) {
		$classes .= ' wpsubs-tooltip--' . $args['align'];
	}
	if ( '' !== $args['class'] ) {
		$classes .= ' ' . $args['class'];
	}

	// A <span> (not a <button>/<input>) is NOT a labelable element, so nesting
	// it inside a <label> does not associate the label with it — hovering the
	// label text therefore never reveals the tooltip, only hovering the icon
	// does. The text is exposed to assistive tech via role="img" + aria-label.
	return sprintf(
		'<span class="%1$s" data-tip="%2$s" role="img" aria-label="%2$s">'
			. '<span class="dashicons dashicons-editor-help wpsubs-tooltip__icon" aria-hidden="true"></span>'
			. '</span>',
		esc_attr( $classes ),
		esc_attr( $text )
	);
}
