<?php

/**
 * Server-side tracker for ChatGPT Ads (OpenAI Conversions API)
 * https://developers.openai.com/ads/conversions-api
 */
class Wp_Sdtrk_Tracker_Oai
{

    private $pixelId;

    private $apiToken;

    private $debugMode;
    private $debugMode_frontend;

    private $trackServer;

    public function __construct()
    {
        $this->pixelId = false;
        $this->apiToken = false;
        $this->debugMode = false;
        $this->debugMode_frontend = false;
        $this->trackServer = false;
        $this->init();
    }

    /**
     * Initialize the saved Data
     */
    private function init()
    {
        // Pixel ID
        $this->pixelId = WP_SDTRK_Helper_Options::get_string_option('oai_pixelid');

        // Conversions API key
        $this->apiToken = WP_SDTRK_Helper_Options::get_string_option('oai_trk_server_token');

        // Track Server
        $this->trackServer = WP_SDTRK_Helper_Options::get_bool_option('oai_trk_server', false);

        // Debug Mode
        $this->debugMode = WP_SDTRK_Helper_Options::get_bool_option('oai_trk_debug', false);
    }

    /**
     * Returns the API Url to the Conversions API
     *
     * @return string|boolean
     */
    private function getApiUrl()
    {
        if ($this->pixelId && $this->apiToken) {
            return 'https://bzr.openai.com/v1/events?pid=' . rawurlencode($this->pixelId);
        }
        return false;
    }

    /**
     * Checks if Server-Tracking is enabled
     *
     * @return boolean
     */
    private function trackingEnabled_Server()
    {
        return ($this->pixelId && $this->trackServer && $this->apiToken && $this->getApiUrl());
    }

    /**
     * Set and return the frontend debug mode
     * @param Boolean|String $debugMode
     */
    public function setAndGetDebugMode_frontend($debugMode)
    {
        $this->debugMode_frontend = ($debugMode === true || $debugMode === '1') ? true : false;
        return ($this->debugMode_frontend === true && $this->debugMode === true);
    }

    /**
     * Fires the Server-based Tracking
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     * @param String $handler
     * @param Array $data
     * @return boolean
     */
    public function fireTracking_Server($event, $handler, $data)
    {
        // Abort if tracking is disabled
        if (! $this->trackingEnabled_Server()) {
            return true;
        }
        // Check if given handler exists
        $functionName = 'fireTracking_Server_' . $handler;
        if (! method_exists($this, $functionName)) {
            return false;
        }
        $response = $this->$functionName($event, $data);
        sdtrk_log("Response:", "debug", !$this->debugMode);
        sdtrk_log($response, "debug", !$this->debugMode);
        return ($this->setAndGetDebugMode_frontend($this->debugMode_frontend)) ? $response : true;
    }

    /**
     * Fires the Page-Hit-Tracking
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     * @param Array $data
     * @return array
     */
    private function fireTracking_Server_Page($event, $data)
    {
        $requestData = $this->getData_base($event, $data, 'page_viewed', $event->getEventId());
        $requestData['data'] = array(
            'type' => 'contents',
            'contents' => array(array(
                'id' => (string) $event->getPageId(),
                'name' => (string) $event->getPageName(),
                'content_type' => 'page'
            ))
        );
        return $this->payLoadServerRequest($requestData);
    }

    /**
     * Fires the Page-Hit-Event-Tracking (conversion events)
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     * @param Array $data
     * @return array|boolean
     */
    private function fireTracking_Server_Event($event, $data)
    {
        $name = $this->convert_eventname($event);
        if ($name === false) {
            return false;
        }
        $requestData = $this->getData_base($event, $data, $name, $event->getEventId());
        $requestData['data'] = $this->getData_event($event, $name);
        return $this->payLoadServerRequest($requestData);
    }

    /**
     * Return the base data of an event (one item of the "events" array)
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     * @param array $data
     * @param string $type The OpenAI event type
     * @param string $id The event id shared with the pixel
     * @return array
     */
    private function getData_base($event, $data, $type, $id)
    {
        return array(
            'id' => (string) $id,
            'type' => $type,
            'timestamp_ms' => $this->getTimestampMs($event),
            'source_url' => $event->getEventSource(),
            'action_source' => 'web'
        );
    }

    /**
     * Event time in milliseconds. OpenAI only accepts the last 7 days up to 10
     * minutes ahead, so an out-of-range client clock falls back to server time.
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     * @return int
     */
    private function getTimestampMs($event)
    {
        $now = time();
        $time = intval($event->getTime());
        if ($time < $now - 7 * 86400 + 60 || $time > $now + 600) {
            $time = $now;
        }
        return $time * 1000;
    }

    /**
     * Return the data object of a conversion event
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     * @param string $name The OpenAI event name
     * @return array
     */
    private function getData_event($event, $name)
    {
        $eventData = array('type' => $this->get_data_type($name));
        $currency = $event->getCurrency();
        if ($event->getEventValue() > 0 || $name === 'order_created') {
            $eventData['amount'] = self::toMinorUnits($event->getEventValue(), $currency);
            $eventData['currency'] = $currency;
        }
        // customer_action carries no contents
        if ($eventData['type'] !== 'contents') {
            return $eventData;
        }
        $contents = $this->getData_contents_list($event, $currency);
        if (! empty($contents)) {
            $eventData['contents'] = $contents;
        }
        return $eventData;
    }

    /**
     * Return the cart contents — the whole cart when per-line items are present,
     * the single product otherwise, empty when the event has no product.
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     * @param string $currency
     * @return array
     */
    private function getData_contents_list($event, $currency)
    {
        $items = $event->getItems();
        if (! empty($items)) {
            $list = array();
            foreach ($items as $item) {
                $content = array(
                    'id'           => (string) ($item['id'] ?? ''),
                    'name'         => (string) ($item['name'] ?? ''),
                    'content_type' => 'product',
                    'quantity'     => max(1, (int) ($item['qty'] ?? 1)),
                );
                $price = (float) ($item['price'] ?? 0);
                if ($price > 0) {
                    $content['amount'] = self::toMinorUnits($price, $currency);
                    $content['currency'] = $currency;
                }
                $list[] = $content;
            }
            return $list;
        }
        if (! empty($event->getProductId())) {
            return array(array(
                'id'           => (string) $event->getProductId(),
                'name'         => (string) $event->getProductName(),
                'content_type' => 'product',
                'quantity'     => 1
            ));
        }
        return array();
    }

    /**
     * Converts an amount to an integer in the currency's ISO 4217 minor unit
     * (e.g. 25.99 EUR -> 2599, 1500 JPY -> 1500, 1.5 KWD -> 1500)
     *
     * @param float $value The amount in major units
     * @param string $currency The ISO 4217 currency code
     * @return int
     */
    public static function toMinorUnits($value, $currency)
    {
        $exponents = array(
            'BIF' => 0, 'CLP' => 0, 'DJF' => 0, 'GNF' => 0, 'ISK' => 0, 'JPY' => 0, 'KMF' => 0, 'KRW' => 0, 'PYG' => 0,
            'RWF' => 0, 'UGX' => 0, 'UYI' => 0, 'VND' => 0, 'VUV' => 0, 'XAF' => 0, 'XOF' => 0, 'XPF' => 0,
            'BHD' => 3, 'IQD' => 3, 'JOD' => 3, 'KWD' => 3, 'LYD' => 3, 'OMR' => 3, 'TND' => 3,
            'CLF' => 4, 'UYW' => 4,
        );
        $code = strtoupper((string) $currency);
        $exponent = $exponents[$code] ?? 2;
        return (int) round(((float) $value) * pow(10, $exponent));
    }

    /**
     * Returns the OpenAI data shape for an OpenAI event name
     *
     * @param string $name
     * @return string
     */
    private function get_data_type($name)
    {
        switch ($name) {
            case 'lead_created':
            case 'registration_completed':
                return 'customer_action';
            default:
                return 'contents';
        }
    }

    /**
     * Payloads the Data and sends it to the Server
     *
     * @param array $requestData One event of the "events" array
     * @return array
     */
    private function payLoadServerRequest($requestData)
    {
        $fields = array(
            'validate_only' => false,
            'events' => array($requestData)
        );

        sdtrk_log($fields, "debug", !$this->debugMode);
        $payload = json_encode($fields);
        sdtrk_log($payload, "debug", !$this->debugMode);
        $headers = array(
            'Authorization: Bearer ' . $this->apiToken
        );

        // Send Request
        return WP_SDTRK_Helper_Event::do_post($this->getApiUrl(), $payload, $headers, $this->debugMode);
    }

    /**
     * Converts the Raw-Eventname to the OpenAI event name
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     * @return string|boolean
     */
    private function convert_eventname($event)
    {
        switch ($event->getEventName()) {
            case 'view_item':
                return 'contents_viewed';
            case 'generate_lead':
                return 'lead_created';
            case 'sign_up':
                return 'registration_completed';
            case 'add_to_cart':
                return 'items_added';
            case 'begin_checkout':
                return 'checkout_started';
            case 'purchase':
                return 'order_created';
            default:
                return false;
        }
    }
}
