<?php

namespace Yale\Yes3Exporter2;

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
//ini_set('memory_limit', '512M'); // increase memory limit for large exports

require "Yes3Trait.php";
require "Yes3Export.php";
require "Yes3ExportItem.php";
require "Yes3SasCoder.php";
require "Yes3ExportValidator.php";
require "Yes3Fn.php"; // factory functions and constants

use Exception;
use REDCap;
use ZipArchive;
use Project;
use Records;

class Yes3Exporter2 extends \ExternalModules\AbstractExternalModule
{
    public $sysmsg = "";
    public $errmsg = "";

    private $project_id = 0;
    private $date_shift_max = 0;
    private $project_salt = "";
    
    public $EXTERNAL_MODULE_ID = 0;

    const EXPORTER_DOWNLOAD_COOKIE_NAME = "yes3-exporter-download";

    const LOG_MESSAGE_FILES_WRITTEN = "export files written";
    const LOG_MESSAGE_DD_DOWNLOADED = "export data dictionary downloaded";
    const LOG_MESSAGE_DATA_DOWNLOADED = "export data downloaded";
    const LOG_MESSAGE_ZIP_DOWNLOADED = "export zip downloaded";
    const LOG_MESSAGE_LEGACY_TRANSFERRED = "legacy exporter environment transferred";

    const DESTINATION_DOWNLOAD = "download";
    const DESTINATION_FILESYSTEM = "filesystem";
    const DESTINATION_BATCH = "filesystem(batch)";  // user-requested batch filesystem export
    const DESTINATION_CRON = "filesystem(cron)";  // cron batch export

    const EMLOG_MSG_EXPORT_SPECIFICATION = 'yes3-export-specification';
    const EMLOG_MSG_EXPORT_EVENTS = 'yes3-export-events';
    const EMLOG_TYPE_EXPORT_SPECIFICATION = 'yes3-export-specification';
    const EMLOG_TYPE_EXPORT_EVENTS = 'yes3-export-events';
    const EMLOG_TYPE_EXPORT_LOG_ENTRY = 'yes3-export-log';
    const EMLOG_TYPE_ERROR_REPORT = 'yes3-export-error-report';
    const EMLOG_TYPE_CRON_LOG = 'yes3-exporter-cron-log';
    const VARNAME_GROUP_ID = 'redcap_data_access_group_id';
    const VARNAME_GROUP_NAME = 'redcap_data_access_group';
    const VARNAME_EVENT_ID = 'redcap_event_id';
    const VARNAME_EVENT_NAME = 'redcap_event_name';
    const VARNAME_INSTANCE = 'redcap_repeat_instance';

    const ALL_OF_THEM = '_all_'; // a token for 'all events', 'all forms' etc

    use Yes3Trait;

    public function getCopyRight(){

        $version = $this->escape($this->getVersion());

        // the current year
        $year = date("Y");

        return REDCap::getCopyright() . "<br />YES3 Exporter {$version} - &copy; {$year} REDCap@Yale";
    }

    private function addSysmsg($msg)
    {
        // add the message to the system message log only if it is not already there
        if ( strpos($this->sysmsg, $msg)===false ){

            if ( strlen($this->sysmsg) > 0 ) $this->sysmsg .= "\n";

            $this->sysmsg .= $msg;
        }
    }

    private function addErrmsg($msg)
    {
        // add the message to the system message log only if it is not already there
        if ( strpos($this->errmsg, $msg)===false ){

            if ( strlen($this->errmsg) > 0 ) $this->errmsg .= "\n";

            $this->errmsg .= $msg;
        }
    }

    public function getModuleId(){

        if ( !$this->EXTERNAL_MODULE_ID ) {
            $sql = "select external_module_id from redcap_external_modules where directory_prefix=?";
            $this->EXTERNAL_MODULE_ID = Yes3Fn::fetchValue($sql, [ $this->PREFIX ]);
        }

        return $this->EXTERNAL_MODULE_ID;
    }

    public function getFormExportPermissions(){

        return $this->yes3UserRights()['form_export_permissions'];
    }

    public function getImageUrl(){

        return [
            'dark' => [
                'logo_square' => $this->getUrl('images/YES3_Logo_Square_Black.png'),
                'logo_horizontal' => $this->getUrl('images/YES3_Logo_Horizontal_Black.png')
            ],
            'light' => [
                'logo_square' => $this->getUrl('images/YES3_Logo_Square_White.png'),
                'logo_horizontal' => $this->getUrl('images/YES3_Logo_Horizontal_White.png')
            ]
        ];
    }

    public function getVersion(){

        return $this->getConfig()['versions'][0]['version'];
    }

    public function getServiceUrl(){

        return $this->getUrl('services/services.php');
    }

    public function getDocumentationUrl(){

        return "https://yale-redcap.github.io/yes3-exporter2-docs/";
    }

    public function getChangelogUrl(){

        return "https://yale-redcap.github.io/yes3-exporter2-docs/guides/technical-guide/";
    }

    public function getTechnicalDocumentationUrl(){

        return "https://yale-redcap.github.io/yes3-exporter2-docs/guides/technical-guide/";
    }

    public function getOverviewDocumentationUrl(){

        return "https://yale-redcap.github.io/yes3-exporter2-docs/getting-started/";
    }

    private function get_project_props()
    {
        if ( $this->project_id > 0 ) return;

        $this->project_id = $this->getProjectId();

        $Proj = new Project( $this->project_id );

        $this->date_shift_max = (int)($Proj->project['date_shift_max'] ?? 0);

        $this->project_salt = $Proj->project['__SALT__'] ?? '';
    }

    /**
     * Build the export data dictionary
     * 
     * The export data dictionary is an array of associative arrays, one per export column
     * It is built from the export specification as follows:
     * 
     * export specification -> export object -> export items -> data dictionary.
     * 
     * Too much invested in the legacy 'array of arrays' approach to refactor to 'array of objects' at this time.
     * 
     * The export specification, export object and data dictionary are all returned to the caller.
     * 
     * function: buildExportDataDictionary
     * @param mixed $export_uuid
     */

    public function buildExportDataDictionary( $export_uuid )
    {
        /**
         * Event settings
         * 
         * [ {event_id, event_name, event_prefix}, ... ]
         * 
         * event_name is REDCap unique event name ("screen_arm_1")
         * 
         */
        $event_settings = $this->getEventSettings(); /* evcen abbreviations */
    
        /**
         * Export specification (assoc array):
         * 
         *      export_uuid
         *      export_name
         *      export_username
         *      export_layout
         *      export_multiselect
         *      export_selection
         *      export_criterion_field
         *      export_criterion_event
         *      export_criterion_value
         *      export_target
         *      export_max_label_length
         *      export_max_text_length
         *      export_inoffensive_text
         *      export_no_tags
         *      export_ascii_text
         *      export_remove_phi
         *      export_remove_freetext
         *      export_remove_largetext
         *      export_remove_dates
         *      export_shift_dates
         *      export_hash_recordid
         *      export_hash_recordid_legacy
         *      export_uspec_json DEPRECATED
         *      export_items_json
         *      export_batch
         *      export_sascode
         *      export_sascode_ascii
         *      export_sascode_libref
         *      export_sascode_libref_path
         *      export_sascode_dsname
         *      export_file_type
         *      export_code_filename_base
         *      export_rcode
         *      export_has_repeatables

         *      removed
         *      
         */
        $export_specification = $this->getExportSpecification($export_uuid);

        // add filesystem target, which is stored in EM settings
        $export_specification['export_target_folder'] = $this->get_export_target_folder();


        //$this->logDebugMessage($this->getProjectId(), print_r($export_specification, true), "buildExportDataDictionary");

        /**
         * export object
         * 
         */
        $export = new Yes3Export( $export_specification ); 
    
        /**
         * 
         * form_metadata: list of form objects
         *      {
         *      form_name,
         *      form_label,
         *      form_events: list of event objects {event_id, event_label},
         *      form_fields: list of field names,
         *      form_repeating: 1 or 0
         *      }
         * 
         * form_index: list index, keyed by form_name
         * 
         */
        $forms = $this->getFormMetadataStructures();

        // set the export 
    
        /**
         * 
         * field_metadata: list of field objects
         *      {
         *          field_name
         *          field_label
         *          form_name
         *          field_type: REDCap element_type
         *          field_validation
         *          field_phi
         *          field_valueset: list of valueset objects {value, label}
         *      }
         * 
         * field_index: list index, keyed by field_name
         * 
         */
        $fields = $this->getFieldMetadataStructures();

        /**
         * uRights: a curated set of rights and permissions
         * 
         *  username
         *  isDesigner
         *  isSuper
         *  group_id
         *  dag:    unique group name
         *  export: data_export_tool permission
         *  import: data_import_tool permission
         *  api_export
         *  api_import
         *  form_permissions: assoc array of (1,0) read permission, keyed by form_name
         * 
        */
        $uRights = $this->yes3UserRights();

        if ( !$uRights['exporter'] ){

            throw new Exception("ERROR: User does not have permission to export data.");
        }
        /*
        $allowed = [
            'group_id' => 0,
            'forms' => [],
            'phi' => ( $export->export_remove_phi ) ? 0 : (($uRights['export']==1) ? 1:0),
            'dates' => ( $export->export_remove_dates ) ? 0 : (($uRights['export']==1 || $uRights['export']==3) ? 1:0),
            'smalltext' => ( $export->export_remove_freetext ) ? 0 : (($uRights['export']==1 || $uRights['export']==3) ? 1:0),
            'largetext' => ( $export->export_remove_freetext || $export->export_remove_largetext) ? 0 : (($uRights['export']==1 || $uRights['export']==3) ? 1:0)
        ];
        */
        $allowed = [
            'group_id' => 0,
            'forms' => [],
            'phi' => ( $export->export_remove_phi ) ? 0 : 1,
            'dates' => ( $export->export_remove_dates ) ? 0 : 1,
            'smalltext' => ( $export->export_remove_freetext ) ? 0 : 1,
            'largetext' => ( $export->export_remove_freetext || $export->export_remove_largetext) ? 0 : 1
        ];

        /**
         * list of forms for which the user has at least some export rights
         */
        foreach ($uRights['form_export_permissions'] as $form_name => $xPerm ){

            if ( (int)$xPerm > 0 ){

                $allowed['forms'][] = $form_name;
            }
        }

        if ( $uRights['group_id'] ){

            $allowed['group_id'] = $uRights['group_id'];
        }
/*
        //$this->logDebugMessage($this->getProjectId(), print_r($export_specification, true), "buildExportDataDictionary: export_specification");
        //$this->logDebugMessage($this->getProjectId(), print_r($export, true), "buildExportDataDictionary: export");
        //$this->logDebugMessage($this->getProjectId(), print_r($allowed, true), "buildExportDataDictionary: allowed");
        throw new Exception("Have a nice day");
*/   
        /**
         * DATA DICTIONARY
         * 
         * {
         *      var_name
         *      var_type ( INTEGER, FLOAT, NOMINAL, TEXT, DATE, DATETIME, TIME )
         *      var_label
         *      valueset [{value, label}, ...] as JSON string (NOMINAL only)
         *      events [event_id, ...]
         * 
         *      for computation:
         * 
         *      source_field
         *      source_form
         *      origin ('specification', 'redcap')
         *      spec_value_map [{spec_value, redcap_value}, ... ] as JSON string
         * 
         *      for validation / code generation:
         * 
         *      non_missing_count
         *      min_length (TEXT)
         *      max_length (TEXT)
         *      min_value
         *      max_value
         *      sum_of_values
         *      sum_of_squared_values
         *      frequency_table (JSON)
         *      repeatable (1,0)
         * }
         * 
         */

        /**
         * Start with recordid field; always the first column
         */
        $field_name = $this->getRecordIdField();

        $this->addExportItem_REDCapField( $export, $field_name, $this->getREDCapEventIdForField($field_name), $fields, $forms, $event_settings, $allowed, $uRights['form_export_permissions'] );

        if ( $groupNames = $this->getGroupNames() ) {

            $dag_valueset = [];
            foreach ($groupNames as $value => $label) {
                $dag_valueset[] = [
                    'value' => Yes3Fn::sanitizeForText((string)$value, 0, true, false, true),
                    'label' => Yes3Fn::sanitizeForLabel($label, Yes3Fn::MAX_LABEL_LEN)
                ];
            }

            $this->addExportItem_otherProperty($export, self::VARNAME_GROUP_ID,   "REDCap Data Access Group Id", "NOMINAL", $dag_valueset);
            $this->addExportItem_otherProperty($export, self::VARNAME_GROUP_NAME, "REDCap Data Access Group Name", "TEXT");
        }

        if ( $export->export_layout !== "h" && $this->isLongitudinal() ) {

            $event_valueset = [];
            if ($eventNames = $this->getEventNames(true)) {
                foreach ($eventNames as $value => $label) {
                    $event_valueset[] = [
                        'value' => Yes3Fn::sanitizeForText((string)$value, 0, true, false, true),
                        'label' => Yes3Fn::sanitizeForLabel($label, Yes3Fn::MAX_LABEL_LEN)
                    ];
                }
            }

            $this->addExportItem_otherProperty($export, self::VARNAME_EVENT_ID,   "REDCap Event Id", "NOMINAL", $event_valueset);
            $this->addExportItem_otherProperty($export, self::VARNAME_EVENT_NAME, "REDCap Event Name", "TEXT");
        }

        if ( isset($export_specification['export_has_repeatables']) && $export_specification['export_has_repeatables'] ) {

            $this->addExportItem_otherProperty($export, self::VARNAME_INSTANCE, "REDCap Repeat Instance", "INTEGER");
        }

        $export_items = json_decode( $export_specification['export_items_json'], true);

        //$export_uspec = json_decode( $export_specification['export_uspec_json'], true);
    
        foreach ($export_items as $element){
    
            if ( $element['redcap_field_name'] ) {
    
                $this->addExportItem_REDCapField( $export, $element['redcap_field_name'], $element[self::VARNAME_EVENT_ID], $fields, $forms, $event_settings, $allowed, $uRights['form_export_permissions'] );
            }
    
            elseif ( $element['redcap_form_name'] ) {
    
                $this->addExportItem_REDCapForm( $export, $element['redcap_form_name'], $element[self::VARNAME_EVENT_ID], $fields, $forms, $event_settings, $allowed, $uRights['form_export_permissions'] );
            }
        }

        $dd = []; // data dictionary array of arrays

        $fields_rejected = 0; // always zero: we don't dynamically remove columns any more

        //$this->logDebugMessage($this->getProjectId(), print_r($uRights['form_permissions'], true), "buildExportDataDictionary:fp");
        //$this->logDebugMessage($this->getProjectId(), print_r($export->export_items, true), "buildExportDataDictionary:fp");

        foreach( $export->export_items as $export_item ){

            $dd[] = [
                'var_name' => $export_item->var_name,
                'var_label' => $this->truncate( $export_item->var_label, $export->export_max_label_length ),
                'var_type' => $export_item->var_type,
                'valueset' => ( $export_item->valueset ) ? json_encode($export_item->valueset) : "",
                'origin' => $export_item->origin,
                'redcap_field_name' => $export_item->redcap_field_name,
                'redcap_source_option' => $export_item->multiselect_option,
                'redcap_form_name' => $export_item->redcap_form_name,
                'redcap_events' => json_encode($export_item->redcap_events),
                self::VARNAME_EVENT_ID => $export_item->redcap_event_id,
                self::VARNAME_EVENT_NAME => $export_item->redcap_event_name,
                'non_missing_count' => $export_item->non_missing_count,
                'min_length' => $export_item->min_length,
                'max_length' => $export_item->max_length,
                'min_value' => $export_item->min_value,
                'max_value' => $export_item->max_value,
                'sum_of_values' => $export_item->sum_of_values,
                'sum_of_squared_values' => $export_item->sum_of_squared_values, 
                'mean' => $export_item->mean, 
                'standard_deviation' => $export_item->standard_deviation, 
                'formatted_min_value' => $export_item->formatted_min_value,
                'formatted_max_value' => $export_item->formatted_max_value,
                'formatted_mean' => $export_item->formatted_mean,
                'frequency_table' => $export_item->frequency_table,
                'repeatable' => $export_item->repeatable,
                'hashed' => $export_item->hashed
            ];
        }

        // nuke the export_items to save memory
        $export->export_items = [];

        return [
            'export_uuid' => $export_uuid,
            'export' => $export,
            /*
            'export_name' => $export->export_name,
            'export_label' => $export->export_label,
            'export_order' => $export->export_order,
            'export_layout' => $export->export_layout,
            'export_batch' => $export->export_batch,
            'export_rcode' => $export->export_rcode,
            'export_sascode' => $export->export_sascode,
            'export_sascode_ascii' => $export->export_sascode_ascii,
            'export_sascode_libref' => $export->export_sascode_libref,
            'export_sascode_libref_path' => $export->export_sascode_libref_path,
            'export_sascode_dsname' => $export->export_sascode_dsname,
            'export_file_type' => $export->export_file_type,
            'export_data_extension' => $export->export_data_extension,
            'export_data_delimiter' => $export->export_data_delimiter,
            'export_code_filename_base' => $export->export_code_filename_base,
            'export_multiselect' => $export->export_multiselect,
            'export_selection' => $export->export_selection,
            'export_criterion_field' => $export->export_criterion_field,
            'export_criterion_event' => $export->export_criterion_event,
            'export_criterion_value' => $export->export_criterion_value,
            'export_target' => $export->export_target,
            'export_target_folder' => $export->export_target_folder,
            'export_max_label_length' => $export->export_max_label_length,
            'export_max_text_length' => $export->export_max_text_length,
            'export_inoffensive_text' => $export->export_inoffensive_text,
            'export_no_tags' => $export->export_no_tags,
            'export_ascii_text' => $export->export_ascii_text,
            'export_hash_recordid' => $export->export_hash_recordid,
            'export_hash_recordid_legacy' => $export->export_hash_recordid_legacy,
            'export_shift_dates' => $export->export_shift_dates,
            'export_event_list' => $export->export_event_list,
            'export_has_repeatables' => $export->export_has_repeatables,
            */
            'export_group_id' => $allowed['group_id'],
            'export_specification' => $export_specification,
            'export_data_dictionary' => $dd,
            'export_fields_rejected' => $fields_rejected
        ];

        //$this->download($export->export_name . "_dd", $dd);
    
        //return count($dd) . " export columns defined.";
    }

    /**
     * Returns a non-assoc array suitable for CSV export
     * Utility columns removed
     * 
     * function: dataDictionaryForExport
     * 
     * @param mixed $dd
     * @param mixed $export_layout
     * 
     * @return void
     */
    private function dataDictionaryForExport( $dd, $export_layout, $dd_only_download=false):array
    {
        // columns that must be removed if this a dd-only download
        $dx_colnames = [
            'non_missing_count',
            'min_length',
            'max_length',
            'min_value',
            'max_value',
            'sum_of_values',
            'sum_of_squared_values', 
            'mean', 
            'standard_deviation', 
            'formatted_min_value',
            'formatted_max_value',
            'formatted_mean',
            'frequency_table'  
        ];

        // delete event columns as needed
        $columns_to_delete = [];

        $colnames = array_keys($dd[0]);

        $xx = [
            []
        ];

        for ($i=0; $i<count($colnames); $i++){

            if ( $colnames[$i]==="redcap_events") {

                $columns_to_delete[] = $i;
            }

            elseif ( $dd_only_download && in_array($colnames[$i], $dx_colnames) ){

                $columns_to_delete[] = $i;
            }

            elseif ( $export_layout !== "h" && ($colnames[$i]==="redcap_event_id" || $colnames[$i]==="redcap_event_name")){

                $columns_to_delete[] = $i;  
            }

            else {

                $xx[0][] = $colnames[$i];
            }
        }

        foreach ($dd as $dditem ){

            $v = array_values($dditem);

            $x = [];

            for ($i=0; $i<count($v); $i++){

                if ( !in_array($i, $columns_to_delete) ){

                    $x[] = Yes3Fn::sanitizeForExcel($v[$i]);
                }
            }

            $xx[] = $x;
        }

        return $xx;
    }
    
    private function writeCodeFile($export_name, $export_target_folder, $destination, $code, $code_type, $extension)
    {
        if ( !$code ) return $this->genCodeReturnObjectFail("FAIL: Could not generate {$code_type} code file.");

        $path = ""; // temp (for downloads) or file system path will be set by export_file_handle()

        $codeFileName = $this->exportCodeFilename($export_name, $code_type, $extension, $destination); // may be timestamped

        $codeFilenameBase = $this->exportCodeFilename($export_name, $code_type, $extension, $destination, null);  // no timestamp, just the base name (for zipped payloads and filesystem exports)

        $h = $this->export_file_handle( $path, $destination, $export_target_folder, $codeFileName, false );

        $bytesWritten = fwrite($h, $code);
        
        fclose($h);

        if ( $bytesWritten === false ) {

            return $this->genCodeReturnObjectFail("FAIL: Could not write {$code_type} code file to {$path}.");
        }

        return $this->genCodeReturnObjectSuccess($path, $code, $codeFilenameBase);
    }

    private function genCodeReturnObjectSuccess($path, $code, $codeFilenameBase)
    {
        $codeLineCount = substr_count($code, "\n");
        $bytesWritten = strlen($code);

        return [
            'result' => Yes3Fn::STD_RETURN_OBJECT_SUCCESS,
            'path' => $path,
            'code_filename_base' => $codeFilenameBase,
            'message' => "{$bytesWritten} bytes and {$codeLineCount} lines were written to {$codeFilenameBase}."
        ];
    }

    private function genCodeReturnObjectFail($message)
    {
        return [
            'result' => Yes3Fn::STD_RETURN_OBJECT_FAIL,
            'path' => "",
            'code_filename_base' => "",
            'message' => $message
        ];
    }

    private function writeSasCodeInputFile($export_name, $export_target_folder, $export_data_extension, $destination, $Sascoder)
    {
        $infile_path = $this->exportDataFilename($export_name, $export_data_extension, SELF::DESTINATION_FILESYSTEM);
        $code = $Sascoder->genInputCode( $infile_path );

        return $this->writeCodeFile($export_name, $export_target_folder, $destination, $code, "input", "sas");
    }
    
    private function writeSasCodeFormatsCreateFile($export_name, $export_target_folder, $destination, $Sascoder)
    {
        $code = $Sascoder->genFormatsCreateCode();

        return $this->writeCodeFile($export_name, $export_target_folder, $destination, $code, "fmtlib_create", "sas");
    }
    
    private function writeSasCodeFormatsAssignFile($export_name, $export_target_folder, $destination, $Sascoder){

        $code = $Sascoder->genFormatsAssignCode();

        return $this->writeCodeFile($export_name, $export_target_folder, $destination, $code, "fmtlib_assign", "sas");
    }

    private function writeExportInfoFile(
        Yes3Export $export,
        //$export_name, 
        //$export_target_folder, 
        //$export_uuid, 
        //$export_layout, 
        $bytesWritten, 
        $R, 
        $C, 
        $data_file_path, 
        $destination){

        /*
        if ( !$export_target_folder || $destination===self::DESTINATION_DOWNLOAD ) {

            $root = sys_get_temp_dir();
            $path = tempnam($root, "ys3");

        }
        else {

            if ( substr($export_target_folder, -1) !== DIRECTORY_SEPARATOR ){

                $export_target_folder .= DIRECTORY_SEPARATOR;
            }

            $root = $export_target_folder;
            $path = $root . $this->exportInfoFilename($export_name, $destination);           
        }

        $h = $this->fopen_w_utf8( $path, "w", $root );

        if ( $h===false ){

            throw new Exception("Fail: could not create export file {$path}");
        }        
        */

        $path = "";

        // note that the JSON spec forbids the UTF8 BOM, so we do not write it here.
        $export_filename = $this->exportInfoFilename($export->export_name, $destination);
        $h = $this->export_file_handle( $path, $destination, $export->export_target_folder, $export_filename, false );

        $project = $this->getProject();

        // human-readable timestamp
        $timestamp = date("Y-m-d H:i:s");

        $export_props = $this->get_export_props($export);

        $info = [
            "host" => APP_PATH_WEBROOT_FULL,
            "timestamp" => $timestamp,
            "project_id" => $project->getProjectId(),
            "export_properties" => $export_props,
            "project_recordid_field" => $this->getRecordIdField(),
            "project_title" => $project->getTitle(),
            "project_is_longitudinal" => ( $this->isLongitudinal() ) ? 1:0,
            "project_has_dags" => ( $this->getGroupNames() !== FALSE ) ? 1:0,
            "path" => $data_file_path,
            "bytes_written" => $bytesWritten,
            "columns" => $C,
            "rows" => $R,
            "destination" => $destination,
            "notification_email" => $this->getAdminEmail(),
            "username" => $this->getAdminUsername()
        ];

        $json = $this->json_encode_pretty($info);

        $bytesWritten = fwrite($h, $json);

        fclose($h);

        unset($info['export_properties']); // too much detail for the return object

        return [
            'export_info_filename' => $path,
            'export_info_message' => "Success: {$bytesWritten} bytes written to {$path}.",
            'export_info_file_size' => $bytesWritten,
            'export_info' => $info,
            'export_info_timestamp' => $timestamp
        ];
    }

    private function writeExportDataDictionaryFile( $export_name, $export_target_folder, $dd, $destination, $export_layout, &$bytesWritten=0 )
    {
        /*
        if ( !$export_target_folder || $destination===self::DESTINATION_DOWNLOAD ) {

            $path = tempnam(sys_get_temp_dir(), "ys3");
        }
        else {

            if ( substr($export_target_folder, -1) !== DIRECTORY_SEPARATOR ){

                $export_target_folder .= DIRECTORY_SEPARATOR;
            }

            $path = $export_target_folder . $this->exportDataDictionaryFilename($export_name, "filesystem");           
        }

        //$h = fopen( $path, "w" );
        $h = $this->fopen_w_utf8( $path );

        if ( $h===false ){

            throw new Exception("Fail: could not create export file {$path}");
        }

        */
        
        $path = "";

        // we include the UTF8 BOM in the data dictionary file, so that it can be opened in Excel without issues.
        $export_filename = $this->exportDataDictionaryFilename($export_name, $destination);
        $h = $this->export_file_handle( $path, $destination, $export_target_folder, $export_filename, true );

        $R = 0;

        $xx = (array) $this->dataDictionaryForExport($dd, $export_layout);

        $C = count($xx[0]);
           
        $bytesWritten = 0;
     
        foreach ( $xx as $x ) {

            $bytesWritten += Yes3Fn::fputcsv($h, $x,",");
            $R++;
        }
     
        fclose($h);

        return [
            'export_data_dictionary_message' => "Success: {$bytesWritten} bytes, {$R} rows and {$C} columns written to {$path}.",
            'export_data_dictionary_filename' => $path,
            'export_data_dictionary_file_size' => $bytesWritten,
            'export_data_dictionary_rows' => $R-1, // first row is the header row
            'export_data_dictionary_columns' => $C,
        ];
    }

    /**
     * file handle for downloading or exporting data
     * If $destination is self::DESTINATION_DOWNLOAD, the file is created in the PHP temp dir and later downloaded.
     * Otherwise, the file is created in the export_target_folder (e.g., an automounted folder on the REDCap server).
     * 
     * @param mixed $path 
     * @param mixed $destination 
     * @param mixed $export_target_folder 
     * @param string $filename 
     * @param bool $utf8_bom 
     * @return resource 
     * @throws Exception 
     */
    private function export_file_handle( &$path, &$destination, $export_target_folder, $filename="", $utf8_bom = true)
    {
        if ( !$export_target_folder || $destination===self::DESTINATION_DOWNLOAD ) {

            $root = sys_get_temp_dir();
            $path = tempnam($root, "ys3");
        }
        else {

            $root = $export_target_folder;
            if ( substr($export_target_folder, -1) !== DIRECTORY_SEPARATOR ){

                $root .= DIRECTORY_SEPARATOR;
            }
            $path = $root . $filename;   
        }

        return $this->fopen_w_safe( $path, "w", $root, $utf8_bom );
    }

    public function fopen_w_safe( $filename, $mode="w", $root = "", $utf8_bom=true )
    {
     
        $h = fopen( $this->getSafePath($filename, $root), $mode );

        if ( $h===false ){

            throw new Exception("fopen_w_safe: could not open file " . $filename . "\n\nerror: " . print_r(error_get_last() . "\n\n", true));
        }

        if ( $utf8_bom ){

            fwrite($h, "\xEF\xBB\xBF" );
        }

        return $h;
    }

    /**
     * fopen_r_safe
     * 
     * @param mixed $path   filesystem path of file to open
     * @return resource 
     * @throws Exception 
     */
    public function fopen_r_safe( $path )
    {
        $h = fopen(  $this->getSafePath($path, sys_get_temp_dir()), 'r' );

        if ( $h===false ){

            throw new Exception("Fail: could not open file " . $this->getSafePath($path, sys_get_temp_dir()) );
        }

        return $h;
    }

    public function get_export_target_folder()
    {
        $enable_host_filesystem_exports = $this->getProjectSetting("enable-host-filesystem-exports") ? 1 : 0;

        if ( !$enable_host_filesystem_exports ){

            return "";
        }

        $etf = $this->getProjectSetting("export-target-folder");

        if ( !$etf ) $etf = "";

        return $etf;
    }

    /**
     * writeExportFiles
     * 
     * This beast is way too long, but it is the main download/filesystem export function.
     * 
     * It writes the data file, the data dictionary file, the info file and optionally the SAS code files.
     * * The data file is written in UTF8 with BOM, so that it can be opened in Excel without issues.
     * * The data dictionary file is written in UTF8 with BOM, so that it can be opened in Excel without issues.
     * * The info file is written in JSON format, without BOM.
     * * The SAS code files are written in UTF8 without BOM.
     * * The data file is not written for support packages, only the info, dictionary and sascode (if requested) files.
     * 
     * Files are optionally written to the export_target_folder, which is set in the EM settings, 
     * or to the PHP temp dir if the destination is self::DESTINATION_DOWNLOAD.
     * 
     * This function is called by:
     * * Yes3Exporter::exportData() - for filesystem exports of data, data dictionary and info files
     * * Yes3Exporter::downloadData() - for data downloads
     * * Yes3Exporter::downloadZip() - foe zip (package) downloads
     * 
     * @param mixed &$ddPackage The data dictionary package. See Yes3Exporter::buildExportDataDictionary()
     * @param string $destination See the Yes3Exporter::DESTINATION_* constants.
     * @param int &$bytesWritten size of the data file written, in bytes.
     * @param bool $support_package If true, the data file is not written, only the info, dictionary and sascode (if requested) files.
     * @param bool $sascode If true, the SAS code files are written.
     * @return array{export_data_message: string, export_data_filename: string, export_data_file_size: mixed, export_data_items: int, export_data_rows: int, export_data_columns: int, export_data_dictionary_message: string, export_data_dictionary_filename: mixed, export_data_dictionary_file_size: mixed, export_info_message: string, export_info_filename: string, export_info_file_size: int|false, export_info: array{host: mixed, timestamp: mixed, project_id: mixed, project_recordid_field: mixed, project_title: mixed, project_is_longitudinal: int, project_has_dags: int, export_name: mixed, export_layout: mixed, export_uuid: mixed, export_target_folder: mixed, path: mixed, bytes_written: mixed, columns: mixed, rows: mixed, destination: mixed, notification_email: mixed, username: mixed}, export_info_timestamp: mixed} 
     * @throws Exception 
     */
    private function writeExportFiles( &$ddPackage, $destination="", &$bytesWritten=0, $support_package=false )
    {
        // ensure project props are set (salt, days_shift_max, etc)
        $this->get_project_props();

        /** @var Yes3Export $export */
        $export = $ddPackage['export'];

        //$export_uuid                = $ddPackage['export_uuid'] ?? "";
        //$export_label               = $ddPackage['export_label'] ?? "";
        //$export_name                = $ddPackage['export_name'] ?? "";
        //$export_target_folder       = $this->get_export_target_folder() ?? "";
        //$export_layout              = $ddPackage['export_layout'];
        //$export_max_text_length     = (int)($ddPackage['export_max_text_length'] ?? 0);
        //$export_inoffensive_text    = (int)($ddPackage['export_inoffensive_text'] ?? 0);
        //$export_no_tags             = (int)($ddPackage['export_no_tags'] ?? 0);
        //$export_ascii_text          = (int)($ddPackage['export_ascii_text'] ?? 0);
        //$export_shift_dates         = (int)($ddPackage['export_shift_dates'] ?? 0);
        $export_group_id            = (int)($ddPackage['export_group_id'] ?? 0);
        //$export_hash_recordid       = (int)($ddPackage['export_hash_recordid'] ?? 0);
        //$export_event_list          = $ddPackage['export_event_list'] ?? [];
        $export_specification       = $ddPackage['export_specification'] ?? [];
        //$export_has_repeatables     = (int)($ddPackage['export_has_repeatables'] ?? 0);
        //$export_sascode            = (int)($ddPackage['export_sascode'] ?? 0);
        //$export_sascode_ascii       = (int)($ddPackage['export_sascode_ascii'] ?? 0);
        //$export_sascode_libref      = $ddPackage['export_sascode_libref'] ?? "";
        //$export_sascode_libref_path = $ddPackage['export_sascode_libref_path'] ?? "";
        //$export_sascode_dsname      = $ddPackage['export_sascode_dsname'] ?? "";
        //$export_file_type           = $ddPackage['export_file_type'] ?? "csv";
        //$export_data_extension      = $ddPackage['export_data_extension'] ?? "csv";
        //$export_data_delimiter      = $ddPackage['export_data_delimiter'] ?? ",";
        //$export_code_filename_base  = $ddPackage['export_code_filename_base'] ?? "";

        $export_data_filename = $this->exportDataFilename($export->export_name, $export->export_data_extension, $destination);

        $dd = $ddPackage['export_data_dictionary'] ?? [];

        $redcap_data = $this->getDataTable();

        $path = "";

        // data file is not written for support packages
        if ( $support_package ) {

            $h = false;
        } else {

            // we include the UTF8 BOM in the data file, so that it can be opened in Excel without issues.
            $h = $this->export_file_handle( $path, $destination, $export->export_target_folder, $export_data_filename, true );
        }
        /**
         * build an assoc array for rapid event name resolution
         */
        $eventSpecs = $this->getEventSettings();

        $eventName = [];

        foreach( $eventSpecs as $eventSpec){

            $eventName[$eventSpec['event_id']] = $eventSpec['event_name'];
        }

        /**
         * get an assoc array of dag names
        **/

        $dagNameForGroupId = $this->getGroupNames(true);
        if ( !$dagNameForGroupId ){

            $dagNameForGroupId = [];
        }

        //$spec = $this->getExportSpecification( $export_uuid );

        /**
         * DD 'helper' index arrays
         * 
         * A dd row corresponds to a column in the export file. 
         * The helper arrays allow fast retrieval of a column's metadata during record processing.
         * Presumably this is a performance optimization over a linear search through the dd array.
         * 
         * Two arrays are created. The first is for regular REDCap fields, the second is for multiselect fields.
         * The value returned is the index of the row in the master dd array.
         * 
         *   (1) dd_index: for redcap fields that are not multiselects, keyed by:
         *       vertical layouts: field_name
         *       horizontal layouts: field_name and event_id
         * 
         *   (2) dd_multiselect_index: for multiselect fields, keyed by:
         *       vertical layouts: field_name and multiselect option value
         *       horizontal layouts: field_name, event_id and multiselect option value
         */

        //$dd_specmap_index = []; // deprecated, a leftover from the old NIACROMS crosswalk code, retained in case crosswalk is re-introduced

        $dd_index = []; // dd index for a given REDCap field_name and event_id (horizontal) or field_name (vertical)
        $dd_multiselect_index = []; // dd index for a multiselect option 

        //$this->logDebugMessage($this->getProjectId(), print_r($dd, true), "writeExportDataFile: dd");

        for ($i=0; $i<count($dd); $i++){

            if ( $export->export_layout==="h" ){

                if ( $dd[$i]['redcap_field_name'] && $dd[$i][self::VARNAME_EVENT_ID] && is_numeric($dd[$i][self::VARNAME_EVENT_ID]) ){

                    //if ( $dd[$i]['origin'] === "redcap" ){

                        $dd_index[$dd[$i]['redcap_field_name']][$dd[$i][self::VARNAME_EVENT_ID]] = $i;
    
                        if ( $this->ddIsMultiselect($dd[$i]) ){

                            $dd_multiselect_index[$dd[$i]['redcap_field_name']][$dd[$i][self::VARNAME_EVENT_ID]][$dd[$i]['redcap_source_option']] = $i;
                        }
                    //}

                    //elseif ( $dd[$i]['origin'] === "specification" ){
                    //
                    //    $dd_specmap_index[$dd[$i]['redcap_field_name']][$dd[$i][self::VARNAME_EVENT_ID]] = $i;
                    //}
                }
            }
            else {

                if ( $dd[$i]['redcap_field_name'] ){

                    //if ( $dd[$i]['origin'] === "redcap" ){

                        if ( $this->ddIsMultiselect($dd[$i]) ){

                            $dd_multiselect_index[$dd[$i]['redcap_field_name']][$dd[$i]['redcap_source_option']] = $i;
                        }
                        else {

                            $dd_index[$dd[$i]['redcap_field_name']] = $i;
                        }
                    //}

                    //elseif ( $dd[$i]['origin'] === "specification" ){

                    //    $dd_specmap_index[$dd[$i]['redcap_field_name']] = $i;
                    //}
                }
            }

            /**
             * The valueset for multiselects are stored as JSON strings.
             * We will decode them here, so that they can be used to build the frequency tables that are part of the exported data dictionary,
             * and which are accumulated during the record processing.
             */
            if ( $dd[$i]['valueset'] && is_string($dd[$i]['valueset']) ){

                $dd[$i]['valueset'] = json_decode($dd[$i]['valueset'], true);
            }
        }

        /**
         * BUILDING THE LIST OF RECORDS TO EXPORT
         * 
         * Assemble the SELECT query and event params to be passed to the record writer.
         */
        $sqlSelect = "SELECT d.* FROM $redcap_data d WHERE d.`project_id`=? AND d.`record`=?";

        $sqlEvent = "";
        $sqlEventParams = [];
        $sqlOrderBy = "";

        // from the dd: the combined list of events from all of the export items
        if ( $export->export_event_list ){
            
            $sqlEvent = "AND d.`event_id` IN(";

            for($e=0; $e<count($export->export_event_list); $e++){

                $sqlEvent .= ( $e===0 ) ? "?":",?";

                $sqlEventParams[] = $export->export_event_list[$e];
            }

            $sqlEvent .= ")";
        }
        /*
        if ( $ddPackage['export_layout']==="r" ){

            $sqlOrderBy = " ORDER BY d.`event_id`, d.`instance`";
        }
        else if ( $ddPackage['export_layout']==="v" ){

            $sqlOrderBy = "ORDER BY d.`event_id`";
        }
        */

        /**
         * WITHIN-RECORD SORT ORDER
         * repeating layout (DEPRECATED): breaks on event, instance
         * vertical layout: breaks on event, instance
         * default (=horizontal) layout: breaks on instance
         */

        if ( $export->export_layout==="r" ){

            $sqlOrderBy = " ORDER BY d.`event_id`, d.`instance`";
        }
        else if ( $export->export_layout==="v" ){

            $sqlOrderBy = "ORDER BY d.`event_id`, d.`instance`";
        }
        else {

            $sqlOrderBy = " ORDER BY d.`instance`, d.`event_id`";
        }

        if ( $sqlEvent ){

            $sqlSelect .= "\n" . $sqlEvent;
        }

        if ( $sqlOrderBy ){

            $sqlSelect .= "\n" . $sqlOrderBy;
        }

        /**
         * Assemble the list of records to include
         */

        $sqlParams = [$this->getProjectId()];

        // selection by criterion, possibly within a DAG
        if ( $export->export_selection=='2' ) {

            if ( !strlen($export->export_criterion_field) ||
                !strlen($export->export_criterion_event) ||
                !strlen($export->export_criterion_value) ){

                throw new Exception("Cannot proceed with the export or download, because the selection field, event and/or value is missing.");
            }
            /*
            if ( $export_layout==="h"){

                $critXFieldMetadata = $dd[$dd_index[$ddPackage['export_criterion_field'][$ddPackage['export_criterion_event']]]];
            }
            else {

                $critXFieldMetadata = $dd[$dd_index[$ddPackage['export_criterion_field']]];
            }
            */
            $critXOperators = [ ">=", "<=", "<>", "=", "<", ">"];

            $sqlCritXParams = [];

            $critXStr = trim( $export->export_criterion_value );

            $critXOp = "="; // default operator for the SELECT query
            $critXVal = $critXStr; // the value applied to the operator

            $critXQ = ""; // the query expression to be determined

            /**
             * lists of comma-separated values are allowed
             */
            if ( strpos($critXVal, ',') !== false ){

                $valParts = explode(",", $critXVal);

                $critXQList = "";

                foreach ( $valParts as $val){

                    $critXQList .= (( $critXQList ) ? "," : "") . "?";
                    $sqlCritXParams[] = $val;
                }
                $critXQ = "IN({$critXQList})";
            }
            else {

                foreach ($critXOperators as $op){
                    if (strpos($critXStr, $op)===0){
                        $critXOp = $op;
                        $critXVal = trim(substr($critXStr, strlen($critXOp)));
                        break;
                    }
                }

                if ( $critXOp==="<>" && ( $critXVal==="" || $critXVal==="''" || $critXVal==='""' ) ){

                    $critXQ = "<>''";
                }
                elseif ( strlen($critXVal)>0 && is_numeric($critXVal) ){

                    $critXQ = $critXOp . intval($critXVal);
                }
                else {

                    $critXQ = $critXOp . "?";
                    $sqlCritXParams[] = $critXVal;
                }
            }

            if ( $export_group_id ){
                /*
                $sql = "
                SELECT DISTINCT d.`record`
                FROM $redcap_data d
                WHERE d.`project_id`=? AND d.`event_id`=? AND d.`field_name`=? AND d.`value` IS NOT NULL AND d.`value` {$critXQ}
                AND d.`record` IN(SELECT DISTINCT dg.`record` FROM $redcap_data dg WHERE dg.`project_id`=? AND dg.field_name='__GROUPID__' AND dg.`value`=?)
                ";
                $sqlParams = array_merge([ $this->getProjectId(), $export->export_criterion_event, $export->export_criterion_field ], $sqlCritXParams, [$this->getProjectId(), $export_group_id]);
                */
                
                $sql = "
                SELECT DISTINCT rl.`record`, rl.dag_id
                FROM redcap_record_list rl 
                INNER JOIN $redcap_data d on d.project_id=rl.project_id AND d.record=rl.record
                WHERE rl.`project_id`=? AND rl.dag_id=? AND d.`event_id`=? AND d.`field_name`=? AND d.`value` IS NOT NULL AND d.`value` {$critXQ}
                ";
                $sqlParams = array_merge([ $this->getProjectId(), $export_group_id, $export->export_criterion_event, $export->export_criterion_field ], $sqlCritXParams);
            }
            else {
                /*
                $sql = "
                SELECT DISTINCT d.`record`
                FROM $redcap_data d
                WHERE d.`project_id`=? AND d.`event_id`=? AND d.`field_name`=? AND d.`value` IS NOT NULL AND d.`value` {$critXQ}";

                $sqlParams = array_merge([$this->getProjectId(), $export->export_criterion_event, $export->export_criterion_field ], $sqlCritXParams);
                */
                
                $sql = "
                SELECT DISTINCT rl.`record`, rl.dag_id
                FROM redcap_record_list rl 
                INNER JOIN $redcap_data d on d.project_id=rl.project_id AND d.record=rl.record
                WHERE rl.`project_id`=? AND d.`event_id`=? AND d.`field_name`=? AND d.`value` IS NOT NULL AND d.`value` {$critXQ}
                ";
                $sqlParams = array_merge([ $this->getProjectId(), $export->export_criterion_event, $export->export_criterion_field ], $sqlCritXParams);
            }
        }
        // no selection criterion, export all records (possibly within a DAG)
        else if ( $export->export_selection=='1' ) {

            if ( $export_group_id ){
                /*
                $sql = "SELECT DISTINCT dg.`record` FROM $redcap_data dg WHERE dg.`project_id`=? AND dg.field_name='__GROUPID__' AND dg.`value`=?";

                $sqlParams = [ $this->getProjectId(), $export_group_id ];
                */
                $sql = "SELECT DISTINCT `record`, `dag_id` FROM redcap_record_list WHERE `project_id`=? AND `dag_id`=?";

                $sqlParams = [ $this->getProjectId(), $export_group_id ];
            }
            else {
                /*
                $sql = "SELECT DISTINCT d.`record` FROM $redcap_data d WHERE d.`project_id`=?";

                $sqlParams = [ $this->getProjectId() ];
                */                
                $sql = "SELECT DISTINCT `record`, `dag_id` FROM redcap_record_list WHERE `project_id`=?";

                $sqlParams = [ $this->getProjectId() ];
            }
        }
        else {

            throw new Exception("Cannot proceed with the export or download, because the record selection option is not specified.");
        }

        //$sql .= " LIMIT 10";

        //$this->logDebugMessage($this->getProjectId(), $sql, "writeExportDataFile: CritX SQL");
        //$this->logDebugMessage($this->getProjectId(), implode(",", $sqlParams), "writeExportDataFile: CritX Params");

        $records = [];
        foreach ( $this->recordGeneratorUnbuffered($sql, $sqlParams) as $x ){

            $records[] = [ 'record' => $x['record'], 'group_id' => $x['dag_id'] ];
        }
    
        // sort the records in natural case-insensitive order
        usort($records, function($a, $b) {

            return strnatcasecmp($a['record'], $b['record']);
        });
        
        /**
         * More helper arrays, required by writeExportDataForRecord()
         */
        $field_events = []; // assoc array of field names and their events, for non-horizontal exports

        $multiselect_fields = []; // list of multiselect fields

        foreach ( $dd as $d ){

            if ( $this->ddIsMultiselect($d) ){

                $multiselect_fields[] = $d['redcap_field_name'];
            }

            if ( $export->export_layout !== "h" ){

                if ( $d['redcap_field_name'] && $d['redcap_events']){

                    $field_events[$d['redcap_field_name']] = json_decode($d['redcap_events'], true);
                }
            }
        }
        /*
        if ( $export_layout !== "h" ){

            foreach ( $dd as $d ){

                if ( $d['redcap_field_name'] && $d['redcap_events']){

                    $field_events[$d['redcap_field_name']] = json_decode($d['redcap_events'], true);
                }
            }
        }
        */

        //$this->logDebugMessage($this->getProjectId(), print_r($field_events, true), 'writeX:field_events');
        //$this->logDebugMessage($this->getProjectId(), $sqlSelect, 'writeX:sqlSelect');
        //$this->logDebugMessage($this->getProjectId(), print_r($sqlEventParams, true), 'writeX:sqlEventParams');

        $K = 0; // datum count
        $R = 0; // export row count
        $C = 0; // col count
        $bytesWritten = 0;

        $maxRecLen = 0;

        foreach ( $records as $recordSpec ){

            $record = $recordSpec['record'];
            $group_id = $recordSpec['group_id'];

            $sqlSelectParams = array_merge([$this->getProjectId(), $record], $sqlEventParams);

            $recLen = $this->writeExportDataForRecord(
                $export,
                $record,
                $group_id,
                $sqlSelect, 
                $sqlSelectParams, 
                $eventName, 
                $dd, 
                $dd_index, 
                //$dd_specmap_index,
                $dd_multiselect_index,
                $field_events,
                $multiselect_fields,
                $dagNameForGroupId, 
                $h, 
                //$export_layout, 
                //$export_max_text_length, 
                //$export_inoffensive_text,
                //$export_no_tags,
                //$export_ascii_text,
                //$export_no_tabs,
                //$export_no_newlines,
                //$export_no_dquotes,
                //$export_hash_recordid,
                //$export_shift_dates,
                //$export_group_id,
                //$export_has_repeatables,
                //$export_data_delimiter,
                $K, 
                $R, 
                $C
            );

            if ( $recLen > $maxRecLen ){

                $maxRecLen = $recLen; // the maximum record length, used for the export info file
            }

            $bytesWritten += $recLen;
        }
        
        $export_data_message = "Success: {$bytesWritten} bytes, {$R} rows and {$C} columns written to {$path}. The maximum record length is {$maxRecLen} characters.";
        
        if ( $h !== false ){

            fclose($h);
        }
        else {

            $R = -1; // signals that the export file was not written (but stats were accumulated for the dd)
        }


        /**
         * DD post-processing
         * 
         * (1) repack the valueset
         * (2) Tidy up the dd validation section
         */

        //$this->tidyUpDD($dd);
    
        $dd = $this->tidyUpDDv2($dd);

        $ddPackage['export_data_dictionary'] = $dd;

        $export_data_dictionary_response = $this->writeExportDataDictionaryFile( $export->export_name, $export->export_target_folder, $dd, $destination, $export->export_layout );

        $export_info_file_response = $this->writeExportInfoFile(
            $export,
            //$export->export_name, 
            //$export->export_target_folder, 
            //$export->export_uuid, 
            //$export->export_layout,
            $bytesWritten,
            $R,
            $C,
            $path,
            $destination
        );

        if ( $export->export_sascode ){

            $Sascoder = new Yes3SasCoder();

            $sasCoderInitializationResponse = $Sascoder->initialize(
                $export_info_file_response['export_info_filename'],
                $export_data_dictionary_response['export_data_dictionary_filename'],
                $export->export_sascode_libref,
                $export->export_sascode_libref_path,
                $export->export_sascode_dsname,
                $export->export_code_filename_base,
                $export->export_sascode_ascii,
                $export->export_data_delimiter,
                $maxRecLen

            );
            /*$this->logDebugMessage(
                $this->getProjectId(),
                print_r($sasCoderInitializationResponse['message'], true),
                "export_sascode_dsname: " . $export_sascode_dsname
            );*/

            $export_sascode_results = [

                'input' => $this->writeSasCodeInputFile($export->export_name, $export->export_target_folder, $export->export_data_extension, $destination, $Sascoder),
                'fmtlib_create' => $this->writeSasCodeFormatsCreateFile($export->export_name, $export->export_target_folder, $destination, $Sascoder),
                'fmtlib_assign' => $this->writeSasCodeFormatsAssignFile($export->export_name, $export->export_target_folder, $destination, $Sascoder)
            ];

            $export_sascode_message = 
                $export_sascode_results['input']['message']         . "\n\n" .
                $export_sascode_results['fmtlib_create']['message'] . "\n\n" .
                $export_sascode_results['fmtlib_assign']['message'];
        }
        else {

            $export_sascode_results = [];
            $export_sascode_message = "SAS code export is not requested.";
        }

        $this->logExport(
            self::LOG_MESSAGE_FILES_WRITTEN,
            $export,
            [
                'destination' => $destination,
                'filename_data' => $path,
                'filename_data_dictionary' => $export_data_dictionary_response['export_data_dictionary_filename'],
                'exported_bytes' => $bytesWritten,
                'exported_items' => $K,
                'exported_rows' => $R,
                'exported_columns' => $C,
                'data_dictionary_rows' => $export_data_dictionary_response['export_data_dictionary_rows']
            ]
        );

        $export_summary_message =
             $this->escape($export_info_file_response['export_info_message']) . "\n\n" .
             $this->escape($export_data_message) . "\n\n" .
             $this->escape($export_data_dictionary_response['export_data_dictionary_message']) . "\n\n" .
             $this->escape($export_sascode_message)
        ;

        $results = [
            'export_uuid'                      => $this->escape($export->export_uuid),
            'export_name'                      => $this->escape($export->export_name),
            'export_data_message'              => $this->escape($export_data_message),
            'export_data_filename'             => $this->escape($path),
            'export_data_file_size'            => (int)$bytesWritten,
            'export_data_items'                => (int)$K,
            'export_data_rows'                 => (int)$R,
            'export_data_columns'              => (int)$C,
            'export_data_dictionary_message'   => $this->escape($export_data_dictionary_response['export_data_dictionary_message']),
            'export_data_dictionary_filename'  => $this->escape($export_data_dictionary_response['export_data_dictionary_filename']),
            'export_data_dictionary_file_size' => $this->escape($export_data_dictionary_response['export_data_dictionary_file_size']),
            'export_info_message'              => $this->escape($export_info_file_response['export_info_message']),
            'export_info_filename'             => $this->escape($export_info_file_response['export_info_filename']),
            'export_info_file_size'            => $this->escape($export_info_file_response['export_info_file_size']),
            'export_info'                      => $this->escape($export_info_file_response['export_info']),
            'export_info_timestamp'            => $this->escape($export_info_file_response['export_info_timestamp']),
            'export_sascode_results'           => $this->escape($export_sascode_results),
            'export_sascode_message'           => $this->escape($export_sascode_message),
            'export_summary_message'           => $this->escape($export_summary_message),
        ];

        /**
         * the results of a download are communicated to the browser by way of a cookie
         */
        //if ( $destination===self::DESTINATION_DOWNLOAD) {

        //    $this->setExportCookie( $export_uuid, json_encode($results) );
        //}

        return $results;
    }

    private function setExportCookie( $value )
    {
        $expires = time() + 30 * 60 * 60; // expires in 30 minutes
        setcookie(
            self::EXPORTER_DOWNLOAD_COOKIE_NAME, 
            $value, 
            $expires,
            "/"
        );
    }

    private function sanitizeForExportCookie( $value )
    {
        return nl2br( $this->escape($value) );
    }

    private function get_export_props( Yes3Export $export )
    {
        $export_props = get_object_vars($export);

        unset($export_props['export_items']); // can be very large, so remove it from the info object
        unset($export_props['export_order']); // not needed here
        unset($export_props['export_event_list']); // not needed here
        unset($export_props['export_target']); // deprecated

        return $export_props;
    }

    private function logExport(
        $message,
        $export,
        $customParams = []
    ) {

        $uRights = $this->yes3UserRights();

        $basicParams = [
            'user_name' => $uRights['username'],
            'user_designer' => $uRights['isDesigner'],
            'user_dag' => $uRights['dag'],
            'log_entry_type' => self::EMLOG_TYPE_EXPORT_LOG_ENTRY,
        ];

        // extract the settings
        if ( $export instanceof Yes3Export ){

            $export_props = $this->get_export_props($export);

            // remove blank values
            foreach( $export_props as $k => $v ){
                if ( empty($v) ){
                    unset($export_props[$k]);
                }
            }
        }
        else {

            $export_props = [];
        }

        $params = array_merge($basicParams, $customParams, $export_props);

        $log_id = $this->log(
            $message,
            $params
        );

        //$this->logDebugMessage($this->getProjectId(), "message: {$message}", "logExport:log_id: {$log_id}");
        //$this->logDebugMessage($this->getProjectId(), print_r($params, true), "logExport:log_id: {$log_id}");

        return $log_id;
    }

    public function getExportLogs($export_uuid, $descending = false, $sinceWhen = 0)
    {
        $pSql = "
SELECT project_id, log_id, timestamp, username, message
    , log_entry_type, destination, export_uuid, export_name
    , filename_data, filename_data_dictionary, filename_zip 
    , exported_bytes, exported_items, exported_rows, exported_columns, export_specification, user_name
WHERE project_id=? AND log_entry_type=?
        ";

        $params = [ 
            $this->getProjectId(), 
            self::EMLOG_TYPE_EXPORT_LOG_ENTRY 
        ];

        if ( $export_uuid ){

            $pSql .= " AND export_uuid=?";

            $params[] = $export_uuid;
        }

        if ( $sinceWhen ){

            $pSql .= " AND TIMEDIFF(timestamp, ?) >= 0";

            $params[] = $sinceWhen;
        }

        if ( $descending ){

            $pSql .= " ORDER BY log_id DESC";
        }
        else {

            $pSql .= " ORDER BY log_id ASC";
        }

        $logRecords = [];

        $result = $this->queryLogs($pSql, $params);

        while ($logRecord = $result->fetch_assoc()){

            $logRecords[] = $logRecord;
        }

        return $logRecords;
    }

    private function tidyUpDD( &$dd, $noCalculations=false )
    {
        //return false;

        //$noCalculations = true; // for testing, no calculations are performed
        
        for ( $i=1; $i<count($dd); $i++ ){ // skip the first row, which is the recordid field and needs no tidying up

            /**
             * dang "complete" fields
             */
            if ( $dd[$i]['redcap_field_name'] === $dd[$i]['redcap_form_name']."_complete" ){

                $dd[$i]['valueset'] = [
                    [
                        'value'=>"0",
                        'label'=>"incomplete"
                    ],
                    [
                        'value'=>"1",
                        'label'=>"unverified"
                    ],
                    [
                        'value'=>"2",
                        'label'=>"complete"
                    ]
                ];
            }     
            
            if ( is_array($dd[$i]['valueset']) && count($dd[$i]['valueset']) > 0 ){

                $dd[$i]['valueset'] = json_encode($dd[$i]['valueset']);
            }
            
            if ( $dd[$i]['min_value']===NULL || $noCalculations ){

                $dd[$i]['min_value'] = "";
                $dd[$i]['max_value'] = "";
                $dd[$i]['mean'] = "";
                $dd[$i]['standard_deviation'] = "";
                $dd[$i]['formatted_min_value'] = "";
                $dd[$i]['formatted_max_value'] = "";
                $dd[$i]['formatted_mean'] = "";
                $dd[$i]['sum_of_values'] = "";
                $dd[$i]['sum_of_squared_values'] = "";

                if ( !$dd[$i]['non_missing_count']  ) {

                    $dd[$i]['min_length'] = "";
                    $dd[$i]['max_length'] = "";
                }

                if ( $noCalculations ){

                    $dd[$i]['frequency_table'] = "";
                    $dd[$i]['non_missing_count'] = "";
                }
            }
            elseif ( $dd[$i]['non_missing_count'] > 0 ) {

                $dd[$i]['mean'] = (float) $dd[$i]['sum_of_values'] / $dd[$i]['non_missing_count'];

                if ( $dd[$i]['non_missing_count'] > 1 ) {

                    $dd[$i]['standard_deviation'] 
                        = (float) sqrt(($dd[$i]['sum_of_squared_values'] - ($dd[$i]['sum_of_values']*$dd[$i]['sum_of_values']/$dd[$i]['non_missing_count']))/($dd[$i]['non_missing_count'] - 1));
                }

                if ( $dd[$i]['var_type']==="DATE" ){

                    $dd[$i]['formatted_min_value'] = strftime("%F", $dd[$i]['min_value']);
                    $dd[$i]['formatted_max_value'] = strftime("%F", $dd[$i]['max_value']);
                    $dd[$i]['formatted_mean']      = strftime("%F", round($dd[$i]['mean']));
                }

                elseif ( $dd[$i]['var_type']==="DATETIME" ){

                    $dd[$i]['formatted_min_value'] = strftime("%F %T", $dd[$i]['min_value']);
                    $dd[$i]['formatted_max_value'] = strftime("%F %T", $dd[$i]['max_value']);
                    $dd[$i]['formatted_mean']      = strftime("%F %T", round($dd[$i]['mean']));
                }

                elseif ( $dd[$i]['var_type']==="TIME" ){

                    $dd[$i]['formatted_min_value'] = strftime("%T", $dd[$i]['min_value']);
                    $dd[$i]['formatted_max_value'] = strftime("%T", $dd[$i]['max_value']);
                    $dd[$i]['formatted_mean']      = strftime("%T", round($dd[$i]['mean']));
                }
            }

            if ( is_array($dd[$i]['frequency_table']) && count($dd[$i]['frequency_table']) > 0 ){

                $frqTbl = [];

                $j = 0;

                foreach($dd[$i]['frequency_table'] as $key=>$count ){
                
                    $frqTbl[$j] = [
                        "value" => $key,
                        "count" => $count
                    ];
                    $j++;
                }

                $arVal = array_column($frqTbl, 'value');

                array_multisort($arVal, SORT_ASC, SORT_NATURAL, $frqTbl);

                //$dd[$i]['frequency_table'] = $this->json_encode_pretty($dd[$i]['frequency_table']);
                $dd[$i]['frequency_table'] = json_encode($frqTbl);
            }

            else {

                $dd[$i]['frequency_table'] = "";
            }
        }
    }

    private function tidyUpDDv2( $dd, $noCalculations=false )
    {
        //return false;

        //$noCalculations = true; // for testing, no calculations are performed

        $newDD = [];

        $i = 0;
        foreach ($dd as $d) {
    
            if ( $i == 0 ){

                // skip the first row, which is the recordid field and needs no tidying up
                $newDD[] = $d;
                $i++;
                continue;
            }

            /**
             * dang "complete" fields
             */
            if ( $d['redcap_field_name'] === $d['redcap_form_name']."_complete" ){

                $d['valueset'] = [
                    [
                        'value'=>"0",
                        'label'=>"incomplete"
                    ],
                    [
                        'value'=>"1",
                        'label'=>"unverified"
                    ],
                    [
                        'value'=>"2",
                        'label'=>"complete"
                    ]
                ];
            }     
            
            if ( is_array($d['valueset']) && count($d['valueset']) > 0 ){

                $d['valueset'] = json_encode($d['valueset']);
            }
            
            if ( $d['min_value']===NULL || $noCalculations ){

                $d['min_value'] = "";
                $d['max_value'] = "";
                $d['mean'] = "";
                $d['standard_deviation'] = "";
                $d['formatted_min_value'] = "";
                $d['formatted_max_value'] = "";
                $d['formatted_mean'] = "";
                $d['sum_of_values'] = "";
                $d['sum_of_squared_values'] = "";

                if ( !$d['non_missing_count']  ) {

                    $d['min_length'] = "";
                    $d['max_length'] = "";
                }

                if ( $noCalculations ){

                    $d['frequency_table'] = "";
                    $d['non_missing_count'] = "";
                }
            }
            elseif ( $d['non_missing_count'] == 0 ) {

                $d['min_value'] = ""; // started as some large number for calculation purposes
            }
            else {

                $d['mean'] = (float) $d['sum_of_values'] / $d['non_missing_count'];

                if ( $d['non_missing_count'] > 1 ) {

                    $d['standard_deviation'] 
                        = (float) sqrt(($d['sum_of_squared_values'] - ($d['sum_of_values']*$d['sum_of_values']/$d['non_missing_count']))/($d['non_missing_count'] - 1));
                }

                if ( $d['var_type']==="DATE" ){

                    $d['formatted_min_value'] = strftime("%F", $d['min_value']);
                    $d['formatted_max_value'] = strftime("%F", $d['max_value']);
                    $d['formatted_mean']      = strftime("%F", round($d['mean']));
                }

                elseif ( $d['var_type']==="DATETIME" ){

                    $d['formatted_min_value'] = strftime("%F %T", $d['min_value']);
                    $d['formatted_max_value'] = strftime("%F %T", $d['max_value']);
                    $d['formatted_mean']      = strftime("%F %T", round($d['mean']));
                }

                elseif ( $d['var_type']==="TIME" ){

                    $d['formatted_min_value'] = strftime("%T", $d['min_value']);
                    $d['formatted_max_value'] = strftime("%T", $d['max_value']);
                    $d['formatted_mean']      = strftime("%T", round($d['mean']));
                }
            }

            if ( is_array($d['frequency_table']) && count($d['frequency_table']) > 0 ){

                $frqTbl = [];

                $j = 0;

                foreach($d['frequency_table'] as $key=>$count ){
                
                    $frqTbl[$j] = [
                        "value" => $key,
                        "count" => $count
                    ];
                    $j++;
                }

                $arVal = array_column($frqTbl, 'value');

                array_multisort($arVal, SORT_ASC, SORT_NATURAL, $frqTbl);

                //$d['frequency_table'] = $this->json_encode_pretty($d['frequency_table']);
                $d['frequency_table'] = json_encode($frqTbl);
            }

            else {

                $d['frequency_table'] = "";
            }

            $newDD[] = $d;
        }

        return $newDD;
    }

    private function sortByValue($a, $b)
    {
        if ( is_numeric($a['value']) && is_numeric($b['value']) ){

            return intval($a['value']) > intval($b['value']);
        }

        return $a['value'] > $b['value'];
    }

    private function isDateOrTimeType( $varType )
    {
        return in_array( $varType, ['DATE', 'TIME', 'DATETIME']);
    }

    private function isDateType( $varType )
    {
        return in_array( $varType, ['DATE', 'DATETIME']);
    }

    private function ddIsMultiselect( $d ){

        return ( strlen($d['redcap_source_option']) ) ? true : false;
    }
    
    /**
     * Write the export data for a REDCap record.
     * Note that multiple export records can be written for a singl;e REDCap record,
     * depending on the export layout and the number of events and instances.
     * 
     * @param mixed $record 
     * @param mixed $sqlSelect 
     * @param mixed $sqlSelectParams 
     * @param mixed $eventName 
     * @param mixed &$dd 
     * @param mixed $dd_index 
     * @param mixed $dd_specmap_index 
     * @param mixed $dd_multiselect_index 
     * @param mixed $field_events 
     * @param mixed $multiselect_fields 
     * @param mixed $dagNameForGroupId 
     * @param mixed $h 
     * @param mixed $export_layout 
     * @param mixed $export_max_text_length 
     * @param mixed $export_inoffensive_text 
     * @param mixed $export_no_tags
     * @param mixed $export_ascii_text
     * @param mixed $export_hash_recordid 
     * @param mixed $export_shift_dates 
     * @param mixed $export_group_id 
     * @param mixed $export_has_repeatables 
     * @param mixed &$K 
     * @param mixed &$R 
     * @param mixed &$C 
     * @return mixed 
     * @throws Exception 
     */
    private function writeExportDataForRecord( 
        Yes3Export $export,
        $record,
        $group_id,
        $sqlSelect, 
        $sqlSelectParams, 
        $eventName, 
        &$dd, 
        $dd_index, 
        //$dd_specmap_index, 
        $dd_multiselect_index,
        $field_events,
        $multiselect_fields,
        $dagNameForGroupId, 
        $h, 
        //$export_layout, 
        //$export_max_text_length, 
        //$export_inoffensive_text,
        //$export_no_tags,
        //$export_ascii_text,
        //$export_no_tabs,
        //$export_no_newlines,
        //$export_no_dquotes,
        //$export_hash_recordid,
        //$export_shift_dates,
        //$export_group_id,
        //$export_has_repeatables,
        //$export_data_delimiter,
        &$K, 
        &$R, 
        &$C
    ){
        $event_id = "?";
        $instance = "?";
        $field_index = -1;
        $days_to_shift = 0;

        if ( $export->export_shift_dates ){

            $days_to_shift = Yes3Fn::get_shift_days($record, $this->date_shift_max, $this->project_salt);
        }

        if ( $export->export_hash_recordid ){

            if ( $export->export_hash_recordid_legacy ){

                $record = Yes3Fn::hash_record_legacy($record, $this->project_salt);
            }
            else {

                $record = Yes3Fn::hash_record($record, $this->project_salt);
            }
        }

        $y = [];

        $BOR = true;

        $RecordIdField = $this->getRecordIdField();

        $bytesWritten = 0;

        $exportValues = 0;

        // to simplify the code a bit
        //$conditionData = ($export_inoffensive_text || $export_no_tags || $export_ascii_text || $export_no_tabs || $export_no_dquotes) ? true : false;
        //$conditionData = ($export_inoffensive_text || $export_no_tags || $export_ascii_text) ? true : false;
        //$conditionData = false;

        //$this->logDebugMessage($this->getProjectId(), $sqlSelect, "writeExportDataForRecord: sqlSelect");
        //$this->logDebugMessage($this->getProjectId(), print_r($sqlSelectParams, true), "writeExportDataForRecord: sqlSelectParams");

        foreach ( $this->recordGeneratorUnbuffered($sqlSelect, $sqlSelectParams) as $x ){
        //$xx = $this->fetchRecords($sql, $sqlParams);
        //foreach ( $xx as $x ){

            //$K++;

            $x_instance = $x['instance']; if ( !$x_instance ) $x_instance="1";

            /**
             * $BOR: beginning of record
             * 
             * No break for horiz layouts,
             *   (event_id) for vertical,
             *   (event_id, instance) for repeating
             */
            /*
            if ( $export_layout==="v" ) {

                $BOR = ( $x['event_id'] !== $event_id );
            }
            elseif ( $export_layout==="r" ) {

                $BOR = ( $x['event_id'] !== $event_id || $x_instance !== $instance );
            }
            */

            if ( $export->export_layout==="h" ) {

                $BOR = ( $x_instance !== $instance );
            }
            else {

                $BOR = ( $x['event_id'] !== $event_id || $x_instance !== $instance );
            }
           
            if ( $BOR ) {

                // BE AWARE: this code is repeated in the EOR block below
                if ( $y && $exportValues && $h !== false ){

                    // tally the calculations (skip recordid field)
                    for( $i=1; $i<count($dd); $i++ ){

                        $REDCapValue = $y[$dd[$i]['var_name']] ?? '';
                        $dd[$i] = $this->doValidationCalculations($dd[$i], $REDCapValue, false);
                    }

                    $bytesWritten += $this->writeExportRecord($h, $y, $export->export_data_delimiter, $R, $C);
                    $K += $exportValues; // increment the global datum count
                }

                /**
                 * fill out the record
                 */
                $y = [];

                $exportValues = 0;

                $BOR = false;

                foreach ($dd as $d){

                    if ( !isset($y[$d['var_name']]) ){

                        //$y[$d['var_name']] = "";
                        $y[$d['var_name']] =  ( $this->ddIsMultiselect($d) ) ? "0":"";
                    }

                    /**
                     * constant specmap field?
                     */
                    if ( substr($d['redcap_field_name'], 0, 9)==="constant:" ) {

                        $y[$d['var_name']] = str_replace("'", "", trim(substr($d['redcap_field_name'], 9)));
                    }
                }

                $y[$RecordIdField] = $record;

                if ( $export->export_layout!=="h" && $this->isLongitudinal() ) {
    
                    $y[self::VARNAME_EVENT_ID  ] = $x['event_id'];
                    $y[self::VARNAME_EVENT_NAME] = $eventName[$x['event_id']];
                }

                /**
                 * starting v1.1.*, instance is always included in the record
                 */

                if ( $export->export_has_repeatables ) {
    
                    $y[self::VARNAME_INSTANCE  ] = $x_instance;
                }

                if ( isset($y[self::VARNAME_GROUP_ID]) ) {

                    $y[self::VARNAME_GROUP_ID  ] = $group_id; //$this->getGroupIdForRecord($record);
                    $y[self::VARNAME_GROUP_NAME] = $dagNameForGroupId[ $group_id ] ?? '';
                }
            }

            /**
             * add the value to the record
             */

            $event_id = $x['event_id'];

            $instance = $x_instance;

            $field_name = $x['field_name'];

            $REDCapValue = $x['value'] ?? '';

            if ( strlen($REDCapValue) ){

                if ($export->export_data_delimiter === "\t"){

                    /**
                     * TSV export
                     * tabs always sanitized to spaces
                     * export_inoffensive_text controls whether newlines sanitized to spaces
                     */
                    $REDCapValue = Yes3Fn::sanitizeForTSV( $x['value'], $export->export_max_text_length, $export->export_ascii_text, $export->export_inoffensive_text, $export->export_inoffensive_text );
                }
                else {

                    /**
                     * CSV export
                     * export_inoffensive_text controls whether tabs and newlines are sanitized to spaces (for now)
                     */
                    $REDCapValue = Yes3Fn::sanitizeForCSV( $x['value'], $export->export_max_text_length, $export->export_ascii_text, $export->export_inoffensive_text, $export->export_inoffensive_text, $export->export_inoffensive_text );
                }
            }

            /*
            if ( $conditionData ) {

                $REDCapValue = Yes3Fn::sanitizeForText($x['value'], 
                $export_max_text_length, 
                $export_no_tags, 
                $export_ascii_text, 
                $export_inoffensive_text,
                $export_no_newlines,
                $export_no_tabs, 
                $export_no_dquotes);
            }
            elseif ( $export_max_text_length > 0 ){

                $REDCapValue = Yes3Fn::truncate($x['value'], $export_max_text_length);

            } else {

                $REDCapValue = $x['value'];
            }
            */

            $is_multiselect = in_array($field_name, $multiselect_fields);

            if ( $export->export_layout==="h" ){

                if ( $is_multiselect ){

                    $field_index = $dd_multiselect_index[$field_name][$event_id][$REDCapValue] ?? -1;
                } else {

                    $field_index = $dd_index[$field_name][$event_id] ?? -1;
                }

                //$specmap_field_index = $dd_specmap_index[$field_name][$event_id] ?? -1;
            }
            else {

                if ( $is_multiselect ){

                    $field_index = $dd_multiselect_index[$field_name][$REDCapValue] ?? -1;
                } else {

                    $field_index = $dd_index[$field_name] ?? -1;
                }

                //$specmap_field_index = $dd_specmap_index[$field_name] ?? -1;
            }
            
            // acceptable field/event combination?
            $acceptable = ( $field_index > -1 );

            // The dd_index for vertical layouts is keyed only by field name (i.e. one column per field)
            // but the export specification may include specific events for any field.
            // The helper table field_events is referenced to validate the field/event combination
            if ( $acceptable && $export->export_layout!=="h" && isset($field_events[$field_name])){

                $acceptable = in_array((int)$event_id, $field_events[$field_name]);
            }

            // do the things required for the RecordId field
            if ( $field_name === $RecordIdField ){

                $dd[0] = $this->doValidationCalculations($dd[0], $record, true);
            }
            // do the things required for other columns
            elseif ( $acceptable  ){


                /**
                 * goddam multiselects
                 */
                if ( $dd[$field_index]['var_type'] === "CHECKBOX" ){

                    if ( strlen($y[ $dd[ $field_index]['var_name'] ]) ) {

                        $y[ $dd[ $field_index]['var_name'] ] .= ",";
                    }

                    //$y[ $dd[ $field_index]['var_name'] ] .= Yes3Fn::normalized_string( $REDCapValue );
                    $y[ $dd[ $field_index]['var_name'] ] .= $REDCapValue;
                }
                elseif ( $is_multiselect ) {

                    $y[ $dd[ $field_index]['var_name'] ] = "1";
                }
                else {

                    if ( $this->isDateOrTimeType($dd[$field_index]['var_type']) && $days_to_shift > 0 ) {

                        $y[ $dd[ $field_index]['var_name'] ] = Yes3Fn::shift_date_format($REDCapValue, $days_to_shift);
                    }
                    else {

                        $y[ $dd[ $field_index]['var_name'] ] = $REDCapValue;
                    }
                }

                $exportValues++; // increment the count of values exported for this record (other than the recordid)
            }
        }

        // BE AWARE: this code is repeated in the BOR block above
        if ( $y && $exportValues && $h !== false){

            // tally the calculations (skip recordid field)
            for( $i=1; $i<count($dd); $i++ ){

                $REDCapValue = $y[$dd[$i]['var_name']] ?? '';
                $dd[$i] = $this->doValidationCalculations($dd[$i], $REDCapValue, false);
            }

            $bytesWritten += $this->writeExportRecord($h, $y, $export->export_data_delimiter, $R, $C);
            $K += $exportValues; // increment the global datum count
        }

        return $bytesWritten;
    }

    private function doValidationCalculations( $d, $value, $countOnly = false )
    {
        $len = strlen($value);

        //return true; // testing

        if ( !$len ){

            return $d; // no calculations for empty values
        }

        $d['non_missing_count']++;

        if ( $len > $d['max_length'] ) {

            $d['max_length'] = $len;
        }

        if ( $len < $d['min_length'] ){

            $d['min_length'] = $len;
        }

        if ( $countOnly || $d['var_type'] === "CHECKBOX" ){

            return $d; // no further calculations if countOnly requested, or if this is a concatenated checkbox horror
        }

        $var_type = $d['var_type'];

        if ( $var_type === "NOMINAL" ){

            // force an associative array
            $vIndex = (string) $value;

            if ( !isset($d['frequency_table'][$vIndex]) ){

                $d['frequency_table'][$vIndex] = 1;
            }
            else {

                $d['frequency_table'][$vIndex]++;
            }
        }
        else {

            if ( $var_type==="FLOAT" || $var_type==="INTEGER" ){

                $v = is_numeric($value) ? (float) $value : NULL;
            }

            elseif ( $this->isDateOrTimeType($var_type) ){

                $v = strtotime($value);
            }

            else {

                $v = NULL;
            }

            if ( $v !== NULL ) {

                /**
                 * All accumulators start out NULL
                 */
                if ( $d['min_value']===NULL ) {

                    $d['min_value'] = $v;
                    $d['max_value'] = $v;

                    $d['sum_of_values'] = $v;
                    $d['sum_of_squared_values'] = $v*$v;
                }
                else {

                    $d['sum_of_values'] += (float) $v;
                    $d['sum_of_squared_values'] += $v*$v;

                    if ( $v > $d['max_value'] ){

                        $d['max_value'] = $v;
                    }

                    if ( $v < $d['min_value'] ){

                        $d['min_value'] = $v;
                    }
                }
            }
        }
        
        return $d;
    }

    private function writeExportRecord( $h, $y, $export_data_delimiter, &$rowNumber, &$colCount ){

        $bytes = 0;

        if ( $rowNumber===0 ){

            $bytes += Yes3Fn::fputcsv($h, array_keys($y), $export_data_delimiter);
            $colCount = count($y);
        }

        $rowNumber++;

        $bytes += Yes3Fn::fputcsv($h, array_values($y), $export_data_delimiter);

        return $bytes;
    }

    /**
     * Manager for filesystem exports.
     * 
     * @param mixed $export_uuid 
     * @param bool $cron 
     * @param bool $batchMode 
     * @return string 
     * @throws Exception 
     */
    public function exportData($export_uuid, $cron=false, $batchMode=false)
    {
        $t = time();

        $response = "";

        $ddPackage = $this->buildExportDataDictionary($export_uuid);

        //$this->determineExportFileType( $ddPackage['export']->export_file_type );

        if ($cron) {
            $destination = self::DESTINATION_CRON;
        } elseif ($batchMode) {
            $destination = self::DESTINATION_BATCH;
        } else {
            $destination = self::DESTINATION_FILESYSTEM;
        }

        $bytesWritten = 0;

        $results = $this->writeExportFiles($ddPackage, $destination, $bytesWritten, false, true);
        
        /*
        $path = "foo";

        $results = [
            'export_data_message' => "Success: 0 bytes, 0 rows and 0 columns written to {$path}.",
            'export_data_dictionary_message' => "Success: 0 bytes written to {$path}.",
            'export_info_message' => "Success: 0 bytes written to {$path}.",
            'export_fields_rejected' => 32767
        ];
        */

        if ( $ddPackage['export_fields_rejected'] ){

            $response .= "Note: " . $ddPackage['export_fields_rejected'] . " fields were rejected because of form permissions." . "\n\n";
        }

        $response .= $results['export_data_dictionary_message']
        . "\n\n" 
        . $results['export_data_message']
        . "\n\n" 
        . $results['export_info_message']
        . "\n\n"
        . $results['export_sascode_message'] 
        ;

        $t = time() - $t;

        $response .= "\n\nElapsed time: {$t} seconds.";
        /*
        if ( $results['export_info']['notification_email'] && $this->getProjectSetting('enable-email-notifications')==="Y") {

            $this->emailExportNotice( $results['export_info'] );
        }
        */

        //if ( !$batchMode && !$cron ) return $this->escape(nl2br($response)); // format for display in the browser

        return $response;
    }

    private function emailExportNotice( $info ){

        $msg = '<html><body style="font-family:arial,helvetica;">';

        $msg .= '<p>You are receiving this email because you have enabled notifications from the REDCap YES3 Exporter II.</p>';

        $msg .= '<p style="text-decoration:underline;">Summary of export</p>';

        $msg .= '<table><tbody>';

        $msg .= "<tr><td>REDCap host</td><td>"              . APP_PATH_WEBROOT_FULL         . "</td></tr>";
        $msg .= "<tr><td>Date and time</td><td>"            . $info['timestamp']            . "</td></tr>";
        $msg .= "<tr><td>Username</td><td>"                 . $info['username']             . "</td></tr>";
        $msg .= "<tr><td>REDCap project id (pid)</td><td>"  . $info['project_id']           . "</td></tr>";
        $msg .= "<tr><td>REDCap project title</td><td>"     . $info['project_title']        . "</td></tr>";
        $msg .= "<tr><td>Export name</td><td>"              . $info['export_name']          . "</td></tr>";
        $msg .= "<tr><td>Export uuid</td><td>"              . $info['export_uuid']          . "</td></tr>";
        $msg .= "<tr><td>Target folder</td><td>"            . $info['export_target_folder'] . "</td></tr>";
        $msg .= "<tr><td>Path</td><td>"                     . $info['path']                 . "</td></tr>";
        $msg .= "<tr><td>File size (bytes)</td><td>"        . $info['bytes_written']        . "</td></tr>";
        $msg .= "<tr><td>Columns</td><td>"                  . $info['columns']              . "</td></tr>";
        $msg .= "<tr><td>Rows</td><td>"                     . $info['rows']                 . "</td></tr>";

        $msg .= '</tbody></table>';

        $msg .= "</body></html>";

        $result = REDCap::email( 
            
            $info['notification_email'],
            $info['notification_email'],
            "Notice of YES3 Data export",
            $msg

        );
    }

    public function getExportDataDictionary($export_uuid, $summaryOnly=false)
    {
        $ddPackage = $this->buildExportDataDictionary($export_uuid);

        if ( !$ddPackage ){

            return [];
        }
        
        $dd = $ddPackage['export_data_dictionary'];

        if ( $summaryOnly ){

            /** @var Yes3Export $export */
            $export = $ddPackage['export'];

            return [
                'column_count' => count($dd),
                'export_has_repeatables' => $export->export_has_repeatables
            ];
        }

        return $dd;
    }

    /**
     * sets and returns the column count for the supplied export specification
     * a bit doggy because it requires the full data dictionary to be built
     * 
     * @param mixed $specification  The export specification, must include log_id and export_uuid
     * @param bool $countColumns    Whether to count the columns or return the existing count
     * @return int 
     */
    public function getExportColumnCount($specification)
    {
        $log_id = (int) ($specification['log_id'] ?? 0);

        $export_uuid = $specification['export_uuid'];

        if ( !$log_id || !$export_uuid ){

            return 0;
        }

        return (int) ($this->getEmLogParameter($log_id, "column_count") ?? 0);
    }

    /**
     * Sets the calculated fields for the specified export.
     * Called when the export specification is saved or updated.
     * 
     * @param mixed $export_uuid 
     * @param mixed $log_id 
     * @return bool 
     * @throws Exception 
     */
    public function setExportCalculatedFields($export_uuid, $log_id)
    {

        if ( !$log_id ){

            return false;
        }

        if ( !$export_uuid ){

            return false;
        }

        $ddSummary = $this->getExportDataDictionary($export_uuid, true);

        $column_count = (int)($ddSummary['column_count'] ?? 0);
        $export_has_repeatables = (int)($ddSummary['export_has_repeatables'] ?? 0);

        $this->setEmLogParameter($log_id, "column_count", $column_count);
        $this->setEmLogParameter($log_id, "export_has_repeatables", $export_has_repeatables);

        return true;
    }

    /**
     * Manager for data dictionary downloads.
     * 
     * @param string $export_uuid The UUID of the export.
     * @throws Exception If the file cannot be opened or written.
     */
    public function downloadDataDictionary($export_uuid)
    {
        $ddPackage = $this->buildExportDataDictionary($export_uuid);

        /** @var Yes3Export $export */
        $export = $ddPackage['export'];

        //$this->determineExportFileType( $export->export_file_type );

        //$export_name = $ddPackage['export_name'];
        //$export_data_extension = $ddPackage['export_data_extension'];
        //$export_data_delimiter = $ddPackage['export_data_delimiter'];

        $filename = $this->exportDataDictionaryFilename( $export->export_name, self::DESTINATION_DOWNLOAD );

        $ddPackage['export_data_dictionary'] = $this->tidyUpDDv2($ddPackage['export_data_dictionary'], true);

        $xx = (array) $this->dataDictionaryForExport($ddPackage['export_data_dictionary'], $export->export_layout, true);

        $nFields = count($xx)-1; // the first row is the header row

        $this->logExport(
            self::LOG_MESSAGE_DD_DOWNLOADED,
            $export,
            [
                'destination' => self::DESTINATION_DOWNLOAD,
                'filename_data_dictionary' => $filename,
                'data_dictionary_rows' => $nFields
            ]
        );
     
        $h = fopen('php://output', 'w');

        if ( $h===false ){

            throw new Exception("Fail: could not open PHP output stream.");
        }

        $export_summary = "The data dictionary for '{$export->export_name}' was prepared for download.\n\nMetadata for {$nFields} fields were included.\n\nYour download should start soon, if it hasn't already.";

        $this->setExportCookie( $this->sanitizeForExportCookie($export_summary) );

        // bombs away. Hopefully chunking is not needed here.

        header("Content-type: text/csv");
        header('Content-Disposition: attachment; filename=' . basename($filename) );
        header('Pragma: no-cache');
        header('Expires: 0');
     
        $C = 0;
        foreach ( $xx as $x ) {

            $C++;

            Yes3Fn::fputcsv($h, $x, ",");
        }
     
        fclose($h);
    }

    /**
     * Manager for data downloads.
     * 
     * @param string $export_uuid The UUID of the export.
     * @throws Exception If the file cannot be opened or written.
     */
    public function downloadData($export_uuid)
    {
        
        $ddPackage = $this->buildExportDataDictionary($export_uuid);
        
        /** @var Yes3Export $export */
        $export = $ddPackage['export'];

        //$this->determineExportFileType();

        //$export_name = $ddPackage['export_name'];
        //$export_data_extension = $ddPackage['export_data_extension'];

        $filename = $this->exportDataFilename( $export->export_name, $export->export_data_extension, self::DESTINATION_DOWNLOAD );

        $xFileResponse = $this->writeExportFiles($ddPackage, self::DESTINATION_DOWNLOAD);     

        if ( !isset( $xFileResponse['export_data_filename'] ) ) {

            throw new Exception("Fail: download export file not written");
        }

        $filePath = $xFileResponse['export_data_filename'];

        $size = filesize($filePath);

        $file = $this->fopen_r_safe( $filePath );

        if ( $file === false ) {

            throw new Exception("Fail: download export file could not be opened");
        }

        $this->logExport(
            self::LOG_MESSAGE_DATA_DOWNLOADED,
            $export,
            [
                'destination' => self::DESTINATION_DOWNLOAD,
                'filename_data' => $filename,
                'exported_rows' => $xFileResponse['export_data_rows'] ?? 0,
                'exported_columns' => $xFileResponse['export_data_columns'] ?? 0,
                'exported_bytes' => $size
            ]
        );

        $export_summary = "The data file for '{$export->export_name}' ({$size} bytes) was prepared for download.\n\nYour download should start soon, if it hasn't already.";

        $this->setExportCookie( $this->sanitizeForExportCookie($export_summary) );
    
        $chunkSize = 256 * 1024; // 256k per one chunk of file.
        
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . $size);
    
        // Flush system output buffer
        flush();
    
        // Read and output the file in chunks
        while (!feof($file)) {
            echo fread($file, $chunkSize);
            flush(); // Flush system output buffer to the client
        }
    
        // Close the file
        fclose($file);

        //exit;
    }

    /**
     * Manager for ZIP downloads.
     * 
     * @param mixed $export_uuid 
     * @param bool $support_package 
     * @return void 
     * @throws Exception 
     */
    public function downloadZip($export_uuid, $support_package=false)
    {

        $ddPackage = $this->buildExportDataDictionary($export_uuid);
        
        /** @var Yes3Export $export */
        $export = $ddPackage['export'];

        //$this->determineExportFileType();

        $bytesWritten = 0;

        $export_summary = "";

        $xFileResponse = $this->writeExportFiles($ddPackage, self::DESTINATION_DOWNLOAD, $bytesWritten, $support_package);
        /*$this->logDebugMessage(
            $this->getProjectId(),
            print_r($xFileResponse, true),
            "downloadZip: xFileResponse"
        );*/

        /*
        print nl2br(print_r($xFileResponse, true));

        exit;
        */

        if ( !isset( $xFileResponse['export_data_filename']) || !isset( $xFileResponse['export_data_dictionary_filename']) ) {

            throw new Exception("Fail: download export file(s) not written");
        }

        $zipFilename = tempnam(sys_get_temp_dir(), "ys3");

        $zip = new ZipArchive;

        if ( !$zip->open($zipFilename, ZipArchive::CREATE) ) {

            throw new Exception("Fail: could not open zip file for writing");
        }

        /**
         * As of v1.1.4, timestamp is used only for the zip filename,
         * not for the individual files inside the zip.
         */

        $timestamp = $xFileResponse['export_info_timestamp'] ?? $this->timeStampString();
        //$export_name = $ddPackage['export_name'];
        //$export_data_extension = $ddPackage['export_data_extension'];

        // zip payload filenames are NOT timestamped, hence the NULL $timestamp arguments
        $exportDataDictionaryFilename = $this->exportDataDictionaryFilename($export->export_name, self::DESTINATION_DOWNLOAD, NULL);
        $exportDataFilename = $this->exportDataFilename($export->export_name, $export->export_data_extension, self::DESTINATION_DOWNLOAD, NULL);
        $exportInfoFilename = $this->exportInfoFilename($export->export_name, self::DESTINATION_DOWNLOAD, NULL);

        if ( !$zip->addFile($xFileResponse['export_data_dictionary_filename'], $exportDataDictionaryFilename) ) {

            throw new Exception("Fail: could not add data dictionary file to zip");
        }

        $export_summary = "Download summary for '{$export->export_name}':" 
            . "\n\n" . $xFileResponse['export_data_dictionary_file_size'] . " bytes were written to " . $exportDataDictionaryFilename . "."
        ;
    
        /**
         * the ZIP archive will not include the data file if a data martt support package is requested
         */
        if ( !$support_package ) {

            if ( !$zip->addFile($xFileResponse['export_data_filename'], $exportDataFilename) ) {

                throw new Exception("Fail: could not add data file to zip");
            }

            $export_summary .= "\n\n" . $xFileResponse['export_data_rows'] . " rows, " . $xFileResponse['export_data_columns'] . " columns and " . $xFileResponse['export_data_file_size'] . " bytes were written to " . $exportDataFilename . ".";
        }

        if ( !$zip->addFile($xFileResponse['export_info_filename'], $exportInfoFilename) ) {

            throw new Exception("Fail: could not add info file to zip");
        }

        $export_summary .= "\n\n" . $xFileResponse['export_info_file_size'] . " bytes were written to " . $exportInfoFilename . ".";

        if ( $xFileResponse['export_sascode_results'] && isset($xFileResponse['export_sascode_results']['input']) && $xFileResponse['export_sascode_results']['input'] ){

            if ( !$zip->addFile($xFileResponse['export_sascode_results']['input']['path'], $xFileResponse['export_sascode_results']['input']['code_filename_base']) ) {

                throw new Exception("Fail: could not add SAS INPUT code file to zip");
            }
        }

        if ( $xFileResponse['export_sascode_results'] && isset($xFileResponse['export_sascode_results']['fmtlib_create']) && $xFileResponse['export_sascode_results']['fmtlib_create'] ){

            if ( !$zip->addFile($xFileResponse['export_sascode_results']['fmtlib_create']['path'], $xFileResponse['export_sascode_results']['fmtlib_create']['code_filename_base']) ) {

                throw new Exception("Fail: could not add SAS FMTLIB CREATE code file to zip");
            }
        }

        if (  $xFileResponse['export_sascode_results'] && isset($xFileResponse['export_sascode_results']['fmtlib_assign']) && $xFileResponse['export_sascode_results']['fmtlib_assign'] ){

            if ( !$zip->addFile($xFileResponse['export_sascode_results']['fmtlib_assign']['path'], $xFileResponse['export_sascode_results']['fmtlib_assign']['code_filename_base']) ) {

                throw new Exception("Fail: could not add SAS FMTLIB ASSIGN code file to zip");
            }
        }

        $export_summary .= "\n\n" . $xFileResponse['export_sascode_message'];

        $zip->close();

        $filename = $this->exportZipFilename( $export->export_name, self::DESTINATION_DOWNLOAD, $timestamp );

        $chunkSize = 256 * 1024; // 256k per one chunk of file.

        $size = filesize($zipFilename);

        $file = $this->fopen_r_safe($zipFilename);

        if ( $file === false ) {

            throw new Exception("Fail: download zip file could not be opened for download");
        }

        $this->setExportCookie( $this->sanitizeForExportCookie($export_summary) );

        $this->logExport(
            self::LOG_MESSAGE_ZIP_DOWNLOADED,
            $export,
            [
                'destination' => self::DESTINATION_DOWNLOAD,
                'zip_filename' => $filename,
                'zip_filesize' => $size
            ]
        );

        // Set headers for the binary file download
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . $size);

        // Flush system output buffer
        flush();

        // Read and output the file in chunks
        while (!feof($file)) {
            echo fread($file, $chunkSize);
            flush(); // Flush system output buffer to the client
        }

        // Close the file
        fclose($file);

    }

    private function getEventName($event_id, $event_settings)
    {
        foreach ($event_settings as $event){

            if ( $event['event_id']==$event_id ){

                return $event['event_name'];
            }
        }

        return "";
    }

    public function exportDataFilename( $export_name, $export_data_extension="csv", $target=self::DESTINATION_DOWNLOAD, $timestamp="")
    {
        //$this->determineExportFileType();
        return $this->exportFilename($export_name, "data", $export_data_extension, $target, $timestamp);
    }

    public function exportDataDictionaryFilename( $export_name, $target=self::DESTINATION_DOWNLOAD, $timestamp="")
    {
        //$this->determineExportFileType();
        return $this->exportFilename($export_name, "dd", "csv", $target, $timestamp);
    }

    public function exportInfoFilename( $export_name, $target=self::DESTINATION_DOWNLOAD, $timestamp="")
    {
        return $this->exportFilename($export_name, "info", "json", $target, $timestamp);
    }

    public function exportZipFilename( $export_name, $target=self::DESTINATION_DOWNLOAD)
    {
        return $this->exportFilename($export_name, "package", "zip", $target);
    }

    public function exportLogFilename( $export_name, $target=self::DESTINATION_DOWNLOAD, $ext="csv")
    {

        return $this->exportFilename($export_name, "log", $ext, $target);
    }

    public function exportCodeFilename( $export_name, $codeFileType, $codeFileExtension, $target=self::DESTINATION_DOWNLOAD, $timestamp="")
    {
        return $this->exportFilename($export_name, $codeFileType, $codeFileExtension, $target, $timestamp);
    }

    public function exportFilenameBase( $export_name ) {
        // Normalize the export name to a string suitable for filenames
        return Yes3Fn::normalized_string($export_name, 80);
    }

    /**
     * Generates a normalized filename for the export.
     * 
     * @param string $export_name The name of the export.
     * @param string $type The type of export (e.g., data, dd, package).
     * @param string $extension The file extension (e.g., csv, zip).
     * @param string $target The target destination (default is download).
     * @param string|null $timestamp Optional timestamp to append to the filename (downloads only). If blank or not provided, a new timestamp will be generated. If NULL, no timestamp will be added.
     * @return string Normalized filename.
     */
    public function exportFilename( $export_name, $type, $extension, $destination=self::DESTINATION_DOWNLOAD, $timestamp="")
    {
        if ( $destination===self::DESTINATION_DOWNLOAD && $timestamp !== NULL ){

            if ( !$timestamp ){

                $timestamp = $this->timeStampString();
            }

            return $this->exportFilenameBase($export_name) . "_". $type . "_" . $timestamp . "." . $extension;
        }

        return $this->exportFilenameBase($export_name) . "_". $type . "." . $extension;
    }

    public function getEventSettings()
    {
        if ( !$this->isLongitudinal() ){

            return [
                [
                    'event_id' => (string) $this->getFirstREDCapEventId(),
                    'event_name' => "Event_1_arm_1",
                    'event_prefix' => ""
                ]
            ];
        }

        /**
         * [ {event_id, event_name, event_prefix}, ... ]
         */
        $event_settings = $this->getDefaultExportEvents(); 

        $fields = "export_events_json";

        // as of v1.1.0 this will be changed to use the 'log_entry_type parameter' instead of 'setting'
        $pSql = "SELECT {$fields} WHERE project_id=? AND setting='export-events' ORDER BY timestamp DESC LIMIT 1";

        if ( $x = $this->queryLogs($pSql, [$this->getProjectId()])->fetch_assoc() ){

            if ( $export_events_settings = json_decode($x['export_events_json'], true) ){

                for ( $i=0; $i<count($event_settings); $i++ ){

                    for ( $j=0; $j<count($export_events_settings); $j++ ){

                        if ( $export_events_settings[$j]['event_id'] == $event_settings[$i]['event_id'] ){

                            $event_settings[$i]['event_prefix'] = $export_events_settings[$j]['event_prefix'];
                            break;
                        }
                    }
                }
            }
        }

        return $event_settings;
    }

    private function addExportErrorMessage( &$messages, $export_name, $msg )
    {
        $messages[] = $msg;
        //$this->addErrmsg( $export_name . ": " . $msg );
        $this->addErrmsg( $msg );
    }

    private function addExportWarningMessage( &$messages, $export_name, $msg )
    {
        $messages[] = $msg;
        //$this->addSysmsg( $export_name . ": " . $msg );
        $this->addSysmsg( $msg );
    }

    public function confirmSpecificationPermissions( &$specification )
    {
        /**
         * the forms and fields to be exported are recorded in spec.export_items
         */
        $export_items = json_decode( $specification['export_items_json'], true );

        $isLongitudinal = $this->isLongitudinal();

        $specification_forms = [];

        //$sysmsg_prefix = $specification['export_name'] . ": ";

        $export_name = $specification['export_name'];

        $form_export_permissions = $this->getFormExportPermissions();

        $all_forms = array_keys( $form_export_permissions );

        if ( $isLongitudinal ) $all_event_ids = array_keys( $this->getEventNames(true) );
        else $all_event_ids = [ $this->getEventId() ];

        $errors = 0;
        $warnings = 0;
        $permission_denied = 0;

        $error_messages = [];
        $warning_messages = [];

        //$this->logDebugMessage($this->getProjectId(), print_r($export_items, true), $specification['export_name'] . ":export_items");
        //$this->logDebugMessage($this->getProjectId(), print_r($all_forms, true), $specification['export_name'] . ":all_forms");

        //throw new Exception("confirmSpecificationPermissions() is not yet implemented.");

        // accumulate the list of all forms involved in the export specification
        foreach($export_items as $export_item){

            $item_errors = 0;

            // an event may be associated with a form or a field, so the validation check must be up top

            $redcap_event_id = (string) $export_item['redcap_event_id'] ?? "";
            $redcap_form_name = (string) $export_item['redcap_form_name'] ?? "";
            $redcap_field_name = (string) $export_item['redcap_field_name'] ?? "";

            if ( $this->isLongitudinal() && $redcap_event_id && $redcap_event_id !== self::ALL_OF_THEM && !in_array( $redcap_event_id, $all_event_ids ) ){

                $errors++;

                //$this->addErrmsg( $sysmsg_prefix . "The event_id [" . $redcap_event_id . "] is in the export specification but it no longer exists.");

                $this->addExportErrorMessage( $error_messages, $export_name, "The event_id [" . $redcap_event_id . "] is in the export specification but it no longer exists.");

                //$permission_denied++;

                $item_errors++;
            }

            if ( $redcap_form_name ) {

                if ($redcap_form_name === self::ALL_OF_THEM) {

                    // special case if all events are selected
                    if ( !$this->isLongitudinal() || $redcap_event_id === self::ALL_OF_THEM ) {

                        $specification_forms = $all_forms;

                        break;
                    }
                    // otherwise we have to select all forms for the specified event
                    else {

                        foreach($all_forms as $form_name){

                            $form_events = $this->getREDcapEventsForForm($form_name);

                            if  ( $form_events && in_array($redcap_event_id, $form_events) ){
  
                                if ( !in_array( $form_name, $specification_forms ) ){

                                    $specification_forms[] = $form_name;

                                    continue;
                                }
                            }
                        }
                    }
                }
                // single form
                else {

                    if ( !in_array($redcap_form_name, $all_forms ) ){

                        //$this->addErrmsg( $sysmsg_prefix . "The form [" . $redcap_form_name . "] is in the export specification but either it no longer exists, or it is not assigned to any events.");
                        $this->addExportErrorMessage( $error_messages, $export_name,"The form [" . $redcap_form_name . "] is in the export specification but either it no longer exists, or it is not assigned to any events.");

                        //$permission_denied++;

                        $item_errors++;
                    }
                    else if ( !in_array( $redcap_form_name, $specification_forms ) ){

                        $specification_forms[] = $redcap_form_name;
                    }
                }

                //??continue;
            }

            // export item is a single field (there is no 'all fields' option in the UI)
            if ( $redcap_field_name ){

                $form_name = $this->getREDCapFormForField( $redcap_field_name );

                if ( !$form_name ){

                    //$this->addErrmsg( $sysmsg_prefix . "The field [" . $redcap_field_name . "] is in the export specification but it no longer exists.");
                    $this->addExportErrorMessage( $error_messages, $export_name, "The field [" . $redcap_field_name . "] is in the export specification but it no longer exists.");

                    //$permission_denied++;

                    $item_errors++;
                }
                else if ( !in_array( $form_name, $specification_forms ) ){

                    $specification_forms[] = $form_name;
                }
            }

            $errors += $item_errors;
        }

        //$this->logDebugMessage($this->getProjectId(), print_r($specification_forms, true), $specification['export_name'] . ":specification_forms");

        // deny export permission if no forms are involved, e.g. export design error or other mischief
        if ( !$specification_forms ){

            $warnings++;

            $permission_denied++; // can't export a null spec

            //$this->addSysmsg( $sysmsg_prefix . "No items are as yet specified for this export. Click 'Export Items' to add forms and fields to this export." );
            $this->addExportWarningMessage( $warning_messages, $export_name, "No items are as yet specified for this export.");
        }
        else {
            foreach ($specification_forms as $form_name){

                if ( !isset($form_export_permissions[$form_name]) || !$form_export_permissions[$form_name] ){
    
                    //$this->addSysmsg( $sysmsg_prefix. "Export permission was denied for form [" . $form_name . "].");
                    $this->addExportWarningMessage( $warning_messages, $export_name, "Export permission was denied for form [" . $form_name . "].");
    
                    $permission_denied++;
                }
            }    
        }

        // errors in the export specification
        if ( $errors ){

            //$this->addSysmsg( $sysmsg_prefix. "Export permission was denied because of errors detected in the specification.");
            $this->addExportWarningMessage( $warning_messages, $export_name, "Export permission was denied because of errors detected in the specification.");

            $permission_denied++;
        }   
        
        //$this->logDebugMessage($this->getProjectId(), "export permission approved" . $form_name, $specification['export_name'] . ":approval");

        //return ( $permission_denied === 0 ) ? true : false;

        $specification['permission_export'] = ( $permission_denied === 0 ) ? true : false;
        $specification['error_messages'] = $error_messages;
        $specification['warning_messages'] = $warning_messages;

        return $specification['permission_export'];
    }

    private function isConstantExpression($s){

        return ( stripos( $s, "constant:") !== FALSE ) ? true : false;
    }

    /**
     * Determines if a field will be excluded based on export specification options.
     * NOTE: This logic is largely repeated in addExportItem_REDCapField(). It would be good to harmonize.
     * 
     * function: fieldExcludedByExportOptions
     * 
     * @param mixed $specification - export specification. Must have properties:
     *              export_remove_phi
     *              export_remove_dates
     *              export_remove_largetext
     *              export_remove_freetext
     * 
     * @param mixed $field - REDCap field metadata. Must have properties:
     *              element_type
     *              element_validation_type
     *              field_phi
     * 
     * @return bool
     */
    private function fieldExcludedByExportOptions( $specification, $field )
    {
        if ( $field['field_name'] === $this->getRecordIdField() ){

            return false;
        }
        elseif ( $specification['export_remove_phi'] && $field['field_phi'] ){

            return true;
        }
        elseif ( $specification['export_remove_dates'] && $this->isDateOrTimeType($this->REDCapFieldTypeToVarType($field['element_type'], $field['element_validation_type'])) ){

            return true;
        }
        elseif ( $specification['export_remove_largetext'] && $field['element_type']==="textarea" ){

            return true;
        }
        elseif ( $specification['export_remove_freetext'] && $field['element_type']==="text" && !$field['element_validation_type'] ){

            return true;
        }

        return false;
    }

    function specificationHasFieldExclusions( $specification )
    {
        return ( $specification['export_remove_phi'] || $specification['export_remove_dates'] || $specification['export_remove_largetext'] || $specification['export_remove_freetext'] );
    }

    public function logIsThisEmAndProject( $log_id )
    {
        return $this->queryLogs("SELECT COUNT(*) WHERE log_id=?", [$log_id]);
    }

    public function getEmLogParameter( $log_id, $key_name ){

        return $this->fetchValue(
            "SELECT `value` FROM redcap_external_modules_log_parameters WHERE log_id=? AND `name`=?",
            [ 
                $log_id, 
                $key_name 
            ]
        );
    }

    public function setEmLogParameter($log_id, $parameter, $value)
    {
        if ( !$this->logIsThisEmAndProject($log_id) ) return false; // must be this EM and project

        $sqle = "SELECT COUNT(*) FROM redcap_external_modules_log_parameters WHERE log_id=? AND `name`=?";
        $parmRecordExists = $this->fetchValue($sqle, [$log_id, $parameter]);

        if ( $parmRecordExists ){

            $sql = "UPDATE redcap_external_modules_log_parameters SET `value`=? WHERE log_id=? AND `name`=?";
            $this->query($sql, [$value, $log_id, $parameter]);
        }
        else {

            $sql = "INSERT INTO redcap_external_modules_log_parameters (log_id, `name`, `value`) VALUES (?,?,?)";
            $this->query($sql, [$log_id, $parameter, $value]);
        }

        // confirm the update
        $sqlv = "SELECT COUNT(*) FROM redcap_external_modules_log_parameters WHERE log_id=? AND `name`=? AND value=?";
        return $this->fetchValue($sqlv, [$log_id, $parameter, $value]);
    }

    /**
     * 
     * 
     * function: getExportSpecification
     * 
     * @param mixed $export_uuid
     * 
     * @return array
     * @throws Exception
     */
    public function getExportSpecification( $export_uuid, $log_id=0, $history=false ): array
    {        
        $specFields = "log_id, message, timestamp
        , removed
        , export_uuid
        , export_name
        , export_label
        , export_order
        , export_username
        , export_layout
        , export_multiselect
        , export_selection
        , export_criterion_field
        , export_criterion_event
        , export_criterion_value
        , export_target
        , export_max_label_length
        , export_max_text_length
        , export_inoffensive_text
        , export_no_tags
        , export_ascii_text
        , export_remove_phi
        , export_remove_freetext
        , export_remove_largetext
        , export_remove_dates
        , export_shift_dates
        , export_hash_recordid
        , export_hash_recordid_legacy
        , export_items_json
        , export_batch
        , export_sascode
        , export_sascode_ascii
        , export_sascode_libref
        , export_sascode_libref_path
        , export_sascode_dsname
        , export_rcode
        , export_file_type
        , column_count
        , export_has_repeatables
        ";

        //$this->logDebugMessage($this->getProjectId(), $export_uuid, "getExportSpecification");

        if ( $log_id ){

            $pSql = "SELECT {$specFields} WHERE log_id=?";
            $params = [ $log_id ];
        }
        else {
            $pSql = "
                SELECT {$specFields}
                WHERE project_id=? AND message=? AND export_uuid=?
                ORDER BY timestamp DESC
            ";
            $params = [$this->getProjectId(), self::EMLOG_MSG_EXPORT_SPECIFICATION, $export_uuid];
        }

        if ( $history ){

            //$this->logDebugMessage($this->getProjectId(), $this->getQueryLogsSql($pSql), 'getExportSpecification');
            //$this->logDebugMessage($this->getProjectId(), print_r($params, true), 'getExportSpecification');

            $qResult = $this->queryLogs($pSql, $params);

            $specs = [];
            while ( $spec = $qResult->fetch_assoc() ){

                $specs[] = $spec;
            }

            return $specs;
        }
        else {

            $spec = $this->queryLogs($pSql." LIMIT 1", $params)->fetch_assoc();

            if ( !is_array($spec) ) return [];

            // ensure consistent properties if all records are selected
            if ( $spec['export_selection']=="1" ){

                $spec['export_criterion_field'] = "";
                $spec['export_criterion_event'] = "";
                $spec['export_criterion_value'] = "";
            }

            return $spec;
        }
    }

    /**
     * 'export specification' here is equiv to the preferred 'upload specification' or 'specMap'
     * This is stored as a JSON string in the settings.
     * 
     * The data dictionary is based on the 'field map' which is fetched by getExportElements.
     * 
     * 'specification' is also used to refer to the field map. A lot of under-the-hood ambiguity to clean up,
     * 
     * function: getExportSpecifications
     * 
     * 
     * @return array
     * @throws Exception
     */
    public function getExportSpecifications()
    {
        /**
         * retrieve the unique export UUIDs
         */

        $pSql = "SELECT DISTINCT export_uuid WHERE export_uuid IS NOT NULL AND setting='export-specification' AND project_id=?";

        $uuid_result = $this->queryLogs($pSql, [$this->getProjectId()]);
        $uuids = [];
        while ( $row = $uuid_result->fetch_assoc() ){
            $uuids[] = $row['export_uuid'];
        }

        //return $uuids;

        $specifications = [];

        for ( $i=0; $i<count($uuids); $i++ ){

            $fields = "log_id, user, removed, setting, export_uuid, timestamp, export_specification_json";

            $pSql = "
                SELECT {$fields}
                WHERE project_id=? AND setting='export-specification' AND export_uuid=?
                ORDER BY timestamp DESC LIMIT 1
            ";
            $params = [$this->getProjectId(), $uuids[$i]];

            if ( $specification_settings = $this->queryLogs($pSql, $params)->fetch_assoc() ){

 
                if ( $this->is_json_decodable($specification_settings['export_specification_json'])) {
                    
                    $specification = json_decode($specification_settings['export_specification_json']);

                    //$this->logDebugMessage($this->getProjectId(), print_r($specification, true), "getExportSpecifications:object");

                    if ( is_object($specification) ){

                        if ( !is_object($specification->mapping_specification) ) {

                            $specification->mapping_specification = ['elements'=>[]];
                        }

                        if ( !$specification->removed ) {

                            $specification->removed = "0";
                        }

                        $specifications[] = $specification;
                    }
                }
            }
        }

        return $specifications;
    }

    public function getDefaultExportEvents()
    {
        if ( !$this->isLongitudinal() ){
            return [];
        }

        $events = $this->getEventNames(true);

        $maxUniquePrefixLen = 8;
        $uniquePrefixLen = 0;
        $k = 1;
        while ( $k <= $maxUniquePrefixLen && !$uniquePrefixLen ){
            $prefixes = [];
            $uniquePrefixLen = $k;
            foreach ( $events as $event_id=>$event_name ){
                $event_name = str_replace("_", "", $event_name);
                if ( !ctype_lower($event_name[0]) ){
                    $event_name = "e" . $event_name;
                }
                $prefix = substr($event_name, 0, $uniquePrefixLen);
                if ( in_array($prefix, $prefixes, true) ){
                    $uniquePrefixLen = 0;
                    break;
                }
                $prefixes[$event_id] = $prefix;
            }
            $k++;
        }

        $export_events = [];

        $eventNum = 0;

        foreach( $events as $event_id => $event_name ){

            $strEventId = (string) $event_id;

            $eventNum++;

            if ( $uniquePrefixLen ){

                $prefix = $prefixes[$event_id];
            }
            else {

                //$prefix = "e" . $strEventId;
                $prefix = "e" . strval($eventNum);
            }

            $export_events[] = ['event_id'=>$strEventId, 'event_name'=>$event_name, 'event_prefix'=>$prefix];        
        }

        return $export_events;
    }

    public function getExportElements($export_uuid)
    {
        $fields = "log_id, message, user, timestamp, export_uuid, field_mappings";

        $pSql = "
            SELECT {$fields}
            WHERE project_id=? AND setting='yes3-exporter-field-map' AND export_uuid=?
            ORDER BY timestamp DESC LIMIT 1
        ";
        $params = [$this->getProjectId(), $export_uuid];

        if ( !$map_record = $this->queryLogs($pSql, $params)->fetch_assoc() ){
            return [];
        }

        if ( !$field_mappings = json_decode($map_record['field_mappings'], true) ){
            return [];
        }

        if ( !$field_mappings['elements']) {
            return [];
        }

        return $field_mappings['elements'];
    }

    public function getFormMetadataStructures():array
    {
        $user_rights = $this->yes3UserRights();
        $form_export_permissions = $user_rights['form_export_permissions'];
        $designer = $user_rights['isDesigner'];

        $events = [];

        if ( $isLong = $this->isLongitudinal() ) {

            $sql = "
            SELECT DISTINCT m.form_name, m.field_order, m.form_menu_description
            FROM redcap_metadata m
                INNER JOIN redcap_events_forms ef ON ef.form_name=m.form_name
                INNER JOIN redcap_events_metadata em ON em.event_id=ef.event_id
                INNER JOIN redcap_events_arms ea ON ea.arm_id=em.arm_id AND ea.project_id=m.project_id
            WHERE m.project_id=? AND m.form_menu_description IS NOT NULL
            ORDER BY m.field_order
            ";

        } else {

            $events = [[ 
                'event_id' => $this->getFirstREDCapEventId(),
                'descrip' => "Event 1",
                'event_label' => "Event_1"
            ]];

            $sql = "
            SELECT m.form_name, m.form_menu_description
            FROM redcap_metadata m
            WHERE m.project_id=? AND m.form_menu_description IS NOT NULL
            ";

        }

        $mm = $this->fetchRecords($sql, [$this->getProjectId()]);

        //$this->logDebugMessage($this->getProjectId(), print_r($form_export_permissions, true), "form_export_permissions");
        //$this->logDebugMessage($this->getProjectId(), print_r($mm, true), "form metadata");

        $form_metadata = [];

        $form_index_num = 0;

        $form_index = [];

        foreach ($mm as $m){

            if ( !$designer && !$form_export_permissions[$m['form_name']] ){

                continue;
            }

            if ( $isLong ){

                $sqlE = "
                SELECT ef.event_id, em.descrip
                FROM redcap_events_forms ef
                    INNER JOIN redcap_events_metadata em ON em.event_id=ef.event_id
                    INNER JOIN redcap_events_arms ea ON ea.arm_id=em.arm_id
                WHERE ea.project_id=? and ef.form_name=?
                ORDER BY em.day_offset, ef.event_id
                ";

                $ee = $this->fetchRecords($sqlE, [$this->getProjectId(), $m['form_name']]);

                $events = [];

                foreach( $ee as $e ){

                    $events[] = [ 
                        'event_id' => (string)$e['event_id'],
                        'event_label' => Yes3Fn::sanitizeForLabel($e['descrip']),
                        'descrip' => Yes3Fn::sanitizeForLabel($e['descrip'])
                    ];      
                }
            }

            $sqlF = "
            SELECT m.field_name
            FROM redcap_metadata m
            WHERE m.project_id=? and m.form_name=?
                AND m.element_type<>'descriptive'
            ORDER BY m.field_order ; 
            ";

            $form_fields = [];

            $fields = $this->fetchRecords($sqlF, [$this->getProjectId(), $m['form_name']]);
            foreach ( $fields as $field ){
                $form_fields[] = $field['field_name'];
            }
    
            $form_name = Yes3Fn::sanitizeForObjectname($m['form_name'], 0, true, true, true);

            $form_metadata[] = [
                'form_name' => $form_name,
                'form_label' => Yes3Fn::sanitizeForLabel($m['form_menu_description']),
                'form_events' => $events,
                'form_fields' => $form_fields,
                'form_repeating' => ( Yes3Fn::isRepeatingInstrument($m['form_name']) ) ? 1 : 0
            ];

            $form_index[$form_name] = $form_index_num;

            $form_index_num++;
        }
        
        return [
            'form_index'=>$form_index, 
            'form_metadata'=>$form_metadata
        ];
    }

    function getEventNameForEventId( $event_id )
    {
        if ( !$this->isLongitudinal() ) return "n/a";

        if ( !$event_id ) return "";

        if ( $event_id === self::ALL_OF_THEM ) return "all events";

        $event_id = intval($event_id); if ( !$event_id ) return "";

        return $this->getEventNames(true, true, $event_id);
    }

    private function getFormDataEntryFieldMetadata($form_name)
    {
        $sql = "
        SELECT m.field_order, m.form_name, m.field_name, m.element_type, m.element_label, m.element_enum, m.element_validation_type, m.field_phi
        FROM redcap_metadata m
        WHERE m.project_id=?
        AND m.element_type NOT IN('descriptive')";

        $params = [$this->getProjectId()];

        if ( $form_name === self::ALL_OF_THEM ){

            if ( $this->isLongitudinal() ) {

                $sql .= " AND m.form_name IN("
                ."SELECT DISTINCT ef.form_name"
                ." FROM redcap_events_forms ef"
                ." INNER JOIN redcap_events_metadata em ON em.event_id=ef.event_id"
                ." INNER JOIN redcap_events_arms ea ON ea.arm_id=em.arm_id"
                ." WHERE ea.project_id=?"
                .")";
                $params[] = $this->getProjectId();
            }
        }
        else {

            $sql .= " AND m.form_name=?";
            $params[] = $form_name;
        }

        $sql .= " ORDER BY m.field_order";

        //$this->logDebugMessage($this->getProjectId(), $sql, "getFormDataEntryFieldMetadata:" . $form_name);

        return $this->fetchRecords($sql, $params);
    }

    private function getFieldMetadata($field_name)
    {
        $sql = "
        SELECT m.field_order, m.form_name, m.field_name, m.element_type, m.element_label, m.element_enum, m.element_validation_type, m.field_phi
        FROM redcap_metadata m
        WHERE m.project_id=? AND m.field_name=?
        ";

        return $this->fetchRecords($sql, [$this->getProjectId(), $field_name]);
    }

    public function getFieldMetadataStructures(): array
    {
        $form_export_permissions = $this->yes3UserRights()['form_export_permissions'];
        
        if ( $this->isLongitudinal() ){

            $sql = "
            SELECT DISTINCT m.field_order, m.form_name, m.field_name, m.element_type, m.element_label, m.element_enum, m.element_validation_type, m.field_phi
            FROM redcap_metadata m
                INNER JOIN redcap_events_forms ef ON ef.form_name=m.form_name
                INNER JOIN redcap_events_metadata em ON em.event_id=ef.event_id
                INNER JOIN redcap_events_arms ea ON ea.arm_id=em.arm_id AND ea.project_id=m.project_id
            WHERE m.project_id=?
            AND m.element_type NOT IN('descriptive')
            ORDER BY m.field_order;  
            ";
        }
        else {

            $sql = "
            SELECT m.field_order, m.form_name, m.field_name, m.element_type, m.element_label, m.element_enum, m.element_validation_type, m.field_phi
            FROM redcap_metadata m
            WHERE m.project_id=?
            AND m.element_type NOT IN('descriptive')
            ORDER BY m.field_order      
            ";
        }

        $fields = $this->fetchRecords($sql, [$this->getProjectId()]);

        $field_metadata = [];

        $field_autoselect_source = [];

        $field_index_num = 0;

        $field_index = [];

        foreach ($fields as $field){

            $field_name = Yes3Fn::sanitizeForObjectname($field['field_name']);
            $field_label = Yes3Fn::sanitizeForLabel( $field['element_label'], Yes3Fn::MAX_LABEL_LEN);
            $form_name = Yes3Fn::sanitizeForObjectname($field['form_name']);

            $field_type = $field['element_type'];
            $field_validation = $field['element_validation_type'];

            // form_export_permission: 1=Full dataset, 2=Deidentified, 3=No PHI, 0=No access

            $form_export_permission = (int)($form_export_permissions[$form_name] ?? 0);

            if ( !$form_export_permission ) {

                continue;
            }

            // phi only allowed for full access
            if ( $form_export_permission !== 1 && $field['field_phi'] === "1" ){

                continue;
            }

            // large text, small text, dates not allowed for de-identified access
            // note: for now we are 
            if ( $form_export_permission === 2 ){

                if ( $field_type === "textarea"
                    || ($field_type === "text" && !$field_validation)
                    || $this->isDateOrTimeType($this->REDCapFieldTypeToVarType( $field_type, $field_validation )) 
                ) {

                    continue;
                }
            }

            $valueset = [];

            if ( $field['element_type']==="radio" || $field['element_type']==="select" || $field['element_type']==="checkbox"){
                $vv = $this->getChoiceLabels($field_name);
                foreach ( $vv as $value => $label) {
                    $valueset[] = [
                        'value' => Yes3Fn::sanitizeForText((string)$value, 0, true, false, true),
                        'label' => Yes3Fn::sanitizeForLabel($label, Yes3Fn::MAX_LABEL_LEN)
                    ];
                }
            }

            elseif ( $field['element_type']==="yesno" ){

                $valueset = [
                    ['value' =>" 0", "label" => "No"],
                    ['value' =>" 1", "label" => "Yes"]
                ];
            }

            elseif ( $field['element_type']==="truefalse" ){

                $valueset = [
                    ['value' =>" 0", "label" => "False"],
                    ['value' =>" 1", "label" => "True"]
                ];
            }

            $field_metadata[] = [

                'field_name'        => $field_name,
                'form_name'         => $form_name,
                'form_repeating'    => ( Yes3Fn::isRepeatingInstrument($form_name) ) ? 1 : 0,
                'field_label'       => $field_label,
                'field_type'        => $field['element_type'],
                'field_validation'  => $field['element_validation_type'],
                'field_phi'         => $field['field_phi'],
                'field_valueset'    => $valueset

            ];

            /**
             * (1) Fields from repeating instruments are not selectable, 
             *     since only forms are allowed on repeating layouts.
             * (2) The record ID field is not selectable. Its inclusion is determined at export time.
            
            if ( !Yes3Fn::isRepeatingInstrument($form_name) && $field_name !== $this->getRecordIdField() ) {
                $field_autoselect_source[] = [
                    'value' => $field_name,
                    'label' => "[" . $field_name . "] " . $field_label
                ];
            }
            */

            /**
             * record ID field is not selectable
             */
            if ( $field_name !== $this->getRecordIdField() ) {
                $field_autoselect_source[] = [
                    'value' => $field_name,
                    'label' => "[" . $field_name . "] " . $field_label
                ];
            }

            $field_index[$field_name] = $field_index_num;

            $field_index_num++;
        }

        //$field_index = [];
        //$field_metadata = [];
        //$field_autoselect_source = [];

        return [
            'field_index'=>$field_index, 
            'field_metadata'=>$field_metadata, 
            'field_autoselect_source'=>$field_autoselect_source
        ];
    }

    /*
    private function addExportItem_Specification( $export, $element, $event_settings, $export_uspec )
    {
        $valueset = [];

        // fetch the corresponding uspec item
        foreach ($export_uspec['elements'] as $uspec_element ){

            if ( $uspec_element['name']===$element['uspec_element_name'] ){

                 // update the uSpec valueset with mapped REDCap field values
                
                $valueset = [];

                foreach( $uspec_element['valueset'] as $v ){

                    $redcap_field_value = "";

                    // walk through the value map for this export specification
                    foreach( $element['uspec_element_value_map'] as $vMap ){
                        
                        if ( $vMap['uspec_value']==$v['value'] ){

                            $redcap_field_value = $vMap['redcap_field_value'];
                            break;
                        }
                    }

                    $valueset[] = [
                        'value' => $v['value'],
                        'label' => $v['label'],
                        'redcap_field_value' => $redcap_field_value
                    ];
                }

                $export->addExportItem([
                    'var_name' => $uspec_element['name'],
                    'var_label' => $uspec_element['label'],
                    'var_type' => $this->specificationTypeToVarType( $uspec_element['type'], $valueset ),
                    'valueset' => $valueset,
                    'origin' => "specification",
                    'redcap_field_name' => $element['redcap_field_name'],
                    'redcap_form_name'  => $this->getREDCapFormForField($element['redcap_field_name']),
                    self::VARNAME_EVENT_ID    => $element[self::VARNAME_EVENT_ID],
                    self::VARNAME_EVENT_NAME  => $this->getEventName($element[self::VARNAME_EVENT_ID], $event_settings)
                ]);

                break;    
            }
        }
    }
    */

    /**
     * Adds a REDCap field item to an export. 
     * The parameters are set in buildExportDataDictionary(), 
     * and passed either directly or through addExportItem_REDCapForm().
     * 
     * NOTE: see harmonization comment for fieldExcludedByExportOptions().
     * 
     * function: addExportItem_REDCapField
     * 
     * @param mixed $export - the export object
     * @param mixed $redcap_field_name - from export specification
     * @param mixed $redcap_event_id - from export specification
     * @param mixed $fields - the fields metadata array returned by getFieldMetadataStructures()
     * @param mixed $forms - the forms metadata array returned by getFormMetadataStructures()
     * @param mixed $event_settings - the array returned by getEventSettings()
     * @param mixed $allowed - array of allowed DAGs, forms, field types etc. Set in buildExportDataDictionary()
     * @param mixed $form_export_permissions - array of form permissions for the user. 
     *              Keyed by form_name. Values: 1=Full dataset, 2=Deidentified, 3=No PHI, 0=No access
     * 
     * @return int
     * @throws Exception
     */
    private function addExportItem_REDCapField( $export, $redcap_field_name, $redcap_event_id, $fields, $forms, $event_settings, $allowed, $form_export_permissions )
    {
                
        $field_index = $fields['field_index'][$redcap_field_name];

        $form_name = $fields['field_metadata'][$field_index]['form_name'];

        if ( !in_array($form_name, $allowed['forms']) ){

            return 0;
        }

        $form_export_permission = $form_export_permissions[$form_name];

        $field_type = $fields['field_metadata'][$field_index]['field_type']; // aka element_type

        $field_validation = $fields['field_metadata'][$field_index]['field_validation']; // aka element_validation_type

        $field_phi = ( $fields['field_metadata'][$field_index]['field_phi'] == "1" );

        $field_largetext = ( $fields['field_metadata'][$field_index]['field_type']==="textarea" );

        $field_smalltext = false;

        $field_date = false;

        if ( $field_type === "text" ){

            if ( !$field_validation ){

                $field_smalltext = true;
            }
            elseif ( $this->isDateOrTimeType($this->REDCapFieldTypeToVarType( $field_type, $field_validation )) ) {

                $field_date = true;
            }
        }

        /*
        $msg = "redcap_field_name={$redcap_field_name}: form_name={$form_name}, field_type={$field_type}, field_validation={$field_validation}, field_phi={$field_phi}"
        .", field_largetext={$field_largetext}"
        .", field_smalltext={$field_smalltext}"
        .", field_date={$field_date}."
        ."\nallowed: phi={$allowed['phi']} largetext={$allowed['largetext']} smalltext={$allowed['smalltext']} dates={$allowed['dates']}."
        ;
        //$this->logDebugMessage($this->getProjectId(), $msg, "addExportItem_REDCapField");
        */

        /**
         * data dictionary inclusion depends on the export options and the user's form export permissions
         * the record id field always gets a pass
         */

        if (  $redcap_field_name !== $this->getRecordIdField() ){

            if ( $field_phi && (!$allowed['phi'] || $form_export_permission != 1) ){

                return 0;
            }

            if ( $field_largetext && (!$allowed['largetext'] || $form_export_permission == 2 ) ){

                return 0;
            }

            if ( $field_smalltext && (!$allowed['smalltext'] || $form_export_permission == 2 ) ){

                return 0;
            }

            if ( $field_date && (!$allowed['dates'] || $form_export_permission == 2 ) ){

                return 0;
            }
        }

        $event_ids = [];

        //if ( $redcap_event_id === self::ALL_OF_THEM && $export->export_layout === "h" ){
        if ( $redcap_event_id === self::ALL_OF_THEM ){

            $form_index = $forms['form_index'][$form_name];

            foreach($forms['form_metadata'][$form_index]['form_events'] as $event){

                $event_ids[] = $event['event_id'];
            }
        }
        else {

            $event_ids = [ $redcap_event_id ];
        }

        foreach ( $event_ids as $event_id ){

            $var_name = $this->exportFieldName($export, $redcap_field_name, $event_id, $event_settings);

            //print "\n" . $redcap_field_name . ", var_name=" . $var_name . "\nexport=" . print_r($export, true);

            if ( !$export->itemInExport($var_name) ){

                $var_label = $fields['field_metadata'][$field_index]['field_label'];
                $var_type = $this->REDCapFieldTypeToVarType($field_type, $field_validation);
                $valueset = $fields['field_metadata'][$field_index]['field_valueset'] ?? [];
                $event_name = $this->getEventName($event_id, $event_settings);

                if ( $field_type === "checkbox" && $export->export_multiselect === "1" ) {

                    //Yes3::logDebugMessage( $this->getProjectId(), print_r($valueset, true),"addExportItem_REDCapField:CHECKBOX {$redcap_field_name}" );
                    
                    foreach ( $valueset as $option ){

                        $export->addExportItem([
                            'var_name' =>  Yes3Fn::sanitizeForObjectname($var_name . Yes3Fn::MULTISELECT_DELIM . $option['value']),
                            'var_label' =>  Yes3Fn::sanitizeForLabel($var_label. ": " . $option['label']),
                            'var_type' => "INTEGER",
                            'valueset' => [],
                            'origin' => "redcap",
                            'redcap_field_name' => $redcap_field_name,
                            'redcap_events' => [ (int)$event_id ],
                            'redcap_form_name' => $form_name,
                            'multiselect' => "1",
                            'multiselect_option' => strval($option['value']),
                            self::VARNAME_EVENT_ID => $event_id,
                            self::VARNAME_EVENT_NAME => $event_name
                        ],
                        $this->getRecordIdField());
                    }
                }
                else if ($field_type === "checkbox" && $export->export_multiselect === "2") {

                    // multiselect as nominal with concatenated values
                    /*
                    $concat_valueset = [];
                    foreach ($valueset as $option) {
                        $concat_valueset[] = $option['value'];
                    }
                    $concat_value = implode(Yes3Fn::MULTISELECT_DELIM, $concat_valueset);
                    */

                    $export->addExportItem([
                        'var_name' =>  Yes3Fn::sanitizeForObjectname($var_name),
                        'var_label' =>  Yes3Fn::sanitizeForLabel($var_label),
                        'var_type' => "CHECKBOX",
                        /*
                        'valueset' => [
                            [
                                'value' => Yes3Fn::sanitizeForText($concat_value, 0, true, false, true),
                                'label' => "Multiple values: " . implode(", ", array_column($valueset, 'label'))
                            ]
                        ],
                        */
                        'valueset' => $valueset,
                        'origin' => "redcap",
                        'redcap_field_name' => $redcap_field_name,
                        'redcap_events' => [ (int)$event_id ],
                        'redcap_form_name' => $form_name,
                        self::VARNAME_EVENT_ID => $event_id,
                        self::VARNAME_EVENT_NAME => $event_name
                    ],
                    $this->getRecordIdField());

                }
                else {
                    $export->addExportItem([
                        'var_name' => $var_name,
                        'var_label' => Yes3Fn::sanitizeForLabel($var_label),
                        'var_type' => $var_type,
                        'valueset' => $valueset,
                        'origin' => "redcap",
                        'redcap_field_name' => $redcap_field_name,
                        'redcap_events' => [ (int)$event_id ],
                        'redcap_form_name' => $form_name,
                        self::VARNAME_EVENT_ID => $event_id,
                        self::VARNAME_EVENT_NAME => $event_name
                    ],
                    $this->getRecordIdField());
                }
            }
            else {

                $export->updateExportItemEvents($var_name, $event_id);
            }
        }

        return 1;
    }

    private function addExportItem_otherProperty( $export, $property_name, $property_label, $property_type="TEXT", $property_valueset=[] )
    {
        $export->addExportItem([
            'var_name' => $property_name,
            'var_label' => $property_label,
            'var_type' => $property_type,
            'valueset' => $property_valueset,
            'origin' => "other",
            'redcap_field_name' => "",
            'redcap_form_name' => "",
            self::VARNAME_EVENT_ID => "",
            self::VARNAME_EVENT_NAME => ""
        ],
        $this->getRecordIdField());
    }

    private function addExportItem_REDCapForm( $export, $redcap_form_name, $redcap_event_id, $fields, $forms, $event_settings, $allowed, $form_export_permissions )
    {

        $form_names = [];

        if ( $redcap_form_name === self::ALL_OF_THEM ){

            foreach ( $forms['form_metadata'] as $form ){

                // no longer exlude repeating forms
                if ( in_array($form['form_name'], $allowed['forms']) /*&& !$form['form_repeating']*/) {

                    $includeForm = ( $redcap_event_id === self::ALL_OF_THEM || !$this->isLongitudinal() );

                    if ( !$includeForm ){

                        foreach ( $form['form_events'] as $event ){

                            if ( $event['event_id'] == $redcap_event_id ){

                                $includeForm = true;
                                break;
                            }
                        }
                    }

                    if ( $includeForm ){
                        
                        $form_names[] = $form['form_name'];
                    }
                }
            }
        }
        else {

            if ( in_array($redcap_form_name, $allowed['forms']) ){
    
                $form_names = [ $redcap_form_name ];
            }
        }

        foreach ( $form_names as $form_name ){

            $form_index = $forms['form_index'][$form_name];

            $event_ids = [];

            if ( $redcap_event_id === self::ALL_OF_THEM ) {

                foreach ( $forms['form_metadata'][$form_index]['form_events'] as $event ){

                    $event_ids[] = $event['event_id'];
                }
            }
            else {

                $event_ids[] = $redcap_event_id;
            }

            foreach ( $event_ids as $event_id ){

                foreach ( $forms['form_metadata'][$form_index]['form_fields'] as $field_name ){

                    $this->addExportItem_REDCapField($export, $field_name, $event_id, $fields, $forms, $event_settings, $allowed, $form_export_permissions);
                }
            }
        }

        return count($form_names);
    }

    private function exportFieldName( $export, $field_name, $event_id, $event_settings)
    {
        if ( $export->export_layout==="h" && $field_name !== $this->getRecordIdField() ) {
    
            return $this->eventPrefixForEventId($event_id, $event_settings) . "_" . $field_name;
        }
    
        return $field_name;
    }
    
    private function eventPrefixForEventId($event_id, $event_settings)
    {
        for ( $i=0; $i<count($event_settings); $i++){
    
            if ( $event_settings[$i]['event_id']==$event_id ){
    
                return $event_settings[$i]['event_prefix'];
            }
        }
    
        return "???";
    }
 
    private function specificationTypeToVarType( $spec_type, $valueset )
    {
        if ( $valueset ) return "NOMINAL";

        if ( !$spec_type ) return "TEXT";

        $spec_type = strtolower(trim($spec_type));

        if ( $spec_type==="string" ) return "TEXT";
        if ( $spec_type==="text" ) return "TEXT";
        if ( $spec_type==="character" ) return "TEXT";
        if ( $spec_type==="integer" ) return "INTEGER";
        if ( $spec_type==="float" ) return "FLOAT";
        if ( $spec_type==="date" ) return "DATE";
        if ( $spec_type==="datetime" ) return "DATETIME";
        if ( $spec_type==="time" ) return "TIME";
        if ( $spec_type==="number" ) return "FLOAT";
        if ( $spec_type==="real" ) return "FLOAT";
        if ( $spec_type==="categorical" ) return "NOMINAL";

        return "TEXT";
    }

    private function REDCapFieldTypeToVarType( $field_type, $field_validation )
    {
        if ( $field_type === "radio" ) return "NOMINAL";
        if ( $field_type === "dropdown" ) return "NOMINAL";
        if ( $field_type === "yesno" ) return "NOMINAL";
        if ( $field_type === "truefalse" ) return "NOMINAL";
        if ( $field_type === "checkbox" ) return "CHECKBOX";
        if ( $field_type === "select" ) return "NOMINAL";
        if ( $field_type === "slider" ) return "INTEGER";
        if ( $field_type === "calc" ) return "FLOAT";

        if ( $field_validation === "date_mdy" ) return "DATE";
        if ( $field_validation === "date_ymd" ) return "DATE";
        if ( $field_validation === "date_dmy" ) return "DATE";
        if ( $field_validation === "datetime_mdy" ) return "DATETIME";
        if ( $field_validation === "datetime_ymd" ) return "DATETIME";
        if ( $field_validation === "datetime_dmy" ) return "DATETIME";
        if ( $field_validation === "datetime_seconds_mdy" ) return "DATETIME";
        if ( $field_validation === "datetime_seconds_ymd" ) return "DATETIME";
        if ( $field_validation === "datetime_seconds_dmy" ) return "DATETIME";
        if ( $field_validation === "time" ) return "TIME";
        if ( $field_validation === "float" ) return "FLOAT";
        if ( $field_validation === "int" ) return "INTEGER";

        return "TEXT";
    }

    /* ==== CRONS ==== */

    public function getAdminEmail()
    {
        $admin_user = $this->getProjectSetting("cron-user");

        if ( $admin_user ){

            $userObj = $this->getUser( $admin_user );

            if ( $userObj ) return $userObj->getEmail();
        }

        return "joe.user@trantor.gov";
    }

    public function getAdminUsername()
    {
        $admin_user = $this->getProjectSetting("cron-user");

        if ( $admin_user ){

            $userObj = $this->getUser( $admin_user );

            if ( $userObj ) return $userObj->getUsername();
        }

        return "joe user";
    }

    /**
     * CRON manager
     * 
     * Handles the scheduling and execution of CRON jobs for the YES3 Exporter II.
     * 
     * Per config.json, this function is called every 30 minutes, and is allowed up to an hour to run.
     * However, the daily tasks are executed only once in a given 24-hour period.
     * 
     * While this is run at the system level, the cron log for each project is stored in the project settings.
     * 
     */
    public function yes3_exporter_cron( $cronInfo=['cron_description'=>"noname"] )
    {
        // ensures only one cron execution per 24 hour period,
        // based on the last execution time that is stored in the system settings
        if ( !$this->okayToRunCron() ){

            return "";
        }

        $t0 = time();

        $projects = 0;

        $cronlog = "Starting the \"{$cronInfo['cron_description']}\" cron job at " . strftime("%F %T");

        $originalPid = $_GET['pid'];
      
        // set the cron start time
        $this->setSystemSetting("cron-ran-at", strftime("%F %T"));

        //$cronlog .= "\n" . "Original PID: [" . $originalPid . "]";

        foreach($this->getProjectsWithModuleEnabled() as $localProjectId){

            $this->setProjectId($localProjectId); // to keep framework methods happy

            $_GET['pid'] = $localProjectId;

            $cron_user = $this->getProjectSetting("cron-user");

            if ( $cron_user ) $this->setCronUsername($cron_user); // to keep Yes3Trait methods happy
            else $this->unsetCronUsername();

            //$x = $this->getGroupNames();
            //$y = $this->getEventNames();

            $projects++;

            $projCronLog = "Starting the \"{$cronInfo['cron_description']}\" cron job at " . strftime("%F %T") . " for project #{$localProjectId}";

            //$this->project_id = $localProjectId;

            //$this->logDebugMessage($localProjectId, "project {$localProjectId} has YES3 Exporter module enabled", "yes3_exporter_cron");

            // BATCH EXPORT

            if ( $this->getProjectSetting("enable-cron-batch-exports") ){

                $projCronLog .= "\n" . $this->cronJob( "exportBatch" );
            }

            // DAILY EMAIL

            if ( $this->getProjectSetting("notification-email-enable") ){

                $projCronLog .= "\n" . $this->cronJob( "emailDailyLog" );
            }

            // HOUSEKEEPING

            $projCronLog .= "\n" . $this->cronJob( "hk_generations" );

            // PROJECT CRON LOG

            $projCronLog .= "\nEnding the \"{$cronInfo['cron_description']}\" cron job at " . strftime("%F %T") . " for project #{$localProjectId}";

            $cronlog .= "\n" . $projCronLog;
    
            $this->setProjectSetting("project-cron-log", $projCronLog);
        }
    
        $_GET['pid'] = $originalPid;

        
        //$cronlog .= "\n" . "PID: [" . $originalPid . "]";

        // SYSTEM CRON TIME AND LOG

        $cronlog .= "\nEnding the \"{$cronInfo['cron_description']}\" cron job at " . strftime("%F %T") . "\n";

        //$this->setSystemSetting("cron-ran-at", strftime("%F %T"));
        $this->setSystemSetting("cron-log", $cronlog);

        // SYSTEM LOG ENTRY

        $seconds = time() - $t0;

        $cron_summary = "YES3 Exporter cron jobs completed for {$projects} project(s). Run time was {$seconds} seconds.";

        $params = [
            "log_entry_type" => self::EMLOG_TYPE_CRON_LOG,
            "cronlog" => $cronlog
        ];

        $this->log( $cron_summary, $params );

        return $cronlog;
    }

    private function cronJob( $methodName )
    {
        try {

            $cronLog = $this->$methodName();
        } 
        catch( \Exception $e ){

            $cronLog = "{$methodName} ERROR: " . $e->getMessage();
            $this->logException( "{$methodName} cron job exception", $e);
        }

        return $cronLog;
    }

    /**
     * Ensures cron runs once per 24 hour interval,
     * based on the cron run time specified in the system settings (defaults to 00:11:00).
     * 
     * function: okayToRunCron
     * 
     * @return int|false
     * @throws Exception
     */
    private function okayToRunCron()
    {
        $today = strftime("%F");
        
        $cron_ran_at = $this->getSystemSetting("cron-ran-at");
        if ( $cron_ran_at ) {

            $lastRunDay = strftime("%F", strtotime($cron_ran_at));

            if ( $today === $lastRunDay ){

                return false; // ran today sometime
            }
        }

        $cron_time = $this->getSystemSetting("cron-time"); // hh:mm:ss to run job
        if ( !$cron_time ) {

            $cron_time = "00:11:00";
            $this->setSystemSetting("cron-time", $cron_time);
        }
        $runAt = strtotime( $today." ".$cron_time ); // today's cron run time

        return ( time() >= $runAt );
    }

    public function exportBatch()
    {
        $project_id = $this->getProjectId();

        //$this->determineExportFileType();

        $time = time();

        $bxLog = "\nBatch export started for project #{$project_id} at " . strftime("%F %T");

        $exports = $this->getExportSpecificationList(); if ( !$exports ) $exports=[];

        if ( !count($exports) ) return "exportBatch: Nothing to do since project has no non-removed exports.";

        $cron_user = $this->getProjectSetting("cron-user");

        if ( $cron_user ){

            $this->setCronUsername($cron_user); // to keep Yes3Trait methods happy
        }
        else return "exportBatch: The cron user is not set.";

        $batchExportCount = 0;

        foreach ($exports as $export){

            if ( $export['export_batch']=='1' ){

                $batchExportCount++;

                $export_uuid = $export['export_uuid'];

                $bxLog .= "\n" . $this->exportData($export_uuid, true);
            }
        }

        $time = (time() - $time)/60; // minutes elapsed

        return $bxLog . "\nBatch export ran for project #{$project_id} in {$time} minutes. {$batchExportCount} exports were processed.";
    }

    public function emailDailyLog(){

        if ( !$to = $this->getAdminEmail() ){

            return "Cannot email daily log summary: no email address is provided.";
        }

        $sincewhen = strftime("%F %T", time()-Yes3Fn::ONE_DAY);

        $cc = "";

        $bcc = "";

        $project_contact = $this->getProjectContact(); // as stored in project settings

        if ( $project_contact['project_contact_email'] ){

            $from = $project_contact['project_contact_email'];
        }
        else {

            $from = $to;
        }

        $fromName = "YES3 Exporter";

        $subject = "YES3 Exporter Daily Log Report for PID #" . $this->getProjectId();

        $export_logs = $this->getExportLogs("", false, $sincewhen);

        if ( !$export_logs ) {
            
            $export_logs = [];

            return "The daily activity log summary was NOT emailed because there was nothing to report.";
        }

        $msg = '<html><body style="font-family:arial,helvetica;">';

        $msg .= '<style>td,th{padding-right: 10px;text-align:left;}</style>';

        $msg .= '<p>You are receiving this email because you have enabled notifications from the REDCap YES3 Exporter II.</p>';

        $msg .= '<table style="border-collapse:collapse;"><tbody>';

        $msg .= "<tr>" . $this->emailTableCell("td", "Date and time of report") . $this->emailTableCell("td", strftime("%F %T")) . "</tr>";
        $msg .= "<tr>" . $this->emailTableCell("td", "REDCap host") . $this->emailTableCell("td", APP_PATH_WEBROOT_FULL) . "</tr>";
        $msg .= "<tr>" . $this->emailTableCell("td", "REDCap project id (pid)") . $this->emailTableCell("td", $this->getProjectId()) . "</tr>";
        $msg .= "<tr>" . $this->emailTableCell("td", "REDCap project title") . $this->emailTableCell("td", $this->getProject()->getTitle()) . "</tr>";

        $msg .= '</tbody></table>';

        //$msg .= '<p style="text-decoration:underline;">Export logging activity</p>';

        $msg .= '<p>' . count($export_logs) . ' export events were logged in the past 24 hours.</p><p>Use the YES3 Exporter Log plugin to inspect and/or download the detailed log entries.</p>';

        $msg .= '<table style="border-collapse:collapse;"><tbody>';

        $msg .= '<tr>';

        $msg .= $this->emailTableCell("th", "user");
        $msg .= $this->emailTableCell("th", "timestamp");
        $msg .= $this->emailTableCell("th", "log_id");
        $msg .= $this->emailTableCell("th", "export name");
        $msg .= $this->emailTableCell("th", "summary");
        //$msg .= $this->emailTableCell("th", "destination");        
        //$msg .= $this->emailTableCell("th", "log message");
        //$msg .= $this->emailTableCell("th", "records");

        $msg .= '</tr>';

        foreach ($export_logs as $log){

            $export_summary = $log['message'];

            $rows = $log['exported_rows'] ?? 0;

            if ( $log['destination'] === self::DESTINATION_DOWNLOAD ){

                if ( $log['message'] === self::LOG_MESSAGE_FILES_WRITTEN ){

                    $export_summary = "{$rows} record(s) generated for download.";
                }
            }
            elseif ( $log['destination'] === self::DESTINATION_BATCH ){

                $export_summary = "{$rows} record(s) exported via batch job.";
            }
            elseif ( $log['destination'] === self::DESTINATION_CRON ){

                $export_summary = "{$rows} record(s) exported via cron job.";
            }
            elseif ( $log['destination'] === self::DESTINATION_FILESYSTEM ){

                $export_summary = "{$rows} record(s) exported to filesystem.";
            }

            $msg .= '<tr>';

            $msg .= $this->emailTableCell("td", $this->escape($log['user_name']));
            // timestamp in yyyy-mm-dd hh:mm:ss format
            $msg .= $this->emailTableCell("td", $this->escape(date("Y-m-d H:i:s", strtotime($log['timestamp']))));
            $msg .= $this->emailTableCell("td", $this->escape($log['log_id']));
            $msg .= $this->emailTableCell("td", $this->escape($log['export_name']));
            $msg .= $this->emailTableCell("td", $this->escape($export_summary));
            //$msg .= $this->emailTableCell("td", $this->escape($log['destination']));
            //$msg .= $this->emailTableCell("td", $this->escape($log['message']));
            //$msg .= $this->emailTableCell("td", $this->escape($log['exported_rows']));

            $msg .= '</tr>';
        }

        $msg .= '</tbody></table>';

        $msg .= "</body></html>";
        /*
        print "to=" . $to
            . "<br>from=" . $from
            . "<br>subject=" . $subject
            . "<br>msg=<br>" . $msg
            . "<br>fromName=" . $fromName
        ;
        */
        $result = REDCap::email( 
            
            $to,
            $from,
            $subject,
            $msg,
            $cc,
            $bcc,
            $fromName

        );

        if ( $result ){

            $this->setProjectSetting("notification-email-ran-at", strftime("%F %T"));

            return "The daily activity log summary was emailed to {$to}.";
        }

        return "The daily activity log summary was NOT emailed.";
    }

    private function getProjectContact()
    {
        $sql = "
        SELECT c1.value AS `project_contact_email`, c2.value AS `project_contact_name` 
        FROM redcap_config c1
          LEFT JOIN redcap_config c2 ON c2.field_name = 'project_contact_name'
        WHERE c1.field_name = 'project_contact_email'
        ";

        return $this->fetchRecord( $sql );
    }

    private function emailTableCell( $TdOrTh, $content ){

        return "<{$TdOrTh} style='padding-right:15px;text-align:left'>{$content}</{$TdOrTh}>";
    }

    /* ==== DAILY HOUSEKEEPING ==== */

    public function hk_generations()
    {
        $nGens = $this->getProjectSetting("export-spec-backup-retention");

        if ( $nGens === "all" ) return "hk_generations: Nothing to do since project is configured to retain all backups.";

        $log = "";

        $exports = $this->getExportSpecificationList(); if ( !$exports ) $exports=[];

        if ( !count($exports) ) return "hk_generations: Nothing to do since project has no export backups saved.";

        //$this->logDebugMessage($this->getProjectId(), print_r($exports, true), "hk_generations");

        foreach($exports as $export){

            $specification_history = $this->getExportSpecification($export['export_uuid'], 0, true);
            
            $nHx = count($specification_history);

            if ( $log ) $log .= "\n";

            $log .= "export_uuid=" . $export['export_uuid'] . ", export_name=" . $export['export_name'] . ", generation count=" . $nHx;

            $k= 0;
            $removed = 0;
            $ts = "";
            foreach ( $specification_history as $hx) {

                $k++;

                if ( $k <= $nGens ){

                    $ts = $hx['timestamp'];
                }
                else {

                    $removed++;
                }
            }

            if ( !$removed ){

                $log .= ": no backup generations removed.";
            }
            else {
            
                $log .= ": " . $removed . " generations saved before " . strftime("%F %T", strtotime($ts)) . " removed. ";

                $pSql = "project_id = ? and export_uuid = ? and message = ? and timestamp < ?";
                $params = [$this->getProjectId(), $export['export_uuid'], self::EMLOG_MSG_EXPORT_SPECIFICATION, $ts];
                $this->removeLogs( $pSql, $params);
            }
        }
        return $log;
    }

    public function getExportSpecificationList($get_removed=""):array
    {
       /**
         * Distinct export specifications best determined by direct query
         */
        $sqlUUID = "
        SELECT DISTINCT p01.`value` AS `export_uuid`
        FROM redcap_external_modules_log x
        INNER JOIN redcap_external_modules_log_parameters p01 ON p01.log_id=x.log_id AND p01.name='export_uuid'
        WHERE x.external_module_id=? and x.project_id=? and x.message=?
        ";

        $UUIDs = $this->fetchRecords($sqlUUID, [$this->getModuleId(), $this->getProjectId(), self::EMLOG_MSG_EXPORT_SPECIFICATION]);

        $data = [];

        foreach($UUIDs as $u){

            $s = $this->getExportSpecification($u['export_uuid']);

            if ( $s['removed']==='0' || $get_removed ) {

                $data[] = [
                    'timestamp' => $s['timestamp'],
                    'log_id' => $s['log_id'],
                    'export_uuid' => $s['export_uuid'],
                    'export_name' => ( $s['export_name'] ) ? $this->escapeHtml($s['export_name']) : "noname-{$s['log_id']}",
                    'export_layout' => $s['export_layout'],
                    'export_username' => ( $s['export_username'] ) ? $s['export_username'] : "nobody",
                    'export_batch' => $s['export_batch'] ?? "",
                    'removed' => $s['removed']
                ];
            }
        }

        return $data;
    }

    public function countExportItems( $export_items_json )
    {
        if ( !$export_items_json ){

            return "0";
        }

        $elements = json_decode( $export_items_json, true );

        if ( !is_array($elements) ){

            return "err";
        }

        return (string) count( $elements );
    }

    /* ==== FILESYSTEM WRITE TEST ==== */

    public function testFilesystemWrite( $testPath=null )
    {

        if ( $testPath === null ) $testPath = trim($this->getProjectSetting('export-target-folder'));

        if ( !$testPath ){

            return Yes3Fn::failString("No path is provided. Check the EM configuration.");
        }

        $filename = "yes3_exporter_test_" . Yes3Fn::compactTimestamp() . ".txt";

        // append the directory separator to the mount path if it is not already there
        if (substr($testPath, -1) !== DIRECTORY_SEPARATOR) {
            $testPath .= DIRECTORY_SEPARATOR;
        }
        
        // path to the file to be created
        $file_path = $testPath . $filename;

        $file_content = "This is a test file created by the Yes3 Exporter2 External Module on " . date('Y-m-d H:i:s');

        $file_content .= "\nProject: " . $this->getProject()->getTitle() . " ( pid " . $this->getProjectId() . " )";

        $file_content .= "\nUser: " . $this->getUser()->getUsername() . "\n";

        // write the content to the file and store the result in a variable
        $write_result = file_put_contents($file_path, $file_content);

        if ($write_result === false) {

            return Yes3Fn::failString("Failed to write to file: $file_path.\nPlease check the path and permissions.");
        } else {

            // delete the test file after writing
            unlink($file_path);

            // confirm deletion
            if (file_exists($file_path)) {
                return Yes3Fn::failString("The test file was written but could not be deleted: $file_path.\nPlease check permissions.");
            }

            return Yes3Fn::successString("Wrote $write_result bytes to file: $file_path.\nThe test file was successfully deleted.");
        }
    }

    /* ==== IMPORT EXPORTER I SETTINGS ==== */

    public function transferLegacyEnvironment() {

        $tx1 = $this->transferLegacySettings();

        $tx2 = $this->transferLegacyEMLogs( true );

        $log = implode("\n", array_merge($tx1['log'], $tx2['log']));

        $errors = $tx1['errors'] + $tx2['errors'];

        $settings_transferred = $tx1['transferred'];

        $logs_transferred = $tx2['transferred'];

        $summary = "Transfers completed. $settings_transferred project settings transferred, $logs_transferred logs transferred.";

        if ( $errors > 0 ){
            
            $summary .= "\nNOTE: $errors error(s) occurred during the transfer. See the console log (F12) for details.";
        }
        else {

            $this->setProjectSetting('legacy-transfer-status', 'transferred');
        }

        // log this transfer
        $this->log( self::LOG_MESSAGE_LEGACY_TRANSFERRED, [ 'summary' => $summary, 'log' => $log, 'errors' => $errors ]);

        return [
            'log' => $log,
            'errors' => $errors,
            'summary' => $summary
        ];
    }

    private function getLegacyProjectSettings() {

        $legacy_external_module_id = $this->getLegacyEMID();

        if ( !$legacy_external_module_id ) {

            return [];
        }

        $sql = "select ems.*
        from redcap_external_modules em
        inner join redcap_external_module_settings ems on ems.external_module_id=em.external_module_id
        where em.external_module_id=? and ems.project_id=? and ifnull(ems.value, '') <> ''";

        return Yes3Fn::fetchRecords($sql, [ $legacy_external_module_id,$this->getProjectId() ]);
    }

    private function transferLegacySettings() {

        $legacyProjectSettings = $this->getLegacyProjectSettings();

        if ( !$legacyProjectSettings ) {

            return [ 'No legacy project settings found.' ];
        }

        $transfer_log = [];

        $configProjectSettings =$this->getConfig()['project-settings'];

        $settingsTransferred = 0;

        $errors = 0;

        foreach ( $legacyProjectSettings as $setting ) {

            // see if the legacy key is in the config
            foreach ( $configProjectSettings as $configSetting ) {

                if ( $setting['key'] == $configSetting['key']) {

                    if ( empty($this->getProjectSetting($setting['key']))) {

                        $value = $setting['value'];

                        $blockBoolean = false; // no need to transfer a false boolean

                        // most legacy booleean settings were set up as 'Y'/'N' radio buttons
                        if ( $configSetting['type'] == 'checkbox' ) {

                            if ( $value === 'Y' || $value === '1' ) {
                                $value = "1";
                            } else {
                                $value = "0";
                                $blockBoolean = true;
                            }
                        }

                        if ( !$blockBoolean ){
                            $transfer_log[] = "Transferred legacy project setting {$setting['key']}.";

                            $this->setProjectSetting($setting['key'], $value);
                            $settingsTransferred++;
                        }

                        continue 2;
                    }
                }
            }
        }

        $transfer_log[] = "Transferred $settingsTransferred legacy project settings.";

        return [
            'errors' => $errors,
            'transferred' => $settingsTransferred,
            'log' => $transfer_log
        ];
    }

    private function setLogTimestamp( $log_id, $timestamp ) {

        $sql = "update redcap_external_modules_log set timestamp=? where log_id=?";
        $this->query($sql, [ $timestamp, $log_id ]);

        // verify: this is fabulously important
        $storedTimestamp = Yes3Fn::fetchValue("select timestamp from redcap_external_modules_log where log_id=?", [ $log_id ]);

        return $storedTimestamp === $timestamp;
    }

    private function transferLegacyEMLogs( $force=false ) {

        $legacy_external_module_id = $this->getLegacyEMID();

        $this_external_module_id = $this->getModuleId();

        $transfer_log = [];

        $sql = "select eml.* from redcap_external_modules_log eml where eml.external_module_id=? and eml.project_id=?";

        $legacy_logs = Yes3Fn::fetchRecords($sql, [ $legacy_external_module_id, $this->getProjectId() ]);
        $legacy_log_count = count($legacy_logs);
        $transfer_log[] = "Processing $legacy_log_count legacy logs.";
        $transfer_count = 0;
        $transfer_errcount = 0;
        foreach ( $legacy_logs as $legacy_log ) {

            $legacy_log_id = $legacy_log['log_id'];
            $new_log_id = null;
            $restamped = false; // the log timestamp must be transferred or else versioning is kaput

            // skip or replace if already transferred
            if ( $this->logIsAlreadyTransferred($legacy_log_id) ) {

                if ( !$force ) {
                    $transfer_log[] = "Legacy log $legacy_log_id has already been transferred, and will be skipped.";
                    continue;
                }

                $removed_log_count = $this->removeTransferredLegacyLog($legacy_log_id);

                if ( !$removed_log_count ) {
                    $transfer_log[] = "Failed to remove the transferred copy of legacy log $legacy_log_id.";
                    $transfer_errcount++;
                    continue;
                }

                $transfer_log[] = "Legacy log $legacy_log_id will be re-transferred.";
            }

            $legacy_log_message = $legacy_log['message'];
            $legacy_log_timestamp = $legacy_log['timestamp'];

            // gather ye parameters
            $legacy_parameters = $this->getEMLogParameters($legacy_log_id);

            $legacy_parameters['legacy_log_id'] = $legacy_log_id; // so we can prevent repeat transfers

            $legacy_parameter_count = count($legacy_parameters);

            // store the legacy log message and parameters
            $new_log_id = $this->log($legacy_log_message, $legacy_parameters);

            if ( $new_log_id ) {

                // transfer the timestamp so versioning will work correctly
                $restamped = $this->setLogTimestamp($new_log_id, $legacy_log_timestamp);

                if ( !$restamped ) {
                    $transfer_log[] = "Failed to transfer timestamp for legacy log $legacy_log_id to new log $new_log_id.";
                    $transfer_errcount++;
                }
            }

            if ( $new_log_id && $restamped ) {

                $transfer_log[] = "Transferred legacy log $legacy_log_id ($legacy_log_message) to new log $new_log_id with $legacy_parameter_count parameters.";
                $transfer_count++;
            }
            else {
                $transfer_log[] = "Failed to transfer legacy log $legacy_log_id ($legacy_log_message).";
                $transfer_errcount++;
            }
        }

        $transfer_log[] = "Transferred $transfer_count legacy log(s).";
        $transfer_log[] = "$transfer_errcount error(s) reported.";

        return [
            'errors' => $transfer_errcount,
            'transferred' => $transfer_count,
            'log' => $transfer_log
        ];
;
    }
    
    private function getEMLogParameters( $log_id = null ) {

        $sql = "select name, value from redcap_external_modules_log_parameters where log_id=?";
        $rows = Yes3Fn::fetchRecords($sql, [ $log_id ]);

        if ( empty($rows) ) {
            return [];
        }

        $parameters = [];

        foreach ( $rows as $row ) {
            $parameters[$row['name']] = $row['value'];
        }

        return $parameters;
    }

    private function getLegacyEMID(){

        return Yes3Fn::getEMIDbyPrefix("yes3_exporter");
    }

    private function logIsAlreadyTransferred( $legacy_log_id ){

        $psuedoSql = "select log_id where legacy_log_id = ?";
        $params = [$legacy_log_id];
        $result = $this->queryLogs($psuedoSql, $params);

        return ( $result->num_rows > 0 );
    }

    private function removeTransferredLegacyLog( $legacy_log_id ){

        return $this->removeLogs( 'legacy_log_id=?', $legacy_log_id );
    }

    public function legacyEnvironmentExists()
    {

        $legacy_project_settings = $this->getLegacyProjectSettings();


        return !empty($legacy_project_settings);
    }

    public function determineLegacyTransferStatus()
    {
        $legacy_transfer_status = $this->getProjectSetting("legacy-transfer-status") ?? "";

        if ( !$legacy_transfer_status ) {

            $legacy_environment_exists = $this->legacyEnvironmentExists();
            $legacy_transfer_status = $legacy_environment_exists ? "pending" : "nolegacy";
            $this->setProjectSetting("legacy-transfer-status", $legacy_transfer_status);
        }

        return $legacy_transfer_status;
    }

    /* ==== HOOKS ==== */

    public function redcap_module_link_check_display( $project_id, $link )
    {  
        $user_rights = $this->yes3UserRights();

        $user_name = $this->getUsername();

        $enable_host_filesystem_exports = $this->getProjectSetting("enable-host-filesystem-exports") ?? false;

        //$projectSettings = $this->getProjectSettings();

        if ( !$user_rights['exporter'] ){

            return false;
        }

        // event prefixes are applicable only to longitudinal projects
        if ( !$this->isLongitudinal() && $link['name'] !== "YES3 Exporter II Event Prefixes" ){ 

            return false;
        }

        if ( $link['name'] === "YES3 Exporter II Mount Test" 
            && (!$enable_host_filesystem_exports || !$user_rights['isDesigner'])) { 

            return false;
        }

        if ($link['name'] === "YES3 Exporter II Workshop" 
            && $user_name !== "criwebtools" && $user_name !== "charpe" ) { 

            return false;
        }

        if ( !$user_rights['isDesigner'] && 
            (   $link['name'] === "YES3 Exporter II Manager"  ||
                $link['name'] === "YES3 Exporter II Event Prefixes"
            )){

            return false;
        }

        return $link;
    }

    function redcap_every_page_top($project_id)
    {        
        if ( PAGE !== 'manager/project.php') {

            return; // only run on the EM manager page
        }

        $xpII = new \Yale\Yes3Exporter2\Yes3Exporter2();

        $legacy_transfer_status = $xpII->determineLegacyTransferStatus();

        if ( $legacy_transfer_status !== "pending" ){

            return; // no legacy transfer form to render
        }

        $module_json = "4";

        ?>

        <style>

            #legacy-transfer-form div {
                width: 100%;
                margin: 5px 0;
                font-weight: 600;
                color: black;
                font-size: 11px;
            }
            
            #legacy-transfer-form button {
                margin-right: 8px;
            }

            #ajax-response {
                width: 100%;
                color: black;
                margin: 5px 0;
                font-weight: 600;
            }
            
            
        </style>

        <?=$xpII->initializeJavascriptModuleObject()?>

        <script>

            $( function () {
                
                const module = <?=$this->getJavascriptModuleObjectName()?>;
                const legacy_transfer_status = "<?php echo $legacy_transfer_status; ?>";
                
                const $emSettingsContainer = $('tr[data-module="yes3_exporter2"]');
                const $description = $emSettingsContainer.find('div.external-modules-description');

                console.log("YES3 Exporter II module object: ", module);
                console.log("Legacy transfer status: ", legacy_transfer_status);
                
                if (legacy_transfer_status==='pending') {

                    renderLegacyTransferForm();
                }

                function renderLegacyTransferForm() {

                    const $form              = $('<form>').attr('id', 'legacy-transfer-form').prop('novalidate', true);
                    const $explain           = $('<div>').addClass('explanation').text('Legacy Yes3 Exporter I environment detected. Click the button below to transfer all of your legacy exporter project settings, export specifications and logs to the new YES3 Exporter II module. This will not overwrite any existing settings or specifications in the new module.');
                    const $submit_transfer   = $('<button>').attr('id', 'submit-transfer').attr('action', 'legacy-transfer').attr('type', 'submit').text('Yes, please transfer').addClass('btn btn-success btn-sm');
                    const $submit_notransfer = $('<button>').attr('id', 'submit-notransfer').attr('action', 'no-legacy-transfer').attr('type', 'submit').text(`No, do not transfer (and don't ask again)`).addClass('btn btn-sm btn-rcred btn-rcred-light');

                    $form
                    .append($explain)
                    .append($submit_transfer)
                    .append($submit_notransfer)
                    ;

                    $description
                    .append($form);

                    addFormListener();
                }

                function addFormListener(){

                    const $form = $('#legacy-transfer-form');

                    $form.on('submit', function (e) {

                        e.preventDefault(); // action irrelevant here

                        const btn    = e.originalEvent.submitter;  // <- jQuery wraps, so use .originalEvent
                        const action = btn.getAttribute('action');

                        module.ajax(action, {}).then(function(response) {

                            $form.remove();
                            $description.append($('<div>').attr('id', 'ajax-response').text(response['summary']));
                            // Process response
                            console.log('Transfer log:', response['log']);
                        }).catch(function(err) {
                            // Handle error
                            console.error('AJAX error:', err);
                        });                        
                    });
                }
            });

        </script>

        <?php
    }

    private function handleLegacyTransfer()
    {
        // Handle legacy transfer
        return $this->transferLegacyEnvironment();
    }

    private function handleNoLegacyTransfer()
    {
        $this->setProjectSetting('legacy-transfer-status', 'declined');

        // Handle no legacy transfer
        return [
            'summary' => 'Legacy transfer declined. No further action taken.',
            'errors' => 0,
            'log' => ''
        ];
    }

    public function redcap_module_ajax($action, $payload, $project_id, $record, $instrument, $event_id, $repeat_instance, $survey_hash, $response_id, $survey_queue_hash, $page, $page_full, $user_id, $group_id)
    {
        // Handle AJAX requests
        if ($action === 'legacy-transfer') {

            return $this->handleLegacyTransfer();

        } elseif ($action === 'no-legacy-transfer') {

            return $this->handleNoLegacyTransfer();
        }
        elseif ( $action === 'test-filesystem-write' ) {

            return $this->testFilesystemWrite();
        }
        else {

            return "Sorry, the action '$action' is most abhorrent.";
        }
    }
}
