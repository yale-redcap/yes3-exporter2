<?php

namespace Yale\Yes3Exporter2;

use HtmlPage;

/*
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
*/

$module = new Yes3Exporter2();

$HtmlPage = new HtmlPage();
$HtmlPage->ProjectHeader();

/**
 * getCodeFor will: 
 *   (1) output html tags and code for js and css libraries named [param1]
 *   (2) if [param2] is true, output /html/yes3.html (yes3 dialog panels)
 *   (3) output js code to build the global yes3ModuleProperties object
 */

$module->getCodeFor("yes3_export_prefixes", true);

?>

<div id="yes3-help-panel" class="yes3-panel yes3-help-panel yes3-draggable" style="display:none">

    <div class="yes3-panel-header-row">
        <div class="yes3-panel-row-left" id="yes3-help-panel-title">
            Getting started with the Arm/Event Prefix Editor
        </div>
        <div class="yes3-panel-row-right">
            <a href="javascript: YES3.Help_closePanel()"><i class="fas fa-times fa-2x"></i></a>
        </div>
    </div>
    
    <div class="yes3-scrolling-container yes3-full-width yes3-default-panel-height">

        <div class="yes3-panel-row">

            <h5>Overview</h5>
            <p>
                The Exporter Arm/Event Prefix Editor is a tool for designing and editing <em>arm/event prefixes</em> 
                which are used to formulate variable names for horizontal export layouts.
            </p>
            <p>
                This Getting Started panel provides guidance on using the Exporter Arm/Event Prefix Editor.
                You may leave this panel open while working with the Exporter Arm/Event Prefix Editor, or close it and refer to it later as needed.
            </p>
            <h5>How help is organized</h5>
            <p>
                The YES3 Exporter Arm/Event Prefix Editor supports a 2-tiered help system:
            </p>
            <ol>
                <li><strong>tooltips</strong>: brief descriptions of buttons and fields, available by hovering over them for 1 second or more with the mouse pointer. Every input field, button, checkbox, or other interactive element has an associated tooltip.</li>
                <li><strong>online help</strong>: comprehensive documentation available online, accessible by clicking on the 'book-reader' icon ( <i class="fas fa-book-reader yes3-action-icon yes3-nohandler"></i> ).</li>
            </ol>

            <h5>Action icons (top right)</h5>
            <p>
                The Exporter Arm/Event Prefix Editor interface includes a set of 'action icons' located at the top right of the page.
                These invoke editor-level features such as save and rollback. Each action icon is displayed below, along with the feature it invokes.
            </p>

            <table class="yes3-legroom yes3-help-table">
                <tbody>  

                    <tr>
                        <td>
                            <i class="far fa-save yes3-action-icon yes3-nohandler"></i>
                        </td>
                        <td>
                            Save the export event prefixes.
                        </td>
                    </tr>
                    
                    <tr>
                        <td>
                            <i class="fas fa-undo yes3-action-icon yes3-nohandler"></i>
                        </td>
                        <td>
                            Restore all the settings on this page to their stored values (undo).
                        </td>
                    </tr>   

                    <tr>
                        <td>
                            <i class="fas fa-question yes3-action-icon yes3-nohandler"></i>
                        </td>
                        <td>
                            Display this Help panel.
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

                </tbody>
            </table>

            <h5>About event prefixes</h5>

            <p>
                The YES3 Exporter supports a 'horizontal layout' for longitudinal projects,
                in which fields for forms that are placed on multiple events are all 
                included on the same row.
            </p><p> 
                To make this possible, the field names are altered by prefixing them
                with the event abbreviations displayed on this page. The export field name
                will have the form 
            </p><p>
                <pre>[event prefix]_[REDCap field name]</pre>
            </p><p>
                You should inspect and edit the default prefixes, for both brevity and clarity.
            </p>

            <h5>REDCap field name considerations</h5>

            <p>
                As you design a project, you should be aware of any field name length limits 
                imposed by the statistical package(s) that will process the exported data.
                For example, SAS 9.4 imposes a 32-character limit on variable names.
            </p>

            <p>
                You may leave this help panel open as you use the Yes3 Exporter. 
                Grab it on the top row to drag it out of the way.
            </p>

        </div> <!-- panel row -->

    </div> <!-- scrolling container -->
    
    <div class='yes3-panel-got-it' id='yes3-help-panel-got-it'>
        <label class="yes3-checkmarkContainer  yes3-semibold">
        <input type="checkbox" name="yes3-got-it" value="1" onclick="YES3.Help_setGotIt()">
        <span class="yes3-checkmark" data-bs-toggle="tooltip" data-bs-html="true" data-bs-placement="top" title="If checked, this 'getting started' panel will not be automatically displayed when the editor is opened. Uncheck to re-enable automatic display."></span>
        Do NOT display this panel automatically when the Export ArmsEvent Prefix Editor is opened.
        </label>
    </div>

</div>

<div class="yes3-container" id="yes3-container" style="padding-right: 20px;">

    <div class="yes3-flex-container-vbaseline yes3-fmapr-controls">

        <div class="yes3-flex-col-33 yes3-flex-hleft yes3-ellipsis">
            <span class="yes3-title">YES3</span> <span class="yes3-subtitle">Exporter II <span class="yes3-semibold">Prefixes</span></span>
        </div>

        <div class="yes3-flex-col-33 yes3-flex-hleft yes3-ellipsis">

            <div id="yes3-message"></div>

        </div>

        <div class="yes3-flex-col-33 yes3-flex-vcenter-hright">

            <i class="far fa-save yes3-action-icon yes3-action-icon-controlpanel yes3-designer-only yes3-save-control" id="yes3-fmapr-save-control" action="Exportspecifications_saveSettings" title="Save all settings on this page."></i>
            <i class="fas fa-undo yes3-action-icon yes3-action-icon-controlpanel yes3-fmapr-display-when-dirty yes3-designer-only" action="Exportspecifications_undoSettings" title="Restore all settings on this page to their stored values (undo)."></i>
            <i class="fas fa-question yes3-action-icon yes3-action-icon-controlpanel" action="Help_openPanel" title="get some help"></i>

            <i class="fas fa-book-reader yes3-action-icon yes3-action-icon-controlpanel yes3-tooltip-static" action="Open_docPage" data-bs-toggle="tooltip" title="Open the YES3 Exporter2 online documentation."></i>

            <i class="fas fa-moon yes3-action-icon yes3-action-icon-controlpanel yes3-light-theme-only" action="Theme_dark" title="Switch to the dark side"></i>
            <i class="fas fa-sun yes3-action-icon yes3-action-icon-controlpanel yes3-dark-theme-only" action="Theme_light" title="Switch to the sunny side"></i>

            <img class="yes3-square-logo yes3-logo" alt="YES3 Logo" title="More about YES3..." />

        </div>

    </div>

    <!-- **** FIELD MAPPER SETUP **** -->

    <div class="row yes3-fmapr">

        <div class="col-lg-2">&nbsp;</div>

        <div class="col-lg-8 yes3-fmapr-longitudinal-only yes3-fmapr-setup-settings">

            <div class="yes3-information">
                <h1>Arm/Event prefixes</h1>
                <p>
                For horizontal export layouts the YES3 Exporter attaches arm/event prefixes to variable names.
                </p>
                <p>
                Keep the prefixes as short as you can manage. 
                </p>
                <p>
                    Click <a href="javascript:FMAPR.restoreToDefaultValues()">here</a> to restore your event prefixes to their default values.
                </p>
            </div>

            <table id="yes3-fmapr-setup-events" class="yes3-fmapr yes3-fmapr-specification yes3-fmapr-item yes3-dashboard yes3-editor">

                <thead>

                    <tr class='yes3-fmapr-event-prefixes-header'>
                        <th>Event</th>
                        <th>Prefix</th>
                    </tr>

                </thead>

                <tbody>


                </tbody>

            </table>

        </div>

        <div class="col-lg-2">&nbsp;</div>

    </div>

</div> <!-- container -->


<script>

    (function(){



    })

</script>




