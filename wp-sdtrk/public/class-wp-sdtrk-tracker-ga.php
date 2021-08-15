<?php

class Wp_Sdtrk_Tracker_Ga
{

    private $measurementId;

    private $trackBrowser;

    private $debugMode;
    private $trackBrowser_cookie_service;
    private $trackBrowser_cookie_id;
    private $consentChecker;
    private $localizedData;

    public function __construct()
    {
        $this->measurementId = false;
        $this->trackBrowser = false;
        $this->debugMode = false;
        $this->trackBrowser_cookie_service = false;
        $this->trackBrowser_cookie_id = false;
        $this->consentChecker = new Wp_Sdtrk_Tracker_Cookie();
        $this->localizedData = array();
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
        
        // Track Browser Cookie Service
        $trkBrowserCookieService = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "ga_trk_browser_cookie_service");
        $this->trackBrowser_cookie_service = ($trkBrowserCookieService && ! empty(trim($trkBrowserCookieService))) ? $trkBrowserCookieService : false;
        
        // Track Browser Cookie ID
        $trkBrowserCookieId = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "ga_trk_browser_cookie_id");
        $this->trackBrowser_cookie_id = ($trkBrowserCookieId && ! empty(trim($trkBrowserCookieId))) ? $trkBrowserCookieId : false;
                
        //LocalizedData
        $this->localizedData['wp_sdtrk_ga_b_consent'] = $this->consentChecker->hasConsent($this->trackBrowser_cookie_service, $this->trackBrowser_cookie_id);
        $this->localizedData['ajax_url'] = admin_url('admin-ajax.php');
        $this->localizedData['_nonce'] = wp_create_nonce('security_wp-sdtrk');
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
        //$this->localizedData['wp_sdtrk_ga_eventData'] = $event->getEventAsArray();
        
        // Send the Data
        // Browser
        if ($this->trackingEnabled_Browser()) {
            $this->fireTracking_Browser($event);
        }
        else{
            //Needed to prevent the Backload-Function from crashing
            wp_localize_script("wp_sdtrk-ga", 'wp_sdtrk_ga', $this->localizedData);
            wp_enqueue_script('wp_sdtrk-ga');
        }
    }

    /**
     * Fires the Browser-based Tracking
     *
     * @param Wp_Sdtrk_Tracker_Event $event
     */
    private function fireTracking_Browser($event)
    {        
        $browserLocalizedData = $this->localizedData;
        
        $eventData = array();
        $initData = array('debug_mode' => $this->debugMode);
        $campaignData = array();
        
        //The Base Data
        $eventData['transaction_id'] = $event->getEventId();        
        if($event->getEventValue() >0){
            $eventData['value'] = $event->getEventValue();
            $eventData['currency'] = "EUR";
        }        
        //Maybe utm_campaign has to be renamed to utm_name!
        foreach($event->getUtmData() as $utmData => $value){
            $dataName = str_replace("utm_", "", $utmData);
            $dataVal = $value;
            $campaignData[$dataName] = $dataVal;
            $initData[$dataName] = $dataVal;
            $eventData[$dataName] = $dataVal;
        }
        
        if(!empty($campaignData)){
            $initData['campaign'] = $campaignData;
            $eventData['campaign'] = $campaignData;
        }
        
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
        
        $browserLocalizedData['wp_sdtrk_ga_id'] = $this->measurementId;
        $browserLocalizedData['wp_sdtrk_ga_eventName'] = $event->getEventName();
        $browserLocalizedData['wp_sdtrk_ga_eventData'] = $eventData;
        $browserLocalizedData['wp_sdtrk_ga_initData'] = $initData;

        wp_localize_script("wp_sdtrk-ga", 'wp_sdtrk_ga', $browserLocalizedData);
        wp_enqueue_script('wp_sdtrk-ga');
    }
}
