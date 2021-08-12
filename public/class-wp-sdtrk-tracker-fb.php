<?php

class Wp_Sdtrk_Tracker_Fb
{
    
    private $pixelId;
    private $apiToken;
    private $testCode;

    public function __construct()
    {
        $this->pixelId = false;
        $this->apiToken = false;
        $this->testCode = false;
        $this->init();
    }
    
    /**
     * Initialize the saved Data
     */
    private function init(){
        //Pixel ID
        $fb_pixelId = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "fb_pixelid");
        $this->pixelId = ($fb_pixelId && ! empty(trim($fb_pixelId))) ? $fb_pixelId : false;
        
        //Srv Token
        $fb_srvToken = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "fb_trk_server_token");
        $this->apiToken = ($fb_srvToken && ! empty(trim($fb_srvToken))) ? $fb_srvToken : false;
        
        //Test-Code
        $fb_testCode = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "fb_trk_server_debug_code");
        $this->testCode = ($fb_testCode && ! empty(trim($fb_testCode))) ? $fb_testCode : false;
    }
    
    /**
     * Generate an unique identifier
     * @return string
     */
    private function generateEventId(){
        return substr(str_shuffle(MD5(microtime())), 0, 10);
    }
        
    /**
     * Returns the API Url to the Conversion API
     * @return string
     */
    private function getApiUrl(){
        if($this->pixelId && $this->apiToken){
            return 'https://graph.facebook.com/v11.0/' . $this->pixelId . '/events?access_token=' . $this->apiToken;
        }
        return false;       
    }
    
    /**
     * Checks if Browser-Tracking is enabled
     * @return boolean
     */
    private function trackingEnabled_Browser(){
        $setting = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "fb_trk_browser"), "yes") == 0) ? true : false;
        return ($setting && $this->pixelId !== false);
    }
    
    /**
     * Checks if Server-Tracking is enabled
     * @return boolean
     */
    private function trackingEnabled_Server(){
        $setting = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "fb_trk_server"), "yes") == 0) ? true : false;
        return ($setting && $this->apiToken !== false && $this->getApiUrl() !==false);
    }
    
    /**
     * Checks if Debug is enabled
     * @return boolean
     */
    private function debugEnabled_Server(){
        return (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "fb_trk_server_debug"), "yes") == 0) ? true : false;
    }
    
    /**
     * Fires the Tracking
     */
    public function fireTracking(){
        //Collect the Data
        $data = array(
            "baseData" => array(
                'pixelId' => $this->pixelId,
                'eventId' => $this->generateEventId()
            ),
            "customData" => array(
                "currency" => "EUR",
                "value" => 0,
                "content_ids" => "[12345]",
                "content_type" => "product",
                "content_name" => "custom",
                "utm_source" => "facebook"
            )
        );        
        
        //Browser
        if ($this->trackingEnabled_Browser()) {
            $this->fireTracking_Browser($data);
        }
        
        //Server
        if ($this->trackingEnabled_Server()) {
            $this->fireTracking_Server($data);
        }
    }
    
    /**
     * Fires the Browser-based Tracking
     */
    private function fireTracking_Browser($data){
        wp_localize_script("wp_sdtrk-fb", 'wp_sdtrk_fb', array(
            'wp_sdtrk_fb_data' => $data
        ));
        wp_enqueue_script('wp_sdtrk-fb');
    }
    
    /**
     * Fires the Server-based Tracking
     */
    private function fireTracking_Server($data){
        $requestData = array(
            0 => array(
                "event_name" => "PageView",
                "event_time" => date_create()->getTimestamp(),
                "event_id" => $fbEventId,
                "event_source_url" => Wp_Sdtrk_Helper::wp_sdtrk_getCurrentURL(),
                "action_source"=> "website",
                "user_data" => array(
                    "client_ip_address" => Wp_Sdtrk_Helper::wp_sdtrk_getClientIp(),
                    "client_user_agent" => $_SERVER['HTTP_USER_AGENT'],
                    "fbc" => "",
                    "fbp" => $_COOKIE['_fbp'] ?? ''
                ),
                "contents" => array(
                    array(
                        "id" => "product123",
                        "quantity" => 1
                    )
                ),
                "custom_data" => $data['customData'],
                
            )
        );
        
        //Create the Payload
        $fields = array(
            "data" => $requestData
        );        
        if($this->debugEnabled_Server()){
            $fields["test_event_code"] = $this->testCode;
        }        
        $payload = json_encode($fields);
        
        //Send Request
        Wp_Sdtrk_Helper::wp_sdtrk_httpPost($this->getApiUrl(), $payload);
    }
    
    
    
    
}
