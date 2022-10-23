<?php

class Wp_Sdtrk_Tracker_Event
{

    private $eventData;

    private $timeTriggerData;

    private $scrollTriggerData;

    private $clickTriggerData;

    private $visibilityTriggerData;

    public function __construct($eventData)
    {
        $this->eventData = $eventData;
        $this->timeTriggerData = false;
        $this->scrollTriggerData = false;
        $this->clickTriggerData = false;
        $this->visibilityTriggerData = false;
    }

    /**
     * Returns the event name
     */
    public function getEventName()
    {
        return $this->parseEventName($this->grabFirstValue('eventName'));
    }

    /**
     * Return the event value
     *
     * @return number
     */
    public function getEventValue()
    {
        $value = $this->grabFirstValue('value');
        return (! empty($value)) ? floatval($value) : 0;
    }

    /**
     * Return the product-id
     *
     * @return string
     */
    public function getProductId()
    {
        return $this->grabFirstValue('prodId');
    }

    /**
     * Return the page-id
     *
     * @return string
     */
    public function getPageId()
    {
        return ($this->setAndFilled('pageId')) ? $this->eventData['pageId'] : "";
    }

    /**
     * Return the page-name
     *
     * @return string
     */
    public function getPageName()
    {
        return ($this->setAndFilled('pageName')) ? $this->eventData['pageName'] : "";
    }

    /**
     * Return the product-name or custom if no name is set
     *
     * @return string
     */
    public function getProductName()
    {
        $prodName = $this->grabFirstValue('prodName');
        return (! empty($prodName)) ? $prodName : "custom";
    }

    /**
     * Return the user-firstname
     *
     * @return string
     */
    public function getUserFirstName()
    {
        return $this->grabFirstValue('userFirstName');
    }

    /**
     * Return the user-lastname
     *
     * @return string
     */
    public function getUserLastName()
    {
        return $this->grabFirstValue('userLastName');
    }
    
    /**
     * Return the user-fingerprint
     *
     * @return string
     */
    public function getUserFingerprint()
    {
        return ($this->setAndFilled('userFP')) ? $this->eventData['userFP'] : false;
    }

    /**
     * Return the user-email
     *
     * @return string
     */
    public function getUserEmail()
    {
        return $this->grabFirstValue('userEmail');
    }

    /**
     * Return the Brandname
     *
     * @return string
     */
    public function getBrandName()
    {
        $brandName = ($this->setAndFilled('brandName')) ? $this->eventData['brandName'] : Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "brandname");
        return ($brandName && ! empty(trim($brandName))) ? $brandName : get_bloginfo('name');
    }

    /**
     * Return UTM-Data
     *
     * @return string[]
     */
    public function getUtmData()
    {
        $utmData = array();
        if (isset($this->eventData['utm'])) {
            foreach ($this->eventData['utm'] as $key => $value) {
                if (! empty($value)) {
                    $utmData[$key] = $value;
                }
            }
        }
        return $utmData;
    }

    /**
     * Return the transaction/order-id
     *
     * @return string
     */
    public function getTransactionId()
    {
        return $this->grabFirstValue('orderId');
    }

    /**
     * Return a random event-id
     *
     * @return string
     */
    public function getEventId()
    {
        if (! empty($this->getTransactionId())) {
            return $this->getTransactionId();
        }
        return ($this->setAndFilled('eventId')) ? $this->eventData['eventId'] : substr(str_shuffle(MD5(microtime())), 0, 10);
    }

    /**
     * Return the IP-Adress
     *
     * @return string
     */
    public function getEventIp()
    {
        return ($this->setAndFilled('eventSourceAdress')) ? $this->eventData['eventSourceAdress'] : Wp_Sdtrk_Helper::wp_sdtrk_getClientIp();
    }

    /**
     * Return the User-Agent
     *
     * @return string
     */
    public function getEventAgent()
    {
        if ($this->setAndFilled('eventSourceAgent')) {
            return $this->eventData['eventSourceAgent'];
        }
        if (isset($_SERVER['HTTP_USER_AGENT']) && $_SERVER['HTTP_USER_AGENT']) {
            return $_SERVER['HTTP_USER_AGENT'];
        }
        return "";
    }

    /**
     * Return the Source-URL
     *
     * @return string
     */
    public function getEventSource()
    {
        return ($this->setAndFilled('eventSource')) ? $this->eventData['eventSource'] : Wp_Sdtrk_Helper::wp_sdtrk_getCurrentURL();
    }

    /**
     * Return the Referer-URL
     *
     * @return string
     */
    public function getEventReferer()
    {
        return ($this->setAndFilled('eventSourceReferer')) ? $this->eventData['eventSourceReferer'] : Wp_Sdtrk_Helper::wp_sdtrk_getCurrentReferer();
    }

    /**
     * Returns the path of event-url with query
     *
     * @return string
     */
    public function getEventPath()
    {
        return ($this->setAndFilled('eventPath')) ? $this->eventData['eventPath'] : $_SERVER['REQUEST_URI'];
    }

    /**
     * Returns the domain of event
     *
     * @return string
     */
    public function getEventDomain()
    {
        return ($this->setAndFilled('eventDomain')) ? $this->eventData['eventDomain'] : rtrim(get_site_url(), "/") . '/';
    }

    /**
     * Returns the full url of event with query
     *
     * @return string
     */
    public function getEventUrl()
    {
        return ($this->setAndFilled('eventUrl')) ? $this->eventData['eventUrl'] : Wp_Sdtrk_Helper::wp_sdtrk_getCurrentURL();
    }

    /**
     * Returns the hour of event-time
     *
     * @return string
     */
    public function getEventTimeHour()
    {
        return ($this->setAndFilled('eventTimeHour')) ? $this->eventData['eventTimeHour'] : "";
    }

    /**
     * Returns the day of event-time
     *
     * @return string
     */
    public function getEventTimeDay()
    {
        return ($this->setAndFilled('eventTimeDay')) ? $this->eventData['eventTimeDay'] : "";
    }

    /**
     * Returns the month of event-time
     *
     * @return string
     */
    public function getEventTimeMonth()
    {
        return ($this->setAndFilled('eventTimeMonth')) ? $this->eventData['eventTimeMonth'] : "";
    }

    /**
     * Returns the timestamp of event
     *
     * @return string
     */
    public function getEventTime()
    {
        return ($this->setAndFilled('eventTime')) ? $this->eventData['eventTime'] : time();
    }

    /**
     * Return the Event-Time
     *
     * @return string
     */
    public function getTime()
    {
        return ($this->setAndFilled('eventTime')) ? $this->eventData['eventTime'] : Wp_Sdtrk_Helper::wp_sdtrk_DateToTimestamp(false);
    }

    /**
     * Sets the Time Data for Time-Events
     *
     * @param String $eventName
     * @param String $eventId
     */
    public function setTimeTriggerData($eventName, $eventId)
    {
        if (empty($eventName) || empty($eventId)) {
            $this->timeTriggerData = false;
        }
        $this->timeTriggerData = [
            'name' => $eventName,
            'id' => $eventId
        ];
    }

    /**
     * Returns the Time-Trigger-Data
     *
     * @return boolean|String[]
     */
    public function getTimeTriggerData()
    {
        return $this->timeTriggerData;
    }

    /**
     * Sets the Scroll Data for Scroll-Events
     *
     * @param String $eventName
     * @param String $eventId
     */
    public function setScrollTriggerData($eventName, $eventId)
    {
        if (empty($eventName) || empty($eventId)) {
            $this->scrollTriggerData = false;
        }
        $this->scrollTriggerData = [
            'name' => $eventName,
            'id' => $eventId
        ];
    }

    /**
     * Returns the Scroll-Trigger-Data
     *
     * @return boolean|String[]
     */
    public function getScrollTriggerData()
    {
        return $this->scrollTriggerData;
    }

    /**
     * Sets the Click Data for Click-Events
     *
     * @param String $eventName
     * @param String $eventId
     */
    public function setClickTriggerData($eventName, $eventId, $tag)
    {
        if (empty($eventName) || empty($eventId) || $tag) {
            $this->clickTriggerData = false;
        }
        $this->clickTriggerData = [
            'name' => $eventName,
            'id' => $eventId,
            'tag' => $tag
        ];
    }

    /**
     * Returns the Click-Trigger-Data
     *
     * @return boolean|String[]
     */
    public function getClickTriggerData()
    {
        return $this->clickTriggerData;
    }

    /**
     * Sets the Visibility Data for Scroll-Events
     *
     * @param String $eventName
     * @param String $eventId
     */
    public function setVisibilityTriggerData($eventName, $eventId, $tag)
    {
        if (empty($eventName) || empty($eventId) || $tag) {
            $this->visibilityTriggerData = false;
        }
        $this->visibilityTriggerData = [
            'name' => $eventName,
            'id' => $eventId,
            'tag' => $tag
        ];
    }

    /**
     * Returns the Visibility-Trigger-Data
     *
     * @return boolean|String[]
     */
    public function getVisibilityTriggerData()
    {
        return $this->visibilityTriggerData;
    }

    /**
     * Get a simplified version of this event for local database
     *
     * @return array();
     */
    public function getLocalizedEventData()
    {
        $data = array();
        $data['product'] = array(
            'id' => $this->getProductId(),
            'name' => $this->getProductName(),
            'value' => $this->getEventValue()
        );
        $data['page'] = array(
            'id' => $this->getPageId(),
            'name' => $this->getPageName(),
            'source' => $this->getEventSource()
        );
        //if fingerprint is set
        if($this->getUserFingerprint() !== false){
            $data['user'] = array(
                'id' => $this->getUserFingerprint()
            );
        }

        // UTM
        $utmData = array();
        foreach ($this->getUtmData() as $key => $value) {
            $utmData[str_replace("utm_", "", $key)] = $value;
        }
        $data['utm'] = $utmData;
        return $data;
    }

    /**
     * Checks if the given fieldname exists and is not empty
     *
     * @param string $fieldname
     * @return boolean
     */
    private function setAndFilled($fieldname)
    {
        return (isset($this->eventData[$fieldname]) && ! empty($this->eventData[$fieldname]) && $this->eventData[$fieldname] !== 'false') ? true : false;
    }

    /**
     * Grab first value of given object
     *
     * @param array $valueArray
     * @return string
     */
    private function grabFirstValue($keyName)
    {
        $valueArray = (isset($this->eventData[$keyName]) && is_array($this->eventData[$keyName])) ? $this->eventData[$keyName] : array();
        foreach ($valueArray as $field) {
            if (! empty($field)) {
                return $field;
            }
        }
        return "";
    }

    private function parseEventName($name)
    {
        // convert to lowercase without underlines or hyphens if valid value
        $name = (! $name || empty($name)) ? $name : str_replace("-", "", str_replace("_", "", strtolower($name)));

        // if no name is set but there is an order-id, setup as purchase
        if (empty($name) && $this->getEventValue() > 0) {
            return "purchase";
        }
        // if no name is set but there is an product-id, setup as view-item
        if (empty($name) && ! empty($this->getProductId())) {
            return 'view_item';
        }
        // map typical names (eg. from fb or tt) to ga-events
        $map = array(
            // 'page_view' => array('pageview', 'viewpage', 'view'), //The page-view
            'view_item' => array(
                'viewitem',
                'viewcontent'
            ), // If an page is visited which relates to an product
            'generate_lead' => array(
                'generatelead',
                'lead',
                'submitform'
            ), // The leads before doi
            'sign_up' => array(
                'signup',
                'completeregistration',
                'doi'
            ), // The leads after doi
            'add_to_cart' => array(
                'addtocart',
                'atc'
            ), // The add to cart
            'begin_checkout' => array(
                'begincheckout',
                'initiatecheckout'
            ), // The start of the checkout process
            'purchase' => array(
                'purchase',
                'placeanorder',
                'sale'
            ) // The purchase
        );
        foreach ($map as $key => $value) {
            if (in_array($name, $value)) {
                return $key;
            }
        }
        return false;
    }
    
    /**
     * Gives an Event-Name for given type
     * @param string $type
     * @param string $data
     */
    public function get_CustomEventName($type, $data = '0'){
        $map = Wp_Sdtrk_Helper::wp_sdtrk_getDefaultEventMap();
        if(isset($map[$type])){
            return str_replace('%', $data, $map[$type]);
        }
        return $type;
    }
}
