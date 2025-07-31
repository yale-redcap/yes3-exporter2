<?php

namespace Yale\Yes3Exporter;

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$module = new Yes3Exporter();

use Yale\Yes3\Yes3;
use REDCap;
use HtmlPage;

$HtmlPage = new HtmlPage();
$HtmlPage->ProjectHeader();

phpinfo();

exit();

$event_id = 44; // 45 day interview

print "<pre>";

print "\n-----\nREDCap::isLongitudinal()\n-----\n";
print print_r(REDCap::isLongitudinal(), true);
print "\n-----\nmodule->isLongitudinal()\n-----\n";
print print_r($module->isLongitudinal(), true);

print "\n-----\nREDCap::getGroupNames()\n-----\n";
print print_r(REDCap::getGroupNames(), true);
print "\n-----\nmodule->getGroupNames()\n-----\n";
print print_r($module->getGroupNames(), true);

print "\n-----\nREDCap::getGroupNames(true)\n-----\n";
print print_r(REDCap::getGroupNames(true), true);
print "\n-----\nmodule->getGroupNames(true)\n-----\n";
print print_r($module->getGroupNames(true), true);

print "\n-----\nREDCap::getEventNames()\n-----\n";
print print_r(REDCap::getEventNames(), true);
print "\n-----\nmodule->getEventNames()\n-----\n";
print print_r($module->getEventNames(), true);

print "\n-----\nREDCap::getEventNames(true)\n-----\n";
print print_r(REDCap::getEventNames(true), true);
print "\n-----\nmodule->getEventNames(true)\n-----\n";
print print_r($module->getEventNames(true), true);

print "\n-----\nREDCap::getEventNames(true, true, $event_id)\n-----\n";
print print_r(REDCap::getEventNames(true, true, $event_id), true);
print "\n-----\nmodule->getEventNames(true, true, $event_id)\n-----\n";
print print_r($module->getEventNames(true, true, $event_id), true);

print "</pre>";

