<?php

class Wp_Sdtrk_Tracker_Fb
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
        $this->pixelId = WP_SDTRK_Helper_Options::get_string_option('meta_pixelid');

        // Srv Token
        $this->apiToken = WP_SDTRK_Helper_Options::get_string_option('meta_trk_server_token');

        // Test-Code
        $this->debugCode = WP_SDTRK_Helper_Options::get_string_option('meta_trk_server_debug_code');

        // Track Server
        $this->trackServer = WP_SDTRK_Helper_Options::get_bool_option('meta_trk_server', false);

        // Debug Mode
        $this->debugMode = WP_SDTRK_Helper_Options::get_bool_option('meta_trk_debug', false);
    }

    /**
     * Returns the API Url to the Conversion API
     *
     * @return string
     */
    private function getApiUrl()
    {
        if ($this->pixelId && $this->apiToken) {
            return 'https://graph.facebook.com/v11.0/' . $this->pixelId . '/events?access_token=' . $this->apiToken;
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
        $requestData = $this->getData_base($event);
        $requestData['event_name'] = "PageView";
        $requestData["user_data"] = $this->getData_user($event, $data);
        $requestData['custom_data'] = $this->getData_custom($event);
        return $this->payLoadServerRequest($requestData);
    }

    /**
     * Fires the Page-Hit-Event-Tracking
     * Note: These hits are only fired if there is an event-name given
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     * @param Array $data
     * @return boolean
     */
    private function fireTracking_Server_Event($event, $data)
    {
        $requestData = $this->getData_base($event);
        $requestData['event_name'] = $this->convert_eventname($event);
        $requestData["user_data"] = $this->getData_user($event, $data);
        $requestData['custom_data'] = $this->getData_custom($event);
        // Add value if given
        if ($event->getEventValue() > 0 || $this->convert_eventname($event) === 'Purchase') {
            $requestData['custom_data']['currency'] = "EUR";
            $requestData['custom_data']['value'] = $event->getEventValue();
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
        $scrollEventId = $event->getEventId() . "-s" . $data['percent'];
        $scrollEventName = $event->get_CustomEventName('Scroll', $data['percent']);
        $event->setScrollTriggerData($scrollEventName, $scrollEventId);

        $requestData = $this->getData_base($event);
        $requestData['event_id'] = $event->getScrollTriggerData()['id'];
        $requestData['event_name'] = $event->getScrollTriggerData()['name'];
        $requestData["user_data"] = $this->getData_user($event, $data);
        $requestData['custom_data'] = $this->getData_custom($event);
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
        $timeEventId = $event->getEventId() . "-t" . $data['time'];
        $timeEventName = $event->get_CustomEventName('Time', $data['time']);
        $event->setTimeTriggerData($timeEventName, $timeEventId);

        $requestData = $this->getData_base($event);
        $requestData['event_id'] = $event->getTimeTriggerData()['id'];
        $requestData['event_name'] = $event->getTimeTriggerData()['name'];
        $requestData["user_data"] = $this->getData_user($event, $data);
        $requestData['custom_data'] = $this->getData_custom($event);
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
        $clickEventId = $event->getEventId() . "-b" . $data['tag'];
        $event->setClickTriggerData($event->get_CustomEventName('Click', $data['tag']), $clickEventId, $data['tag']);

        $requestData = $this->getData_base($event);
        $requestData['event_id'] = $event->getClickTriggerData()['id'];
        $requestData['event_name'] = $event->getClickTriggerData()['name'];
        $requestData["user_data"] = $this->getData_user($event, $data);
        $requestData['custom_data'] = $this->getData_custom($event);
        $requestData['custom_data']['buttonTag'] = $data['tag'];
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
        $visitEventId = $event->getEventId() . "-v" . $data['tag'];
        $event->setVisibilityTriggerData($event->get_CustomEventName('Visibility', $data['tag']), $visitEventId, $data['tag']);

        $requestData = $this->getData_base($event);
        $requestData['event_id'] = $event->getVisibilityTriggerData()['id'];
        $requestData['event_name'] = $event->getVisibilityTriggerData()['name'];
        $requestData["user_data"] = $this->getData_user($event, $data);
        $requestData['custom_data'] = $this->getData_custom($event);
        $requestData['custom_data']['itemTag'] = $event->getVisibilityTriggerData()['tag'];
        return $this->payLoadServerRequest($requestData);
    }

    /**
     * Return the base data of event
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     * @return array
     */
    private function getData_base($event)
    {
        $baseData = array(
            "event_time" => $event->getTime(),
            "event_id" => $event->getEventId(),
            "event_source_url" => $event->getEventSource(),
            "action_source" => "website"
        );

        // Product
        if (! empty($event->getProductId())) {
            $baseData['contents'] = array(
                array(
                    "id" => $event->getProductId(),
                    "quantity" => 1
                )
            );
        }
        return $baseData;
    }

    /**
     * Return the custom data of event
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     * @return array
     */
    private function getData_custom($event)
    {
        // Collect the Custom-Data
        $customData = $event->getUtmData();

        // Product
        if (! empty($event->getProductId())) {
            $customData['content_ids'] = '["' . $event->getProductId() . '"]';
            $customData['content_type'] = "product";
            $customData['content_name'] = $event->getProductName();
        }
        return $customData;
    }

    /**
     * Return the user-data
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     * @param array $data
     * @return array
     */
    private function getData_user($event, $data)
    {
        $userData = array(
            "client_ip_address" => $event->getEventIp(),
            "client_user_agent" => $event->getEventAgent()
        );
        if (isset($data["fbp"])) {
            $userData["fbp"] = $data["fbp"];
        }
        if (isset($data["fbc"])) {
            $userData["fbc"] = $data["fbc"];
        }
        if ($event->getUserFirstName()) {
            $userData["fn"] = hash('sha256', $event->getUserFirstName());
        }
        if ($event->getUserLastName()) {
            $userData["ln"] = hash('sha256', $event->getUserLastName());
        }
        if ($event->getUserEmail()) {
            $userData["em"] = hash('sha256', $event->getUserEmail());
        }
        return $userData;
    }

    /**
     * Payloads the Data and sends it to the Server
     *
     * @param array $requestData
     */
    private function payLoadServerRequest($requestData)
    {
        // Create the Payload
        $fields = array(
            "data" => array(
                0 => $requestData
            )
        );
        if ($this->debugEnabled_Server()) {
            $fields["test_event_code"] = $this->debugCode;
        }
        $payload = json_encode($fields);

        // Send Request
        return Wp_Sdtrk_Helper::wp_sdtrk_httpPost($this->getApiUrl(), $payload, array(), $this->debugMode);
    }

    /**
     * Converts an EventName to FB-EventName
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     */
    private function convert_eventname($event)
    {
        switch ($event->getEventName()) {
            case 'page_view':
                return 'PageView';
            case 'view_item':
                return 'ViewContent';
            case 'generate_lead':
                return 'Lead';
            case 'sign_up':
                return 'CompleteRegistration';
            case 'add_to_cart':
                return 'AddToCart';
            case 'begin_checkout':
                return 'InitiateCheckout';
            case 'purchase':
                return 'Purchase';
            default:
                return false;
        }
    }
}
