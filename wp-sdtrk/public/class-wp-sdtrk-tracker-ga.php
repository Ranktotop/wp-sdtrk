<?php

class Wp_Sdtrk_Tracker_Ga
{

    private $measurementId;

    private $debugMode;

    private $apiToken;

    public function __construct()
    {
        $this->measurementId = false;
        $this->debugMode = false;
        $this->debugModeLive = false;
        $this->apiToken = false;
        $this->trackServer = false;
        $this->init();
    }

    /**
     * Initialize the saved Data
     */
    private function init()
    {
        // ID
        $messId = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "ga_measurement_id");
        $this->measurementId = ($messId && ! empty(trim($messId))) ? $messId : false;

        // Srv Token
        $ga_srvToken = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "ga_trk_server_token");
        $this->apiToken = ($ga_srvToken && ! empty(trim($ga_srvToken))) ? $ga_srvToken : false;

        // Track Server
        $this->trackServer = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "ga_trk_server"), "yes") == 0) ? true : false;

        // Debug Mode
        $debug = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "ga_trk_debug"), "yes") == 0) ? true : false;
        $this->debugMode = ($debug && ! empty(trim($debug))) ? $debug : false;
        
        // Debug Mode Live
        $debugLive = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "ga_trk_debug_live"), "yes") == 0) ? true : false;
        $this->debugModeLive = ($debugLive && ! empty(trim($debugLive))) ? $debugLive : false;        
    }

    /**
     * Returns the API Url to the Measurement Protocol
     *
     * @return string
     */
    private function getApiUrl()
    {
        if ($this->measurementId && $this->apiToken) {
            $baseUrl = ($this->debugMode && ! $this->debugModeLive) ? "https://www.google-analytics.com/debug/mp/collect" : "https://www.google-analytics.com/mp/collect";
            return $baseUrl . '?api_secret=' . $this->apiToken . '&measurement_id=' . $this->measurementId;
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
        return ($this->measurementId && $this->trackServer && $this->apiToken && $this->getApiUrl());
    }

    /**
     * Checks if Debug is enabled
     *
     * @return boolean
     */
    private function debugEnabled()
    {
        return ($this->debugMode);
    }

    /**
     * Fires the Server-based Tracking
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     */
    public function fireTracking_Server($event, $cid = "")
    {
        // Abort if tracking is disabled
        if (! $this->trackingEnabled_Server()) {
            return 'Tracking disabled for server';
        }

        $eventsToFire = array();

        // is TimeTracker-Event
        $isTimeTrigger = ($event->getTimeTriggerData() !== false) ? true : false;
        $isScrollTrigger = ($event->getScrollTriggerData() !== false) ? true : false;
        $isClickTrigger = ($event->getClickTriggerData() !== false) ? true : false;

        // The basic data for each event
        $baseParams = array(
            "page_referrer" => $event->getEventReferer(),
            "page_location" => $event->getEventUrl(),
            "page_path" => $event->getEventPath(),
            "page_title" => $event->getPageName(),
            "event_time" => $event->getTime()
        );
        // Set debug switch
        if ($this->debugMode) {
            $baseParams['debug_mode'] = true;
        }
        // UTM
        foreach ($event->getUtmData() as $key => $value) {
            $baseParams[$key] = $value;
        }

        // /////////////////
        // /The Page-View///
        // /////////////////
        $eventsData = array(
            "name" => "page_view",
            "params" => $baseParams
        );        

        // ////////////////
        // /The Event//////
        // ////////////////        
        $baseParams['non_interaction'] = true;
        $baseParams['post_type'] = "product";
        $baseParams['post_id'] = $event->getPageId();
        $baseParams['plugin'] = "Wp-Sdtrk";
        $baseParams['event_url'] = $event->getEventSource();
        $baseParams['user_role'] = "guest";
        $baseParams['event_day'] = $event->getEventTimeDay();
        $baseParams['event_month'] = $event->getEventTimeMonth();
        $baseParams['landing_page'] = $event->getEventDomain();

        // Check for time-trigger
        if ($isTimeTrigger) {
            $triggerParams = $baseParams;
            $timeTriggerData = $event->getTimeTriggerData();
            $timeTriggerEventName = $timeTriggerData['name'];
            $timeTriggerEventId = $timeTriggerData['id'];
            
            $triggerParams['transaction_id'] = $timeTriggerEventId;
            
            $eventsData = array(
                'name' => $timeTriggerEventName,
                'params' => $triggerParams
            );
        }
        
        // Check for scroll-trigger
        if ($isScrollTrigger) {
            $triggerParams = $baseParams;
            $scrollTriggerData = $event->getScrollTriggerData();
            $scrollTriggerEventName = $scrollTriggerData['name'];
            $scrollTriggerEventId = $scrollTriggerData['id'];
            
            $triggerParams['transaction_id'] = $scrollTriggerEventId;
            
            $eventsData = array(
                'name' => $scrollTriggerEventName,
                'params' => $triggerParams
            );
        }
        
        // Check for click-trigger
        if ($isClickTrigger) {
            $triggerParams = $baseParams;
            $clickTriggerData = $event->getClickTriggerData();
            $clickTriggerEventName = $clickTriggerData['name'];
            $clickTriggerEventId = $clickTriggerData['id'];
            $clickTriggerEventTag = $clickTriggerData['tag'];
            
            $triggerParams['transaction_id'] = $clickTriggerEventId;
            $triggerParams['buttonTag'] = $clickTriggerEventTag;
            
            $eventsData = array(
                'name' => $clickTriggerEventName,
                'params' => $triggerParams
            );
        }        
        array_push($eventsToFire, $eventsData);
        
        // ////////////////////////
        // /Conversion-Events//////
        // ////////////////////////
        if ($event->getEventName() !== false && $event->getEventName() !== 'page_view' && $isTimeTrigger === false && $isScrollTrigger === false) {
            $baseParams['transaction_id'] = $event->getEventId();
            
            // Value
            if ($event->getEventValue() > 0 || $event->getEventName() == 'purchase') {
                $baseParams['value'] = $event->getEventValue();
                $baseParams['currency'] = "EUR";
            }

            // Product
            if (! empty($event->getProductId())) {
                $productData = array();
                $productData['id'] = $event->getProductId();
                $productData['quantity'] = 1;
                $productData['name'] = $event->getProductName();
                $productData['price'] = $event->getEventValue();
                $productData['brand'] = $event->getBrandName();
                $baseParams['items'] = array(
                    $productData
                );
            }

            $eventsData = array(
                'name' => $event->getEventName(),
                'params' => $baseParams
            );
            array_push($eventsToFire, $eventsData);
        }

        // ---Send Request
        $requestData = array(
            "client_id" => $cid,
            "events" => $eventsToFire
        );

        $responses = array();
        array_push($responses, $this->payLoadServerRequest($requestData));

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
        $payload = json_encode($requestData);
        $res = Wp_Sdtrk_Helper::wp_sdtrk_httpPost($this->getApiUrl(), $payload);

        Wp_Sdtrk_Helper::wp_sdtrk_vardump_log($res);
        // Send Request
        return $res;
    }
}
