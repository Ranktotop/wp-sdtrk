<?php

class Wp_Sdtrk_Tracker_Tt
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
        $tt_pixelId = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "tt_pixelid");
        $this->pixelId = ($tt_pixelId && ! empty(trim($tt_pixelId))) ? $tt_pixelId : false;

        // Srv Token
        $tt_srvToken = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "tt_trk_server_token");
        $this->apiToken = ($tt_srvToken && ! empty(trim($tt_srvToken))) ? $tt_srvToken : false;

        // Test-Code
        $tt_testCode = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "tt_trk_server_debug_code");
        $this->debugCode = ($tt_testCode && ! empty(trim($tt_testCode))) ? $tt_testCode : false;

        // Track Server
        $this->trackServer = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "tt_trk_server"), "yes") == 0) ? true : false;

        // Debug Mode
        $this->debugMode = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "tt_trk_server_debug"), "yes") == 0) ? true : false;
    }

    /**
     * Returns the API Url to the Conversion API
     *
     * @return string
     */
    private function getApiUrl()
    {
        if ($this->pixelId && $this->apiToken) {
            return 'https://business-api.tiktok.com/open_api/v1.2/pixel/track/';
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
        $requestData = $this->getData_base($event, $data);
        $requestData['event'] = "PageView";
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
        if ($this->readEventName($event) !== false && $this->readEventName($event) !== 'ViewContent') {
            $requestData = $this->getData_base($event, $data);
            $requestData['event'] = $this->readEventName($event);
            $requestData["properties"]["description"] = $this->readEventName($event);

            // Add value if given
            if ($event->getEventValue() > 0 || $this->readEventName($event) === 'PlaceAnOrder') {
                $requestData["properties"]["currency"] = "EUR";
                $requestData["properties"]["value"] = $event->getEventValue();
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
        $scrollEventId = $event->getEventId() . "-s" . $data['percent'].'_'.$data['hash'];
        $scrollEventName = 'Scrolldepth-' . $data['percent'] . '-Percent';
        $event->setScrollTriggerData($scrollEventName, $scrollEventId);    
        
        $requestData = $this->getData_base($event, $data);
        $requestData['event'] = $event->getScrollTriggerData()['name'];
        $requestData['event_id'] = $event->getScrollTriggerData()['id'];        
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
        $timeEventId = $event->getEventId() . "-t" . $data['time'].'_'.$data['hash'];
        $timeEventName = 'Watchtime-' . $data['time'] . '-Seconds';
        $event->setTimeTriggerData($timeEventName, $timeEventId);
        
        $requestData = $this->getData_base($event, $data);
        $requestData['event'] = $event->getTimeTriggerData()['name'];
        $requestData['event_id'] = $event->getTimeTriggerData()['id'];
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
        $clickEventId = $event->getEventId() . "-b" . $data['tag'].'_'.$data['hash'];
        $event->setClickTriggerData('ButtonClick', $clickEventId, $data['tag']);
        
        $requestData = $this->getData_base($event, $data);
        $requestData['event'] = $event->getClickTriggerData()['name'];
        $requestData['event_id'] = $event->getClickTriggerData()['id'];
        $requestData["properties"]["description"] = $event->getClickTriggerData()['name']."/".$data['tag'];
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
        $visitEventId = $event->getEventId() . "-v" . $data['tag'].'_'.$data['hash'];
        $event->setVisibilityTriggerData('ItemVisit', $visitEventId, $data['tag']);
        
        $requestData = $this->getData_base($event, $data);
        $requestData['event'] = $event->getVisibilityTriggerData()['name'];
        $requestData['event_id'] = $event->getVisibilityTriggerData()['id'];
        $requestData["properties"]["description"] = $event->getVisibilityTriggerData()['name']."/".$data['tag'];
        $response = $this->payLoadServerRequest($requestData);
    }    

    /**
     * Return the base data of event
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     * @return array
     */
    private function getData_base($event, $data)
    {
        // Base-Data
        $baseData = array(
            "pixel_code" => $this->pixelId,
            "event_id" => $event->getEventId() . "_" . $data['hash'],
            "timestamp" => date('c', $event->getTime()),
            // "timestamp" => strval(intval($event->getTime())*1000),
            "context" => $this->getData_context($event, $data),
            "properties" => array(
                "contents" => $this->getData_contents($event)
                // "description" => "ViewContent", // will be replaced later on events
                // "query" => "" // you can pass a keyword from on-page-search here
            )
        );
        return $baseData;
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
        // User-Data
        $userData = array();
        if ($event->getUserEmail()) {
            $userData["email"] = hash('sha256', $event->getUserEmail());
        }
        if (isset($data['ttc'])) {
            $userData["external_id"] = $data['ttc'];
        }
        return $userData;
    }

    /**
     * Return the context data of event
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     * @return array
     */
    private function getData_context($event, $data)
    {
        $contextData = array(
            "page" => array(
                "url" => $event->getEventSource(),
                "referrer" => $event->getEventReferer()
            ),
            "ip" => $event->getEventIp(),
            "user_agent" => $event->getEventAgent()
        );

        // Add ttc if exists
        if (isset($data['ttc'])) {
            $contextData['ad'] = array(
                "callback" => $data['ttc']
            );
        }
        // Add user-data if exists
        if (! empty($this->getData_user($event, $data))) {
            $contextData["user"] = $this->getData_user($event, $data);
        }
        return $contextData;
    }

    /**
     * Return the content data of event
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
        
        if ($event->getEventValue() > 0 || $this->readEventName($event) === 'PlaceAnOrder') {
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
        if ($this->debugEnabled_Server()) {
            $requestData["test_event_code"] = $this->debugCode;
        }

        // Create the Payload
        $fields = $requestData;
        // Wp_Sdtrk_Helper::wp_sdtrk_vardump_log($fields);
        $payload = json_encode($fields);
        // Wp_Sdtrk_Helper::wp_sdtrk_vardump_log($payload);
        $headers = array();
        array_push($headers, "Access-Token:" . $this->apiToken);

        // Send Request
        return Wp_Sdtrk_Helper::wp_sdtrk_httpPost($this->getApiUrl(), $payload, $headers, $this->debugMode);
    }

    /**
     * Converts the Raw-Eventname to Tik Tok-Event-Name
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     */
    private function readEventName($event)
    {
        $rawEvent = $event->getEventName();
        switch ($rawEvent) {
            case 'page_view':
                return 'ViewContent';
            case 'add_to_cart':
                return 'AddToCart';
            case 'purchase':
                return 'PlaceAnOrder';
            case 'sign_up':
                return 'CompleteRegistration';
            case 'generate_lead':
                return 'SubmitForm';
            case 'begin_checkout':
                return 'InitiateCheckout';
            case 'view_item':
                return 'ViewContent';
            default:
                return $rawEvent;
        }
    }

    /**
     * This example is 1:1 from Tik Tok Documentation
     */
    private function debugEvent()
    {
        $curl = curl_init();

        $fields = array(
            "pixel_code" => $this->pixelId,
            "event" => "InitiateCheckout",
            "event_id" => "1616318632825_357",
            "timestamp" => "2020-09-17T19:49:27Z",
            "context" => array(
                "ad" => array(
                    "callback" => "123ATXSfe"
                ),
                "page" => array(
                    "url" => "http://demo.mywebsite.com/purchase",
                    "referrer" => "http://demo.mywebsite.com"
                ),
                "user" => array(
                    "external_id" => "f0e388f53921a51f0bb0fc8a2944109ec188b59172935d8f23020b1614cc44bc",
                    "phone_number" => "2f9d2b4df907e5c9a7b3434351b55700167b998a83dc479b825096486ffcf4ea",
                    "email" => "dd6ff77f54e2106661089bae4d40cdb600979bf7edc9eb65c0942ba55c7c2d7f"
                ),
                "user_agent" => "Mozilla/5.0 (iPhone; CPU iPhone OS 14_7_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 musical_ly_21.1.0 JsSdk/2.0 NetType/WIFI Channel/App Store ByteLocale/en Region/US RevealType/Dialog isDarkMode/0 WKWebView/1",
                "ip" => "13.57.97.131"
            ),
            "properties" => array(
                "contents" => [
                    array(
                        "price" => 8,
                        "quantity" => 2,
                        "content_type" => "product",
                        "content_id" => "1077218"
                    ),
                    array(
                        "price" => 30,
                        "quantity" => 1,
                        "content_type" => "product",
                        "content_id" => "1197218"
                    )
                ],
                "currency" => "USD",
                "value" => 46.00
            ),
            "test_event_code" => $this->debugCode
        );
        Wp_Sdtrk_Helper::wp_sdtrk_vardump_log($fields);
        Wp_Sdtrk_Helper::wp_sdtrk_vardump_log(json_encode($fields));

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://business-api.tiktok.com/open_api/v1.2/pixel/track/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($fields),
            // CURLOPT_POSTFIELDS => '{"pixel_code":"'.$this->pixelId.'","event":"InitiateCheckout","event_id":"1616318632825_357","timestamp":"1645131187000","context":{"ad":{"callback":"123ATXSfe"},"page":{"url":"http://demo.mywebsite.com/purchase","referrer":"http://demo.mywebsite.com"},"user":{"external_id":"f0e388f53921a51f0bb0fc8a2944109ec188b59172935d8f23020b1614cc44bc","phone_number":"2f9d2b4df907e5c9a7b3434351b55700167b998a83dc479b825096486ffcf4ea","email":"dd6ff77f54e2106661089bae4d40cdb600979bf7edc9eb65c0942ba55c7c2d7f"},"user_agent":"Mozilla/5.0 (platform; rv:geckoversion) Gecko/geckotrail Firefox/firefoxversion","ip":"13.57.97.131"},"properties":{"contents":[{"price":8,"quantity":2,"content_type":"product","content_id":"1077218"},{"price":30,"quantity":1,"content_type":"product","content_id":"1197218"}],"currency":"USD","value":46},"test_event_code":"'.$this->debugCode.'"}',
            CURLOPT_HTTPHEADER => array(
                'Access-Token: ' . $this->apiToken,
                'Content-Type: application/json'
            )
        ));

        $response = curl_exec($curl);
        Wp_Sdtrk_Helper::wp_sdtrk_vardump_log($response);

        curl_close($curl);
        return;
    }
}
