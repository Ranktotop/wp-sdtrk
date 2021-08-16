<?php

class Wp_Sdtrk_Tracker_Event
{
    private $eventData;

    public function __construct($eventData)
    {
        $this->eventData = $eventData;
    }

    /**
     * Return the Event-Data as array
     *
     * @return array
     */
    public function getEventAsArray()
    {
        return array(
            'brandName' => $this->getBrandName(),
            'transactionId' => $this->getTransactionId(),
            'productId' => $this->getProductId(),
            'value' => $this->getEventValue(),
            'eventName' => $this->getEventName(),
            'eventId' => $this->getEventId(),
            'productName' => $this->getProductName(),
            'utmData' => $this->getUtmData(),
            'adress' => $this->getEventIp(),
            'source' => $this->getEventSource(),
            'agent' => $this->getEventAgent(),
            'time' => $this->getTime()
        );
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

        if (empty($rawEventName) && ! empty($this->transactionId)) {
            return "purchase";
        }
        if (empty($rawEventName) && ! empty($this->productId)) {
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
     * Return the product-name
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
     * Return the Brandname
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
     * @return string[]
     */
    public function getUtmData()
    {
        $utmData = array();
        if (isset($this->eventData['utm'])) {
            foreach ($this->eventData['utm'] as $key => $value) {
                if (! empty($value)) {
                    $utmData[str_replace('utm_', '', $key)] = $value;
                }
            }
        }
        return $utmData;
    }

    /**
     * Return a random event-id
     * @return string
     */
    public function getEventId()
    {
        if(!empty($this->getTransactionId())){
            return $this->getTransactionId();
        }
        
        if (isset($this->eventData['eventId']) && ! empty($this->eventData['eventId'])) {
            return $this->eventData['eventId'];
        }
        return substr(str_shuffle(MD5(microtime())), 0, 10);
    }
    
    /**
     * Return the IP-Adress
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
     * Return the Event-Time
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
}
