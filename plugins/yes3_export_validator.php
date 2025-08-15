<?php

namespace Yale\Yes3Exporter;

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$module = new Yes3Exporter();

$copy = $module->getCopyright();

use Yale\Yes3\Yes3;
use REDCap;
use HtmlPage;

$HtmlPage = new HtmlPage();
$HtmlPage->ProjectHeader();

$module->getCodeFor("yes3_export_validator", true);

?>

<!-- HELP PANEL -->

<div id="yes3-help-panel" class="yes3-panel yes3-help-panel yes3-draggable" style="display:none">

    <div class="yes3-panel-header-row">
        <div class="yes3-panel-row-left" id="yes3-help-panel-title">
            Here's some help
        </div>
        <div class="yes3-panel-row-right">
            <a href="javascript: YES3.Help_closePanel()"><i class="fas fa-times fa-x"></i></a>
        </div>
    </div>

    <div class="yes3-panel-row" style="margin-top: 20px !important">
    </div>

    <div class='yes3-panel-row yes3-information'>

        <div class='yes3-panel-row'>
            You may leave this help panel open as you use the Yes3 Exporter. 
            Grab it on the top row to drag it out of the way.
        </div>

    </div>

</div>

<div class="yes3-container" id="yes3-container" style="padding-right: 20px;">

    <!-- TITLE BAR -->

    <div class="yes3-flex-container-vbaseline yes3-fmapr-controls  yes3-look-out-below">

        <div class="yes3-flex-col-67 yes3-hleft yes3-ellipsis">
            <span class="yes3-title">YES3</span> <span class="yes3-subtitle">Exporter II <span class="yes3-semibold">Validator</span></span>
        </div>

        <div class="yes3-flex-col-33 yes3-flex-vcenter-hright">

            <!--i class="fas fa-refresh yes3-action-icon yes3-action-icon-controlpanel" action="Page_refresh" title="refresh this page"></i-->
            
            <i class="fas fa-question yes3-action-icon yes3-action-icon-controlpanel" action="Help_openPanel" title="get some help"></i>

            <i class="fas fa-moon yes3-action-icon yes3-action-icon-controlpanel yes3-light-theme-only" action="Theme_dark" title="Switch to the dark side"></i>
            <i class="fas fa-sun yes3-action-icon yes3-action-icon-controlpanel yes3-dark-theme-only" action="Theme_light" title="Switch to the light theme"></i>

            <img class="yes3-square-logo yes3-logo" alt="YES3 Logo" title="More about YES3..." >

        </div>

    </div>

    <!-- EXPORT SELECTOR -->

    <div class="yes3-flex-container-left-aligned">

        <div class="yes3-hleft yes3-fmapr-validator-inbox" id="yes3-fmapr-export-selection">
            <ul>
                <li><span class="name">my big fat export</span><span class="label"> don't leave home without it</span></li>
                <li><span class="name">the hot skinny</span><span class="label"> everything you need to know</span></li>
            </ul>
        </div>

        <div class="yes3-hleft yes3-fmapr-validator-inbox-noborders" id="yes3-fmapr-export-upload">
            <form id="yes3-fmapr-export-upload-form" enctype="multipart/form-data">
                <input type="file" id="yes3-fmapr-export-upload-file" name="yes3-fmapr-export-upload-file" accept=".csv,.tsv" />
            </form>
        </div>

        <div class="yes3-hleft yes3-fmapr-validator-inbox-noborders" id="yes3-fmapr-export-validate">
            <form id="yes3-fmapr-export-upload-form" enctype="multipart/form-data">
                <input type="button" id="yes3-fmapr-export-validate-button" value="validate">
            </form>
        </div>

        <div class="yes3-hleft yes3-fmapr-validator-inbox-noborders" id="yes3-fmapr-export-validate-message">
        </div>

    </div>

    <!-- EXPORT VALIDATION RESULTS -->

    <div id="yes3-fmapr-export-validate-results-container">
        <table id="yes3-fmapr-export-validate-results-table" class="yes3-fampr-scrolling-table">
            <thead>
                <tr>
                    <th>Row</th>
                    <th>Record</th>
                    <th>Event</th>
                    <th>Instance</th>
                    <th>Field</th>
                    <th>Error</th>
                    <th>Message</th>
                </tr>
            </thead>
            <tbody id="yes3-fmapr-export-validate-results-body">
            </tbody>
        </table>
    </div>

    <!-- FOOTER -->

    <div class="yes3-flex-container" id="yes3-fmapr-footer">

        <div class="yes3-flex-col-50 yes3-flex-vcenter-hleft">

            <div id='yes3-fmapr-copyright'></div>

        </div>

        <div class="yes3-flex-col-50 yes3-flex-vcenter-hleft">

            <div id="yes3-message"></div>

        </div>

    </div>


</div>

<script>

    $(function(){
        $('#yes3-fmapr-copyright').html(YES3.copyright);
    })

</script>




