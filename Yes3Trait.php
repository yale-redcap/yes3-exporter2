<?php

namespace Yale\Yes3Exporter2;

use REDCap;
use Project;
use ExternalModules\ExternalModules as EM;
use System;

use \DateTime;
use \DateTimeZone;
use \Exception;
use \Throwable;

trait Yes3Trait {

    public $cron_username = "";
    public $isLongitudinal = false;
    public $yes3UserRights = [];

    public function objectProperties()
    {
        $propKeys = [];

        /**
         * A ReflectionObject is apparently required to distinuish the non-private properties of this object
         * https://www.php.net/ReflectionObject
         */
        $publicProps = (new \ReflectionObject($this))->getProperties(\ReflectionProperty::IS_PUBLIC+\ReflectionProperty::IS_PROTECTED);

        foreach( $publicProps as $rflxnProp){
            $propKeys[] = $rflxnProp->name;
        }
        
        $props = [ 'CLASS' => __CLASS__ ];

        foreach ( $propKeys as $propKey ){

            $json = json_encode($this->$propKey);

            /**
             * some properties can't be json-encoded...
             */
            if ( $json===false ){
                $props[$propKey] = "json encoding failed for {$propKey}: " . json_last_error_msg();
            }
            else {
                $props[$propKey] = $this->$propKey;
            }
        }

        if ( !$json = json_encode($props) ){
            return json_encode(['message'=>json_last_error_msg()]);
        }
        
        return $json;
    }

    public function setCronUsername( $username="" )
    {
        $this->cron_username = $username;
    }

    public function unsetCronUsername()
    {
        $this->cron_username = "";
    }

    public function getCronUsername()
    {
        return $this->cron_username;
    }

    public function getUsername(){

        if ( $this->getCronUsername() ) return $this->getCronUsername();
        
        return $this->getUser()->getUsername();
    }

    public function getUserObject()
    {
        return $this->getUser($this->getUsername());
    }

    /**
     * Returns a curated array of user rights and form export permissions
     * 
     * @param bool $designerHasExportRights If true, designers are treated as having full export rights
     *                                      Note: currently this param is never set to true
     * @return array
     */
    public function yes3UserRights( $designerHasExportRights=false )
    {
        $User = $this->getUserObject();
        
        $isDesigner = ( $User->hasDesignRights() ) ? 1:0;

        $user = $User->getRights();

        $longitudinal = $this->isLongitudinal();

        //$this->logDebugMessage($this->project_id, print_r($user, true), "user rights");

        /**
         * The rank order of export permission codes
         * 
         * 0 - no access (export code = 0)
         * 1 - de-identified: no identifiers, dates or text fields (export code = 2)
         * 2 - no identifiers (export code = 3)
         * 3 - full access (export code = 1)
         */
        $exportPermRank = [0, 3, 1, 2];

        /**
         * DATA ENTRY PERMISSIONS
         * Feb 24: for longitudinal projects, only forms that are on the event grid are included in the form permissions array
         */

        $formPermString = str_replace("[", "", $user['data_entry']);

        $formPerms = explode("]", $formPermString);
        $formPermissions = []; // view/edit permissions by form
        foreach( $formPerms as $formPerm){

            // apparently there can be empty entries
            if ( $formPerm ){

                $formPermParts = explode(",", $formPerm);
                $form_name = $formPermParts[0];

                // for longitudinal projects, only include forms that are on the event grid
                if ( !$longitudinal || $this->getREDCapEventsForForm($form_name) ){
                    // designers can always edit
                    $formPermissions[ $form_name ] = ($isDesigner) ? 1 : (int) $formPermParts[1];
                }
            }
        }

        /**
         * EXPORT PERMISSIONS
         * Feb 24: for longitudinal projects, only forms that are on the event grid are included in the form permissions array
         * 
         * The REDCap Export permission model changed with v12, so we have to handle both pre-v12 and v12+ permissions
         * 
         */

        $formExportPermissions = [];

        $exporter = ( $designerHasExportRights ) ? $isDesigner : 0; // designers can always export if flag is set

        if ( $isDesigner && $designerHasExportRights ){

            $export_tool = 1; // simulated pre-v12 property
        }
        else {

            // this is always blank in v12+, have to build it while sweeping the forms
            // i.e. we set it to the highest-ranked permission we find for any form
            $export_tool = (int)$user['data_export_tool']; 
            if ( !$export_tool ) $export_tool = 0; // I'm paranoid
        }

        // 'data_export_instruments' is a v12+ property
        if ( isset($user['data_export_instruments'])) {

            //$this->logDebugMessage($this->project_id, print_r($user['data_export_instruments'], true), "user[data_export_instruments]");

            /**
             * the data_export_instruments string looks like this:
             * [form_name,export_code][form_name,export_code]...
             *
             * So we explode on "]" to get each stringified form tuple, then explode each of those on "," to get the form name and export code tuple.
             */
            $formExportPermString = str_replace("[", "", $user['data_export_instruments']);

            $formExportPerms = explode("]", $formExportPermString);

            foreach( $formExportPerms as $formExportPerm){

                if ( $formExportPerm ){

                    $formExportPermParts = explode(",", $formExportPerm);

                    /**
                     * REDCap Form export permission codes and their rank orders:
                     * 
                     * 0 - no access (rank order 0)
                     * 1 - full access (rank order 3)
                     * 2 - de-identified: no identifiers, dates or text fields (rank order 2)
                     * 3 - no identifiers (rank order 1)
                     */
                    $xPerm = (int)$formExportPermParts[1];

                    $form_name = $formExportPermParts[0];

                    // for longitudinal projects, only include forms that are on the event grid
                    if ( !$longitudinal || $this->getREDCapEventsForForm($form_name) ){

                        // set the simulated pre-v12-style 'export_tool' property to the highest-ranked permission we find
                        if ( $exportPermRank[$xPerm] > $exportPermRank[$export_tool] ){

                            $export_tool = $xPerm; 
                        }

                        if ( $xPerm > 0 && $exporter === 0 ){

                            $exporter = 1; // boolean: has any export permission on any form
                        }
                        
                        $formExportPermissions[ $form_name ] = $xPerm;
                    }
                }
            }
        }
        // pre-v12
        else {

            // create the v12-style form export permission array from the form view/edit permissions, with each instrument having the export_tool permission
            foreach ( array_keys($formPermissions) as $instrument){

                $formExportPermissions[$instrument] = $export_tool;
            }
            $exporter = ( $export_tool > 0 ) ? 1 : 0; // boolean: has any export permission
        }

        /**
         * set export permission to "none" for any form the user is not allowed to view
         */
        foreach ( $formPermissions as $form_name=>$formperm){

            if ( !$formperm ){

                $formExportPermissions[$form_name] = 0;
            }
        }

        //$this->logDebugMessage($this->project_id, print_r($formPermissions, true), "form permissions");
        
        return [

            'username' => $User->getUsername(),
            'isDesigner' => ( $User->hasDesignRights() ) ? 1:0,
            'isSuper' => ( $User->isSuperUser() ) ? 1:0,
            'group_id' => (int)$user['group_id'],
            'dag' => ( $user['group_id'] ) ? REDCap::getGroupNames(true, $user['group_id']) : "",
            'export' => $export_tool,
            'import' => (int)$user['data_import_tool'],
            'api_export' => (int)$user['api_export'],
            'api_import' => (int)$user['api_import'],
            'form_permissions' => $formPermissions,
            'form_export_permissions' => $formExportPermissions,
            'exporter' => $exporter
        ];
    }

    /**
     * Returns the HTML, CSS and JS code required to run the specified page (libname)
     */
    public function getCodeFor( string $libname, bool $includeHtml=false ):string
    {
        $s = "";
        $js = "";
        $css = "";
        
        $s .= "\n<!-- Yes3 getCodeFor: {$libname} -->";
        
        $js .= file_get_contents( $this->getModulePath()."js/yes3.js" );  
        $js .= file_get_contents( $this->getModulePath()."js/common.js" );  
        $js .= file_get_contents( $this->getModulePath()."js/{$libname}.js" );

        $js .= "\n" . $this->initializeJavascriptModuleObject() . ";";

        $js .= "\nYES3.version = '" . $this->getVersion() . "';";

        $js .= "\nYES3.copyright = '" . $this->getCopyRight() . "';";

        $js .= "\nYES3.username = '" . $this->getUser()->getUsername() . "';";

        $js .= "\nYES3.userRights = " . json_encode( $this->yes3UserRights() ) . ";\n";

        $js .= "\nYES3.isLongitudinal = " . (( $this->isLongitudinal() ) ? "1":"0") . ";";

        $js .= "\nYES3.RecordIdField = '" . $this->getRecordIdField() . "';";

        $js .= "\nYES3.imageUrl = " . json_encode($this->getImageUrl()) . ";";

        $js .= "\nYES3.serviceUrl = '" . $this->getServiceUrl() . "';";

        $js .= "\nYES3.documentationUrl = '" . $this->getDocumentationUrl() . "';";

        $js .= "\nYES3.changelogUrl = '" . $this->getChangelogUrl() . "';";

        $js .= "\nYES3.technicalDocumentationUrl = '" . $this->getTechnicalDocumentationUrl() . "';";

        $js .= "\nYES3.overviewDocumentationUrl = '" . $this->getOverviewDocumentationUrl() . "';";

        $js .= "\nYES3.moduleObject = " . $this->getJavascriptModuleObjectName() . ";";

        $js .= "\nYES3.moduleObjectName = '" . $this->getJavascriptModuleObjectName() . "';";

        $js .= "\nYES3.moduleProperties = " . $this->objectProperties() . ";\n";

        $js .= "\nYES3.EMSettings = " . json_encode($this->getProjectSettings()) . ";\n";

        $css .= file_get_contents( $this->getModulePath()."css/yes3.css" );
        $css .= file_get_contents( $this->getModulePath()."css/common.css" );
        $css .= file_get_contents( $this->getModulePath()."css/{$libname}.css" );

        if ( $js ) $s .= "\n<script>{$js}</script>";

        if ( $css ) $s .= "\n<style>{$css}</style>";

        if ( $includeHtml ){
            $s .= file_get_contents( $this->getModulePath()."html/yes3.html" );
            $s .= file_get_contents( $this->getModulePath()."html/common.html" );
        }

        print $s;

        return $s;
    }

    /* ==== ERROR LOGGING ==== */

    public function logException( string $message, \Exception $e )
    {
        $exceptionReport = "message: " . $e->getMessage()
            . "\nFile: " . $e->getFile()
            . "\nLine: " . $e->getLine()
            . "\nTrace: " . $e->getTraceAsString()
        ;

        $params = [
            'username' => $this->getUsername(),
            'log_entry_type' => "yes3-export-error-report",
            'exception_report' => $exceptionReport,
        ];

        $log_id = $this->log(
            $message,
            $params
        );

        return $log_id;
    }

    /**
     * V13, V14+ compatible method for getting the redcap data table for supplied project_id
     * 
     * @param string $project_id 
     * @return mixed 
     */
    public function getDataTable( $project_id=0 ){

        if ( !is_numeric($project_id) || $project_id < 1 ) $project_id = (int) $this->getProjectId();

        if ( method_exists('REDCap', "getDataTable") ) {

            //$this->logDebugMessage($project_id, "using REDCap::getDataTable: project_id={$project_id}, dataTable=".REDCap::getDataTable($project_id), "getDataTable");
            
            return REDCap::getDataTable($project_id);
        }

        return "redcap_data";
    }

    /**
     * record generators: buffered and unbuffered
     * Note: unbuffered required faith that the global $rc_connection is available
     */

    public function recordGenerator( $sql, $parameters = [] )
    {
        $resultSet = $this->query($sql, $parameters);

        while ($row = $resultSet->fetch_assoc()) {

            yield $row;
        }
    }

    public function recordGeneratorUnbuffered( $sql, $parameters = [] )
    {
        global $rc_connection;

        $result = System::queryWithParameters($rc_connection, $sql, $parameters, MYSQLI_USE_RESULT);

        try {
            while ($row = $result->fetch_assoc()) {
                yield $row;
            }
        } catch (\Throwable $e) {
            // Handle exception (optional: log or rethrow)
            throw $e;
        } finally {
            // Free the result set; required for unbuffered queries
            if ($result) {$result->free();}
        }
    }

    /**
     * Fetch multiple records as an array of associative arrays
     */
    public function fetchRecords($sql, $parameters = [])
    {

        $rows = [];
        $resultSet = $this->query($sql, $parameters);
        if ( $resultSet->num_rows > 0 ) {
            while ($row = $resultSet->fetch_assoc()) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function sql_limit_1( $sql )
    {

        if ( stripos($sql, "LIMIT 1") === false ) {
            return $sql . " LIMIT 1";
        } else {
            return $sql;
        }

    }

    /**
     * Fetch a single record as an associative array
     */
    public function fetchRecord($sql, $parameters = [])
    {

        return $this->query($this->sql_limit_1($sql), $parameters)->fetch_assoc();
    }

    /**
     * Fetch a single value
     */
    public function fetchValue($sql, $parameters = [])
    {
        return $this->query($this->sql_limit_1($sql), $parameters)->fetch_row()[0];
    }

    public function tableExists($table_name)
    {
        $dbname = $this->fetchValue("SELECT DATABASE() AS DB");
        if ( !$dbname ) return false;
        $sql = "SELECT COUNT(*) FROM information_schema.tables"
            ." WHERE table_schema=?"
            ." AND table_name=?"
        ;
        return $this->fetchValue($sql, [$dbname, $table_name]);
    }

    public function json_encode_pretty( $x )
    {
        return json_encode( $x, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT );
    }

    public function is_json_encodable( $x )
    {
        if ( json_encode( $x )===false ) return false;
        return true;
    }

    public function is_json_decodable( $s )
    {
        if ( json_decode( $s)===null ) return false;
        return true;
    }

    public function getFirstREDCapEventId(int $project_id=null)
    {
        if ( !$project_id ){
            $project_id = $this->getProjectId();
        }

        $sql = "SELECT e.event_id
        FROM redcap_events_metadata e
            INNER JOIN redcap_events_arms a ON a.arm_id=e.arm_id
        WHERE a.project_id=?
        ORDER BY e.day_offset, e.event_id
        LIMIT 1";

        return $this->fetchValue($sql, [$project_id]);
    }

    public function getREDCapEventIdForField( string $field_name, int $project_id=null )
    {
        return $this->getREDCapEventIdForForm( $this->getREDCapFormForField($field_name, $project_id) );
    }

    /**
    * Returns first event_id associated with form
    * Only useful if form is on a single event
    * 
    * function: getREDCapEventIdForForm
    * 
    * @param string $form_name
    * @param int|null $project_id
    * 
    * @return mixed
    * @throws Exception
    */
    public function getREDCapEventIdForForm( string $form_name, int $project_id=null )
    {
        if ( !$project_id ){
            $project_id = $this->getProjectId();
        }

        if ( $this->isLongitudinal() ) {     
            $sql = "SELECT e.event_id
            FROM redcap_events_metadata e
                INNER JOIN redcap_events_arms a ON a.arm_id=e.arm_id
                INNER JOIN redcap_events_forms ef ON ef.form_name=? AND ef.event_id=e.event_id
            WHERE a.project_id=?
            ORDER BY e.day_offset, e.event_id
            LIMIT 1";

            return $this->fetchValue($sql, [$form_name, $project_id]) ?? 0;
        }
        
        return $this->getFirstREDCapEventId($project_id);
    }

    public function getREDcapEventsForForm($form_name, $project_id=null)
    {
        if ( !$project_id ){
            $project_id = $this->getProjectId();
        }

        if ( !$this->isLongitudinal() ) return $this->getFirstREDCapEventId($project_id);

        $sql = "SELECT e.event_id
        FROM redcap_events_metadata e
            INNER JOIN redcap_events_arms a ON a.arm_id=e.arm_id
            INNER JOIN redcap_events_forms ef ON ef.form_name=? AND ef.event_id=e.event_id
        WHERE a.project_id=?
        ORDER BY e.day_offset, e.event_id";

        $eventRecords = $this->fetchRecords($sql, [$form_name, $project_id]);

        if ( !$eventRecords ) return [];

        return array_column($eventRecords, 'event_id');
    }

    public function getEventIdForDescription( int $project_id, string $descrip)
    {
        return (int) $this->fetchValue(
            "SELECT e.event_id
            from redcap_events_metadata e
            INNER join redcap_events_arms a on a.arm_id=e.arm_id
            where a.project_id=? and e.descrip=?",
            [$project_id, $descrip]
        );
    }

    public function getREDCapFormForField( string $field_name, int $project_id=null )
    {
        if ( !$project_id ){
            $project_id = $this->getProjectId();
        }

        $sql = "SELECT m.form_name
        FROM redcap_metadata m
        WHERE m.project_id=? AND m.field_name=?
        LIMIT 1";

        return $this->fetchValue($sql, [$project_id, $field_name]);
    }

    public function getREDCapValue( string $record, string $field_name, int $event_id=null, $instance=1 )
    {
        $project_id = $this->getProjectId();

        if ( !$event_id ) {
            $event_id = $this->getREDCapEventIdForField($field_name, $project_id);
        }

        $redcap_data = $this->getDataTable($project_id);

        $sql = "
    SELECT `value` 
    FROM $redcap_data 
    WHERE `project_id`=? AND `event_id`=? AND `record`=? AND `field_name`=? AND ifnull(instance, 1)=? LIMIT 1
    ";
        return $this->fetchValue($sql, [$project_id, $event_id, $record, $event_id, $instance]);
    }

    public function REDCapDateTimeString()
    {
        return strftime("%Y-%m-%d %H:%M");
    }

    public function timeStampString()
    {
        return strftime("%y%m%d%H%M%S");
    }

    public function inoffensiveFieldName( $s )
    {

        if ( is_null($s) ){

            return "";
        }

        if ( !strlen($s) ){

            return "";
        }

        /**
         * @psalm-suppress InvalidReturnStatement
         */
        return preg_replace("/[^a-zA-Z0-9_]+/", "", str_replace(' ', '_', $s));
    }


    /**
     * Converts every ASCII/UTF-8 quotation mark-like character to straight quote (including html entities)
     * 
     * adapted from:
     * https://stackoverflow.com/questions/20025030/convert-all-types-of-smart-quotes-with-php
     * 
     * function: straightQuoter
     * 
     * @param $s
     * 
     * @return string
     */
    public function straightQuoter( $s ):string
    {  

        if ( is_null($s) ){

            return "";
        }

        if ( !strlen($s) ){

            return "";
        }

        $qSearch = [

            '"',

            // Windows codepage 1252

            "\xC2\x82", // U+0082⇒U+201A single low-9 quotation mark
            "\xC2\x84", // U+0084⇒U+201E double low-9 quotation mark
            "\xC2\x8B", // U+008B⇒U+2039 single left-pointing angle quotation mark
            "\xC2\x91", // U+0091⇒U+2018 left single quotation mark
            "\xC2\x92", // U+0092⇒U+2019 right single quotation mark
            "\xC2\x93", // U+0093⇒U+201C left double quotation mark
            "\xC2\x94", // U+0094⇒U+201D right double quotation mark
            "\xC2\x9B", // U+009B⇒U+203A single right-pointing angle quotation mark
        
            // Regular Unicode  
            
            "\x22"        , // U+0022 quotation mark (")
            "\x60"        , // U+0060 grave accent

            "\xC2\xB4"    , // U+00B4 acute accent
            "\xC2\xAB"    , // U+00AB left-pointing double angle quotation mark
            "\xC2\xBB"    , // U+00BB right-pointing double angle quotation mark
            "\xE2\x80\x98", // U+2018 left single quotation mark
            "\xE2\x80\x99", // U+2019 right single quotation mark
            "\xE2\x80\x9A", // U+201A single low-9 quotation mark
            "\xE2\x80\x9B", // U+201B single high-reversed-9 quotation mark
            "\xE2\x80\x9C", // U+201C left double quotation mark
            "\xE2\x80\x9D", // U+201D right double quotation mark
            "\xE2\x80\x9E", // U+201E double low-9 quotation mark
            "\xE2\x80\x9F", // U+201F double high-reversed-9 quotation mark
            "\xE2\x80\xB9", // U+2039 single left-pointing angle quotation mark
            "\xE2\x80\xBA"  // U+203A single right-pointing angle quotation mark         
        ];

        return str_replace($qSearch, "'", $s);
    }

    /**
     * Tries to guarantee inoffensive text, suitable for labels or SAS text fields
     * 
     * - trimmed
     * - stripped of HTML tags
     * - control chars (0-31, 127) converted to spaces
     * - all flavors of quotes converted to straight quote (apostrophe)
     * - converted to UTF-8 encoding
     * 
     * regexp from: https://stackoverflow.com/questions/1176904/how-to-remove-all-non-printable-characters-in-a-string
     * 
     * function: inoffensiveText
     * DEPRECATED: use Yes3Fn::sanitizeForText() instead
     * 
     * @param $s
     * @param int $maxLen
     * 
     * @return string
     */
    public function inoffensiveText( $s, $maxLen=0 ):string
    {
        if ( is_null($s) ){

            return "";
        }

        if ( !strlen($s) ){

            return "";
        }
        
        $s = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $this->straightQuoter( strip_tags($s)) ); 

        if ( $maxLen ) $s = $this->truncate($s, $maxLen);

        return mb_convert_encoding($s, 'UTF-8');     
    }

    /**
     * Allows ASCII alphanumerics
     * 
     * function: alphaNumericString
     * 
     * @param $s
     * 
     * @return string
     */
    public function alphaNumericString( $s ):string
    {
        if ( is_null($s) ){

            return "";
        }

        if ( !strlen($s) ){

            return "";
        }
        
        return preg_replace("/[^a-zA-Z0-9_ ]+/", "", $s);
    }

    /**
     * Strips control characters, allows all UTF-8
     * all HTML tags stripped
     * 
     * function: printableEscHtmlString
     * 
     * @param mixed $s
     * @param int $maxLen
     * 
     * @return string|false|string[]|null
     */
    public function printableEscHtmlString( $s, $maxLen=0)
    {
        if ( is_null($s) ){

            return "";
        }

        if ( !strlen($s) ){

            return "";
        }

        $s = preg_replace('/[\x00-\x1F\x7F]/u', '', strip_tags($s));

        if ( $maxLen ) $s = $this->truncate($s, $maxLen);

        return trim($s);
    }

    public function escapeHtml( $s )
    {
        if ( is_null($s) ){

            return "";
        }

        if ( !strlen($s) ){

            return "";
        }

        return REDCap::escapeHtml($s);
    }

    public function ellipsis( $s, $len=64 )
    {
        if ( is_null($s) ){

            return "";
        }

        if ( !strlen($s) ){

            return "";
        }

        $s = trim($s);
        if ( $len > 0 &&  strlen($s) > $len-3 ) {
            return substr($s, 0, $len-3)."...";
        }
        return $s;
    }

    public function truncate( $s, $len=64 )
    {
        if ( is_null($s) ){

            return "";
        }

        if ( !strlen($s) ){

            return "";
        }

        if ( $len > 0 && strlen($s) > $len) {

            return substr($s, 0, $len);
        }

        return $s;
    }

    /*
    * LOGGING DEBUG INFO
    * Call this function to log messages intended for debugging, for example an SQL statement.
    * The log database must exist and its name stored in the DEBUG_LOG_TABLE constant.
    * Required columns: project_id(INT), debug_message_category(VARCHAR(100)), debug_message(TEXT).
    * (best to add an autoincrement id field). Sample table-create query:
    *
            CREATE TABLE ydcclib_debug_messages
            (
                debug_id               INT AUTO_INCREMENT PRIMARY KEY,
                project_id             INT                                 NULL,
                debug_message_category VARCHAR(100)                        NULL,
                debug_message          TEXT                                NULL,
                debug_timestamp        TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL ON UPDATE CURRENT_TIMESTAMP
            );

        */

    public function logDebugMessage($project_id, $msg, $msgcat="") 
    {   
        if ( !Yes3Fn::LOG_DEBUG_MESSAGES || !$this->tableExists(Yes3Fn::DEBUG_LOG_TABLE) ) return false;

        $sql = "INSERT INTO `".Yes3Fn::DEBUG_LOG_TABLE."` (project_id, debug_message, debug_message_category) VALUES (?,?,?)";

        return $this->query($sql, [$project_id, $msg, $msgcat]);
    }
    
    /**
     * An object that functions can return
     * 
     * @param mixed $result 
     * @param string $message 
     * @param array $data 
     * @param bool $json 
     * @return string|false|array 
     */
    static function stdReturnObj( $result, $message="", $data=[], $json=true){

        $retObj = [
            'result' => $result,
            'message' => $message,
            'data' => $data
        ];

        return ( $json ) ? json_encode( $retObj ) : $retObj;
    }

    /**
     * Core function replacements, i.e. for cron jobs
     */

    public function isLongitudinal()
    {
        return (new Project($this->getProjectId()))->longitudinal;
    }

    public function getGroupnames( $unique_names=false, $group_id=null )
    {
        $Proj = new Project($this->getProjectId());
        
        // Get groups
		if ($unique_names) {
			$groups = $Proj->getUniqueGroupNames($group_id);
		} else {
			$groups = $Proj->getGroups($group_id);
		}

		// If no groups exist, return FALSE
		if (empty($groups)) return false;

		// Return groups as array
		return $groups;

    }

    public function getEventNames($unique_names=false, $append_arm_name=false, $event_id=null)
    {
        $Proj = new Project($this->getProjectId());

		// Make sure project is longitudinal, else return FALSE
		if (!$this->isLongitudinal()) return false;

		// If $event_id is not valid, then return FALSE
		if ($event_id != null && !isset($Proj->eventInfo[$event_id])) return false;
		// Get and return events
		if ($unique_names) {
			$events = $Proj->getUniqueEventNames($event_id);
		} else {
			// Validate $append_arm_name
			$append_arm_name = ($append_arm_name === true);
			// Loop through all events and collect event_id and name to return as array
			$events = array();
			foreach ($Proj->eventInfo as $this_event_id=>$attr) {
				// If event_id was specified, return only its event name
				if ($this_event_id == $event_id) {
					return ($append_arm_name ? $attr['name_ext'] : $attr['name']);
				} else {
					$events[$this_event_id] = ($append_arm_name ? $attr['name_ext'] : $attr['name']);
				}
			}
		}
		// Return events as array
		return $events;
	}

    public function UTC2Local( $utcTimestamp ){
        // Create DateTime object in UTC timezone
        $date = new DateTime($utcTimestamp, new DateTimeZone('UTC'));

        // Get the server's local timezone
        $localTimezone = new DateTimeZone(date_default_timezone_get());

        // Convert to the local timezone
        $date->setTimezone($localTimezone);

        // Display the local date and time
        return $date->format('Y-m-d H:i:s');
    }

}