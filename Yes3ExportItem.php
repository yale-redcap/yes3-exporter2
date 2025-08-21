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

        // If the item is a repeatable, we set the repeatable flag
        if ( !$this->repeatable && isset( $exportItemProperties['redcap_form_name'] ) && Yes3Fn::isRepeatingInstrument( $exportItemProperties['redcap_form_name'] ) ) {
            
            $this->repeatable = 1;
        } 
    }
}