<?php

namespace Yale\Yes3Exporter2;


ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);


$module = new Yes3Exporter2();
use Yale\Yes3\Yes3;
use REDCap;
use HtmlPage;

$HtmlPage = new HtmlPage();
$HtmlPage->ProjectHeader();

$exports_enabled = $module->getProjectSetting('enable-host-filesystem-exports') ? 1 : 0;

if ($exports_enabled !== 1) {
    echo "<h4>Exports to file system are not enabled</h4>";
    exit();
}

$mount_path = $module->getProjectSetting('export-target-folder');

if (empty($mount_path)) {
    echo "<h4>Export target folder is not set</h4>";
    exit();
}

$filename = "yes3_exporter_test_" . getCompactTimestamp() . ".txt";

echo "<pre>";

echo "Mount path: $mount_path\n";

echo "Test filename: $filename\n";

// append the directopry separator to the mount path if it is not already there
if (substr($mount_path, -1) !== DIRECTORY_SEPARATOR) {
    $mount_path .= DIRECTORY_SEPARATOR;
}

// path to the file to be created
$file_path = $mount_path . $filename;

$file_content = "This is a test file created by the Yes3 Exporter2 External Module on " . date('Y-m-d H:i:s');

$file_content .= "\nProject: " . $module->getProject()->getTitle() . " ( pid " . $module->getProjectId() . " )";

$file_content .= "\nUser: " . $module->getUser()->getUsername() . "\n";

// write the content to the file and store the result in a variable
$write_result = file_put_contents($file_path, $file_content);

if ($write_result === false) {
    echo "Failed to write to file: $file_path.\n";
} else {
    echo "Successfully wrote $write_result bytes to file: $file_path.\n";
    // delete the test file after writing
    //unlink($file_path);
}

echo "</pre>";

/**
 * Encodes a number in base 36 (0-9, a-z)
 * 
 * @param mixed $num 
 * @return string 
 */
function base36Encode($num) {
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

function getCompactTimestamp() {
    $currentTimestamp = time();
    return base36Encode($currentTimestamp);
}
