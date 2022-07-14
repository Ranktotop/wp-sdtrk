<?php

class Wp_Sdtrk_Decrypter_ds24
{

    private $key;

    private $prefix;

    private $validationLength;
    
    private $decryptedData;
    
    private $encryptedData;

    /**
     * Constructor
     * @param string $key
     * @param array $encryptedData
     */
    public function __construct($key, $encryptedData)
    {
        //The prefix > ds24 < 3c856b5c32076e39e8c0c4dce0bd9b89-Y0RSWEhwVXZTcGZhWHY2V2dLdjhUZz09
        $this->prefix = "ds24";
        //The numbers for validation after prefix ds24 > 3c856 < b5c32076e39e8c0c4dce0bd9b89-Y0RSWEhwVXZTcGZhWHY2V2dLdjhUZz09
        $this->validationLength = 5;
        $this->key = $key;
        $this->encryptedData = $encryptedData;
        $this->decryptedData = array();
        //Decrypt Data
        $this->decrypt_all();
    }
    
    /**
     * ****************************************************
     * *****************Validation*************************
     * ****************************************************
     */
    
    /**
     * Calculate a new SHA String from given parameters
     * @param string $sha_passphrase the secret key
     * @param array $parameters the GET Parameters
     * @param boolean $convert_keys_to_uppercase
     * @return string
     */
    private function calculate_sha($sha_passphrase, $parameters, $convert_keys_to_uppercase = false)
    {
        $algorythm = 'sha512';
        $sort_case_sensitive = true;
        
        if (! $sha_passphrase) {
            return 'no_signature_passphrase_provided';
        }
        
        unset($parameters['sha_sign']);
        unset($parameters['SHASIGN']);
        
        if ($convert_keys_to_uppercase) {
            $sort_case_sensitive = false;
        }
        
        $keys = array_keys($parameters);
        $keys_to_sort = array();
        foreach ($keys as $key) {
            $keys_to_sort[] = $sort_case_sensitive ? $key : strtoupper($key);
        }
        
        array_multisort($keys_to_sort, SORT_STRING, $keys);
        
        $sha_string = "";
        foreach ($keys as $key) {
            $value = $parameters[$key];
            
            $is_empty = ! isset($value) || $value === "" || $value === false;
            if ($is_empty) {
                continue;
            }
            
            $upperkey = $convert_keys_to_uppercase ? strtoupper($key) : $key;
            
            $sha_string .= "$upperkey=$value$sha_passphrase";
        }
        
        $sha_sign = strtoupper(hash($algorythm, $sha_string));
        
        return $sha_sign;
    }    

    /**
     * Checks if a new calculated sha_sign matches the given one
     * @return boolean
     */
    private function validate()
    {
        if(!isset($this->encryptedData["sha_sign"])){
            return false;
        }
        $expected_sha = $this->calculate_sha($this->key, $this->encryptedData);
        return $expected_sha == $this->encryptedData["sha_sign"];
    }
    
    /**
     * ****************************************************
     * *****************Encryption*************************
     * ****************************************************
     */    
    
    /**
     * Encrypt an array of parameters
     * @param string $secret_key the saved key in backend
     * @param array $array the GET-Parameters
     * @param string $keys_to_encrypt
     * @param array $keys_to_not_encrypt
     * @return array
     */
    private function encrypt_all($secret_key, $array, $keys_to_encrypt = 'all', $keys_to_not_encrypt = [])
    {
        $encryptedData = array();
        //Iterate parameters and encrypt
        foreach ($array as $key => $value) {
            if (in_array($key, $keys_to_not_encrypt)) {
                $encryptedData[$key] = $value;
                continue;
            }
            
            $must_encrypt = $keys_to_encrypt === 'all' || in_array($key, $keys_to_encrypt);
            if ($must_encrypt) {
                $value = $this->encrypt_and_check_string($secret_key, $value);
                $encryptedData[$key] = $value;
            }
        }
        return $encryptedData;
    }
    
    /**
     * Encrypt an decrypted value and check if it is valid
     * @param string $secret_key the secret key
     * @param string $plaintext
     * @return string
     */
    private function encrypt_and_check_string($secret_key, $plaintext)
    {
        if (empty($secret_key)) {
            $secret_key = $this->prefix;
        }
        
        $len = $this->validationLength;
        
        $validation_prefix = $secret_key ? mb_substr($secret_key, 0, $len) : '';
        
        return $this->prefix . $this->encrypt_string($secret_key, $validation_prefix . $plaintext);
    }
    

    /**
     * Encrypt a single decrypted string
     * @param string $secret_key
     * @param string $plain_text
     * @return string
     */
    private function encrypt_string($secret_key, $plain_text)
    {
        if (empty($secret_key)) {
            $secret_key = $this->prefix;
        }

        $encrypt_method = "AES-256-CBC";

        $key = hash('sha256', $secret_key);

        $iv = random_bytes(16);

        $output = openssl_encrypt($plain_text, $encrypt_method, $key, 0, $iv);
        $output = base64_encode($output);

        $output = str_replace(array(
            '=',
            '+'
        ), array(
            '_e',
            '_p'
        ), $output);

        $output = bin2hex($iv) . '-' . $output;

        return $output;
    }
    
    /**
     * ****************************************************
     * *****************Decryption*************************
     * ****************************************************
     */  

    /**
     * Decrypt an array of parameters
     * @param string $secret_key the saved key in backend
     * @param array $array the GET-Parameters
     * @return array
     */
    private function decrypt_all()
    {   
        //TODO implement this to work with custom parameters
        //Check for signed Data
        //if(!$this->validate()){
        //return $this->encryptedData;
        //}
        
        //Iterate parameters and decrypt
        foreach ($this->encryptedData as $key => $value) {
            $value = $this->decrypt_and_check_string($this->key, $value);
            $this->decryptedData[$key] = $value;
        }
    }    

    /**
     * Decrypt an encrypted value and check if it is valid
     * @param string $secret_key the secret key
     * @param string $enrypted_string
     * @return string|boolean returns encrypted string if invalid
     */
    private function decrypt_and_check_string($secret_key, $enrypted_string)
    {
        $secret_key = $this->key;
        //If no string is given
        if (! $enrypted_string) {
            return $enrypted_string;
        }

        //Check if string is encrypted
        $is_encrypted = Wp_Sdtrk_Helper::wp_sdtrk_strStartsWith($enrypted_string, $this->prefix);
        if (! $is_encrypted) {
            return $enrypted_string;
        }

        //Check for valid secret key
        if (empty($secret_key)) {
            $secret_key = $this->prefix;
        }

        //Get base string without prefix
        $baseString = mb_substr($enrypted_string, strlen($this->prefix));

        //Decrypt string
        $decryptedString = $this->decrypt_string($secret_key, $baseString);
        if (! $decryptedString) {
            return false;
        }
        
        //Get the prefix with validation numbers included
        $len = $this->validationLength;
        $validation_prefix = $secret_key ? mb_substr($secret_key, 0, $len) : '';
        
        //Check if decrypted string starts with prefix
        $is_valid = $secret_key ? Wp_Sdtrk_Helper::wp_sdtrk_strStartsWith($decryptedString, $validation_prefix) : true;

        //Return the base-string without prefix if its valid
        return $is_valid ? mb_substr($decryptedString, mb_strlen($validation_prefix)) : false;
    }
    
    /**
     * Decrypt a single encrypted string
     * @param string $secret_key
     * @param string $encrypted_string
     * @return string
     */
    private function decrypt_string($secret_key, $encrypted_string)
    {
        if (empty($secret_key)) {
            $secret_key = $this->prefix;
        }
        
        $encrypt_method = "AES-256-CBC";
        
        $secret_iv = $secret_key;
        $key = hash('sha256', $secret_key);
        
        $encrypted_string = str_replace(array(
            '_e',
            '_p'
        ), array(
            '=',
            '+'
        ), $encrypted_string);
        
        $is_iv_appended = strlen($encrypted_string) > 33 && $encrypted_string[32] === '-';
        
        if ($is_iv_appended) {
            $iv = @hex2bin(substr($encrypted_string, 0, 32));
            $encrypted_string = substr($encrypted_string, 33);
            
            if (empty($iv)) {
                return $encrypted_string;
            }
        } else {
            $iv = substr(hash('sha256', $secret_iv), 0, 16);
        }
        
        $plain_text = openssl_decrypt(base64_decode($encrypted_string), $encrypt_method, $key, 0, $iv);
        
        return $plain_text;
    }
    
    /**
     * ****************************************************
     * **************Access Functions**********************
     * ****************************************************
     */  

    /**
     * Decrypt given data
     * @param array $data the GET-Parameters
     */
    public function getDecryptedData()
    {
        return $this->decryptedData;
    }
}