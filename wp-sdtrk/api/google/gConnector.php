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
        $this->endColumn =$options["endColumn"];
        $this->startRow =$options["startRow"];  
        $this->debug = $options["debug"];  
        $this->sheetRange = $this->tableName.'!'.$this->startColumn.$this->startRow.':'.$this->endColumn;
        $this->sheetData = [];
        $this->connected = false;
        $this->init();
    }
    
    /**
     * Prints into the wordpress debug file if debugging is enabled
     */
    private function debugLog($msg){
        if($this->debug){
            Wp_Sdtrk_Helper::wp_sdtrk_write_log($msg);
        }
    }
    
    /**
     * Get the connection-state
     * @return boolean
     */
    public function isConnected(){
        return $this->connected;
    }
    
    /**
     * Saves the token to the database
     * @param string $data
     */
    private function saveToken($data){
        update_option('wp-sdtrk-gauth-token',$data);
    }
    
    /**
     * Gets the token from the database
     * @return string
     */
    private function getToken(){
        return get_option('wp-sdtrk-gauth-token');
    }
    
    private function init(){
        require 'google-api-php-client-2.4.0/vendor/autoload.php';
        try {
            $this->service = new Google_Service_Sheets($this->getClient());
        } catch (Exception $e) {
            $this->debugLog($e->getMessage()."\n");
            $this->debugLog("---Error authenticate client. Abort...---\n");
            die("Authentication error!");
        }
    }
    
    /**
     * Returns an authorized API client.
     * @return Google_Client the authorized client object
     */
    private function getClient()
    {
        $redirect_uri = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF']."?wp-sdtrk=gauth";
        //Init Client
        $client = new Google_Client();
        $client->setApplicationName('Sheet ITNS Connection');
        $client->setAuthConfig($this->credentials);
        $client->setRedirectUri($redirect_uri);
        $client->setScopes(Google_Service_Sheets::SPREADSHEETS);
        //Allow refreshing Tokens
        $client->setAccessType('offline');
        $client->setPrompt("consent");
        $client->setIncludeGrantedScopes(true);
        
        if (isset($_REQUEST['logout'])) {
            unset($_SESSION['upload_token']);
        }       
        
        //Check if there is a token file and read it
        $tokenContent = $this->getToken();
        if ($tokenContent!==false) {
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
                try{
                    $result = $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                    $this->debugLog("---Refresh-Result: ".json_encode($result)." ---\n");
                }
                catch(Exception $e){
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
                        $this->debugLog("---Error refreshing Token: ".$accessToken." ---\n");
                        throw new Exception(join(', ', $accessToken));
                    }
                    else{
                        // Save the token to options.
                        $this->saveToken(json_encode($client->getAccessToken()));
                        $this->debugLog("---Wrote Token to options! ---\n");
                    }
                }
                else{
                    $this->debugLog('---GET-Parameter "code" is missing...---\n');
                }
                // set the access token as part of the client
                if (!empty($_SESSION['upload_token'])) {
                    $client->setAccessToken($_SESSION['upload_token']);
                    if ($client->isAccessTokenExpired()) {
                        unset($_SESSION['upload_token']);
                    }
                } else {
                    $authUrl = $client->createAuthUrl();
                    header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
                }
            }
        }
        else{
            $this->debugLog("---Google Access-Token valid -> Continue!---\n");
            $this->connected = true;
        }
        return $client;
    }
    
    public function addEntry($data){   
        $startIndex = $this->startRow + sizeof($this->sheetData);
        $updateRange = $this->tableName.'!'.$this->startColumn.$startIndex.':'.$this->endColumn;
        
        $this->debugLog("---Adding following Data to Sheet from Index ".$startIndex." (Range: ".$updateRange.") ---\n");
        $this->debugLog(json_encode($data)."\n");
        
        $updateBody = new \Google_Service_Sheets_ValueRange([
            'range' => $updateRange,
            'majorDimension' => 'ROWS',
            'values' => ['values' => $data],
        ]);
        
        $this->service->spreadsheets_values->update(
            $this->sheetId,
            $updateRange,
            $updateBody,
            ['valueInputOption' => 'USER_ENTERED']
            );       
        array_push($this->sheetData,array($data));
    }
    
    public function readEntries(){
        $this->debugLog("---Start readEntries() in gConnector---\n");
        $data = [];
        try{
            $rows = $this->service->spreadsheets_values->get($this->sheetId, $this->sheetRange, ['majorDimension' => 'ROWS']);
        }
        catch(Exception $e){
            $this->debugLog("---Token valid, but Permission denied! Is your Sheets API enabled? Visit: https://console.developers.google.com/apis/api/sheets.googleapis.com/ ---\n");
        }
        
        if (isset($rows['values'])) {
            $this->debugLog("---Found ".sizeof($rows['values'])." Rows in Sheet---\n");
            foreach ($rows['values'] as $row) {
                /*
                 * If first column is empty, consider it an empty row and skip (this is just for example)
                 */
                if (empty($row[0])) {
                    break;
                }
                
                $rowData = [];
                foreach($row as $column){
                    array_push($rowData,$column);
                }
                array_push($data,$rowData);
            }
            
            $this->sheetData = $data;
        }
        else{
            $this->debugLog("---rows[values] not found in Sheet -> The sheet seems to be empty!---\n");
        }
        
        
    }
    
    public function getEntries(){
        return $this->sheetData;
    }
}