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
        $debug = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "fb_trk_server_debug"), "yes") == 0) ? true : false;
        $this->debugMode = ($debug && ! empty(trim($debug))) ? $debug : false;
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
     */
    public function fireTracking_Server($event, $fbp = "", $fbc = "")
    {
        // Abort if tracking is disabled
        if (! $this->trackingEnabled_Server()) {
            return;
        }

        // ---Prepare Request
        // Base-Data
        $requestData = array(
            "event_time" => $event->getTime(),
            "event_id" => $event->getEventId(),
            "event_source_url" => $event->getEventSource(),
            "action_source" => "website",
            "user_data" => array(
                "client_ip_address" => $event->getEventIp(),
                "client_user_agent" => $event->getEventAgent(),
                "fbc" => $fbc,
                "fbp" => $fbp
            )
        );

        // Collect the Custom-Data
        $customData = $event->getUtmData();

        // Product
        if (! empty($event->getProductId())) {
            $customData['content_ids'] = '["' . $event->getProductId() . '"]';
            $customData['content_type'] = "product";
            $customData['content_name'] = $event->getProductName();
            $requestData['contents'] = array(
                array(
                    "id" => $event->getProductId(),
                    "quantity" => 1
                )
            );
        }

        // ---Send Request
        // The PageView
        $requestData['event_name'] = "PageView";
        $requestData['custom_data'] = $customData;
        $this->payLoadServerRequest($requestData);

        // The Event
        if ($this->readEventName($event) !== false && $this->readEventName($event) !== 'PageView') {
            if ($event->getEventValue() > 0 || $this->readEventName($event) === 'Purchase') {
                $customData['currency'] = "EUR";
                $customData['value'] = $event->getEventValue();
            }
            $requestData['event_name'] = $this->readEventName($event);
            $requestData['custom_data'] = $customData;
            $this->payLoadServerRequest($requestData);
        }
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
        Wp_Sdtrk_Helper::wp_sdtrk_httpPost($this->getApiUrl(), $payload);
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
