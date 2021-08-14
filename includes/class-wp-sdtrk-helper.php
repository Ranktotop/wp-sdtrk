<?php

/**
 * Helper Functions
 *
 * @link       https://www.rank-to-top.de
 * @since      1.0.0
 *
 */
class Wp_Sdtrk_Helper
{
    
    /**
     * Get value of given key in multidimensional Array
     *
     * @param string[] $haystack
     * @param string $needle
     * @return string|mixed
     */
    public static function wp_sdtrk_recursiveFind($haystack, $needle)
    {
        if (! is_array($haystack)) {
            return "";
        }
        $iterator = new RecursiveArrayIterator($haystack);
        $recursive = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::SELF_FIRST);
        foreach ($recursive as $key => $value) {
            if ($key === $needle) {
                return $value;
            }
        }
    }
    
    /**
     * Write to log, if debug is enabled
     *
     * @param string $log
     */
    public static function wp_sdtrk_write_log($log)
    {
        if (is_array($log) || is_object($log)) {
            error_log(print_r($log, true));
        } else {
            error_log($log);
        }
    }
    
    /**
     * Write dumb to log, if debug is enabled
     *
     * @param string $log
     */
    public static function wp_sdtrk_vardump_log($var)
    {
        ob_start();
        var_dump($var);
        return self::wp_sdtrk_write_log(ob_get_clean());
    }
    
    /**
     * Get all Pages/Posts by metakey and value (usefull for exopite options)
     *
     * @param string $key
     * @param string $value
     * @return array
     */
    public static function wp_sdtrk_getPagesByMeta($key, $value, $limit = -1)
    {
        // Prepare query
        $args = array(
            'post_type' => array(
                'post',
                'page'
            ),
            'posts_per_page' => $limit,
            'meta_query' => array(
                array(
                    'key' => $key,
                    'value' => sprintf(':"%s";', $value),
                    'compare' => 'LIKE'
                )
            )
        );
        
        // Get all pages within this category
        $query = new WP_Query($args);
        if (isset($query->posts)) {
            return $query->posts;
        }
        return array();
    }
    
    /**
     * Replace Value for Key recursive in array
     * @param array $array
     * @param String $searchkey
     * @param String $newValue
     * @return array
     */
    public static function wp_sdtrk_replaceValueForKey_recursive(&$array, $searchkey, $newValue)
    {
        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                self::wp_sdtrk_replaceValueForKey_recursive($value, $searchkey, $newValue);
            } else {
                
                if (strcmp($key, $searchkey) == 0) {
                    $array[$key] = $newValue;
                    break;
                }
            }
        }
        
        return $array;
    }
    
    /**
     * Replaces key or value in array (recursive) with given value (or function)
     *
     * @param string[] $subject
     * @param string $oldValue
     * @param
     *            string | callable $newValue
     * @param string $type
     * @return string[]
     */
    public static function wp_sdtrk_replace_arrayeElement($subject, $oldValue, $newValue, $type = "key")
    {
        if (! is_array($subject)) {
            return $subject;
        }
        
        $helper = array();
        foreach ($subject as $key => $value) {
            $compareItem = (strcasecmp($type, "key") == 0) ? $key : $value;
            
            // If the key or value match the searched term
            if (! is_array($compareItem) && strcmp($compareItem, $oldValue) == 0) {
                
                // Get the new term
                if (method_exists('Wp_Sdtrk_Helper', $newValue)) {
                    $newItem = forward_static_call([
                        'Wp_Sdtrk_Helper',
                        $newValue
                    ]);
                } else {
                    $newItem = $newValue;
                }
                
                $newKey = (strcasecmp($type, "key") == 0) ? $newItem : $key;
                $newValue = (strcasecmp($type, "key") == 0) ? $value : $newItem;
                $helper[$newKey] = (is_array($value)) ? self::wp_sdtrk_replace_arrayeElement($value, $oldValue, $newValue, $type) : $newValue;
            } else {
                $helper[$key] = (is_array($value)) ? self::wp_sdtrk_replace_arrayeElement($value, $oldValue, $newValue, $type) : $value;
            }
        }
        return $helper;
    }
    
    /**
     * Generates a random Number as String
     *
     * @return string
     */
    private static function wp_sdtrk_getRandomNumberAsInt()
    {
        return strval(rand(10000, 99999));
    }
    
    /**
     * Update Wordpress Option, create if it doesnt exist
     *
     * @param string $opt
     * @param string $val
     */
    public static function wp_sdtrk_insertOrUpdateOption($opt, $val)
    {
        if (get_option($opt) !== false) {
            update_option($opt, $val);
        } else {
            add_option($opt, $val);
        }
    }
    
    /**
     * Checks if a key exists anywhere in the array
     *
     * @param string $key
     * @param string[] $array
     * @return boolean
     */
    public static function wp_sdtrk_array_key_exists_recursive($key, $array)
    {
        if (array_key_exists($key, $array)) {
            return true;
        }
        foreach ($array as $k => $value) {
            if (is_array($value) && self::wp_sdtrk_array_key_exists_recursive($key, $value)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Gets all array keys and keep its path
     *
     * @param string[] $array
     * @param string $MAXDEPTH
     * @param number $depth
     * @param array $arrayKeys
     * @return string[]
     */
    public static function wp_sdtrk_array_keys_recursive($array, $MAXDEPTH = INF, $depth = 0, $arrayKeys = array())
    {
        if ($depth < $MAXDEPTH) {
            $depth ++;
            $keys = array_keys($array);
            foreach ($keys as $key) {
                if (is_array($array[$key])) {
                    $arrayKeys[$key] = self::wp_sdtrk_array_keys_recursive($array[$key], $MAXDEPTH, $depth);
                }
            }
        }
        
        return $arrayKeys;
    }
    
    /**
     * Clear the Cache from common plugins
     */
    public static function wp_sdtrk_clear_caches(){
        
        //Wordpress
        global $wp_object_cache;
        $wp_object_cache->flush();
        
        // WP Rocket
        if ( function_exists( 'rocket_clean_domain' ) ) {
            rocket_clean_domain();
        }
    }
    
    /**
     * Send a CUrl Post
     * @param string $url
     * @param array $fields
     * @return mixed
     */
    public static function wp_sdtrk_httpPost($url, $payload)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);        
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_URL, $url);
        $response = curl_exec($curl);
        if($errno = curl_errno($curl)) {
            $error_message = curl_strerror($errno);
            self::wp_sdtrk_write_log("cURL error ({$errno}):\n {$error_message}");
        }
        curl_close($curl);
        return $response;
    }
    
    /**
     * Retrieves the clients ip
     * @return String
     */
    public static function wp_sdtrk_getClientIp(){
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }
    
    /**
     * Retrieves the current URL
     * @return String
     */
    public static function wp_sdtrk_getCurrentURL(){ 
        return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    }
    
    /**
     * Get a Get-Parameter or Cookie if no GET-Param is given
     * @param String $name
     * @param boolean $firstParty
     * @return String|boolean
     */
    public static function wp_sdtrk_getGetParamWithCookie($name, $firstParty=true){
        //Check for a get Param
        if(isset($_GET[$name])){            
            return $_GET[$name];
        }        
        $partyName = ($firstParty) ? "wpsdtrk_".$name : $name;
        if(isset($_COOKIE[$partyName])){
            return $_COOKIE[$partyName];
        }
        return "";
    }
    
    /**
     * Returns the root Domain
     * @return mixed
     */
    public static function wp_sdtrk_getRootDomain(){
        $host = strtolower(trim(self::wp_sdtrk_getCurrentURL()));
        $host = ltrim(str_replace("http://","",str_replace("https://","",$host)),"www.");
        $count = substr_count($host, '.');
        if($count === 2){
            if(strlen(explode('.', $host)[1]) > 3) $host = explode('.', $host, 2)[1];
        } else if($count > 2){
            $host = getDomainOnly(explode('.', $host, 2)[1]);
        }
        $host = explode('/',$host);
        return $host[0];
    }
    
    /**
     * Sets a Cookie
     * @param String $name
     * @param String $value
     * @param boolean $firstParty
     * @param number $validDays
     * @return boolean
     */
    public static function wp_sdtrk_setCookie($name,$value,$firstParty=true,$validDays=14){        
        $partyName = ($firstParty) ? "wpsdtrk_".$name : $name;
        $timestamp = time() + 24*60*60*$validDays;        
        return setcookie($partyName,$value,$timestamp,"",self::wp_sdtrk_getRootDomain(),false,false);
    }
    
    /**
     * Looks for a parameter list and returns the first found value
     * @param String[] $paramList
     * @return string
     */
    public static function wp_sdtrk_searchParams($paramList){
        $value ="";
        //Iterate all Params and try to get the Value
        foreach($paramList as $paramName){
            $value = self::wp_sdtrk_getGetParamWithCookie($paramName);
            if(!empty($value)){
                break;
            }
        }
        return $value;
    }
}