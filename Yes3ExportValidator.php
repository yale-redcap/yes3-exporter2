<?php

namespace Yale\Yes3Exporter2;

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);


use ExternalModules\ExternalModules;

use Exception;

use Project;

use REDCap;

use RuntimeException;

use Generator;

use Records;

use Yale\Yes3Exporter2\Yes3Fn;

/**
 * Yes3ExportValidator class
 *
 * This class is responsible for validating the exported data against the stored data.
 * It is the inverse function and system test for the Yes3Exporter, and is intended to ensure data integrity and consistency.
 *
 * @package Yale\Yes3Exporter
 */

class Yes3ExportValidator {

    public $version = "0.0.1";

    public $sysmsg = '';

    private const ROW_LIMIT = 10000;
    private const ERROR_LIMIT = 2000;
    private const ERROR_LABELS = [
        'HDR_COUNT' => 'The column count does not match the field count in the data dictionary.',
        'HDR_NAME' => 'One or more column names do not match the field names in the data dictionary.',
        'ROW_HASH' => 'The record id hash does not match the expected value.',
        'ROW_ID' => 'The record id is missing for the row.',
        'CHKBX_OPTN' => 'The exported checkbox option state does not match the stored state.',
        'CHKBX_LIST' => 'The exported checkbox list does not match the stored state.',
        'DATE_SHIFT' => 'The exported date value does not match the stored value (after date shifting).',
        'VALUE' => 'The exported value does not match the stored value.'
    ];

    public $error_report = []; // array of discrepancy messages
    public $counts = [
        'errors' => 0,
        'rows' => 0,
        'cells' => 0,
        'details' => []
    ]; 

    private $dd_rowcount = 0;
    private $dd_colcount = 0;

    private $export_uuid                = "";
    private $export_label               = "";
    private $export_name                = "";
    private $export_layout              = ""; // h, v, r
    private $export_max_text_length     = 0;
    private $export_inoffensive_text    = 0;
    private $export_no_tags             = 0;
    private $export_ascii_text          = 0;
    private $export_shift_dates         = 0;
    private $export_group_id            = 0;
    private $export_hash_recordid       = 0;
    private $export_has_repeatables     = 0;
    private $dd                         = [];

    private $project_id = 0;

    private $redcap_data_table = "";

    private $delimiter = "\t"; // default delimiter for all exports

    private $fileType = "csv"; // csv or tsv

    private $filePath = "";

    private $project_salt = "";
    private $date_shift_max = 0; // max date shift in days, default is 0 (no shift)

    function __construct() {}

    public function initialize( $project_id, $filePath, $ddPackage ) {

        $this->project_id = (int) $project_id;
        $this->filePath = $filePath;
        $this->redcap_data_table = REDCap::getDataTable($this->project_id);

        $this->export_uuid                = $ddPackage['export_uuid'] ?? "";
        $this->export_label               = $ddPackage['export_label'] ?? "";
        $this->export_name                = $ddPackage['export_name'] ?? "";
        $this->export_layout              = $ddPackage['export_layout'] ?? "";
        $this->export_max_text_length     = (int)($ddPackage['export_max_text_length'] ?? 0);
        $this->export_inoffensive_text    = (int)($ddPackage['export_inoffensive_text'] ?? 0);
        $this->export_no_tags             = (int)($ddPackage['export_no_tags'] ?? 0);
        $this->export_ascii_text          = (int)($ddPackage['export_ascii_text'] ?? 0);
        $this->export_shift_dates         = (int)($ddPackage['export_shift_dates'] ?? 0);
        $this->export_group_id            = (int)($ddPackage['export_group_id'] ?? 0);
        $this->export_hash_recordid       = (int)($ddPackage['export_hash_recordid'] ?? 0);
        $this->export_has_repeatables     = (int)($ddPackage['export_has_repeatables'] ?? 0);
        $this->dd                         = $ddPackage['export_data_dictionary'] ?? [];

        if ( !is_array($this->dd) || empty($this->dd) ) {

            throw new RuntimeException("Data dictionary is not valid.");
        }

        $this->dd_rowcount = count($this->dd);
        $this->dd_colcount = count($this->dd[0] ?? []);

        if ( $this->dd_rowcount < 1 || $this->dd_colcount < 1 ) {
            throw new RuntimeException("Data dictionary is empty or malformed.");
        }

        // infer the delimiter based on filename extension (csv or tsv)
        $extension = pathinfo($this->filePath, PATHINFO_EXTENSION);

        if (strtolower($extension) === 'tsv') {

            $this->delimiter = "\t";
            $this->fileType = "tsv";
        }

        // items from the project object
        $proj = new Project($this->project_id);

        $this->project_salt = $proj->project['__SALT__'] ?? "";
        $this->date_shift_max = (int)($proj->project['date_shift_max'] ?? 0);

        return( Yes3Fn::successObject(
            "The data dictionary has {$this->dd_rowcount} rows and {$this->dd_colcount} columns.",
            [
                'dd' => $this->dd
            ]
        ));
    }

    public function processUploadedRecords(): Generator {

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

    /**
     * Check if the given data dictionary checkbox item is a checkbox *option* variable (as opposed to a checkbox *list*).
     *
     * These are characterized by having a 'redcap_source_option' dd field set to the checkbox option value, as defined in the REDCap form designer.
     * In the export, checkbox option variables have names that follow the REDCap convention, i.e
     * [redcap field name]___[redcap source option], and take on values of 1 or blank (no way to distinguish 'unchecked' from 'missing').
     * Their var_type is NOMINAL (same as for radio, dropdown, yesno and truefalse fields).
     */
    private function isCheckboxOption( $ddItem ){

        return (( isset($ddItem['redcap_source_option']) && $ddItem['redcap_source_option'] ));
    }

    /**
     * A checkbox *list* variable is named [redcap_field_name], 
     * its var_type is CHECKBOX and its value is a comma-delimited string of checked option values.
     */
    private function isCheckboxList( $ddItem ){

        return (( isset($ddItem['var_type']) && $ddItem['var_type']==='CHECKBOX' ));
    }

    public function validate() {

        $rowCount = 0;
        $colCount = 0;

        $header = [];

        $this->sysmsg = "";

        //xx$yieldLog = "";

        foreach($this->processUploadedRecords() as $row) {

            if ( !is_array( $row ) ){

                throw new Exception("Invalid row format.");
            }

            //xx$yieldLog .= "Row {$rowCount}: " . implode(", ", $row) . "\n";

            if ($rowCount === 0) {
            
                $row[0] = $this->cleanString($row[0]); // clean the first column of the first row to remove BOM

                $header = $row;

                // Validate header against data dictionary
                $headerValidation = $this->validateHeader($header); // boolean

                if (!$headerValidation) {

                    $this->sysmsg = "Header validation failed.";

                    break;
                }
            }
            else {

                // Validate each row
                // returns true or false depending on whether an error is found in the row
                $rowValidation = $this->validateRow($row); // true or false

                if ( $this->counts['errors'] > self::ERROR_LIMIT ) {
                    $this->sysmsg = "Error limit exceeded: {$this->counts['errors']} errors found.";
                    break; // stop processing if we hit the error limit
                }
            }

            $rowCount++;

            if ($rowCount > self::ROW_LIMIT ) {
                $this->sysmsg = "Row limit exceeded: {$rowCount} rows processed. {$this->counts['errors']} discrepancies found.";
                break; // stop processing if we hit the row limit
            }
        }

        if ( !$this->sysmsg ) {

            $this->sysmsg = "Validation completed " . ( $this->counts['errors'] > 0 ? "with errors." : "successfully." );
        }

        if ( $this->counts['errors'] > 0 ) {

            return Yes3Fn::failObject($this->sysmsg,
            [
                'counts' => $this->counts,
                'error_report' => $this->error_report,
                'dd' => $this->dd
                //xx, 'yield_log' => $yieldLog
            ]);
        }

        return Yes3Fn::successObject(
            $this->sysmsg,
            [
                'counts' => $this->counts,
                'error_report' => [],
                'dd' => $this->dd
                //xx, 'yield_log' => $yieldLog
            ]
        );
    }

    private function validateHeader($header):bool {

        $ddFields = array_column($this->dd, 'var_name') ?? [];

        $dd_count = count($ddFields);
        $hdr_col_count = count($header);

        if ( $hdr_col_count !== $dd_count ) {

           $this->reportValidationError([], "HDR_COUNT", "The number of fields in the header ({$hdr_col_count}) does not match the data dictionary field count ({$dd_count}).");
            return false;
        }

        $hdrOk = true;

        for ($i = 0; $i < count($header); $i++) {

            $export_col_name = $this->cleanString($header[$i]);
            $dd_col_name = $this->cleanString($ddFields[$i]);

            if ($export_col_name !== $dd_col_name) {

                $hdrOk = false; // header validation failed

               $this->reportValidationError([], "HDR_NAME", "Column name '{$export_col_name}' does not match data dictionary field name '{$dd_col_name}' for column {$i}.");
            }
        }

        return $hdrOk;
    }

    private function getRecordIdFromHash($record_hash) {


        $sql = "SELECT record FROM redcap_record_list WHERE project_id = ?";
        $params = [$this->project_id];

        foreach (Yes3Fn::recordGenerator($sql, $params) as $row) {

            if ( Yes3Fn::hash_record($row['record'], $this->project_salt) === $record_hash ) {
                return $row['record'];
            }
        }

        return null; // Record not found
    }

    private function validateRow($row): bool {

        $x = [
            'record' => $row[0] ?? null,
            'redcap_event_id' => 0,
            'redcap_event_name' => "",
            'redcap_data_access_group_id' => 0,
            'redcap_repeat_instance' => 1,
            'redcap_field_name' => "",
            'value' => "",
            'stored_value' => "",
            'exported_value' => ""
        ];


        $this->counts['rows']++;

        $debug_row = false;

        $rowStr = implode(", ", $row);
    
        if ( $debug_row ) {

            $this->reportValidationError([], 'debug', $rowStr);
        }

        //$this->reportValidationError([], 'debug',$rowStr);
        //$xStr = print_r($x, true);
        //$this->reportValidationError($x, "debug", $xStr);

        if ( $this->export_hash_recordid ) {

            $record_hash = $x['record'];

            $x['record'] = $this->getRecordIdFromHash($record_hash);

            if ( !$x['record'] ) {

               $this->reportValidationError([], 'ROW_HASH', "Record ID hash {$record_hash} not matched to any record.");

                return false;
            }
        }

        if ( ! $x['record'] ) {

           $this->reportValidationError([], 'ROW_ID', "Missing record ID.");

            return false;
        }

        $pre_error_count = $this->counts['errors']; // keep track of errors before validation


        // skip the first column (record ID) and gather any other redcap fields
        for ($i = 1; $i < count($row); $i++) {

            $x['value'] = "";
            $x['exported_value'] = "";

            $value = $row[$i] ?? "";

            $ddItem = $this->dd[$i] ?? null;

            $var_name = $ddItem['var_name'];

            $x['var_name'] = $var_name;

            if ( $this->isRedCapVar($var_name) ) {

                $x[$var_name] = $value; // assign the value to the redcap variable
            }
            else {

                $x['value'] = $value;
                $x['exported_value'] = $value;
                $x['redcap_field_name'] = $ddItem['redcap_field_name'];

                // for horiz layout, the event_id comes from the dd
                
                if ( $this->export_layout==='h' && isset($ddItem['redcap_event_id']) && $ddItem['redcap_event_id'] ) {

                    $x['redcap_event_id'] = (int) $ddItem['redcap_event_id'];
                    $x['redcap_event_name'] = $ddItem['redcap_event_name'] ?? "";
                }
                
                $this->counts['cells']++;

                if ( $this->isCheckboxOption($ddItem) ) {

                    $validationResult = $this->validateCheckboxOption($x, $ddItem);
                }
                else if ( $this->isCheckboxList($ddItem) ) {

                    $validationResult = $this->validateCheckboxList($x, $ddItem);
                }
                else {

                    $validationResult = $this->validateValue($x, $ddItem); // a success or fail std return object
                }
            }

            if ( $debug_row ) {

                $xStr = print_r($x, true);
                $this->reportValidationError($x, "debug-x", "i = " . $i . ", " . $var_name . " = " . substr($row[$i], 0, 10));
            }
        }

        return ( $this->counts['errors'] === $pre_error_count );
    }

    private function reportValidationError($x=[], $err_type="", $message=null) {

        $this->counts['errors']++;
        $this->counts['details'][$err_type] = ($this->counts['details'][$err_type] ?? 0) + 1;

        $report = [
            'row' => $this->counts['rows'],
            'error_type' => $err_type,
            'message' => $message ?? self::ERROR_LABELS[$err_type] ?? "Unknown error type",
            'record' => $x['record'] ?? null,
            'event_id' => $x['redcap_event_id'] ?? null,
            'event_name' => $x['redcap_event_name'] ?? null,
            'field' => $x['redcap_field_name'] ?? null,
            'instance' => $x['redcap_repeat_instance'] ?? null,
            'exported_value' => $x['exported_value'] ?? null,
            'stored_value' => $x['stored_value'] ?? null
        ];

        $this->error_report[] = $report;
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
    private function validateCheckboxOption( $x, $ddItem ){

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
            $x['record'],
            $x['redcap_field_name'],
            $x['redcap_event_id'],
            $x['redcap_repeat_instance'],
            $option_value
        ];
        $stored_checked_state = Yes3Fn::fetchValue( $sql, $params ) ? 1 : 0;

        // in the export, a "1" indicates that the associated option is selected
        $exported_checked_state = ( $x['exported_value'] == "1" ) ? 1 : 0;

        //$exported_checked_state = 1 - $exported_checked_state; // testing, should immediately fail

        if ( $stored_checked_state === $exported_checked_state ) {

            return true;
        }

       $this->reportValidationError($x, "CHKBX_OPTN", "option '{$option_value}': the exported ({$exported_checked_state}) and stored ({$stored_checked_state}) checked states do not match.");

        return false;
    }

    private function validateCheckboxList( $x, $ddItem ){

        $exported_selections = [];
        $stored_selections = [];

        // parse out the exported values
        if ( $x['exported_value'] ){

            $exported_selections = array_unique(array_map('trim', explode(",", $x['exported_value'])));
        }

        // assemble the stored exported values
        $sql = "SELECT value FROM {$this->redcap_data_table} WHERE project_id = ? AND event_id = ? AND ifnull(instance, 1) = ? AND record = ? AND field_name = ? ";
        $params = [
            $this->project_id,
            $x['redcap_event_id'],
            $x['redcap_repeat_instance'],
            $x['record'],
            $x['redcap_field_name']
        ];
        $stored_selection_values = Yes3Fn::fetchRecords($sql, $params) ?? [];
        foreach( $stored_selection_values as $stored_selection_value){

            $stored_selections[] = trim($stored_selection_value['value']);
        }
        $stored_selections = array_unique($stored_selections); // just in case

        // determine the union of the two lists
        $all_selections = array_values(array_unique(array_merge($exported_selections, $stored_selections)));

        // if empty, we're done (no selections exported, no selections stored)
        if ( empty($all_selections) ) {

            return true;
        }

        // if the union is also the intersection, we're done
        if ( count($all_selections)===count($stored_selections) && count($all_selections)===count($exported_selections) ){

            return true;
        }

        // how sad, gotta grind it out

        $listCorrect = true;

        foreach ($all_selections as $selection) {

            $in_exported = in_array($selection, $exported_selections) ? 1 : 0;
            $in_stored = in_array($selection, $stored_selections) ? 1 : 0;
            if ($in_exported !== $in_stored) {

                $listCorrect = false;

               $this->reportValidationError($x, "CHKBX_LIST", "selection '{$selection}': exported: {$in_exported}, stored: {$in_stored}.");
            }
        }

        return $listCorrect;
    }

    private function validateValue($x, $ddItem) {

        if ( empty($x['redcap_repeat_instance']) || !is_numeric($x['redcap_repeat_instance']) || $x['redcap_repeat_instance'] < 1 ) {
            $x['redcap_repeat_instance'] = 1;
        }

        $sql = "SELECT value FROM {$this->redcap_data_table} WHERE project_id = ? AND record = ? AND field_name = ? AND event_id = ? AND ifnull(instance, 1) = ? LIMIT 1";
        $params = [
            $this->project_id,
            $x['record'],
            $x['redcap_field_name'],
            $x['redcap_event_id'],
            $x['redcap_repeat_instance']
        ];

        $x['stored_value'] = trim(Yes3Fn::fetchValue( $sql, $params ) ?? "");
        /*
        if ( $x['redcap_field_name'] === 'participant_type' ) {

            $xStr = print_r($x, true);
            $ddStr = print_r($ddItem, true);

            throw new Exception("Debug participant_type: x['stored_value'] is '{$x['stored_value']}'. Data: {$xStr}. DD: {$ddStr}");
        }
        */
        if ( $x['stored_value'] === $x['exported_value'] ) {

            return true;
        }
        /*
        if ( $x['redcap_field_name'] === 'participant_type' ) {

            $xStr = print_r($x, true);
            $ddStr = print_r($ddItem, true);

            throw new Exception("Debug participant_type: x['stored_value'] is '{$x['stored_value']}'. Data: {$xStr}. DD: {$ddStr}");
        }
        */

        // no match, but it could be a shifted date
        if ( $ddItem['var_type'] === 'DATE' && $this->export_shift_dates ) {

            $days_to_shift = Records::get_shift_days($x['record'], $this->date_shift_max, $this->project_salt);
            /*
            if ( !is_numeric(substr($x['stored_value'],5,2)) ) {

                throw new Exception("Stored date value '{$x['stored_value']}' is not in a valid format.");
            }
            */
            $shifted_stored_value = Records::shift_date_format($x['stored_value'], $days_to_shift);

            if ( $shifted_stored_value === $x['exported_value'] ) {
                return true;
            }
            else {
               $this->reportValidationError($x, "DATE_SHIFT", "the exported value '{$x['exported_value']}' does not match the shifted stored value '{$shifted_stored_value}'.");
                return false;
            }
        }

        // no match, it could be a conditioning issue

        $x['sanitized_stored_value'] = Yes3Fn::sanitizeForFiletype(
            $x['stored_value'],
            $this->export_max_text_length,
            $this->export_ascii_text,
            $this->fileType
        );

        if ( $x['exported_value'] === $x['sanitized_stored_value'] ) {

            return true;
        }

        // okay, we surrender and report the discrepancy

        if ( $x['sanitized_stored_value'] !== $x['stored_value'] ) {

            $msg = "The exported value '{$x['exported_value']}' does not match the conditioned stored value '{$x['sanitized_stored_value']}'";
        }
        else {

            if ( $x['redcap_field_name'] === 'participant_type' ) {

                $strx = print_r($x, true);

                //throw new Exception("Validation error: exported value '{$x['exported_value']}' does not match stored value '{$x['stored_value']}' for record {$x['record']} and field {$x['redcap_field_name']}. Data: {$strx}");
            }
            $msg = "The exported value '{$x['exported_value']}' does not match the stored value '{$x['stored_value']}'";
        }

       $this->reportValidationError($x, "VALUE", $msg);

        return false;
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
            'dd_rowcount' => $this->dd_rowcount,
            'dd_colcount' => $this->dd_colcount,
            'dd' => $this->dd,
            'sysmsg' => $this->sysmsg,
            'project_id' => $this->project_id
        ];
    }
}