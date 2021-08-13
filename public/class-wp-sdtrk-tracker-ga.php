<?php

class Wp_Sdtrk_Tracker_Ga
{

    private $measurementId;

    private $trackBrowser;

    private $debugMode;

    public function __construct()
    {
        $this->measurementId = false;
        $this->trackBrowser = false;
        $this->debugMode = false;
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

        // Track Browser
        $trkBrowser = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "ga_trk_browser"), "yes") == 0) ? true : false;
        $this->trackBrowser = ($trkBrowser && ! empty(trim($trkBrowser))) ? $trkBrowser : false;

        // Debug Mode
        $debug = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "ga_trk_debug"), "yes") == 0) ? true : false;
        $this->debugMode = ($debug && ! empty(trim($debug))) ? $debug : false;
    }

    /**
     * Checks if Browser-Tracking is enabled
     *
     * @return boolean
     */
    private function trackingEnabled_Browser()
    {
        return ($this->measurementId && $this->trackBrowser);
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
     * Fires the Tracking
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     */
    public function fireTracking($event)
    {
        // Send the Data
        // Browser
        if ($this->trackingEnabled_Browser()) {
            $this->fireTracking_Browser($event);
        }
    }

    /**
     * Fires the Browser-based Tracking
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     */
    private function fireTracking_Browser($event)
    {        
        $eventData = array();
        $initData = array('debug_mode' => $this->debugMode);
        
        //The Base Data
        $eventData['transaction_id'] = $event->getEventId();        
        if($event->getEventValue() >0){
            $eventData['value'] = $event->getEventValue();
            $eventData['currency'] = "EUR";
        }        
        foreach($event->getUtmData() as $utmData => $value){
            $dataName = str_replace("utm_", "", $utmData);
            $dataVal = $value;
            $eventData[$dataName] = $dataVal;
            $initData[$dataName] = $dataVal;
        }
        //For Testing
        $initData['source'] = "software";
        $initData['campaign'] = "progCampaign";
        $initData['medium'] = "progMedium";
        $initData['term'] = "progTerm";
        $initData['content'] = "progContent";
        
        //The Event Data
        if(!empty($event->getProductId())){
            $eventData['items'] = array(0=>array(
                'item_name' => $event->getProductName(),
                'item_id' => $event->getProductId(),
                'price' => $event->getEventValue(),
                'item_brand' => $event->getBrandName(),
                'quantity' => 1,
            ));
        }        

        wp_localize_script("wp_sdtrk-ga", 'wp_sdtrk_ga', array(
            'wp_sdtrk_ga_id' => $this->measurementId,
            'wp_sdtrk_ga_eventName' => $event->getEventName(),
            'wp_sdtrk_ga_eventData' => $eventData,
            'wp_sdtrk_ga_initData' => $initData
        ));
        wp_enqueue_script('wp_sdtrk-ga');
    }
}
