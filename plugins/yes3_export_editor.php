<?php

namespace Yale\Yes3Exporter;

//use HtmlPage;


ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);


$module = new Yes3Exporter();

$csrf_token = $module->getCSRFToken();

$copy = $module->getCopyright();

$default_sascode_libref = $module->getProjectSetting('sascode-libref');
$default_sascode_libref_path = $module->getProjectSetting('sascode-libref-path');
$default_sascode_dsname = $module->getProjectSetting('sascode-dsname') ?? "yes3_exporter";
$enable_host_filesystem_exports = $module->getProjectSetting('enable-host-filesystem-exports') ?? "N";

//$HtmlPage = new HtmlPage();
//$HtmlPage->ProjectHeader();

//$module->getCodeFor("yes3_export_editor", true);

?>

<!DOCTYPE html>
<html>
<head>

    <title>YES3 Exporter II</title>

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

<?php $module->getCodeFor('yes3_export_editor', true, true) ?>

<script>

    // project defaults that probably apply to this export
    FMAPR.defaults = {
        "export_sascode_libref": "<?= $default_sascode_libref ?>",
        "export_sascode_libref_path": "<?= $default_sascode_libref_path ?>"
    };

    FMAPR.enable_host_filesystem_exports = "<?= $enable_host_filesystem_exports ?>";

</script>

<!-- WARNINGS/ERROR REPORT -->
 
<div id="yes3-fmapr-error-report" class="yes3-panel yes3-help-panel yes3-draggable" style="display:none">

    <div class="yes3-panel-header-row">
        <div class="yes3-panel-row-left" id="yes3-help-panel-title">
            Export specification errors
        </div>
        <div class="yes3-panel-row-right">
            <a href="javascript: FMAPR.ErrReport_closePanel()"><i class="fas fa-times fa-x"></i></a>
        </div>
    </div>

    <div class='yes3-panel-row'>
        <div id='yes3-fmapr-error-report-title'></div>
        <div id="yes3-fmapr-error-report-content"></div>
        <div id="yes3-fmapr-error-report-note"></div>
    </div>

</div>

<!-- HELP -->

<div id="yes3-help-panel" class="yes3-panel yes3-help-panel yes3-draggable" style="display:none">

    <div class="yes3-panel-header-row">
        <div class="yes3-panel-row-left" id="yes3-help-panel-title">
            Here's some help
        </div>
        <div class="yes3-panel-row-right">
            <a href="javascript: YES3.Help_closePanel()"><i class="fas fa-times fa-x"></i></a>
        </div>
    </div>

    <div class='yes3-panel-row'>
        Access to YES3 Export Editor features is through clicking on 'action icons'.
        Each action icon is listed below, along with the feature it invokes.
    </div>

   <div class="yes3-panel-row yes3-duck" >
        <table>
            <tbody>

                <tr>
                    <td>
                        <i class="fas fa-list-alt yes3-action-icon yes3-nohandler"></i>
                    </td>
                    <td>
                        Add a new export specification.
                    </td>
                </tr>

                <tr>
                    <td>
                        <i class="fas fa-plus yes3-action-icon yes3-nohandler"></i>
                    </td>
                    <td>
                        Open the bulk insertion tool, which will allow you to insert or append one or more form or field items.
                    </td>
                </tr>
                
                <tr>
                    <td>
                        <i class="far fa-save yes3-action-icon yes3-nohandler"></i>
                    </td>
                    <td>
                        Save the export specification (no unsaved changes).
                    </td>
                </tr>
                
                <tr>
                    <td>
                        <i class="far fa-save yes3-action-icon yes3-dirty yes3-nohandler"></i>
                    </td>
                    <td>
                    Save the export specification (unsaved changes detected).
                    </td>
                </tr>
                
                <tr>
                    <td>
                        <i class="fas fa-undo yes3-action-icon yes3-nohandler"></i>
                    </td>
                    <td>
                        Restore the export specification to a prior version.
                    </td>
                </tr>
                
                <tr>
                    <td>
                        <i class="fas fa-download yes3-action-icon yes3-nohandler"></i>
                    </td>
                    <td>
                        Download the data dictionary and/or data spreadsheet.
                    </td>
                </tr>
                
                <tr>
                    <td>
                        <i class="fas fa-file-export yes3-action-icon yes3-nohandler"></i>
                    </td>
                    <td>
                        Export data dictionary and data spreadsheet to the file system (if configured; requires super user and IT support).
                    </td>
                </tr>

                <tr>
                    <td>
                        <i class="far fa-question-circle yes3-action-icon yes3-nohandler"></i>
                    </td>
                    <td>
                        Display a Help panel (like this one).
                    </td>
                </tr>

                <tr>
                    <td>
                        <i class="fas fa-book-reader yes3-action-icon yes3-nohandler"></i>
                    </td>
                    <td>
                        Open the YES3 Exporter online documentation.
                    </td>
                </tr>
                            
                <tr class="yes3-light-theme-only--xxx">
                    <td>
                        <i class="fas fa-moon yes3-action-icon yes3-nohandler"></i>
                    </td>
                    <td>
                        Switch to dark theme.
                    </td>
                </tr>   
                
                <tr class="yes3-dark-theme-only--xxx">
                    <td>
                        <i class="fas fa-sun yes3-action-icon yes3-nohandler"></i>
                    </td>
                    <td>
                        Switch to light theme.
                    </td>
                </tr>   
                
            </tbody>
        </table>
   </div>

   <div class='yes3-panel-row'>
        At the end of the Export Item list is a 'QUICK ADD' form that you may use to append a single study form or field to the item list.
        It lacks the more advanced options available on the bulk insertion tool.
   </div>

   <div class='yes3-panel-row'>
        <span class='yes3-information-em'>Need more help?</span> 
            Click <a href="javascript:YES3.Help_openReadMe();">here</a> for the Yes3 Exporter documentation.
    </div>

    <!--div class='yes3-panel-row'>
        Click <a href="javascript:YES3.Help_openChangeLog();">here</a> to view the development change log.
    </div-->

    <div class='yes3-panel-row'>
        You may leave this help panel open as you use the Yes3 Exporter. Grab it on the top row to drag it out of the way.
    </div>

    <div class='yes3-panel-row yes3-help-panel-got-it'>
        <label class="yes3-checkmarkContainer">
            <input type="checkbox" name="yes3_got_it" onclick="YES3.Help_setGotIt()">
            <span class="yes3-checkmark"></span>Got it! Do not automatically display this panel again.
        </label>
    </div>
   
</div>

<!-- FORM INSERTION HELP -->

<div id="yes3-fmapr-form-insertion-help-panel" class="yes3-panel yes3-help-panel yes3-draggable" style="display:none">

    <div class="yes3-panel-header-row">
        <div class="yes3-panel-row-left">
            Adding Forms to the Export Specification
        </div>
        <div class="yes3-panel-row-right">
            <a href="javascript: FMAPR.closeHelpFormInsertionForm()"><i class="fas fa-times fa-x"></i></a>
        </div>
    </div>

    <div class="yes3-information ">

    <p class="yes3-fmapr-crossectional-only">
            Use this panel to add one (or all) forms to the export.
        </p>

        <p class="yes3-fmapr-longitudinal-only">
            Use this panel to add one (or all) forms to the export, and also to specify the events for which form data should be exported.
        </p>

        <p class="yes3-fmapr-crossectional-only">
            By default, all forms are pre-selected. 
            Instead of all forms, you may select a specific form to add to the export.
        </p>

        <p class="yes3-fmapr-longitudinal-only">
            By default, all forms and events are pre-selected.
            Instead of all forms and events, you may select event(s) for a specific form, or form(s) for a specific event to add to the export.
        </p>

        <p class="yes3-fmapr-longitudinal-only">
            <span class="yes3-information-em">Selecting events(s) for a specific form:</span>
            To select one (or all) events for a specific form, do the following:
            <ol>
                <li><span class="yes3-information-em-light">Make sure the event selection is "all events"</span> (it will be if this panel was just opened).</li>
                <li><span class="yes3-information-em-light">Select a specific form.</span> This will repopulate the event selector with those events to which the selected form as been assigned.</li>
                <li><span class="yes3-information-em-light">Select the event</span>, or leave as "all events" to add all events for the selected form.</li>
            </ol>
        </p>

        <p class="yes3-fmapr-longitudinal-only">
            <span class="yes3-information-em">Selecting form(s) for a specific event:</span>
            To select one (or all) forms for a specific event, do the following:
            <ol>
                <li><span class="yes3-information-em-light">Make sure the form selection is "all forms"</span> (it will be if this panel was just opened).</li>
                <li><span class="yes3-information-em-light">Select the specific event.</span> This will repopulate the form selector with just those forms assigned to the event.</li>
                <li><span class="yes3-information-em-light">Select the form</span>, or leave as "all forms" to add all forms assigned to the selected event.</li>
            </ol>
        </p>

        <p>
            <span class="yes3-information-em">Insert options: </span>
            You will have up to three options for adding your selection to the Export.
            <ol>
            <li><span class="yes3-information-em-light">As a single item.</span>
                    The selection will be entered into the Export specs as a single form/event item, e.g. "MMSE , 3 month followup".
                </li>
                <li><span class="yes3-information-em-light">As forms.</span>
                    The selection will be entered into the Export specs as one item per form.
                    You might select this option if you intend to (1) remove one or more form items after insertion, which might be easier than adding 
                    each form individually; or (2) you intend to rearrange the form order after insertion.
                </li>
                <li><span class="yes3-information-em-light">As fields.</span>
                    The selection will be entered into the Export specs as one item per field.
                    You might select this option if you intend to remove or rearrange fields after insertion.
                </li>
            </ol>
        </p>

    </div>
</div>

<!-- SASCODE HELP -->

<div id="yes3-fmapr-sascode-help-panel" class="yes3-panel yes3-help-panel yes3-draggable" style="display:none">

    <div class="yes3-panel-header-row">
        <div class="yes3-panel-row-left">
            SAS Code Generation
        </div>
        <div class="yes3-panel-row-right">
            <a href="javascript: FMAPR.closeHelpSasCodeForm()"><i class="fas fa-times fa-x"></i></a>
        </div>
    </div>

    <div class="yes3-information yes3-legroom">
        You may choose to have the Exporter generate execution-ready SAS code to input the data from the Export specification into a SAS dataset.
    </div>

    <h5>Generated files</h5>

    <div class="yes3-information">
        The following code files will be included in download packages as well as file system exports.
    </div>

    <table class="yes3-fmapr-panel-table yes3-headroom yes3-legroom">
        <tr>
            <td class="propvalue"><span name='export_code_filename_base'>[export name]</span>_input.sas</td>
            <td>
                A SAS code file that will read the exported tsv data file to create a permanent SAS dataset.
            </td>
        </tr>
        <tr>
            <td class="propvalue"><span name='export_code_filename_base'>[export name]</span>_create_formats.sas</td>
            <td>
                A SAS code file that will create (or update) a permanent SAS format library containing PROC FORMAT code for all fields having choice sets (radio buttons, dropdown menus).
                One format is defined for each unique choice set among the fields in the Export specification.
            </td>
        </tr>
        <tr>
            <td class="propvalue"><span name='export_code_filename_base'>[export name]</span>_assign_formats.sas</td>
            <td>
                A SAS code file that will assign the formats created in the format library to the corresponding variables in the SAS dataset.
                Code from this file may be copied and pasted as needed for SAS programs that use the dataset.
            </td>
        </tr>
    </table>

    <h5>Required fields</h5>

    <div class="yes3-information yes3-headroom">
        The following items are required to generate the SAS LIBNAME statement, which specifies where your SAS datasets will be written. 
    </div>

    <table class="yes3-fmapr-panel-table yes3-table-borders yes3-headroom yes3-legroom">
        <tr>
            <td class="propvalue">Library&nbsp;reference&nbsp;(libref)&nbsp;</td>
            <td>
                Enter a SAS libref (1-8 characters). 
                Must start with a letter or underscore and contain only letters, digits, or underscores. 
                Used to reference the data folder in your SAS code.
                Typically this is an abbreviation of your project name. 
            </td>
        </tr>
        <tr>
            <td class="propvalue">Library&nbsp;path</td>
            <td>
                Enter the full path to the folder containing your SAS datasets (e.g., \\storage.yale.edu\myshare\myproject). 
                This will be assigned to the libref in your generated code.
            </td>
        </tr>
    </table>

    <h5>Default settings</h5>

    <div class="yes3-information yes3-headroom yes3-legroom">
        Typically, the same libref and SAS dataset folder is used for all SAS datasets for a project.
        Accordingly, you may enter the default libref and SAS dataset folder for all project export specifications 
        into the YES3 Exporter External Module settings.
        These defaults will automatically populate exports, but may be overridden for individual export specifications.
    </div>

    <div class="yes3-information-em yes3-headroom yes3-legroom">
        Note: the generated SAS code uses the observed maximum lengths of REDCap field values to define the lengths of SAS character variables.
        In this way, the generated code will not truncate any data and the SAS dataset will be as compact as possible.
        We recommend that you regenerate and execute the updated SAS code each time you export data, as the maximum value lengths may change over time as you add or modify REDCap data.
    </div>

</div>

<!-- CRITERION VALUE HELP -->

<div id="yes3-fmapr-criterion-value-help-panel" class="yes3-panel yes3-help-panel yes3-draggable" style="display:none">

    <div class="yes3-panel-header-row">
        <div class="yes3-panel-row-left">
            Selection criterion: value(s)
        </div>
        <div class="yes3-panel-row-right">
            <a href="javascript: FMAPR.closeHelpCriterionValueForm()"><i class="fas fa-times fa-x"></i></a>
        </div>
    </div>

    <div class="yes3-information">
        <p>
            This entry is used to select records based on the value of the criterion field.
        </p>
        <p>
            You may enter a single value, in which case only records having this value will be downloaded and/or exported.
        </p>
        <p>
            You may also enter a <em>criterion expression</em> to select based on a range of values.
            <br>Here are examples of valid criterion expression syntax: 
        </p>
        <table class="yes3-fmapr-panel-table yes3-table-borders">
            <tr><td class="propvalue">1        </td><td>Value must be 1.</td></tr>
            <tr><td class="propvalue">= 1      </td><td>Value must be 1 (alternate syntax).</td></tr>
            <tr><td class="propvalue">3,1,4,5,9</td><td>Value must be 3, 1, 4, 5 or 9.</td></tr>
            <tr><td class="propvalue">< 10     </td><td>Value must be less than 10 (numeric comparison).</td></tr>
            <tr><td class="propvalue"><= 10    </td><td>Value must be less than or equal to 10 (numeric comparison).</td></tr>
            <tr><td class="propvalue">> 10     </td><td>Value must be greater than 10 (numeric comparison).</td></tr>
            <tr><td class="propvalue">>= 10    </td><td>Value must be greater than or equal to 10 (numeric comparison).</td></tr>
            <tr><td class="propvalue"><> 10    </td><td>Value must be not equal to 10.</td></tr>
            <tr><td class="propvalue">apple, table, penny</td><td>Value must be 'apple', 'table' or 'penny'.</td></tr>
            <tr><td class="propvalue">>= 1952-06-25</td><td>Value must be June 25th, 1952 or later (note: you must use the date format yyyy-mm-dd).</td></tr>
            <tr><td class="propvalue"><></td><td>Value must be nonblank.</td></tr>
        </table>

    </div>

    <div class="yes3-panel-bottom-row yes3-panel-row-border-top yes3-headroom">
        <p class="yes3-headroom">
            <span class="yes3-information-em-light">Criterion field properties</span>: below is a table of REDCap properties for the criterion field you have selected.
        </p>

        <table class='yes3-fmapr-panel-table'>
            <tbody>
        
                <tr property="field_name">
                    <td>
                        REDCap&nbsp;field&nbsp;name
                    </td>
                    <td class="propvalue"></td>
                </tr>
       
                <tr property="field_label">
                    <td>
                        REDCap&nbsp;field&nbsp;label
                    </td>
                    <td class="propvalue"></td>
                </tr>
                 
                <tr property="field_type" class="yes3-fmapr-criterion-field-defined">
                    <td>
                        REDCap&nbsp;field&nbsp;type
                    </td>
                    <td class="propvalue"></td>
                </tr>
                 
                <tr property="field_valueset" class="yes3-fmapr-criterion-field-defined yes3-fmapr-nominal">
                    <td>
                        Values
                    </td>
                    <td class="propvalue">
                        <table class="yes3-scrollable yes3-fmapr-help-valueset">
                            <tbody style="height:8rem" >

                            </tbody>
                        </table>
                    </td>
                </tr>

            </tbody>
        </table>

    </div>

   <!--div class='yes3-panel-row'>
        <span class='yes3-information-em'>Need more help?</span> 
            Click <a href="javascript:YES3.Help_openReadMe();">here</a> for the Yes3 Exporter documentation.
    </div-->

</div>

<!-- WAYBACK -->

<div id="yes3-fmapr-wayback-panel" class="yes3-panel yes3-panel-small yes3-draggable" style="display:none">

   <div class="yes3-panel-header-row">
      <div class="yes3-panel-row-left" id="yes3-fmapr-wayback-panel-title">
         Wayback Machine
      </div>
      <div class="yes3-panel-row-right">
         <a href="javascript: FMAPR.Wayback_closeForm()"><i class="fas fa-times fa-x"></i></a>
      </div>
   </div>

   <div class="yes3-panel-row yes3-duck" >
        <select id="yes3-fmapr-wayback-select" class="yes3-select" onchange="FMAPR.Wayback_Buttons();"></select>
   </div>

    <div class="yes3-panel-row">

        <div class="yes3-flex-container-right-aligned">

            <div class="yes3-flex-vcenter-hright">
                <input type="button" onClick="FMAPR.Wayback_closeForm();" class="yes3-panel-button yes3-button-caption-cancel" />
            </div>

            <div class="yes3-flex-vcenter-hright">
                <input type="button" onClick="FMAPR.Wayback_Execute();" id="yes3-fmapr-wayback-restore" class="yes3-panel-button yes3-button-caption-restore" />
            </div>

        </div>

   </div>

</div>

<!-- EXPORT ITEM EDITOR -->

<div id="yes3-fmapr-item-editor-panel" class="yes3-panel yes3-panel-small yes3-draggable" style="display:none;width:600px">

    <div class="yes3-panel-header-row">
        <div class="yes3-panel-row-left" id="yes3-fmapr-fieldinsertion-panel-title">
            Export Item Editor
        </div>
        <div class="yes3-panel-row-right">
            <a href="javascript: FMAPR.closeItemEditorForm()"><i class="fas fa-times fa-x"></i></a>
        </div>
    </div>

    <input type="hidden" name="yes3_fmapr_data_element_name" />
    <input type="hidden" name="row_number" />
    <input type="hidden" name="mode" /> <!-- edit, insert or append -->

    <div class="yes3-panel-row yes3-duck">
        <table class="yes3-fmapr-editor">            
            <tr>
                <td>REDCap object type</td>
                <td>
                    <input type="radio" class="balloon" value="form" name="object_type" id="yes3-fmapr-redcap-object-type-form">
                    <label for="yes3-fmapr-redcap-object-type-form" title="Display the export settings form">Form</label>

                    <input type="radio" class="balloon" value="field" name="object_type" id="yes3-fmapr-redcap-object-type-field">
                    <label for="yes3-fmapr-redcap-object-type-field" title="Display the forms and fields to be exported">Field</label>
                </td>
            </tr>
                
            <tr class="yes3-longitudinal-only yes3-hide-on-open">
                <td>Select the EVENT</td>
                <td>
                    <select name="object_event" id="yes3-fmapr-item-editor-event"></select>
                </td>
            </tr>
            
            <tr class="yes3-fmapr-form-only yes3-hide-on-open">
                <td>Select the FORM</td>
                <td>
                    <select name="redcap_form" id="yes3-fmapr-item-editor-form_name"></select>
                </td>
            </tr>
            
            <tr class="yes3-fmapr-field-only yes3-hide-on-open">
                <td>Select the FIELD</td>
                <td>
                    <input type="text" name="redcap_field" id="yes3-fmapr-item-editor-field_name" placeholder="start typing or spacebar for all" />
                </td>
            </tr>
            
            <tr class="yes3-fmapr-form-only yes3-hide-on-open">
                <td>Insert as</td>
                <td>
                    <input type="radio" class="balloon" value="item" name="insert_as" id="yes3-fmapr-insert-as-item">
                    <label for="yes3-fmapr-insert-as-item" title="Insert as a single item">a single export item</label>

                    <br class="yes3-fmapr-all-forms-indicated">
                    <input type="radio" class="balloon yes3-fmapr-all-forms-indicated" value="forms" name="insert_as" id="yes3-fmapr-insert-as-forms">
                    <label for="yes3-fmapr-insert-as-forms" class="yes3-fmapr-all-forms-indicated" title="Insert as individual forms">one export item per form</label>

                    <br>
                    <input type="radio" class="balloon" value="fields" name="insert_as" id="yes3-fmapr-insert-as-fields">
                    <label for="yes3-fmapr-insert-as-fields" title="Insert as individual fields">one export item per field</label>
                </td>
            </tr>
        </table>
    </div>
    <div class="yes3-flex-container-evenly-distributed">

        <div class="yes3-flex-vcenter-hleft" id="yes3-fmapr-item-editor-mode">

        </div>

        <div class="yes3-flex-vcenter-hright">

            <div class="yes3-flex-container-right-aligned">

                <div class="yes3-flex-vcenter-hright">
                    <input type="button" class="yes3-panel-button yes3-button-caption-cancel" />
                </div>

                <div class="yes3-flex-vcenter-hright">
                    <input type="button" class="yes3-panel-button yes3-button-caption-save yes3-save-button" />
                </div>

            </div>

        </div>

    </div>

</div>

<!-- **** MAIN CONTENT CONTAINER **** -->

<div class="yes3-container" id="yes3-container">

    <div class="yes3-flex-container-vbaseline yes3-fmapr-controls">

        <div class="yes3-flex-col-33 yes3-flex-hleft yes3-ellipsis">
            <span class="yes3-fmapr-title">YES3</span> <span class="yes3-fmapr-subtitle">Exporter II <span class="yes3-semibold">Editor</span></span>
        </div>

        <div class="yes3-flex-col-33 yes3-flex-hleft yes3-ellipsis">

            <span class="yes3-fmapr-subtitle yes3-fmapr-export-name">Hang on there, friend</span>

        </div>

        <div class="yes3-flex-col-33 yes3-flex-hright">

            <i class="far fa-save yes3-action-icon yes3-action-icon-controlpanel yes3-loaded yes3-designer-only yes3-save-control" id="yes3-fmapr-save-control" action="saveExportSpecification" title="Save the export specification."></i>

            <i class="fas fa-download yes3-action-icon yes3-action-icon-controlpanel yes3-loaded yes3-designer-only yes3-display-when-clean" id="yes3-fmapr-download-control" action="download" title="Download."></i>

                <i class="fas fa-file-export yes3-action-icon yes3-action-icon-controlpanel yes3-loaded yes3-designer-only yes3-display-when-clean yes3-fmapr-export-options" id="yes3-fmapr-export-control" action="export" title="Export to file system."></i>
                
                <i class="fas fa-undo yes3-action-icon yes3-action-icon-controlpanel yes3-loaded yes3-designer-only" action="Wayback_openForm" title="Restore the specification from a stored backup."></i>
    
                <i class="far fa-question-circle yes3-action-icon yes3-action-icon-controlpanel" action="Help_openPanel" title="Get some help"></i>
                <!--i class="fas fa-book-reader yes3-action-icon yes3-action-icon-controlpanel" action="Open_docPage" title="Open the YES3 Exporter documentation page"></i-->

                <i class="fas fa-moon yes3-action-icon yes3-action-icon-controlpanel yes3-light-theme-only" action="Theme_dark" title="Switch to the dark side"></i>
                <i class="fas fa-sun yes3-action-icon yes3-action-icon-controlpanel yes3-dark-theme-only" action="Theme_light" title="Switch to the sunny side"></i>

                <!--img class="yes3-square-logo yes3-logo" alt="YES3 Logo" title="More about YES3..." /-->

        </div>

    </div>

    <!--  DASHBOARD HEADER -->

    <div class="yes3-dashboard-head yes3-flex-container-vcenter" id="yes3-fmapr-dashboard-head" style="display:none">

        <div class="yes3-flex-hleft yes3-flex-col-33 yes3-dashboard-options">

            <span class="yes3-semibold">EDIT:&nbsp;&nbsp;</span>

            <input type="radio" class="balloon" value="settings" name="yes3-dashboard-options" id="yes3-dashboard-option-settings" onclick="FMAPR.dashboardOptionHandler()">
            <label for="yes3-dashboard-option-settings" title="Display the export settings form">Settings</label>&nbsp;

            <input type="radio" class="balloon" value="items" name="yes3-dashboard-options" id="yes3-dashboard-option-items" onclick="FMAPR.dashboardOptionHandler()">
            <label for="yes3-dashboard-option-items" title="Display the forms and fields to be exported">Items</label>&nbsp;

        </div>

        <div id="yes3-export-editor-warning" class="yes3-flex-hleft yes3-flex-col-33 yes3-warning yes3-ellipsis"></div>

        <div class="yes3-flex-hright yes3-flex-col-33 yes3-dashboard-title yes3-ellipsis"></div>

    </div>

    <!-- **** SETTINGS FORM **** -->

    <div id="yes3-fmapr-settings" class="yes3-expanded">

        <div class="row yes3-fmapr yes3-editor">

            <div class="col-xl-8">

                <table id="yes3-fmapr-settings-1" name="yes3-fmapr-settings" class="yes3-fmapr-settings yes3-full-width">

                <tr>
                        <td>Export name:</td>
                        
                        <td>
                            <input type="text"   name="export_name" data-setting="export_name" value="" class="yes3-full-width" placeholder="enter an export name">
                            <input type="hidden" name="export_uuid" data-setting="export_uuid" value="" />
                            <input type="hidden" name="export_layout" data-setting="export_layout" value="" />
                            <input type="hidden" name="removed"     data-setting="removed"     value="0" />
                        </td>                    
                    </tr>

                    <tr>
                        <td>Description:</td>
                        <td>
                            <textarea name="export_label" data-setting="export_label" value="" class="yes3-optional yes3-full-width" placeholder="enter a brief description"></textarea>
                        </td>
                    </tr>

                    <tr>
                        <td>Layout:</td>
                        
                        <td class="yes3-fmapr-export-layout-text">
                        </td>                    
                    </tr>

                    <tr>
                        <td>Multi-select values exported as:</td>

                        <td class="yes3-fmapr-export-specification yes3-fmapr-layout-options">

                            <input type="radio" class="balloon" value="1" name="export_multiselect" data-setting="export_multiselect" id="yes3-fmapr-export_multiselect-1">
                            <label for="yes3-fmapr-export_multiselect-1" title="Export as multiple columns (one per option, each having 1=selected, 0=not selected values)">Multiple columns</label>&nbsp;

                            <input type="radio" class="balloon" value="2" name="export_multiselect" data-setting="export_multiselect" id="yes3-fmapr-export_multiselect-2">
                            <label for="yes3-fmapr-export_multiselect-2" title="Export as a single column (a list of selected option values)">Single column</label>&nbsp;

                        </td>                                  
                    </tr>

                </table>

                <div class="yes3-fmapr-settings-section">
                    Options for selecting records to export
                    <i class="far fa-question-circle yes3-action-icon yes3-action-icon-inline-large" action="Help_criterionValue" title="Guidance for entering the criterion value expression."></i>
                </div>

                <table class="yes3-fmapr-settings" name="yes3-fmapr-settings">

                    <tr>

                        <td class="yes3-fmapr-export-specification">Include:</td>

                        <td class="yes3-fmapr-export-specification yes3-fmapr-layout-options">

                            <input type="radio" class="balloon" value="1" name="export_selection" data-setting="export_selection" id="yes3-fmapr-export-selection-1">
                            <label for="yes3-fmapr-export-selection-1" title="Include all records">All records</label>&nbsp;

                            <input type="radio" class="balloon" value="2" name="export_selection" data-setting="export_selection" id="yes3-fmapr-export-selection-2">
                            <label for="yes3-fmapr-export-selection-2" title="Selected records">Selected records</label>&nbsp;

                        </td>               
                    </tr>

                    <tr class="yes3-fmapr-if-selected yes3-fmapr-skipped-over">

                        <td class="yes3-fmapr-export-specification">Selection criterion: field</td>

                        <td class="yes3-fmapr-export-specification">
                            <input type="text" name="export_criterion_field" data-setting="export_criterion_field" id="export_criterion_field" value="" placeholder="start typing...">
                        </td>                   
                    </tr>

                    <tr class="yes3-fmapr-if-selected yes3-fmapr-longitudinal-only yes3-fmapr-skipped-over yes3-fmapr-select-event">

                        <td class="yes3-fmapr-export-specification">Selection criterion: event</td>

                        <td class="yes3-fmapr-export-specification">
                            <select name="export_criterion_event" data-setting="export_criterion_event" id="export_criterion_event" class="yes3-fmapr-select-event" placeholder="select an event"></select>
                        </td>
                    </tr>

                    <tr class="yes3-fmapr-if-selected yes3-fmapr-skipped-over">

                        <td class="yes3-fmapr-export-specification">Selection criterion: value(s)</td>

                        <td class="yes3-fmapr-export-specification">
                            <input type="text" name="export_criterion_value" data-setting="export_criterion_value" class="yes3-input-integer" placeholder="value">
                        </td>
                    </tr>

                </table>
                
                <div class="yes3-fmapr-settings-section">
                    Options for data compliance (de-identified and coded datasets)
                </div>

                <div class="yes3-fmapr yes3-flex-container">

                    <div class="yes3-flex-left">
                            
                        <div class="yes3-fmapr-filter-option yes3-fmapr-settings-block">
                            <label class="yes3-checkmarkContainer">
                                <input type="checkbox" name="export_remove_phi" data-setting="export_remove_phi" value="1" />
                                <span class="yes3-checkmark"></span>Remove tagged identifiers
                            </label>
                        </div>

                        <div class="yes3-fmapr-filter-option yes3-fmapr-settings-block">
                            <label class="yes3-checkmarkContainer">
                                <input type="checkbox" name="export_remove_dates" data-setting="export_remove_dates" value="1" />
                                <span class="yes3-checkmark"></span>Remove date/time fields
                            </label>
                        </div>
                    </div>

                    <div class="yes3-flex-left">
        
                        <div class="yes3-fmapr-filter-option yes3-fmapr-settings-block">
                            <label class="yes3-checkmarkContainer">
                                <input type="checkbox" name="export_remove_freetext" data-setting="export_remove_freetext" value="1" />
                                <span class="yes3-checkmark"></span>Remove all freetext fields
                            </label>
                        </div>

                        <div class="yes3-fmapr-filter-option yes3-fmapr-settings-block">
                            <label class="yes3-checkmarkContainer">
                                <input type="checkbox" name="export_remove_largetext" data-setting="export_remove_largetext" value="1" />
                                <span class="yes3-checkmark"></span>Remove note/paragraph fields
                            </label>
                        </div>
                    </div>

                    <div class="yes3-flex-left">
                        
                        <div class="yes3-fmapr-filter-option yes3-fmapr-settings-block">
                            <label class="yes3-checkmarkContainer">
                                <input type="checkbox" name="export_shift_dates" data-setting="export_shift_dates" value="1" />
                                <span class="yes3-checkmark"></span>Coded (shifted) dates
                            </label>
                        </div>
                        
                        <div class="yes3-fmapr-filter-option yes3-fmapr-settings-block">
                            <label class="yes3-checkmarkContainer">
                                <input type="checkbox" name="export_hash_recordid" data-setting="export_hash_recordid" value="1" />
                                <span class="yes3-checkmark"></span>Coded (hashed) record id values
                            </label>
                        </div> 
                                
                    </div>

                </div>

                <div class="yes3-fmapr-settings-section">
                    Options for conditioning exported data and data dictionaries
                </div>

                <div class="yes3-fmapr yes3-flex-container">

                    <!--div class="yes3-flex-left">

                        <div class="yes3-fmapr-filter-option yes3-fmapr-settings-block">
                            <label class="yes3-checkmarkContainer">
                                <input type="checkbox" name="export_inoffensive_text" data-setting="export_inoffensive_text" value="1" />
                                <span class="yes3-checkmark"></span>Remove unprintable characters
                            </label>
                        </div>

                        <div class="yes3-fmapr-filter-option yes3-fmapr-settings-block yes3-fmapr-sanitize-option">
                            <label class="yes3-checkmarkContainer">
                                <input type="checkbox" name="export_no_tags" data-setting="export_no_tags" value="1" />
                                <span class="yes3-checkmark"></span>Remove HTML tags
                            </label>
                        </div>
                    </div-->

                    <div class="yes3-flex-left">

                        <div class="yes3-fmapr-filter-option yes3-fmapr-settings-block yes3-fmapr-sanitize-option">
                            <label class="yes3-checkmarkContainer">
                                <input type="checkbox" name="export_ascii_text" data-setting="export_ascii_text" value="1" />
                                <span class="yes3-checkmark"></span>ASCII characters only
                            </label>
                        </div>
                                
                    </div>
                </div>

                <table class="yes3-fmapr-settings">

                    <tr>
                        <td class="yes3-fmapr-export-specification">Maximum text value length:</td>

                        <td class="yes3-fmapr-export-specification">
                            <input type="text" name="export_max_text_length" data-setting="export_max_text_length" value="" class="yes3-input-integer yes3-optional" placeholder="max #characters">
                        </td>
                    </tr>

                    <tr>
                        <td class="yes3-fmapr-export-specification">Maximum field label length:</td>

                        <td class="yes3-fmapr-export-specification">
                            <input type="text" name="export_max_label_length" data-setting="export_max_label_length" value="" class="yes3-input-integer yes3-optional" placeholder="max #characters">
                        </td>
                    </tr>
             
                </table>

                <div class="yes3-fmapr-settings-section">
                    Options for SAS code generation
                    <i class="far fa-question-circle yes3-action-icon yes3-action-icon-inline-large" action="Help_sascode" title="Guidance for SAS code generation."></i>
                </div>

                <table class="yes3-fmapr-settings yes3-full-width">

                    <tr>
                        <td class="yes3-fmapr-export-specification" colspan="2">

                            <div class="yes3-fmapr-filter-option yes3-fmapr-settings-block">
                                <label class="yes3-checkmarkContainer">
                                    <input type="checkbox" name="export_sascode" data-setting="export_sascode" value="1" />
                                    <span class="yes3-checkmark"></span>Include SAS code in exports
                                </label>
                            </div>

                        </td>
                    </tr>

                    <tr class="yes3-fmapr-if-sascode">
                        <td class="yes3-fmapr-export-specification" colspan="2">

                            <div class="yes3-fmapr-filter-option yes3-fmapr-settings-block">
                                <label class="yes3-checkmarkContainer">
                                    <input type="checkbox" name="export_sascode_ascii" data-setting="export_sascode_ascii" value="1" />
                                    <span class="yes3-checkmark"></span>ASCII characters only for SAS labels
                                </label>
                            </div>

                        </td>
                    </tr>

                    <tr class="yes3-fmapr-if-sascode">
                        <td class="yes3-fmapr-export-specification">Library reference (libref):</td>
                        <td class="yes3-fmapr-export-specification">
                            <input type="text" name="export_sascode_libref" data-setting="export_sascode_libref" value="" class="yes3-input-text yes3-optional" placeholder="e.g. myproj">
                        </td>
                    </tr>

                    <tr class="yes3-fmapr-if-sascode">
                        <td class="yes3-fmapr-export-specification">Library path:</td>
                        <td class="yes3-fmapr-export-specification">
                            <input type="text" name="export_sascode_libref_path" data-setting="export_sascode_libref_path" value="" class="yes3-optional yes3-full-width" placeholder="e.g. \\storage.yale.edu\myshare\myproject\sasdata">
                        </td>
                    </tr>

                    <tr class="yes3-fmapr-if-sascode">
                        <td class="yes3-fmapr-export-specification">SAS dataset name:</td>
                        <td class="yes3-fmapr-export-specification">
                            <input type="text" name="export_sascode_dsname" data-setting="export_sascode_dsname" value="" class="yes3-input-text yes3-optional" placeholder="e.g. myproj_data">
                        </td>
                    </tr>
             
                </table>
                
            </div> <!-- left section: export settings -->

            <div class="col-xl-4">

                <div class="yes3-fmapr-settings-section yes3-fmapr-export-options">
                    BATCH EXPORT
                </div>

                <div class="yes3-fmapr-filter-option yes3-fmapr-settings-block yes3-fmapr-export-options yes3-max-legroom">
                    <label class="yes3-checkmarkContainer">
                        <input type="checkbox" name="export_batch" data-setting="export_batch" value="1" />
                        <span class="yes3-checkmark"></span>Include in automated (cron) batch exports
                    </label>
                </div>

                <!--div class="yes3-fmapr-filter-option yes3-fmapr-settings-block">
                    <label class="yes3-checkmarkContainer">
                        <input type="checkbox" name="removed" data-setting="removed" id="yes3-export-removed" value="1" onclick="FMAPR.highlightRemovedIfSelected()"/>
                        <span class="yes3-checkmark"></span>REMOVED
                    </label>
                </div-->

                <div class="yes3-fmapr-settings-section">
                    NOTIFICATIONS
                </div>

                <div id="yes3-fmapr-status"></div>
                
                <!-- SYSTEM MESSAGE CONTAINER -->

                <div class="yes3-fmapr-system-message" id="yes3-fmapr-system-message"></div>

            </div> <!-- right section: export options -->

        </div>

    </div> <!-- #yes3-fmapr-settings -->

    <!-- **** EXPORT ITEMS MANAGER **** -->

    <div class="yes3-container yes3-fmapr yes3-divider yes3-designer-only-xxx yes3-editor" id="yes3-fmapr-wrapper">

        <table class='yes3-fmapr yes3-fmapr-specification yes3-fmapr-item yes3-scrollable yes3-dashboard' id='yes3-fmapr-export-items-table'>
            
            <!--thead>

                <tr>
                    <th class='yes3-header colspan="6" yes3-th-title'>&nbsp;&nbsp;Forms and Fields to Export (click <i class="far fa-edit yes3-fmapr-item-editor"></i> to edit)</th>
                </tr>
                
            </thead-->

            <tbody id='yes3-fmapr-export-items-tbody'>

            </tbody>

        </table>

        <!-- NEW ITEM OPTIONS -->

        <div class="yes3-flex-container-left-aligned yes3-divider yes3-designer-only-xxx yes3-editor yes3-dashboard-tail" id="yes3-fmapr-new-item-options">

            <div class="yes3-flex-vcenter-hleft">
                INSERT OR APPEND:
            </div>

            <div class="yes3-flex-vcenter-hleft">
                SOMETHING
            </div>

            <div class="yes3-flex-vcenter-hleft">
                OR
            </div>

            <div class="yes3-flex-vcenter-hleft">
                OTHER
            </div>

        </div>
        
     </div>


    <!-- FOOTER -->

    <div class="yes3-flex-container" id="yes3-fmapr-footer">

        <div class="yes3-flex-col-33 yes3-flex-vcenter-hleft">

            <div id="yes3-fmapr-copyright"><?= $copy ?></div>

        </div>

        <div class="yes3-flex-col-67 yes3-flex-vcenter-hleft">

            <div id="yes3-message"></div>

        </div>

    </div>

    <!--div class="row" id="yes3-fmapr-page-footer">

        <div class="col-xl-4" style="padding-left: 0">

            <div id="yes3-fmapr-copyright"></div>

        </div>

        <div class="col-xl-8" style="padding-left: 0">

            <div id="yes3-fmapr-system-message"></div>

        </div>

    </div-->

    <!--div id='yes3-fmapr-page-footer'>
                
            <div id="yes3-fmapr-copyright"></div>
    </div-->

</div> <!-- container -->

<script>

    (function(){



    })

</script>




