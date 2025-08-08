<?php

namespace Yale\Yes3Exporter;

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);


use ExternalModules\ExternalModules;

use Exception;

use Project;

use REDCap;

use RuntimeException;

use Yale\Yes3Exporter\Yes3K;

use Generator;

use Yale\Yes3Exporter\Yes3Fn;

/**
 * Yes3SasCoder class
 *
 * This class is responsible for generating SAS code based on the data dictionary and info files.
 * It reads the data dictionary and info files, processes them, and generates SAS code for input.
 */

class Yes3ExportValidator {

    public $version = "0.0.1";

    public $sysmsg = '';   

    public $workDir = '';
    public $ddFilename = '';
    public $infoFilename = '';
    public $dd_rowcount = 0;
    public $dd_colcount = 0;
    public $dd = [];

    public $project_id = 0;

    public $redcap_data_table = "";

    public $delimiter = ","; // default delimiter for all exports

    public $filePath = "";

    function __construct() {}

    public function initialize( $project_id, $export_uuid, $filePath, $dd ) {

        $this->project_id = (int) $project_id;
        $this->filePath = $filePath;
        $this->redcap_data_table = REDCap::getDataTable($this->project_id);

        if ( !is_array($dd) || empty($dd) ) {

            throw new RuntimeException("Data dictionary is not valid.");
        }

        $this->dd = $dd;
        $this->dd_rowcount = count($dd);
        $this->dd_colcount = count($dd[0] ?? []);

        if ( $this->dd_rowcount < 1 || $this->dd_colcount < 1 ) {
            throw new RuntimeException("Data dictionary is empty or malformed.");
        }

        // infer the delimiter based on filename extension (csv or tsv)
        $extension = pathinfo($this->filePath, PATHINFO_EXTENSION);

        if (strtolower($extension) === 'tsv') {

            $this->delimiter = "\t";
        }


        return( Yes3Fn::successObject(
            "The data dictionary has {$this->dd_rowcount} rows and {$this->dd_colcount} columns.",
            [
                'dd' => $this->dd
            ]
        ));
    }

    public function readUploadedRecords(): Generator {

        $handle = fopen($this->filePath, 'r');

        if (!$handle) {

            throw new Exception("Cannot open file: $this->filePath");
        }

        while (($row = fgetcsv($handle, 0, $this->delimiter)) !== false) {

            yield $row;
        }

        fclose($handle);
    }

    private function isRedCapVar($var_name) {

        $redcap_vars = [
            'redcap_event_id',
            'redcap_event_name',
            'redcap_data_access_group_id',
            'redcap_data_access_group',
            'redcap_event_id',
            'redcap_event_name',
            'redcap_repeat_instance'
        ];

        return in_array($var_name, $redcap_vars);
    }

    private function isCheckbox( $ddItem ){

        return (( isset($ddItem['redcap_source_option']) && $ddItem['redcap_source_option'] ));
    }

    public function validate() {

        $rowCount = 0;
        $colCount = 0;

        $header = [];

        foreach($this->readUploadedRecords() as $row) {

            if ($rowCount === 0) {

                $header = $row;

                // Validate header against data dictionary
                $headerValidation = $this->validateHeader($header); // a success or fail std return object

                if ($headerValidation['result'] !== Yes3K::STD_RETURN_OBJECT_SUCCESS) {

                    return $headerValidation;
                }

                $colCount = count($row);

                // Skip the header row
                $rowCount++;
                continue;
            }

            // Validate each row
            $rowValidation = $this->validateRow($row); // a success or fail std return object

            if ($rowValidation['result'] !== Yes3K::STD_RETURN_OBJECT_SUCCESS) {

                return $rowValidation;
            }

            $rowCount++;

            if ($rowCount > 10000 ) {
                break; // for testing, limit to first 10 data rows
            }
        }

        return Yes3Fn::successObject(
            "The data file has {$rowCount} rows and {$colCount} columns.",
            [
                'rowCount' => $rowCount,
                'colCount' => $colCount,
                'header' => $header,
                'dd' => $this->dd
            ]
        );
    }

    private function validateHeader($header) {

        $ddFields = array_column($this->dd, 'var_name') ?? [];

        if ( count($header) !== count($ddFields) ) {

            return Yes3Fn::failObject("Header validation failed: The number of fields in the header does not match the data dictionary.");
        }

        for ($i = 0; $i < count($header); $i++) {

            $export_col_name = $this->cleanString($header[$i]);
            $dd_col_name = $this->cleanString($ddFields[$i]);

            if ($export_col_name !== $dd_col_name) {

                return Yes3Fn::failObject("Header validation failed: Column name '{$export_col_name}' does not match data dictionary field name '{$dd_col_name}'.");
            }
        }

        return Yes3Fn::successObject("Header validation passed.");
    }
    
    private function validateRow($row) {

        $record = $row[0] ?? null;
        $redcap_event_id = 0;
        $redcap_data_access_group_id = 0;
        $redcap_repeat_instance = 1;
        $redcap_field_name = "";

        if ( !$record ) {

            return Yes3Fn::failObject("Row validation failed: Record ID is missing.");
        }

        // skip the first column (record ID) and gather any other redcap fields
        for ($i = 1; $i < count($row); $i++) {
            
            $value = $row[$i] ?? "";

            $var_name = $this->dd[$i]['var_name'];

            if ( $this->isRedCapVar($var_name) ) {

                switch ($var_name) {

                    case 'redcap_event_id':
                        $redcap_event_id = (int) $value;
                        break;

                    case 'redcap_data_access_group_id':
                        $redcap_data_access_group_id = (int) $value;
                        break;

                    case 'redcap_repeat_instance':
                        $redcap_repeat_instance = (int) $value;
                        break;
                }

                continue; // skip validation for redcap fields
            }
            
            $redcap_field_name = $this->dd[$i]['redcap_field_name'];

            // for horiz layout, the event_id comes from the dd
            if ( isset($this->dd[$i]['redcap_event_id']) && $this->dd[$i]['redcap_event_id'] ) {

                $redcap_event_id = (int) $this->dd[$i]['redcap_event_id'];
            }

            if ( $this->isCheckbox($this->dd[$i]) ) {

                $validationResult = $this->validateCheckbox($record, $redcap_event_id, $redcap_field_name, $redcap_repeat_instance, $value, $this->dd[$i]);
            }
            else {

                $validationResult = $this->validateValue($record, $redcap_event_id, $redcap_field_name, $redcap_repeat_instance, $value); // a success or fail std return object
            }

            // bail on failed validation
            if ($validationResult['result'] !== Yes3K::STD_RETURN_OBJECT_SUCCESS) {
                return $validationResult;
            }
        }

        // Placeholder for row validation logic
        return Yes3Fn::successObject("Row validation passed.");
    }

    /**
     * validates checkboxes, including multiselects
     * 
     * @param mixed $record 
     * @param mixed $redcap_event_id 
     * @param mixed $redcap_field_name 
     * @param mixed $redcap_repeat_instance 
     * @param mixed $value 
     * @param mixed $ddItem 
     * @return void 
     */
    private function validateCheckbox( $record, $redcap_event_id, $redcap_field_name, $redcap_repeat_instance, $value, $ddItem ){

        /**
         * To validate, we retrieve the associated option value from the data dictionary, which is the value stored in the redcap data table.
         */
        $option_value = $ddItem['redcap_source_option'] ?? null;

        if ( !$option_value ) {

            return Yes3Fn::failObject("Checkbox validation failed: No associated option value found in data dictionary."); // truly enfargled
        }

        // we start by determining whether a 'checked state' is present in the redcap database
        $sql = "SELECT COUNT(*) FROM {$this->redcap_data_table} WHERE project_id = ? AND record = ? AND field_name = ? AND event_id = ? AND ifnull(instance, 1) = ? AND value = ? LIMIT 1";
        $params = [
            $this->project_id,
            $record,    
            $redcap_field_name,
            $redcap_event_id,
            $redcap_repeat_instance,
            $option_value
        ];
        $stored_checked_state = Yes3Fn::fetchValue( $sql, $params ) ? 1 : 0;

        // in the export, a "1" indicates that the associated option is selected
        $exported_checked_state = ( $value == "1" ) ? 1 : 0;

        //$exported_checked_state = 1 - $exported_checked_state; // testing, should immediately fail

        if ( $stored_checked_state === $exported_checked_state ) {

            return Yes3Fn::successObject("Checkbox validation passed.");
        }

        return Yes3Fn::failObject("Checkbox validation failed for record '{$record}', event_id '{$redcap_event_id}', instance '{$redcap_repeat_instance}', field '{$redcap_field_name}', option '{$option_value}': the exported ({$exported_checked_state}) and stored ({$stored_checked_state}) checked states do not match.");
    }

    private function validateValue($record, $redcap_event_id, $redcap_field_name, $redcap_repeat_instance, $exported_value) {

        $sql = "SELECT value FROM {$this->redcap_data_table} WHERE project_id = ? AND record = ? AND field_name = ? AND event_id = ? AND ifnull(instance, 1) = ? LIMIT 1";
        $params = [
            $this->project_id,
            $record,
            $redcap_field_name,
            $redcap_event_id,
            $redcap_repeat_instance
        ];

        $stored_value = Yes3Fn::fetchValue( $sql, $params ) ?? "";

        if ( trim($stored_value) === trim($exported_value) ) {

            return Yes3Fn::successObject("The exported and stored values match.");
        }

        return Yes3Fn::failObject("Value validation failed: For record '{$record}', event_id '{$redcap_event_id}', instance '{$redcap_repeat_instance}', field '{$redcap_field_name}': the exported value '{$exported_value}' does not match the stored value '{$stored_value}'.");
   }

    private function cleanString($str) {
        // Remove UTF-8 BOM if present
        $bom = "\xEF\xBB\xBF";
        if (substr($str, 0, 3) === $bom) {
            $str = substr($str, 3);
        }

        return trim($str);
    }

    public function getProperties() {

        return [
            'ddFilename' => $this->ddFilename,
            'dd_rowcount' => $this->dd_rowcount,
            'dd_colcount' => $this->dd_colcount,
            'dd' => $this->dd,
            'sysmsg' => $this->sysmsg,
            'project_id' => $this->project_id
        ];
    }
}