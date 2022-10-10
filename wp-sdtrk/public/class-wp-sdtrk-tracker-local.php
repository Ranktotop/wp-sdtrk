<?php

class Wp_Sdtrk_Tracker_Local
{
    private $debugMode;

    private $trackServer;

    public function __construct()
    {
        $this->debugMode = false;
        $this->trackServer = false;
        $this->init();
    }

    /**
     * Initialize the saved Data
     */
    private function init()
    {
        // Track Server
        $this->trackServer = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "local_trk_server"), "yes") == 0) ? true : false;
        
        // Debug Mode
        $this->debugMode = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "local_trk_server_debug"), "yes") == 0) ? true : false;
    }

    /**
     * Checks if Server-Tracking is enabled
     *
     * @return boolean
     */
    private function trackingEnabled_Server()
    {
        return $this->trackServer;
    }

    /**
     * Checks if Debug is enabled
     *
     * @return boolean
     */
    private function debugEnabled_Server()
    {
        return ($this->trackingEnabled_Server() && $this->debugMode);
    }

    /**
     * Fires the Server-based Tracking
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     */
    public function fireTracking_Server($event,$meta)
    {
        // Abort if tracking is disabled
        if (! $this->trackingEnabled_Server()) {
            return 'Tracking disabled for server';
        }
        //Get the type of event
        $subType = (isset($meta['subtype'])) ? $meta['subtype'] : false;    
        //Get Event-Name
        switch($subType){
            case 'init':
                $eventName = (!empty($event->getEventName())) ? $event->getEventName() : 'page_view';                
                break;
            case 'tt':
                $eventName = (isset($meta['timeEventName'])) ? $meta['timeEventName'] : false;
                $event->setTimeTriggerData($eventName, "0");
                break;
            case 'sd':
                $eventName = (isset($meta['scrollEventName'])) ? $meta['scrollEventName'] : false;
                $event->setScrollTriggerData($eventName, "0");
                break;
            case 'bc':
                $btnTag = (isset($meta['clickEventTag'])) ? $meta['clickEventTag'] : false;
                $eventName = (isset($meta['clickEventName'])) ? $meta['clickEventName'] : false;
                $eventName = ($btnTag!==false) ? $eventName.'_'.$btnTag : $eventName;
                $event->setClickTriggerData($eventName, strval(rand(10000,99999)), $btnTag);
                break;                
        }        
        
        //Print some debug info
        Wp_Sdtrk_Helper::wp_sdtrk_vardump_log("-----Start Local Event-Data-----:",$this->debugMode);
        Wp_Sdtrk_Helper::wp_sdtrk_vardump_log("Event-Name: ".$eventName,$this->debugMode);
        Wp_Sdtrk_Helper::wp_sdtrk_vardump_log("Source-Event:",$this->debugMode);
        Wp_Sdtrk_Helper::wp_sdtrk_vardump_log($event,$this->debugMode);
        Wp_Sdtrk_Helper::wp_sdtrk_vardump_log("Converted-Event:",$this->debugMode);
        Wp_Sdtrk_Helper::wp_sdtrk_vardump_log($event->getLocalizedEventData(),$this->debugMode);
        Wp_Sdtrk_Helper::wp_sdtrk_vardump_log("-----End Local Event-Data-----:",$this->debugMode);
        
        $dbHelper = new Wp_Sdtrk_DbHelper();
        return $dbHelper->saveHit($eventName, $event->getEventTime(), $event->getLocalizedEventData());
    }
}
