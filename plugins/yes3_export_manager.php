<?php

namespace Yale\Yes3Exporter;


ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);


$module = new Yes3Exporter();

$copy = $module->getCopyright();

$enable_host_filesystem_exports = ($module->getProjectSetting('enable-host-filesystem-exports') ?? "N") === 'Y';

use Yale\Yes3\Yes3;
use REDCap;
use HtmlPage;

//$module->emailDailyLog();

$HtmlPage = new HtmlPage();
$HtmlPage->ProjectHeader();

/**
 * build the export options
 */



/**
 * getCodeFor will: 
 *   (1) output html tags and code for js and css libraries named [param1]
 *   (2) if [param2] is true, output /html/yes3.html (yes3 dialog panels)
 *   (3) output js code to build the global yes3ModuleProperties object
 */

$module->getCodeFor("yes3_export_manager", true);

?>

<script type="text/javascript">

    FMAPR.enableHostFilesystemExports = <?php echo $enable_host_filesystem_exports ? 'true' : 'false'; ?>;  
    FMAPR.copyright = "<?php echo $copy; ?>";

</script>


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

    <!-- CONTROL BAR -->

    <div class="yes3-flex-container-vbaseline yes3-fmapr-controls">

        <div class="yes3-flex-col-33 yes3-flex-hleft yes3-ellipsis">
            <span class="yes3-title">YES3</span> <span class="yes3-subtitle">Exporter II <span class="yes3-semibold">Manager</span></span>
        </div>

        <div class="yes3-flex-col-33 yes3-flex-hleft yes3-ellipsis">

            <div id="yes3-fmapr-header-center"></div>

        </div>

        <div class="yes3-flex-col-33 yes3-flex-vcenter-hright">

            <!--i class="fas fa-refresh yes3-action-icon yes3-action-icon-controlpanel" action="Page_refresh" title="refresh this page"></i-->
            
            <i class="fas fa-question yes3-action-icon yes3-action-icon-controlpanel" action="Help_openPanel" title="get some help"></i>

            <i class="fas fa-moon yes3-action-icon yes3-action-icon-controlpanel yes3-light-theme-only" action="Theme_dark" title="Switch to the dark side"></i>
            <i class="fas fa-sun yes3-action-icon yes3-action-icon-controlpanel yes3-dark-theme-only" action="Theme_light" title="Switch to the light theme"></i>

            <img class="yes3-square-logo yes3-logo" alt="YES3 Logo" title="More about YES3..." >

        </div>

    </div>

    <!-- DATA TABLE -->

    <div class="yes3-container">

            <table class="yes3-dashboard" id="yes3-fmapr-export-table">

                <thead>
                    <tr>
                        <th class="yes3-col-sm yes3-header yes3-halign-center yes3-required-column yes3-designer-only" title="refresh all rows"><i class="fas fa-refresh" onclick="FMAPR.loadSpecifications( 1 );"></i></th>
                        <th class="yes3-col-sm yes3-header yes3-halign-center yes3-required-column yes3-designer-only" title="edit the selected export, or add a new export">Edit/add</th>
                        <th class="yes3-col-sm yes3-header yes3-halign-center yes3-required-column" title="download or export the selected export">Export</th>
                        <th class="yes3-col-md yes3-header yes3-halign-left   yes3-required-column" title="export name">Name</th>
                        <th class="yes3-col-lg yes3-header yes3-halign-left  " title="export label">Label</th>
                        <th class="yes3-col-sm yes3-header yes3-halign-center" title="export layout">Layout</th>
                        <th class="yes3-col-sm yes3-header yes3-halign-center yes3-fmapr-filesystem-exports" title="export included in daily batch (cron) job">Batch</th>
                        <th class="yes3-col-sm yes3-header yes3-halign-center" title="column count">Columns</th>
                        <th class="yes3-col-sm yes3-header yes3-halign-center yes3-required-column yes3-designer-only" title="remove the export (can be restored later)">Remove</th>
                    </tr>
                </thead>

                <tbody id="yes3-fmapr-export-tbody"></tbody>

                <tfoot id="yes3-fmapr-export-tfoot">
                    <tr class='yes3-designer-only'>
                        <td class="yes3-col-sm yes3-halign-center yes3-required-column" title="dum de dum-dum">&nbsp</i></td>
                        <td class="yes3-col-sm yes3-halign-center yes3-required-column" title="click to add a new export specification"><i class="fas fa-plus" onclick="FMAPR.NewExport_openPanel()"></i></td>
                        <td class="yes3-col-sm yes3-halign-center yes3-required-column"></td>
                        <td class="yes3-col-md yes3-halign-left   yes3-required-column"><em>new export</em></td>
                        <td class="yes3-col-lg yes3-halign-left"></td>
                        <td class="yes3-col-sm yes3-halign-left"></td>
                        <td class="yes3-col-sm yes3-halign-left yes3-fmapr-filesystem-exports"></td>
                        <td class="yes3-col-sm yes3-halign-left"></td>
                        <td class="yes3-col-sm yes3-halign-center yes3-required-column" id="yes3-fmapr-visibility-control" title="click to show removed exports, which can then be restored">
                            show xx<br>removed
                        </td>
                    </tr>
                </tfoot>

            </table>


    </div>

    <div class="yes3-flex-container" id="yes3-fmapr-footer">

        <div class="yes3-flex-col-33 yes3-flex-vcenter-hleft">

            <div id="yes3-fmapr-copyright"><?= $copy ?></div>

        </div>

        <div class="yes3-flex-col-67 yes3-flex-vcenter-hleft">

            <div id="yes3-message"></div>

        </div>

    </div>


</div>

<!-- NEW EXPORT FORM -->

<div id="yes3-fmapr-new-export-form" class="yes3-panel yes3-panel-medium yes3-draggable" style="display:none">

    <div class="yes3-panel-header-row">

        <div class="yes3-panel-row-left" id="yes3-help-panel-title">
            New Export
        </div>
        <div class="yes3-panel-row-right">
            <a href="javascript: FMAPR.NewExport_closePanel()"><i class="fas fa-times fa-x"></i></a>
        </div>
        
    </div>

    <div class="yes3-panel-row yes3-duck" >
    
        <p class="yes3-panel-subtitle">
            Please provide a name and and layout for the export to be added.
        </p>
        <p>Note that while you can change the name later, <strong>you cannot change the layout once the export has been created.</strong></p>

        <table class="yes3-settings" id="yes3-fmapr-new-export">

            <tr>
                <td>
                    Export NAME
                </td>

                <td>
                    <input type="text" id="new_export_name" value="new export" class="" placeholder="enter an export name">
                </td>
            </tr>

            <tr>
                <td>
                    Export LAYOUT
                </td>

                <td>

                    <input class="balloon yes3-longitudinal-only" type="radio" class="balloon" value="h" name="new_export_layout" id="yes3-fmapr-new-export-layout-h">
                    <label class="yes3-longitudinal-only" for="yes3-fmapr-new-export-layout-h" title="Horizontal layout (longitudinal studies: one row per record per repeat instance)">
                        Horizontal: one row per record (per repeat instance)
                    </label>

                    <br class="yes3-longitudinal-only">

                    <input type="radio" class="balloon" value="v" name="new_export_layout" id="yes3-fmapr-new-export-layout-v">
                    <label for="yes3-fmapr-new-export-layout-v" title="Vertical: one row per record per event (per repeat instance)">
                        Vertical: one row per record per event (per repeat instance)
                    </label>

                    <!--br class="yes3-has-repeating-forms">

                    <input type="radio" class="balloon yes3-has-repeating-forms" value="r" name="new_export_layout" id="yes3-fmapr-new-export-layout-r">
                    <label class="yes3-has-repeating-forms" for="yes3-fmapr-new-export-layout-r" title="Repeating SINGLE Form: one row per record per event (per repeat instance), only one form allowed">
                        Repeating Form (DEPRECATED): one row per record per event per repeat instance
                    </label-->

                </td>
            </tr>

            <tr><td colspan="2">

                <p class="yes3-panel-subtitle yes3-duck">July 2025 notice</p>

                <div class="yes3-information">

                    <p class="yes3-information-bold">
                        As of version 2.0.0 (July 2025), the Vertical and Horizontal layouts each support repeating events and forms.
                        The YES3 Exporter I Repeating Form layout, which is equivalent to the YES3 Exporter II Vertical layout for a single repeating form, is no longer supported.
                    </p>

                    <p>
                        If you open a YES3 Exporter I export specification that uses the Repeating Form layout, it will be converted to the YES3 Exporter II Vertical layout.
                    </p>

                </div>
            </td></tr>

        </table>

    </div>

    <div class="yes3-panel-row">

        <div class="yes3-flex-container-evenly-distributed">

            <div class="yes3-flex-vcenter-hleft">

            </div>

            <div class="yes3-flex-vcenter-hright">

                <div class="yes3-flex-container-right-aligned">

                    <div class="yes3-flex-vcenter-hright">
                        <input type="button" onClick="FMAPR.NewExport_closePanel();" class="yes3-panel-button yes3-button-caption-cancel" />
                    </div>

                    <div class="yes3-flex-vcenter-hright">
                        <input type="button" onClick="FMAPR.NewExport_execute();" class="yes3-panel-button yes3-button-caption-save" />
                    </div>

                </div>

            </div>

        </div>
    </div>
</div>

<script>

    $(function(){

        if ( !FMAPR.enableHostFilesystemExports ) {

            // remove items associated with filesystem exports
            $('.yes3-fmapr-filesystem-exports').remove();
        }
    })

</script>




