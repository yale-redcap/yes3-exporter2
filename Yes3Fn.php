<?php

namespace Yale\Yes3Exporter;

use ExternalModules\ExternalModules;

use Exception;
use Generator;
use Project;

use REDCap;

use function PHPUnit\Framework\assertFalse;

class Yes3Fn {

   static function helloWorld() {
      return "hello world!";
   }


    /**
     * Compacts a UUIDv4 to a 22-character base64 string (URL-safe)
     * NOTE: The output is a URL-safe base64-encoded string (not strict base64).
     *       This operation trims padding and replaces '+' and '/' with '-' and '_'.
     *       If reversal is needed, reapply the transformations: '-' → '+' and '_' → '/'.
     */
    public static function compactUUIDv4() {

        // Generate a UUIDv4
        $uuid = bin2hex(random_bytes(16));
        
        // Convert hex UUID to binary data
        $binaryUUID = '';
        for ($i = 0; $i < strlen($uuid); $i += 2) {
            $binaryUUID .= chr(hexdec($uuid[$i] . $uuid[$i + 1]));
        }
        
        // Base64 encode the binary data, apply url-safe transform,  and remove padding
        $base64UUID = rtrim(strtr(base64_encode($binaryUUID), '+/', '-_'), '=');
        
        return $base64UUID;
    }

    /**
     * Escapes a string to ensure it is 22 characters long
     * and contains only "safe for URL" Base64 characters.
     *
     * @param string $input The string to escape
     * @return string Escaped string
     */
    public static function escapeUUIDv4String($input) {
        // Allowed Base64 URL-safe characters
        $allowedChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
        
        // Remove any characters not in the allowed set
        $escaped = preg_replace('/[^' . preg_quote($allowedChars, '/') . ']/', '', $input);
        
        // Ensure the string is exactly 22 characters
        if (strlen($escaped) < 22) {
            // Pad with 'A' (arbitrary choice) if shorter
            $escaped = str_pad($escaped, 22, 'A');
        } elseif (strlen($escaped) > 22) {
            // Truncate if longer
            $escaped = substr($escaped, 0, 22);
        }
        
        return $escaped;
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

   public static function getFirstREDCapEventId(int $project_id=null)
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
      $pattern = '~^(.*?/modules/'. Yes3K::YES3_MODULE_PREFIX . '_v\d+\.\d+\.\d+)/~';
  
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

   public static function isRepeatingInstrument(string $form_name, $project_id=null )
   {
      if ( !$project_id ) $project_id = self::getProjectId();

      $sql = "SELECT COUNT(*) AS k
               FROM redcap_events_repeat er
                  INNER JOIN redcap_events_metadata em ON em.event_id=er.event_id
                  INNER JOIN redcap_events_arms ea ON ea.arm_id=em.arm_id
               WHERE ea.project_id=? AND er.form_name=?";
      
      return self::fetchValue($sql, [$project_id, $form_name]);
   }

    public static function hasRepeatingInstruments($project_id=null)
    {
      if ( !$project_id ) $project_id = self::getProjectId();

        $sql = "SELECT COUNT(*) AS k
                FROM redcap_events_repeat er
                    INNER JOIN redcap_events_metadata em ON em.event_id=er.event_id
                    INNER JOIN redcap_events_arms ea ON ea.arm_id=em.arm_id
                WHERE ea.project_id=?";
        
        return self::fetchValue($sql, $project_id);
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

   /**
    * Sanitizes a string to allow only characters suitable for a record key.
    * Allowed characters: A-Z, a-z, 0-9, and a limited set of safe special characters.
    *
    * @param string $input The string to sanitize.
    * @return string The sanitized string.
    */
    public static function sanitizeForKey( $input ): string {

      if ( !is_string($input) ) return "";

      // Define a pattern for allowed characters: alphanumeric and safe special characters
      $allowedPattern = '/[^a-zA-Z0-9!@#$%^&*()\-_=+[\]{}|;:\'",.?\/]/';

      // Remove characters that do not match the allowed set
      return preg_replace($allowedPattern, '', $input);
   }

   /**
    * Sanitizes a string for suitability as a name
    *
    * @param string $input The string to sanitize.
    * @return string The sanitized string.
    */
    public static function sanitizeForName($input): string {

      if ( !is_string($input) ) return "";

      // Define a pattern for allowed characters: alphanumeric and safe special characters
      $allowedPattern = '/[^a-zA-Z0-9\-_. ]/';

      // Remove characters that do not match the allowed set
      return preg_replace($allowedPattern, '', $input);
   }

   /**
    * Sanitizes a string for suitability as a REDCap API token
    *
    * @param string $input The string to sanitize.
    * @return string The sanitized string.
    */
    public static function sanitizeForApiToken($input): string {

      if ( !is_string($input) ) return "";

      // Define a pattern for allowed characters: alphanumeric and safe special characters
      $allowedPattern = '/[^A-Z0-9]/';

      // Remove characters that do not match the allowed set
      return preg_replace($allowedPattern, '', $input);
   }

   public static function keyTransform($string, $ucase=false)
   {
       if ( $string === null ) {
           
           return "";
       }
       
       if ( !is_string($string) ) {
           
           $string = strval($string);
       }

       if ( $ucase ) $string = strtoupper($string);
       else $string = strtolower($string);
       
       // trimmed, non alpha-numeric characters converted to underscores
       return preg_replace("/[^a-zA-Z0-9]/", "_", $string);
   }

   // a 'key parm' is a string conforming to the rules for a REDCap field name, but uppercase
   public static function sanitizeForKeyParm( $input )
   {

        return self::keyTransform($input);
        //return strtoupper( self::sanitizeForName($input) );
   }

   /**
    * Sanitizes a string to allow any UTF-8 printable character, including '<' and '>',
    * while making it safe from code injection.
    *
    * @param string $input The string to sanitize.
    * @return string The sanitized string.
    */
   public static function sanitizeUtf8Printable($input): string {

      if ( !is_string($input) ) return "";

      // Convert the string to UTF-8 encoding
      $input = mb_convert_encoding($input, 'UTF-8', 'UTF-8');
      
      // Strip or encode control characters (ASCII < 32 except for newline, carriage return, and tab)
      $input = preg_replace('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/u', '', $input);

      // Escape characters that could lead to code injection in HTML or SQL
      // Use htmlspecialchars to prevent HTML injection
      $safeString = htmlspecialchars($input, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

      // Return the safe, sanitized string
      return $safeString;
   }

   public static function sanitizeForInteger( $input ):string
   {
      if ( is_string($input) && is_numeric($input) ) {

         return $input;
      }

      if ( is_string($input) && $input === "0" ) {

         return "0";
      }

      return "";
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

    public static function sanitizeForFiletype( 
        $input,
        $maxLen=0,
        $ascii=false,
        $fileType='csv'    
        ): string {

        if ($fileType === 'tsv') return self::sanitizeForTSV($input, $maxLen, $ascii);
        else return self::sanitizeForCSV($input, $maxLen, $ascii);
    }

    public static function sanitizeForTSV(
        $input,
        $maxLen=0,
        $ascii=false    
        ): string {

        return self::sanitizeForText(
            $input,
            $maxLen,
            false, // tags okay
            $ascii, // ascii
            true, // noUnprintableCharacters
            false, // noNewlines
            true, // noTabs
            false  // noDQuotes
        );
    }

    public static function sanitizeForCSV(
        $input,
        $maxLen=0,
        $ascii=false    
        ): string {

        return self::sanitizeForText(
            $input,
            $maxLen,
            false, // tags okay
            $ascii, // ascii
            true, // noUnprintableCharacters
            false, // noNewlines
            true, // noTabs
            false  // noDQuotes
        );
    }

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
            false, // noNewlines
            true, // noTabs
            true  // noDQuotes
        );
    }

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
            true, // noNewlines
            true, // noTabs
            true  // noDQuotes
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
     * @param bool $enforce enforce ASCII-only output
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
                // Drop anything not in ASCII printable range + common whitespace you already vetted
                $out = preg_replace('/[^\x20-\x7E]/', '', $out);
            } else {
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
     * @param bool $noUnprintableCharacters (aka inoffensiveText) If true, removes unprintable characters except for newline, carriage return, and tab. Default is false.
     * @param bool $noNewlines If true, removes newline characters. Default is false.
     * @param bool $notabs If true, replaces tab characters with spaces. Default is TRUE (since exports are tsv by default).
     * @param bool $nodquotes If true, replaces double quotes with single quotes. Suitable for labels. Default is false.
     * @return string The sanitized string.
     */
    public static function sanitizeForText( $input, 
        $maxLen=0, 
        $notags=false, 
        $ascii=false, 
        $noUnprintableCharacters=false,
        $noNewlines=false,
        $noTabs=true,
        $noDQuotes=false
        ):string
    {
        if ( !is_string($input) ) return "";

        // always remove leading and trailing whitespace
        $input = trim($input);

        // always ensure correct UTF8
        $input = mb_convert_encoding($input, 'UTF-8', 'UTF-8');

        // always remove CR, to prevent embedded CRLF which is the CSV/TSV row terminator
        $input = str_replace("\r", '', $input);

        // remove control characters except for newline and tab
        //if ( $noUnprintableCharacters ) {

        //    $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $input);
        //}

        // Clean up input by removing:
        //   1. All Unicode control characters (\p{Cc}), except:
        //      - Horizontal tab (U+0009, \x09)
        //      - Line feed (U+000A, \x0A)
        //   2. All Unicode line/paragraph separators (\p{Zl}, \p{Zp})
        //   3. All invisible or zero-width space characters, including:
        //      - No-break space (U+00A0)
        //      - Zero-width space, joiners, and directional marks (U+200B–U+200F)
        //      - Line/paragraph separators and narrow no-break space (U+2028–U+202F)
        //      - Word joiner and other invisible format controls (U+2060–U+206F)
        //      - Byte Order Mark (U+FEFF)
        // This ensures that only meaningful printable characters, tabs, and line feeds remain.
        // Works safely for both ASCII and UTF-8 encoded input.
        // ref: GPT 5
        if ($noUnprintableCharacters) {

            $input = preg_replace(
                '/[\p{Cc}\p{Zl}\p{Zp}\x{00A0}\x{200B}-\x{200F}\x{2028}-\x{202F}\x{2060}-\x{206F}\x{FEFF}&&[^\x09\x0A]]/u',
                '',
                $input
            );
        }

        // replace newline and carriage return with a single space
        if ( $noNewlines) {

            $input = str_replace("\n", ' ', $input);
        }
        
        if ( $notags ) {

            // remove all HTML tags
            $input = strip_tags($input);
        }

        if ( $noTabs ) {

            // replace tabs with a single space
            $input = str_replace("\t", ' ', $input);
        }

        // convert to ASCII if requested
        if ( $ascii ) {

            $input = self::utf8_to_ascii($input); // enforce ASCII-only output
        }

        // convert double quotes to single quotes if requested (appropriate for labels)
        if ( $noDQuotes ) {
            
            $input = str_replace('"', "'", $input);
        }

        // Truncate to the specified length if maxLen is greater than 0
        if ($maxLen > 0 && strlen($input) > $maxLen) {

            $input = substr($input, 0, $maxLen);
        }

        return $input;
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
        if (strlen($varname) > Yes3K::SAS_LENGTH_MAX_VARNAME) {

            if ($varnameHasSuffix) {

                // If the variable name is a special case, truncate the prefix and append the suffix
                $varPrefix = substr($varPrefix, 0, Yes3K::SAS_LENGTH_MAX_VARNAME - strlen($varSuffix));
                $varname = $varPrefix . $varSuffix;
            } else {

                // Otherwise, we just truncate the variable name
                $varname = substr($varname, 0, Yes3K::SAS_LENGTH_MAX_VARNAME);
            }
        }

        // Ensure the variable name is unique within the provided list of variable names
        // 
        $counter = 1;
        while (in_array($varname, $varnames) && $counter < 4096) { // 4096 is a reasonable limit for counter to avoid infinite loops    

            $uSegment = str_pad($counter, 3, '0', STR_PAD_LEFT); // we can reasonably assume that the counter will not exceed 999 for a given collision

            // insert the 'usegment' between the prefix and suffix, truncating the prefix if necessary

            $varname = substr($varPrefix, 0, Yes3K::SAS_LENGTH_MAX_VARNAME - strlen($uSegment) - strlen($varSuffix)) . $uSegment . $varSuffix;
            
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
        if (strlen($fmtname) > Yes3K::SAS_LENGTH_MAX_FMTNAME) {
            $fmtname = substr($fmtname, 0, Yes3K::SAS_LENGTH_MAX_FMTNAME);
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
         return Yes3K::STD_RETURN_OBJECT_FAIL . ": " . (( is_array( $rc['errors']) ) ? implode($rc['errors']) : $rc['errors']);
      }
      return Yes3K::STD_RETURN_OBJECT_SUCCESS . ": " . $rc['item_count'] . " item(s) saved";
   }

    public static function REDCapSaveRCToStdRetObj( $rc, $json=true )
    {
        $result = self::REDCapSaveRCSummary( $rc );

        return self::stdReturnObj( 
            ( strpos($result, Yes3K::STD_RETURN_OBJECT_SUCCESS) === 0 ) ? Yes3K::STD_RETURN_OBJECT_SUCCESS : Yes3K::STD_RETURN_OBJECT_FAIL,
            $result,
            $rc,
            $json
        );
    }

    public static function failString($msg) {

        return Yes3K::STD_RETURN_OBJECT_FAIL . ": " . $msg;
    }

    public static function successString($msg) {

        return Yes3K::STD_RETURN_OBJECT_SUCCESS . ": " . $msg;
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

        return self::stdReturnObj( Yes3K::STD_RETURN_OBJECT_FAIL, $msg, $data, $json );
    }

    public static function successObject( $msg, $data=[], $json=false ) {

        return self::stdReturnObj( Yes3K::STD_RETURN_OBJECT_SUCCESS, $msg, $data, $json );
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
     * formula from Records::getData
     * verified as of REDCap v14
     * 
     * function: hash_record
     * 
     * @param string $record
     * @param string $project_salt
     * 
     * @return string
     */
    public static function hash_record(string $record, string $project_salt): string
    {
        global $salt; // global REDCap salt, determined at installation

        return md5($salt . $record . $project_salt);
    }
}