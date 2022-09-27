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
     *
     * @param string $eventName
     * @param Wp_Sdtrk_Tracker_Event $event
     * @return boolean
     */
    public function saveHit($eventName, $eventTime, $eventData)
    {
        if ($eventName === false) {
            return false;
        }
        $tableName = "wp_wpsdtrk_hits";
        $dataset = array(
            "date" => $eventTime,
            "eventName" => $eventName,
            "eventParams" => serialize($eventData),
            "gsync" => false
        );
        $typeset = array(
            "%s",
            "%s",
            "%s",
            "%d"
        );
        return $this->insertData(array(), $tableName, $dataset, $typeset);
    }

    /**
     * Mark the given id-element as synced
     * @param string $id
     * @return boolean
     */
    public function markGSync($id)
    {
        Wp_Sdtrk_Helper::wp_sdtrk_vardump_log($id);
        if ($id === false || empty($id)) {
            return false;
        }
        global $wpdb;
        $dataset = array(
            "gsync" => true
        );
        $typeset = array(
            "%d"
        );
        $primaryKey = array(
            "id" => $id
        );
        $primaryKeyType = array(
            "%d"
        );
        Wp_Sdtrk_Helper::wp_sdtrk_vardump_log($id);
        Wp_Sdtrk_Helper::wp_sdtrk_vardump_log($dataset);
        Wp_Sdtrk_Helper::wp_sdtrk_vardump_log($primaryKey);
        Wp_Sdtrk_Helper::wp_sdtrk_vardump_log($typeset);
        Wp_Sdtrk_Helper::wp_sdtrk_vardump_log($primaryKeyType);
        $wpdb->update("wp_wpsdtrk_hits", $dataset, $primaryKey, $typeset, $primaryKeyType);
        return true;
    }

    /**
     * Clear the whole local db-table
     */
    public function clearDB()
    {
        $this->deleteAllData("wp_wpsdtrk_hits");
    }

    /**
     * Clear the whole sync state in db-table
     */
    public function clearGSync()
    {
        global $wpdb;
        $query = "UPDATE wp_wpsdtrk_hits SET gsync=0";
        return $wpdb->get_results($query);
    }

    /**
     * Get all rows for sync with google sheets
     *
     * @return array
     */
    public function getRowsForGsync()
    {
        global $wpdb;
        $table = "wp_wpsdtrk_hits";
        $whereColumn = "gsync";
        $whereVal = "0";
        $oderColumn = "date";
        $query = $wpdb->prepare("SELECT * FROM {$table} WHERE {$whereColumn} = %s ORDER BY {$oderColumn}", array(
            $whereVal
        ));
        // Create Query
        return $wpdb->get_results($query);
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
            $query = $wpdb->prepare("SELECT * FROM {$table} WHERE {$wherename} = %s", array(
                $wherevalue
            ));
            // Create Query
            $data = $wpdb->get_results($query);
            if (sizeof($data) > 0) {
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
     *
     * @param string $table
     * @param string $orderby
     * @param string $order
     * @param string $columnA
     * @param string $columnB
     * @return array
     */
    private function getAllRowsNotEqual($table, $orderby = "", $order = "ASC", $columnA, $columnB)
    {
        if (! empty($table) && ! empty($columnA) && ! empty($columnB)) {
            global $wpdb;
            if (! empty($orderby)) {
                $query = "SELECT * FROM {$table} WHERE NOT {$columnA} <=> {$columnB} ORDER BY {$orderby} {$order}";
            } else {
                $query = "SELECT * FROM {$table} WHERE NOT {$columnA} <=> {$columnB}";
            }

            return $wpdb->get_results($query);
        }
        return array();
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
     * Deletes all records from given table
     *
     * @param string $tablename
     */
    private function deleteAllData($tablename)
    {
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE $tablename");
    }

    /**
     * Get data by a joined request
     *
     * @param string $baseTable
     * @param string $additionalTable
     * @param string $sharedBaseIndex
     * @param string $sharedAdditionalIndex
     * @param string $whereColumnFromBase
     * @param string $whereValue
     * @param string $orderColumnFromAdditional
     * @return array
     */
    private function joinedRequest($baseTable, $additionalTable, $sharedBaseIndex, $sharedAdditionalIndex, $whereColumnFromBase, $whereValue, $orderColumnFromAdditional)
    {
        global $wpdb;
        $whereString = "";
        $orderString = "";
        if (! empty(trim($whereColumnFromBase)) && ! empty(trim($whereValue))) {
            $whereString = "WHERE " . $whereColumnFromBase . " = '" . $whereValue . "'";
        }

        if (! empty(trim($orderColumnFromAdditional))) {
            $orderString = "ORDER BY $additionalTable." . $orderColumnFromAdditional . "";
        }
        return $wpdb->get_results("SELECT * FROM $baseTable LEFT JOIN $additionalTable ON $baseTable.$sharedBaseIndex = $additionalTable.$sharedAdditionalIndex " . $whereString . " " . $orderString . "");
    }
}