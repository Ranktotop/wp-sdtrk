<?php

class Wp_Sdtrk_Tracker_Fb
{

    private $pixelId;

    private $apiToken;

    private $debugCode;

    private $debugMode;

    private $trackServer;

    public function __construct()
    {
        $this->pixelId = false;
        $this->apiToken = false;
        $this->debugCode = false;
        $this->debugMode = false;
        $this->trackServer = false;
        $this->init();
    }

    /**
     * Initialize the saved Data
     */
    private function init()
    {
        // Pixel ID
        $fb_pixelId = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "fb_pixelid");
        $this->pixelId = ($fb_pixelId && ! empty(trim($fb_pixelId))) ? $fb_pixelId : false;

        // Srv Token
        $fb_srvToken = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "fb_trk_server_token");
        $this->apiToken = ($fb_srvToken && ! empty(trim($fb_srvToken))) ? $fb_srvToken : false;

        // Test-Code
        $fb_testCode = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "fb_trk_server_debug_code");
        $this->debugCode = ($fb_testCode && ! empty(trim($fb_testCode))) ? $fb_testCode : false;

        // Track Server
        $this->trackServer = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "fb_trk_server"), "yes") == 0) ? true : false;

        // Debug Mode
        $this->debugMode = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "fb_trk_server_debug"), "yes") == 0) ? true : false;
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
        return $this->$functionName($event, $data);
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
        $response = $this->payLoadServerRequest($requestData);
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
        if ($this->readEventName($event) !== false && $this->readEventName($event) !== 'PageView') {
            $requestData = $this->getData_base($event);
            $requestData['event_name'] = $this->readEventName($event);
            $requestData["user_data"] = $this->getData_user($event, $data);
            $requestData['custom_data'] = $this->getData_custom($event);
            // Add value if given
            if ($event->getEventValue() > 0 || $this->readEventName($event) === 'Purchase') {
                $requestData['custom_data']['currency'] = "EUR";
                $requestData['custom_data']['value'] = $event->getEventValue();
            }
            $response = $this->payLoadServerRequest($requestData);
        }
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
        $scrollEventName = 'Scrolldepth-' . $data['percent'] . '-Percent';
        $event->setScrollTriggerData($scrollEventName, $scrollEventId);

        $requestData = $this->getData_base($event);
        $requestData['event_id'] = $event->getScrollTriggerData()['id'];
        $requestData['event_name'] = $event->getScrollTriggerData()['name'];
        $requestData["user_data"] = $this->getData_user($event, $data);
        $requestData['custom_data'] = $this->getData_custom($event);
        $response = $this->payLoadServerRequest($requestData);
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
        $timeEventName = 'Watchtime-' . $data['time'] . '-Seconds';
        $event->setTimeTriggerData($timeEventName, $timeEventId);

        $requestData = $this->getData_base($event);
        $requestData['event_id'] = $event->getTimeTriggerData()['id'];
        $requestData['event_name'] = $event->getTimeTriggerData()['name'];
        $requestData["user_data"] = $this->getData_user($event, $data);
        $requestData['custom_data'] = $this->getData_custom($event);
        $response = $this->payLoadServerRequest($requestData);
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
        $event->setClickTriggerData('ButtonClick', $clickEventId, $data['tag']);
        
        $requestData = $this->getData_base($event);
        $requestData['event_id'] = $event->getClickTriggerData()['id'];
        $requestData['event_name'] = $event->getClickTriggerData()['name'];
        $requestData["user_data"] = $this->getData_user($event, $data);
        $requestData['custom_data'] = $this->getData_custom($event);
        $requestData['custom_data']['buttonTag'] = $data['tag'];
        $response = $this->payLoadServerRequest($requestData);
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
        $event->setVisibilityTriggerData('ItemVisit', $visitEventId, $data['tag']);

        $requestData = $this->getData_base($event);
        $requestData['event_id'] = $event->getVisibilityTriggerData()['id'];
        $requestData['event_name'] = $event->getVisibilityTriggerData()['name'];
        $requestData["user_data"] = $this->getData_user($event, $data);
        $requestData['custom_data'] = $this->getData_custom($event);
        $requestData['custom_data']['itemTag'] = $data['tag'];
        $response = $this->payLoadServerRequest($requestData);
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
     * Converts the Raw-Eventname to Facebook-Event-Name
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     */
    private function readEventName($event)
    {
        $rawEvent = $event->getEventName();
        switch ($rawEvent) {
            case 'page_view':
                return 'PageView';
            case 'add_to_cart':
                return 'AddToCart';
            case 'purchase':
                return 'Purchase';
            case 'sign_up':
                return 'CompleteRegistration';
            case 'generate_lead':
                return 'Lead';
            case 'begin_checkout':
                return 'InitiateCheckout';
            case 'view_item':
                return 'ViewContent';
            default:
                return $rawEvent;
        }
    }
}
