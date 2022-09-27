<?php

class Wp_Sdtrk_hitContainer
{

    private $hits;

    private $fieldNames;

    // Constructor
    public function __construct()
    {
        $this->hits = array();
        $this->fieldNames = array("id","date","eventName","gsync","hitSource");
    }

    /**
     * Formats and appends gsheet hit entries
     *
     * @param array $sheetData
     */
    public function addSheetHits($sheetData)
    {
        if (sizeof($sheetData) > 1) {
            foreach ($sheetData as $data) {
                $this->addSheetHit($data);
            }
        }
    }

    /**
     * Formats and appends a gsheet hit entry
     *
     * @param array $sheetData
     */
    public function addSheetHit($data)
    {
        $hit = array(
            "hitSource" => "gsheet",
            "gsync" => "1"
        );
        // Add existing fields
        
        foreach ($data as $key => $value) {
            if (in_array($key, $this->fieldNames)) {
                
                //convert date
                if($key === "date"){
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
        // Add if id is existing and not already added
        if (isset($hit["id"]) && !empty($hit["id"]) && ! $this->exists("id", $hit["id"])) {
            array_push($this->hits, $hit);
        }
    }

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
     * Set the sync state for local entries
     */
    public function updateLocalStates(){
        $dbHelper = new Wp_Sdtrk_DbHelper();
        foreach($this->hits as $hit){
            if($hit["gsync"] === "0" && $hit["hitSource"] === "local" && isset($hit["id"]) && !empty($hit["id"])){
                $dbHelper->markGSync($hit["id"]);
            }
        }
        return true;
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
     * Formats and appends a local hit entry
     *
     * @param array $localData
     */
    public function addLocalHit($localData)
    {
        array_push($this->hits, $this->flatLocalHit($localData));
    }

    /**
     * Returns all hits
     */
    public function getHits($stackSize = 0)
    {
        $stack = array();
        foreach ($this->hits as $hit) {
            if ($hit["gsync"] === "0" || $hit["hitSource"] === "gsheet") {
                array_push($stack, $hit);
            }
        }        
        if($stackSize<1){
            return array($stack);
        }
        else{
            return array_chunk($stack, $stackSize,false);
        }
    }

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
            "gsync" => $hit->gsync,
            "hitSource" => "local"
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
     * Returns a list of fieldnames (this is used as google sheet header
     *
     * @return string[]
     */
    public function getFieldNames()
    {
        return $this->fieldNames;
    }
    
    /**
     * Add fieldname if it doesnt already exist
     * @param string $fieldName
     */
    private function addFieldName($fieldName){
        if (! in_array($fieldName, $this->fieldNames)) {
            array_push($this->fieldNames, $fieldName);
        }
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
}