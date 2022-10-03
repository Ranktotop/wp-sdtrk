<?php

class Wp_Sdtrk_hitContainer
{

    private $hits;

    private $fieldNames;

    private $stackSize;

    private $dbHelper;

    private $debug;

    // Constructor
    public function __construct($debug = false)
    {
        $this->stackSize = 500;
        $this->hits = array();
        $this->fieldNames = array(
            "id",
            "date",
            "eventName",
            "gsync"
        );
        $this->dbHelper = new Wp_Sdtrk_DbHelper();
        $this->debug = $debug;
        $this->readDatabase();
    }

    /**
     * *****************************************************
     * Start Reading Functions
     * *****************************************************
     */

    /**
     * Fetch all Data from Database
     */
    private function readDatabase()
    {
        $localData = $this->dbHelper->getAllHits();
        $this->addLocalHits($localData);
    }

    /**
     * Read all fieldnames and create a unique list
     */
    private function readFieldNames()
    {
        foreach ($this->hits as $hit) {
            foreach (array_keys($hit) as $fieldName) {
                $this->addFieldName($fieldName);
            }
        }
    }

    /**
     * *****************************************************
     * End Reading Functions
     * *****************************************************
     */

    /**
     * *****************************************************
     * Start Add Functions
     * *****************************************************
     */

    /**
     * Add fieldname if it doesnt already exist
     *
     * @param string $fieldName
     */
    private function addFieldName($fieldName)
    {
        if (! in_array($fieldName, $this->fieldNames)) {
            array_push($this->fieldNames, $fieldName);
        }
    }

    /**
     * Formats and appends a local hit entry
     *
     * @param array $localData
     */
    public function addLocalHit($localData)
    {
        array_push($this->hits, $this->flatLocalHit($localData));
    }

    /**
     * Formats and appends local hit entries
     *
     * @param array $localData
     */
    public function addLocalHits($localData)
    {
        foreach ($localData as $hit) {
            $this->addLocalHit($hit);
        }
    }

    /**
     * Formats and appends a gsheet hit entry
     *
     * @param array $sheetData
     */
    public function addGSheetHit($data)
    {
        $hit = array(
            "gsync" => "0"
        );
        // Add existing fields

        foreach ($data as $key => $value) {
            if (in_array($key, $this->fieldNames)) {

                // convert date
                if ($key === "date") {
                    $value = strtotime($value);
                }

                $hit[$key] = $value;
            }
        }
        // Fill up non-existing fields
        foreach ($this->fieldNames as $fieldName) {
            if (! isset($hit[$fieldName])) {
                $hit[$fieldName] = "";
            }
        }
        // Add if id is existing
        if (isset($hit["id"]) && ! empty($hit["id"])) {
            // Shedule resync
            $updateSuccess = $this->markForResyc($hit["id"]);  
            //If update was not successful, the hit is a foreign gsheet entry and will be kept
            if(!$updateSuccess){
                array_push($this->hits, $hit);
            }
        }
    }

    /**
     * Formats and appends gsheet hit entries
     *
     * @param array $sheetData
     */
    public function addGSheetHits($sheetData)
    {
        if (sizeof($sheetData) > 1) {
            Wp_Sdtrk_Helper::wp_sdtrk_write_log("Found ".sizeof($sheetData)." entries in gSheet, will be parsed to the local ".sizeof($this->hits)." hits", $this->debug);
            foreach ($sheetData as $data) {
                $this->addGSheetHit($data);
            }
            Wp_Sdtrk_Helper::wp_sdtrk_write_log("Finished! Total amount of hits is now ".sizeof($this->hits), $this->debug);
        }
    }

    /**
     * *****************************************************
     * End Add Functions
     * *****************************************************
     */

    /**
     * *****************************************************
     * Start Converter Functions
     * *****************************************************
     */

    /**
     * Create an one-dimensional array from multidimensional hit
     *
     * @param array[] $hit
     * @return array
     */
    private function flatLocalHit($hit)
    {
        $data = array(
            "id" => $hit->id,
            "date" => $hit->date,
            "eventName" => $hit->eventName,
            "gsync" => $hit->gsync
        );
        $unserializedParams = unserialize($hit->eventParams);
        // Iterate eventParams -> eg. "product"
        foreach (array_keys($unserializedParams) as $paramName) {
            // iterate subfield eg. "id"
            foreach (array_keys($unserializedParams[$paramName]) as $fieldName) {
                $data[$paramName . '_' . $fieldName] = $unserializedParams[$paramName][$fieldName];
                $this->addFieldName($paramName . '_' . $fieldName);
            }
        }
        return $data;
    }

    /**
     * Converts a hit to row format
     *
     * @param array $hit
     */
    private function convertToRow($hit, $skipFields, $fieldNames)
    {
        $row = array();
        for ($i = 0; $i < sizeof($fieldNames); $i ++) {
            // Skip unwanted fields
            if (in_array($fieldNames[$i], $skipFields)) {
                continue;
            }

            if (isset($hit[$fieldNames[$i]])) {

                $value = $hit[$fieldNames[$i]];
                // convert time
                if ($fieldNames[$i] === "date") {
                    $value = date("d.m.Y H:i:s", $value);
                }
                array_push($row, $value);
            } else {
                array_push($row, "");
            }
        }
        return $row;
    }

    /**
     * *****************************************************
     * End Converter Functions
     * *****************************************************
     */

    /**
     * *****************************************************
     * Start Checker Functions
     * *****************************************************
     */

    /**
     * Check if an hit with given fieldname already exists
     *
     * @param string $fieldName
     * @param string $fieldValue
     * @return boolean
     */
    private function exists($fieldName, $fieldValue)
    {
        foreach ($this->hits as $hit) {
            if (isset($hit[$fieldName]) && $hit[$fieldName] === $fieldValue) {
                return true;
            }
        }
        return false;
    }

    /**
     * Re-shedule a hit for gsync
     *
     * @param string $id
     * @return boolean
     */
    private function markForResyc($id)
    {
        foreach ($this->hits as $hit) {
            if (isset($hit["id"]) && $hit["id"] === $id) {
                $hit["gsync"] = 0;
                return true;
            }
        }
        return false;
    }

    /**
     * *****************************************************
     * End Checker Functions
     * *****************************************************
     */

    /**
     * *****************************************************
     * Start Writer Functions
     * *****************************************************
     */

    /**
     * Set the gsync state for local entries
     */
    public function updateGsyncStates()
    {
        foreach ($this->hits as $hit) {
            if ($hit["gsync"] === "0" && isset($hit["id"]) && ! empty($hit["id"])) {
                $this->dbHelper->markGSync($hit["id"]);
            }
        }
        return true;
    }

    /**
     * *****************************************************
     * End Writer Functions
     * *****************************************************
     */

    /**
     * *****************************************************
     * Start Getter Functions
     * *****************************************************
     */

    /**
     * Returns a list of fieldnames (this is used as google sheet header
     *
     * @return string[]
     */
    public function getFieldNames()
    {
        return $this->fieldNames;
    }

    /**
     * Return all hits
     *
     * @return array
     */
    public function getHits($stack = true)
    {
        if ($stack) {
            return array_chunk($this->hits, $this->stackSize, false);
        }
        return $this->hits;
    }

    /**
     * Returns all hits for gsync in row-format
     */
    public function getHitsForGsync($stack = true)
    {
        $gsyncHits = array();

        $skipFields = array(
            "gsync"
        );

        foreach ($this->hits as $hit) {
            if ($hit["gsync"] === "0") {
                array_push($gsyncHits, $this->convertToRow($hit, $skipFields, $this->getFieldNames()));
            }
        }
        if ($stack) {
            $stacks = array_chunk($gsyncHits, $this->stackSize, false);
            Wp_Sdtrk_Helper::wp_sdtrk_write_log("Created ".sizeof($stacks)." gSheet-stacks with total number of ".sizeof($gsyncHits)." items (Stacksize: ".$this->stackSize." items)", $this->debug);
            return $stacks;
        }
        return $gsyncHits;
    }

    /**
     * Get rows for csv export
     *
     * @return array
     */
    public function getHitsForCSVAsRow()
    {
        $rows = array();

        // Delete unwanted fields
        $fieldNames = $this->fieldNames;
        $skipFields = array(
            "id",
            "gsync"
        );
        foreach ($skipFields as $field) {
            $index = array_search($field, $fieldNames);
            if ($index) {
                unset($fieldNames[$index]);
            }
        }
        // reorder index
        $fieldNames = array_values($fieldNames);
        array_push($rows, $fieldNames);

        // Collect CSV Hits
        foreach ($this->hits as $hit) {
            array_push($rows, $this->convertToRow($hit, $skipFields, $fieldNames));
        }
        return $rows;
    }

    /**
     * Get Hits for csv export
     *
     * @return array
     */
    public function getHitsForCSV()
    {
        $skipFields = array(
            "gsync"
        );
        $csvHits = array();
        //iterate all hits
        foreach ($this->getHits(false) as $hit) {
            $csvHit = array();
            //iterate all fields
            foreach ($hit as $key => $value) {
                // convert time
                if ($key === "date") {
                    $value = date("d.m.Y H:i:s", $value);
                }                
                //if key is not skipped add field
                if (! in_array($key, $skipFields)) {
                    $csvHit[$key] = $value;
                }
            }
            //fill empty fields
            foreach($this->getFieldNames() as $fieldName){
                //if fieldname doesnt exist and is not skipped
                if(!isset($csvHit[$fieldName]) && !in_array($fieldName,$skipFields)){
                    $csvHit[$fieldName] = "";
                }
            }
            
            if (sizeof($csvHit) > 0) {
                array_push($csvHits, $csvHit);
            }
        }
        return $csvHits;
    }

/**
 * *****************************************************
 * End Getter Functions
 * *****************************************************
 */
}