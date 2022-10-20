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
     * @param Wp_Sdtrk_Tracker_Event $event
     * @param String $handler
     * @param Array $data
     * @return boolean
     */
    public function fireTracking_Server($event,$handler,$data){
        // Abort if tracking is disabled
        if (! $this->trackingEnabled_Server()) {
            return true;
        }        
        // Check if given handler exists
        $functionName = 'fireTracking_Server_'.$handler;
        if (! method_exists($this, $functionName)) {
            return false;
        }
        return $this->$functionName($event,$data);
    }

    /**
     * Fires the Page-Hit-Tracking
     * @param Wp_Sdtrk_Tracker_Event $event
     * @param Array $data
     * @return boolean
     */
    private function fireTracking_Server_Page($event,$data)
    {   
        //Get Event-Name
        $eventName = 'page_view';
        $this->printEventDebug($eventName, $event, $data);
        $dbHelper = new Wp_Sdtrk_DbHelper();
        return $dbHelper->saveHit($eventName, $event->getEventTime(), $event->getLocalizedEventData());
    }
    
    /**
     * Fires the Event-Hit-Tracking
     * @param Wp_Sdtrk_Tracker_Event $event
     * @param Array $data
     * @return boolean
     */
    private function fireTracking_Server_Event($event,$data)
    {
        //Get Event-Name
        $eventName = (!empty($event->getEventName())) ? $event->getEventName() : 'page_view';
        $this->printEventDebug($eventName, $event, $data);
        $dbHelper = new Wp_Sdtrk_DbHelper();
        return $dbHelper->saveHit($eventName, $event->getEventTime(), $event->getLocalizedEventData());
    }
    
    /**
     * Fires the Time-Hit-Tracking
     * @param Wp_Sdtrk_Tracker_Event $event
     * @param Array $data
     * @return boolean
     */
    private function fireTracking_Server_Time($event,$data)
    {
        //Get Event-Name
        $eventName = (isset($data['time'])) ? 'Watchtime_'.$data['time'].'_Seconds' : 'Watchtime';
        $event->setTimeTriggerData($eventName, "0");        
        $this->printEventDebug($eventName, $event, $data);
        $dbHelper = new Wp_Sdtrk_DbHelper();
        return $dbHelper->saveHit($eventName, $event->getEventTime(), $event->getLocalizedEventData());
    }
    
    /**
     * Fires the Scroll-Hit-Tracking
     * @param Wp_Sdtrk_Tracker_Event $event
     * @param Array $data
     * @return boolean
     */
    private function fireTracking_Server_Scroll($event,$data)
    {
        //Get Event-Name
        $eventName = (isset($data['percent'])) ? 'Scrolldepth_'.$data['percent'].'_Percent' : 'Scrolldepth';
        $event->setScrollTriggerData($eventName, "0");
        $this->printEventDebug($eventName, $event, $data);
        $dbHelper = new Wp_Sdtrk_DbHelper();
        return $dbHelper->saveHit($eventName, $event->getEventTime(), $event->getLocalizedEventData());
    }
    
    /**
     * Fires the Click-Hit-Tracking
     * @param Wp_Sdtrk_Tracker_Event $event
     * @param Array $data
     * @return boolean
     */
    private function fireTracking_Server_Click($event,$data)
    {
        //Get Event-Name
        $btnTag = (isset($data['tag'])) ? $data['tag'] : false;
        $eventName = ($btnTag!==false) ? 'ButtonClick'.'_'.$btnTag : 'ButtonClick';        
        $event->setClickTriggerData($eventName, strval(rand(10000,99999)), $btnTag);        
        $this->printEventDebug($eventName, $event, $data);
        $dbHelper = new Wp_Sdtrk_DbHelper();
        return $dbHelper->saveHit($eventName, $event->getEventTime(), $event->getLocalizedEventData());
    }
    
    /**
     * Fires the Visibility-Hit-Tracking
     * @param Wp_Sdtrk_Tracker_Event $event
     * @param Array $data
     * @return boolean
     */
    private function fireTracking_Server_Visibility($event,$data)
    {
        //Get Event-Name
        $elementTag = (isset($data['tag'])) ? $data['tag'] : false;
        $eventName = ($elementTag!==false) ? 'Visibility'.'_'.$elementTag : 'Visibility';
        $event->setVisibilityTriggerData($eventName, strval(rand(10000,99999)), $elementTag);
        $this->printEventDebug($eventName, $event, $data);
        $dbHelper = new Wp_Sdtrk_DbHelper();
        return $dbHelper->saveHit($eventName, $event->getEventTime(), $event->getLocalizedEventData());
    }
    
    /**
     * Prints some info about the event in the debug log
     * @param String $eventName
     * @param Wp_Sdtrk_Tracker_Event $event
     * @param Array $data
     */
    private function printEventDebug($eventName,$event,$data){
        //Print some debug info
        Wp_Sdtrk_Helper::wp_sdtrk_vardump_log("------Start Local Event-Data------:",$this->debugMode);
        Wp_Sdtrk_Helper::wp_sdtrk_vardump_log("Event-Name: ".$eventName,$this->debugMode);
        Wp_Sdtrk_Helper::wp_sdtrk_vardump_log("-Source-Event:",$this->debugMode);
        Wp_Sdtrk_Helper::wp_sdtrk_vardump_log($event,$this->debugMode);
        Wp_Sdtrk_Helper::wp_sdtrk_vardump_log("-Additional data:",$this->debugMode);
        Wp_Sdtrk_Helper::wp_sdtrk_vardump_log($data,$this->debugMode);
        Wp_Sdtrk_Helper::wp_sdtrk_vardump_log("-->Converted-Event:",$this->debugMode);
        Wp_Sdtrk_Helper::wp_sdtrk_vardump_log($event->getLocalizedEventData(),$this->debugMode);
        Wp_Sdtrk_Helper::wp_sdtrk_vardump_log("------End Local Event-Data------:",$this->debugMode);
    }
}
