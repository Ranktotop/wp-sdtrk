<?php
require_once ('wp-load.php');

class Wp_Sdtrk_Hitfeed
{

    private $feed_enabled;

    private $trk_enabled;

    private $debug;

    private $secret;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->init();
    }

    /**
     * Inits the Hitfeed
     */
    private function init()
    {
        $this->trk_enabled = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "local_trk_server"), "yes") == 0) ? true : false;
        $this->feed_enabled = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "local_trk_server_xml"), "yes") == 0) ? true : false;
        $this->debug = (strcmp(Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "local_trk_server_debug"), "yes") == 0) ? true : false;
        $this->secret = Wp_Sdtrk_Helper::wp_sdtrk_recursiveFind(get_option("wp-sdtrk", false), "local_trk_server_xml_secret");
        if (empty($this->secret)) {
            $this->secret = false;
        }
    }

    /**
     * Checks if activated and authenticated
     *
     * @return boolean
     */
    private function isEnabled()
    {
        if ($this->trk_enabled && $this->feed_enabled && $this->isAuthenticated()) {
            return true;
        }
        return false;
    }

    /**
     * Checks if secret is set and if its valid
     *
     * @return boolean
     */
    private function isAuthenticated()
    {
        if ($this->secret === false) {
            return true;
        }
        if (isset($_GET['key']) && $_GET['key'] === $this->secret) {
            return true;
        }
        return false;
    }

    public function print()
    {
        $mode = 'csv';
        if ($this->isEnabled()) {
            $hitContainer = new Wp_Sdtrk_hitContainer($this->debug);
            if ($mode === 'xml') {
                header('Content-type: text/xml; charset=utf-8');
                header('Pragma: public');
                header('Cache-control: private');
                header('Expires: -1');                
                $hits = $hitContainer->getHitsForCSV();
                $header = $hitContainer->getFieldNames();
                $this->print_XML($hits, $header);
            }
            if ($mode === 'csv') {
                $hits = $hitContainer->getHitsForCSV();
                $header = array_keys($hits[0]);
                sort($header);
                $this->print_CSV($hits, $header);
            }
        }
    }

    /**
     * $array, $filename = "export.csv", $delimiter = ";"
     */
    public function print_CSV($hits,$header)
    {
        $delimiter = ',';
        $filename = 'localHits.csv';
        Wp_Sdtrk_Helper::wp_sdtrk_write_log('---Requested CSV-Feed-Call. Start generating Feed on ' . Wp_Sdtrk_Helper::wp_sdtrk_TimestampToDate('d.m.Y H:i:s', false, 'Europe/Berlin') . '...', $this->debug);
    
        // open raw memory as file so no temp files needed, you might run out of memory though
        $f = fopen('php://memory', 'w');
        
        //write the header
        fputcsv($f, $header, $delimiter);
        
        // loop over the input array
        foreach ($hits as $hit) {
            ksort($hit); //sort same as header
            fputcsv($f, $hit, $delimiter);
        }
        // reset the file pointer to the start of the file
        fseek($f, 0);
        // tell the browser it's going to be a csv file
        header('Content-Type: application/csv');
        // tell the browser we want to save it instead of displaying it
        header('Content-Disposition: attachment; filename="' . $filename . '";');
        // make php send the generated csv lines to the browser
        fpassthru($f);
        Wp_Sdtrk_Helper::wp_sdtrk_write_log('---Finished generating CSV-Feed on ' . Wp_Sdtrk_Helper::wp_sdtrk_TimestampToDate('d.m.Y H:i:s', false, 'Europe/Berlin') . '!', $this->debug);
    }

    /**
     * Creates the XML for the feed
     *
     * @param array $hits
     */
    public function print_XML($hits, $header)
    {
        $skipFields = array(
            "gsync"
        );

        Wp_Sdtrk_Helper::wp_sdtrk_write_log('---Requested XML-Feed-Call. Start generating Feed on ' . Wp_Sdtrk_Helper::wp_sdtrk_TimestampToDate('d.m.Y H:i:s', false, 'Europe/Berlin') . '...', $this->debug);
        $domtree = new DOMDocument('1.0', 'UTF-8');
        $xmlRoot = $domtree->createElement("feed");
        $xmlRoot = $domtree->appendChild($xmlRoot);
        $xmlRoot->appendChild($domtree->createElement('id', 'Wp-Sdtrk'));
        $xmlRoot->appendChild($domtree->createElement('updated', time()));
        $xmlRoot->appendChild($domtree->createElement('title', get_bloginfo('name')));
        $xmlRoot->appendChild($domtree->createElement('subtitle', 'Local Tracking Feed'));

        // print the fieldnames
        $headerEntry = $domtree->createElement("fieldnames");
        $headerEntry = $xmlRoot->appendChild($headerEntry);
        foreach ($header as $field) {
            if (! in_array($field, $skipFields)) {
                $headerEntry->appendChild($domtree->createElement('fieldname', $field));
            }
        }

        // print the entries
        foreach ($hits as $hit) {
            $hitEntry = $domtree->createElement("entry");
            $hitEntry = $xmlRoot->appendChild($hitEntry);
            foreach ($hit as $key => $value) {
                $hitEntry->appendChild($domtree->createElement($key, $value));
            }
        }
        /* get the xml printed */
        echo $domtree->saveXML();
        Wp_Sdtrk_Helper::wp_sdtrk_write_log('---Finished generating XML-Feed on ' . Wp_Sdtrk_Helper::wp_sdtrk_TimestampToDate('d.m.Y H:i:s', false, 'Europe/Berlin') . '!', $this->debug);
    }
}

$hitFeed = new Wp_Sdtrk_Hitfeed();
$hitFeed->print();

