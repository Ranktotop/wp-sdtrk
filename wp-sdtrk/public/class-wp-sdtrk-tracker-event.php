<?php

class Wp_Sdtrk_Tracker_Event
{

    private $eventData;

    private $timeTriggerData;

    private $scrollTriggerData;

    private $clickTriggerData;

    public function __construct($eventData)
    {
        $this->eventData = $eventData;
        $this->timeTriggerData = false;
        $this->scrollTriggerData = false;
        $this->clickTriggerData = false;
    }

    /**
     * Returns the event name
     */
    public function getEventName()
    {
        $rawEventName = "";
        if (isset($this->eventData['eventName'])) {
            foreach ($this->eventData['eventName'] as $value) {
                if (! empty($value)) {
                    $rawEventName = $value;
                    break;
                }
            }
        }

        if (empty($rawEventName) && ! empty($this->getTransactionId())) {
            return "purchase";
        }
        if (empty($rawEventName) && ! empty($this->getProductId())) {
            return 'view_item';
        }
        switch (strtolower($rawEventName)) {
            case 'pageview':
                return 'page_view';
            case 'addtocart':
                return 'add_to_cart';
            case 'purchase':
                return 'purchase';
            case 'completeregistration':
                return 'sign_up';
            case 'lead':
                return 'generate_lead';
            case 'initiatecheckout':
                return 'begin_checkout';
            case 'viewcontent':
                return 'view_item';
            default:
                return (empty($rawEventName)) ? false : $rawEventName;
        }
    }

    /**
     * Return the event value
     *
     * @return number
     */
    public function getEventValue()
    {
        if (isset($this->eventData['value'])) {
            foreach ($this->eventData['value'] as $value) {
                if (! empty($value)) {
                    return floatval($value);
                }
            }
        }
        return 0;
    }

    /**
     * Return the product-id
     *
     * @return string
     */
    public function getProductId()
    {
        if (isset($this->eventData['prodId'])) {
            foreach ($this->eventData['prodId'] as $value) {
                if (! empty($value)) {
                    return $value;
                }
            }
        }
        return "";
    }

    /**
     * Return the page-id
     *
     * @return string
     */
    public function getPageId()
    {
        if (isset($this->eventData['pageId'])) {
            return $this->eventData['pageId'];
        }
        return "";
    }

    /**
     * Return the page-name
     *
     * @return string
     */
    public function getPageName()
    {
        if (isset($this->eventData['pageName'])) {
            return $this->eventData['pageName'];
        }
        return "";
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

    public function getClickTriggerData()
    {
        return $this->clickTriggerData;
    }

    /**
     * Return the product-name
     *
     * @return string
     */
    public function getProductName()
    {
        if (isset($this->eventData['prodName'])) {
            foreach ($this->eventData['prodName'] as $value) {
                if (! empty($value)) {
                    return $value;
                }
            }
        }
        return "custom";
    }

    /**
     * Return the user-firstname
     *
     * @return string
     */
    public function getUserFirstName()
    {
        if (isset($this->eventData['userFirstName'])) {
            foreach ($this->eventData['userFirstName'] as $value) {
                if (! empty($value)) {
                    return $value;
                }
            }
        }
        return False;
    }

    /**
     * Return the user-lastname
     *
     * @return string
     */
    public function getUserLastName()
    {
        if (isset($this->eventData['userLastName'])) {
            foreach ($this->eventData['userLastName'] as $value) {
                if (! empty($value)) {
                    return $value;
                }
            }
        }
        return False;
    }

    /**
     * Return the user-email
     *
     * @return string
     */
    public function getUserEmail()
    {
        if (isset($this->eventData['userEmail'])) {
            foreach ($this->eventData['userEmail'] as $value) {
                if (! empty($value)) {
                    return $value;
                }
            }
        }
        return False;
    }

    /**
     * Return the Brandname
     *
     * @return string
     */
    public function getBrandName()
    {
        if (isset($this->eventData['brandName']) && ! empty($this->eventData['brandName'])) {
            return $this->eventData['brandName'];
        }
        $brandName = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "brandname");
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
     * Return a random event-id
     *
     * @return string
     */
    public function getEventId()
    {
        if (! empty($this->getTransactionId())) {
            return $this->getTransactionId();
        }

        if (isset($this->eventData['eventId']) && ! empty($this->eventData['eventId'])) {
            return $this->eventData['eventId'];
        }
        return substr(str_shuffle(MD5(microtime())), 0, 10);
    }

    /**
     * Return the IP-Adress
     *
     * @return string
     */
    public function getEventIp()
    {
        if (isset($this->eventData['eventSourceAdress']) && ! empty($this->eventData['eventSourceAdress'])) {
            return $this->eventData['eventSourceAdress'];
        }
        return Wp_Sdtrk_Helper::wp_sdtrk_getClientIp();
    }

    /**
     * Return the User-Agent
     *
     * @return string
     */
    public function getEventAgent()
    {
        if (isset($this->eventData['eventSourceAgent']) && ! empty($this->eventData['eventSourceAgent'])) {
            return $this->eventData['eventSourceAgent'];
        }
        return (isset($_SERVER['HTTP_USER_AGENT']) && $_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : "";
    }

    /**
     * Return the Source-URL
     *
     * @return string
     */
    public function getEventSource()
    {
        if (isset($this->eventData['eventSource']) && ! empty($this->eventData['eventSource'])) {
            return $this->eventData['eventSource'];
        }
        return Wp_Sdtrk_Helper::wp_sdtrk_getCurrentURL();
    }

    /**
     * Return the Referer-URL
     *
     * @return string
     */
    public function getEventReferer()
    {
        if (isset($this->eventData['eventSourceReferer']) && ! empty($this->eventData['eventSourceReferer'])) {
            return $this->eventData['eventSourceReferer'];
        }
        return Wp_Sdtrk_Helper::wp_sdtrk_getCurrentReferer();
    }

    /**
     * Returns the path of event-url with query
     *
     * @return string
     */
    public function getEventPath()
    {
        if (isset($this->eventData['eventPath']) && ! empty($this->eventData['eventPath'])) {
            return $this->eventData['eventPath'];
        }
        return $_SERVER['REQUEST_URI'];
    }

    /**
     * Returns the domain of event
     *
     * @return string
     */
    public function getEventDomain()
    {
        if (isset($this->eventData['eventDomain']) && ! empty($this->eventData['eventDomain'])) {
            return $this->eventData['eventDomain'];
        }
        return rtrim(get_site_url(), "/") . '/';
    }

    /**
     * Returns the full url of event with query
     *
     * @return string
     */
    public function getEventUrl()
    {
        if (isset($this->eventData['eventUrl']) && ! empty($this->eventData['eventUrl'])) {
            return $this->eventData['eventUrl'];
        }
        return Wp_Sdtrk_Helper::wp_sdtrk_getCurrentURL();
    }

    /**
     * Returns the hour of event-time
     *
     * @return string
     */
    public function getEventTimeHour()
    {
        if (isset($this->eventData['eventTimeHour']) && ! empty($this->eventData['eventTimeHour'])) {
            return $this->eventData['eventTimeHour'];
        }
        return "";
    }

    /**
     * Returns the day of event-time
     *
     * @return string
     */
    public function getEventTimeDay()
    {
        if (isset($this->eventData['eventTimeDay']) && ! empty($this->eventData['eventTimeDay'])) {
            return $this->eventData['eventTimeDay'];
        }
        return "";
    }

    /**
     * Returns the month of event-time
     *
     * @return string
     */
    public function getEventTimeMonth()
    {
        if (isset($this->eventData['eventTimeMonth']) && ! empty($this->eventData['eventTimeMonth'])) {
            return $this->eventData['eventTimeMonth'];
        }
        return "";
    }

    /**
     * Returns the timestamp of event
     *
     * @return string
     */
    public function getEventTime()
    {
        if (isset($this->eventData['eventTime']) && ! empty($this->eventData['eventTime'])) {
            return $this->eventData['eventTime'];
        }
        return time();
    }

    /**
     * Return the Event-Time
     *
     * @return string
     */
    public function getTime()
    {
        if (isset($this->eventData['eventTime']) && ! empty($this->eventData['eventTime'])) {
            return $this->eventData['eventTime'];
        }
        return date_create()->getTimestamp();
    }

    /**
     * Return the transaction/order-id
     *
     * @return string
     */
    public function getTransactionId()
    {
        if (isset($this->eventData['orderId'])) {
            foreach ($this->eventData['orderId'] as $value) {
                if (! empty($value)) {
                    return $value;
                }
            }
        }
        return "";
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
        $data['utm'] = $this->getUtmData();
        return $data;
    }
}
