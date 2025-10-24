<?php

namespace Yale\Yes3Exporter2;

/*
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
*/

$module = new Yes3Exporter2();

$copy = $module->getCopyright();

$uRights = $module->yes3UserRights();

$uSummary = $uRights['username'];

if ( $uRights['dag'] ) {

    $uSummary .= " / " . $uRights['dag'];
}

$module->disableBetaFeatures();

/*
$x = $module->getProjectSetting('enable-host-filesystem-exports');
$xt = gettype($x);

print "<pre> '" . $x . "' " . $xt . " </pre>";

exit();
*/

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

    FMAPR.copyright = "<?php echo $copy; ?>";

</script>


<!-- HELP PANEL -->

<div id="yes3-help-panel" class="yes3-panel yes3-help-panel yes3-draggable" style="display:none">

    <div class="yes3-panel-header-row">
        <div class="yes3-panel-row-left yes3-help-panel-title" id="yes3-help-panel-title">
            Getting Started with the Export Manager
        </div>
        <div class="yes3-panel-row-right">
            <a href="javascript: YES3.Help_closePanel()"><i class="fas fa-times fa-2x"></i></a>
        </div>
    </div>

    <div class="yes3-scrolling-container yes3-full-width">

        <h5>Overview</h5>
        <p>
            The Exporter Manager is a tool for adding, removing and editing <em>export specification</em>.
            An export specification defines the data to be exported from your project, as well as the format of the exported data.
        </p>
        <p>
            This Getting Started panel provides an overview of the Export Manager interface, with particular attention to inline and online help resources.
            You may leave this panel open while working with the Export Manager, or close it and refer to it later as needed.
        </p>
        <h5>How help is organized</h5>
        <p>
            The YES3 Exporter II Export Manager has a 2-tiered help system:
        </p>
        <ol>
            <li><span class="yes3-semibold">tooltips</span>: explanations of interface elements (action icons, below), available by hovering over them for 1 second or more with the mouse pointer.</li>
            <li><span class="yes3-semibold">online help</span>: user and technical guide available online, accessible by clicking on the 'book-reader' icon.</li>
        </ol>
        
        <div class="yes3-panel-blockquote yes3-legroom">
            <span class="yes3-semibold">Note: Every interactive element in the Exporter II Manager interface has a tooltip.</span>
            <br>If you are unsure of what an icon or button does, hover over it with your mouse pointer to see its tooltip.
        </div>

        <h5>Action icons</h5>
        <p class="yes3-legroom">
        All of the Export Manager features are accessed through clicking on 'action icons'.
        Each action icon is displayed below, along with the feature it invokes.
        </p>

        <table>
            <tbody>
                
                <tr>
                    <td>
                        <i class="fas fa-question yes3-action-icon yes3-nohandler"></i>
                    </td>
                    <td>
                        Open this Getting Started panel.
                    </td>
                </tr>

                <tr>
                    <td>
                        <i class="fas fa-book-reader yes3-action-icon yes3-nohandler"></i>
                    </td>
                    <td>
                        Open the YES3 Exporter online documentation (it will open into a new tab in your browser).
                    </td>
                </tr>
                            
                <tr class="yes3-light-theme-only">
                    <td>
                        <i class="fas fa-moon yes3-action-icon yes3-nohandler"></i>
                    </td>
                    <td>
                        Switch to dark theme.
                    </td>
                </tr>   

                <tr class="yes3-dark-theme-only">
                    <td>
                        <i class="fas fa-sun yes3-action-icon yes3-nohandler"></i>
                    </td>
                    <td>
                        Switch to light theme.
                    </td>
                </tr>  

                <tr>
                    <td>
                        <i class="fas fa-refresh yes3-action-icon yes3-nohandler"></i>
                    </td>
                    <td>
                        Refresh the export specification summarized in the specific row. Be sure to click the refresh icon after making changes to an export specification in the Exporter II Editor.
                    </td>
                </tr>

                <tr>
                    <td>
                        <i class="fas fa-edit yes3-action-icon yes3-nohandler"></i>
                    </td>
                    <td>
                        Edit the export specification summarized in the specific row.
                    </td>
                </tr>

                <tr>
                    <td>
                        <i class="fas fa-plus yes3-action-icon yes3-nohandler"></i>
                    </td>
                    <td>
                        (displayed at the bottom of the table) Add a new export specification.
                    </td>
                </tr>
                
                <tr>
                    <td>
                        <i class="fas fa-file-export yes3-action-icon yes3-nohandler"></i>
                    </td>
                    <td>
                        Export data dictionary and datasheet to the file system (if configured; requires super user and IT support).
                    </td>
                </tr>
                
                <tr>
                    <td>
                        <i class="fas fa-download yes3-action-icon yes3-nohandler"></i>
                    </td>
                    <td>
                        Download the data dictionary and/or datasheet.
                    </td>
                </tr>

                <tr>
                    <td>
                        <i class="far fa-trash-alt yes3-action-icon yes3-nohandler"></i>
                    </td>
                    <td>
                        Remove the export specification. The export specification is not deleted, but is hidden from view and excluded from exports. You can restore a removed export specification by clicking on the 'show removed' icon ( <i class="fas fa-eye yes3-action-icon yes3-nohandler"></i> ) at the bottom of the table, then clicking the 'restore' icon ( <i class="fas fa-trash-restore yes3-action-icon yes3-nohandler"></i> ) in the row of the export specification to be restored.
                    </td>
                </tr>

                <tr>
                    <td>
                        <i class="fas fa-eye yes3-action-icon yes3-nohandler"></i>
                    </td>
                    <td>
                        (displayed at the bottom of the table) Show removed export specifications.
                    </td>
                </tr>

                <tr>
                    <td>
                        <i class="fas fa-eye-slash yes3-action-icon yes3-nohandler"></i>
                    </td>
                    <td>
                        (displayed at the bottom of the table) Hide removed export specifications.
                    </td>
                </tr>

                
            </tbody>
        </table>

    </div> <!-- yes3-scrolling-container -->

    <div class='yes3-panel-got-it' id='yes3-help-panel-got-it'>
        <label class="yes3-checkmarkContainer  yes3-semibold">
        <input type="checkbox" name="yes3-got-it" value="1" onclick="YES3.Help_setGotIt()">
        <span class="yes3-checkmark" data-bs-toggle="tooltip" data-bs-html="true" data-bs-placement="top" title="If checked, this 'getting started' panel will not be automatically displayed when the editor is opened. Uncheck to re-enable automatic display."></span>
        Do NOT display this panel automatically when the Export Manager is opened.
        </label>
    </div>
   
</div>

<div class="yes3-container" id="yes3-container" style="padding-right: 20px;">

    <!-- CONTROL BAR -->

    <div class="yes3-flex-container-vbaseline yes3-fmapr-controls  yes3-look-out-below">

        <div class="yes3-flex-col-33 yes3-flex-hleft yes3-ellipsis">
            <span class="yes3-title">YES3</span> <span class="yes3-subtitle">Exporter II <span class="yes3-semibold">Manager</span></span>
        </div>

        <div class="yes3-flex-col-33 yes3-flex-hleft yes3-ellipsis">

            <div id="yes3-fmapr-header-center"></div>

        </div>

        <div class="yes3-flex-col-33 yes3-flex-vcenter-hright">

            <i class="fas fa-question yes3-action-icon yes3-action-icon-controlpanel yes3-tooltip-static" action="Help_openPanel" data-bs-toggle="tooltip" title="Open a navigation help panel."></i>

            <i class="fas fa-book-reader yes3-action-icon yes3-action-icon-controlpanel yes3-tooltip-static" action="Open_docPage" data-bs-toggle="tooltip" title="Open the YES3 Exporter2 online documentation."></i>

            <i class="fas fa-moon yes3-action-icon yes3-action-icon-controlpanel yes3-light-theme-only yes3-tooltip-static" action="Theme_dark" data-bs-toggle="tooltip" title="Give in to the dark side (dark theme)."></i>
            <i class="fas fa-sun yes3-action-icon yes3-action-icon-controlpanel yes3-dark-theme-only yes3-tooltip-static" action="Theme_light" data-bs-toggle="tooltip" title="Switch to the light theme."></i>

            <img class="yes3-square-logo yes3-logo yes3-tooltip-static" alt="YES3 Logo" data-bs-toggle="tooltip" title="Open the Yale YES Portal website.">

        </div>

    </div>

    <!-- DATA TABLE -->

    <div class="yes3-container">

            <table class="yes3-dashboard" id="yes3-fmapr-export-table">

                <thead>
                    <tr>
                        <th class="yes3-col-sm yes3-header yes3-halign-center yes3-required-column yes3-designer-only yes3-tooltip-static" data-bs-toggle="tooltip" title="Refresh ALL specifications."><i class="fas fa-refresh refresh-all-rows" onclick="FMAPR.loadSpecifications( 1 );"></i></th>
                        <th class="yes3-col-sm yes3-header yes3-halign-center yes3-required-column yes3-designer-only yes3-tooltip-static" data-bs-toggle="tooltip" data-bs-html="true" title="Edit the selected export (<i class='fas fa-edit'></i>), or add a new export (<i class='fas fa-plus'></i>)">Edit/add</th>
                        <th class="yes3-col-sm yes3-header yes3-halign-center yes3-required-column yes3-tooltip-static" data-bs-toggle="tooltip" title="Download the selected data dictionary and/or data, and/or if configured, export to the host file system.">Export</th>
                        <th class="yes3-col-md yes3-header yes3-halign-left   yes3-required-column yes3-tooltip-static" id="foo" data-bs-placement="top-start" data-bs-toggle="tooltip" title="The export name, which is the basis for export file names.">Name</th>
                        <th class="yes3-col-lg yes3-header yes3-halign-left   yes3-tooltip-static" data-bs-toggle="tooltip" title="A description of the export.">Label</th>
                        <th class="yes3-col-sm yes3-header yes3-halign-center yes3-tooltip-static" data-bs-toggle="tooltip" title="Export layout: vertical or horizontal">Layout</th>
                        <th class="yes3-col-sm yes3-header yes3-halign-center yes3-fmapr-batch-exports yes3-tooltip-static" data-bs-toggle="tooltip" title="Indicates whether the export is included<br />in the daily batch (cron) job.">Batch</th>
                        <th class="yes3-col-sm yes3-header yes3-halign-center yes3-tooltip-static" data-bs-toggle="tooltip" title="The column count for the selected export.">Columns</th>
                        <th class="yes3-col-sm yes3-header yes3-halign-center yes3-required-column yes3-tooltip-static yes3-designer-only" data-bs-toggle="tooltip" title="Remove or restore the export.">Remove</th>
                    </tr>
                </thead>

                <tbody id="yes3-fmapr-export-tbody"></tbody>

                <tfoot id="yes3-fmapr-export-tfoot">
                    <tr class='yes3-designer-only'>
                        <td class="yes3-col-sm yes3-halign-center yes3-required-column" title="dum de dum-dum">&nbsp</i></td>
                        <td class="yes3-col-sm yes3-halign-center yes3-required-column" data-bs-toggle="tooltip" title="Click to <span class='yes3-semibold'>add a new export specification</span>"><i class="fas fa-plus" onclick="FMAPR.NewExport_openPanel()"></i></td>
                        <td class="yes3-col-sm yes3-halign-center yes3-required-column"></td>
                        <td class="yes3-col-md yes3-halign-left   yes3-required-column"><em>new export</em></td>
                        <td class="yes3-col-lg yes3-halign-left"></td>
                        <td class="yes3-col-sm yes3-halign-left"></td>
                        <td class="yes3-col-sm yes3-halign-left yes3-fmapr-batch-exports"></td>
                        <td class="yes3-col-sm yes3-halign-left"></td>
                        <td class="yes3-col-sm yes3-halign-center yes3-required-column" id="yes3-fmapr-visibility-control">
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

        <div class="yes3-flex-col-33 yes3-flex-vcenter-hleft">

            <div id="yes3-message"></div>

        </div>

        <div class="yes3-flex-col-33 yes3-flex-vcenter-hright">

            <div id="yes3-user"><?= $uSummary ?></div>

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
            <a href="javascript: FMAPR.NewExport_closePanel()"><i class="fas fa-times fa-2x"></i></a>
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

        if ( !YES3.EMSettings['enable-host-filesystem-exports'] ) {

            // remove items associated with filesystem exports
            $('.yes3-fmapr-filesystem-exports').remove();
        }

        // BETA export validator is only available internally
        if ( YES3.username !== 'criwebtools' ) {

            $('.yes3-fmapr-validator-only').remove();
        }
    })

</script>




