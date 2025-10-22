<?php
// File: includes/models/class-wp-sdtrk-model-linkedin.php

/**
 * Model for entries in wp_sdtrk_linkedins.
 */
class WP_SDTRK_Model_Linkedin extends WP_SDTRK_Model_Base
{
    /**
     * Base table name (without WP prefix).
     *
     * @var string
     */
    protected static string $table = 'sdtrk_linkedin';

    /**
     * Columns and their WPDB format strings.
     *
     * @var array<string,string>
     */
    protected static array $db_fields = [
        'id' => '%d',
        'convid' => '%s',
        'event' => '%s',
        'rules' => '%s'
    ];

    /**
     * Columns that should never be mass-updated.
     *
     * @var string[]
     */
    protected static array $guarded = ['id'];

    /**
     * Columns that should be automatically cast.
     * - ipn_date → DateTime
     * - ipn      → JSON decode into array
     *
     * @var array<string,string>
     */
    protected static array $casts = [
        'rules'      => 'json',
    ];

    /** @var int|null Primary key */
    public ?int $id = null;

    /** @var string */
    public string $convid = '';

    /** @var string */
    public string $event = '';

    /** @var mixed[] Decoded JSON payload */
    public array $rules = [];

    /**********************************/
    /**********************************/
    /* CHECKER                        */
    /**********************************/
    /**********************************/

    /**
     * Checks if the current event is a scroll event.
     *
     * Determines whether the tracking event being processed is related to
     * user scrolling behavior on LinkedIn content or pages.
     *
     * @return bool True if the event is a scroll event, false otherwise.
     */
    public function is_scroll_event(): bool
    {
        $regex = $this->buildExtractRegex(WP_SDTRK_Helper_Event::get_scroll_event_pattern(), true);
        return preg_match($regex, $this->event) === 1;
    }

    /**
     * Checks if the current event is a time-based event.
     *
     * Determines whether the LinkedIn tracking event is triggered by time-based
     * conditions rather than user interactions or other event types.
     *
     * @return bool True if this is a time-based event, false otherwise.
     */
    public function is_time_event(): bool
    {
        $regex = $this->buildExtractRegex(WP_SDTRK_Helper_Event::get_time_event_pattern(), true);
        return preg_match($regex, $this->event) === 1;
    }

    /**
     * Checks if the current event is a button click event.
     *
     * @return bool True if this is a button click event, false otherwise.
     */
    public function is_button_click_event(): bool
    {
        $regex = $this->buildExtractRegex(WP_SDTRK_Helper_Event::get_button_click_event_pattern(), false);
        return preg_match($regex, $this->event) === 1;
    }

    /**
     * Checks if the current event is a element visibility event.
     *
     * @return bool True if this is a element visibility event, false otherwise.
     */
    public function is_element_visibility_event(): bool
    {
        $regex = $this->buildExtractRegex(WP_SDTRK_Helper_Event::get_element_visible_event_pattern(), false);
        return preg_match($regex, $this->event) === 1;
    }

    /**
     * Checks if the event is scroll or time based and the scroll or time value is still valid
     *
     * @return bool True if this is a valid custom event, false otherwise.
     */
    public function is_valid_event(): bool
    {
        if ($this->is_scroll_event()) {
            $scroll_triggers = WP_SDTRK_Helper_Options::get_scroll_triggers();
            if (!in_array($this->get_scroll_depth(), $scroll_triggers, true)) {
                return false;
            }
        }
        if ($this->is_time_event()) {
            $time_triggers = WP_SDTRK_Helper_Options::get_time_triggers();
            if (!in_array($this->get_time_seconds(), $time_triggers, true)) {
                return false;
            }
        }

        if ($this->is_button_click_event() || $this->is_element_visibility_event()) {
            if (empty($this->get_tag_name())) {
                return false;
            }
        }
        //if not custom event, return true
        return true;
    }

    /**********************************/
    /**********************************/
    /* GETTER                         */
    /**********************************/
    /**********************************/

    /**
     * Get the linkedin conversion id.
     *
     * @return string
     */
    public function get_conversion_id(): string
    {
        return $this->convid;
    }

    /**
     * Get the linkedin event name.
     *
     * @return string
     */
    public function get_event(): string
    {
        return $this->event;
    }

    /**
     * Get the event label for LinkedIn tracking.
     *
     * This method returns a string representation of the event label
     * used for LinkedIn tracking purposes.
     *
     * @return string The event label for LinkedIn tracking
     */
    public function get_event_label(): string
    {
        //if is scroll event, return label from scroll triggers
        if ($this->is_scroll_event()) {
            return sprintf(WP_SDTRK_Helper_Event::get_scroll_event_pattern(true), esc_html($this->get_scroll_depth()));
        }

        //if is time event, return label from time triggers
        if ($this->is_time_event()) {
            return sprintf(WP_SDTRK_Helper_Event::get_time_event_pattern(true), esc_html($this->get_time_seconds()));
        }

        //if is button click event, return label from button triggers
        if ($this->is_button_click_event()) {
            return sprintf(WP_SDTRK_Helper_Event::get_button_click_event_pattern(true), esc_html($this->get_tag_name()));
        }

        //if is element visibility event, return label from element triggers
        if ($this->is_element_visibility_event()) {
            return sprintf(WP_SDTRK_Helper_Event::get_element_visible_event_pattern(true), esc_html($this->get_tag_name()));
        }

        $events = WP_SDTRK_Helper_Event::get_default_events();

        //if the name is in events, return its label
        if (isset($events[$this->event])) {
            return $events[$this->event];
        }
        return $this->event;
    }

    /**
     * Get the scroll depth percentage for LinkedIn tracking.
     *
     * Retrieves the current scroll depth as an integer value representing
     * the percentage of the page that has been scrolled.
     *
     * @since 1.0.0
     * 
     * @return int The scroll depth percentage (0-100) or -1 if not a scroll event.
     */
    public function get_scroll_depth(): int
    {
        if ($this->is_scroll_event()) {
            $regex = $this->buildExtractRegex(WP_SDTRK_Helper_Event::get_scroll_event_pattern(), true);
            if (preg_match($regex, $this->event, $m) === 1) {
                return (int)trim($m[1]);
            }
        }
        return -1;
    }

    /**
     * Get the time duration in seconds.
     *
     * @return int The time duration in seconds or -1 if not a time event.
     */
    public function get_time_seconds(): int
    {
        if ($this->is_time_event()) {
            $regex = $this->buildExtractRegex(WP_SDTRK_Helper_Event::get_time_event_pattern(), true);
            if (preg_match($regex, $this->event, $m) === 1) {
                return (int)trim($m[1]);
            }
        }
        return -1;
    }

    /**
     * Get the tag name of tag based event.
     *
     * @return string The plain tag or an empty string if not tag based.
     */
    public function get_tag_name(): string
    {
        if ($this->is_button_click_event()) {
            $regex = $this->buildExtractRegex(WP_SDTRK_Helper_Event::get_button_click_event_pattern(), false);
            if (preg_match($regex, $this->event, $m) === 1) {
                return trim($m[1]);
            }
        } elseif ($this->is_element_visibility_event()) {
            $regex = $this->buildExtractRegex(WP_SDTRK_Helper_Event::get_element_visible_event_pattern(), false);
            if (preg_match($regex, $this->event, $m) === 1) {
                return trim($m[1]);
            }
        }
        return '';
    }

    /**
     * Get the rules.
     *
     * @return WP_SDTRK_Model_Linkedin_Rule[]
     */
    public function get_rules(): array
    {
        // return object for each rule
        $rules = [];
        foreach ($this->rules as $rule_data) {
            $rules[] = new WP_SDTRK_Model_Linkedin_Rule(
                $rule_data['param'] ?? '',
                $rule_data['value'] ?? ''
            );
        }
        return $rules;
    }

    /**********************************/
    /**********************************/
    /* SETTER                         */
    /**********************************/
    /**********************************/

    public function set_conversion_id(string $convid): static
    {
        if ('' === trim($convid)) {
            throw new \InvalidArgumentException('Conversion ID darf nicht leer sein.');
        }
        $this->convid = $convid;
        return $this;
    }

    public function set_event(string $event): static
    {
        $event = trim($event);
        $default_events = WP_SDTRK_Helper_Event::get_default_events();
        $dynamic_events = WP_SDTRK_Helper_Event::get_dynamic_events();
        $valid_events = array_merge(array_keys($default_events), array_keys($dynamic_events));

        // On tag based events we simply add the event if it matches the pattern and has a valid tag
        $regex_buttonclick = $this->buildExtractRegex(WP_SDTRK_Helper_Event::get_button_click_event_pattern(), false);
        $regex_elementvisibility = $this->buildExtractRegex(WP_SDTRK_Helper_Event::get_element_visible_event_pattern(), false);
        if (preg_match($regex_buttonclick, $event, $m) === 1) {
            $valid_events[] = $event;
        } elseif (preg_match($regex_elementvisibility, $event, $m) === 1) {
            $valid_events[] = $event;
        }

        if (!in_array($event, $valid_events, true)) {
            throw new \InvalidArgumentException(sprintf('Ungültiger Event-Typ: %s', $event));
        }
        $this->event = $event;
        return $this;
    }

    public function set_rules(string $rules_json): static
    {
        $decoded = json_decode($rules_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Ungültiges JSON für Rules.');
        }
        $this->rules = $decoded;
        return $this;
    }

    /**********************************/
    /**********************************/
    /* OTHER                          */
    /**********************************/
    /**********************************/

    private function buildExtractRegex(string $pattern, bool $numbersOnly = true): string
    {
        $capture = $numbersOnly ? '(\d+)' : '(.+)';
        return '/^' . str_replace('%s', $capture, preg_quote($pattern, '/')) . '$/';
    }
}
