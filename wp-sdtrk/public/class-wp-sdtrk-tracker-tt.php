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
     */
    public function fireTracking_Server($event, $externalId = "", $ttc = "")
    {
        // Abort if tracking is disabled
        if (! $this->trackingEnabled_Server()) {
            return 'Tracking disabled for server';
        }

        // is TimeTracker-Event
        $isTimeTrigger = ($event->getTimeTriggerData() !== false) ? true : false;
        $isScrollTrigger = ($event->getScrollTriggerData() !== false) ? true : false;
        $isClickTrigger = ($event->getClickTriggerData() !== false) ? true : false;

        // ---Prepare Request
        // Base-Data
        $requestData = array(
            "pixel_code" => $this->pixelId,
            "event_id" => $event->getEventId() . "_" . $externalId,
            "timestamp" => str_replace('+00:00', 'Z', date('c', $event->getTime())),
            //"timestamp" => strval(intval($event->getTime())*1000),
            "context" => array(
                "ad" => array(
                    "callback" => $ttc // send only if ttc is set
                ),
                "page" => array(
                    "url" => $event->getEventSource(),
                    "referrer" => ""
                ),
                "user" => array(
                    "external_id" => $externalId,
                    "phone_number" => "",
                    "email" => ""
                ),
                "ip" => '"'.$event->getEventIp().'"',
                "user_agent" => '"'.$event->getEventAgent().'"'
            ),
            "properties" => array(
                "contents" => [],
                //"description" => "ViewContent", // will be replaced later on events
                //"query" => "" // you can pass a keyword from on-page-search here
            )
        );

        // Product
        if (! empty($event->getProductId())) {
            array_push($requestData["properties"]["contents"],array(
                'content_id' => $event->getProductId(),
                'content_name' => $event->getProductName(),
                'content_type' =>"product",
                'quantity' => 1
                )
            );
        }

        // ---Send Request
        // The PageView
        $requestData['event'] = "ViewContent";

        // Check for time-trigger
        if ($isTimeTrigger) {
            $timeTriggerData = $event->getTimeTriggerData();
            $timeTriggerEventName = $timeTriggerData['name'];
            $timeTriggerEventId = $timeTriggerData['id'];
            $requestData['event'] = $timeTriggerEventName;
            $requestData['event_id'] = $timeTriggerEventId;
            //$requestData["properties"]["description"] = $timeTriggerEventName;
        }

        // Check for scroll-trigger
        if ($isScrollTrigger) {
            $scrollTriggerData = $event->getScrollTriggerData();
            $scrollTriggerEventName = $scrollTriggerData['name'];
            $scrollTriggerEventId = $scrollTriggerData['id'];
            $requestData['event'] = $scrollTriggerEventName;
            $requestData['event_id'] = $scrollTriggerEventId . "_" . $externalId;
            //$requestData["properties"]["description"] = $scrollTriggerEventName;
        }

        // Check for click-trigger
        if ($isClickTrigger) {
            $clickTriggerData = $event->getClickTriggerData();
            $clickTriggerEventName = $clickTriggerData['name'];
            $clickTriggerEventId = $clickTriggerData['id'];
            //$clickTriggerEventTag = $clickTriggerData['tag'];
            $requestData['event'] = $clickTriggerEventName;
            $requestData['event_id'] = $clickTriggerEventId . "_" . $externalId;
            //$requestData["properties"]["description"] = $clickTriggerEventName."/".$clickTriggerEventTag;
        }

        $responses = array();
        array_push($responses, $this->payLoadServerRequest($requestData));

        // The Event
        if ($this->readEventName($event) !== false && $this->readEventName($event) !== 'ViewContent' && $isTimeTrigger === false && $isScrollTrigger === false) {

            if ($event->getEventValue() > 0 || $this->readEventName($event) === 'PlaceAnOrder') {
                $requestData["properties"]["currency"] = "EUR";
                $requestData["properties"]["value"] = $event->getEventValue();
                // $requestData["properties"]["contents"][0]["price"] = $event->getEventValue();
            }
            $requestData['event'] = $this->readEventName($event);
            $requestData["properties"]["description"] = $this->readEventName($event);
            array_push($responses, $this->payLoadServerRequest($requestData));
        }

        // Return
        return $responses;
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
        //Wp_Sdtrk_Helper::wp_sdtrk_vardump_log($fields);
        $payload = json_encode($fields);
        Wp_Sdtrk_Helper::wp_sdtrk_vardump_log($payload);
        $headers = array();
        array_push($headers, "Access-Token:" . $this->apiToken);

        // Send Request
        return Wp_Sdtrk_Helper::wp_sdtrk_httpPost($this->getApiUrl(), $payload, $headers);
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
                "user_agent" => "Mozilla/5.0 (platform; rv:geckoversion) Gecko/geckotrail Firefox/firefoxversion",
                "ip" => "13.57.97.131"
            ),
            "properties" => array(
                "contents" => [
                    array(
                        "price" => 8,
                        "quantity" => 2,
                        "content_type" => "socks",
                        "content_id" => "1077218"
                    ),
                    array(
                        "price" => 30,
                        "quantity" => 1,
                        "content_type" => "dress",
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
            //CURLOPT_POSTFIELDS => '{"pixel_code":"C872O6BSVD34VNSUBREG","event":"InitiateCheckout","event_id":"1616318632825_357","timestamp":"1645131187000","context":{"ad":{"callback":"123ATXSfe"},"page":{"url":"http://demo.mywebsite.com/purchase","referrer":"http://demo.mywebsite.com"},"user":{"external_id":"f0e388f53921a51f0bb0fc8a2944109ec188b59172935d8f23020b1614cc44bc","phone_number":"2f9d2b4df907e5c9a7b3434351b55700167b998a83dc479b825096486ffcf4ea","email":"dd6ff77f54e2106661089bae4d40cdb600979bf7edc9eb65c0942ba55c7c2d7f"},"user_agent":"Mozilla/5.0 (platform; rv:geckoversion) Gecko/geckotrail Firefox/firefoxversion","ip":"13.57.97.131"},"properties":{"contents":[{"price":8,"quantity":2,"content_type":"socks","content_id":"1077218"},{"price":30,"quantity":1,"content_type":"dress","content_id":"1197218"}],"currency":"USD","value":46},"test_event_code":"TEST01381"}',
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
