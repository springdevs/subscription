<?php
/**
 * @var array $actions ;
 * @var array $actions_data ;
 * @var string $status ;
 */
?>
<p class="subscrpt_sub_box">
    <select id="subscrpt_order_type" name="subscrpt_order_action">
        <option value="" disabled selected><?php esc_html_e( 'Choose Action', 'sdevs_subscrpt' ); ?></option>
        <?php foreach ( $actions as $action_slug ) : ?>
            <?php
                $action = $actions_data[$action_slug];
            ?>
            <option value="<?php echo esc_html( $action['value'] ); ?>">
                <?php echo esc_html( $action['label'] ); ?>
            </option>
        <?php endforeach; ?>
    </select>
</p>
<div class="submitbox">
    <input type="submit" class="button save_order button-primary tips" name="save" value="Process">
</div>
