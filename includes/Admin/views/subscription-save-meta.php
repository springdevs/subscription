<p class="subscrpt_sub_box">
			<select id="subscrpt_order_type" name="subscrpt_order_action">
				<option value="" disabled><?php esc_html_e( 'Choose Action', 'sdevs_subscrpt' ); ?></option>
				<?php foreach ( $actions as $action ) : ?>
					<option value="<?php echo esc_html( $action['value'] ); ?>" 
												<?php
												if ( $action['value'] == $status ) {
													echo 'selected';}
												?>
					><?php echo esc_html( $action['label'] ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<div class="submitbox">
			<input type="submit" class="button save_order button-primary tips" name="save" value="Process">
		</div>
