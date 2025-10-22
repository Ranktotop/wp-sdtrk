<?php
// File: includes/models/class-wp-sdtrk-model-linkedin.php

/**
 * Model for entries in wp_sdtrk_linkedins.
 */
class WP_SDTRK_Model_Linkedin_Rule
{
    private string $key_name;
    private string $value;

    public function __construct(string $key_name, string $value)
    {
        $this->key_name = $key_name;
        $this->value = $value;
    }

    /**********************************/
    /* GETTER                         */
    /**********************************/

    public function get_key_name(): string
    {
        return $this->key_name;
    }
    public function get_value(): string
    {
        return $this->value;
    }
    public function get_label(): string
    {
        $map = [
            "prodid" => __('Product ID', 'wp-sdtrk'),
            "prodname" => __('Product Name', 'wp-sdtrk'),
        ];
        return $map[$this->key_name] ?? $this->key_name;
    }
}
