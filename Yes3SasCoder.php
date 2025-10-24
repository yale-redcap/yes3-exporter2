<?php

namespace Yale\Yes3Exporter2;
/*
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
*/

use ExternalModules\ExternalModules;

use Exception;

use Project;

use REDCap;

use Yale\Yes3Exporter2\Yes3Fn;

/**
 * Yes3SasCoder class
 *
 * This class is responsible for generating SAS code based on the data dictionary and info files.
 * It reads the data dictionary and info files, processes them, and generates SAS code for input.
 */

class Yes3SasCoder {

    public $version = "0.0.1";

    public $sysmsg = '';   

    public $workDir = '';
    public $ddFilename = '';
    public $infoFilename = '';
    public $dd_rowcount = 0;
    public $dd_colcount = 0;
    public $dd = [];
    public $formats = []; // formats generated from the data dictionary
    public $valuesets = []; // array to hold unique valuesets
    public $info = [];

    public $project_id = 0;

    public $libref = 'YES3DM'; // default libref for SAS datasets
    public $libref_path = ''; // path to the SAS datasets
    public $ascii = false; // whether to generate ASCII labels and formats
    public $fileref_path = ''; // path to the input CSV file
    public $filename_base = ''; // base name for the SAS code files
    public $dataset_name = ''; // name of the dataset to be created
    public $destination = ''; // destination for the generated SAS code files
    public $export_target_folder = ''; // target folder for the export

    public $delimiter = "\t"; // default delimiter for all exports
    public $lrecl = 32767; // default logical record length for SAS input files

    function __construct() {}

    public function initialize( $infoFilename, $ddFilename, $libref, $libref_path, $dsname, $filename_base, $ascii=false, $delimiter = "\t", $lrecl=32767 ) {


        if (!file_exists($infoFilename)) {

            return( Yes3Fn::failObject("The info file is missing.") );
        }

        if (!file_exists($ddFilename)) {

            return( Yes3Fn::failObject("The data dictionary file is missing.") );
        }

        $this->ddFilename = $ddFilename;
        $this->infoFilename = $infoFilename;
        $this->dd_rowcount = 0;
        $this->dd = [];
        $this->info = [];
        $this->sysmsg = "The data dictionary and info files have been found.";

        $this->libref = $libref;
        $this->libref_path = $libref_path;
        $this->ascii = $ascii;
        $this->filename_base = Yes3Fn::normalized_string($filename_base);

        $this->delimiter = $delimiter;
        $this->lrecl = (int) $lrecl;

        // Note that the data dictionary is always an excel-friendly CSV file delinmited with commas
        // (the input data file can be either comma- or tab-delimited)
        $this->dd = Yes3Fn::csvFileToArray( $ddFilename, "," );

        if (!is_array($this->dd) || count($this->dd) == 0) {

            return( Yes3Fn::failObject("The data dictionary file is empty or invalid.") );
        }

        $this->info = Yes3Fn::jsonFileToArray($infoFilename);

        if (!is_array($this->info) || count($this->info) == 0) {

            return( Yes3Fn::failObject("The info file is empty or invalid.") );
        }

        // Get the project ID from the info file
        if (isset($this->info['project_id']) && is_numeric($this->info['project_id'])) {

            $this->project_id = (int)$this->info['project_id'];
        } else {

            return( Yes3Fn::failObject("The project ID is missing or invalid in the info file.") );
        }

        // establish the project context
        Yes3Fn::setProjectId($this->project_id);

        $this->dd_rowcount = count($this->dd);
        $this->dd_colcount = count($this->dd[0]);

        $this->dataset_name = $dsname;

        $this->addSASVarnamesToDD(); // determines compliant and unique SAS variable names, stored as 'sas_var_name' in the data dictionary

        // build the unique valuesets from the data dictionary
        $this->addSASFormatsToDD();

        $this->sysmsg = "The data dictionary and info files have been successfully loaded.";

        return( Yes3Fn::successObject(
            "The data dictionary has {$this->dd_rowcount} rows and {$this->dd_colcount} columns.",
            [
                'dd' => $this->dd,
                'info' => $this->info,
                'ddFilename' => $this->ddFilename,
                'infoFilename' => $this->infoFilename
            ]
        ));
    }

    public function getProperties() {

        return [
            'ddFilename' => $this->ddFilename,
            'infoFilename' => $this->infoFilename,
            'dd_rowcount' => $this->dd_rowcount,
            'dd_colcount' => $this->dd_colcount,
            'dd' => $this->dd,
            'info' => $this->info,
            'sysmsg' => $this->sysmsg,
            'project_id' => $this->project_id
        ];
    }



    public function summaryComment() {

        // Generate a summary comment for the SAS code
        $summary = "\n/*\n";
        $summary .= "   =====================================\n";
        $summary .= "   YES3 Datamart SAS Coder\n";
        $summary .= "   version ". $this->version . "\n";
        $summary .= "   Code generated on " . date('Y-m-d H:i:s') . "\n";
        $summary .= "   =====================================\n";
        $summary .= "   Project ID: " . $this->project_id . "\n";
        $summary .= "   Project Name: ". $this->info['project_title'] . "\n";
        $summary .= "   SAS dataset name: ". $this->dataset_name . "\n";
        $summary .= "   Export UUID: ". $this->info['export_properties']['export_uuid'] . "\n";
        $summary .= "   Export Name: ". $this->info['export_properties']['export_name'] . "\n";
        $summary .= "   Export Timestamp: ". $this->info['timestamp'] . "\n";
        $summary .= "   Data Dictionary Field Count: " . $this->dd_rowcount . "\n";
        $summary .= "   Copyright (c) 2025 by CRI Web Tools LLC\n";
        $summary .= "*/\n";

        return $summary;
    }

    public function genInputCode(
        $infile="full-pathspec-of-input-csv-file"
        ) {

        // This function should generate SAS code based on the data dictionary and info
        // For now, we will just return a placeholder string
        // You can implement the actual logic as needed
        // bollocks to that

        $timestamp = date('Y-m-d H:i:s');

        $header = $this->summaryComment();

        $header .= "\nTITLE1 '{$this->info['export_properties']['export_name']} input program';\n";

        $header .= "\nOPTIONS FORMCHAR = '|----|+|---+=|-/\<>*';";
        $header .= "\nOPTIONS PS=55 LS=72;\n";

        $header .= "\nLIBNAME {$this->libref} '{$this->libref_path}';\n";

        $datastep = "\nDATA {$this->libref}.{$this->dataset_name} (LABEL='Generated from REDCap project #{$this->project_id} by YES3 Datamart SASCoder on {$timestamp}');\n";
            
        $infile = Yes3Fn::sanitizeForObjectname($infile);

        if ( $this->delimiter == "\t" ) {

            $datastep .= "\n   INFILE '{$infile}' DELIMITER='09'x MISSOVER DSD LRECL={$this->lrecl} TERMSTR=CRLF FIRSTOBS=2;\n"; // use hex for tab
        }
        else {

            $datastep .= "\n   INFILE '{$infile}' DELIMITER=',' MISSOVER DSD LRECL={$this->lrecl} TERMSTR=CRLF FIRSTOBS=2;\n"; 
        }

        $attrib = "\n   ATTRIB\n";

        $record_var_name = $this->dd[0]['var_name'];

        $input = "\n   INPUT\n      ";

        $varnum = 0;

        $var_names = [];

        foreach ($this->dd as $row) {

            $varnum++;
            
            $var_name = $row['sas_var_name'];

            $var_names[] = $var_name; // add to the list of variable names

            $sas_format_name = $row['sas_format_name'] ?? '';

            $redcap_field_name = $row['redcap_field_name'] ?? '';

            $label = Yes3Fn::sanitizeForLabel(
                $row['var_label'] ?? "no label for $var_name", 
                Yes3Fn::SAS_LENGTH_MAX_LABEL,
                $this->ascii, // whether to generate ASCII labels
            );
            $dd_var_type = $row['var_type'];
            $dd_max_len = (int) $row['max_length']; 

            if ( $var_name == $record_var_name ) {

                if ( !$dd_max_len ) $dd_max_len = 16; // default length for record_id
            }
            elseif ( $var_name == "redcap_event_name" || $var_name == "redcap_data_access_group" ) {

                $dd_max_len = 64; // special case for redcap metadata
            }
            else {

                if ( $dd_max_len < 1 ) { 
                    $dd_max_len = 1; 
                } 
            }

            $length = "8"; // default length for all variables
            $informat = "";
            $format = "";

            $sas_var_len = 0;
            $sas_var_type = "NUM"; // default to numeric

            if ( $row['valueset'] && $row['valueset'] != '' && $dd_var_type != 'CHECKBOX' ) {

                // if the variable has a valueset, we will use that to determine the type
                $valueset = json_decode($row['valueset'], true);

                if ( is_array($valueset) && count($valueset) > 0 ) {

                    $this->setSasVarTypeForNominals($valueset, $sas_var_type, $sas_var_len);

                    if ( $sas_var_type == "CHAR" ) {
                        // if the variable is character, set the length
                        $dd_var_type = "TEXT"; // treat as text nominal
                        $dd_max_len = $sas_var_len; // use the length from the valueset
                    }
                    else {
                        $dd_var_type = "INTEGER";
                        $length = "4"; // hopefully this is enough for all nominals
                    }
                }
            }

            if ( $dd_var_type == 'TEXT' || $dd_var_type == 'NOMINAL' || $dd_var_type == 'CHECKBOX' ) {
                $length ="\${$dd_max_len}";
                $informat = "{$length}.";
            }
            elseif ( $dd_var_type == 'DATE' ) {
                $length = "8";
                $format = "MMDDYY10.";
                $informat = "ANYDTDTE.";
            }
            elseif ( $dd_var_type == 'TIME' ) {
                $timelen = $row['max_length'] ?? "8"; // default to 8 if not specified
                $length = "8";
                $format = "TIME{$timelen}.";
                $informat = "TIME{$timelen}.";
            }
            elseif ( $dd_var_type == 'DATETIME' ) {
                $length = "8";
                $format = "DATETIME19.";
                $informat = "ANYDTDTM.";
            }

            $attrib .= "      " . $var_name . "\n";
            $attrib .= "          LENGTH = {$length}\n";
            $attrib .= "          LABEL = \"{$label}\"\n";

            if ($informat) {
                $attrib .= "          INFORMAT = {$informat}\n";
            }
            
            if ($format) {
                // if no format name is specified, use the format determined by the variable type
                $attrib .= "          FORMAT = {$format}\n";
            }

            // add the REDCap field name as a comment
            if ($redcap_field_name && $redcap_field_name != $var_name) {

                $redcap_field_name = Yes3Fn::sanitizeForObjectname($redcap_field_name, 0, true);
                $attrib .= "          /* source REDCap field = [{$redcap_field_name}] */\n";
            }

            if ($sas_format_name) {
            //    $attrib .= "          /* FORMAT = {$sas_format_name}. */\n";
            }

            if ( $varnum % 4 == 0 ) {
                $input .= "\n      ";
            }

            $input .= "{$var_name}";

            //if ( $informat ) {
            //    $input .= " {$informat}";
            //}

            $input .= " ";

        }

        $attrib .= "   ;\n";

        $input .= "\n   ;\n";

        return $header . $datastep . $attrib . $input . "\n\nRUN;\n\n";
    }

    /**
     * Adds SAS variable names to the data dictionary.
     * This method processes the data dictionary and generates compliant SAS variable names
     * for each variable, ensuring uniqueness and compliance with SAS naming conventions.
     */
    private function addSASVarnamesToDD() {

        $var_names = []; // array to hold the variable names

        $newDD = []; /* array to hold the new data dictionary with sanitized variable names */  

        foreach ($this->dd as &$row) {

            $sas_var_name = Yes3Fn::sanitizeForSASVarname($row['var_name'], $var_names);
            $var_names[] = $sas_var_name; // add to the list of variable names
            $row['sas_var_name'] = $sas_var_name; // update the row with the sanitized variable name
            $newDD[] = $row; // add the row with the sanitized variable name to the new data dictionary
        }

        $this->dd = $newDD; // update the data dictionary with sanitized variable names
    }

    /**
     * Adds SAS formats to the data dictionary based on unique valuesets.
     * This method processes the data dictionary and assigns format names to variables
     * that have associated valuesets.
     */
    private function addSASFormatsToDD() {

        $this->valuesets = []; // array to hold unique valuesets

        $newDD = []; // array to hold the new data dictionary with valueset names

        foreach ($this->dd as $row) {

            $hasValueset = isset($row['valueset']) && is_string($row['valueset']) && trim($row['valueset']) !== '' && $row['var_type'] != 'CHECKBOX';
            
            $fmt_name = "";

            if ( $hasValueset ) {


                $var_name = $row['sas_var_name'];
                $valueset = json_decode($row['valueset'], true);

                $hasValueset = is_array($valueset) && count($valueset) > 0;
            }

            for($v=0; $v<count($this->valuesets); $v++) {

                if ( $this->valuesets[$v]['valuesetJSON'] == $row['valueset'] ) {

                    // if we found an existing valueset, use its format name
                    $fmt_name = $this->valuesets[$v]['fmt_name']; // use existing format name if valueset matches
                    $this->valuesets[$v]['var_names'][] = $var_name; // add the variable name to the existing format
                    break;
                }
            }

            if ( $hasValueset && !$fmt_name ) {

                $sas_var_type = $this->setSasVarTypeForNominals($valueset);  // NUM or CHAR

                $fmt_name = $this->formatName( count($this->valuesets), $sas_var_type );

                $valuesetJSON = $row['valueset'];

                $this->valuesets[] = [
                    'var_names' => [$var_name],
                    'fmt_name' => $fmt_name,
                    'valuesetJSON' => $valuesetJSON,
                    'sas_var_type' => $sas_var_type
                ];
            }

            // add format name to the dd
            $row['sas_format_name'] = $fmt_name;
            $newDD[] = $row; // add the row with the format name to the new data dictionary
        }

        $this->dd = $newDD; // update the data dictionary with format names
    }

    /**
     * Generates SAS code to create a permanent format library.
     *
     * @return string The generated SAS code.
     */
    public function genFormatsCreateCode() {

        $timestamp = date('Y-m-d H:i:s');

        $header = $this->summaryComment();

        $header .= "\nTITLE1 '{$this->info['export_properties']['export_name']} format library creation program';\n";

        $header .= "\nOPTIONS FORMCHAR = '|----|+|---+=|-/\<>*';";
        $header .= "\nOPTIONS PS=55 LS=72;\n";
        $header .= "\nOPTIONS FMTSEARCH=({$this->libref}, work);\n";

        $header .= "\nLIBNAME {$this->libref} '{$this->libref_path}';\n";

        if ( count($this->valuesets) == 0 ) {

            return $header . "\n* Note: No valuesets found in the data dictionary. No formats will be generated.;\n\n";
        }

        $procFormat = "\nPROC FORMAT LIBRARY={$this->libref};  ** generates a PERMANENT format library! **;\n";
        $procFormat .= "\n   /* Formats generated from REDCap project #{$this->project_id} by YES3 Datamart SASCoder on {$timestamp} */\n";

        foreach ($this->valuesets as $vs) {

            $fmt_name = $vs['fmt_name'];
            $var_names = $vs['var_names'];
            $valuesetJSON = $vs['valuesetJSON'];

            $valuesetTuples = json_decode($valuesetJSON, true);

            if ( !is_array($vs) || count($vs) == 0 ) {

                continue; // skip if valueset is not an array or is empty
            }

            $sas_var_type = $this->setSasVarTypeForNominals($valuesetTuples);

            $procFormat .= "\n   VALUE {$fmt_name}\n";

            //$procFormat .= print_r($valueset, true) . "\n";

            foreach ($valuesetTuples as $vsTuple) {

                // skip if value or label is not set
                if (!isset($vsTuple['value']) || !isset($vsTuple['label'])) {
                    continue;
                }

                // if either prop is empty
                if ( $vsTuple['value'] === '' || $vsTuple['label'] === '' ) {

                    continue; // skip empty values
                }

                $value = $vsTuple['value'] ?? '';

                $label = Yes3Fn::sanitizeForText(
                    $vsTuple['label'] ?? '', 
                    Yes3Fn::SAS_LENGTH_MAX_LABEL,
                    true, // no HTML tags
                    $this->ascii, // whether to generate ASCII labels
                    true , // no unprintable characters
                    false, // new lines okay
                    true, // convert tabs to spaces
                    true // convert double quotes to single quotes
                );

                if ( $sas_var_type == "NUM" ) {
                    // numeric value
                    $procFormat .= "      {$value} = \"{$label}\"\n";
                } else {
                    // text value
                    $procFormat .= "      \"{$value}\" = \"{$label}\"\n";
                }
            }

            // add the variable names that use this format
            foreach ($var_names as $var_name) {
                $procFormat .= "      /* Used by {$var_name} */\n";
            }

            $procFormat .= "   ;\n\n";
        }
  
        $run = "\nRUN;\n";

        $quit = "\nQUIT;\n";

        return $header . $procFormat . $run . $quit;
    }

    /**
     * Generates SAS code to permanently assign formats to the dataset.
     *
     * @return string The generated SAS code.
     */
    public function genFormatsAssignCode() {

        $timestamp = date('Y-m-d H:i:s');

        $header = $this->summaryComment();

        $header .= "\nTITLE1 '{$this->info['export_properties']['export_name']} format assignments program';\n";

        $header .= "\nOPTIONS FORMCHAR = '|----|+|---+=|-/\<>*';";
        $header .= "\nOPTIONS PS=55 LS=72;\n";
        $header .= "\nOPTIONS FMTSEARCH=({$this->libref}, work);\n";

        $header .= "\nLIBNAME {$this->libref} '{$this->libref_path}';\n";

        if ( count($this->valuesets) == 0 ) {

            return $header . "\n* Note: No valuesets found in the data dictionary. No formats will be assigned.;\n\n";
        }
        
        // use PROC DATASETS to add formats to the dataset
        $procDatasets = "\nPROC DATASETS LIBRARY={$this->libref} nolist;\n";

        $procDatasets .= "\n   MODIFY {$this->dataset_name};\n";
        $procDatasets .= "\n   FORMAT\n";

        foreach ($this->valuesets as $vs) {

            $fmt_name = $vs['fmt_name'];
            $var_names = $vs['var_names'];

            foreach ($var_names as $var_name) {

                // right-pad var_name to 40 characters
                $procDatasets .= "      " . str_pad($var_name, 40, ' ') . $fmt_name . ".\n";
            }
        }

        $procDatasets .= "   ;\n";

        $run = "\nRUN;\n";

        $quit = "\nQUIT;\n";

        return $header . $procDatasets . $run . $quit;
    }

    private function formatName( $index, $sas_var_type ) {

        // Generate a format name based on the index
        $suffix = "_" . str_pad($index, 4, '0', STR_PAD_LEFT) ."f";

        $len = strlen($this->dataset_name);

        if ( $len + strlen($suffix) > Yes3Fn::SAS_LENGTH_MAX_FMTNAME ) {

            // if the format name is too long, truncate the dataset name
            $fmtname_base = substr($this->dataset_name, 0, Yes3Fn::SAS_LENGTH_MAX_FMTNAME - strlen($suffix));
        } else {
            $fmtname_base = $this->dataset_name;
        }

        if ( $sas_var_type == "CHAR" ) {
            $fmtname_base = '$' . $fmtname_base; // prefix with $ for character formats
        }

        return $fmtname_base . $suffix;
    }

    private function setSasVarTypeForNominals( $vs, &$sas_var_type=NULL, &$sas_var_length=NULL ) {

        $sas_var_type = "NUM"; // default to numeric
        $sas_char_length = 0; // default length for numeric variables

        for ( $v=0; $v<count($vs); $v++) {

            $value = $vs[$v]['value'] ?? '';

            $vlen = strlen(strval($value));

            if ( $vlen > $sas_char_length ) {
                // if any value is longer than the current length, update the length
                $sas_char_length = $vlen;
            }

            if ( !ctype_digit($value) ) {
                $sas_var_type = "CHAR"; // if any value is not numeric, we will use character format
            }
        }

        if ( $sas_var_type == "CHAR" ) {
            // if the variable is character, set the length
            $sas_var_length = $sas_char_length;
        } else {
            // if the variable is numeric, set the length to 8
            $sas_var_length = 4;
        }

        return $sas_var_type;
    }
}