<?php
// File: includes/helpers/class-wp-sdtrk-helper-linkedin.php

/**
 * @extends WP_SDTRK_Helper_Base<WP_SDTRK_Model_Linkedin>
 */
class WP_SDTRK_Helper_Linkedin extends WP_SDTRK_Helper_Base
{
    /** @var string Table name without prefix */
    protected static string $table = 'sdtrk_linkedin';

    /**
     * @var class-string<WP_SDTRK_Model_Linkedin>
     */
    protected static string $model_class = WP_SDTRK_Model_Linkedin::class;

    /**
     * Retrieves an array of common LinkedIn tracking events.
     *
     * This method returns a predefined list of standard LinkedIn Insight Tag events
     * that are commonly used for tracking user interactions and conversions.
     *
     * @since 1.0.0
     *
     * @return array An array of common LinkedIn tracking event names/identifiers.
     */
    public static function get_common_events(): array
    {
        return [
            'page_view'       => __('Page View', 'wp-sdtrk'),
            'add_to_cart'    => __('Add to Cart', 'wp-sdtrk'),
            'purchase'       => __('Purchase', 'wp-sdtrk'),
            'sign_up'        => __('Sign Up', 'wp-sdtrk'),
            'generate_lead'  => __('Generate Lead', 'wp-sdtrk'),
            'begin_checkout' => __('Begin Checkout', 'wp-sdtrk'),
            'view_item'      => __('View Item', 'wp-sdtrk'),
        ];
    }

    /**
     * Retrieve the currently saved mappings
     * 
     * @return WP_SDTRK_Model_Linkedin[]
     */
    public static function get_all_mappings(): array
    {
        global $wpdb;
        $table = static::getTableName();

        $sql = "SELECT * FROM {$table} ORDER BY id ASC";

        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];

        return array_map(
            fn(array $row) => static::$model_class::load_by_row($row),
            $rows
        );
    }

    /**
     * Retrieve a single mapping by event and conversion ID
     * 
     * @param string $event The event name
     * @param string $convid The conversion ID
     * @return WP_SDTRK_Model_Linkedin|null Returns the mapping or null if not found
     */
    public static function get_by_event_and_convid(string $event, string $convid): ?WP_SDTRK_Model_Linkedin
    {
        global $wpdb;
        $table = static::getTableName();

        $sql = "SELECT * FROM {$table} WHERE event = %s AND convid = %s LIMIT 1";

        $row = $wpdb->get_row(
            $wpdb->prepare($sql, $event, $convid),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        return static::$model_class::load_by_row($row);
    }

    /**
     * Create a new mapping.
     *
     * @param  string $convid
     * @param  string $event
     * @param  mixed[] $rules
     * @return WP_SDTRK_Model_Linkedin
     * @throws Exception On duplicate event convid or DB error.
     */
    public static function create(string $convid, string $event, array $rules): WP_SDTRK_Model_Linkedin
    {
        global $wpdb;
        $table = static::getTableName();

        // Unique check
        $exists = static::get_by_event_and_convid($event, $convid);
        if ($exists) {
            throw new \Exception(sprintf(
                __('Mapping with event "%s" and conversion ID "%s" already exists.', 'wp-sdtrk'),
                $event,
                $convid
            ));
        }

        // encode rules
        $rules = json_encode($rules);

        $ok = $wpdb->insert(
            $table,
            compact('convid', 'event', 'rules'),
            ['%s', '%s', '%s']
        );
        if (false === $ok) {
            throw new \Exception("DB insert error: {$wpdb->last_error}");
        }

        return static::get_by_id((int)$wpdb->insert_id);
    }

    /**
     * Update an existing mapping.
     *
     * @param  int    $id
     * @param  string $event
     * @param  string $convid
     * @param  mixed[] $rules
     * @return WP_SDTRK_Model_Linkedin
     * @throws Exception On DB error.
     */
    public static function update(int $id, string $event, string $convid, array $rules): WP_SDTRK_Model_Linkedin
    {
        global $wpdb;
        $table = static::getTableName();

        $ok = $wpdb->update(
            $table,
            compact('event', 'convid', 'rules'),
            ['id' => $id],
            ['%s', '%s', '%s'],
            ['%d']
        );
        if (false === $ok) {
            throw new \Exception("DB update error: {$wpdb->last_error}");
        }

        return static::get_by_id($id);
    }

    /**
     * Delete a mapping by ID.
     *
     * @param  int $id
     * @return bool
     * @throws Exception On DB error.
     */
    public static function delete(int $id): bool
    {
        global $wpdb;
        $ok = $wpdb->delete(
            static::getTableName(),
            ['id' => $id],
            ['%d']
        );
        if (false === $ok) {
            throw new \Exception("DB delete error: {$wpdb->last_error}");
        }
        return (bool)$ok;
    }
}
