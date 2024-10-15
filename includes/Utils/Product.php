<?php

namespace SpringDevs\Subscription\Utils;

use SpringDevs\Subscription\Illuminate\Helper;

abstract class Product
{

    protected \WC_Product $product;

    public function __construct(\WC_Product $product)
    {
        $this->product = $product;
    }

    public function get_trial(): ?string
    {
        if ($this->has_trial()) {
            $product_trial_per = $this->get_trial_timing_per();

            return $product_trial_per . ' ' . Helper::get_typos($product_trial_per, $this->get_trial_timing_option());
        }

        return null;
    }

    public function has_trial(): bool
    {
        $trial_timing_per = $this->get_trial_timing_per();
        $product_id = $this->product->get_id();

        return ($trial_timing_per > 0) && Helper::check_trial($product_id);
    }

    public function get_button_label()
    {
        return $this->product->get_meta('_subscrpt_cart_btn_label');
    }

    public function __call($name, $arguments)
    {
        return $this->product->{$name}(...$arguments);
    }

    public function get_timing_per(): int
    {
        return 1;
    }

    public function get_timing_option(): string
    {
        return Helper::get_typos($this->get_timing_per(), $this->product->get_meta('_subscrpt_timing_option'));
    }

    public function get_limit()
    {
        return $this->product->get_meta('_subscrpt_limit');
    }

    public function is_enabled(): bool
    {
        return $this->product->get_meta('_subscrpt_enabled');
    }

    public function get_trial_timing_per(): int
    {
        return (int) ($this->product->get_meta('_subscrpt_trial_timing_per') ?? 1);
    }

    public function get_trial_timing_option()
    {
        return $this->product->get_meta('_subscrpt_trial_timing_option');
    }

    public function get_signup_fee(): int
    {
        return 0;
    }
}
