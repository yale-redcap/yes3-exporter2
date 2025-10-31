<?php

namespace Yale\Yes3Exporter2;

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

use ExternalModules\ExternalModules;

use Exception;

use Project;

use REDCap;

use Yale\Yes3Exporter2\Yes3Fn;

class Yes3ValueSetIndexer
{
  const LOG_MESSAGE_VSET_INDEX = "value set index";
  public function welcome()
  {
    return "Welcome to the YES3 Value Set Indexer!";
  }

  public function indexValueSet( $element_enum )
  {
    $params = [
      'vset_id' => Yes3Fn::compactUUIDv4(),
      'vset_timestamp' => date("Y-m-d H:i:s"),
      'element_enum' => $element_enum
    ];

    $log_id = ExternalModules::log( self::LOG_MESSAGE_VSET_INDEX, $params );

    if ( !$log_id ) {
      throw new Exception("Failed to log value set index operation");
    }

    return $params['vset_id'];
  }

}