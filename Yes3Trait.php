<?php

namespace Yale\Yes3Exporter2;

use REDCap;
use Project;
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

        //Yes3Fn::logDebugMessage($this->project_id, print_r($user, true), "user rights");

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
                if ( !$longitudinal || Yes3Fn::getREDCapEventsForForm($form_name) ){
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

            //Yes3Fn::logDebugMessage($this->project_id, print_r($user['data_export_instruments'], true), "user[data_export_instruments]");

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
                    if ( !$longitudinal || Yes3Fn::getREDCapEventsForForm($form_name) ){

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

        //Yes3Fn::logDebugMessage($this->project_id, print_r($formPermissions, true), "form permissions");
        
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

        $js .= "\nYES3.codebookUrl = '" . $this->getCodebookUrl() . "';";

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

            //Yes3Fn::logDebugMessage($project_id, "using REDCap::getDataTable: project_id={$project_id}, dataTable=".REDCap::getDataTable($project_id), "getDataTable");
            
            return REDCap::getDataTable($project_id);
        }

        return "redcap_data";
    }

    private function sql_limit_1( $sql )
    {

        if ( stripos($sql, "LIMIT 1") === false ) {
            return $sql . " LIMIT 1";
        } else {
            return $sql;
        }

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

    public function timeStampString()
    {
        return strftime("%y%m%d%H%M%S");
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
}