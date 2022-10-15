<?php

class Wp_Sdtrk_Tracker_Ga
{

    private $measurementId;

    private $apiToken;

    private $debugMode;

    private $trackServer;

    public function __construct()
    {
        $this->measurementId = false;
        $this->apiToken = false;
        $this->debugMode = false;
        $this->debugModeLive = false;
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
    private function debugEnabled_Server()
    {
        return ($this->debugMode);
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
        $clientId = (isset($data['cid']) && ! empty($data['cid'])) ? $data['cid'] : false;
        return $this->$functionName($event, $data, $clientId);
    }

    /**
     * Fires the Page-Hit-Tracking
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     * @param Array $data
     * @return boolean
     */
    private function fireTracking_Server_Page($event, $data, $clientId)
    {
        $requestData = array(
            "client_id" => $clientId,
            "events" => array(
                array(
                    "name" => "page_view",
                    "params" => $this->getData_base($event)
                )
            )
        );
        $response = $this->payLoadServerRequest($requestData);
    }

    /**
     * Fires the Page-Hit-Event-Tracking
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     * @param Array $data
     * @return boolean
     */
    private function fireTracking_Server_Event($event, $data, $clientId)
    {
        if ($event->getEventName() !== false && $event->getEventName() !== 'page_view') {
            $customData = $this->getData_custom($event);
            $customData['transaction_id'] = $event->getEventId();
            // Add value if given
            if ($event->getEventValue() > 0 || $event->getEventName() == 'purchase') {
                $customData['value'] = $event->getEventValue();
                $customData['currency'] = "EUR";
            }
            // Add product if given
            if (! empty($event->getProductId())) {
                $customData['items'] = array(
                    $this->getData_products($event)
                );
            }
            // ---Send Request
            $requestData = array(
                "client_id" => $clientId,
                "events" => array(
                    array(
                        "name" => $event->getEventName(),
                        "params" => $customData
                    )
                )
            );
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
    private function fireTracking_Server_Scroll($event, $data, $clientId)
    {
        // Update the event
        $scrollEventId = $event->getEventId() . "-s" . $data['percent'];
        $scrollEventName = 'Scrolldepth-' . $data['percent'] . '-Percent';
        $event->setScrollTriggerData($scrollEventName, $scrollEventId);
        
        $customData = $this->getData_custom($event);
        $customData['transaction_id'] = $event->getScrollTriggerData()['id'];
        $requestData = array(
            "client_id" => $clientId,
            "events" => array(
                array(
                    "name" => $event->getScrollTriggerData()['name'],
                    "params" => $customData
                )
            )
        );
        $response = $this->payLoadServerRequest($requestData);
    }
    
    /**
     * Fires the Time-Hit-Tracking
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     * @param Array $data
     * @return boolean
     */
    private function fireTracking_Server_Time($event, $data, $clientId)
    {
        // Update the event
        $timeEventId = $event->getEventId() . "-t" . $data['time'];
        $timeEventName = 'Watchtime-' . $data['time'] . '-Seconds';
        $event->setTimeTriggerData($timeEventName, $timeEventId);
        
        $customData = $this->getData_custom($event);
        $customData['transaction_id'] = $event->getTimeTriggerData()['id'];
        $requestData = array(
            "client_id" => $clientId,
            "events" => array(
                array(
                    "name" => $event->getTimeTriggerData()['name'],
                    "params" => $customData
                )
            )
        );
        $response = $this->payLoadServerRequest($requestData);
    }
    
    /**
     * Fires the Click-Hit-Tracking
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     * @param Array $data
     * @return boolean
     */
    private function fireTracking_Server_Click($event, $data, $clientId)
    {
        // Update the event
        $clickEventId = $event->getEventId() . "-b" . $data['tag'];
        $event->setClickTriggerData('ButtonClick', $clickEventId, $data['tag']);        
        
        $customData = $this->getData_custom($event);
        $customData['transaction_id'] = $event->getClickTriggerData()['id'];
        $customData['buttonTag'] = $event->getClickTriggerData()['tag'];
        
        $requestData = array(
            "client_id" => $clientId,
            "events" => array(
                array(
                    "name" => $event->getClickTriggerData()['name'],
                    "params" => $customData
                )
            )
        );
        $response = $this->payLoadServerRequest($requestData);
    }
    
    /**
     * Fires the Scroll-Hit-Tracking
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     * @param Array $data
     * @return boolean
     */
    private function fireTracking_Server_Visibility($event, $data, $clientId)
    {
        // Update the event
        $visitEventId = $event->getEventId() . "-v" . $data['tag'];
        $event->setVisibilityTriggerData('ItemVisit', $visitEventId, $data['tag']);
        
        $customData = $this->getData_custom($event);
        $customData['transaction_id'] = $event->getVisibilityTriggerData()['id'];
        $customData['itemTag'] = $event->getVisibilityTriggerData()['tag'];
        
        $requestData = array(
            "client_id" => $clientId,
            "events" => array(
                array(
                    "name" => $event->getVisibilityTriggerData()['name'],
                    "params" => $customData
                )
            )
        );
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
        // The basic data for each event
        $baseData = array(
            "page_referrer" => $event->getEventReferer(),
            "page_location" => $event->getEventUrl(),
            "page_path" => $event->getEventPath(),
            "page_title" => $event->getPageName(),
            "event_time" => $event->getTime()
        );
        // Set debug switch
        if ($this->debugMode) {
            $baseData['debug_mode'] = true;
        }
        // UTM
        foreach ($event->getUtmData() as $key => $value) {
            $baseData[$key] = $value;
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
        $baseData = $this->getData_base($event);
        $baseData['non_interaction'] = true;
        $baseData['post_type'] = "product";
        $baseData['post_id'] = $event->getPageId();
        $baseData['plugin'] = "Wp-Sdtrk";
        $baseData['event_url'] = $event->getEventSource();
        $baseData['user_role'] = "guest";
        $baseData['event_day'] = $event->getEventTimeDay();
        $baseData['event_month'] = $event->getEventTimeMonth();
        $baseData['landing_page'] = $event->getEventDomain();
        return $baseData;
    }

    /**
     * Return the product data of event
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     * @return array
     */
    private function getData_products($event)
    {
        $productData = array();
        $productData['id'] = $event->getProductId();
        $productData['quantity'] = 1;
        $productData['name'] = $event->getProductName();
        $productData['price'] = $event->getEventValue();
        $productData['brand'] = $event->getBrandName();
        return $productData;
    }

    /**
     * Payloads the Data and sends it to the Server
     *
     * @param array $requestData
     */
    private function payLoadServerRequest($requestData)
    {
        $payload = json_encode($requestData);
        $res = Wp_Sdtrk_Helper::wp_sdtrk_httpPost($this->getApiUrl(), $payload, array(), $this->debugMode);
        // Send Request
        return $res;
    }
}
