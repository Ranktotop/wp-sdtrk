<?php
/**
 * Class for checking cookie consent from different services
 * @author marcmeese
 *
 */
class Wp_Sdtrk_Tracker_Cookie
{   
    /**
     * Checks if the cookie consent is given
     * @param String $service
     * @param String $cookieId
     * @return boolean
     */
    public function hasConsent($service, $cookieId){
        //Check for Cookie-Input
        if(!$cookieId || empty($cookieId)){
            return true;
        }
        switch($service){
            case 'none':
                return true;
            case 'borlabs':
                return $this->borlabs_hasConsent($cookieId);
        }
        return true;
    }
    
    /**
     * 
     * @param String $cookieId
     * @return boolean
     */
    private function borlabs_hasConsent($cookieId){
        //Check for Plugin-Activation
        require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        if(!is_plugin_active('borlabs-cookie')){
            if (function_exists('BorlabsCookieHelper')) {
                return BorlabsCookieHelper()->gaveConsent($cookieId);                
            }
        }
        return true;
    }
    
    

}
