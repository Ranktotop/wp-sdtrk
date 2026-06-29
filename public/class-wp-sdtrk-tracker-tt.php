<?php

class Wp_Sdtrk_Tracker_Tt
{

    private $pixelId;

    private $apiToken;

    private $debugCode;

    private $debugMode;
    private $debugMode_frontend;

    private $trackServer;

    public function __construct()
    {
        $this->pixelId = false;
        $this->apiToken = false;
        $this->debugCode = false;
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
        $this->pixelId = WP_SDTRK_Helper_Options::get_string_option('tt_pixelid');

        // Srv Token
        $this->apiToken = WP_SDTRK_Helper_Options::get_string_option('tt_trk_server_token');

        // Track Server
        $this->trackServer = WP_SDTRK_Helper_Options::get_bool_option('tt_trk_server', false);

        // Debug Mode
        $this->debugMode = WP_SDTRK_Helper_Options::get_bool_option('tt_trk_debug', false);

        // Test-Code
        $this->debugCode = WP_SDTRK_Helper_Options::get_string_option('tt_trk_server_debug_code');
    }

    /**
     * Returns the API Url to the Conversion API
     *
     * @return string
     */
    private function getApiUrl()
    {
        if ($this->pixelId && $this->apiToken) {
            return 'https://business-api.tiktok.com/open_api/v1.3/event/track/';
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
     * Checks if Debug is enabled
     *
     * @return boolean
     */
    private function debugEnabled_Server()
    {
        return ($this->debugMode && $this->debugCode);
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
        //NOTE UTMs seems to be unsupported , if that changes they will be added
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
     * @return boolean
     */
    private function fireTracking_Server_Page($event, $data)
    {
        $requestData = $this->getData_base($event, $data);
        $requestData['event'] = "ViewContent"; // TT doesnt have an PageView-Event
        return $this->payLoadServerRequest($requestData);
    }

    /**
     * Fires the Page-Hit-Event-Tracking
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     * @param Array $data
     * @return boolean
     */
    private function fireTracking_Server_Event($event, $data)
    {
        // The Conversion-Events
        $requestData = $this->getData_base($event, $data);
        $requestData['event'] = $this->convert_eventname($event);
        $requestData["properties"]["description"] = $this->convert_eventname($event);

        // Add value if given
        if ($event->getEventValue() > 0 || $this->convert_eventname($event) === 'PlaceAnOrder') {
            $requestData["properties"]["currency"] = $event->getCurrency();
            $requestData["properties"]["value"] = $event->getEventValue();
        }
        return $this->payLoadServerRequest($requestData);
    }

    /**
     * Fires the Scroll-Hit-Tracking
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     * @param Array $data
     * @return boolean
     */
    private function fireTracking_Server_Scroll($event, $data)
    {

        // Update the event
        $scrollEventId = $event->getEventId() . "-s" . $data['percent'] . '_' . $data['hash'];
        $scrollEventName = $event->get_CustomEventName('Scroll', $data['percent']);
        $event->setScrollTriggerData($scrollEventName, $scrollEventId);

        $requestData = $this->getData_base($event, $data);
        $requestData['event'] = $event->getScrollTriggerData()['name'];
        $requestData['event_id'] = $event->getScrollTriggerData()['id'];
        return $this->payLoadServerRequest($requestData);
    }

    /**
     * Fires the Time-Hit-Tracking
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     * @param Array $data
     * @return boolean
     */
    private function fireTracking_Server_Time($event, $data)
    {
        // Update the event
        $timeEventId = $event->getEventId() . "-t" . $data['time'] . '_' . $data['hash'];
        $timeEventName = $event->get_CustomEventName('Time', $data['time']);
        $event->setTimeTriggerData($timeEventName, $timeEventId);

        $requestData = $this->getData_base($event, $data);
        $requestData['event'] = $event->getTimeTriggerData()['name'];
        $requestData['event_id'] = $event->getTimeTriggerData()['id'];
        return $this->payLoadServerRequest($requestData);
    }

    /**
     * Fires the Click-Hit-Tracking
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     * @param Array $data
     * @return boolean
     */
    private function fireTracking_Server_Click($event, $data)
    {
        // Update the event
        $clickEventId = $event->getEventId() . "-b" . $data['tag'] . '_' . $data['hash'];
        $event->setClickTriggerData($event->get_CustomEventName('Click', $data['tag']), $clickEventId, $data['tag']);

        $requestData = $this->getData_base($event, $data);
        $requestData['event'] = $event->getClickTriggerData()['name'];
        $requestData['event_id'] = $event->getClickTriggerData()['id'];
        $requestData["properties"]["description"] = $event->getClickTriggerData()['name'] . "/" . $data['tag'];
        return $this->payLoadServerRequest($requestData);
    }

    /**
     * Fires the Scroll-Hit-Tracking
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     * @param Array $data
     * @return boolean
     */
    private function fireTracking_Server_Visibility($event, $data)
    {
        // Update the event
        $visitEventId = $event->getEventId() . "-v" . $data['tag'] . '_' . $data['hash'];
        $event->setVisibilityTriggerData($event->get_CustomEventName('Visibility', $data['tag']), $visitEventId, $data['tag']);

        $requestData = $this->getData_base($event, $data);
        $requestData['event'] = $event->getVisibilityTriggerData()['name'];
        $requestData['event_id'] = $event->getVisibilityTriggerData()['id'];
        $requestData["properties"]["description"] = $event->getVisibilityTriggerData()['name'] . "/" . $data['tag'];
        return $this->payLoadServerRequest($requestData);
    }

    /**
     * Return the base data of an event (one item of the v1.3 "data" array)
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     * @return array
     */
    private function getData_base($event, $data)
    {
        // One event object inside the v1.3 "data" array
        $baseData = array(
            "event_id" => $event->getEventId() . "_" . $data['hash'],
            "event_time" => intval($event->getTime()), // Unix timestamp in seconds
            "page" => array(
                "url" => $event->getEventSource(),
                "referrer" => $event->getEventReferer()
            ),
            "user" => $this->getData_user($event, $data),
            "properties" => array(
                "contents" => $this->getData_contents_list($event) // array of contents (whole cart)
                // "description" => "ViewContent", // will be replaced later on events
                // "query" => "" // you can pass a keyword from on-page-search here
            )
        );
        return $baseData;
    }

    /**
     * Return the user-data (v1.3: ip/user_agent live inside "user")
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     * @param array $data
     * @return array
     */
    private function getData_user($event, $data)
    {
        // User-Data
        $userData = array(
            "ip" => $event->getEventIp(),
            "user_agent" => $event->getEventAgent()
        );
        if ($event->getUserEmail()) {
            $userData["email"] = hash('sha256', $event->getUserEmail());
        }
        // ttclid: the click-id appended as the ttclid GET-param
        if (isset($data['ttc'])) {
            $userData["ttclid"] = $data['ttc'];
        }
        // ttp: the _ttp first-party cookie
        if (isset($data['ttp'])) {
            $userData["ttp"] = $data['ttp'];
        }
        return $userData;
    }

    /**
     * Return the cart contents as a list — the whole cart when per-line items are
     * present, single-product fallback otherwise. Always at least one entry.
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     * @return array
     */
    private function getData_contents_list($event)
    {
        $items = $event->getItems();
        if (! empty($items)) {
            $list = array();
            foreach ($items as $item) {
                $content = array(
                    'content_id'   => (string) ($item['id'] ?? ''),
                    'content_name' => (string) ($item['name'] ?? ''),
                    'content_type' => "product",
                    'quantity'     => (int) ($item['qty'] ?? 1),
                );
                $price = (float) ($item['price'] ?? 0);
                if ($price > 0) {
                    $content['price'] = $price;
                }
                $list[] = $content;
            }
            return $list;
        }
        return array($this->getData_contents($event));
    }

    /**
     * Return the single content object (fallback for non-cart events).
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     * @return array
     */
    private function getData_contents($event)
    {
        // Product
        $contentId = (! empty($event->getProductId())) ? $event->getProductId() : $event->getPageId();
        $contentName = (! empty($event->getProductId())) ? $event->getProductName() : $event->getPageName();
        $contents = array(
            'content_id' => $contentId,
            'content_name' => $contentName,
            'content_type' => "product",
            'quantity' => 1
        );

        if ($event->getEventValue() > 0 || $this->convert_eventname($event) === 'PlaceAnOrder') {
            $contents["price"] = $event->getEventValue();
        }

        return $contents;
    }

    /**
     * Payloads the Data and sends it to the Server
     *
     * @param array $requestData
     */
    private function payLoadServerRequest($requestData)
    {
        // Wrap the single event into the v1.3 envelope
        $fields = array(
            "event_source" => "web",
            "event_source_id" => $this->pixelId,
            "data" => array($requestData)
        );
        if ($this->debugEnabled_Server()) {
            $fields["test_event_code"] = $this->debugCode;
        }

        sdtrk_log($fields, "debug", !$this->debugEnabled_Server());
        $payload = json_encode($fields);
        sdtrk_log($payload, "debug", !$this->debugEnabled_Server());
        $headers = array();
        array_push($headers, "Access-Token:" . $this->apiToken);

        // Send Request
        return WP_SDTRK_Helper_Event::do_post($this->getApiUrl(), $payload, $headers, $this->debugMode);
    }

    /**
     * Converts the Raw-Eventname to Tik Tok-Event-Name
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     */
    private function convert_eventname($event)
    {
        switch ($event->getEventName()) {
            case 'page_view':
                return 'ViewContent';
            case 'view_item':
                return 'ViewContent';
            case 'generate_lead':
                return 'SubmitForm';
            case 'sign_up':
                return 'CompleteRegistration';
            case 'add_to_cart':
                return 'AddToCart';
            case 'begin_checkout':
                return 'InitiateCheckout';
            case 'purchase':
                return 'PlaceAnOrder';
            default:
                return false;
        }
    }
}
