<?php

class Wp_Sdtrk_Tracker_Lin
{

    private $pixelId;

    private $trackServer;
    
    private $debugMode;

    public function __construct()
    {
        $this->pixelId = false;
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
        $lin_pixelId = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "lin_pixelid");
        $this->pixelId = ($lin_pixelId && ! empty(trim($lin_pixelId))) ? $lin_pixelId : false;
    }
    
    /**
     * Returns the API Url to the Measurement Protocol
     *
     * @return string
     */
    private function getApiUrl()
    {
        return false;
    }
    
    /**
     * Checks if Server-Tracking is enabled
     *
     * @return boolean
     */
    private function trackingEnabled_Server()
    {
        return false;
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
       
    }
}
