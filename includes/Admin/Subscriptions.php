<?php

namespace SpringDevs\Subscription\Admin;

use SpringDevs\Subscription\Illuminate\Action;

/**
 * Subscriptions class
 * @package SpringDevs\Subscription\Admin
 */
class Subscriptions
{
    public function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'custom_enqueue_scripts']);
        add_filter('post_row_actions', [$this, 'post_row_actions'], 10, 2);
        add_filter('bulk_actions-edit-subscrpt_order', [$this, 'edit_bulk_actions']);
        add_filter('manage_subscrpt_order_posts_columns', [$this, 'add_custom_columns']);
        add_action('manage_subscrpt_order_posts_custom_column', [$this, 'add_custom_columns_data'], 10, 2);
        add_action('add_meta_boxes', array($this, "create_meta_boxes"));
        add_action('admin_head-post.php', [$this, "some_styles"]);
        add_action('admin_head-post-new.php', [$this, "some_styles"]);
        add_action('admin_footer-post.php', [$this, "some_scripts"]);
        add_action('admin_footer-post-new.php', [$this, "some_scripts"]);
        add_action('save_post', [$this, 'save_subscrpt_order']);
        add_filter('woocommerce_order_item_get_formatted_meta_data', [$this, 'remove_order_meta'], 10, 1);
    }

    public function edit_bulk_actions($options)
    {
        unset($options['trash']);
        return $options;
    }

    public function remove_order_meta($formatted_meta)
    {
        $temp_metas = [];
        foreach ($formatted_meta as $key => $meta) {
            if (isset($meta->key) && !in_array($meta->key, ['_renew_subscrpt'])) {
                $temp_metas[$key] = $meta;
            }
        }
        return $temp_metas;
    }

    public function custom_enqueue_scripts()
    {
        wp_enqueue_style("subscrpt_admin_css");
    }

    public function post_row_actions($unset_actions, $post)
    {
        global $current_screen;
        if ($current_screen->post_type != 'subscrpt_order')
            return $unset_actions;
        unset($unset_actions['inline hide-if-no-js']);
        unset($unset_actions['view']);
        unset($unset_actions['trash']);
        unset($unset_actions['edit']);
        return $unset_actions;
    }

    public function add_custom_columns($columns)
    {
        $columns['subscrpt_start_date'] = __('Start Date', 'sdevs_subscrpt');
        $columns['subscrpt_customer'] = __('Customer', 'sdevs_subscrpt');
        $columns['subscrpt_next_date'] = __('Next Date', 'sdevs_subscrpt');
        $columns['subscrpt_status'] = __('Status', 'sdevs_subscrpt');
        unset($columns['date']);
        return $columns;
    }

    public function add_custom_columns_data($column, $post_id)
    {
        $post_meta = get_post_meta($post_id, "_subscrpt_order_general", true);
        $order = wc_get_order($post_meta['order_id']);
        if ($order) {
            if ($column == "subscrpt_start_date") {
                echo date('F d, Y', $post_meta['start_date']);
            } elseif ($column == "subscrpt_customer") {
?>
                <?php echo wp_kses_post($order->get_formatted_billing_full_name()); ?>
                <br />
                <a href="mailto:<?php echo wp_kses_post($order->get_billing_email()); ?>"><?php echo wp_kses_post($order->get_billing_email()); ?></a>
                <br />
                Phone : <a href="tel:<?php echo esc_js($order->get_billing_phone()); ?>"><?php echo esc_js($order->get_billing_phone()); ?></a>
            <?php
            } elseif ($column == "subscrpt_next_date") {
                echo date('F d, Y', $post_meta['next_date']);
            } elseif ($column == "subscrpt_status") {
                echo esc_html(get_post_status($post_id));
            }
        } else {
            _e("Order not found !!", "sdevs_subscrpt");
        }
    }

    public function create_meta_boxes()
    {
        remove_meta_box('submitdiv', 'subscrpt_order', 'side');
        // Save Data
        add_meta_box(
            'subscrpt_order_save_post',
            __('Subscription Action', 'sdevs_subscrpt'),
            [$this, 'subscrpt_order_save_post'],
            'subscrpt_order',
            'side',
            'default'
        );

        add_meta_box(
            'subscrpt_customer_info',
            __('Customer Info', 'sdevs_subscrpt'),
            [$this, 'subscrpt_customer_info'],
            'subscrpt_order',
            'side',
            'default'
        );

        add_meta_box(
            'subscrpt_order_info',
            __('Subscription Info', 'sdevs_subscrpt'),
            [$this, 'subscrpt_order_info'],
            'subscrpt_order',
            'normal',
            'default'
        );

        add_meta_box(
            'subscrpt_order_history',
            __('Subscription History', 'sdevs_subscrpt'),
            [$this, 'subscrpt_order_history'],
            'subscrpt_order',
            'normal',
            'default'
        );

        add_meta_box(
            'subscrpt_order_activities',
            __('Subscription Activities', 'sdevs_subscrpt'),
            [$this, 'subscrpt_order_activities'],
            'subscrpt_order',
            'normal',
            'default'
        );
    }

    public function subscrpt_order_history()
    {
        $order_histories = get_post_meta(get_the_ID(), '_subscrpt_order_history', true);
        rsort($order_histories);
        include 'views/order-history.php';
    }

    public function subscrpt_order_activities()
    {
        if (function_exists('subscrpt_pro_activated')) :
            if (subscrpt_pro_activated()) :
                do_action('subscrpt_order_activities', get_the_ID());
            else :
            ?>
                <a href="https://springdevs.com" target="_blank">
                    <img style="width: 100%;" src="<?php echo SUBSCRPT_ASSETS . '/images/subscrpt-ads.png'; ?>" />
                </a>
            <?php
            endif;
        endif;
    }

    public function subscrpt_order_save_post()
    {
        $actions = [
            ["label" => __('Activate Subscription', 'sdevs_subscrpt'), "value" => 'active'],
            ["label" => __('Pending Subscription', 'sdevs_subscrpt'),  "value" => 'pending'],
            ["label" => __('Expire Subscription', 'sdevs_subscrpt'),   "value" => 'expired'],
            ["label" => __('Cancel Subscription', 'sdevs_subscrpt'),   "value" => 'cancelled'],
        ];
        $status = get_post_status(get_the_ID());
        include 'views/subscription-save-meta.php';
    }

    public function subscrpt_customer_info()
    {
        $post_meta = get_post_meta(get_the_ID(), "_subscrpt_order_general", true);
        $order = wc_get_order($post_meta["order_id"]);
        if (!$order) return;
        include 'views/subscription-customer.php';
    }

    public function subscrpt_order_info()
    {
        $post_meta = get_post_meta(get_the_ID(), "_subscrpt_order_general", true);
        $order_item = null;
        $order = wc_get_order($post_meta["order_id"]);
        if (!$order) return;
        foreach ($order->get_items() as $cart_item) {
            if ($cart_item["product_id"] == $post_meta["product_id"]) {
                $order_item = $cart_item;
                break;
            }
        }
        include 'views/subscription-order-info.php';
    }

    public function some_styles()
    {
        global $post;
        if ($post->post_type == "subscrpt_order") :
            ?>
            <style>
                .submitbox {
                    display: flex;
                    justify-content: space-around;
                }

                .subscrpt_sub_box {
                    display: grid;
                    line-height: 2;
                }
            </style>
        <?php
        endif;
    }

    public function some_scripts()
    {
        global $post;
        if ($post->post_type == "subscrpt_order") :
        ?>
            <script>
                jQuery(document).ready(function() {
                    jQuery(window).off("beforeunload", null);
                });
            </script>
<?php
        endif;
    }

    public function save_subscrpt_order($post_id)
    {
        if (wp_is_post_revision($post_id)) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!isset($_POST['subscrpt_order_action'])) return;
        remove_all_actions('save_post');

        $action = sanitize_text_field($_POST['subscrpt_order_action']);
        wp_update_post([
            'ID' => $post_id,
            'post_status' => $action
        ]);

        $post_meta = get_post_meta($post_id, "_subscrpt_order_general", true);
        if ($action === "active") {
            $order = wc_get_order($post_meta['order_id']);
            $order->update_status('completed');
        }
        $data = ["post" => $post_id, "product" => $post_meta['product_id']];
        if (isset($post_meta['variation_id'])) $data['variation'] = $post_meta['variation_id'];
        Action::status($action, sanitize_text_field($_POST['post_author']), $data);
    }
}
