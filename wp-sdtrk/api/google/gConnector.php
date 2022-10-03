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

    private $currentMaxRows;

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
        $this->currentMaxRows = 0;
        $this->init();
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
            Wp_Sdtrk_Helper::wp_sdtrk_write_log($e->getMessage() . "\n", $this->debug);
            Wp_Sdtrk_Helper::wp_sdtrk_write_log("---Error authenticate client. Abort...---\n", $this->debug);
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
            Wp_Sdtrk_Helper::wp_sdtrk_write_log("---Google Access-Token found...---\n", $this->debug);
            $accessToken = json_decode($tokenContent, true);
            $client->setAccessToken($accessToken);
        }
        // If there is no previous token or it's expired.
        if ($client->isAccessTokenExpired()) {
            Wp_Sdtrk_Helper::wp_sdtrk_write_log("---Google Access-Token expired -> try refreshing...---\n", $this->debug);
            // Refresh the token if possible, else fetch a new one.
            if ($client->getRefreshToken()) {
                Wp_Sdtrk_Helper::wp_sdtrk_write_log("---Refresh Token found -> refreshing...---\n", $this->debug);
                try {
                    $result = $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                    Wp_Sdtrk_Helper::wp_sdtrk_write_log("---Refresh-Result: " . json_encode($result) . " ---\n", $this->debug);
                    $this->saveToken(json_encode($client->getAccessToken()));
                    $this->connected = true;
                } catch (Exception $e) {
                    Wp_Sdtrk_Helper::wp_sdtrk_write_log("---Exception while refreshing! Maybe your Client was deleted in Google Developer Console! -> Die()---\n", $this->debug);
                    die();
                }
            } else {
                Wp_Sdtrk_Helper::wp_sdtrk_write_log("---Refresh Token not found! Init o-Auth procedure...---\n", $this->debug);
                if (isset($_GET['code'])) {
                    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
                    $client->setAccessToken($token);
                    // store in the session also
                    $_SESSION['upload_token'] = $token;
                    // redirect back to the example
                    header('Location: ' . filter_var($redirect_uri, FILTER_SANITIZE_URL));

                    // Check to see if there was an error.
                    if (array_key_exists('error', $accessToken)) {
                        Wp_Sdtrk_Helper::wp_sdtrk_write_log("---Error refreshing Token: " . $accessToken . " ---\n", $this->debug);
                        throw new Exception(join(', ', $accessToken));
                    } else {
                        // Save the token to options.
                        $this->saveToken(json_encode($client->getAccessToken()));
                        Wp_Sdtrk_Helper::wp_sdtrk_write_log("---Wrote Token to options! ---\n", $this->debug);
                    }
                } else {
                    Wp_Sdtrk_Helper::wp_sdtrk_write_log('---GET-Parameter "code" is missing...---\n', $this->debug);
                }
                // set the access token as part of the client
                if (! empty($_SESSION['upload_token'])) {
                    $client->setAccessToken($_SESSION['upload_token']);
                    if ($client->isAccessTokenExpired()) {
                        unset($_SESSION['upload_token']);
                    }
                } else {
                    $authUrl = $client->createAuthUrl();
                    Wp_Sdtrk_Helper::wp_sdtrk_write_log('---Redirect to G-Auth-Login-Page "' . filter_var($authUrl, FILTER_SANITIZE_URL) . '"...---\n', $this->debug);
                    header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
                }
            }
        } else {
            Wp_Sdtrk_Helper::wp_sdtrk_write_log("---Google Access-Token valid -> Continue!---\n", $this->debug);
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
            Wp_Sdtrk_Helper::wp_sdtrk_write_log("---Adding following Data to Sheet from Index " . $startIndex . " (Range: " . $updateRange . ") ---\n", $this->debug);
            Wp_Sdtrk_Helper::wp_sdtrk_write_log(json_encode($tableHeader) . "\n", $this->debug);

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
                // Expand Sheet if needed
                if ($endIndex > $this->currentMaxRows) {
                    $expandingSucess = $this->updateSheetSize($endIndex);
                    if ($expandingSucess === false) {
                        return false;
                    }
                }
                $updateRange = $this->tableName . '!' . 'A' . $startIndex . ':' . 'ZZ' . $endIndex;
                Wp_Sdtrk_Helper::wp_sdtrk_write_log("---Adding following Data to Sheet from Index " . $startIndex . " (Range: " . $updateRange . "), Stack-Size is " . sizeof($stack) . "---\n", $this->debug);
                Wp_Sdtrk_Helper::wp_sdtrk_write_log(json_encode($stack) . "\n", $this->debug);

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
            Wp_Sdtrk_Helper::wp_sdtrk_write_log($e->getMessage(), $this->debug);
            return false;
        }
    }

    /**
     * Expands the size of the sheet to the given range
     *
     * @param integer $newSize
     * @return boolean
     */
    private function updateSheetSize($newSize)
    {        
        Wp_Sdtrk_Helper::wp_sdtrk_write_log("Sheet size " . $this->currentMaxRows . " is too small! Expand to " . $newSize . "...", $this->debug);
        try {
            //index starts by 1 in gsheet!
            $startRows = $this->currentMaxRows;
            if($startRows < 1){
                $startRows = 1;
            }
            if($newSize <1){
                $newSize = 1;
            }
            
            $updateRange = $this->tableName . '!' . 'A' . $startRows . ':' . 'ZZ' . $newSize;
            $valueRange = new Google_Service_Sheets_ValueRange();
            $valueRange->setValues([
                "values" => [
                    ""
                ]
            ]);
            $conf = [
                "valueInputOption" => "RAW"
            ];
            $ins = [
                "insertDataOption" => "INSERT_ROWS"
            ];
            $this->service->spreadsheets_values->append($this->sheetId, $updateRange, $valueRange, $conf, $ins);
            $this->currentMaxRows = $newSize;
            Wp_Sdtrk_Helper::wp_sdtrk_write_log("Sheet size successfully expanded!", $this->debug);
            return true;
        } catch (Exception $e) {
            Wp_Sdtrk_Helper::wp_sdtrk_write_log($e->getMessage(), $this->debug);
            return false;
        }
    }

    /**
     * Gets Data from local DB and pushes them to gSheet
     *
     * @param array $data
     */
    public function sync()
    {
        $hitContainer = new Wp_Sdtrk_hitContainer($this->debug);
        $hitContainer->addGSheetHits($this->readEntries());        
        $stacks = $hitContainer->getHitsForGsync(true);
        Wp_Sdtrk_Helper::wp_sdtrk_write_log("Collect Stacks before clearing google sheet", $this->debug);
        
        if (sizeof($stacks) > 0) {
            Wp_Sdtrk_Helper::wp_sdtrk_write_log("Clearing google sheet", $this->debug);
            $result = $this->clearSheet();
            if ($result) {
                Wp_Sdtrk_Helper::wp_sdtrk_write_log("Uploading table-header to google sheet", $this->debug);
                $result = $this->uploadTableHeader($hitContainer->getFieldNames());
            } else {
                Wp_Sdtrk_Helper::wp_sdtrk_write_log("Error while clearing google sheet", $this->debug);
            }
            if ($result) {
                Wp_Sdtrk_Helper::wp_sdtrk_write_log("Uploading entries to google sheet", $this->debug);
                $result = $this->uploadEntries($stacks);
            } else {
                Wp_Sdtrk_Helper::wp_sdtrk_write_log("Error while uploading table-header", $this->debug);
            }
            if ($result) {
                Wp_Sdtrk_Helper::wp_sdtrk_write_log("Updating local gsync state", $this->debug);
                return $hitContainer->updateGsyncStates();
            } else {
                Wp_Sdtrk_Helper::wp_sdtrk_write_log("Error while uploading entries", $this->debug);
            }
            return false;
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
        Wp_Sdtrk_Helper::wp_sdtrk_write_log("---Start readEntries() in gConnector---\n", $this->debug);
        $formattedData = array();
        try {
            $rows = $this->service->spreadsheets_values->get($this->sheetId, $this->sheetRange, [
                'majorDimension' => 'ROWS'
            ]);
        } catch (Exception $e) {
            Wp_Sdtrk_Helper::wp_sdtrk_write_log("---Token valid, but Permission denied! Is your Sheets API enabled? Visit: https://console.developers.google.com/apis/api/sheets.googleapis.com/ ---\n", $this->debug);
        }

        if (isset($rows['values'])) {
            $this->currentMaxRows = sizeof($rows['values']);
            Wp_Sdtrk_Helper::wp_sdtrk_write_log("---Found " . $this->currentMaxRows . " Rows in Sheet---\n", $this->debug);

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
            Wp_Sdtrk_Helper::wp_sdtrk_write_log("---rows[values] not found in Sheet -> The sheet seems to be empty!---\n", $this->debug);
        }
        return $formattedData;
    }

    public function getEntries()
    {
        return $this->sheetData;
    }
}