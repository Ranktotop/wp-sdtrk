<?php
require_once ('wp-load.php');
//Redirect non logged-in users
if ( !is_user_logged_in() || !current_user_can( 'manage_options' )) {
    wp_redirect( home_url('login') );
    exit;
}
class Wp_Sdtrk_GAuthenticator
{
    // Constructor
    public function __construct()
    {
        $this->init();
    }
    
    private function init(){        
        require_once ('google/gConnector.php');
    }

    public function authenticate()
    {
        $options = $this->getOptions();
        if($options !== false){
            
            //Delete Token if requested
            if(isset($_GET['reauthenticate']) && $_GET['reauthenticate'] === "1"){
                delete_option('wp-sdtrk-gauth-token');
            }
            
            $gConnector = new gConnector($options);
            if($gConnector->isConnected()){
                echo $this->getHeaders();
                echo '<h2>'.__('Authentication was successfull!', 'wp-sdtrk').'</h2>';
                echo '<a class="btn" href="'.'https://' . $_SERVER['HTTP_HOST'] . '/wp-admin/options-general.php?page=wp-sdtrk'.'">'.__('Go back to settings', 'wp-sdtrk').'</a>';
            }
        }
    }
    
    private function getOptions(){
        $cred = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "local_trk_server_gsync_cred");
        $sheetId = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "local_trk_server_gsync_sheetId");
        $tableName = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "local_trk_server_gsync_tableName");
        $debug = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "local_trk_server_debug"), "yes") == 0) ? true : false;
        if($cred && $sheetId && $tableName){
            return array(
                "cred" => $cred, 
                "sheetId"=>$sheetId,
                "tableName"=>$tableName,
                "startColumn" => "A",
                "endColumn" => "Z",
                "startRow" => "1",
                "debug" => $debug
            );
        }
        return false;
    }
    
    /**
     * Checks if sync is active in options
     * @return boolean
     */
    private function syncActive(){
        $trkServer = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "local_trk_server"), "yes") == 0) ? true : false;
        $syncGsheet = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "local_trk_server_gsync"), "yes") == 0) ? true : false;
        if($trkServer && $syncGsheet){
            return true;
        }
        return false;
    }
    
    /**
     * Prints the header styles
     */
    private function getHeaders(){
        return '<html><head><meta charset="utf-8"><style>
        a.btn{
            margin: 0;
            background: #80a9d4;
            border: 1px solid #80a9d4;
            border-radius: 0;
            height: 31px;
            line-height: 29px;
            box-shadow: none;
            text-shadow: none;
            vertical-align: baseline;
            padding: 0 10px;
            color: #fff;
            text-decoration: none;
            display: inline-block;
            white-space: nowrap;
            box-sizing: border-box;
        }
        </style></head>';
    }
}


$gAuth = new Wp_Sdtrk_GAuthenticator();
$gAuth->authenticate();
