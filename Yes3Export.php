<?php

namespace Yale\Yes3Exporter2;

use Yale\Yes3Exporter2\Yes3Fn;

class Yes3Export {

    const VARTYPE_TEXT = 'TEXT';
    const HASHED_VALUE_LENGTH = 32; // SHA-256 hash length

    public $export_name = "";
    public $export_label = "";
    public $export_order = "";
    public $export_uuid = "";
    public $export_layout = "";
    public $export_multiselect = "";
    public $export_selection = "";
    public $export_criterion_field = "";
    public $export_criterion_event = "";
    public $export_criterion_value = "";
    public $export_target = "";
    public $export_target_folder = "";
    public $export_max_label_length = "";
    public $export_max_text_length = "";
    public $export_inoffensive_text = "";
    public $export_no_tags = "";
    public $export_ascii_text = "";
    public $export_shift_dates = "";
    public $export_hash_recordid = "";
    public $export_hash_recordid_legacy = "";

    public $export_remove_phi = "";
    public $export_remove_dates = "";
    public $export_remove_freetext = "";
    public $export_remove_largetext = "";

    public $export_batch = "";

    public $export_sascode = "";
    public $export_sascode_ascii = "";
    public $export_sascode_libref = "";
    public $export_sascode_libref_path = "";
    public $export_sascode_dsname = "";

    public $export_file_type = ""; // csv or tsv
    public $export_data_delimiter = ","; // default delimiter for export data files
    public $export_data_extension = "csv"; // default extension for export data files

    public $export_code_filename_base = "";
    public $export_rcode = "";

    public $export_event_list = [];

    public $export_has_repeatables = 0;

    public $export_items = [];

    public function __construct( $exportSettings )
    {
        $this->export_name = $exportSettings['export_name'] ?? "noname";    
        $this->export_label = $exportSettings['export_label'] ?? "";
        $this->export_order = $exportSettings['export_order'] ?? "0";
        $this->export_uuid = $exportSettings['export_uuid'] ?? "";
        $this->export_layout = $exportSettings['export_layout'] ?? "";
        $this->export_multiselect = $exportSettings['export_multiselect'] ?? "1";
        $this->export_selection = $exportSettings['export_selection'] ?? "";
        $this->export_criterion_field = $exportSettings['export_criterion_field'] ?? "";
        $this->export_criterion_event = $exportSettings['export_criterion_event'] ?? "";
        $this->export_criterion_value = $exportSettings['export_criterion_value'] ?? "";
        $this->export_target = $exportSettings['export_target'] ?? "";
        $this->export_target_folder = $exportSettings['export_target_folder'] ?? "";
        $this->export_max_label_length = $exportSettings['export_max_label_length'] ?? "0";
        $this->export_max_text_length = $exportSettings['export_max_text_length'] ?? "0";
        $this->export_inoffensive_text = $exportSettings['export_inoffensive_text'] ?? "0";
        $this->export_no_tags = $exportSettings['export_no_tags'] ?? "0";
        $this->export_ascii_text = $exportSettings['export_ascii_text'] ?? "0";
        $this->export_shift_dates = $exportSettings['export_shift_dates'] ?? "0";
        $this->export_hash_recordid = $exportSettings['export_hash_recordid'] ?? "0";
        $this->export_hash_recordid_legacy = $exportSettings['export_hash_recordid_legacy'] ?? "0";

        $this->export_remove_phi = $exportSettings['export_remove_phi'] ?? "0";
        $this->export_remove_dates = $exportSettings['export_remove_dates'] ?? "0";
        $this->export_remove_freetext = $exportSettings['export_remove_freetext'] ?? "0";
        $this->export_remove_largetext = $exportSettings['export_remove_largetext'] ?? "0";

        $this->export_has_repeatables = 0; // $exportSettings['export_has_repeatables'] ?? "0";

        $this->export_batch = $exportSettings['export_batch'] ?? "0";

        $this->export_rcode = $exportSettings['export_rcode'] ?? "0";
        $this->export_sascode = $exportSettings['export_sascode'] ?? "0";
        $this->export_sascode_ascii = $exportSettings['export_sascode_ascii'] ?? "0";
        $this->export_sascode_libref = $exportSettings['export_sascode_libref'] ?? "";
        $this->export_sascode_libref_path = $exportSettings['export_sascode_libref_path'] ?? "";
        $this->export_sascode_dsname = $exportSettings['export_sascode_dsname'] ?? "";

        $this->export_file_type = $exportSettings['export_file_type'] ?? "csv"; // csv or tsv

        $this->export_code_filename_base = Yes3Fn::normalized_string($exportSettings['export_name'] ?? "");

        // derived properties
        $this->export_data_delimiter = ( $this->export_file_type === "tsv" ) ? "\t" : "," ;
        $this->export_data_extension = $this->export_file_type;
    }

    public function addExportItem( $exportItemProperties, $RecordIdField )
    {

        // the record ID field should not be associated with an event
        if ( isset($exportItemProperties['redcap_field_name']) && $exportItemProperties['redcap_field_name']===$RecordIdField ){

            $exportItemProperties['redcap_event_id'] = 0;
            $exportItemProperties['frequency_table'] = "";

            if ( $this->export_hash_recordid) {

                // If the record ID is hashed, we set the hashed flag
                $exportItemProperties['hashed'] = 1;
                $exportItemProperties['var_type'] = self::VARTYPE_TEXT; // hashed record ID is always a text field
            }
        }

        $export_item = new Yes3ExportItem($exportItemProperties);

        $this->export_items[] = $export_item;

        if ( isset($exportItemProperties['redcap_event_id']) ) {

            $this->updateEventList( $exportItemProperties['redcap_event_id'] );
        }

        // If the item is a repeatable, we set the repeatable flag
        if ( !$this->export_has_repeatables && $export_item->repeatable ) {
            $this->export_has_repeatables = 1;
        }
    }

    public function itemInExport($var_name)
    {
        for ( $i=0; $i<count($this->export_items); $i++){

            if ( $var_name===$this->export_items[$i]->var_name ){
                
                return true;
            }
            // goddam multiselects
            if ( strpos($this->export_items[$i]->var_name, $var_name . Yes3Fn::MULTISELECT_DELIM) === 0 ){

                return true;
            }
        }
        return false;
    }

    public function updateExportItemEvents($var_name, $event_id)
    {
        $event_id = (int) $event_id;

        if ( !$event_id ){

            return false;
        }

        $this->updateEventList( $event_id );
        
        for ( $i=0; $i<count($this->export_items); $i++){

            if ( $var_name===$this->export_items[$i]->var_name ){
                
                $this->export_items[$i]->redcap_events[] = $event_id;

                return true;
            }
        }
        return false;
    }

    private function updateEventList($event_id)
    {
        $event_id = (int) $event_id;

        if ( $event_id ){

            if ( !in_array($event_id, $this->export_event_list) ){

                $this->export_event_list[] = $event_id;
            }
        }
    }
}