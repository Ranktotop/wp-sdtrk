<?php

class gConnector
{

    private $debug;

    private $sheetId;

    private $sheetRange;

    private $credentials;

    private $service;

    private $sheetData;

    private $startColumn;

    private $endColumn;

    private $startRow;

    private $tableName;

    private $connected;

    // Constructor
    public function __construct($options)
    {
        $this->credentials = $options["cred"];
        $this->sheetId = $options["sheetId"];
        $this->tableName = $options["tableName"];
        $this->startColumn = $options["startColumn"];
        $this->endColumn = $options["endColumn"];
        $this->startRow = $options["startRow"];
        $this->debug = $options["debug"];
        $this->sheetRange = $this->tableName . '!' . $this->startColumn . $this->startRow . ':' . $this->endColumn;
        $this->sheetData = [];
        $this->connected = false;
        $this->init();
    }

    /**
     * Prints into the wordpress debug file if debugging is enabled
     */
    private function debugLog($msg)
    {
        if ($this->debug) {
            Wp_Sdtrk_Helper::wp_sdtrk_write_log($msg);
        }
    }

    /**
     * Get the connection-state
     *
     * @return boolean
     */
    public function isConnected()
    {
        return $this->connected;
    }

    /**
     * Saves the token to the database
     *
     * @param string $data
     */
    private function saveToken($data)
    {
        update_option('wp-sdtrk-gauth-token', $data);
    }

    /**
     * Gets the token from the database
     *
     * @return string
     */
    private function getToken()
    {
        return get_option('wp-sdtrk-gauth-token');
    }

    private function init()
    {
        require 'google-api-php-client-2.4.0/vendor/autoload.php';
        try {
            $this->service = new Google_Service_Sheets($this->getClient());
        } catch (Exception $e) {
            $this->debugLog($e->getMessage() . "\n");
            $this->debugLog("---Error authenticate client. Abort...---\n");
            delete_option('wp-sdtrk-gauth-token');
            die("Authentication error!");
        }
    }

    /**
     * Returns an authorized API client.
     *
     * @return Google_Client the authorized client object
     */
    private function getClient()
    {
        $redirect_uri = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . "?wp-sdtrk=gauth";
        // Init Client
        $client = new Google_Client();
        $client->setApplicationName('Sheet ITNS Connection');
        $client->setAuthConfig($this->credentials);
        $client->setRedirectUri($redirect_uri);
        $client->setScopes(Google_Service_Sheets::SPREADSHEETS);
        // Allow refreshing Tokens
        $client->setAccessType('offline');
        $client->setPrompt("consent");
        $client->setIncludeGrantedScopes(true);

        if (isset($_REQUEST['logout'])) {
            unset($_SESSION['upload_token']);
        }

        // Check if there is a token file and read it
        $tokenContent = $this->getToken();
        if ($tokenContent !== false) {
            $this->debugLog("---Google Access-Token found...---\n");
            $accessToken = json_decode($tokenContent, true);
            $client->setAccessToken($accessToken);
        }
        // If there is no previous token or it's expired.
        if ($client->isAccessTokenExpired()) {
            $this->debugLog("---Google Access-Token expired -> try refreshing...---\n");
            // Refresh the token if possible, else fetch a new one.
            if ($client->getRefreshToken()) {
                $this->debugLog("---Refresh Token found -> refreshing...---\n");
                try {
                    $result = $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                    $this->debugLog("---Refresh-Result: " . json_encode($result) . " ---\n");
                    $this->saveToken(json_encode($client->getAccessToken()));
                    $this->connected = true;
                } catch (Exception $e) {
                    $this->debugLog("---Exception while refreshing! Maybe your Client was deleted in Google Developer Console! -> Die()---\n");
                    die();
                }
            } else {
                $this->debugLog("---Refresh Token not found! Init o-Auth procedure...---\n");
                if (isset($_GET['code'])) {
                    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
                    $client->setAccessToken($token);
                    // store in the session also
                    $_SESSION['upload_token'] = $token;
                    // redirect back to the example
                    header('Location: ' . filter_var($redirect_uri, FILTER_SANITIZE_URL));

                    // Check to see if there was an error.
                    if (array_key_exists('error', $accessToken)) {
                        $this->debugLog("---Error refreshing Token: " . $accessToken . " ---\n");
                        throw new Exception(join(', ', $accessToken));
                    } else {
                        // Save the token to options.
                        $this->saveToken(json_encode($client->getAccessToken()));
                        $this->debugLog("---Wrote Token to options! ---\n");
                    }
                } else {
                    $this->debugLog('---GET-Parameter "code" is missing...---\n');
                }
                // set the access token as part of the client
                if (! empty($_SESSION['upload_token'])) {
                    $client->setAccessToken($_SESSION['upload_token']);
                    if ($client->isAccessTokenExpired()) {
                        unset($_SESSION['upload_token']);
                    }
                } else {                    
                    $authUrl = $client->createAuthUrl();
                    $this->debugLog('---Redirect to G-Auth-Login-Page "'.filter_var($authUrl, FILTER_SANITIZE_URL).'"...---\n');
                    header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
                }
            }
        } else {
            $this->debugLog("---Google Access-Token valid -> Continue!---\n");
            $this->connected = true;
        }
        return $client;
    }

    /**
     * Cleans the whole sheet
     */
    private function clearSheet()
    {
        try {
            $clearRange = $this->tableName . '!' . 'A' . 1 . ':' . 'ZZ';
            $clearBody = new \Google_Service_Sheets_ClearValuesRequest([
                'range' => $clearRange
            ]);
            $this->service->spreadsheets_values->clear($this->sheetId, $clearRange, $clearBody);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Push table-header to google sheet
     */
    private function uploadTableHeader($tableHeader)
    {
        try {
            $skipFields = array(
                "hitSource",
                "gsync"
            );

            // Delete unwanted fields
            foreach ($skipFields as $field) {
                $index = array_search($field, $tableHeader);
                if ($index) {
                    unset($tableHeader[$index]);
                }
            }

            // reorder index
            $tableHeader = array_values($tableHeader);

            // write table-header
            $startIndex = 1;
            $updateRange = $this->tableName . '!' . 'A' . $startIndex . ':' . 'ZZ' . $startIndex;
            $this->debugLog("---Adding following Data to Sheet from Index " . $startIndex . " (Range: " . $updateRange . ") ---\n");
            $this->debugLog(json_encode($tableHeader) . "\n");

            $updateBody = new \Google_Service_Sheets_ValueRange([
                'range' => $updateRange,
                'majorDimension' => 'ROWS',
                'values' => [
                    'values' => $tableHeader
                ]
            ]);

            $this->service->spreadsheets_values->update($this->sheetId, $updateRange, $updateBody, [
                'valueInputOption' => 'USER_ENTERED'
            ]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Push stacks to google sheet
     *
     * @param array[] $stacks
     */
    private function uploadEntries($stacks)
    {
        try {
            // index 0 is the table header
            $startIndex = 2;

            foreach ($stacks as $stack) {
                $endIndex = $startIndex + sizeof($stack);
                $updateRange = $this->tableName . '!' . 'A' . $startIndex . ':' . 'ZZ' . $endIndex;
                $this->debugLog("---Adding following Data to Sheet from Index " . $startIndex . " (Range: " . $updateRange . "), Stack-Size is ".sizeof($stack)."---\n");
                $this->debugLog(json_encode($stack) . "\n");

                $updateBody = new \Google_Service_Sheets_ValueRange([
                    'range' => $updateRange,
                    'majorDimension' => 'ROWS',
                    'values' => $stack
                ]);

                $this->service->spreadsheets_values->update($this->sheetId, $updateRange, $updateBody, [
                    'valueInputOption' => 'USER_ENTERED'
                ]);
                $startIndex = $endIndex;
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Converts Stacks to row-elements
     *
     * @param array[] $stacks
     */
    private function convertStacksToRows($stacks, $fieldnames)
    {
        $skipFields = array(
            "hitSource",
            "gsync"
        );

        $rowStacks = array();
        foreach ($stacks as $stack) {
            $rowStack = array();
            foreach ($stack as $hit) {
                $row = array();
                for ($i = 0; $i < sizeof($fieldnames); $i ++) {
                    // Skip unwanted fields
                    if (in_array($fieldnames[$i], $skipFields)) {
                        continue;
                    }

                    if (isset($hit[$fieldnames[$i]])) {

                        $value = $hit[$fieldnames[$i]];
                        // convert time
                        if ($fieldnames[$i] === "date") {
                            $value = date("d.m.Y H:i:s", $value);
                        }
                        array_push($row, $value);
                    } else {
                        array_push($row, "");
                    }
                }
                array_push($rowStack, $row);
            }
            array_push($rowStacks, $rowStack);
        }
        return $rowStacks;
    }

    /**
     * Gets Data from local DB and pushes them to gSheet
     *
     * @param array $data
     */
    public function pushLocalData($data)
    {
        // How many rows shall be submitted with every request (0 = all)
        $stackSize = 500;
        if (sizeof($data) > 0) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'hitContainer.php';
            $container = new Wp_Sdtrk_hitContainer();
            $container->addLocalHits($data);
            $container->addSheetHits($this->readEntries());
            $stacks = $this->convertStacksToRows($container->getHits($stackSize), $container->getFieldNames());

            if (sizeof($stacks) > 0) {
                $this->debugLog("Clearing google sheet");
                $result = $this->clearSheet();
                if ($result) {
                    $this->debugLog("Uploading table-header to google sheet");
                    $result = $this->uploadTableHeader($container->getFieldNames());
                }
                else{
                    $this->debugLog("Error while clearing google sheet");
                }
                if ($result) {
                    $this->debugLog("Uploading entries to google sheet");
                    $result = $this->uploadEntries($stacks);
                }
                else{
                    $this->debugLog("Error while uploading table-header");
                }
                if ($result) {
                    $this->debugLog("Updating local gsync state");
                    return $container->updateLocalStates();
                }
                else{
                    $this->debugLog("Error while uploading entries");
                }
                
                return false;
            }
        }
    }

    /**
     * Read entries from google sheet
     *
     * @param boolean $raw
     * @return array
     */
    public function readEntries()
    {
        $this->debugLog("---Start readEntries() in gConnector---\n");
        $formattedData = array();
        try {
            $rows = $this->service->spreadsheets_values->get($this->sheetId, $this->sheetRange, [
                'majorDimension' => 'ROWS'
            ]);
        } catch (Exception $e) {
            $this->debugLog("---Token valid, but Permission denied! Is your Sheets API enabled? Visit: https://console.developers.google.com/apis/api/sheets.googleapis.com/ ---\n");
        }

        if (isset($rows['values'])) {
            $this->debugLog("---Found " . sizeof($rows['values']) . " Rows in Sheet---\n");

            // save for later use
            $this->sheetData = $rows['values'];

            if (sizeof($rows['values']) > 1) {
                $tableHeader = $rows['values'][0];

                // Iterate all rows (first row is header)
                for ($i = 1; $i < sizeof($rows['values']); $i ++) {
                    $formattedEntry = array();

                    // Iterate all columns
                    for ($j = 0; $j < sizeof($tableHeader); $j ++) {
                        $fieldName = $tableHeader[$j];
                        if (empty($fieldName)) {
                            continue;
                        }
                        if (isset($rows['values'][$i][$j])) {
                            $fieldVal = $rows['values'][$i][$j];
                        } else {
                            $fieldVal = "";
                        }
                        $formattedEntry[$fieldName] = $fieldVal;
                    }
                    array_push($formattedData, $formattedEntry);
                }
            }
        } else {
            $this->debugLog("---rows[values] not found in Sheet -> The sheet seems to be empty!---\n");
        }
        return $formattedData;
    }

    public function getEntries()
    {
        return $this->sheetData;
    }
}