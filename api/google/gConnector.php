<?php

use Google_Client;
use Google\Service\Sheets;
use Google\Service\Sheets\ClearValuesRequest;
use Google\Service\Sheets\ValueRange;

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
        try {
            $this->service = new Sheets($this->getClient());
        } catch (Exception $e) {
            sdtrk_log($e->getMessage(), "error");
            sdtrk_log("---Error authenticate client. Abort...---", "error");
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
        $client->setScopes(Sheets::SPREADSHEETS);
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
            sdtrk_log("---Google Access-Token found...---\n", "info");
            $accessToken = json_decode($tokenContent, true);
            $client->setAccessToken($accessToken);
        }
        // If there is no previous token or it's expired.
        if ($client->isAccessTokenExpired()) {
            sdtrk_log("---Google Access-Token expired -> try refreshing...---", "info");
            // Refresh the token if possible, else fetch a new one.
            if ($client->getRefreshToken()) {
                sdtrk_log("---Refresh Token found -> refreshing...---\n", "info");
                try {
                    $result = $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                    sdtrk_log("---Refresh-Result: " . json_encode($result) . " ---", "info");
                    $this->saveToken(json_encode($client->getAccessToken()));
                    $this->connected = true;
                } catch (Exception $e) {
                    sdtrk_log("---Exception while refreshing! Maybe your Client was deleted in Google Developer Console! -> Die()---", "error");
                    die();
                }
            } else {
                sdtrk_log("---Refresh Token not found! Init o-Auth procedure...---", "info");
                if (isset($_GET['code'])) {
                    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
                    $client->setAccessToken($token);
                    // store in the session also
                    $_SESSION['upload_token'] = $token;
                    // redirect back to the example
                    header('Location: ' . filter_var($redirect_uri, FILTER_SANITIZE_URL));

                    // Check to see if there was an error.
                    if (array_key_exists('error', $accessToken)) {
                        sdtrk_log("---Error refreshing Token: " . $accessToken . " ---", "error");
                        throw new Exception(join(', ', $accessToken));
                    } else {
                        // Save the token to options.
                        $this->saveToken(json_encode($client->getAccessToken()));
                        sdtrk_log("---Wrote Token to options! ---", "info");
                    }
                } else {
                    sdtrk_log('---GET-Parameter "code" is missing...---', "error");
                }
                // set the access token as part of the client
                if (! empty($_SESSION['upload_token'])) {
                    $client->setAccessToken($_SESSION['upload_token']);
                    if ($client->isAccessTokenExpired()) {
                        unset($_SESSION['upload_token']);
                    }
                } else {
                    $authUrl = $client->createAuthUrl();
                    sdtrk_log('---Redirect to G-Auth-Login-Page "' . filter_var($authUrl, FILTER_SANITIZE_URL) . '"...---', "info");
                    header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
                }
            }
        } else {
            sdtrk_log("---Google Access-Token valid -> Continue!---", "info");
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
            $clearRange = $this->tableName . '!A1:ZZ';
            $body = new ClearValuesRequest(); // PSR-4 Klasse, leer reicht

            $this->service->spreadsheets_values->clear(
                $this->sheetId,
                $clearRange,
                $body
            );
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Push table-header to google sheet
     */
    private function uploadTableHeader($tableHeader)
    {
        try {
            $skipFields = ["hitSource", "gsync"];

            // Unerwünschte Felder entfernen
            foreach ($skipFields as $field) {
                $index = array_search($field, $tableHeader, true);
                if ($index !== false) {
                    unset($tableHeader[$index]);
                }
            }

            // Index neu ordnen
            $tableHeader = array_values($tableHeader);

            // Range bauen
            $startIndex  = 1;
            $updateRange = $this->tableName . '!A' . $startIndex . ':ZZ' . $startIndex;

            // ValueRange (eine Zeile!)
            $updateBody = new ValueRange([
                'range'          => $updateRange,
                'majorDimension' => 'ROWS',
                'values'         => [$tableHeader], // << wichtig: doppelt verschachtelt
            ]);

            $this->service->spreadsheets_values->update(
                $this->sheetId,
                $updateRange,
                $updateBody,
                ['valueInputOption' => 'USER_ENTERED']
            );

            return true;
        } catch (\Exception $e) {
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
            // erste Datenzeile (1 = Header)
            $startIndex = 2;

            foreach ($stacks as $stack) {
                $rowsCount = is_countable($stack) ? count($stack) : 0;
                if ($rowsCount === 0) {
                    continue;
                }

                // inklusives Ende für die Range
                $endIndex = $startIndex + $rowsCount - 1;

                // Sheet ggf. vergrößern
                if ($endIndex > $this->currentMaxRows) {
                    if ($this->updateSheetSize($endIndex) === false) {
                        return false;
                    }
                }

                $updateRange = $this->tableName . '!A' . $startIndex . ':ZZ' . $endIndex;
                sdtrk_log("---Adding Data from row {$startIndex} (Range: {$updateRange}), Stack size: {$rowsCount} ---", "info");
                sdtrk_log(json_encode($stack) . "\n", "debug");

                // PSR-4 ValueRange
                $updateBody = new ValueRange([
                    'range'          => $updateRange,
                    'majorDimension' => 'ROWS',
                    'values'         => $stack,   // array von Zeilen: [ [..], [..], ... ]
                ]);

                $this->service->spreadsheets_values->update(
                    $this->sheetId,
                    $updateRange,
                    $updateBody,
                    ['valueInputOption' => 'USER_ENTERED']
                );

                // nächste Startzeile (Ende + 1)
                $startIndex = $endIndex + 1;
                $this->currentMaxRows = max($this->currentMaxRows, $endIndex);
            }

            return true;
        } catch (\Exception $e) {
            sdtrk_log($e->getMessage(), "error");
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
        sdtrk_log("Sheet size {$this->currentMaxRows} is too small! Expanding to {$newSize}...", "debug");

        try {
            // Index startet bei 1 in Google Sheets
            $startRows = max(1, $this->currentMaxRows);
            $newSize   = max(1, $newSize);

            $updateRange = "{$this->tableName}!A{$startRows}:ZZ{$newSize}";

            // ValueRange benötigt Array von Zeilen (jede Zeile ist ein Array)
            $valueRange = new ValueRange([
                'values' => [['']], // eine leere Zeile
            ]);

            $params = [
                'valueInputOption' => 'RAW',
                'insertDataOption' => 'INSERT_ROWS',
            ];

            $this->service->spreadsheets_values->append(
                $this->sheetId,
                $updateRange,
                $valueRange,
                $params
            );

            $this->currentMaxRows = $newSize;
            sdtrk_log("Sheet size successfully expanded!", "debug");
            return true;
        } catch (\Exception $e) {
            sdtrk_log($e->getMessage(), "error");
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
        sdtrk_log("Collect Stacks before clearing google sheet", "debug");
        $stacks = $hitContainer->getHitsForGsync(true);

        if (sizeof($stacks) > 0) {
            sdtrk_log("Clearing google sheet", "debug");
            $result = $this->clearSheet();
            if ($result) {
                sdtrk_log("Uploading table-header to google sheet", "debug");
                $result = $this->uploadTableHeader($hitContainer->getFieldNames());
            } else {
                sdtrk_log("Error while clearing google sheet", "error");
            }
            if ($result) {
                sdtrk_log("Uploading entries to google sheet", "debug");
                $result = $this->uploadEntries($stacks);
            } else {
                sdtrk_log("Error while uploading table-header", "error");
            }
            if ($result) {
                sdtrk_log("Updating local gsync state", "debug");
                return $hitContainer->updateGsyncStates();
            } else {
                sdtrk_log("Error while uploading entries", "error");
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
        sdtrk_log("---Start readEntries() in gConnector---", "debug");
        $formattedData = array();
        try {
            $rows = $this->service->spreadsheets_values->get($this->sheetId, $this->sheetRange, [
                'majorDimension' => 'ROWS'
            ]);
        } catch (Exception $e) {
            sdtrk_log("---Token valid, but Permission denied! Is your Sheets API enabled? Visit: https://console.developers.google.com/apis/api/sheets.googleapis.com/ ---", "error");
        }

        if (isset($rows['values'])) {
            $this->currentMaxRows = sizeof($rows['values']);
            sdtrk_log("---Found " . $this->currentMaxRows . " Rows in Sheet---", "debug");

            // save for later use
            $this->sheetData = $rows['values'];

            if (sizeof($rows['values']) > 1) {
                $tableHeader = $rows['values'][0];

                // Iterate all rows (first row is header)
                for ($i = 1; $i < sizeof($rows['values']); $i++) {
                    $formattedEntry = array();

                    // Iterate all columns
                    for ($j = 0; $j < sizeof($tableHeader); $j++) {
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
            sdtrk_log("---rows[values] not found in Sheet -> The sheet seems to be empty!---", "warning");
        }
        return $formattedData;
    }

    public function getEntries()
    {
        return $this->sheetData;
    }
}
