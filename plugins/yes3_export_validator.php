<?php

namespace Yale\Yes3Exporter2;

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$module = new Yes3Exporter2();

$csrf_token = $module->getCSRFToken();

?>

<!DOCTYPE html>
<html>
<head>

    <title>YES3 Exporter II Validator</title>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/ui/1.14.0/jquery-ui.min.js" integrity="sha256-Fb0zP4jE3JHqu+IBB9YktLcSjI1Zc6J2b6gTjB0LpoM=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/smoothness/jquery-ui.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="icon" type="image/x-icon" href="<?= $module->getUrl('favicon.ico')?>">

</head>
<body>

<style>
    body {
        padding: 20px;
    }
</style>

<script>
    var redcap_csrf_token = '<?= $csrf_token?>';
</script>

<?php $module->getCodeFor('yes3_export_validator', true, true) ?>


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

<div class="yes3-container" id="yes3-container" style="padding-right: 20px; min-width: 800px">

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

        <div class="yes3-hleft yes3-fmapr-validator-inbox yes3-scrolling-container" id="yes3-fmapr-export-selection">
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

    <div id="yes3-fmapr-export-validate-results-section">
        <div id="yes3-fmapr-export-validate-results-container">

            <table id="yes3-fmapr-export-validate-results-table" class="yes3-fampr-scrolling-table">
                <colgroup>
                    <col class="col-row yes3-text-center">
                    <col class="col-error">
                    <col class="col-record">
                    <col class="col-event">
                    <col class="col-instance yes3-text-center">
                    <col class="col-field">
                    <col class="col-message">
                </colgroup>
                <thead>
                    <tr>
                        <th class="yes3-text-center">Row</th>
                        <th>Error</th>
                        <th>Record</th>
                        <th>Event</th>
                        <th class="yes3-text-center">Inst</th>
                        <th>Field</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody id="yes3-fmapr-export-validate-results-body">
                </tbody>
            </table>
        </div>
    </div>

    <!-- FOOTER -->

    <div class="yes3-flex-container" id="yes3-fmapr-validate-footer">

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




