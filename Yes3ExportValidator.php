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

use Records;

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

    private const ROW_LIMIT = 100;
    private const ERROR_LIMIT = 1000;
    private const ERROR_LABELS = [
        'HDR_FIELDCOUNT' => 'The column count does not match the field count in the data dictionary.',
        'HDR_FIELDNAMES' => 'One or more column names do not match the field names in the data dictionary.',
        'ROW_HASH' => 'The record id hash does not match the expected value.',
        'ROW_ID' => 'The record id is missing for the row.',
        'CHKBX_OPTION' => 'The exported checkbox option state does not match the stored state.',
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

    private $delimiter = ","; // default delimiter for all exports

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

    private function isCheckboxOption( $ddItem ){

        return (( isset($ddItem['redcap_source_option']) && $ddItem['redcap_source_option'] ));
    }

    private function isCheckboxList( $ddItem ){

        return (( isset($ddItem['var_type']) && $ddItem['var_type']==='CHECKBOX' ));
    }

    public function validate() {

        $rowCount = 0;
        $colCount = 0;

        $header = [];

        $this->sysmsg = "";

        foreach($this->readUploadedRecords() as $row) {

            if ($rowCount === 0) {

                $header = $row;

                // Validate header against data dictionary
                $headerValidation = $this->validateHeader($header); // boolean

                if (!$headerValidation) {

                    return Yes3Fn::failObject("Header validation failed.",
                    [
                        'counts' => $this->counts,
                        'error_report' => $this->error_report,
                        'header' => $header,
                        'dd' => $this->dd
                    ]);
                }

                $colCount = count($row);

                // Skip the header row
                $rowCount++;
                continue;
            }

            // Validate each row
            // returns true or false depending on whether an error is found in the row
            $rowValidation = $this->validateRow($row); // true or false

            $rowCount++;

            if ( $this->counts['errors'] > self::ERROR_LIMIT ) {
                $this->sysmsg = "Error limit exceeded: {$this->counts['errors']} errors found.";
                break; // stop processing if we hit the error limit
            }

            if ($rowCount > self::ROW_LIMIT ) {
                $this->sysmsg = "Row limit exceeded: {$rowCount} rows processed.";
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
            ]);
        }

        return Yes3Fn::successObject(
            $this->sysmsg,
            [
                'counts' => $this->counts
            ]
        );
    }

    private function validateHeader($header):bool {

        $ddFields = array_column($this->dd, 'var_name') ?? [];

        $dd_count = count($ddFields);
        $hdr_col_count = count($header);

        if ( $hdr_col_count !== $dd_count ) {

            $this->reportValidationError([], "HDR_FIELDCOUNT", "The number of fields in the header ({$hdr_col_count}) does not match the data dictionary field count ({$dd_count}).");
            return false;
        }

        $hdrOk = true;

        for ($i = 0; $i < count($header); $i++) {

            $export_col_name = $this->cleanString($header[$i]);
            $dd_col_name = $this->cleanString($ddFields[$i]);

            if ($export_col_name !== $dd_col_name) {

                $hdrOk = false; // header validation failed

                $this->reportValidationError([], "HDR_FIELDNAMES", "Column name '{$export_col_name}' does not match data dictionary field name '{$dd_col_name}' for column {$i}.");
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
            'redcap_data_access_group_id' => 0,
            'redcap_repeat_instance' => 1,
            'redcap_field_name' => "",
            'stored_value' => "",
            'exported_value' => ""
        ];

        $this->counts['rows']++;

        if ( $this->export_hash_recordid ) {

            $record_hash = $x['record'];

            $x['record'] = $this->getRecordIdFromHash($record_hash);

            if ( !$x['record'] ) {

                $msg = "Record ID hash {$record_hash} not matched to any record.";

                $this->reportValidationError([], 'ROW_HASH', $msg);

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

            $value = $row[$i] ?? "";

            $var_name = $this->dd[$i]['var_name'];

            if ( $this->isRedCapVar($var_name) ) {

                switch ($var_name) {

                    case 'redcap_event_id':
                        $x['redcap_event_id'] = (int) $value;
                        break;

                    case 'redcap_data_access_group_id':
                        $x['redcap_data_access_group_id'] = (int) $value;
                        break;

                    case 'redcap_repeat_instance':
                        $x['redcap_repeat_instance'] = (int) $value;
                        break;
                }

                continue; // skip validation for redcap fields
            }

            
            //continue; // shut down whilst I refactor

            $x['exported_value'] = $value;
            $x['redcap_field_name'] = $this->dd[$i]['redcap_field_name'];

            // for horiz layout, the event_id comes from the dd
            if ( isset($this->dd[$i]['redcap_event_id']) && $this->dd[$i]['redcap_event_id'] ) {

                $x['redcap_event_id'] = (int) $this->dd[$i]['redcap_event_id'];
            }

            $this->counts['cells']++;

            if ( $this->isCheckboxOption($this->dd[$i]) ) {

                $validationResult = $this->validateCheckboxOption($x, $this->dd[$i]);
            }
            else if ( $this->isCheckboxList($this->dd[$i]) ) {

                $validationResult = $this->validateCheckboxList($x, $this->dd[$i]);
            }
            //else if ( $this->dd[$i]['var_type'] === 'DATE' && $this->export_shift_dates ) {
            //    $validationResult = $this->validateShiftedDate($record, $redcap_event_id, $redcap_field_name, $redcap_repeat_instance, $value, $this->dd[$i]);
            //}
            else {

                $validationResult = $this->validateValue($x, $this->dd[$i]); // a success or fail std return object
            }

            // bail on failed validation
            //if ($validationResult['result'] !== Yes3K::STD_RETURN_OBJECT_SUCCESS) {
            //    return $validationResult;
            //}
        }

        return ( $this->counts['errors'] === $pre_error_count );
    }

    private function reportValidationError($x=[], $err_type="", $message="") {

        $this->counts['errors']++;
        $this->counts['details'][$err_type] = ($this->counts['details'][$err_type] ?? 0) + 1;

        if ( !empty($x) ) {

            $this->error_report[] = "Row {$this->counts['rows']}: [{$err_type}] {$message} (record: {$x['record']}, event_id: {$x['redcap_event_id']}, field: {$x['redcap_field_name']}, instance: {$x['redcap_repeat_instance']})";
        }
        else {
            $this->error_report[] = "Row {$this->counts['rows']}: [{$err_type}] {$message}";
        }
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

        $this->reportValidationError($x, "CHKBX_OPTION", "option '{$option_value}': the exported ({$exported_checked_state}) and stored ({$stored_checked_state}) checked states do not match.");

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

        $sql = "SELECT value FROM {$this->redcap_data_table} WHERE project_id = ? AND record = ? AND field_name = ? AND event_id = ? AND ifnull(instance, 1) = ? LIMIT 1";
        $params = [
            $this->project_id,
            $x['record'],
            $x['redcap_field_name'],
            $x['redcap_event_id'],
            $x['redcap_repeat_instance']
        ];

        $stored_value = trim(Yes3Fn::fetchValue( $sql, $params ) ?? "");

        $exported_value = trim($x['exported_value']);

        if ( $stored_value === $exported_value ) {

            return true;
        }

        // no match, but it could be a shifted date
        if ( $ddItem['var_type'] === 'DATE' && $this->export_shift_dates ) {

            $days_to_shift = Records::get_shift_days($x['record'], $this->date_shift_max, $this->project_salt);

            $shifted_stored_value = Records::shift_date_format($stored_value, $days_to_shift);

            if ( $shifted_stored_value === $exported_value ) {
                return true;
            }
            else {
                $this->reportValidationError($x, "DATE_SHIFT", "Value validation failed (after date shifting): the exported value '{$exported_value}' does not match the shifted stored value '{$shifted_stored_value}'.");
                return false;
            }
        }

        // no match, but it could be a max length issue
        if ( $this->export_max_text_length > 0 ) {

            if ( substr($exported_value, 0, $this->export_max_text_length) === substr($stored_value, 0, $this->export_max_text_length) ) {

                return true;
            }
        }

        // no match, it could be a sanitization issue
        $exported_value = Yes3Fn::sanitizeForText(
            $exported_value,
            $this->export_max_text_length,
            false, // notags is currently not an export option
            $this->export_ascii_text,
            $this->export_inoffensive_text
        );

        $stored_value = Yes3Fn::sanitizeForText(
            $stored_value,
            $this->export_max_text_length,
            false, // notags is currently not an export option
            $this->export_ascii_text,
            $this->export_inoffensive_text
        );

        if ( $stored_value === $exported_value ) {

            return true;
        }

        $this->reportValidationError($x, "VALUE", "Value validation failed: the exported value '{$exported_value}' does not match the stored value '{$stored_value}'.");
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