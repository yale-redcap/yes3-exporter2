<?php

namespace Yale\Yes3Exporter2;

use Yale\Yes3Exporter2\Yes3Fn;

class Yes3ExportItem {

    public $var_name = "";
    public $var_type = "";
    public $var_label = "";
    public $valueset = [];

    public $origin = "";
    public $redcap_field_name = "";
    public $redcap_events = [];
    public $redcap_form_name = "";
    public $redcap_event_id = "";
    public $redcap_event_name = "";
    
    public $repeatable = 0;
    public $event_repeats = 0;
    public $form_repeats = 0;

    public $non_missing_count = 0;
    public $min_length = Yes3Fn::VERY_LARGE_NUMBER;
    public $max_length = 0;
    public $min_value = NULL;
    public $max_value = NULL;
    public $sum_of_values = NULL;
    public $sum_of_squared_values = NULL;
    public $mean = NULL;
    public $standard_deviation = NULL;
    public $formatted_min_value = NULL;
    public $formatted_max_value = NULL;
    public $formatted_mean = NULL;
    public $frequency_table = [];

    public $hashed = 0; // whether this item is a hashed record ID

    public function __construct( $exportItemProperties )
    {
        foreach ( $exportItemProperties as $propName => $propValue ) {

            $this->$propName = $propValue;
        }

        // If the item is a repeatable form or event, we set the repeatable flags
        if ( isset( $exportItemProperties['redcap_form_name']) && isset( $exportItemProperties['redcap_event_id'] ) ) {

            $form_repeats_this_event = 0;
            $event_repeats = 0;

            if ( isset( $exportItemProperties['redcap_event_id'] ) ) {

                $event_repeats = Yes3Fn::isRepeatingEvent( $exportItemProperties['redcap_event_id'] );
            
                $form_repeats_this_event = Yes3Fn::isRepeatingInstrumentForEvent( 
                    $exportItemProperties['redcap_form_name'], 
                    $exportItemProperties['redcap_event_id'] 
                );
            }
            
            $this->repeatable =  ( $event_repeats | $form_repeats_this_event );  // this is what counts, as it indicates that the instance must be on the export
            $this->form_repeats = $form_repeats_this_event;
            $this->event_repeats = $event_repeats;
        } 
    }
}