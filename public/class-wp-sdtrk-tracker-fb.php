<?php

class Wp_Sdtrk_Tracker_Fb
{

    private $pixelId;

    private $apiToken;

    private $debugCode;

    private $debugMode;

    private $trackServer;

    private $trackBrowser;

    public function __construct()
    {
        $this->pixelId = false;
        $this->apiToken = false;
        $this->debugCode = false;
        $this->debugMode = false;
        $this->trackServer = false;
        $this->trackBrowser = false;
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
        $trkServer = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "fb_trk_server"), "yes") == 0) ? true : false;
        $this->trackServer = ($trkServer && ! empty(trim($trkServer))) ? $trkServer : false;

        // Track Browser
        $trkBrowser = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "fb_trk_browser"), "yes") == 0) ? true : false;
        $this->trackBrowser = ($trkBrowser && ! empty(trim($trkBrowser))) ? $trkBrowser : false;

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
     * Checks if Browser-Tracking is enabled
     *
     * @return boolean
     */
    private function trackingEnabled_Browser()
    {
        return ($this->pixelId && $this->trackBrowser);
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
     * Fires the Tracking
     * @param Wp_Sdtrk_Tracker_Event $event
     */
    public function fireTracking($event)
    {
        //Send the Data
        // Browser
        if ($this->trackingEnabled_Browser()) {
            $this->fireTracking_Browser($event);
        }

        // Server
        if ($this->trackingEnabled_Server()) {
            $this->fireTracking_Server($event);
        }
    }

    /**
     * Fires the Browser-based Tracking
     * @param Wp_Sdtrk_Tracker_Event $event
     */
    private function fireTracking_Browser($event)
    {
        
        // Collect the Base-Data
        $baseData = array(
            'pixelId' => $this->pixelId,
            'eventId' => $event->getEventId(),
            'eventName' => $this->readEventName($event)
        );
        
        //Collect the Custom-Data
        $customData = array();
        
        //Value
        if($event->getEventValue() >0){
            $customData['currency'] = "EUR";
            $customData['value'] = $event->getEventValue();
        }
        
        //Product
        if(!empty($event->getProductId())){
            $customData['content_ids'] = '["'.$event->getProductId().'"]';
            $customData['content_type'] = "product";
            $customData['content_name'] = $event->getProductName();
            $customData['contents'] = '[{"id":"'.$event->getProductId().'","quantity":'.strval(1).'}]';
        }
        
        //UTM
        foreach ($event->getUtmData() as $key => $value) {
            $customData[$key] = $value;
        }   
        
        wp_localize_script("wp_sdtrk-fb", 'wp_sdtrk_fb', array(
            'wp_sdtrk_fb_basedata' => $baseData,
            'wp_sdtrk_fb_customdata' => $customData
        ));
        wp_enqueue_script('wp_sdtrk-fb');
    }

    /**
     * Fires the Server-based Tracking
     * @param Wp_Sdtrk_Tracker_Event $event
     */
    private function fireTracking_Server($event)
    {        
        //---Prepare Request
        //Base-Data
        $requestData = array(
            "event_time" => date_create()->getTimestamp(),
            "event_id" => $event->getEventId(),
            "event_source_url" => Wp_Sdtrk_Helper::wp_sdtrk_getCurrentURL(),
            "action_source" => "website",
            "user_data" => array(
                "client_ip_address" => Wp_Sdtrk_Helper::wp_sdtrk_getClientIp(),
                "client_user_agent" => $_SERVER['HTTP_USER_AGENT'],
                "fbc" => $this->getFBClid(),
                "fbp" => Wp_Sdtrk_Helper::wp_sdtrk_getGetParamWithCookie('_fbp', false)
            )
        ); 
        
        //Collect the Custom-Data
        $customData = array();
        
        //UTM
        foreach ($event->getUtmData() as $key => $value) {
            $customData[$key] = $value;
        }  
        
        //Product
        if(!empty($event->getProductId())){
            $customData['content_ids'] = '["'.$event->getProductId().'"]';
            $customData['content_type'] = "product";
            $customData['content_name'] = $event->getProductName();
            $requestData['contents'] = array(array("id" =>$event->getProductId(),"quantity"=>1));
        }      
        
        //---Send Request
        //The PageView
        $requestData['event_name'] = "PageView";   
        $requestData['custom_data'] = $customData;        
        $this->payLoadServerRequest($requestData);
        
        //The Event
        if($this->readEventName($event) !== false && $this->readEventName($event) !== 'PageView'){
            if($event->getEventValue() >0){
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
     * @param array $requestData
     */
    private function payLoadServerRequest($requestData){
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
     * Get or generate the Facebook Click ID
     *
     * @return string|boolean|string
     */
    private function getFBClid()
    {
        $fbc = Wp_Sdtrk_Helper::wp_sdtrk_getGetParamWithCookie('_fbc', false);
        if (! empty($fbc)) {
            return $fbc;
        }
        $fbclid = Wp_Sdtrk_Helper::wp_sdtrk_getGetParamWithCookie("fbclid", false);
        if (! empty($fbclid)) {
            $version = 'fb';
            $subdomainIndex = '1';
            $creationTime = date_create()->getTimestamp();
            return $version . '.' . $subdomainIndex . '.' . $creationTime . '.' . $fbclid;
        }
        return "";
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
