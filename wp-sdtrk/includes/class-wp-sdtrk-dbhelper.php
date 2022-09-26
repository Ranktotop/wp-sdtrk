<?php

/**
 * Database Helper Functions
 *
 * @link       https://www.rank-to-top.de
 * @since      1.0.0
 *
 */
class Wp_Sdtrk_DbHelper
{    
    /**
     * Saves a hit to database
     * @param string $eventName
     * @param Wp_Sdtrk_Tracker_Event $event
     * @return boolean
     */
    public function saveHit($eventName,$eventTime,$eventData){
        if($eventName === false){
            return false;
        }
        $tableName = "wp_wpsdtrk_hits";        
        $dataset = array(
            "date" => $eventTime,
            "eventName" => $eventName,
            "eventParams" => serialize($eventData)
        );
        $typeset = array(
            "%s",
            "%s",
            "%s"
        );        
        return $this->insertData(array(),$tableName, $dataset, $typeset);
    }
    
    
    /**
     * Get Single Field from DB
     *
     * @param string $column
     * @param string $table
     * @param string $wherename
     * @param string $wherevalue
     * @return string
     */
    private function getSingleField($column = "id", $table, $wherename, $wherevalue)
    {
        if (! empty($column) && ! empty($table) && ! empty($wherename) && ! empty($wherevalue)) {
            $data = $this->getSingleRow($table, $wherename, $wherevalue);
            if (sizeof($data) > 0 && isset($data->$column)) {
                return $data->$column;
            }
        }
        return "";
    }
    
    /**
     * Get Single Row from DB
     *
     * @param string $field
     * @param string $table
     * @param string $wherename
     * @param string $wherevalue
     * @return array
     */
    private function getSingleRow($table, $wherename, $wherevalue)
    {
        $foundRow = array();
        if (! empty($table) && ! empty($wherename) && ! empty($wherevalue)) {
            global $wpdb;
            $query = $wpdb->prepare("SELECT * FROM {$table} WHERE {$wherename} = %s", array($wherevalue));
            // Create Query
            $data = $wpdb->get_results($query);
            if(sizeof($data)>0){
                $foundRow = $data[0];
            }
            
        }
        return $foundRow;
    }
    
    /**
     * Get all rows for table
     *
     * @param string $table
     * @param string $orderby
     * @param string $order
     * @return array
     */
    private function getAllRows($table, $orderby = "", $order = "ASC")
    {
        if (! empty($table)) {
            global $wpdb;
            $orderString = "";
            if (! empty($orderby)) {
                $orderString .= "ORDER BY `" . $orderby . "` " . $order;
            }
            return $wpdb->get_results("SELECT * FROM $table " . $orderString);
        }
        return array();
    }
    
    /**
     * Returns all rows where two columns are not equal
     * @param string $table
     * @param string $orderby
     * @param string $order
     * @param string $columnA
     * @param string $columnB
     * @return array
     */
    private function getAllRowsNotEqual($table, $orderby = "", $order = "ASC",$columnA, $columnB){
        if (! empty($table) && ! empty($columnA) && ! empty($columnB)) {
            global $wpdb;
            if (! empty($orderby)) {
                $query = "SELECT * FROM {$table} WHERE NOT {$columnA} <=> {$columnB} ORDER BY {$orderby} {$order}";
            }
            else{
                $query = "SELECT * FROM {$table} WHERE NOT {$columnA} <=> {$columnB}";
            }
            
            return $wpdb->get_results($query);
        }
        return array();
    }
    
    /**
     * Push Data to DB
     *
     * @param string $tablename
     * @param integer $id
     * @param array $dataset
     * @param array $typeset
     * @param string $query
     */
    private function pushData($tablename, $id, $dataset, $typeset, $query)
    {
        $state = false;
        
        global $wpdb;
        $knownData = $wpdb->get_results($query);
        if (! empty($id)) {
            $state = $this->updateData($knownData, $id, $tablename, $dataset, $typeset);
        } else {
            $state = $this->insertData($knownData, $tablename, $dataset, $typeset);
        }
        
        return $state;
    }
    
    /**
     * Save new Data to DB
     *
     * @param array $knownData
     * @param string $tablename
     * @param string $query
     * @param array $dataset
     * @param array $typeset
     */
    private function insertData($knownData, $tablename, $dataset, $typeset)
    {
        global $wpdb;
        
        if (sizeof($knownData) == 0) {
            $wpdb->insert($tablename, $dataset, $typeset);
            return true;
        }
        
        return false;
    }
    
    /**
     * Update Data in the DB
     *
     * @param array $knownData
     * @param integer $id
     * @param string $tablename
     * @param string $query
     * @param array $dataset
     * @param array $typeset
     */
    private function updateData($knownData, $id, $tablename, $dataset, $typeset)
    {
        global $wpdb;
        $updateSafe = false;
        
        if (sizeof($knownData) > 0) {
            if ($knownData[0]->id == $id) {
                $updateSafe = true;
            }
        } else {
            $updateSafe = true;
        }
        
        if ($updateSafe) {
            $primaryKey = array(
                "id" => $id
            );
            $primaryKeyType = array(
                "%d"
            );
            $wpdb->update($tablename, $dataset, $primaryKey, $typeset, $primaryKeyType);
            return true;
        }
        
        return false;
    }
    
    
    
    /**
     * Delete Data from DB
     *
     * @param string $tablename
     * @param integer $id
     */
    private function deleteData($tablename, $id)
    {
        global $wpdb;
        $wpdb->delete($tablename, array(
            'id' => $id
        ));
    }
    
    /**
     * Get data by a joined request
     * @param string $baseTable
     * @param string $additionalTable
     * @param string $sharedBaseIndex
     * @param string $sharedAdditionalIndex
     * @param string $whereColumnFromBase
     * @param string $whereValue
     * @param string $orderColumnFromAdditional
     * @return array
     */
    private function joinedRequest($baseTable,$additionalTable,$sharedBaseIndex,$sharedAdditionalIndex,$whereColumnFromBase,$whereValue,$orderColumnFromAdditional){
        global $wpdb;
        $whereString = "";
        $orderString ="";
        if(!empty(trim($whereColumnFromBase)) && !empty(trim($whereValue))){
            $whereString = "WHERE ".$whereColumnFromBase." = '".$whereValue."'";
        }
        
        if(!empty(trim($orderColumnFromAdditional))){
            $orderString = "ORDER BY $additionalTable.".$orderColumnFromAdditional."";
        }
        return $wpdb->get_results("SELECT * FROM $baseTable LEFT JOIN $additionalTable ON $baseTable.$sharedBaseIndex = $additionalTable.$sharedAdditionalIndex ".$whereString." ".$orderString."");
    }
}