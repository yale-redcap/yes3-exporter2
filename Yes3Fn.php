<?php

namespace Yale\Yes3Exporter2;

use ExternalModules\ExternalModules;

use Exception;
use Generator;
use Project;
use Random\RandomException;
use System;

use REDCap;
class Yes3Fn {
    
    public const DEBUG_LOG_TABLE = 'ydcclib_debug_messages';
    public const LOG_DEBUG_MESSAGES = 0; // 0 for no logging, 1 for logging
    
    public const YES3_MODULE_NAME = "Yes3Exporter2";
    public const YES3_MODULE_PREFIX = "yes3_exporter2";
    
    public const ONE_MINUTE = 60;
    public const ONE_HOUR = 60*60;
    public const ONE_DAY = 24*60*60;

    public const DATA_MANAGEMENT_ROLE_NAME  = "data";
    public const DATA_COLLECTION_ROLE_NAME  = "staff";

    public const STD_RETURN_OBJECT_FAIL = "fail";
    public const STD_RETURN_OBJECT_SUCCESS = "success";

    public const FORM_COMPLETION_COMPLETE = 2;
    public const FORM_COMPLETION_INCOMPLETE = 1;
    public const FORM_COMPLETION_NA = 9;
    public const FORM_COMPLETION_UNINITIALIZED = ".";

    public const SAS_LENGTH_MAX_VARNAME = 32; // SAS variable names are limited to 32 characters.
    public const SAS_LENGTH_MAX_LABEL = 256; // SAS variable labels are limited to 256 characters.
    public const SAS_LENGTH_MAX_FMTNAME = 30; // SAS format names are limited to 32 characters, INCLUDING the trailing period and the leading dollar sign, so we impose a 30-char limit.

    public const MULTISELECT_DELIM = "___";
    public const MAX_LABEL_LEN = 1024; // max length of field label in the database

    public const VERY_LARGE_NUMBER = 9999999999; // used for min_value in calculations    

    static function helloWorld() {
        return "hello world!";
    }

    /**
     *  
     * returns a standard 36-character UUIDv4 string
     * 
     * @return string 
     * @throws RandomException 
     */
    static function UUIDv4() {

        $data = random_bytes(16);

        // Set version (4)
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);

        // Set variant (RFC 4122)
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Compacts a UUIDv4 to a 22-character base64 string (URL-safe)
     * NOTE: The output is a URL-safe base64-encoded string (not strict base64).
     *       This operation trims padding and replaces '+' and '/' with '-' and '_'.
     *       If reversal is needed, reapply the transformations: '-' → '+' and '_' → '/'.
     */
    static function compactUUIDv4() {
            
        $bytes = random_bytes(16);

        // Set version (4)
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);

        // Set variant (RFC 4122)
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /**
     * Returns a url-friendly compact UUID with marginally higher entropy (by 6 bits) than UUIDv4
     * 
     * @return string 
     * @throws RandomException 
     */
    static function compactUUID() {
        $bytes = random_bytes(16);
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /**
     * Encodes a number in base 36 (0-9, a-z)
     * 
     * @param mixed $num 
     * @return string 
     */
    public static function base36Encode($num) {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyz';
        $base = strlen($chars);
        $encoded = '';

        while ($num > 0) {
            $remainder = $num % $base;
            $num = intval($num / $base);
            $encoded = $chars[$remainder] . $encoded;
        }

        return $encoded;
    }
    
    public static function compactTimestamp() {
        $currentTimestamp = time();
        return self::base36Encode($currentTimestamp);
    }

   public static function query($sql, $parameters = [])
   {
      return ExternalModules::query($sql, $parameters);
   }

   public static function recordGenerator($sql, $parameters = [])
   {
      $resultSet = self::query($sql, $parameters);
      if ( $resultSet->num_rows > 0 ) {
         while ($row = $resultSet->fetch_assoc()) {
            yield $row;
         }
      }
   }

    public static function recordGeneratorUnbuffered( $sql, $parameters = [] )
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

   public static function fetchRecords($sql, $parameters = [])
   {
      $rows = [];
      $resultSet = self::query($sql, $parameters);
      if ( $resultSet->num_rows > 0 ) {
         while ($row = $resultSet->fetch_assoc()) {
            $rows[] = $row;
         }
      }

      return $rows;
   }

   private static function sql_limit_1( $sql )
   {
      if ( stripos($sql, "LIMIT 1") === false ) {
         return $sql . " LIMIT 1";
      } else {
         return $sql;
      }
   }

   public static function fetchRecord($sql, $parameters = [])
   {
      return self::query(self::sql_limit_1($sql), $parameters)->fetch_assoc();
   }

   public static function fetchValue($sql, $parameters = [])
   {
      return self::query(self::sql_limit_1($sql), $parameters)->fetch_row()[0];
   }

   /*
    * FORMS, FIELDS and EVENTS
    */
   
   public static function getEventIdForForm( string $form_name, $project_id=null )
   {
      if ( !$project_id ) $project_id = self::getProjectId();

      if ( self::isLongitudinal( $project_id ) ) {     
            $sql = "SELECT e.event_id
            FROM redcap_events_metadata e
               INNER JOIN redcap_events_arms a ON a.arm_id=e.arm_id
               INNER JOIN redcap_events_forms ef ON ef.form_name=? AND ef.event_id=e.event_id
            WHERE a.project_id=?
            ORDER BY e.day_offset, e.event_id
            LIMIT 1";

            return self::fetchValue($sql, [$form_name, $project_id]) ?? 0;
      }
      
      return self::getFirstEventId();
   }
   
   public static function getFirstEventId( $project_id=null )
   {
      if ( !$project_id ) $project_id = self::getProjectId();
      
      $sql = "SELECT e.event_id
      FROM redcap_events_metadata e
      INNER JOIN redcap_events_arms a ON a.arm_id=e.arm_id
      WHERE a.project_id=?
      ORDER BY e.day_offset, e.event_id
      LIMIT 1";

      return self::fetchValue($sql, [$project_id]);
   }
 
   /**
    * returns the array of form names in their correct order
    * If longitudinal, only forms on the grid will be returned
    * 
    * @return array 
    * @throws Exception 
    */
   public static function getFormNames()
   {
      $params = [ self::getProjectId() ];
      $longitudinalCode = "";

      if ( self::isLongitudinal() ){

         $longitudinalCode = "and m.form_name in(
               select ef.form_name
               from redcap_events_forms ef
               inner join redcap_events_metadata em on em.event_id=ef.event_id
               inner join redcap_events_arms ea on ea.arm_id=em.arm_id
               where ea.project_id=?
         )";
         $params[] = self::getProjectId();
      }

      $sql = "select distinct m.form_name, m.field_order
         from redcap_metadata m
         where m.project_id=?
         and m.field_name = concat(m.form_name, '_complete')
         {$longitudinalCode}
         order by m.field_order
      ";
      
      return array_column( Yes3Fn::fetchRecords($sql, $params), 'form_name' );
   }

   public static function getREDCapFormForField( string $field_name )
   {
      $project_id = self::getProjectId();

      $sql = "SELECT m.form_name
      FROM redcap_metadata m
      WHERE m.project_id=? AND m.field_name=?
      LIMIT 1";

      return self::fetchValue($sql, [$project_id, $field_name]);
   }

   public static function getFirstREDCapEventId(int $project_id=0)
   {
      if ( !$project_id ){
         $project_id = self::getProjectId();
      }

      $sql = "SELECT e.event_id
      FROM redcap_events_metadata e
         INNER JOIN redcap_events_arms a ON a.arm_id=e.arm_id
      WHERE a.project_id=?
      ORDER BY e.day_offset, e.event_id
      LIMIT 1";

      return self::fetchValue($sql, [$project_id]);
   }

   /**
    * Returns first event_id associated with form
    * Only useful if form is on a single event
    * 
    */

   public static function getREDCapEventIdForForm( string $form_name )
   {
         $project_id = self::getProjectId();

         if ( REDCap::isLongitudinal() ) {     
            $sql = "SELECT e.event_id
            FROM redcap_events_metadata e
               INNER JOIN redcap_events_arms a ON a.arm_id=e.arm_id
               INNER JOIN redcap_events_forms ef ON ef.form_name=? AND ef.event_id=e.event_id
            WHERE a.project_id=?
            ORDER BY e.day_offset, e.event_id
            LIMIT 1";

            return self::fetchValue($sql, [$form_name, $project_id]) ?? 0;
         }
         
         return self::getFirstREDCapEventId($project_id);
   }

    public static function getREDcapEventsForForm($form_name, $project_id=0)
    {
        if ( !$project_id ){
            $project_id = self::getProjectId();
        }

        if ( !self::isLongitudinal() ) return self::getFirstREDCapEventId($project_id);

        $sql = "SELECT e.event_id
        FROM redcap_events_metadata e
            INNER JOIN redcap_events_arms a ON a.arm_id=e.arm_id
            INNER JOIN redcap_events_forms ef ON ef.form_name=? AND ef.event_id=e.event_id
        WHERE a.project_id=?
        ORDER BY e.day_offset, e.event_id";

        $eventRecords = self::fetchRecords($sql, [$form_name, $project_id]);

        if ( !$eventRecords ) return [];

        return array_column($eventRecords, 'event_id');
    }

   public static function getREDCapEventIdForField( string $field_name )
   {
      return self::getREDCapEventIdForForm( self::getREDCapFormForField($field_name) );
   }

   /*
    * NEC
    */

   public static function conditionURLComponent( $s ){

      if ( substr($s, 0, 1) === DIRECTORY_SEPARATOR ) $s = substr($s, 1);
      if ( substr($s, -1) !== DIRECTORY_SEPARATOR ) $s .= DIRECTORY_SEPARATOR;

      return $s;
   }
 
   public static function getIrlName( $username ){

      return
         self::fetchValue(
            "SELECT CONCAT(user_firstname, ' ', user_lastname) AS irlname FROM redcap_user_information WHERE username=?",
            [ $username ]
         );
   }


   /*
    * typechecking functions
    */
   
    // determine if an array is associative (vs sequential)
    public static function is_assoc_array($x)
    {
        return is_array($x) && array_diff_key($x,array_keys(array_keys($x)));
    }

    // determine if array is sequential (vs associative)
    public static function is_tuple($x)
    {
        return is_array($x) && !array_diff_key($x,array_keys(array_keys($x)));
    }

    public static function is_scalar($x)
    {
        return ( !is_array($x) && !is_object($x) );
    }

    public static function is_array_of_scalars($x)
    {
        if ( !self::is_tuple($x) ) return false;

        foreach( $x as $y ){

            if ( !self::is_scalar($y) ) return false;
        }

        return true;
    }

    public function is_array_of_objects($x)
    {
        if ( !$this->is_tuple($x) ) return false;

        foreach( $x as $y ){

            if ( $this->is_scalar($y) ) return false;
        }

        return true;
    }

   /*
    * inserts LF and tab (spaces) into a JSON string
    */
   public static function prettyJSON( $json ){
      $search = [
         '{',
         '}',
         '",',
         ',"'
      ];
      $replace = [
         '{'."\n   ",
         "\n".'}'."\n",
         '",'."\n   ",
         ',' . "\n" . '   "'
      ];
      return str_replace($search, $replace, $json);
   }

    public static function normalized_string( $s, $len=4096 )
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
        return trim(substr(preg_replace("/[^a-z0-9_]+/", "", strtolower(str_replace([' ', '-', '.'], '_', $s))), 0, $len));   
    }

   /**
    * Core function replacements, i.e. for cron jobs and deprecations
   */
   public static function getModulePath() {

      // Normalize directory separators to '/'
      $normalizedDir = str_replace(DIRECTORY_SEPARATOR, '/', __DIR__);
  
      // Define the regex pattern for the root path
      $pattern = '~^(.*?/modules/'. self::YES3_MODULE_PREFIX . '_v\d+\.\d+\.\d+)/~';
  
      // Perform the regex match
      if (preg_match($pattern, $normalizedDir, $matches)) {
          // Normalize the result back to the system's directory separator
          return str_replace('/', DIRECTORY_SEPARATOR, $matches[1] . DIRECTORY_SEPARATOR);
      }
  
      // If no match is found, return null or handle as needed
      return null;
  }  


   public static function setProjectId( $project_id )
   {
      // ugh
      $_GET['pid'] = (int) $project_id;
   }

   public static function getProjectId()
   {
      if ( isset($_GET['pid']) ) {
         return (int) $_GET['pid'];
      }

      if (defined('PROJECT_ID')) {
         return (int) PROJECT_ID;
      }

      return 0;
   }

   public static function compressedArray( $array ){

      $compressed = [];

      foreach( $array as $key => $value ){

         if ( $value ) $compressed[$key] = $value;
      }

      return $compressed;
   }

    public static function isLongitudinal( $project_id=null )
    {
        if ( !$project_id ) $project_id = self::getProjectId();
        
        return (new Project((int) $project_id))->longitudinal;
    }

    /**
     * Determine if an event is a repeating event
     * 
     * A repeating event is represented in redcap_events_repeat with a NULL form_name
     * 
     * @param int|string $event_id
     * @param int|null $project_id
     * 
     * @return int 0/1
     */
    public static function isRepeatingEvent($event_id, $project_id=null )
    {
        if ( !$project_id ) $project_id = self::getProjectId();

        $event_id = intVal($event_id);

        if ( !$event_id ) return 0;

        $sql = "SELECT COUNT(*) AS k
                FROM redcap_events_repeat er
                    INNER JOIN redcap_events_metadata em ON em.event_id=er.event_id
                    INNER JOIN redcap_events_arms ea ON ea.arm_id=em.arm_id
                WHERE ea.project_id=? AND er.event_id=? AND er.form_name IS NULL";
        
        return ( self::fetchValue($sql, [$project_id, $event_id]) ) ? 1 : 0;
    }

    /**
     * Determine if a form is a repeating instrument (on any event)
     * 
     * @param string $form_name
     * @param int|null $project_id
     * 
     * @return int 0/1
     */
    public static function isRepeatingInstrument(string $form_name, $project_id=null )
    {
        if ( !$project_id ) $project_id = self::getProjectId();

        $sql = "SELECT COUNT(*) AS k
                FROM redcap_events_repeat er
                    INNER JOIN redcap_events_metadata em ON em.event_id=er.event_id
                    INNER JOIN redcap_events_arms ea ON ea.arm_id=em.arm_id
                WHERE ea.project_id=? AND er.form_name=?";
        
        return ( self::fetchValue($sql, [$project_id, $form_name]) ) ? 1 : 0;
    }

    /**
     * Determine if a form is a repeating instrument for a specific event
     * 
     * @param string $form_name
     * @param int|string $event_id
     * @param int|null $project_id
     * 
     * @return int 0/1
     */
    public static function isRepeatingInstrumentForEvent(string $form_name, $event_id, $project_id=null )
    {
        if ( !$form_name ) return 0;
        
        $event_id = intVal($event_id);

        if ( !$event_id ) return 0;

        if ( !$project_id ) $project_id = self::getProjectId();

        $sql = "SELECT COUNT(*) AS k
                FROM redcap_events_repeat er
                    INNER JOIN redcap_events_metadata em ON em.event_id=er.event_id
                    INNER JOIN redcap_events_arms ea ON ea.arm_id=em.arm_id
                WHERE ea.project_id=? AND er.form_name=? AND er.event_id=?";
        
        return ( self::fetchValue($sql, [$project_id, $form_name, $event_id]) ) ? 1 : 0;
    }

    /**
     * Determine if the project has any repeating instruments
     * 
     * @param int|null $project_id
     * 
     * @return int count of repeating instruments
     */
    public static function hasRepeatingInstruments($project_id=null)
    {
        if ( !$project_id ) $project_id = self::getProjectId();

        $sql = "SELECT COUNT(*) AS k
                FROM redcap_events_repeat er
                    INNER JOIN redcap_events_metadata em ON em.event_id=er.event_id
                    INNER JOIN redcap_events_arms ea ON ea.arm_id=em.arm_id
                WHERE ea.project_id=? AND er.form_name IS NOT NULL";
        
        return ( self::fetchValue($sql, $project_id) ) ? 1 : 0;
    }

    /**
     * Determine if the project has any repeating events
     * 
     * @param int|null $project_id
     * 
     * @return int count of repeating events
     */
    public static function hasRepeatingEvents($project_id=null)
    {
        if ( !$project_id ) $project_id = self::getProjectId();

        $sql = "SELECT COUNT(*) AS k
                FROM redcap_events_repeat er
                    INNER JOIN redcap_events_metadata em ON em.event_id=er.event_id
                    INNER JOIN redcap_events_arms ea ON ea.arm_id=em.arm_id
                WHERE ea.project_id=? AND er.form_name IS NULL";
        
        return ( self::fetchValue($sql, $project_id) ) ? 1 : 0;
    }

   public static function getGroupnames( $unique_names=false, $group_id=null )
   {
      $Proj = new Project(self::getProjectID());
      
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

   public static function getEventNames($unique_names=false, $append_arm_name=false, $event_id=null, $project_id=null)
   {
      // If $project_id is not specified, use the current project
      if ($project_id == null) {
         $project_id = self::getProjectID();
      }

      $Proj = new Project((int) $project_id);

      // Make sure project is longitudinal, else return FALSE
      if (!self::isLongitudinal( $project_id )) return false;

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

    public static function getEventIdForEventName($unique_event_name, $project_id=null)
    {
        if ( !$project_id ) $project_id = self::getProjectId();

        // Make sure project is longitudinal, else return FALSE
        if (!self::isLongitudinal( $project_id )) return false;

        // Get event id using unique event name
    
        $Proj = new Project((int) $project_id);

        return $Proj->getEventIdUsingUniqueEventName($unique_event_name);
    }

    public static function dateTimeString()
    {
        return date("Y-m-d H:i:s");
    }

    public static function saveRepeatingData( int $project_id, string $record, string $form_name, array $x, int $event_id, int $instance, bool $overwriteWithBlank = true, bool $dataLogging = false )
    {
        $data = [];

        $data[$record]['repeat_instances'][$event_id][$form_name][$instance] = $x;

        $params = [
            'project_id'=>$project_id,
            'dataFormat'=>'array',
            'data'=>$data,
            'overwriteBehavior'=> (( $overwriteWithBlank ) ? 'overwrite' : 'normal'),
            'dataLogging'=>$dataLogging,
            'commitData'=>TRUE
        ];

        $rc = REDCap::saveData( $params );

        return $rc;
    }

    public static function safeFieldName( $field_name )
    {
        
        if ( !is_string($field_name) ) return "";

        // remove any non-alphanumeric characters except underscores, and ensure the string starts with a letter
        
        return strtolower(preg_replace("/^[^a-z]|[^a-z0-9_]/", "", strtolower(trim($field_name))));
    }

    public static function truncate( $s, $len=64 )
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
     * Sanitizes a string for use in CSV or TSV files. (default is CSV)
     */
    public static function sanitizeForFiletype( 
        $input,
        $maxLen=0,
        $ascii=false,
        $fileType='csv'    
        ): string {

        if ($fileType === 'tsv') return self::sanitizeForTSV($input, $maxLen, $ascii);
        else return self::sanitizeForCSV($input, $maxLen, $ascii);
    }

    /**
     * Sanitizes a string, with defaults suitable for use in TSV files.
     *
     * @param string $input                     The input string to sanitize.
     * @param int  $maxLen                      Optional maximum length of the output string. Default is 0 (no limit).
     * @param bool $ascii                       Optional flag to enforce ASCII-only output. Default is false.
     * @param bool $noUnprintableCharacters     Optional flag to remove unprintable characters. Default is true.
     * @param bool $newLineToSpace              Optional flag to convert newlines to spaces. Default is false.
     * @param bool $tabToSpace                  Optional flag to convert tabs to spaces. Default is true.
     * @param bool $DQuoteToSQuote              Optional flag to convert double quotes to single quotes. Default is false.
     * @return string                           Sanitized string for TSV.
     */
    public static function sanitizeForTSV(
        $input,
        $maxLen=0,
        $ascii=false,                       // UTF-8 is okay
        $noUnprintableCharacters=true,      // unprintable control characters probably NOT okay
        $newLineToSpace=false,              // embedded newlines okay
        $DQuoteToSQuote=false               // embedded double quotes okay
        ): string {

        return self::sanitizeForText(
            $input,
            $maxLen,
            false, // tags okay
            $ascii, // ascii
            $noUnprintableCharacters, // noUnprintableCharacters
            $newLineToSpace, // newLineToSpace
            true, // tabToSpace
            $DQuoteToSQuote  // DQuoteToSQuote
        );
    }

    /**
     * Sanitizes a string, with defaults suitable for use in CSV files.
     *
     * @param string $input                     The input string to sanitize.
     * @param int  $maxLen                      Optional maximum length of the output string. Default is 0 (no limit).
     * @param bool $ascii                       Optional flag to enforce ASCII-only output. Default is false.
     * @param bool $noUnprintableCharacters     Optional flag to remove unprintable characters. Default is true.
     * @param bool $newLineToSpace              Optional flag to convert newlines to spaces. Default is false.
     * @param bool $tabToSpace                  Optional flag to convert tabs to spaces. Default is true.
     * @param bool $DQuoteToSQuote              Optional flag to convert double quotes to single quotes. Default is false.
     * @return string                           Sanitized string for CSV.
     */
    public static function sanitizeForCSV(
        $input,
        $maxLen=0,
        $ascii=false,                       // UTF-8 is okay  
        $noUnprintableCharacters=true,      // unprintable control characters probably NOT okay
        $newLineToSpace=false,              // embedded newlines okay
        $tabToSpace=false,                  // embedded tabs okay
        $DQuoteToSQuote=false               // embedded double quotes okay
        ): string {

        return self::sanitizeForText(
            $input,
            $maxLen,
            false, // tags okay
            $ascii, // ascii
            $noUnprintableCharacters, // noUnprintableCharacters
            $newLineToSpace, // newLineToSpace
            $tabToSpace, // tabToSpace
            $DQuoteToSQuote  // DQuoteToSQuote
        );
    }

    public static function sanitizeForExcel($input, $maxLen=0, $ascii=false): string {

        return self::sanitizeForText(
            $input,
            $maxLen,
            true, // tags NOT okay
            $ascii, // ascii
            true, // noUnprintableCharacters
            false, // newLineToSpace
            false, // tabToSpace
            false,  // DQuoteToSQuote
            true   // Excel
        );
    }

    /**
     * Sanitizes a string for use as a label (e.g., variable label, field label).
     * The output will have no tags, no tabs, no unprintable characters, no embedded double quotes.
     *
     * @param string $input The input string to sanitize.
     * @param int  $maxLen Optional maximum length of the output string. Default is 0 (no limit).
     * @param bool $ascii Optional flag to enforce ASCII-only output. Default is false.
     * @return string Sanitized label.
     */
    public static function sanitizeForLabel(
        $input,
        $maxLen=0,
        $ascii=false    
        ): string {

        return self::sanitizeForText(
            $input,
            $maxLen,
            true, // notags
            $ascii, // ascii
            true, // noUnprintableCharacters
            false, // newLineToSpace
            true, // tabToSpace
            true  // DQuoteToSQuote
        );
    }

    /**
     * Sanitizes a string for use as an object name (e.g., field name, form name, file name).
     * The output will be pure ascii, lowercase, with no tags, no newlines, no CRs, no unprintable characters, no embedded double quotes.
     * Note: This does NOT ensure the name is a valid variable name in any specific programming language, and allows embedded spaces.
     *
     * @param string $input The input string to sanitize.
     * @param int  $maxLen Optional maximum length of the output string. Default is 0 (no limit).
     * @return string Sanitized object name.
     */
    public static function sanitizeForObjectname(
        $input,
        $maxLen=0   
        ): string {

        return strtolower(self::sanitizeForText(
            $input,
            $maxLen,
            true, // notags
            true, // ascii
            true, // noUnprintableCharacters
            true, // newLineToSpace
            true, // tabToSpace
            true  // DQuoteToSQuote
        ));
    }

    /**
     * Convert UTF-8 text to ASCII.
     * - Applies a UTF-8 => ASCII symbol map (quotes, dashes, math, currency, etc.)
     * - Transliterates remaining characters to ASCII (diacritics/non-Latin scripts)
     * - Optionally enforces ASCII-only (replace or drop any leftover non-ASCII)
     *
     * NOTE: This function assumes you've already handled control chars, tabs, CR/LF.
     *
     * @param string $input
     * @param bool $enforce enforce printable ASCII-only output
     * @param string $repl replacement for non-ASCII characters
     * @return string
     * 
     * Wrought by chatGPT 5
     */
    
    public static function utf8_to_ascii(string $input, $repl='', $enforce=true): string
    {
        // basic UTF-8 => ASCII symbol map
        $baseMap = [
            // Quotation marks 
            "\u{2018}" => "'", "\u{2019}" => "'", "\u{201A}" => "'", "\u{201B}" => "'",
            "\u{201C}" => "'", "\u{201D}" => "'", "\u{201E}" => "'", "\u{201F}" => "'",
            "\u{00AB}" => '"', "\u{00BB}" => '"', "\u{02BA}" => '"', "\u{02B9}" => "'",
            "\u{275B}" => "'", "\u{275C}" => "'", "\u{275D}" => '"', "\u{275E}" => '"',

            // Dashes / hyphens
            "\u{2013}" => "-", "\u{2014}" => "-", "\u{2010}" => "-", "\u{2011}" => "-", "\u{2212}" => "-",

            // Spaces (NB: control/invisible filtering is assumed handled elsewhere;
            // these map exotic spaces to a normal space)
            "\u{00A0}" => " ", "\u{2000}" => " ", "\u{2001}" => " ", "\u{2002}" => " ", "\u{2003}" => " ",
            "\u{2004}" => " ", "\u{2005}" => " ", "\u{2006}" => " ", "\u{2007}" => " ", "\u{2008}" => " ",
            "\u{2009}" => " ", "\u{200A}" => " ", "\u{202F}" => " ", "\u{205F}" => " ", "\u{3000}" => " ",
            "\u{FEFF}" => "",

            // Math symbols
            "\u{2264}" => "<=", "\u{2265}" => ">=", "\u{00D7}" => "x", "\u{00F7}" => "/", "\u{2215}" => "/",
            "\u{2217}" => "*", "\u{2248}" => "~", "\u{2260}" => "!=", "\u{2206}" => "delta",
            "\u{2202}" => "d", "\u{2211}" => "sum",

            // Arrows
            "\u{2190}" => "<-", "\u{2192}" => "->", "\u{2191}" => "^", "\u{2193}" => "v", "\u{21D2}" => "=>",

            // Fractions
            "\u{00BC}" => "1/4", "\u{00BD}" => "1/2", "\u{00BE}" => "3/4",
            "\u{2153}" => "1/3", "\u{2154}" => "2/3", "\u{2155}" => "1/5", "\u{2156}" => "2/5",
            "\u{2157}" => "3/5", "\u{2158}" => "4/5", "\u{2159}" => "1/6", "\u{215A}" => "5/6",
            "\u{215B}" => "1/8", "\u{215C}" => "3/8", "\u{215D}" => "5/8", "\u{215E}" => "7/8",

            // Currency
            "\u{00A3}" => "GBP", "\u{00A5}" => "YEN", "\u{20AC}" => "EUR", "\u{0024}" => "$",

            // Misc
            "\u{2032}" => "'", "\u{2033}" => "'", "\u{2034}" => "'", "\u{2026}" => "...", "\u{2022}" => "*",
            "\u{00A9}" => "(c)", "\u{00AE}" => "(r)", "\u{2122}" => "TM", "\u{00B0}" => "deg",
            "\u{2020}" => "+", "\u{2021}" => "++", "\u{203D}" => "!?",
            "\u{2117}" => "(P)", "\u{2118}" => "(Q)", "\u{2119}" => "(R)",
        ];

        $extraMap = [
            // More quotes / primes / guillemets
            "\u{2039}" => "'", "\u{203A}" => "'", "\u{02BC}" => "'", "\u{301D}" => '"',
            "\u{301E}" => '"', "\u{301F}" => '"',

            // Dashes / hyphens
            "\u{2012}" => "-", "\u{2015}" => "-", "\u{00AD}" => "",

            // Invisible format chars)
            "\u{200B}" => "", "\u{200C}" => "", "\u{200D}" => "", "\u{2060}" => "",

            // Bullets / middots
            "\u{00B7}" => "*", "\u{2219}" => "*", "\u{25E6}" => "*", "\u{2981}" => "*",

            // Inverted punctuation
            "\u{00A1}" => "!", "\u{00BF}" => "?",

            // Superscripts/subscripts (ASCII-ish approximations)
            "\u{00B9}" => "^1", "\u{00B2}" => "^2", "\u{00B3}" => "^3",
            "\u{2070}" => "^0", "\u{2074}" => "^4", "\u{2075}" => "^5", "\u{2076}" => "^6",
            "\u{2077}" => "^7", "\u{2078}" => "^8", "\u{2079}" => "^9", "\u{207A}" => "^+",
            "\u{207B}" => "^-",
            "\u{2081}\u{2080}" => "_10",
            "\u{2080}" => "_0", "\u{2081}" => "_1", "\u{2082}" => "_2", "\u{2083}" => "_3",
            "\u{2084}" => "_4", "\u{2085}" => "_5", "\u{2086}" => "_6", "\u{2087}" => "_7",
            "\u{2088}" => "_8", "\u{2089}" => "_9", "\u{208A}" => "_+", "\u{208B}" => "_-",

            // More fractions
            "\u{2150}" => "1/7", "\u{2151}" => "1/9", "\u{2152}" => "1/10", "\u{215F}" => "1/",

            // Math / logic
            "\u{00B1}" => "+/-", "\u{2213}" => "-/+", "\u{221E}" => "inf", "\u{00AC}" => "NOT",
            "\u{2227}" => "AND", "\u{2228}" => "OR", "\u{2200}" => "forall", "\u{2203}" => "exists",
            "\u{2229}" => "INTERSECT", "\u{222A}" => "UNION", "\u{2286}" => "subseteq",
            "\u{2282}" => "subset", "\u{2261}" => "===", "\u{2243}" => "~=", "\u{226A}" => "<<",
            "\u{226B}" => ">>", "\u{22C5}" => "*",

            // More arrows
            "\u{21D0}" => "<=", "\u{21D4}" => "<=>", "\u{21A6}" => "->", "\u{2194}" => "<->",

            // Currency (extra)
            "\u{00A2}" => "cent", "\u{20B9}" => "INR", "\u{20BD}" => "RUB", "\u{20A9}" => "KRW",
            "\u{20B1}" => "PHP", "\u{20AA}" => "ILS", "\u{20BA}" => "TRY", "\u{20B4}" => "UAH",
            "\u{20A8}" => "Rs", "\u{20BF}" => "BTC",

            // Units / Greek
            "\u{03A9}" => "ohm", "\u{2126}" => "ohm", "\u{00B5}" => "u", "\u{03BC}" => "u",
            "\u{03C0}" => "pi", "\u{00F0}" => "d", "\u{2207}" => "del", "\u{03B1}" => "alpha",
            "\u{03B2}" => "beta", "\u{03B3}" => "gamma", "\u{03B4}" => "delta",
            "\u{03B5}" => "epsilon", "\u{03B6}" => "zeta", "\u{03B7}" => "eta", "\u{03B8}" => "theta",
            "\u{0394}" => "Delta",

            // Legal / paragraph
            "\u{00A7}" => "Section", "\u{00B6}" => "Para",

            // Misc punctuation
            "\u{2030}" => "per mille", "\u{2031}" => "per 10k", "\u{2016}" => "||",
            "\u{00A8}" => '"',

            // emojis
            "\u{1F336}" => "pepper", "\u{2615}" => "coffee",
            "\u{1F37A}" => "beer", "\u{1F37B}" => "wine",
            "\u{1F4C4}" => "page", "\u{1F4C5}" => "page with curl",
            "\u{2764}" => "heart", "\u{1F96A}" => "sandwich", "\u{1F3AF}" => "dart",
        ];

        $charMap = array_replace($baseMap, $extraMap);
        $out = strtr($input, $charMap);

        // --- 2) Transliterate to ASCII (diacritics + non-Latin scripts) ---
        // Prefer ICU Transliterator (ext/intl). Fallback to iconv.
        
        if (class_exists('Transliterator')) {
            $t = \Transliterator::create('Any-Latin; Latin-ASCII; NFD; [:Nonspacing Mark:] Remove; NFC');
            if ($t) {
                $out = $t->transliterate($out);
            }
        } else {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $out);
            if ($converted !== false) {
                $out = $converted;
            }
        }

        // --- 3) Optionally enforce ASCII-only (strip/replace any leftovers) ---
        if ($enforce) {
            if ($repl === '') {
                // Drop anything not in ASCII printable range + common whitespace already vetted
                $out = preg_replace('/[^\x20-\x7E]/', '', $out);
            } else {
                // Replace anything not in ASCII printable range + common whitespace already vetted
                $out = preg_replace('/[^\x20-\x7E]/', $repl, $out);
            }
        }

        return $out;
    }

    /**
     * Sanitizes a string for text output, removing or replacing special characters.
     * This is the one sanitizer to rule them all.
     * 
     * The following transformations are always applied:
     * - leading and trailing whitespace are trimmed
     * - valid UTF-8 is enforced
     * - embedded \r (CR) characters are removed.
     *
     * A note about newlines: export .csv and .tsv files are terminated by \r\n (CRLF). 
     * This allows for \n to be preserved in the exported cells.
     *
     * @param string $input The input string to sanitize.
     * @param int  $maxLen Optional maximum length of the output string. Default is 0 (no limit).
     * @param bool $notags If true, removes HTML tags. Default is true.
     * @param bool $ascii If true, converts non-ASCII characters to their ASCII equivalents. Default is false.
     * @param bool $noUnprintableCharacters (aka inoffensiveText) If true, removes unprintable control characters. Default is false.
     * @param bool $newLineToSpace If true, removes newline characters. Default is false. 
     *             Overriden by noUnprintableCharacters=true.
     * @param bool $tabToSpace If true, replaces tab characters with spaces. Default is TRUE (since exports are tsv by default). 
     *             Overriden by noUnprintableCharacters=true.
     * @param bool $DQuoteToSQuote If true, replaces double quotes with single quotes. Suitable for labels. Default is false.
     * @return string The sanitized string.
     */
    public static function sanitizeForText( $input, 
        $maxLen=0, 
        $notags=false, 
        $ascii=false, 
        $noUnprintableCharacters=false,
        $newLineToSpace=false,
        $tabToSpace=true,
        $DQuoteToSQuote=false,
        $excel=false // enforce Excel conventions
        ):string
    {
        if ( !is_scalar($input) ) return ""; // reject arrays and objects

        if ( is_bool($input) ) return $input ? "1" : "0"; // convert bool to [0,1] string
        
        if ( !is_string($input) ) return strval($input); // convert [int,float,null] to string

        // Okay, it's a string. Always remove leading and trailing whitespace.
        $output = trim($input);

        // always ensure correct UTF8
        $output = mb_convert_encoding($output, 'UTF-8', 'UTF-8');

        if ( $excel ) {

            // Excel: embedded \n bad, embedded \r\n good
            $output = str_replace("\n", "\r\n", $output);
            $output = str_replace("\r\r\n", "\r\n", $output); // fix double CR we may have just created
        }
        else {

            // non-excel (datasheet): prevent embedded CRLF which is the CSV/TSV row terminator
            $output = str_replace("\r\n", '\n', $output);

        } // end non-excel

        
        // Remove unprintable characters, if requested
        // This removes:
        //   1. All Unicode control characters (\p{Cc}), except:
        //      - Horizontal tab (U+0009, \x09)
        //      - Line feed (U+000A, \x0A)
        //      - Carriage return (U+000D, \x0D)
        //      - (these three are allowed because they are commonly used in text)
        //   2. All Unicode line/paragraph separators (\p{Zl}, \p{Zp})
        //   3. All invisible or zero-width space characters, including:
        //      - No-break space (U+00A0)
        //      - Zero-width space, joiners, and directional marks (U+200B–U+200F)
        //      - Line/paragraph separators and narrow no-break space (U+2028–U+202F)
        //      - Word joiner and other invisible format controls (U+2060–U+206F)
        //      - Byte Order Mark (U+FEFF)
        // This ensures that only meaningful printable characters, tabs, and line feeds remain.
        // Works safely for both ASCII and UTF-8 encoded input.
        // Regex generated by GPT5

        if ($noUnprintableCharacters) {

            $output = preg_replace(
                '/[\p{Cc}\p{Zl}\p{Zp}\x{00A0}\x{200B}-\x{200F}\x{2028}-\x{202F}\x{2060}-\x{206F}\x{FEFF}&&[^\x09\x0A\x0D]]/u',
                '',
                $output
            );
        } 

        // convert newlines to spaces, if requested
        if ( $newLineToSpace) {

            $output = str_replace("\n", ' ', $output);
            $output = str_replace("\r", '', $output);
        }

        // convert tabs to spaces, if requested
        if ( $tabToSpace ) {

            $output = str_replace("\t", ' ', $output);
        }
    
        // strip HTML tags, if requested
        if ( $notags ) {

            $output = strip_tags($output);
        }

        // convert to ASCII if requested
        if ( $ascii ) {

            $output = self::utf8_to_ascii($output); // enforce ASCII-only output
        }

        // convert double quotes to single quotes if requested (appropriate for labels)
        if ( $DQuoteToSQuote ) {
            
            $output = str_replace('"', "'", $output);
        }

        // Truncate to the specified length if maxLen is greater than 0
        if ($maxLen > 0 && strlen($output) > $maxLen) {

            $output = substr($output, 0, $maxLen);
        }

        return $output;
    }
 
    public static function sanitizeForSASVarname($varname, $varnames = []) {

        $matches = [];
        $varPrefix = '';
        $varSuffix = '';

        // Convert to lowercase
        $varname = strtolower($varname);

        // Remove any characters that are not alphanumeric or underscores
        $varname = preg_replace('/[^a-z0-9_]/', '_', $varname);

        // Ensure the variable name starts with a letter or underscore
        if (!preg_match('/^[a-z_]/', $varname)) {
            $varname = 'v' . $varname; // prepend 'v' if it doesn't start with a letter or underscore
        }

        // This regex captures the prefix and suffix separately
        // The suffix is defined as one or more underscores followed by one or more alphanumeric characters
        // e.g., "_complete", "___123", "_v1", etc.
        // The transformed variable name will include the full suffix, and the prefix will be truncated if necessary
        $varnameHasSuffix = preg_match('/^(.*?)(_+[a-z0-9]+)$/i', $varname, $matches);

        // Reject suffixes that are longer than 24 characters, because we need a buffer for unique varnames
        if ( $varnameHasSuffix && strlen($matches[2]) > 24 ) {

            $varnameHasSuffix = false;
        }

        if ($varnameHasSuffix) {

            $varPrefix = $matches[1];
            $varSuffix = $matches[2];
        }
        else {

            $varPrefix = $varname;
            $varSuffix = '';
        }

        // Truncate to the maximum length allowed for SAS variable names
        if (strlen($varname) > self::SAS_LENGTH_MAX_VARNAME) {

            if ($varnameHasSuffix) {

                // If the variable name is a special case, truncate the prefix and append the suffix
                $varPrefix = substr($varPrefix, 0, self::SAS_LENGTH_MAX_VARNAME - strlen($varSuffix));
                $varname = $varPrefix . $varSuffix;
            } else {

                // Otherwise, we just truncate the variable name
                $varname = substr($varname, 0, self::SAS_LENGTH_MAX_VARNAME);
            }
        }

        // Ensure the variable name is unique within the provided list of variable names
        // 
        $counter = 1;
        while (in_array($varname, $varnames) && $counter < 4096) { // 4096 is a reasonable limit for counter to avoid infinite loops    

            $uSegment = str_pad($counter, 3, '0', STR_PAD_LEFT); // we can reasonably assume that the counter will not exceed 999 for a given collision

            // insert the 'usegment' between the prefix and suffix, truncating the prefix if necessary

            $varname = substr($varPrefix, 0, self::SAS_LENGTH_MAX_VARNAME - strlen($uSegment) - strlen($varSuffix)) . $uSegment . $varSuffix;
            
            $counter++;
        }

        // remove any trailing underscores from $varname
        if ( preg_match('/^(.*?)(_+)$/', $varname, $matches)) {

            $varname = $matches(1);
        }

        return $varname;
    }

    public static function sanitizeForSASFmtname($fmtname) {

        // Convert to lowercase
        $fmtname = strtolower($fmtname);

        // Remove any characters that are not alphanumeric or underscores
        $fmtname = preg_replace('/[^a-z0-9_]/', '', $fmtname);

        // Ensure the format name starts with a letter or underscore
        if (!preg_match('/^[a-z_]/', $fmtname)) {
            $fmtname = 'f_' . $fmtname; // prepend 'f_' if it doesn't start with a letter or underscore
        }

        // Truncate to the maximum length allowed for SAS format names
        if (strlen($fmtname) > self::SAS_LENGTH_MAX_FMTNAME) {
            $fmtname = substr($fmtname, 0, self::SAS_LENGTH_MAX_FMTNAME);
        }

        return $fmtname;
    }

    // a version that preserves quotes, apostrophes, < and >
    public static function safeHTML( $s )
    {
        if ( is_null($s) ){

            return "";
        }

        if ( !strlen($s) ){

            return "";
        }

        $safe_html = htmlspecialchars(trim($s), ENT_NOQUOTES, 'UTF-8');

        // decode specific entities
        $safe_html = str_replace(['&lt;', '&gt;'], ['<', '>'], $safe_html);

        return $safe_html;
    }

    public static function safe_debug_output($data): string {
        return json_encode(
            $data,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_HEX_TAG     // <
            | JSON_HEX_AMP     // &
            | JSON_HEX_APOS    // '
            | JSON_HEX_QUOT    // "
            | JSON_PRETTY_PRINT // for readability
            | JSON_PARTIAL_OUTPUT_ON_ERROR // for broken encoding
        );
    }

        
    /**
     * A plug-compatible version of fputcsv that supports the eol parameter for PHP < 8.1.
     * 
     * Default eol is "\r\n" to prevent embedded NL characters from crashing text parsers (n.b. SAS)
     *
     * @param resource $handle The file handle to write to.
     * @param array $fields The fields to write as a CSV line.
     * @param string $delimiter The field delimiter (default is ',').
     * @param string $enclosure The field enclosure (default is '"').
     * @param string $escape_char The escape character (default is '\\').
     * @param string $eol The end-of-line character (default is "\r\n").
     * @return int|false Returns the number of bytes written, or false on failure.
     */
    public static function fputcsv(
        $handle, 
        array $fields, 
        string $delimiter = ',', 
        string $enclosure = '"', 
        string $escape_char = '\\',
        string $eol = "\r\n"
    ){
        if (!is_resource($handle)) {
            // If the handle is not a valid resource, throw an exception
            throw new Exception("Yes3Fn::fputcsv: Invalid file handle.");
        }

        ob_start();
        $tmp = fopen('php://output', 'w');
        fputcsv($tmp, $fields, $delimiter, $enclosure, $escape_char);
        fclose($tmp);
        $csvLine = ob_get_clean();
        $csvLine = rtrim($csvLine, "\r\n") . $eol;

        return fwrite($handle, $csvLine);
    }

    /**
     * Returns a SQL statement that selects the data for a form.
     * 
     * @param int $project_id The project ID.
     * @param string $form_name The name of the form.
     * @param string $record The record ID.
     * @param int $event_id The event ID.
     * @param int $instance The instance number (1-based).
     * @param string|null $required_field_name The name of a field that must be present in the form.
     * @param string|null $filter_values Comma-separated values to filter the results.
     * @return array|false An array containing the SQL statement and parameters, or false on failure.
     */  

   public static function selectFormDataSQL( int $project_id, string $form_name, string $record, int $event_id, int $instance=1, string $required_field_name=null, string $filter_values=null )
   {
        // this function returns a SQL statement that selects the data for a form
        // it is used by getFormData() to fetch the data for a form

        // $form_name is the name of the form
        // $record is the record id
        // $event_id is the event id
        // $instance is the instance number (1-based)
        // $required_field_name is the name of a field that must be present in the form

        if ( !$project_id ) return false;

        if ( !$event_id ) return false;

        if ( !$form_name ) return false;

        if ( !$instance ) $instance = 1;
        
        // determine the study arm from the event_id

        $sqlArm = "SELECT ea.arm_num FROM redcap_events_metadata em INNER JOIN redcap_events_arms ea ON ea.arm_id=em.arm_id WHERE em.event_id=?";
        $arm_num = self::fetchValue($sqlArm, [$event_id]);

        if ( !$arm_num ) return false;

        // assemble the list of field names

        $sqlFields = "SELECT field_name FROM redcap_metadata WHERE project_id=? AND form_name=? AND element_type NOT IN('descriptive') ORDER BY field_order";
        $fieldRecords = self::fetchRecords($sqlFields, [$project_id, $form_name]);

        if ( !$fieldRecords ) return false;

        // project data table
        $redcap_data = REDCap::getDataTable( $project_id );

        // 3 SQL fragments and a parameter list

        // if a field is required, base the query on that field
        // otherwise, use the record list table

        if  ( $required_field_name) {

            $sqlSelect = "SELECT r.project_id, r.`record`, r.`event_id`, r.`instance`";
            $sqlFrom = "\nFROM $redcap_data r";
            $sqlWhere = "\nWHERE r.`project_id`=? AND r.event_id=? AND r.field_name=?";
            $sqlParams = [$project_id, $event_id, $required_field_name];

            if ( strlen($filter_values) ) {

                $values = explode(",", $filter_values);
                $sqlWhere .= " AND r.`value` IN (" . implode(",", array_fill(0, count($values), "?")) . ")";
                $sqlParams = array_merge($sqlParams, $values);
            }
            $qryInstance = "";
        }
        else {

            $sqlSelect = "SELECT r.project_id, r.`record`, {$arm_num} AS `arm_num`, {$event_id} AS `event_id`, {$instance} AS `instance`";
            $sqlFrom = "\nFROM redcap_record_list r";
            $sqlWhere = "\nWHERE r.`project_id`=? AND r.arm=?";
            $sqlParams = [$project_id, $arm_num];
            $qryInstance = ($instance > 1) ? "= $instance" : "IS NULL";
        }

        if ( $record ) {
            $sqlWhere .= " AND r.`record`=?";
            $sqlParams[] = $record;
        }

        // extract the field_name column from fieldRecords

        $fieldNames = array_column($fieldRecords, 'field_name');

 
        $d = 0;
        foreach ( $fieldNames as $field_name ) {
            $d++;
            $alias = "d$d";

            $field_name = self::safeFieldName($field_name); // take that you evil hackers

            $sqlSelect .= "\n  ,{$alias}.`value` AS `{$field_name}`";

            if ( $required_field_name) {
                $sqlFrom .= "\nLEFT JOIN $redcap_data {$alias} ON {$alias}.project_id=r.project_id AND {$alias}.record=r.record AND {$alias}.event_id=r.event_id AND {$alias}.instance<=>r.instance AND {$alias}.field_name='{$field_name}'";
            }
            else {
                $sqlFrom .= "\nLEFT JOIN $redcap_data {$alias} ON {$alias}.project_id=r.project_id AND {$alias}.record=r.record AND {$alias}.event_id=$event_id AND {$alias}.instance $qryInstance AND {$alias}.field_name='{$field_name}'";
            }
        }

        return [
            'sql' => $sqlSelect . $sqlFrom . $sqlWhere,
            'params' => $sqlParams
        ];
    }

   public static function getFormData( int $project_id, string $form_name, string $record, int $event_id, int $instance, string $required_field_name, int $escape=0, $filter_values="")
   {
      $q = self::selectFormDataSQL($project_id, $form_name, $record, $event_id, $instance, $required_field_name, $filter_values);

      if ( !$q ) return false;

      $sql = $q['sql'];
      $params = $q['params'];

      //return $q;

      //print "<pre>" . $sql . "</pre>";
      //print "<pre>" . print_r($params, true) . "</pre>";

      $data = self::fetchRecords($sql, $params);
      //print "<pre>" . print_r($data, true) . "</pre>";

      // if requested, i.e. for browser display, escape the data. Otherwise raw data are returned
      if ( $escape ) {

        for ( $i=0; $i<count($data); $i++ ) {

            foreach ( $data[$i] as $key => $value ) {

               if ( is_string($value) ) {

                  $data[$i][$key] = self::safeHTML($value);
               }
            }
         }
      }

      return $data;
   }

   public static function toJSON(){

      return json_encode( (new \ReflectionClass(__CLASS__))->getConstants() );
   }

   public static function REDCapSaveRCSummary( $rc )
   {
      if ( !is_array($rc) ) {
         return $rc;
      }  
      if ( $rc['errors'] ) {
         return self::STD_RETURN_OBJECT_FAIL . ": " . (( is_array( $rc['errors']) ) ? implode($rc['errors']) : $rc['errors']);
      }
      return self::STD_RETURN_OBJECT_SUCCESS . ": " . $rc['item_count'] . " item(s) saved";
   }

    public static function REDCapSaveRCToStdRetObj( $rc, $json=true )
    {
        $result = self::REDCapSaveRCSummary( $rc );

        return self::stdReturnObj( 
            ( strpos($result, self::STD_RETURN_OBJECT_SUCCESS) === 0 ) ? self::STD_RETURN_OBJECT_SUCCESS : self::STD_RETURN_OBJECT_FAIL,
            $result,
            $rc,
            $json
        );
    }

    public static function failString($msg) {

        return self::STD_RETURN_OBJECT_FAIL . ": " . $msg;
    }

    public static function successString($msg) {

        return self::STD_RETURN_OBJECT_SUCCESS . ": " . $msg;
    }

    public static function stdReturnObj( $result, $message="", $data=[], $json=true){

        $retObj = [
            'result' => $result,
            'message' => $message,
            'data' => $data
        ];

        return ( $json ) ? json_encode( $retObj ) : $retObj;
    }

    public static function failObject( $msg, $data=[], $json=false ) {

        return self::stdReturnObj( self::STD_RETURN_OBJECT_FAIL, $msg, $data, $json );
    }

    public static function successObject( $msg, $data=[], $json=false ) {

        return self::stdReturnObj( self::STD_RETURN_OBJECT_SUCCESS, $msg, $data, $json );
    }

    public static function BOMsAway($str) {

        // bail if empty or null string
        if (is_null($str) || $str === '') {

            return $str;
        }

        // Remove UTF-8 BOM (Byte Order Mark) if present
        return ltrim($str, "\xEF\xBB\xBF");
    }

    /**
     * Convert a JSON file to an array
     * Handles BOM (UTF8 Byte Order Mark) if present
     * 
     * @param mixed $filename 
     * @return mixed 
     */
    public static function jsonFileToArray($filename) {

        if (!file_exists($filename) || !is_readable($filename)) {
            
            return [];
        }

        $json = file_get_contents($filename);

        $data = json_decode(self::BOMsAway($json), true);

        if (json_last_error() !== JSON_ERROR_NONE) {

            return [];
        }

        return $data;
    }

    public static function csvFileToArray($filename, $delimiter = ",") {

        if (!file_exists($filename) || !is_readable($filename)) {

            return [];
        }

        $data = [];

        if (($handle = fopen($filename, "r")) !== FALSE) {

            $colnames = fgetcsv($handle, 0, $delimiter);

            if (!empty($colnames)) {

                $colnames[0] = self::BOMsAway($colnames[0]); // handle goddam BOM in first column name

                while (($row = fgetcsv($handle, 0, $delimiter)) !== FALSE) {

                    $data_row = [];

                    for ($i = 0; $i < count($colnames); $i++) {

                        $data_row[$colnames[$i]] = $row[$i];
                    }

                    $data[]= $data_row;
                }
            }

            fclose($handle);
        }

        return $data;
    }

    public static function sanitize_utf8($str) {
        return mb_convert_encoding($str, 'UTF-8', 'UTF-8');
    }

    /**
     * legacy formula from Records::getData
     * verified as of REDCap verified as of v15.5.13
     * 
     * function: hash_record
     * 
     * @param string $record
     * @param string $project_salt
     * 
     * @return string
     */
    public static function hash_record_legacy(string $record, string $project_salt): string
    {
        global $salt; // global REDCap salt, determined at installation

        return md5($salt . $record . $project_salt);
    }

    public static function hash_record(string $record, string $project_salt): string
    {
        // use legacy method if salt2 not set
        if (!isset($GLOBALS['salt2']) || !is_string($GLOBALS['salt2']) || $GLOBALS['salt2'] === '') {
            return self::hash_record_legacy($record, $project_salt);
        }

        if (!isset($GLOBALS['password_algo']) || !is_string($GLOBALS['password_algo']) || $GLOBALS['password_algo'] === '') {
            throw new \RuntimeException ('hash_record: global "password_algo" not set');
        }

        if (!in_array($GLOBALS['password_algo'], hash_algos(), true)) {
            throw new \RuntimeException ('hash_record: unsupported hash algorithm "' . $GLOBALS['password_algo'] . '"');
        }

        $hex = hash(
            $GLOBALS['password_algo'],
            $GLOBALS['salt2'] . $GLOBALS['salt'] . $record . $project_salt,
            false
        );

        if ($hex === false) {
            throw new \RuntimeException('hash_record: hashing failed');
        }

        return substr($hex, 0, 32); // match REDCap truncation
    }
 
	/**
	 * DATE SHIFTING: Get number of days to shift for a record
     * Code imported from REDCap's Records::get_shift_days
     * verified as of v15.5.15
	 */
	public static function get_shift_days(string $idnumber, int $date_shift_max, string $project_salt): int
	{
		global $salt;

        $dec = hexdec(substr(md5($salt . $idnumber . $project_salt), 10, 8));
		// Set as integer between 0 and $date_shift_max
		$days_to_shift = round($dec / pow(10,strlen($dec)) * $date_shift_max);
		return $days_to_shift;
	}

	/**
	 * DATE SHIFTING: Shift a date by providing the number of days to shift
     * Code imported from REDCap's Records::shift_date_format
     * verified as of v15.5.15
	 */
	public static function shift_date_format(string $date, int $days_to_shift): string
	{
		if ($date == "") return $date;

        if ( strlen($date) < 10 ) return $date;

		// Explode into date/time pieces (in case a datetime field)
		list ($date, $time) = explode(' ', $date, 2);
		// Separate date into components
		$mm   = (int)substr($date, 5, 2);
		$dd   = (int)substr($date, 8, 2);
		$yyyy = (int)substr($date, 0, 4);
		// Shift the date
		$newdate = date("Y-m-d", mktime(0, 0, 0, $mm , $dd - $days_to_shift, $yyyy));
		// Re-add time component (if applicable)
		$newdate = trim("$newdate $time");
		// Return new date/time
		return $newdate;
	}

    /**
     * Get the external module ID by its directory prefix
     *
     * @param string $directoryPrefix
     * @return int|null
     */
    public static function getEMIDbyPrefix($directoryPrefix) {
        $sql = "select external_module_id from redcap_external_modules where directory_prefix=?";
        return intval(self::fetchValue($sql, [ $directoryPrefix ]) ?? 0);
    }

    public static function tableExists($table_name)
    {
        $dbname = self::fetchValue("SELECT DATABASE() AS DB");
        if ( !$dbname ) return false;
        $sql = "SELECT COUNT(*) FROM information_schema.tables"
            ." WHERE table_schema=?"
            ." AND table_name=?"
        ;
        return self::fetchValue($sql, [$dbname, $table_name]);
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

    public static function logDebugMessage($project_id, $msg, $msgcat="") 
    {   
        if ( !self::LOG_DEBUG_MESSAGES || !self::tableExists(self::DEBUG_LOG_TABLE) ) return false;

        $sql = "INSERT INTO `".self::DEBUG_LOG_TABLE."` (project_id, debug_message, debug_message_category) VALUES (?,?,?)";

        return self::query($sql, [$project_id, $msg, $msgcat]);
    }
}