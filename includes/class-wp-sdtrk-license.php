<?php

/**
 * License Functions
 *
 * @link       https://www.rank-to-top.de
 * @since      1.0.0
 *
 */
class Wp_Sdtrk_License
{

    /**
     * Call the license-Server
     *
     * @param string $licenseKey
     * @param string $lookupReference
     * @param string $action
     * @return boolean
     */
    public static function wp_sdtrk_license_call($licenseKey = "", $lookupReference = "", $action = "")
    {

        // If there is no reference or key given
        if (empty($lookupReference) || empty($licenseKey) || empty($action)) {
            return false;
        }

        $license_data = self::wp_sdtrk_doRequest($action, $licenseKey, $lookupReference);

        // If there is an error in the Connection and the autorelease isnt set
        if (! $license_data && strcmp($action, "autorelease") !== 0) {
            $code = '-1';
            self::saveLicenseData($lookupReference, $code, "error", $action);
            return false;
        }

        $license_data["lookupReference"] = $lookupReference;
        $license_data["action"] = $action;
        $license_data["license_key"] = $licenseKey;

        switch ($action) {
            case 'activate':
                return self::wp_sdtrk_license_register($license_data);
                break;
            case 'deactivate':
                return self::wp_sdtrk_license_deregister($license_data);
                break;
            case 'check':
                $result = self::wp_sdtrk_license_check($license_data);
                self::wp_sdtrk_license_auto_deregister($lookupReference);
                return $result;
                break;
            default:
                return false;
        }
    }

    /**
     * Activate the license
     *
     * @param array $license_data
     * @return boolean
     */
    private static function wp_sdtrk_license_register($license_data)
    {
        $code = (! isset($license_data['error_code'])) ? "200" : $license_data['error_code'];
        self::saveLicenseData($license_data['lookupReference'], $code, $license_data['result'], $license_data['action']);
        //Clear Cache
        Wp_Sdtrk_Helper::wp_sdtrk_clear_caches();
        return false;
    }

    /**
     * Deactivate the license
     *
     * @param array $license_data
     * @return boolean
     */
    private static function wp_sdtrk_license_deregister($license_data)
    {
        $code = (! isset($license_data['error_code'])) ? "220" : $license_data['error_code'];
        self::saveLicenseData($license_data['lookupReference'], $code, $license_data['result'], $license_data['action']);
        //Clear Cache
        Wp_Sdtrk_Helper::wp_sdtrk_clear_caches();
        return false;
    }

    /**
     * Handles the automatic release on specific codes
     *
     * @param array $license_data
     */
    public static function wp_sdtrk_license_auto_deregister($lookupReference, $options = false)
    {
        $saveToDB = false;

        if (! $options) {
            $options = get_option("wp-sdtrk", false);
            $saveToDB = true;
        }

        $releaseCodes = self::getAutoReleaseCodes();
        $currentData = self::getLicenseData($lookupReference);
        $autoRelease = in_array($currentData['code'], $releaseCodes);

        // If Code is assigned to autorelease and options are found
        if ($autoRelease && $options) {
            $options = Wp_Sdtrk_Helper::wp_sdtrk_replaceValueForKey_recursive($options, 'licensekey_activate', 'no');

            if ($saveToDB) {
                Wp_Sdtrk_Helper::wp_sdtrk_insertOrUpdateOption("wp-sdtrk", $options);
                //Clear Cache
                Wp_Sdtrk_Helper::wp_sdtrk_clear_caches();
            }
        }
        return $options;
    }

    /**
     * Check the license
     *
     * @param array $license_data
     * @return boolean
     */
    private static function wp_sdtrk_license_check($license_data)
    {
        $assignedReferences = (isset($license_data['product_ref'])) ? explode(",", $license_data['product_ref']) : array();
        $domains = (isset($license_data['registered_domains'])) ? $license_data['registered_domains'] : array();
        $lookupReference = $license_data['lookupReference'];
        $state = (isset($license_data['status']) && ! empty($license_data['status'])) ? $license_data['status'] : 'unknown';

        // If there are no domains assigned to the license then 70
        if (empty($domains)) {
            $code = '70';
            self::saveLicenseData($license_data['lookupReference'], $code, $license_data['result'], $license_data['action']);
            return false;
        }

        // If the license isnt active get the code and save it
        if ((strcmp($state, "active") != 0)) {
            $code = self::getInvalidStates()[$state];
            self::saveLicenseData($license_data['lookupReference'], $code, $license_data['result'], $license_data['action']);
            return false;
        }

        // Otherwise continue
        $domMatch = false;
        $referenceMatch_license = false;
        $referenceMatch_domain = false;

        // Check all assigned domains
        foreach ($domains as $dom) {

            $domMatch = strcmp($dom['registered_domain'], $_SERVER['SERVER_NAME']) == 0;
            $referenceMatch_license = in_array($lookupReference, $assignedReferences);
            $referenceMatch_domain = strcmp($dom['item_reference'], $lookupReference) == 0;

            // If the current domain and reference are assigned to the license and the current domain is registered with the reference and state is active
            if ($domMatch && $referenceMatch_license && $referenceMatch_domain) {
                $currentlyActive = self::wp_sdtrk_isActiveLicense($license_data['lookupReference']);
                $code = '210';
                self::saveLicenseData($license_data['lookupReference'], $code, $license_data['result'], $license_data['action']);
                // Clear Cache if the license wasnt already active
                if (! $currentlyActive) {
                    Wp_Sdtrk_Helper::wp_sdtrk_clear_caches();
                }
                return true;
            }
        }
        // If nothing maches so far, then return the reason
        $code = (((! $domMatch) ? "150" : (! $referenceMatch_license)) ? "140" : (! $referenceMatch_domain)) ? "130" : "unknown";
        self::saveLicenseData($license_data['lookupReference'], $code, $license_data['result'], $license_data['action']);
        return false;
    }

    /**
     * Function for doing a request to the license-Server
     *
     * @param String $action
     * @param String $key
     * @param String $ref
     * @return boolean|mixed
     */
    private static function wp_sdtrk_doRequest($action, $key, $ref)
    {
        $api_params = array(
            'slm_action' => 'slm_' . $action,
            'secret_key' => WP_SDTRK_LICENSE_SECRET,
            'license_key' => $key,
            'registered_domain' => $_SERVER['SERVER_NAME'],
            'item_reference' => urlencode($ref)
        );
        // Send query to the license manager server
        $query = esc_url_raw(add_query_arg($api_params, WP_SDTRK_LICENSE_SERVER));

        // Process Response
        $response = wp_remote_get($query, array(
            'timeout' => 20,
            'sslverify' => true
        ));

        // Convert response to associative array
        $jsonResponse = json_decode(wp_remote_retrieve_body($response), true);

        // If there is an error in the Connection
        if (is_wp_error($response) || empty($jsonResponse)) {
            return false;
        }

        return $jsonResponse;
    }

    /**
     * Save the license Data to WP
     *
     * @param String $reference
     * @param String $code
     * @param String $result
     * @param String $action
     */
    private static function saveLicenseData($reference, $code, $result, $action)
    {
        Wp_Sdtrk_Helper::wp_sdtrk_insertOrUpdateOption('wp-sdtrk-license-code_' . $reference, $code);
        Wp_Sdtrk_Helper::wp_sdtrk_insertOrUpdateOption('wp-sdtrk-license-result_' . $reference, $result);
        Wp_Sdtrk_Helper::wp_sdtrk_insertOrUpdateOption('wp-sdtrk-license-action_' . $reference, $action);
    }

    /**
     *
     * @param String $reference
     * @return array[]
     */
    private static function getLicenseData($reference)
    {
        return array(
            'code' => get_option('wp-sdtrk-license-code_' . $reference, 'unknown'),
            'result' => get_option('wp-sdtrk-license-result_' . $reference, 'unknown'),
            'action' => get_option('wp-sdtrk-license-action_' . $reference, 'unknown')
        );
    }

    /**
     * Check license based on saved Data
     *
     * @param String $lookupReference
     * @return boolean
     */
    public static function wp_sdtrk_isActiveLicense($lookupReference)
    {
        $licenseData = self::getLicenseData($lookupReference);
        return in_array($licenseData['code'], self::getActiveCodes());
    }

    /**
     * Returns list of Codes which are valid
     *
     * @return string[]
     */
    private static function getActiveCodes()
    {
        $activeCodes = [
            "200",
            "210"
        ];
        return $activeCodes;
    }

    /**
     * Returns the code for the message from license-Server
     *
     * @return string[]
     */
    private static function getInvalidStates()
    {
        $invalidStates = [
            "blocked" => '20',
            "expired" => '30',
            "pending" => '120',
            "unknown" => '-2'
        ];
        return $invalidStates;
    }

    /**
     * Returns the codes for which auto-release should be enabled
     *
     * @return string[]
     */
    private static function getAutoReleaseCodes()
    {
        $releaseCodes = [
            "60",
            "50",
            "70",
            "80",
            "90",
            "120",
            "130",
            "140",
            "150"
        ];
        return $releaseCodes;
    }

    /**
     * Get the user-message for license state
     *
     * @param string $lookupReference
     * @return string[]
     */
    public static function wp_sdtrk_getLicenseStateMessage($lookupReference)
    {
        $licenseData = self::getLicenseData($lookupReference);
        return array(
            "state" => self::wp_sdtrk_getMessage($licenseData['code']),
            "type" => (in_array($licenseData['code'], self::getActiveCodes())) ? "success" : "danger"
        );
    }

    /**
     * Returns Exopite Pro-Only-Message as Fieldset
     *
     * @return string[]
     */
    public static function wp_sdtrk_showProOnlyNotice($source, $lookupReference)
    {
        if (! self::wp_sdtrk_isActiveLicense($lookupReference)) {

            $newArray = array();

            array_push($newArray, array(
                'type' => 'notice',
                'class' => 'info',
                'content' => '<b>' . __('This feature is available in the Pro-Version only!', 'wp_sdtrk') . ' </b>'
            ));

            foreach ($source as $item) {
                array_push($newArray, $item);
            }

            return $newArray;
        }

        return $source;
    }

    /**
     * Show simple text, if license is valid
     *
     * @param string $lookupReference
     * @return string
     */
    public static function wp_sdtrk_showProOnlyText($lookupReference)
    {
        if (! self::wp_sdtrk_isActiveLicense($lookupReference)) {
            return ' <b style="color:#0c5460; background-color:#d1ecf1;padding:0px 5px 0px 5px">(' . __('Pro Version only!', 'wp_sdtrk') . ')</b>';
        }
        return "";
    }

    /**
     * Interprets the state-code and returns a readable message
     *
     * @param string $index
     * @return string
     */
    private static function wp_sdtrk_getMessage($index)
    {
        $messages = array(
            "-2" => __('Something went from with the license-Server. Please contact support!', 'wp_sdtrk'),
            "-1" => __('Unable to connect to license-server. Please try again later!', 'wp_sdtrk'),
            "20" => __('Your license is blocked', 'wp_sdtrk'),
            "30" => __('Your license is expired', 'wp_sdtrk'),
            "50" => __('You reached the maximum Number of registered Domains', 'wp_sdtrk'),
            "60" => __('Your license-Key is invalid', 'wp_sdtrk'), // Deactivate License Switch
            "70" => __('Domain is not registered', 'wp_sdtrk'), // Deactivate License Switch
            "80" => __('This Domain has already been released', 'wp_sdtrk'), // Deactivate License Switch
            "90" => __('Your license key could not be verified', 'wp_sdtrk'),
            "110" => __('Your license is already activated for this domain. Please release first', 'wp_sdtrk'),
            "120" => __('License is not registered. Please try to register again', 'wp_sdtrk'), // Deactivate License Switch
            "130" => __('This product is not registered for this domain', 'wp_sdtrk'), // Deactivate License Switch
            "140" => __('Your license is invalid for this product', 'wp_sdtrk'), // Deactivate License Switch
            "150" => __('This domain is not registered', 'wp_sdtrk'), // Deactivate License Switch
            "200" => __('License successfully registered', 'wp_sdtrk'),
            "210" => __('License is valid and registered', 'wp_sdtrk'),
            "220" => __('License successfully released', 'wp_sdtrk')
        );
        return (isset($messages[$index])) ? $messages[$index] : __('unknown', 'wp_sdtrk');
    }
}