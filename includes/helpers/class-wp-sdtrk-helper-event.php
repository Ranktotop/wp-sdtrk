<?php
// File: includes/helpers/class-wp-sdtrk-helper-event.php

class WP_SDTRK_Helper_Event
{

    /**
     * Send a CUrl Post
     *
     * @param string $url
     * @param array $fields
     * @return mixed
     */
    public static function do_post($url, $payload, $headers = array(), $debug = true)
    {
        $curlHeaders = array(
            'Content-Type:application/json'
        );
        foreach ($headers as $header) {
            array_push($curlHeaders, $header);
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HTTPHEADER, $curlHeaders);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_URL, $url);
        $response = curl_exec($curl);

        // If Curl Error
        if ($errno = curl_errno($curl)) {
            $error_message = curl_strerror($errno);
            $response = [
                'state' => false,
                'code' => $errno,
                'msg' => $error_message,
                'payload_encoded' => $payload,
                'payload_decoded' => json_decode($payload),
                'destination' => $url
            ];
            sdtrk_log('------ START CURL Error-Response: -----', 'error', !$debug);
            sdtrk_log($response, 'error', !$debug);
            sdtrk_log('--> CURL Error-Info:', 'error', !$debug);
            sdtrk_log(curl_getinfo($curl), 'error', !$debug);
            sdtrk_log('------ END CURL Error-Response: -----', 'error', !$debug);
            curl_close($curl);
            return $response;
        }

        // If response is no JSON
        $msg = json_decode($response);
        if (is_null($msg)) {
            // check if curl was successfull but didnt sent content back
            if (isset(curl_getinfo($curl)['http_code']) && substr(curl_getinfo($curl)['http_code'], 0, 1) === "2") {
                // If all is fine
                $response = [
                    'state' => true,
                    'code' => '1',
                    'msg' => curl_getinfo($curl)['http_code'],
                    'payload_encoded' => $payload,
                    'payload_decoded' => json_decode($payload),
                    'destination' => $url
                ];
                curl_close($curl);
                return $response;
            }

            $response = [
                'state' => false,
                'code' => 'json_decode of response failed, error (see msg below for raw response)',
                'msg' => $response,
                'payload_encoded' => $payload,
                'payload_decoded' => json_decode($payload),
                'destination' => $url
            ];
            sdtrk_log('------ START CURL Error-Response: -----', 'error', !$debug);
            sdtrk_log($response, 'error', !$debug);
            sdtrk_log('--> CURL Error-Info:', 'error', !$debug);
            sdtrk_log(curl_getinfo($curl), 'error', !$debug);
            sdtrk_log('------ END CURL Error-Response: -----', 'error', !$debug);
            curl_close($curl);
            return $response;
        }

        // If response is error
        if (isset($msg->error)) {
            $response = [
                'state' => false,
                'code' => $msg->error,
                'msg' => $msg,
                'payload_encoded' => $payload,
                'payload_decoded' => json_decode($payload),
                'destination' => $url
            ];
            sdtrk_log('------ START CURL Error-Response: -----', 'error', !$debug);
            sdtrk_log($response, 'error', !$debug);
            sdtrk_log('--> CURL Error-Info:', 'error', !$debug);
            sdtrk_log(curl_getinfo($curl), 'error', !$debug);
            sdtrk_log('------ END CURL Error-Response: -----', 'error', !$debug);
            curl_close($curl);
            return $response;
        }

        // If all is fine
        $response = [
            'state' => true,
            'code' => '1',
            'msg' => $msg,
            'payload_encoded' => $payload,
            'payload_decoded' => json_decode($payload),
            'destination' => $url
        ];
        curl_close($curl);
        return $response;
    }

    /**
     * Retrieves the clients ip
     *
     * @return String
     */
    public static function getClientIp()
    {
        if (! empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (! empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }

    /**
     * Retrieves the current URL
     *
     * @return String
     */
    public static function getCurrentURL($trimQuery = false)
    {
        // Sicheres Prüfen und Escapen der Server-Variablen
        $scheme = (
            (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
            (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
        ) ? 'https' : 'http';

        // Host sicher abrufen und validieren
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (
            isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost'
        );

        // REQUEST_URI sicher abrufen
        $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';

        // URL zusammenbauen
        $currentUrl = $scheme . '://' . $host . $requestUri;

        // Query-String entfernen, falls gewünscht
        if ($trimQuery) {
            $parsed = parse_url($currentUrl);
            if ($parsed !== false) {
                $currentUrl = $parsed['scheme'] . '://' . $parsed['host'];
                if (isset($parsed['port']) && $parsed['port'] != 80 && $parsed['port'] != 443) {
                    $currentUrl .= ':' . $parsed['port'];
                }
                if (isset($parsed['path'])) {
                    $currentUrl .= $parsed['path'];
                }
            }
        }

        return $currentUrl;
    }

    /**
     * Retrieves the current Referer
     *
     * @return String
     */
    public static function getCurrentReferer($trimQuery = false)
    {
        $currentUrl = (isset($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : "";
        if (! $trimQuery) {
            return $currentUrl;
        } else {
            return strstr($currentUrl, '?', true) ?: $currentUrl;
        }
    }

    /**
     * Get a Get-Parameter or Cookie if no GET-Param is given
     *
     * @param String $name
     * @param boolean $firstParty
     * @return String|boolean
     */
    public static function getGetParamWithCookie($name, $firstParty = true)
    {
        // Check for a get Param
        if (isset($_GET[$name])) {
            return $_GET[$name];
        }
        $partyName = ($firstParty) ? "wpsdtrk_" . $name : $name;
        if (isset($_COOKIE[$partyName])) {
            return $_COOKIE[$partyName];
        }
        return "";
    }

    /**
     * Returns the root Domain
     *
     * @param string|null $host Optional host string to parse, otherwise uses current URL
     * @return string
     */
    public static function getRootDomain($host = null)
    {
        // Wenn kein Host übergeben wurde, hole die aktuelle URL
        if ($host === null) {
            $host = self::getCurrentURL();
        }

        // Bereinige den Host-String
        $host = strtolower(trim($host));
        $host = strtok($host, '?'); // Query-String entfernen

        // Protokoll und www. entfernen
        $host = str_replace(['http://', 'https://', 'www.'], '', $host);

        // Nur den Host-Teil behalten (ohne Pfad)
        $host = explode('/', $host)[0];

        // Sonderfall: localhost oder IP-Adresse
        if ($host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }

        // Anzahl der Punkte zählen
        $count = substr_count($host, '.');

        if ($count === 0) {
            // Keine Domain-Struktur (z.B. nur "localhost")
            return $host;
        } elseif ($count === 1) {
            // Bereits Root-Domain (z.B. example.com)
            return $host;
        } elseif ($count === 2) {
            // Kann Subdomain oder ccTLD sein (z.B. sub.example.com oder example.co.uk)
            $parts = explode('.', $host);
            // Wenn mittlerer Teil > 3 Zeichen, ist es wahrscheinlich eine normale Domain
            if (strlen($parts[1]) > 3) {
                return $parts[1] . '.' . $parts[2];
            }
            return $host;
        } else {
            // Mehr als 2 Punkte: Entferne erste Subdomain und prüfe rekursiv
            $parts = explode('.', $host, 2);
            return self::getRootDomain($parts[1]);
        }
    }

    /**
     * Gets the default Event-Names
     *
     * @return array
     */
    public static function getDefaultEventMap()
    {
        return array(
            'Time' => 'time_spent_%',
            'Scroll' => 'scroll_depth_%',
            'Click' => 'button_click',
            'Visibility' => 'item_visit',
            'Click_Local' => 'button_click_%',
            'Visibility_Local' => 'item_visit_%'
        );
    }

    /**
     * Get the param-names-map for events
     *
     * @return string[][]
     */
    public static function getParamNames()
    {
        $params = array();
        $params['firstname'] = array(
            'buyer_first_name',
            'first_name',
            'firstname',
            'vorname',
            'license_data_first_name',
            'customer_email'
        );
        $params['lastname'] = array(
            'buyer_last_name',
            'last_name',
            'lastname',
            'nachname',
            'license_data_last_name'
        );
        $params['email'] = array(
            'buyer_email',
            'email',
            'license_data_email',
            'customer_email'
        );
        $params['value'] = array(
            'value',
            'net_amount',
            'amount'
        );
        $params['prodid'] = array(
            'prodid',
            'product_id'
        );
        $params['prodname'] = array(
            'product_name',
            'prodname'
        );
        $params['orderid'] = array(
            'order_id'
        );
        $params['affiliate'] = array(
            'affiliate_id'
        );
        $params['type'] = array(
            'type'
        );
        $params['utm'] = array(
            'utm_source',
            'utm_medium',
            'utm_term',
            'utm_content',
            'utm_campaign'
        );
        return $params;
    }
}
