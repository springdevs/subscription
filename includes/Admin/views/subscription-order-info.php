<table class="form-table">
    <tbody>
        <tr>
            <?php
            $product_name = apply_filters('subscrpt_filter_product_name', get_the_title($post_meta['product_id']), $post_meta);
            $product_link = apply_filters('subscrpt_filter_product_permalink', get_the_permalink($post_meta['product_id']), $post_meta);
            ?>
            <th scope="row">Product : </th>
            <td>
                <a href="<?php echo esc_html($product_link); ?>" target="_blank"><?php echo esc_html($product_name); ?></a>
            </td>
        </tr>
        <tr>
            <th scope="row">Cost : </th>
            <td><?php echo esc_sql(wc_price($order->get_item_subtotal($order_item, false, true), array('currency' => $order->get_currency()))); ?></td>
        </tr>
        <tr>
            <th scope="row">Qty : </th>
            <td>x<?php echo esc_html($post_meta['qty']); ?></td>
        </tr>
        <tr>
            <th scope="row">Amount : </th>
            <td><strong><?php echo esc_sql($post_meta['subtotal_price_html']); ?></strong></td>
        </tr>
        <?php if (!empty($post_meta['trial'])) : ?>
            <tr>
                <th scope="row">Trial</th>
                <td><?php echo esc_html($post_meta['trial']); ?></td>
            </tr>
            <tr>
                <th scope="row">Trial Date</th>
                <td><?php echo esc_html(" [ " . date('F d, Y', strtotime($order->get_date_created())) . " - " . date('F d, Y', strtotime($post_meta['trial'], strtotime($order->get_date_created()))) . " ] "); ?></td>
            </tr>
        <?php endif; ?>
        <tr>
            <th scope="row">Started date:</th>
            <td><?php echo esc_html(date('F d, Y', $post_meta['start_date'])); ?></td>
        </tr>
        <tr>
            <th scope="row">Payment due date:</th>
            <td><?php echo esc_html(date('F d, Y', $post_meta['next_date'])); ?></td>
        </tr>
        <tr>
            <th scope="row">Payment Method:</th>
            <td><?php echo esc_html($order->get_payment_method_title()); ?></td>
        </tr>
        <tr>
            <th scope="row">Billing:</th>
            <td><?php echo esc_sql($order->get_formatted_billing_address()); ?></td>
        </tr>
        <tr>
            <th scope="row">Shipping:</th>
            <td><?php echo esc_sql($order->get_formatted_shipping_address() ? $order->get_formatted_shipping_address() : "No shipping address set."); ?></td>
        </tr>
    </tbody>
</table>