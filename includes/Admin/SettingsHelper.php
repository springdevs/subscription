<?php
/**
 * Settings Helper File
 *
 * @package SpringDevs\Subscription\Admin
 */

namespace SpringDevs\Subscription\Admin;

/**
 * Settings Helper Class
 *
 * @package SpringDevs\Subscription\Admin
 */
class SettingsHelper {
	/**
	 * Singleton instance
	 *
	 * @var SettingsHelper|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return SettingsHelper
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize the class.
	 */
	private function __construct() {
		add_filter( 'process_subscrpt_settings_fields', [ $this, 'process_settings_fields' ], 100, 1 );
	}

	/**
	 * Process settings fields.
	 *
	 * @param array $fields Settings fields.
	 * @return array Processed settings fields.
	 */
	public function process_settings_fields( $fields ) {
		// Group settings fields.
		$fields = $this->group_settings_fields( $fields );

		// Sort fields by priority (groups & fields).
		$fields = $this->sort_settings_fields( $fields );

		return $fields;
	}

	/**
	 * Group settings fields.
	 *
	 * @param array $fields Settings fields.
	 * @return array Processed settings fields.
	 */
	public function group_settings_fields( $fields ) {
		$tmp_fields = [];
		foreach ( $fields as $field ) {
			$field_group = $field['group'] ?? 'main';

			if ( $field['type'] === 'heading' ) {
				$group_priority                         = $field['priority'] ?? 0;
				$tmp_fields[ $field_group ]['priority'] = $group_priority;
				$field['priority']                      = -1;
			}

			$tmp_fields[ $field_group ]['fields'][] = $field;
		}
		return $tmp_fields;
	}

	/**
	 * Sort settings fields.
	 *
	 * @param array $fields Settings fields.
	 * @return array Processed settings fields.
	 */
	public function sort_settings_fields( $fields ) {
		// Sort groups by priority.
		uasort(
			$fields,
			function ( $a, $b ) {
				$priority_a = $a['priority'] ?? 0;
				$priority_b = $b['priority'] ?? 0;
				return $priority_a <=> $priority_b;
			}
		);

		// Sort fields within each group by priority.
		foreach ( $fields as $group_key => $group_data ) {
			uasort(
				$group_data['fields'],
				function ( $a, $b ) {
					$priority_a = $a['priority'] ?? 0;
					$priority_b = $b['priority'] ?? 0;
					return $priority_a <=> $priority_b;
				}
			);
			$fields[ $group_key ]['fields'] = $group_data['fields'];
		}
		return $fields;
	}

	/**
	 * Label a settings group for its tab.
	 *
	 * The label is the group's `heading` field, which is also what the panel
	 * shows, so a tab and its panel can never disagree. An add-on that adds a
	 * group without a heading still gets a usable tab rather than a blank one:
	 * `live_qr_settings` reads as "Live Qr Settings", which is wrong-ish but
	 * findable, and the fix is for that add-on to add a heading.
	 *
	 * `main` is the exception. It is what a field with no `group` falls back to,
	 * so it holds whatever nobody placed rather than anything named "Main". No
	 * field ships in it — this plugin has no settings that are merely general —
	 * and it only becomes a tab when something lands there uninvited.
	 *
	 * @param string $group_id Group key.
	 * @param array  $group    Group data: `fields`, `priority`.
	 * @return string Unescaped label.
	 */
	public static function group_label( $group_id, array $group ) {
		foreach ( $group['fields'] ?? array() as $field ) {
			if ( 'heading' === ( $field['type'] ?? '' ) && ! empty( $field['field_data']['title'] ) ) {
				return $field['field_data']['title'];
			}
		}

		if ( 'main' === $group_id ) {
			return __( 'General', 'subscription' );
		}

		return ucwords( str_replace( array( '_', '-' ), ' ', (string) $group_id ) );
	}

	/**
	 * Whether every field in a group is locked behind Pro.
	 *
	 * Drives the "Pro" marker on the tab, so the whole panel does not have to be
	 * opened to find out that none of it can be changed yet.
	 *
	 * @param array $group Group data.
	 * @return bool
	 */
	public static function group_is_pro_locked( array $group ) {
		$has_field = false;

		foreach ( $group['fields'] ?? array() as $field ) {
			if ( 'heading' === ( $field['type'] ?? '' ) ) {
				continue;
			}
			$has_field = true;
			if ( empty( $field['field_data']['pro_locked'] ) ) {
				return false;
			}
		}

		return $has_field;
	}

	/**
	 * Broad sidebar categories, in display order.
	 *
	 * The sidebar is one level up from the panels: each entry gathers several
	 * settings groups, which are then the horizontal tabs inside it. `all` is
	 * first and special — it has no group list and shows every panel at once.
	 *
	 * @return array<string,string> Category key => label.
	 */
	public static function categories() {
		return array(
			'all'       => __( 'All Settings', 'subscription' ),
			'general'   => __( 'General', 'subscription' ),
			'payments'  => __( 'Payments', 'subscription' ),
			'customers' => __( 'Customers', 'subscription' ),
			'advanced'  => __( 'Advanced', 'subscription' ),
		);
	}

	/**
	 * Which broad category a settings group belongs to.
	 *
	 * Unmapped groups — including any an add-on registers without knowing
	 * categories exist — fall into `advanced`, so a new group is always
	 * reachable from the sidebar rather than only from `all`.
	 *
	 * @param string $group_id Group key.
	 * @return string Category key.
	 */
	public static function group_category( $group_id ) {
		$map = array(
			'renewals'            => 'general',
			'switching'           => 'general',
			'guest_checkout'      => 'general',
			'payment_gateways'    => 'payments',
			'payment_failure'     => 'payments',
			'grace_period'        => 'payments',
			'role_based_settings' => 'customers',
			'live_qr_settings'    => 'advanced',
			'health_queue'        => 'advanced',
			'api_settings'        => 'advanced',
		);

		return $map[ $group_id ] ?? 'advanced';
	}

	/**
	 * Group keys bucketed by category, each list in the order the groups
	 * already sort in.
	 *
	 * @param array $settings_fields Grouped, sorted settings fields.
	 * @return array<string,string[]> Category key => ordered group keys.
	 */
	public static function category_groups( array $settings_fields ) {
		$out = array();
		foreach ( array_keys( self::categories() ) as $cat ) {
			if ( 'all' === $cat ) {
				continue;
			}
			$out[ $cat ] = array();
		}

		foreach ( array_keys( $settings_fields ) as $group_id ) {
			$cat           = self::group_category( $group_id );
			$out[ $cat ][] = $group_id;
		}

		return $out;
	}

	/**
	 * Render specified settings field.
	 *
	 * @param string $field Field type.
	 * @param array  $args Field arguments.
	 * @param bool   $should_print Whether to print the field or return as HTML string.
	 */
	public static function render_settings_field( $field, $args, $should_print = true ) {
		if ( empty( $field ) ) {
			$field = 'input'; // Default field type.
			subscrpt_write_debug_log( "[SettingsHelper] Field type not specified. Defaulting to 'input'." );
		}

		switch ( $field ) {
			case 'heading':
				return self::render_heading( $args, $should_print );
			case 'switch':
			case 'toggle':
				return self::render_switch_field( $args, $should_print );
			case 'select':
				return self::render_select_field( $args, $should_print );
			case 'multi_select':
				return self::render_multiselect_field( $args, $should_print );
			case 'join':
				return self::render_joined_field( $args, $should_print );
			case 'editlist':
				return self::render_editlist_field( $args, $should_print );
			case 'input':
			default:
				return self::render_input_field( $args, $should_print );
		}
	}

	/**
	 * Pro badge markup, shown beside settings that require WPSubscription Pro.
	 *
	 * @return string Pre-escaped badge HTML.
	 */
	public static function pro_badge_html() {
		return '<span class="subscrpt-pro-badge" title="' . esc_attr__( 'WPSubscription Pro required', 'subscription' ) . '">' . esc_html__( 'Pro', 'subscription' ) . '</span>';
	}


	/**
	 * Text Element HTML.
	 *
	 * @param array $args Same as 'render_text_field'.
	 * @param bool  $join_item Whether to return element for 'join' container or not.
	 */
	public static function inp_element( $args = [], $join_item = false ) {
		$id          = $args['id'];
		$value       = $args['value'] ?? '';
		$placeholder = $args['placeholder'] ?? '';
		$type        = $args['type'] ?? 'text';

		$disabled_attr = isset( $args['disabled'] ) && $args['disabled'] ? 'disabled' : '';

		$style_attr = '';
		if ( isset( $args['style'] ) ) {
			$style_attr = $args['style'];
		}

		$other_attrs_html = '';
		foreach ( ( $args['attributes'] ?? [] ) as $attr_key => $attr_value ) {
			$other_attrs_html .= sprintf( ' %s="%s" ', esc_attr( $attr_key ), esc_attr( $attr_value ) );
		}

		ob_start();
		?>
			<input
				id="<?php echo esc_attr( $id ); ?>"
				name="<?php echo esc_attr( $id ); ?>"
				class="wpsubs-input"
				<?php
				if ( $style_attr ) :
					?>
					style="<?php echo esc_attr( $style_attr ); ?>"<?php endif; ?>
				type="<?php echo esc_attr( $type ); ?>"
				placeholder="<?php echo esc_attr( $placeholder ); ?>"
				value="<?php echo esc_attr( $value ); ?>"
				<?php echo esc_attr( $disabled_attr ); ?>
				<?php echo wp_kses_post( $other_attrs_html ); ?>
			/>
		<?php
		return ob_get_clean();
	}

	/**
	 * Select Element HTML.
	 *
	 * @param array $args Same as 'render_select_field'.
	 * @param bool  $join_item Whether to return element for 'join' container or not.
	 */
	public static function select_element( $args = [], $join_item = false ) {
		$id = $args['id'];

		// Enhanced / multiselect → wpsubs-tag-select (pill input with filter).
		if ( isset( $args['enhanced'] ) && $args['enhanced'] ) {
			$multiple    = isset( $args['attributes']['multiple'] ) && $args['attributes']['multiple'];
			$adv_options = array();
			foreach ( ( $args['options'] ?? [] ) as $opt_value => $opt_label ) {
				$adv_options[] = array(
					'value' => (string) $opt_value,
					'label' => $opt_label,
				);
			}

			ob_start();
			wpsubs_render_tag_select(
				array(
					'name'     => $id,
					'value'    => $args['selected'] ?? ( $multiple ? array() : '' ),
					'options'  => $adv_options,
					'multiple' => $multiple,
				)
			);
			return ob_get_clean();
		}

		// Regular select → wpsubs-adv-select (button-based custom dropdown).
		$selected    = (string) ( $args['selected'] ?? '' );
		$adv_options = array();
		foreach ( ( $args['options'] ?? [] ) as $opt_value => $opt_label ) {
			$adv_options[] = array(
				'value'    => (string) $opt_value,
				'label'    => $opt_label,
				'disabled' => isset( $args['disabled'] ) && ( is_array( $args['disabled'] )
					? in_array( $opt_value, $args['disabled'], true )
					: $args['disabled'] === $opt_value ),
			);
		}

		ob_start();
		wpsubs_render_adv_select(
			array(
				'name'    => $id,
				'value'   => $selected,
				'options' => $adv_options,
				'align'   => 'left',
				'class'   => $args['class'] ?? '',
			)
		);
		return ob_get_clean();
	}

	/**
	 * Render Field Heading.
	 *
	 * - Args:
	 *   - title (string) - Field title.
	 *   - description (string) - Field description (optional).
	 *
	 * @param array $args Field arguments.
	 * @param bool  $should_print Whether to print the field or return as HTML string.
	 */
	public static function render_heading( $args = [], $should_print = true ) {
		$title       = $args['title'] ?? '';
		$description = $args['description'] ?? '';

		ob_start();
		?>
		<div class="wpsubs-settings-heading">
			<h3 class="wpsubs-settings-heading__title"><?php echo esc_html( $title ); ?></h3>
			<?php if ( ! empty( $description ) ) : ?>
				<p class="wpsubs-settings-heading__desc"><?php echo wp_kses_post( $description ); ?></p>
			<?php endif; ?>
		</div>
		<?php
		$html_content = ob_get_clean();

		// Output not escaped intentionally. Breaks the HTML structure when escaped.
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return $should_print ? print( $html_content ) : $html_content;
	}

	/**
	 * Render Text field.
	 *
	 * - Args:
	 *   - id (string) - Field ID.
	 *   - title (string) - Field title.
	 *   - description (string) - Field description (optional).
	 *   - value (string) - Default value.
	 *   - placeholder (string) - Default placeholder.
	 *   - disabled (bool) - Disabled status.
	 *   - type (string) - Input type [text, email, number, date, time, etc.].
	 *
	 * @param array $args Field arguments.
	 * @param bool  $should_print Whether to print the field or return as HTML string.
	 */
	public static function render_input_field( $args = [], $should_print = true ) {
		$title       = $args['title'] ?? '';
		$description = $args['description'] ?? '';

		// Return error if ID is not provided.
		if ( empty( $args['id'] ?? '' ) ) {
			$field_hint = empty( $title ) ? 'Error' : $title;
			$no_id_msg  = '<p><strong>' . $field_hint . ':</strong> ' . __( 'Field ID is required.', 'subscription' ) . '</p>';
			return $should_print ? print wp_kses_post( $no_id_msg ) : $no_id_msg;
		}

		// Input HTML.
		$text_el_html = self::inp_element( $args );

		ob_start();
		?>
		<div class="wpsubs-settings-field<?php echo ! empty( $args['pro_locked'] ) ? ' wpsubs-settings-field--locked' : ''; ?>">
			<div class="wpsubs-settings-field__label">
				<?php
				if ( ! empty( $args['pro_locked'] ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped badge markup.
					echo self::pro_badge_html();
				}
				?>
				<?php echo esc_html( $title ); ?>
			</div>
			<div class="wpsubs-settings-field__control">
				<?php
					// Output intentionally not escaped as element is already escaped during generation & re-escaping breaks the HTML structure.
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo $text_el_html;
				?>
				<?php if ( ! empty( $description ) ) : ?>
					<p class="wpsubs-settings-field__hint"><?php echo wp_kses_post( $description ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
		$html_content = ob_get_clean();

		// Output not escaped intentionally. Breaks the HTML structure when escaped.
		// All form elements inside $html_content are pre-escaped during generation (esc_attr, esc_html).
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return $should_print ? print( $html_content ) : $html_content;
	}

	/**
	 * Render Switch field.
	 *
	 * - Args:
	 *   - id (string) - Field ID.
	 *   - title (string) - Field title.
	 *   - label (string) - Checkbox label.
	 *   - description (string) - Field description (optional).
	 *   - value (string) - Checked value.
	 *   - checked (bool) - Checked status.
	 *   - disabled (bool) - Disabled status.
	 *
	 * @param array $args Field arguments.
	 * @param bool  $should_print Whether to print the field or return as HTML string.
	 */
	public static function render_switch_field( $args = [], $should_print = true ) {
		$id          = $args['id'];
		$title       = $args['title'] ?? '';
		$label       = $args['label'] ?? '';
		$description = $args['description'] ?? '';
		$value       = $args['value'] ?? '0';

		// Return error if ID is not provided.
		if ( empty( $args['id'] ?? '' ) ) {
			$field_hint = empty( $title ) ? 'Error' : $title;
			$no_id_msg  = '<p><strong>' . $field_hint . ':</strong> ' . __( 'Field ID is required.', 'subscription' ) . '</p>';
			return $should_print ? print wp_kses_post( $no_id_msg ) : $no_id_msg;
		}

		$description_html = '';
		if ( ! empty( $description ) ) {
			$description_html = sprintf(
				'<p class="wpsubs-settings-field__hint">%s</p>',
				wp_kses_post( $description )
			);
		}

		$style_attr = '';
		if ( isset( $args['style'] ) ) {
			$style_attr .= ' ' . $args['style'];
		}

		$other_attrs_html = '';
		foreach ( ( $args['attributes'] ?? [] ) as $attr_key => $attr_value ) {
			$other_attrs_html .= sprintf( ' %s="%s" ', esc_attr( $attr_key ), esc_attr( $attr_value ) );
		}

		$checked_attr  = isset( $args['checked'] ) && (bool) $args['checked'] ? 'checked' : '';
		$disabled_attr = isset( $args['disabled'] ) && (bool) $args['disabled'] ? 'disabled' : '';

		ob_start();
		?>
		<div class="wpsubs-settings-field<?php echo ! empty( $args['pro_locked'] ) ? ' wpsubs-settings-field--locked' : ''; ?>">
			<div class="wpsubs-settings-field__label">
				<?php
				if ( ! empty( $args['pro_locked'] ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped badge markup.
					echo self::pro_badge_html();
				}
				?>
				<?php echo esc_html( $title ); ?>
			</div>
			<div class="wpsubs-settings-field__control">
				<label class="wpsubs-settings-toggle-label" for="<?php echo esc_attr( $id ); ?>">
					<input
						id="<?php echo esc_attr( $id ); ?>"
						name="<?php echo esc_attr( $id ); ?>"
						class="wpsubs-toggle"
						type="checkbox"
						value="<?php echo esc_attr( $value ); ?>"
						<?php echo esc_attr( $checked_attr ); ?>
						<?php echo esc_attr( $disabled_attr ); ?>
						<?php
							// Output intentionally not escaped as element is already escaped during generation & re-escaping breaks the HTML structure.
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo $other_attrs_html;
						?>
					/>
					<span class="wpsubs-toggle-ui" aria-hidden="true"></span>
					<?php if ( ! empty( $label ) ) : ?>
						<span class="wpsubs-settings-toggle-label__text"><?php echo esc_html( $label ); ?></span>
					<?php endif; ?>
				</label>
				<?php echo wp_kses_post( $description_html ); ?>
			</div>
		</div>
		<?php
		$html_content = ob_get_clean();

		// Output not escaped intentionally. Breaks the HTML structure when escaped.
		// All form elements inside $html_content are pre-escaped during generation (esc_attr, esc_html).
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return $should_print ? print( $html_content ) : $html_content;
	}

	/**
	 * Render Select field.
	 *
	 * - Args:
	 *   - id (string) - Field ID.
	 *   - title (string) - Field title.
	 *   - description (string) - Field description (optional).
	 *   - options (array) - Field options [value => label].
	 *   - selected (string) - Selected option value.
	 *   - disabled (string|array) - Disabled option value(s).
	 *
	 * @param array $args Field arguments.
	 * @param bool  $should_print Whether to print the field or return as HTML string.
	 */
	public static function render_select_field( $args = [], $should_print = true ) {
		$title       = $args['title'] ?? '';
		$description = $args['description'] ?? '';

		// Return error if ID is not provided.
		if ( empty( $args['id'] ?? '' ) ) {
			$field_hint = empty( $title ) ? 'Error' : $title;
			$no_id_msg  = '<p><strong>' . $field_hint . ':</strong> ' . __( 'Field ID is required.', 'subscription' ) . '</p>';
			return $should_print ? print wp_kses_post( $no_id_msg ) : $no_id_msg;
		}

		// Select HTML.
		$select_el_html = self::select_element( $args );

		ob_start();
		?>
		<div class="wpsubs-settings-field<?php echo ! empty( $args['pro_locked'] ) ? ' wpsubs-settings-field--locked' : ''; ?>">
			<div class="wpsubs-settings-field__label">
				<?php
				if ( ! empty( $args['pro_locked'] ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped badge markup.
					echo self::pro_badge_html();
				}
				?>
				<?php echo esc_html( $title ); ?>
			</div>
			<div class="wpsubs-settings-field__control">
				<?php
					// Output intentionally not escaped as element is already escaped during generation & re-escaping breaks the HTML structure.
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo $select_el_html;
				?>
				<?php if ( ! empty( $description ) ) : ?>
					<p class="wpsubs-settings-field__hint"><?php echo wp_kses_post( $description ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
		$html_content = ob_get_clean();

		// Output not escaped intentionally. Breaks the HTML structure when escaped.
		// All form elements inside $html_content are pre-escaped during generation (esc_attr, esc_html).
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return $should_print ? print( $html_content ) : $html_content;
	}

	/**
	 * Render Multiselect field.
	 * Just a wrapper over 'render_select_field' with multiple attribute.
	 *
	 * @param array $args Field arguments.
	 * @param bool  $should_print Whether to print the field or return as HTML string.
	 */
	public static function render_multiselect_field( $args = [], $should_print = true ) {
		$default_multiselect_args = [
			'attributes' => [
				'multiple' => 'multiple',
			],
			'enhanced'   => true,
		];

		$args = wp_parse_args( $args, $default_multiselect_args );

		return self::render_select_field( $args, $should_print );
	}

	/**
	 * Render Joined field with multiple elements.
	 *
	 * - Args:
	 *   - title (string) - Field title.
	 *   - description (string) - Field description (optional).
	 *   - vertical (bool) - Whether to show items vertically or not.
	 *   - elements ([...string]) - Array of HTML elements to join.
	 *
	 * @param array $args Field arguments.
	 * @param bool  $should_print Whether to print the field or return as HTML string.
	 */
	public static function render_joined_field( $args = [], $should_print = true ) {
		$title       = $args['title'] ?? '';
		$description = $args['description'] ?? '';

		$vertical_style = ( $args['vertical'] ?? false ) ? 'flex-direction:column;' : '';

		ob_start();
		?>
		<div class="wpsubs-settings-field<?php echo ! empty( $args['pro_locked'] ) ? ' wpsubs-settings-field--locked' : ''; ?>">
			<div class="wpsubs-settings-field__label">
				<?php
				if ( ! empty( $args['pro_locked'] ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped badge markup.
					echo self::pro_badge_html();
				}
				?>
				<?php echo esc_html( $title ); ?>
			</div>
			<div class="wpsubs-settings-field__control">
				<div class="wpsubs-input-group"
				<?php
				if ( $vertical_style ) :
					?>
					style="<?php echo esc_attr( $vertical_style ); ?>"<?php endif; ?>>
					<?php
					foreach ( ( $args['elements'] ?? [] ) as $element_html ) {
						// Output intentionally not escaped as element is already escaped during generation & re-escaping breaks the HTML structure.
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo $element_html;
					}
					?>
				</div>
				<?php if ( ! empty( $description ) ) : ?>
					<p class="wpsubs-settings-field__hint"><?php echo wp_kses_post( $description ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
		$html_content = ob_get_clean();

		// Output not escaped intentionally. Breaks the HTML structure when escaped.
		// All form elements inside $html_content are pre-escaped during generation (esc_attr, esc_html).
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return $should_print ? print( $html_content ) : $html_content;
	}

	/**
	 * Render an editable, reorderable list field (the `wpsubs-editlist` component).
	 *
	 * Generic and reusable: a sortable list of text items with per-row remove/move
	 * controls and an inline input + add button. The ordered list is serialized as
	 * JSON (`[{ key, label }]`) into a hidden input so it submits with the form;
	 * behaviour is wired by `WPSubsEditList` (admin-components/editlist.js). All user-facing
	 * strings are overridable so the field carries no feature-specific text.
	 *
	 * When `modal` is true the list lives inside a `wpsubs-modal` (via the
	 * `WPSubsModal` component) and the settings row only shows a trigger button with
	 * a live item count.
	 *
	 * - Args:
	 *   - id (string)          - Field ID / option key.
	 *   - title (string)
	 *   - description (string)
	 *   - value (array)        - Ordered list of { key, label } entries.
	 *   - add_placeholder (string) - Inline input placeholder.
	 *   - add_label (string)   - Add button accessible label.
	 *   - empty_text (string)  - Message shown when the list is empty.
	 *   - modal (bool)         - Present the list inside a modal (default false).
	 *   - button_label (string) - Modal trigger button text (modal mode).
	 *   - modal_title (string) - Modal header title (modal mode; defaults to title).
	 *   - pro_locked (bool)
	 *
	 * @param array $args Field arguments.
	 * @param bool  $should_print Whether to print the field or return as HTML string.
	 */
	public static function render_editlist_field( $args = [], $should_print = true ) {
		$id          = $args['id'] ?? '';
		$title       = $args['title'] ?? '';
		$description = $args['description'] ?? '';
		$items       = is_array( $args['value'] ?? null ) ? $args['value'] : [];
		$locked      = ! empty( $args['pro_locked'] );
		$modal       = ! empty( $args['modal'] );

		$add_placeholder = $args['add_placeholder'] ?? __( 'Add an item…', 'subscription' );
		$add_label       = $args['add_label'] ?? __( 'Add item', 'subscription' );
		$empty_text      = $args['empty_text'] ?? __( 'No items yet. Add one below.', 'subscription' );
		$button_label    = $args['button_label'] ?? __( 'Manage list', 'subscription' );
		$modal_title     = $args['modal_title'] ?? ( '' !== $title ? $title : __( 'Edit list', 'subscription' ) );

		if ( empty( $id ) ) {
			$field_hint = empty( $title ) ? 'Error' : $title;
			$no_id_msg  = '<p><strong>' . $field_hint . ':</strong> ' . __( 'Field ID is required.', 'subscription' ) . '</p>';
			return $should_print ? print wp_kses_post( $no_id_msg ) : $no_id_msg;
		}

		$body = self::editlist_body_html( $items, $add_placeholder, $add_label, $empty_text, $locked );

		ob_start();
		?>
		<div class="wpsubs-settings-field<?php echo $locked ? ' wpsubs-settings-field--locked' : ''; ?>">
			<div class="wpsubs-settings-field__label">
				<?php
				if ( $locked ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped badge markup.
					echo self::pro_badge_html();
				}
				?>
				<?php echo esc_html( $title ); ?>
			</div>
			<div class="wpsubs-settings-field__control">
				<div class="wpsubs-editlist<?php echo $locked ? ' wpsubs-editlist--locked' : ''; ?>">
					<input type="hidden" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( wp_json_encode( array_values( $items ) ) ); ?>" />
					<?php if ( $modal ) : ?>
						<button type="button" class="wpsubs-btn wpsubs-btn--outline wpsubs-editlist__trigger" data-wpsubs-modal-open="<?php echo esc_attr( $id . '_modal' ); ?>"<?php echo $locked ? ' disabled' : ''; ?>>
							<?php echo esc_html( $button_label ); ?>
							<span class="wpsubs-editlist__count"><?php echo esc_html( (string) count( $items ) ); ?></span>
						</button>
						<?php
						wpsubs_render_modal(
							[
								'id'     => $id . '_modal',
								'title'  => $modal_title,
								'body'   => $body,
								'class'  => 'wpsubs-modal--editlist',
								'footer' => '<button type="button" class="wpsubs-btn wpsubs-btn--primary" data-wpsubs-modal-close>' . esc_html__( 'Done', 'subscription' ) . '</button>',
							]
						);
						?>
					<?php else : ?>
						<?php
						// Body markup is pre-escaped during generation.
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo $body;
						?>
					<?php endif; ?>
				</div>
				<?php if ( ! empty( $description ) ) : ?>
					<p class="wpsubs-settings-field__hint"><?php echo wp_kses_post( $description ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
		$html_content = ob_get_clean();

		// Output not escaped intentionally. Breaks the HTML structure when escaped.
		// All form elements inside $html_content are pre-escaped during generation (esc_attr, esc_html).
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return $should_print ? print( $html_content ) : $html_content;
	}

	/**
	 * Build the inner markup of an edit list (items + empty message + add row).
	 *
	 * Shared by inline and modal presentations of {@see self::render_editlist_field}.
	 *
	 * @param array  $items           Ordered list of { key, label } entries.
	 * @param string $add_placeholder Inline input placeholder.
	 * @param string $add_label       Add button accessible label.
	 * @param string $empty_text      Message shown when the list is empty.
	 * @param bool   $locked          Whether the controls are disabled.
	 * @return string Pre-escaped HTML.
	 */
	private static function editlist_body_html( array $items, $add_placeholder, $add_label, $empty_text, $locked ) {
		ob_start();
		?>
		<ul class="wpsubs-editlist__items">
			<?php foreach ( $items as $item ) : ?>
				<?php
				$item_key   = isset( $item['key'] ) ? (string) $item['key'] : '';
				$item_label = isset( $item['label'] ) ? (string) $item['label'] : '';
				if ( '' === $item_label ) {
					continue;
				}
				?>
				<li class="wpsubs-editlist__item" data-key="<?php echo esc_attr( $item_key ); ?>">
					<span class="wpsubs-editlist__handle" aria-hidden="true">&#8942;&#8942;</span>
					<span class="wpsubs-editlist__label"><?php echo esc_html( $item_label ); ?></span>
					<span class="wpsubs-editlist__actions">
						<button type="button" class="wpsubs-editlist__btn" data-editlist-up aria-label="<?php esc_attr_e( 'Move up', 'subscription' ); ?>"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="18 15 12 9 6 15"/></svg></button>
						<button type="button" class="wpsubs-editlist__btn" data-editlist-down aria-label="<?php esc_attr_e( 'Move down', 'subscription' ); ?>"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg></button>
						<button type="button" class="wpsubs-editlist__btn wpsubs-editlist__btn--danger" data-editlist-remove aria-label="<?php esc_attr_e( 'Remove', 'subscription' ); ?>"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>
		<p class="wpsubs-editlist__empty"<?php echo empty( $items ) ? '' : ' hidden'; ?>><?php echo esc_html( $empty_text ); ?></p>
		<div class="wpsubs-editlist__add">
			<input type="text" class="wpsubs-input wpsubs-editlist__input" placeholder="<?php echo esc_attr( $add_placeholder ); ?>"<?php echo $locked ? ' disabled' : ''; ?> />
			<button type="button" class="wpsubs-editlist__add-btn" data-editlist-add aria-label="<?php echo esc_attr( $add_label ); ?>"<?php echo $locked ? ' disabled' : ''; ?>>
				<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
			</button>
		</div>
		<?php
		return ob_get_clean();
	}
}
