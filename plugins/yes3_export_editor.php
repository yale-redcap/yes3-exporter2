<?php

namespace Yale\Yes3Exporter2;

//use HtmlPage;


ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);


$module = new Yes3Exporter2();

$csrf_token = $module->getCSRFToken();

$copy = $module->getCopyright();

$default_sascode_libref = $module->getProjectSetting('sascode-libref');
$default_sascode_libref_path = $module->getProjectSetting('sascode-libref-path');
$default_sascode_dsname = $module->getProjectSetting('sascode-dsname') ?? "yes3_exporter";
$enable_host_filesystem_exports = $module->getProjectSetting('enable-host-filesystem-exports') ? 1 : 0;

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
    <script src="https://kit.fontawesome.com/ae9d1ced7d.js" crossorigin="anonymous"></script>    

    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/smoothness/jquery-ui.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <!--link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"-->

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

    FMAPR.enable_host_filesystem_exports = <?= $enable_host_filesystem_exports ?>;

</script>

<!-- WARNINGS/ERROR REPORT -->
 
<div id="yes3-fmapr-error-report" class="yes3-panel yes3-help-panel yes3-draggable" style="display:none">

    <div class="yes3-panel-header-row">
        <div class="yes3-panel-row-left" id="yes3-help-panel-title">
            Export specification errors
        </div>
        <div class="yes3-panel-row-right">
            <a href="javascript: FMAPR.ErrReport_closePanel()"><i class="fas fa-times fa-2x"></i></a>
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
            Getting Started with the Export Editor
        </div>
        <div class="yes3-panel-row-right">
            <a href="javascript: YES3.Help_closePanel()"><i class="fas fa-times fa-2x"></i></a>
        </div>
    </div>

    <div class="yes3-scrolling-container yes3-full-width yes3-default-panel-height">

        <div class="yes3-panel-row">
            <h5>Overview</h5>
            <p>
                The Exporter Editor is a tool for designing and editing an <em>export specification</em>.
                An export specification defines the data to be exported from your project, as well as the format of the exported data.
            </p>
            <p>
                This Getting Started panel provides an overview of the Export Editor interface, with particular attention to inline and online help resources.
                You may leave this panel open while working with the Export Editor, or close it and refer to it later as needed.
            </p>
            <h5>How help is organized</h5>
            <p>
                The YES3 Exporter supports a 3-tiered help system:
            </p>
            <ol>
                <li><strong>tooltips</strong>: brief descriptions of buttons and fields, available by hovering over them for 1 second or more with the mouse pointer. Every input field, button, checkbox, or other interactive element has an associated tooltip.</li>
                <li><strong>inline help</strong>: detailed context-specific assistance as you work, available by clicking on 'circled-question mark' icons (  <i class="far fa-question-circle yes3-action-icon yes3-nohandler"></i> ).</li>
                <li><strong>online help</strong>: comprehensive documentation available online, accessible by clicking on the 'book-reader' icon ( <i class="fas fa-book-reader yes3-action-icon yes3-nohandler"></i> ).</li>
            </ol>

            <div class='yes3-panel-row'>
                <h5>Action icons</h5>
                <p>
                Many features are accessed through clicking on 'action icons'.
                Each action icon is displayed below, along with the feature it invokes.
                </p>
            </div>

            <table>
                <tbody>

                    <tr>
                        <td>
                            <i class="far fa-question-circle yes3-action-icon yes3-nohandler"></i>
                        </td>
                        <td>
                            Display a topic-specific inline help panel (see above).
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
                            <i class="fas fa-download yes3-action-icon yes3-nohandler"></i>
                        </td>
                        <td>
                            Download the data dictionary and/or datasheet.
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
                            <i class="fas fa-undo yes3-action-icon yes3-nohandler"></i>
                        </td>
                        <td>
                            Restore the export specification to a prior version.
                        </td>
                    </tr>

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
    
            <h5>Edit mode</h5>
            <p>
                The Export Editor has two editing modes: <strong>Settings</strong> and <strong>Items</strong>.
                The edit mode is set by the EDIT MODE radio buttons at the top left of the Export Editor panel.
            </p>
            <p>
                Export <strong>Settings</strong> mode allows you to configure the export options, while <strong>Items</strong> mode lets you manage the specific items to be exported.
            </p>
            <p>You may switch between edit modes freely as you work.</p>
        </div>

    </div> <!-- yes3-scrolling-container -->

    <div class='yes3-panel-got-it' id='yes3-help-panel-got-it'>
        <label class="yes3-checkmarkContainer  yes3-semibold">
        <input type="checkbox" name="yes3-got-it" value="1" onclick="YES3.Help_setGotIt()">
        <span class="yes3-checkmark" data-bs-toggle="tooltip" data-bs-html="true" data-bs-placement="top" title="If checked, this 'getting started' panel will not be automatically displayed when the editor is opened. Uncheck to re-enable automatic display."></span>
        Do NOT display this panel automatically when the Export Editor is opened.
        </label>
    </div>
   
</div>

<!--- ITEM ROW SELECTOR HELP -->

<div id="yes3-fmapr-row-selector-help-panel" class="yes3-panel yes3-help-panel yes3-draggable" style="display:none">

    <div class="yes3-panel-header-row">
        <div class="yes3-panel-row-left">
            The export item row selector
        </div>
        <div class="yes3-panel-row-right">
            <a href='javascript: YES3.closePanel("yes3-fmapr-row-selector-help-panel")'><i class="fas fa-times fa-2x"></i></a>
        </div>
    </div>

    <div class="yes3-information yes3-scrolling-container yes3-full-width yes3-default-panel-height">

        <p><em>Note: you may leave this information panel open while working with the export item list.</em></p>

        <h6>The row selector is the narrow column to the left of the export item list.
        <br />It is used to select one or more rows for cutting, pasting, dragging or deleting.</h6>

        <ol>
            <li>To select a single row:
                <ul>
                    <li>Click in the row selector of the desired row.</li>
                    <li>The selected row will be highlighted.</li>
                </ul>
            </li>
            <li>To select a contiguous range of rows:
                <ul>
                    <li>Click in the row selector of the first row in the range.</li>
                    <li>Then, while holding down the <strong>Shift</strong> key, click in the row selector of the last row in the range.</li>
                    <li>All rows in the range will be highlighted.</li>
                </ul>
            </li>
            <li>To select multiple non-contiguous rows:
                <ul>
                    <li>Click in the row selector of the first desired row.</li>
                    <li>Then, while holding down the <strong>Ctrl</strong> key (or <strong>Cmd</strong> key on a Mac), click in the row selector of each additional desired row.</li>
                    <li>All selected rows will be highlighted.</li>
                </ul>
            </li>
        </ol>

        <h6>Once one or more rows are selected, the following actions are available:</h6>

        <ol>
            <li>To drag a single selected row to a new location:
                <ul>
                    <li>Click and hold in the row selector of the desired row.</li>
                    <li>Drag the row to the new location and release the mouse button.</li>
                </ul>
            </li>
            <li>To cut the selected row(s):
                <ul>
                    <li>Press <strong>Ctrl&#8594;X</strong>.</li>
                    <li>This will <em>not</em> remove the rows from the table, but it will mark them as cut and available for relocation via pasting.</li>
                </ul>
            </li>

            <li>To paste the cut row(s):
                <ul>
                <li>Click in the row selector of the row <em>above which</em> you want to paste the cut row(s).</li>
                <li>Press <strong>Ctrl&#8594;V</strong>.</li>
                <li>The cut row(s) will be pasted above the selected row.</li>
                </ul>
            </li>

            <li>To delete the selected rows:
                <ul>
                <li>Right-click in the row selector of any of the selected rows to open the cut/paste/delete menu.</li>
                <li>Click <strong>delete</strong>.</li>
                <li>Note: there is no keyboard shortcut for this action.</li>
                </ul>
            </li>

            <li>To clear the selection (deselect all rows):
                <ul>
                <li>Press <strong>Ctrl&#8594;Z</strong>.</li>
                </ul>
            </li>
        </ol>

    </div>
</div>

<!-- FORM INSERTION HELP -->

<div id="yes3-fmapr-form-insertion-help-panel" class="yes3-panel yes3-help-panel yes3-draggable" style="display:none">

    <div class="yes3-panel-header-row">
        <div class="yes3-panel-row-left">
            Adding Forms to the Export Specification
        </div>
        <div class="yes3-panel-row-right">
            <a href="javascript: FMAPR.closeHelpFormInsertionForm()"><i class="fas fa-times fa-2x"></i></a>
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

<!-- MULTISELECT HELP -->

<div id="yes3-fmapr-multiselect-help-panel" class="yes3-panel yes3-help-panel yes3-draggable" style="display:none">

    <div class="yes3-panel-header-row">
        <div class="yes3-panel-row-left">
            Multi-select field handling
        </div>
        <div class="yes3-panel-row-right">
            <a href='javascript: YES3.closePanel("yes3-fmapr-multiselect-help-panel")'><i class="fas fa-times fa-2x"></i></a>
        </div>
    </div>

        <p>REDCap "multi select" fields are checkbox fields that allow the user to select any number of choices from a list of options.</p>
        <p>As for REDCap itself, the YES3 Exporter allows you to export multi-select fields in two ways:</p>

        <ol>
            <li>
                <p>
                As <strong>Multiple Columns</strong>, with one column per choice in the list. In the export, each column will contain a 1 (selected) or <em>blank</em> if not.
                As is typical for checklist data structures, there is no way that we are aware of to distinguish between "not selected" and "missing" for checkbox fields, so we leave it up to the user to interpret the results.
                Each column will be named according to the REDCap convention of appending a triple underscore ("___") and the choice code to the field name.
                </p><p>
                For example, a multi-select field named "fruits" with choices "1, Apple", "2, Banana" and "3, Cherry" would be represented in the export by three columns named:
                "fruits___1", "fruits___2" and "fruits___3".
                </p>
            </li>
            <li>
                <p>
                As a <strong>Single Column</strong>, with the selected choices represented as a comma-separated list of choice codes.
                </p><p>
                For example, if the user selected "Apple" and "Cherry" for the "fruits" field, the value in the export file would be "1,3".
                </p>
            </li>
        </ol>
</div>

<!-- FILE TYPE HELP -->

<div id="yes3-fmapr-export-file-type-help-panel" class="yes3-panel yes3-help-panel yes3-draggable" style="display:none">

    <div class="yes3-panel-header-row">
        <div class="yes3-panel-row-left">
            Export File Type
        </div>
        <div class="yes3-panel-row-right">
            <a href='javascript: YES3.closePanel("yes3-fmapr-export-file-type-help-panel")'><i class="fas fa-times fa-2x"></i></a>
        </div>
    </div>

    <div class="yes3-information yes3-legroom">
        <p>You may choose to have the Exporter generate either tab-delimited (TSV) or comma-delimited (CSV) export files.</p>
        <p>While CSV is a very common format and is the default, you might consider exporting TSV files, for the following reasons:</p>
        <ul>
            <li>TSV files are less likely to contain embedded tabs in text fields than CSV files are to contain embedded commas.</li>
            <li>TSV files do not require double quotes around text fields, which makes them easier to read and inspect.</li>
            <li>TSV files can be imported into Excel without any special handling, whereas CSV files may be misinterpreted by Excel if they contain embedded commas or double quotes.</li>
            <li>TSV files "play nicer" with SAS and other industrial-strength data analysis tools.</li>
        </ul>
        <p>The main drawbacks to TSV files are (1) they are not as widely used as CSV files, so some software may not support them and (2) Excel may not automatically recognize them as delimited files.</p>
        <p>To open a TSV file in Excel, do the following:</p>
        <ol>
            <li>Open a blank Excel workbook.</li>
            <li>From the "Data" tab, select "Get Data" &rarr; "From File" &rarr; "From Text/CSV".</li>
            <li>Select the TSV file you wish to open.</li>
            <li>In the dialog that appears, select "Tab" as the delimiter, then click "Load".</li>
        </ol>
    </div>

</div>

<!-- SASCODE HELP -->

<div id="yes3-fmapr-sascode-help-panel" class="yes3-panel yes3-help-panel yes3-draggable" style="display:none">

    <div class="yes3-panel-header-row">
        <div class="yes3-panel-row-left">
            SAS Code Generation
        </div>
        <div class="yes3-panel-row-right">
            <a href='javascript: YES3.closePanel("yes3-fmapr-sascode-help-panel")'><i class="fas fa-times fa-2x"></i></a>
        </div>
    </div>

    <div class="yes3-scrolling-container yes3-full-width yes3-default-panel-height">

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

        <h5>A note on the export file format</h5>
        
        <div class="yes3-information-em yes3-headroom yes3-legroom">
            <p>The SAS code generated by the YES3 Exporter will support either tab-delimited (TSV) or comma-delimited (CSV) export file types.</p>
            <p>However, we recommend that you use the tab-delimited (TSV) file type, as it is less likely to break the INPUT program.
                We have found that embedded line feeds, quotation marks and other characters in text fields are less likely to cause problems in TSV files than in CSV files.
            </p>
            <ul>
                <li>TSV files are less likely to contain embedded tabs in text fields than CSV files are to contain embedded commas.</li>
                <li>TSV files do not require double quotes around text fields, which makes them easier to read and inspect.</li>
                <li>TSV files can be imported into Excel without any special handling, whereas CSV files may be misinterpreted by Excel if they contain embedded commas or double quotes.</li>
        </div>

    </div>


</div>

<!-- CRITERION VALUE HELP -->

<div id="yes3-fmapr-criterion-value-help-panel" class="yes3-panel yes3-help-panel yes3-draggable" style="display:none">

    <div class="yes3-panel-header-row">
        <div class="yes3-panel-row-left">
            Selection criterion: value(s)
        </div>
        <div class="yes3-panel-row-right">
            <a href="javascript: FMAPR.closeHelpCriterionValueForm()"><i class="fas fa-times fa-2x"></i></a>
        </div>
    </div>
    
    <div class="yes3-scrolling-container yes3-full-width yes3-default-panel-height">

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

    </div>

</div>

<!-- WAYBACK -->

<div id="yes3-fmapr-wayback-panel" class="yes3-panel yes3-panel-small yes3-draggable" style="display:none">

   <div class="yes3-panel-header-row">
      <div class="yes3-panel-row-left" id="yes3-fmapr-wayback-panel-title">
         Wayback Machine
      </div>
      <div class="yes3-panel-row-right">
         <a href="javascript: FMAPR.Wayback_closeForm()"><i class="fas fa-times fa-2x"></i></a>
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
            <a href="javascript: FMAPR.closeItemEditorForm()"><i class="fas fa-times fa-2x"></i></a>
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

            <i class="far fa-save yes3-action-icon yes3-action-icon-controlpanel yes3-loaded yes3-designer-only yes3-save-control" data-bs-toggle="tooltip" id="yes3-fmapr-save-control" action="saveExportSpecification" title="Save the export specification."></i>

            <i class="fas fa-download yes3-action-icon yes3-action-icon-controlpanel yes3-loaded yes3-designer-only yes3-display-when-clean yes3-fmapr-download-options" data-bs-toggle="tooltip" id="yes3-fmapr-download-control" action="download" title="Download the data dictionary, datasheet, or full (zipped) payload to your computer."></i>

            <i class="fas fa-file-export yes3-action-icon yes3-action-icon-controlpanel yes3-loaded yes3-designer-only yes3-display-when-clean yes3-fmapr-export-options" data-bs-toggle="tooltip" id="yes3-fmapr-export-control" action="export" title="Export the full payload to the host file system."></i>
            
            <i class="fas fa-undo yes3-action-icon yes3-action-icon-controlpanel yes3-loaded yes3-designer-only" data-bs-toggle="tooltip" action="Wayback_openForm" title="Restore the export specification from a stored backup."></i>

            <i class="fas fa-question yes3-action-icon yes3-action-icon-controlpanel" data-bs-toggle="tooltip" action="Help_openPanel" title="Display navigation help for this page."></i>

            <i class="fas fa-book-reader yes3-action-icon yes3-action-icon-controlpanel" data-bs-toggle="tooltip" action="Open_docPage" title="Open the YES3 Exporter2 online documentation"></i>

            <i class="fas fa-moon yes3-action-icon yes3-action-icon-controlpanel yes3-light-theme-only" data-bs-toggle="tooltip" action="Theme_dark" title="Switch to the dark side"></i>
            <i class="fas fa-sun yes3-action-icon yes3-action-icon-controlpanel yes3-dark-theme-only" data-bs-toggle="tooltip" action="Theme_light" title="Switch to the sunny side"></i>

                <!--img class="yes3-square-logo yes3-logo" alt="YES3 Logo" title="More about YES3..." /-->

        </div>

    </div>

    <!--  DASHBOARD HEADER -->

    <div class="yes3-dashboard-head yes3-flex-container-vcenter" id="yes3-fmapr-dashboard-head" style="display:none">

        <div class="yes3-flex-hleft yes3-flex-col-33 yes3-dashboard-options">

            <span class="yes3-semibold">EDIT MODE:&nbsp;&nbsp;</span>

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
                        <td>
                            Multi-select values exported as:
                            <i class="far fa-question-circle yes3-action-icon yes3-action-icon-inline-large" action="Help_multiselect" title="Guidance for multi-select values export."></i>
                        </td>

                        <td class="yes3-fmapr-export-specification yes3-fmapr-layout-options">

                            <input type="radio" class="balloon" value="1" name="export_multiselect" data-setting="export_multiselect" id="yes3-fmapr-export_multiselect-1">
                            <label for="yes3-fmapr-export_multiselect-1" title="Export as multiple columns (one per option, each having 1=selected, 0=not selected values)">Multiple columns</label>&nbsp;

                            <input type="radio" class="balloon" value="2" name="export_multiselect" data-setting="export_multiselect" id="yes3-fmapr-export_multiselect-2">
                            <label for="yes3-fmapr-export_multiselect-2" title="Export as a single column (a list of selected option values)">Single column</label>&nbsp;

                        </td>                                  
                    </tr>

                </table>

                <div class="yes3-fmapr-settings-section">
                    Options for selecting records
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

                <div class="yes3-fmapr yes3-flex-container-vtop">

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
                                <input type="checkbox" name="export_hash_recordid" data-setting="export_hash_recordid" value="1" />
                                <span class="yes3-checkmark"></span>Coded (hashed) record id values
                            </label>
                        </div> 
                        
                        <div class="yes3-fmapr-filter-option yes3-fmapr-settings-block">
                            <label class="yes3-checkmarkContainer">
                                <input type="checkbox" name="export_shift_dates" data-setting="export_shift_dates" value="1" />
                                <span class="yes3-checkmark"></span>Coded (shifted) dates
                            </label>
                        </div>
                                
                    </div>

                    
                    <!--div class="yes3-flex-left">
                        
                        <div class="yes3-fmapr-filter-option yes3-fmapr-settings-block">
                            <label class="yes3-checkmarkContainer yes3-fmapr-if-hash-recordid">
                                <input type="checkbox" name="export_hash_recordid_legacy" data-setting="export_hash_recordid_legacy" value="1" />
                                <span class="yes3-checkmark"></span>Legacy hash
                            </label>
                        </div> 

                    </div-->


                </div>

                <div class="yes3-fmapr-settings-section">
                    Options for conditioning exported data
                </div>

                <div class="yes3-fmapr yes3-flex-container">

                    <div class="yes3-flex-left">

                        <div class="yes3-fmapr-filter-option yes3-fmapr-settings-block">
                            <label class="yes3-checkmarkContainer">
                                <input type="checkbox" name="export_inoffensive_text" data-setting="export_inoffensive_text" value="1" />
                                <span class="yes3-checkmark"></span>Remove unprintable characters
                            </label>
                        </div>

                        <!--div class="yes3-fmapr-filter-option yes3-fmapr-settings-block yes3-fmapr-sanitize-option">
                            <label class="yes3-checkmarkContainer">
                                <input type="checkbox" name="export_no_tags" data-setting="export_no_tags" value="1" />
                                <span class="yes3-checkmark"></span>Remove HTML tags
                            </label>
                        </div-->
                    </div>

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
                    Export File Type
                    <i class="far fa-question-circle yes3-action-icon yes3-action-icon-inline-large" action="Help_export_file_type" title="Guidance for export file type."></i>
                </div>

                <table class="yes3-fmapr-settings yes3-full-width">

                    <tr>

                        <td class="yes3-fmapr-export-specification">Export data as:</td>

                        <td class="yes3-fmapr-export-specification">

                            <input type="radio" class="balloon" value="csv" name="export_file_type" data-setting="export_file_type" id="yes3-fmapr-export_file_type-csv">
                            <label for="yes3-fmapr-export_file_type-csv" title="Export data and data dictionary as comma-separated-value (csv) datasheets">Comma-separated values (CSV)</label>&nbsp;

                            <input type="radio" class="balloon" value="tsv" name="export_file_type" data-setting="export_file_type" id="yes3-fmapr-export_file_type-tsv">
                            <label for="yes3-fmapr-export_file_type-tsv" title="Export data and data dictionary as tab-separated-value (tsv) datasheets">Tab-separated values (TSV)</label>&nbsp;

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

                <div class="yes3-fmapr-settings-section yes3-fmapr-batch-export-options">
                    BATCH EXPORT
                </div>

                <div class="yes3-fmapr-filter-option yes3-fmapr-settings-block yes3-fmapr-batch-export-options yes3-max-legroom">
                    <label class="yes3-checkmarkContainer yes3-fmapr-batch-export-options">
                        <input type="checkbox" name="export_batch" data-setting="export_batch" value="1" class="yes3-fmapr-batch-export-options" />
                        <span class="yes3-checkmark yes3-fmapr-batch-export-options"></span>Include in automated (cron) batch exports
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

            <thead id='yes3-fmapr-export-items-thead'>

                <tr>
                    <th class='yes3-header colspan="6" yes3-th-title'>&nbsp;&nbsp;Forms and Fields to Export (click <i class="far fa-edit yes3-fmapr-item-editor"></i> to edit)</th>
                </tr>
                
            </thead>

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

</div> <!-- container -->

<script>

    (function(){



    })

</script>




