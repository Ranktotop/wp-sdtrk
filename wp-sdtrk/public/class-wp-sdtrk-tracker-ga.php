<?php

class Wp_Sdtrk_Tracker_Ga
{

    private $measurementId;
    private $debugMode;

    public function __construct()
    {
        $this->measurementId = false;
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

        // Debug Mode
        $debug = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "ga_trk_debug"), "yes") == 0) ? true : false;
        $this->debugMode = ($debug && ! empty(trim($debug))) ? $debug : false;        
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
     * Fires the Browser-based Tracking
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     */
    private function fireTracking_Sever($event)
    {        
        
    }
}
