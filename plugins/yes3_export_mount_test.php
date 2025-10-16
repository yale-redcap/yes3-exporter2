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

?>

<?= $module->initializeJavascriptModuleObject(); ?>

<script>

    const jmo = <?= $module->getJavascriptModuleObjectName() ?>;

    function runWriteTest() {

        jmo.ajax('test-filesystem-write', {}).then(function(response) {
        
            document.getElementById('test-write-result').innerText = response;
            document.getElementById('test-write-result').style.display = 'block';
        }).catch(function(err) {

            // Handle error
            document.getElementById('test-write-result').innerText = 'Error: ' + err;
            document.getElementById('test-write-result').style.display = 'block';
        });
    }
</script>

<h4>Yes3 Exporter II - Host filesystem write test</h4>

<p>This test will attempt to write a small text file to the configured export target folder.</p>
<p>If the write is successful, the test file will be deleted.</p>
<p>This test does not export any data from REDCap. It only tests the ability of the web server to write to the configured folder.</p>
<p><strong>Note:</strong> The configured folder must be accessible to the web server user (e.g. www-data, apache, etc.) and must have write permissions.</p>
<input type="button" id="test-write-btn" value="Run the test" onclick="runWriteTest();" />
<pre id="test-write-result" style="margin-top:20px;max-width: 1000px;display:none;"></pre>