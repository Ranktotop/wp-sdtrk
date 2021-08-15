<?php

class Wp_Sdtrk_Tracker_Event
{

    private $brandName;

    private $transactionId;

    private $productId;

    private $value;

    private $eventName;

    private $eventId;
    
    private $productName;
    
    private $utmData;

    public function __construct()
    {
        $this->init();
    }

    /**
     * Initialize the saved Data
     */
    private function init()
    {
        // Brandname
        $brandName = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "brandname");
        $this->brandName = ($brandName && ! empty(trim($brandName))) ? $brandName : get_bloginfo('name');

        // Event-ID
        $this->eventId = $this->generateEventId();

        // Transaction-ID
        $this->transactionId = $this->fetchTransactionId();

        // Product-ID
        $this->productId = $this->fetchProductId();

        // Value
        $this->value = $this->fetchEventValue();
        
        // Product-Name
        $this->productName = $this->fetchProductName();
        
        //UTM-Parameters
        $this->utmData = $this->saveAndGetUTM();
        
        // Eventname
        $this->eventName = $this->fetchEventName();
        
    }
    
    /**
     * Return the Event-Data as array
     * @return array
     */
    public function getEventAsArray(){
        return array(
            'brandName' => $this->brandName,
            'transactionId' => $this->transactionId,            
            'productId' => $this->productId,
            'value'=> $this->value,            
            'eventName'=> $this->eventName,            
            'eventId'=> $this->eventId,            
            'productName'=> $this->productName,            
            'utmData'=> $this->utmData,
        );
    }
    
    /**
     * Set Event-Data from array
     * @param array $data
     */
    public function setEventFromArray($data){
        if(isset($data['brandName'])){
            $this->brandName = $data['brandName'];
        }
        if(isset($data['transactionId'])){
            $this->transactionId = $data['transactionId'];
        }
        if(isset($data['productId'])){
            $this->productId = $data['productId'];
        }
        if(isset($data['value'])){
            $this->value = $data['value'];
        }
        if(isset($data['eventName'])){
            $this->eventName = $data['eventName'];
        }
        if(isset($data['eventId'])){
            $this->eventId = $data['eventId'];
        }
        if(isset($data['productName'])){
            $this->productName = $data['productName'];
        }
        if(isset($data['utmData'])){
            $this->utmData = $data['utmData'];
        }
    }
    
    /**
     * Collect UtmData
     * @return string[]
     */
    private function saveAndGetUTM(){
        $utmParams = array('source','medium','term','content','campaign');
        $utmData = array();
        foreach($utmParams as $param){
            $value = Wp_Sdtrk_Helper::wp_sdtrk_getGetParamWithCookie('utm_'.$param,true,true,14);
            if(!empty($value)){
                $utmData['utm_'.$param] = $value;
            }
        }
        return $utmData;
    }

    /**
     * Generate an unique identifier
     *
     * @return string
     */
    private function generateEventId()
    {
        return substr(str_shuffle(MD5(microtime())), 0, 10);
    }

    /**
     * Returns the Transaction-ID
     */
    private function fetchTransactionId()
    {
        $paramList = array(
            'order_id'
        );
        return Wp_Sdtrk_Helper::wp_sdtrk_searchParams($paramList);
    }

    /**
     * Returns the product ID
     */
    private function fetchProductId()
    {
        $paramList = array(
            'prodid',
            'product_id'
        );
        
        $prodId = Wp_Sdtrk_Helper::wp_sdtrk_searchParams($paramList);
        if(empty($prodId)){
            global $post;
            if($post && $post->ID){
                $prodId = get_post_meta($post->ID, 'productid', true);
            }
        }
        return ($prodId !== false) ? $prodId : "";
    }

    /**
     * Returns the Event-Value
     */
    private function fetchEventValue()
    {
        $paramList = array(
            'value',
            'net_amount',
            'amount'
        );
        $value = Wp_Sdtrk_Helper::wp_sdtrk_searchParams($paramList);
        return (!empty($value)) ? floatval($value) : 0;
    }

    /**
     * Returns the Event-Name
     */
    private function fetchEventName()
    {
        $paramList = array(
            'type'
        );
        $rawEventName = Wp_Sdtrk_Helper::wp_sdtrk_searchParams($paramList);
        if (empty($rawEventName) && ! empty($this->transactionId)) {
            return "purchase";
        }
        if(empty($rawEventName) && !empty($this->productId)){
            return 'view_item';
        }
        switch ($rawEventName) {
            case 'PageView':
                return 'page_view';
            case 'AddToCart':
                return 'add_to_cart';
            case 'Purchase':
                return 'purchase';
            case 'CompleteRegistration':
                return 'sign_up';
            case 'Lead':
                return 'generate_lead';
            case 'InitiateCheckout':
                return 'begin_checkout';
            case 'ViewContent':
                return 'view_item';
            default:
                return (empty($rawEventName)) ? false : $rawEventName;
        }
    }

    /**
     * Returns the product Name
     */
    private function fetchProductName()
    {
        $paramList = array(
            'product_name'
        );
        $name = Wp_Sdtrk_Helper::wp_sdtrk_searchParams($paramList);
        return (!empty($name)) ? $name : 'custom';
    }
    
    /**
     * Getter for the Eventname
     * @return string|boolean
     */
    public function getEventName(){
        return $this->eventName;
    }
    
    public function getEventValue(){
        return $this->value;
    }
    
    public function getProductId(){
        return $this->productId;
    }
    
    public function getProductName(){
        return $this->productName;
    }
    
    public function getBrandName(){
        return $this->brandName;
    }
    
    public function getUtmData(){
        return $this->utmData;
    }
    
    public function getEventId(){
        return (!empty($this->transactionId)) ? $this->transactionId : $this->eventId;
    }
}
