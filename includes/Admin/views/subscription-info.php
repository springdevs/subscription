<table class="form-table">
	<tbody>
		<?php foreach ( $rows as $key => $value ) : ?>
		<tr>
			<th scope="row"><?php echo esc_html( $value['label'] ); ?>: </th>
			<td>
				<?php echo wp_kses_post( $value['value'] ); ?>
			</td>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>
