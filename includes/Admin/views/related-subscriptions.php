<table class="widefat striped">
    <thead>
        <tr>
            <th></th>
            <th><?php _e('Started on', 'sdevs_subscrpt'); ?></th>
            <th><?php _e('Recurring', 'sdevs_subscrpt'); ?></th>
            <th><?php _e('Expiry date', 'sdevs_subscrpt'); ?></th>
            <th><?php _e('Status', 'sdevs_subscrpt'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php
        foreach ($order_meta['posts'] as $post) :
            if (get_the_title($post) != "") :
                $post_meta = get_post_meta($post, "_subscrpt_order_general", true);
        ?>
                <tr>
                    <td>
                        <a href="<?php echo esc_js(get_edit_post_link($post)); ?>" target="_blank">#<?php echo esc_html($post); ?> - <?php echo esc_html(get_the_title($post_meta['product_id'])); ?></a>
                    </td>
                    <td>
                        <?php echo $post_meta['trial'] == null ? esc_html(date('F d, Y', $post_meta['start_date'])) : "+" . esc_html($post_meta['trial']) . " " . __('free trial', 'sdevs_subscrpt'); ?>
                    </td>
                    <td><?php echo esc_js($post_meta['total_price_html']); ?></td>
                    <td><?php echo esc_html($post_meta['trial'] == null ? date('F d, Y', $post_meta['next_date']) : date('F d, Y', $post_meta['start_date'])); ?></td>
                    <td><?php echo esc_html(get_post_status($post)); ?></td>
                </tr>
        <?php endif;
        endforeach; ?>
    </tbody>
</table>