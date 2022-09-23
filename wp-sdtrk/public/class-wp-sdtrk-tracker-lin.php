<?php

class Wp_Sdtrk_Tracker_Lin
{

    private $pixelId;

    private $trackServer;

    public function __construct()
    {
        $this->pixelId = false;
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
     * Fires the Server-based Tracking
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     */
    public function fireTracking_Server($event, $externalId = "", $linc = "")
    {
    }

    /**
     * Payloads the Data and sends it to the Server
     *
     * @param array $requestData
     */
    private function payLoadServerRequest($requestData)
    {
    }

    /**
     * Converts the Raw-Eventname to LinkedIn-Event-Name
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     */
    private function readEventName($event)
    {
    }

    /**
     * This example is 1:1 from LinkedIn Documentation
     */
    private function debugEvent()
    {
    }
}
