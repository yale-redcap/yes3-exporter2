FMAPR.insertionRowId = "";

FMAPR.selectionRowId = "";

FMAPR.rowCount = 0;

FMAPR.dashboard_option = "settings";

FMAPR.warning_message = "";

FMAPR.error_message = "";

FMAPR.reloadParms = {
    "export_uuid": "",
    "wayback": false
}

FMAPR.allSummaries = {
    "all_fields": "",
    "all_forms": "",
    "all_events": "",
}

FMAPR.constrainedAutocompleteSource = [];

FMAPR.tooltips = {

    "event_select": "After a REDCap field or form is selected, this drop-down will include all the REDCap events assigned to it.",
    "row_selector": `Row selector: click the <i class="far fa-question-circle"></i> icon directly above in the header for usage.`,
    "row_editor": "Click to edit this export item.",
    "row_trashcan": "Click to remove this export item.",
    "row_event": "The REDCap event for this export item.",

}

FMAPR.rapidEntryFormRowId = "yes3-fmapr-rapidentry-row";
FMAPR.rapidEntryFormId = "yes3-fmapr-rapidentry-form";

FMAPR.userPermissions = {

    "design": false,
    "export": false,
}

// used to control whether columns are recounted when the spec is saved
// currently set when an item row is added or removed
FMAPR.column_count = 0;
FMAPR.columnCountChanged = 0;
FMAPR.log_id = 0;
FMAPR.export_uuid = "";

FMAPR.convertToVertical = 0; // set to 1 if the export layout is 'repeating' and the user has been warned that it will be converted to vertical

/**
 * YES3 FUNCTIONS FOR FMAPR
 * 
 * These functions are called via the YES3 'action icon' mechanism,
 * so they are registered in the YES3 namespace.
 * 
 * Invocation is through Function.prototype.call(),
 * which supplies a 'this' value that functions can use to determine context.
 * 
 * Be sure to use the full YES3.Functions namespace
 */

YES3.Functions.download = function()
{
    // open the download panel
    FMAPR.openDownloadForm();
}

YES3.Functions.export = function()
{ 
    FMAPR.exportToHost(0);
    
    /*
    const warningMessage = "Preparing to export data to: <br><br><strong>" + FMAPR.project.host_filesystem_target 
        + "</strong><br><br>This will replace any existing data for this export on the file system.<br><br>Are you sure you want to proceed?";

    YES3.YesNo(warningMessage, FMAPR.exportToHostGo);
    */
}

YES3.Functions.Help_formInsertion = function()
{
    let thePanel = YES3.openPanel("yes3-fmapr-form-insertion-help-panel", true);

    if ( FMAPR.project.is_longitudinal ){
        thePanel.find(".yes3-fmapr-longitudinal-only").show();
        thePanel.find(".yes3-fmapr-crossectional-only").hide(); 
    }
    else 
    {
        thePanel.find(".yes3-fmapr-longitudinal-only").hide();
        thePanel.find(".yes3-fmapr-crossectional-only").show(); 
    }
}

YES3.Functions.Help_criterionValue = function()
{
    let thePanel = YES3.openPanel("yes3-fmapr-criterion-value-help-panel", true);
    let export_criterion_field = $('input#export_criterion_field').val() || "";
    let export_selection = $('input[name=export_selection]:checked').val();
    let export_criterion_value = $('input#export_criterion_value').val() || "";
    let m = {};
    let field_type = "";
    let field_valueset_list = "";

    if ( export_selection==='1' || !export_criterion_field.length ){

        thePanel.find("tr[property=field_name] td.propvalue").html("(no field selected)");
        thePanel.find("tr[property=field_label]").hide();
        thePanel.find("tr.yes3-fmapr-criterion-field-defined").hide();
    }
    else {
        m = FMAPR.project.field_metadata[FMAPR.project.field_index[export_criterion_field]];
        field_type = m.field_type;
        if ( m.field_validation ){

            field_type += " (" + m.field_validation + ")";
        }
        thePanel.find("tr[property=field_name] td.propvalue").html(export_criterion_field);       
        thePanel.find("tr[property=field_type] td.propvalue").html(field_type).show();
        thePanel.find("tr[property=field_label]").show().find("td.propvalue").html(m.field_label);
        thePanel.find("tr.yes3-fmapr-criterion-field-defined").show();
        
        field_valueset_list = FMAPR.valueSetList( m.field_type, m.field_valueset );

        if ( field_valueset_list.length ){

            thePanel.find('tr[property=field_valueset]')
                .show()
                .find("td.propvalue table tbody").html(field_valueset_list)
            ;
        }
        else {

            thePanel.find('tr[property=field_valueset]').hide();
        }
    }
}

YES3.Functions.Help_sascode = function()
{
    const thePanel = YES3.openPanel("yes3-fmapr-sascode-help-panel", true);
}

YES3.Functions.Help_rowselector = function()
{
    const thePanel = YES3.openPanel("yes3-fmapr-row-selector-help-panel", true);
}

YES3.Functions.Help_export_file_type = function()
{
    const thePanel = YES3.openPanel("yes3-fmapr-export-file-type-help-panel", true);
}

YES3.Functions.Help_multiselect = function()
{
    const thePanel = YES3.openPanel("yes3-fmapr-multiselect-help-panel", true);
}

YES3.Functions.Help_openDocumentation = function() {

    FMAPR.openDocumentation();
}

YES3.Functions.Help_rapidentry = function() {
    let thePanel = YES3.openPanel("yes3-fmapr-rapidentry-help-panel", true);

    if ( FMAPR.project.is_longitudinal ){
        thePanel.find(".yes3-fmapr-longitudinal-only").show();
        thePanel.find(".yes3-fmapr-crossectional-only").hide(); 
    }
    else 
    {
        thePanel.find(".yes3-fmapr-longitudinal-only").hide();
        thePanel.find(".yes3-fmapr-crossectional-only").show(); 
    }
}

/*
 * DOCUMENTATION
 */

FMAPR.openDocumentation = function(){

    window.open(YES3.documentationUrl, "_blank");
}

/** 
 * TOOLTIP and POPOVER SPECIFICATIONS
 * 
 * BS/popper tooltips and popovers are styled to appear indistinguishable from each other, 
 * but popovers will persist until the user clicks elsewhere.
 * Popovers can therefore include UI content, including links.
 * 
 * These are used to set up Bootstrap/popper tooltips and popovers. 
 * 
 * Required tooltip structure (not positionally sensitive):
 * { selector: "CSS selector", html: "HTML content"[, opts] }
 * Implemented by: YES3.enableTooltips( tooltipSpecs )
 * 
 * Required popover structure:
 * { selector: "CSS selector", html: "HTML content"[, opts][, Fn] }
 * Implemented by: YES3.enablePopovers( popoverSpecs )
 * 
 * where 
 * (1) opts is an optional object that that provides overriding options to the popover/tooltip, e.g. {placement: 'top'}
 * (2) Fn is an optional "more help" function or function reference. If provided, a "more help" link will be added to the popover content.
 *     Popovers only. Ignored for tooltips.
 * 
 */

FMAPR.tooltipSpecs = [

    { selector: "i[action=saveExportSpecification]", html: "Save the export specification. You must save the specification before you can download or export data." },
    { selector: "i[action=download]", html: "Download the data dictionary, datasheet, or the full (zipped) payload to your computer. The full payload includes the data dictionary (excel-friendly csv), the datasheet (csv or tsv), the export information file (json) and, if requested, generated SAS code." },
    { selector: "i[action=export]", html: "Export the full payload to the host file system. The full payload includes the data dictionary (excel-friendly csv), the datasheet (csv or tsv), the export information file (json) and, if requested, generated SAS code." },
    { selector: "i[action=Wayback_openForm]", html: "Restore the export specification from a stored backup." },
    { selector: "i[action=Help_openPanel]", html: "Display navigation help for this page." },
    { selector: "i[action=Help_openDocumentation]", html: "Open the YES3 Exporter2 online documentation." },
    { selector: "i[action=Theme_dark]", html: "Give in to the Dark Side." },
    { selector: "i[action=Theme_light]", html: "Return to the Sunny Side." },

    { selector: "input#yes3-dashboard-option-settings+label", html: "Edit the settings for this export specification." },
    { selector: "input#yes3-dashboard-option-items+label", html: "Add or remove items (fields and/or forms) for this export specification." },

    { selector: "i[action=Help_multiselect]", opts: { placement: 'right' }, html: "Checkbox fields can be exported as single columns that list all the selected values or multiple columns, one for each selected value. Click for more information." },
    { selector: "i[action=Help_criterionValue]", opts: { placement: 'right' }, html: "An export may include all records or selected records.<br />Click for guidance on selecting record subsets." },
    { selector: "i[action=Help_export_file_type]", opts: { placement: 'right' }, html: "Data may be exported as either comma-separated files (CSV) or as a tab-delimited text files (TSV). Click for more information." },
    { selector: "i[action=Help_sascode]", opts: { placement: 'right' }, html: "Export payloads, either downloaded or written to the host file system, may include executable SAS code. Click for more information." },
    
    { selector: "input[name=export_batch]+span", opts: { placement: 'top' }, html: "If checked, this export will be included in the nightly batch cron job." },

    { selector: "input#yes3-fmapr-export-selection-1+label", opts: { placement: 'top' }, html: "Include all records that you are allowed to access." },
    { selector: "input#yes3-fmapr-export-selection-2+label", opts: { placement: 'top' }, html: "Include only records, from among those to which you have access, that meet specific criteria." },

    { selector: "input[name=export_name]", opts: { placement: 'top' }, html: "The export name is used to identify this export specification. It must be unique within the project, as it is the basis of exported file names." },
    { selector: "textarea[name=export_label]", opts: { placement: 'top' }, html: "The export label is an optional descriptive label for this export specification. It can be as long as you wish." },

    { selector: "input#yes3-fmapr-export_multiselect-1+label", opts: { placement: 'right' }, html: "Export as multiple columns (one per option, each having 1=selected, blank=not selected values)." },
    { selector: "input#yes3-fmapr-export_multiselect-2+label", opts: { placement: 'right' }, html: "Export as a single column (with all selected options listed in a single cell, separated by commas)." },

    { selector: "input[name=export_criterion_field]", opts: { placement: 'right' }, html: "Select the REDCap field that will be used to determine which records are included in the export.<br /><br />If you need to select records based on the values of multiple fields, we recommend using a calculated field to implement the selection logic.<br /><br />Click the <i class='far fa-question-circle'></i> icon next to <strong>Options for selecting records</strong> for more information." },
    { selector: "select[name=export_criterion_event]", opts: { placement: 'right' }, html: "Select the REDCap event for the criterion field that will be used to determine which records are included in the export.<br /><br />Once you have selected the criterion field, the available events will be listed here.<br /><br />Click the <i class='far fa-question-circle'></i> icon next to <strong>Options for selecting records</strong> for more information." },
    { selector: "input[name=export_criterion_value]", opts: { placement: 'right' }, html: "Enter the value(s) that the criterion field must match for a record to be included in the export.<br /><br />Click the <i class='far fa-question-circle'></i> icon next to <strong>Options for selecting records</strong> for more information." },

    { selector: "input[name=export_remove_phi]+span", opts: { placement: 'top' }, html: "If checked, all fields tagged as PHI (Protected Health Information) in REDCap will be removed from the export." },
    { selector: "input[name=export_remove_freetext]+span", opts: { placement: 'top' }, html: "If checked, all 'free text' fields in REDCap will be removed from the export. These are text and notes/paragraph fields that allow for open-ended responses, as opposed to dates etc." },
    { selector: "input[name=export_remove_dates]+span", opts: { placement: 'top' }, html: "If checked, all date fields in REDCap will be removed from the export." },
    { selector: "input[name=export_remove_largetext]+span", opts: { placement: 'top' }, html: "If checked, all notes/paragraph text fields in REDCap will be removed from the export." },

    { selector: "input[name=export_hash_recordid]+span", opts: { placement: 'top' }, html: "If checked, the record ID field will be hashed (i.e., anonymized) in the export. The more recent hashing formula introduced in REDCap v15.5.13 will be used." },
    { selector: "input[name=export_shift_dates]+span", opts: { placement: 'top' }, html: "If checked, the dates in the export will be shifted using the same formula that REDCap uses (verified as of REDCap v15.5.15)" },

    { selector: "input[name=export_inoffensive_text]+span", opts: { placement: 'top' }, html: "If checked, all text values in the exported data will be stripped of unprintable control characters. These are ASCII characters 1-31 <strong>except</strong> for tab (ASCII 9) and newline (ASCII 10) characters, which are converted to spaces (ASCII 32)." },
    { selector: "input[name=export_ascii_text]+span", opts: { placement: 'top' }, html: "If checked, all text values in the exported data will be stripped of non-ASCII characters. An effort will be made to transform UTF8 characters into their ASCII equivalents (e.g., ≥ => '>='). Non-mappable UTF8 characters will be represented by question marks." },

    { selector: "input[name=export_max_text_length]", opts: { placement: 'right' }, html: "Specify the maximum length (in characters) for text values in the export. Any values exceeding this length will be truncated. Leave blank for no limit." },
    { selector: "input[name=export_max_label_length]", opts: { placement: 'right' }, html: "Specify the maximum length (in characters) for field labels in the exported data dictionary. Any field labels exceeding this length will be truncated. Leave blank for no limit." },

    { selector: "label[for=yes3-fmapr-export_file_type-csv]", fn: 'Help_export_file_type', opts: {placement: 'top'}, html: "Export data as comma-separated values (CSV).<br /><br />Click the <i class='far fa-question-circle'></i> icon next to <strong>Export File Type</strong> for more information." },
    { selector: "label[for=yes3-fmapr-export_file_type-tsv]", fn: 'Help_export_file_type', opts: {placement: 'top'}, html: "Export data as tab-separated values (TSV).<br /><br />Click the <i class='far fa-question-circle'></i> icon next to <strong>Export File Type</strong> for more information." },

    { selector: "input[name=export_sascode]+span", opts: { placement: 'top' }, html: "If checked, executable SAS code will be included in full export payloads, either downloaded or written to the host file system.<br /><br />Click the <i class='far fa-question-circle'></i> icon next to <strong>Options for SAS code generation</strong> for more information." },
    { selector: "input[name=export_sascode_ascii]+span", opts: { placement: 'top' }, html: "If checked, executable SAS code will use ASCII encoding for variable and format labels.<br /><br />Click the <i class='far fa-question-circle'></i> icon next to <strong>Options for SAS code generation</strong> for more information." },
    { selector: "input[name=export_sascode_libref]", opts: { placement: 'right' }, html: "Enter the library reference name for the exported SAS code. This is a 1-8 character string that typically stands in for the project name.<br><br>Example 'lvbtr'.<br /><br />Click the <i class='far fa-question-circle'></i> icon next to <strong>Options for SAS code generation</strong> for more information." },
    { selector: "input[name=export_sascode_libref_path]", opts: { placement: 'right' }, html: "Enter the full path for the exported SAS dataset and format library. This is a file location that must exist for the code to run.<br><br>Example: 'C:\Users\criwebtools\livebetter\sascode'.<br /><br />Click the <i class='far fa-question-circle'></i> icon next to <strong>Options for SAS code generation</strong> for more information." },
    { selector: "input[name=export_sascode_dsname]", opts: { placement: 'right' }, html: "Enter the name for the SAS dataset to be created by this export. <br><br>Example: 'patient_screen'.<br /><br />Click the <i class='far fa-question-circle'></i> icon next to <strong>Options for SAS code generation</strong> for more information." },

];

FMAPR.newItemOptionsTooltipSpecs = [
    
    { selector: "input#yes3-fmapr-rapidentry-object-add-singleton", html: "Add a form or field to the export specification." },
    { selector: "input#yes3-fmapr-rapidentry-object-add-everything", html: "Add ALL forms (and their fields), across all arms and events if applicatble, to the export specification." },
    { selector: "select#yes3-fmapr-rapidentry-object-event", html: "Select an event (or all events) for the form or field to be added to this export specification." },
    { selector: "label[for=yes3-fmapr-rapidentry-object-type-form]", html: "<p>Add a form (or all forms) to the export specification. All fields on the form will be included.</p><p>You will have the option of adding each field as a separate export item, which can be useful if you want to remove a small number of fields, or change the order.</p>" },
    { selector: "label[for=yes3-fmapr-rapidentry-object-type-field]", html: "Add a single field to the export specification." },
    { selector: "input#yes3-fmapr-rapidentry-object-add[value='append as form']", html: "Append the selected form to the export specification, as a single export item." },
    { selector: "input#yes3-fmapr-rapidentry-object-add-field", html: "Append the selected field to the export specification." },
    { selector: "input#yes3-fmapr-rapidentry-object-add-form", html: "Append the selected form(s) to the export specification, as a single export item (as opposed to individual forms or fields)." },
    { selector: "input#yes3-fmapr-rapidentry-object-add-fields", html: "Append each field in the selected form(s) to the export specification, as a separate export item." },
    { selector: "input#yes3-fmapr-rapidentry-object-add-forms", html: "Append each form in the selected event to the export specification, as a separate export item." },
    //{ selector: "input#yes3-fmapr-rapidentry-object-cancel", html: "Cancel adding a new item to the export specification." },
    { selector: "span.yes3-fmapr-rapidentry-object-mode-label.yes3-fmapr-mode-append", html: "<h6>APPEND MODE</h6><p>You are appending an item to the end of the export specification.</p><p>If you would like to insert an item into a specific location, first select the existing item row <em>above which</em> you want to insert the new item.</p>" },
    { selector: "span.yes3-fmapr-rapidentry-object-mode-label.yes3-fmapr-mode-insert", html: "<h6>INSERT MODE</h6><p>You are inserting an item into the export specification, above the selected item.</p><p>If you would like to append an item to the end of the export specification, clear the item selection by pressing <strong>Ctrl&rarr;Z.</strong></p>" },
    // tooltips for longitudinal projects
    { selector: "input#yes3-fmapr-rapidentry-field_name.yes3-longitudinal", html: "<p>Select the field to be added to this export specification.</p><p class='yes3-longitudinal-only'>If you have selected an event for this export specification item, only fields on forms assigned to that event will be selectable.</p><p>This is an autocomplete field. Press the spacebar for the complete list of selectable fields.</p>" },
    { selector: "select#yes3-fmapr-rapidentry-form_name.yes3-longitudinal", html: "<p>Select the form (or all forms) to be added to this export specification.</p><p class='yes3-longitudinal-only'>If you have selected an event for this export specification item, only forms assigned to that event will be selectable.</p>" },
    // tooltips for cross-sectional projects
    { selector: "input#yes3-fmapr-rapidentry-field_name.yes3-cross-sectional", html: "<p>Select the field to be added to this export specification.</p><p>This is an autocomplete field. Press the spacebar for the complete list of selectable fields.</p>" },
    { selector: "select#yes3-fmapr-rapidentry-form_name.yes3-cross-sectional", html: "<p>Select the form (or all forms) to be added to this export specification.</p>" },


];

FMAPR.moreHelpLink = `<a class="yes3-more-help" href="#" data-action="yes3-more-help">Click here for more information.</a>`;

FMAPR.popoverSpecs = [
                
]
 
/**
* MESSAGES
 */

FMAPR.postWarningMessage = function( message )
{
    const $msgContainer = $("div#yes3-export-editor-warning");

    //console.warn(message); // always log message

    // if there is a summary container, update it
    if ( $msgContainer.length ){

        $msgContainer.text(message);
    }
}

FMAPR.clearWarningMessage = function()
{
    FMAPR.warning_message = "";

    const $msgContainer = $("div#yes3-export-editor-warning");

    if ( $msgContainer.length ){

        $msgContainer.text("");
    }
}

FMAPR.warnIfMixedRepeating = function( allForms, allEvents ) {

    allForms = allForms || false;
    allEvents = allEvents || false;

    let repeating_forms = $(".yes3-repeating").length;
    let repeating_events = $(".yes3-repeating-event").length;
    let nonRepeaters = $(".yes3-non-repeating").length;

    // special case: all forms are selected
    if ( $(`tr[data-object_type=form][data-object_name="${ALL_OF_THEM}"]`).length > 0 ) allForms = true;

    if ( allForms ){
        repeating_forms = 0;
        nonRepeaters = 0;

        // iterate through FMAPR.project.form_metadata
        for (const formId in FMAPR.project.form_metadata) {
            const form = FMAPR.project.form_metadata[formId];
            if (form.form_repeating) {
                repeating_forms++;
            } else {
                nonRepeaters++;
            }
        }
    }

    if ( FMAPR.project.is_longitudinal && allEvents ){

        if ( FMAPR.project.is_longitudinal ){
            for (const eventId in FMAPR.project.event_metadata) {
                const event = FMAPR.project.event_metadata[eventId];
                if ( event.event_repeating ) {
                    repeating_events++;
                }
                else {
                    nonRepeaters++;
                }
            }
        }
    }

    if ( nonRepeaters && ( repeating_forms || repeating_events ) ) {

        FMAPR.postWarningMessage("Warning! Mixed repeating and non-repeating forms and/or events.");
    }
    else {

        FMAPR.clearWarningMessage();
    }
}

FMAPR.clearSystemMessage = function()
{
    FMAPR.error_message = "";
    FMAPR.warning_message = "";

    $("div#yes3-fmapr-system-message").html('');
}

FMAPR.markColumnCountChanged = function() {
    FMAPR.columnCountChanged++;
    FMAPR.column_count = 0; // this will force a recount on the next get
}

/**
 * function to convert a string into an unordered list
 */
FMAPR.arrayToListHtml = function( a )
{
    let html = "";

    html += "<ol class='yes3-fmapr-list'>";

    for (let i=0; i<a.length; i++){

        if ( !a[i].length ) continue;
        html += "<li>" + a[i] + "</li>";
    }

    html += "</ol>";

    return html;
}

FMAPR.nl2br = function( s )
{
    return s.replace(/\n/g, "<br>");
}

FMAPR.ErrReport_OpenPanel = function()
{
    if ( !FMAPR.error_message.length ) return;
    
    const reportContainerId = "yes3-fmapr-error-report";
    const $titleContainer   = $("div#yes3-fmapr-error-report-title");
    const $noteContainer    = $("div#yes3-fmapr-error-report-note");
    const $errorsContainer  = $("div#yes3-fmapr-error-report-content");

    const errList = FMAPR.error_message.split("\n");

    $titleContainer.html("The following errors were reported for this export specification:");

    if (FMAPR.userPermissions.design ){

        $noteContainer.html(
            "Note that any deleted forms and/or fields have already been excluded from this editing session. To make these exclusions permanent, simply save the export."
        );
    }
    else {

        $noteContainer.html("");
    }

    $errorsContainer.html(FMAPR.arrayToListHtml(errList));

    YES3.openPanel(reportContainerId, true);
}

FMAPR.ErrReport_closePanel = function()
{
    YES3.closePanel("yes3-fmapr-error-report");
}

FMAPR.handleExportErrorMessages = function( errorMessages, userIsEditor )
{
    errorMessages = errorMessages || "";
    userIsEditor = userIsEditor || false;

    if ( !errorMessages.length ) return;

    FMAPR.error_message = errorMessages;

    const $msgContainer = $("div#yes3-fmapr-system-message");

    console.error(errorMessages); // always log errors

    // if there is a summary container, update it
    if ( $msgContainer.length ){

        const instructions = "Errors were reported for this export specification."
            + (( userIsEditor ) ? " You may edit this export, but the download/export features are disabled." : " The download/export features are disabled.")
            + " Click <a href='JavaScript:FMAPR.ErrReport_OpenPanel()'>here</a> for details."
        ;
        
        const msg = "<span class='yes3-fmapr-system-message-0'>" + instructions + "</span>";

        $msgContainer.html(msg).parent().show();
    }
} 

FMAPR.postExportMessage = function( message )
{
    const $msgContainer = $("div#yes3-fmapr-system-message");

    //console.warn(message); // always log message

    // if there is a summary container, update it
    if ( $msgContainer.length ){
        
        const msg = "<span class='yes3-fmapr-system-message-1'>" + message + "</span>";

        $msgContainer.html(msg).parent().show();
    }
}   

FMAPR.closeHelpCriterionValueForm = function()
{
    YES3.closePanel("yes3-fmapr-criterion-value-help-panel");
}

FMAPR.closeHelpFormInsertionForm = function()
{
    YES3.closePanel("yes3-fmapr-form-insertion-help-panel");
}

FMAPR.closeHelpSasCodeForm = function()
{
    YES3.closePanel("yes3-fmapr-sascode-help-panel");
}

FMAPR.valueSetList = function( field_type, field_valueset )
{
    if ( field_type === "yesno" ){

        return "<tr><td>1</td><td>Yes</td></tr><tr><td>0</td><td>No</td></tr>";
    }

    if ( field_type === "truefalse" ){

        return "<tr><td>1</td><td>True</td></tr><tr><td>0</td><td>False</td></tr>";
    }

    if ( YES3.isEmptyArray(field_valueset) ){

        return "";
    }

    let s = "";

    for (let i=0; i<field_valueset.length; i++ ){

        s += `<tr><td>${field_valueset[i].value}</td><td>${field_valueset[i].label}</td></tr>`;
    }

    return s;
}

YES3.Functions.expandSettingsPanel = function()
{
    YES3.contentExpanded = true;
    $("div.yes3-expanded").show();
    YES3.displayActionIcons();
    FMAPR.displayActionIcons();
    $(window).trigger("resize");
}
 
YES3.Functions.collapseSettingsPanel = function()
{
    YES3.contentExpanded = false;
    $("div.yes3-expanded").hide();
    YES3.displayActionIcons();
    FMAPR.displayActionIcons();
    $(window).trigger("resize");
}

YES3.Functions.NewExport_openPanel = function()
{
    YES3.openPanel("yes3-fmapr-new-export-form");

    const $tbl = $("table#yes3-fmapr-new-export");

    $tbl.find('input[type=text]').val("");
    $tbl.find('input[type=radio]').prop('checked', false);

    if ( FMAPR.project.is_longitudinal ){

        //$("input#yes3-fmapr-new-export-layout-h").prop("checked", true);
       $tbl.find(".yes3-longitudinal-only").show();
    }
    else {

        $tbl.find(".yes3-longitudinal-only").hide();
        //$("input#yes3-fmapr-new-export-layout-v").prop("checked", true);
    }

    if ( FMAPR.project.repeating_forms ){

        $tbl.find(".yes3-has-repeating-forms").show();
    }
    else {

        $tbl.find(".yes3-has-repeating-forms").hide();
    }
}

FMAPR.NewExport_closePanel = function()
{
    YES3.closePanel("yes3-fmapr-new-export-form");
}

YES3.Functions.openDownloadForm = function()
{
    let thePanel = YES3.openPanel("yes3-fmapr-download-panel");

    if ( FMAPR.project.user_data_downloads_disabled ){

        thePanel.find(".yes3-fmapr-data-download-enabled").hide();
        thePanel.find(".yes3-fmapr-data-download-disabled").show();
    }
    else {

        thePanel.find(".yes3-fmapr-data-download-enabled").show();
        thePanel.find(".yes3-fmapr-data-download-disabled").hide();
    }
}

FMAPR.closeDownloadForm = function()
{
    YES3.closePanel('yes3-fmapr-download-panel');
}

FMAPR.countNewFieldRows = function()
{
    return $("tr.yes3-fmapr-redcap-field.yes3-fmapr-new-field").length;
}
  
FMAPR.setRepeatLayoutConstraints = function()
{
    let form_name = "";

    if ( FMAPR.isRepeatedLayout() ){

        let currentConstraint = FMAPR.formNameConstraint;

        let field_name = $('tr.yes3-fmapr-data-element[data-object_type=field]:first').attr('data-object_name');

        if ( !YES3.isEmpty(field_name) ){

            form_name = FMAPR.getFormForField( field_name );
        }
        
        if ( YES3.isEmpty(form_name) ){

            form_name = field_name = $('tr.yes3-fmapr-data-element[data-object_type=form]:first').attr('data-object_name');
        }

        if ( !YES3.isEmpty(form_name) ){

            if ( form_name !== FMAPR.formNameConstraint ) {
            
                FMAPR.formNameConstraint = form_name;
                FMAPR.postMessage(`Note: all further additions must be from the '${FMAPR.formNameConstraint}' form.`, true);
            }
        }
        else {

            FMAPR.formNameConstraint = "";
            FMAPR.clearMessage();
        }

        if ( FMAPR.formNameConstraint !== currentConstraint || FMAPR.constrainedAutocompleteSource.length === 0 ){
            
            FMAPR.setConstrainedAutocompleteSource();
            //FMAPR.ensureNewFieldRowAtEnd(); // ensure 'add field' item has the right autocomplete source
            FMAPR.displayActionIcons(); // make sure the bulk insertion icon is disabled, if constraint active
            
            // constrain the autocompleters
            $("input.yes3-fmapr-listener-set.ui-autocomplete-input").each(function(){

                FMAPR.setExportItemFieldAutoselectInput( $(this) );
            });
        }

    }
    else {

        FMAPR.formNameConstraint = "";
    }

    return FMAPR.formNameConstraint;
}

FMAPR.setConstrainedAutocompleteSource = function(){

    FMAPR.constrainedAutocompleteSource = [];

    if ( !FMAPR.isRepeatedLayout() ){

        return true;
    }

    let okay = 0;
    let form_name = "";
    let isRepeating = false;

    for (let i=0; i<FMAPR.project.field_metadata.length; i++){

        form_name = FMAPR.project.field_metadata[i].form_name;

        okay = 0;

        if ( FMAPR.formNameConstraint.length > 0 ){

            okay = ( form_name === FMAPR.formNameConstraint ) ? 1 : 0;
        }
        else {

            try {

                okay = FMAPR.project.form_metadata[FMAPR.project.form_index[form_name]].form_repeating;
            } catch(error){

                console.error('setConstrainedAutocompleteSource:ERROR resolving form_repeating property for form [' + form_name + '].');
                okay = 0;
            }
        }

        if ( okay===1 ){

            FMAPR.constrainedAutocompleteSource.push({
                "value": FMAPR.project.field_metadata[i].field_name,
                "label": "[" + FMAPR.project.field_metadata[i].field_name + "] " + FMAPR.project.field_metadata[i].field_label
            });
        }
    }

    //YES3.debugMessage('setConstrainedAutocompleteSource', FMAPR.constrainedAutocompleteSource.length + "items");
}

FMAPR.rowSelectorHtml = function()
{
    return '<label class="yes3-checkmarkContainer">'
        + '<input type="checkbox" class="yes3-row-selector">'
        + '<span class="yes3-checkmark"></span></label>'
    ;
}

YES3.Functions.editExportItem = function()
{
    FMAPR.editNewExportItem();
}

FMAPR.exportItemFormHtml = function( form_name, event, theRowBeforeWhich, yes3_fmapr_data_element_name, mode, unsaved, batch )
{   
   
    yes3_fmapr_data_element_name = yes3_fmapr_data_element_name || "";
    unsaved = unsaved || false;
    batch = batch || false;
    form_name = form_name || "";
    event = event || "";

    if ( !form_name.length ){

        console.error("exportItemFormHtml: missing form_name");
        return false;
    }
        
    let form_index = YES3.valueOrMissing(FMAPR.project.form_index[form_name], NUMERIC_MISSING);

    // fail silently if the form has been deleted from the project
    if ( form_index === NUMERIC_MISSING && form_name !== ALL_OF_THEM ){
        return false;
    }

    let unsavedClass = ( unsaved ) ? "yes3-unsaved" : "";
    let form_label = (form_index !== NUMERIC_MISSING) ? FMAPR.project.form_metadata[form_index].form_label : "";
    let form_repeating = ( form_index !== NUMERIC_MISSING ) ? FMAPR.project.form_metadata[form_index].form_repeating || 0 : 0;
    let object_type = "form";
    let object_repeating_class = "yes3-non-repeating";
    let object_repeating_class_title = ( !FMAPR.project.is_longitudinal || event === ALL_OF_THEM ) ? "This export item is a NON-REPEATING form." : "This field is from a NON-REPEATING form, on a NON-REPEATING event.";
    let event_repeating = ( FMAPR.project.event_metadata[event] && FMAPR.project.event_metadata[event].event_repeating || 0 ) ? 1 : 0;

    // disable repeating form status if event is repeating (since they cannot co-exist)
    if ( form_repeating && event_repeating ) {

        form_repeating = false;
    }

    // report all parameters
    //console.warn("exportItemFormHtml: form_name=", form_name, " event=", event, " theRowBeforeWhich=", theRowBeforeWhich, " yes3_fmapr_data_element_name=", yes3_fmapr_data_element_name, " mode=", mode, " unsaved=", unsaved, " batch=", batch );
    //console.warn("exportItemFormHtml: form_repeating=", form_repeating, " event_repeating=", event_repeating );

    // consider the form as NON repeating if the event is repeating (the two cannot co-exist)
    if ( form_repeating ) {

        object_type = "form R";
        object_repeating_class = "yes3-repeating";
        object_repeating_class_title = "This form is REPEATING form";
    } 
    else if ( event_repeating ) {

        object_repeating_class = "yes3-repeating-event";
        object_repeating_class_title = "This form is from a REPEATING EVENT.";        
    }

    if ( YES3.isEmpty( theRowBeforeWhich ) ){
        theRowBeforeWhich = FMAPR.getNewFieldRow();
    }

    mode = ( YES3.isEmpty(theRowBeforeWhich) ) ? "append" : "insert";

    //YES3.debugMessage("addREDCapFormV2:theRowBeforeWhich", theRowBeforeWhich, mode, FMAPR.getNewFieldRow(), YES3.isEmpty( theRowBeforeWhich ));

    if ( form_name == ALL_OF_THEM ){

        object_repeating_class = "";
        object_repeating_class_title = "All forms are selected";
    } 
    else {

        form_index = FMAPR.project.form_index[form_name];

        if ( typeof form_index !== "number" && form_name !== ALL_OF_THEM ){
            //YES3.debugMessage("addREDCapFormV2: invalid form name", form_name);
            return false;
        }
        else {
            form_label = FMAPR.project.form_metadata[form_index].form_label;
        }
    }

    if ( !yes3_fmapr_data_element_name ) {

        yes3_fmapr_data_element_name = FMAPR.RawREDCapDataElementName(0);
    }

    let rowId = FMAPR.dataElementRowId(yes3_fmapr_data_element_name);

    let field_count = (form_name===ALL_OF_THEM) ? FMAPR.project.field_metadata.length : FMAPR.project.form_metadata[form_index].form_fields.length;

    let html = `<tr class='yes3-fmapr-redcap-form yes3-fmapr-data-element ${object_repeating_class} yes3-fmapr-sortable ${unsavedClass}' data-yes3_fmapr_data_element_name="${yes3_fmapr_data_element_name}" data-yes3_fmapr_data_element_description="REDCap form" id="${rowId}" data-element_origin="redcap" data-object_type="form" data-object_name="${form_name}" data-object_event="${event}">`;
    html += `<td class='yes3-fmapr-row-number yes3-bs-tooltip-placement-right' data-bs-toggle='tooltip' data-bs-html='true' title='${FMAPR.tooltips.row_selector}'>&nbsp;</td>`;
    //html += `<td class='yes3-fmapr-redcap-object-editor yes3-bs-tooltip-placement-right' data-bs-toggle='tooltip' data-bs-html='true' title='${FMAPR.tooltips.row_editor}'><i class='far fa-edit yes3-fmapr-item-editor' onclick='FMAPR.editExistingExportItem("${yes3_fmapr_data_element_name}");'></i></td>`;
    html += `<td class='yes3-fmapr-redcap-object-type' data-bs-toggle='tooltip' data-bs-html='true' title='${object_repeating_class_title}'>${object_type}</td>`;
    if ( FMAPR.project.is_longitudinal ){
        html += `<td class='yes3-fmapr-redcap-object-event' data-bs-toggle='tooltip' data-bs-html='true' title='${FMAPR.exportItemRowEventTooltip(event)}'>${FMAPR.exportItemRowEventLabel(event)}</td>`;
    }
    html += `<td class='yes3-fmapr-redcap-object-name' data-bs-toggle='tooltip' data-bs-html='true' title="Form name: <strong>${form_name}</strong><br>Form label: ${form_label}<br>${object_repeating_class_title}">${form_label}</td>`;
    html += `<td class='yes3-gutter-right-top yes3-td-right yes3-fmapr-trashcan yes3-bs-tooltip-placement-left yes3-bs-tooltip-right'><i class='far fa-trash-alt' onclick='FMAPR.removeDataElement("${yes3_fmapr_data_element_name}");' data-bs-toggle='tooltip' data-bs-html='true' title='${FMAPR.tooltips.row_trashcan}'></i></td>`;
    html += "</tr>";

    if ( batch ){

        return html;
    }

    return FMAPR.injectREDCapObjectHtmlV2(theRowBeforeWhich, yes3_fmapr_data_element_name, html);
}

FMAPR.injectREDCapObjectHtmlV2 = function(theRowBeforeWhich, yes3_fmapr_data_element_name, html)
{
    let $fmaprBody = $('table.yes3-fmapr-specification').first().find('tbody');

    if ( YES3.isEmpty( theRowBeforeWhich ) ){
        theRowBeforeWhich = null; //FMAPR.getExportRapidEntryEditor();
    }
    mode = ( YES3.isEmpty(theRowBeforeWhich) ) ? "append" : "insert";

    if ( mode==="insert" ){
        $( html ).insertBefore( theRowBeforeWhich );
    }
    else {
        $fmaprBody.append( html );
    }

    let elementRow = $(`tr.yes3-fmapr-data-element[data-yes3_fmapr_data_element_name='${yes3_fmapr_data_element_name}']`);

    FMAPR.setRowSelectorListenerV2( elementRow );

    FMAPR.setRepeatLayoutConstraints();

    return yes3_fmapr_data_element_name;
}

FMAPR.setObjectInsertMode = function( theRowBeforeWhich )
{
    if ( typeof theRowBeforeWhich === "object" && theRowBeforeWhich.length > 0 ) {
        return "insert"
    };
    return "append";
}

FMAPR.exportItemFieldHtml = function( field_name, event, theRowBeforeWhich, yes3_fmapr_data_element_name, mode, unsaved, batch )
{   
    
    //theRowBeforeWhich = theRowBeforeWhich || FMAPR.getNewFieldRow();
    yes3_fmapr_data_element_name = yes3_fmapr_data_element_name || "";
    unsaved = unsaved || false;
    batch = batch || false;
    event = event || "";
    field_name = field_name || "";

    if ( !field_name.length ){
        console.error("exportItemFieldHtml: missing field_name");
        return false;
    }

    let field_index = YES3.valueOrMissing(FMAPR.project.field_index[field_name], NUMERIC_MISSING);

    // fail silently if the field has been deleted from the project
    if ( field_index === NUMERIC_MISSING && field_name !== ALL_OF_THEM ){
        return false;
    }

    let unsavedClass = ( unsaved ) ? "yes3-unsaved" : "";

    let form_name = (field_index !== NUMERIC_MISSING) ? FMAPR.project.field_metadata[field_index].form_name : "";
    let form_index = YES3.valueOrMissing(FMAPR.project.form_index[form_name], NUMERIC_MISSING);
    let form_label = (form_index !== NUMERIC_MISSING) ? FMAPR.project.form_metadata[form_index].form_label : "";
    let form_repeating = (form_index !== NUMERIC_MISSING) ? FMAPR.project.form_metadata[form_index].form_repeating || 0 : 0;

    let field_label = (field_index !== NUMERIC_MISSING) ? FMAPR.project.field_metadata[field_index].field_label : "";

    let object_type = "field";
    let object_repeating_class = "yes3-non-repeating";
    let object_repeating_class_title = ( !FMAPR.project.is_longitudinal || event === ALL_OF_THEM ) ? "This export item is from a NON-REPEATING form." : "This field is from a NON-REPEATING form, on a NON-REPEATING event.";
    let event_repeating = ( FMAPR.project.event_metadata[event] && FMAPR.project.event_metadata[event].event_repeating || 0 ) ? 1 : 0;

    // report all parameters
    //console.warn("exportItemFieldHtml: field_name=", field_name, "field_index=", field_index, "form_name=", form_name, " event=", event, " theRowBeforeWhich=", theRowBeforeWhich, " yes3_fmapr_data_element_name=", yes3_fmapr_data_element_name, " mode=", mode, " unsaved=", unsaved, " batch=", batch );
    //console.warn("exportItemFieldHtml: form_repeating=", form_repeating, " event_repeating=", event_repeating );

    // disable repeating form status if event is repeating (since they cannot co-exist)
    if ( form_repeating && event_repeating ) {

        form_repeating = false;
    }

    // consider the form as NON repeating if the event is repeating (the two cannot co-exist)
    if ( form_repeating ) {

        object_type = "field R";
        object_repeating_class = "yes3-repeating";
        object_repeating_class_title = "This field is from a REPEATING form.";
    }
    else if ( event_repeating ) {

        object_repeating_class = "yes3-repeating-event";
        object_repeating_class_title = "This field is from a REPEATING EVENT.";        
    }

    if ( !yes3_fmapr_data_element_name ) {
        
        yes3_fmapr_data_element_name = FMAPR.RawREDCapDataElementName(0);
    }

    let rowId = FMAPR.dataElementRowId(yes3_fmapr_data_element_name);

    let html = `<tr class='yes3-fmapr-redcap-field ${object_repeating_class} yes3-fmapr-data-element yes3-fmapr-sortable ${unsavedClass}' data-yes3_fmapr_data_element_name="${yes3_fmapr_data_element_name}" data-yes3_fmapr_data_element_description="REDCap field" id="${rowId}" data-element_origin="redcap" data-object_type="field" data-object_name="${field_name}" data-object_event="${event}">`;
    html += `<td class='yes3-fmapr-row-number yes3-bs-tooltip-placement-right' data-bs-toggle='tooltip' data-bs-html='true' title='${FMAPR.tooltips.row_selector}'>&nbsp;</td>`;
    //html += `<td class='yes3-fmapr-redcap-object-editor yes3-bs-tooltip-placement-right' data-bs-toggle='tooltip' data-bs-html='true' title='${FMAPR.tooltips.row_editor}'><i class='far fa-edit yes3-fmapr-item-editor' onclick='FMAPR.editExistingExportItem("${yes3_fmapr_data_element_name}");'></i></td>`;
    html += `<td class='yes3-fmapr-redcap-object-type' data-bs-toggle='tooltip' data-bs-html='true' title='${object_repeating_class_title}'>${object_type}</td>`;
    if ( FMAPR.project.is_longitudinal ){
        html += `<td class='yes3-fmapr-redcap-object-event' data-bs-toggle='tooltip' data-bs-html='true' title='${FMAPR.exportItemRowEventTooltip(event)}'>${FMAPR.exportItemRowEventLabel(event)}</td>`;
    }
    html += `<td class='yes3-fmapr-redcap-object-name' data-bs-toggle='tooltip' data-bs-html='true' title="form: <strong>${form_label}</strong><br>field: <strong>${field_name}</strong><br>label: ${field_label}<br>${object_repeating_class_title}">${field_name}<span class='yes3-fmapr-field-label'> - ${field_label}</span></td>`;
    html += `<td class='yes3-gutter-right-top yes3-td-right yes3-fmapr-trashcan yes3-bs-tooltip-placement-left yes3-bs-tooltip-right' data-bs-toggle='tooltip' data-bs-html='true' title='${FMAPR.tooltips.row_trashcan}'><i class='far fa-trash-alt' onclick='FMAPR.removeDataElement("${yes3_fmapr_data_element_name}");'></i></td>`;
    html += "</tr>";

    if ( batch ){

        return html;
    }
    
    return FMAPR.injectREDCapObjectHtmlV2(theRowBeforeWhich, yes3_fmapr_data_element_name, html);
}

FMAPR.establishItemTableColumns = function()
{
    let dummyRow = $('tr#yes3-fmapr-dummy-row');

    // if there are items defined, hide the blank 'dummy' top row
    // otherwise show it, to establish the cell count for the form

    if ( FMAPR.itemCount() ){

        dummyRow.hide();
    }
    else {

        dummyRow.show();
    }
}

FMAPR.getNewItemMode = function() {
    let mode = "append";
    if ( FMAPR.selectedRowCount() === 1 ){
        mode = "insert";
    }
    return mode;
}

FMAPR.getNewObjectType = function() {

    return $("input[name=new_object_type]:checked").val() || "?";
}

FMAPR.setBsToolTipsForNewItemOptions = function() {

    const section = FMAPR.getExportNewItemOptionsSection()[0]; // raw DOM element

    // clear any existing tooltips in this section
    YES3.clearBsTooltipsForContainer( section );

    // set tooltip properties for this section
    YES3.setBsTooltipPropertiesFromSpecs( FMAPR.newItemOptionsTooltipSpecs, section );

    // enable tooltips for this section
    YES3.setBsTooltipListenersForContainer( section );
}

FMAPR.renderNewItemOptionsSection = function( force ) {

    force = force || false;

    const $section = FMAPR.getExportNewItemOptionsSection();

    if ( force ){

        $section.empty().removeClass("yes3-fmapr-multiple yes3-fmapr-singleton"); // clear the section and remove any classes
    } else {

        // do not clear the section if it is already rendered
        if ( $section.hasClass("yes3-fmapr-multiple") || $section.hasClass("yes3-fmapr-singleton") ){
            return;
        }
    }

    // render the default item options form: [form or field] OR [everything]
    FMAPR.renderNewItemOptionsSectionFormOrField();
}

/**
 * The default item add form: [form or field] OR [everything]
 * 
 * @returns {void}
 */
FMAPR.renderNewItemOptionsSectionDefault = function() {

    const $section = FMAPR.getExportNewItemOptionsSection();

    $section.empty().removeClass("yes3-fmapr-singleton").addClass("yes3-fmapr-multiple"); // clear the section and remove singleton class

    let html = "";

    if ( FMAPR.everythingIsAdded() ){

        FMAPR.disableIconsWhenEverythingAdded();

        html += `<div class="yes3-flex-container-left-aligned yes3-margin-top yes3-wrap">`;
        html += "Note: since all forms and events are already specified for this export, additional items cannot be added. The 'all forms' item must be deleted if you would like to add specific forms or fields.";
        html += "</div>";

        $section.html(html);

        // there are no listeners to set (the two buttons are bound to JS functions), so just return

        return;
    } 

    html += `<span class="yes3-semibold"><span class="yes3-fmapr-rapidentry-object-mode-label"></span>:&nbsp;</span>`;

    html += `<input type="button" id="yes3-fmapr-rapidentry-object-add-singleton" value="form or field" onclick="FMAPR.addItemSingleton()" />`;

    html += "<span class='yes3-semibold'>OR</span>";

    html += `<input type="button" id="yes3-fmapr-rapidentry-object-add-everything" value="everything" onclick="FMAPR.addEverything()" />`;
    
    $section.append(html);

    // set the mode label that we just added to the UI
    FMAPR.setNewItemModeLabel();

    // tooltips
    FMAPR.setBsToolTipsForNewItemOptions();
}

FMAPR.addItemSingleton = function(){

    FMAPR.renderNewItemOptionsSectionFormOrField();
}
/**
 * The singleton item add form: [form or field]
 * 
 * @returns {void}
 */
FMAPR.renderNewItemOptionsSectionFormOrField = function() {

    const $section = FMAPR.getExportNewItemOptionsSection();
    const section = $section[0]; // raw DOM element

    // clear tooltips in this section
    YES3.clearBsTooltipsForContainer( section );

    $section.empty().removeClass("yes3-fmapr-multiple").addClass("yes3-fmapr-singleton"); // clear the section and add the singleton class

    //$section.css("height", sectionHeight + "px"); // reset the height to the original to minimize layout shifts

    const projTypeClass = ( FMAPR.project.is_longitudinal ) ? "yes3-longitudinal" : "yes3-cross-sectional";

    let html = "";

    const mode = FMAPR.getNewItemMode().toUpperCase();

    html += `<i class="far fa-question-circle yes3-action-icon yes3-action-icon-inline-large" action="Help_rapidentry" data-bs-toggle="tooltip" data-bs-html="true" title="Click for help with adding items to the export."></i>`;

    html += `<span class="yes3-semibold ${projTypeClass}"><span class="yes3-fmapr-rapidentry-object-mode-label">${mode}</span></span>`;

    html += `<input type="radio" class="balloon ${projTypeClass}" value="form" name="new_object_type" id="yes3-fmapr-rapidentry-object-type-form" checked />`;
    html += `<label for="yes3-fmapr-rapidentry-object-type-form" class="${projTypeClass}">form(s)</label>`;

    html += `<input type="radio" class="balloon ${projTypeClass}" value="field" name="new_object_type" id="yes3-fmapr-rapidentry-object-type-field" />`;
    html += `<label for="yes3-fmapr-rapidentry-object-type-field" class="${projTypeClass}">field</label>`;

    if (FMAPR.project.is_longitudinal  ){
        html += `<select name="new_object_event" id="yes3-fmapr-rapidentry-object-event" class="${projTypeClass}">`;
        html += FMAPR.getAllEventOptionsHtml();
        html += `</select>`;
    }

    html += `<input type="text" id="yes3-fmapr-rapidentry-field_name" placeholder="start typing or spacebar for all" class="${projTypeClass}" style="display: none;" />`;

    html += `<select id="yes3-fmapr-rapidentry-form_name" class="${projTypeClass}" style="display: none;">`;
    html += `<option value="">-- select form --</option>`;
    html += `<option value="${ALL_OF_THEM}">all forms</option>`;
    html += `</select>`;

    html += `<input type="button" class="yes3-fmapr-add-object ${projTypeClass}" id="yes3-fmapr-rapidentry-object-add-form" value="append as form" style="display: none;" />`;

    html += `<input type="button" class="yes3-fmapr-add-object ${projTypeClass}" id="yes3-fmapr-rapidentry-object-add-forms" value="append as forms" style="display: none;" />`;

    html += `<input type="button" class="yes3-fmapr-add-object ${projTypeClass}" id="yes3-fmapr-rapidentry-object-add-field" value="append as field" style="display: none;" />`;
 
    html += `<input type="button" class="yes3-fmapr-add-object ${projTypeClass}" id="yes3-fmapr-rapidentry-object-add-fields" value="append as fields" style="display: none;" />`;

    $section.append(html);

    FMAPR.setNewItemOptionsSingletonListeners();

    // set the mode label that we just added to the UI
    FMAPR.setNewItemModeLabel();

    // set tooltips in this section
    FMAPR.setBsToolTipsForNewItemOptions();
}

FMAPR.setNewItemOptionsObjectNameContent = function(){

    const objectType =$("input[type=radio][name=new_object_type]:checked").val();
    const $objectEvent=$("select#yes3-fmapr-rapidentry-object-event");
    const $formSelect = $("select#yes3-fmapr-rapidentry-form_name");
    const $fieldInput = $("input#yes3-fmapr-rapidentry-field_name");

    let objectEvent = $objectEvent.val();

    if ( !objectEvent || objectEvent==="") {
        objectEvent = ALL_OF_THEM;
        $objectEvent.val(objectEvent);
    }

    if ( objectType==="field" ){
        
        FMAPR.setNewItemOptionsFieldAutocomplete();
        $fieldInput.val("");
    }
    else {

        $formSelect.empty().append(FMAPR.getNewItemOptionsFormSelectOptionsHtml()).val("");
    }

    FMAPR.rapidEntrySkipper();
}

FMAPR.setNewItemOptionsSingletonListeners = function()
{
    $("input[type=radio][name=new_object_type]")
        .off()
        .on("change", function(){

            const objectType =$("input[type=radio][name=new_object_type]:checked").val();
            const $formSelect = $("select#yes3-fmapr-rapidentry-form_name");
            const $fieldInput = $("input#yes3-fmapr-rapidentry-field_name");

            if ( objectType==="field" ){
                
                $formSelect.hide();
                $fieldInput.show();
            }
            else {
                $fieldInput.hide();
                $formSelect.show();
            }

            FMAPR.setNewItemOptionsObjectNameContent();
        }
    );

    $("select#yes3-fmapr-rapidentry-object-event")
        .off()
        .on("change", function(){

            FMAPR.setNewItemOptionsObjectNameContent();
        }
    );

    $("select#yes3-fmapr-rapidentry-form_name")
        .off()
        .on("change", function(){

            FMAPR.rapidEntrySkipper();
        }
    );

    // add single form
    $("input#yes3-fmapr-rapidentry-object-add-form")
        .off()
        .on("click", function(){

            const objectType  = "form";
            const objectEvent = $("select#yes3-fmapr-rapidentry-object-event").val();
            const objectName  = $("select#yes3-fmapr-rapidentry-form_name").val();
            
            if ( objectName === ALL_OF_THEM && (objectEvent === ALL_OF_THEM || !FMAPR.project.is_longitudinal ) ) {
                FMAPR.addEverything(false, false);
            }
            else {
                FMAPR.addRapidEntryItem(objectType, objectName, objectEvent, false, false);
            }
        })
    ;

    // add all forms for event (all forms, if not longitudinal)
    $("input#yes3-fmapr-rapidentry-object-add-forms")
        .off()
        .on("click", function(){

            const objectType  = "form";
            const objectEvent = $("select#yes3-fmapr-rapidentry-object-event").val();
            const objectName  = $("select#yes3-fmapr-rapidentry-form_name").val();

            //console.warn("add-forms: objectEvent=", objectEvent, " objectName=", objectName );

            if ( objectEvent === ALL_OF_THEM || !FMAPR.project.is_longitudinal ) {
                //console.warn("-->add-forms: adding everything" );
                FMAPR.addEverything(false, true);
            }
            else {
                //console.warn("-->add-forms: adding forms for event" );
                FMAPR.addRapidEntryItem(objectType, objectName, objectEvent, false, true);
            }
        })
    ;

    // add all fields for form
    $("input#yes3-fmapr-rapidentry-object-add-fields")
        .off()
        .on("click", function(){

            const objectType  = "form";
            const objectEvent = $("select#yes3-fmapr-rapidentry-object-event").val();
            const objectName  = $("select#yes3-fmapr-rapidentry-form_name").val();

            if ( objectName === ALL_OF_THEM && ( objectEvent === ALL_OF_THEM || !FMAPR.project.is_longitudinal )) {
                FMAPR.addEverything( true, false );
                return;
            }
            else {
                FMAPR.addRapidEntryItem(objectType, objectName, objectEvent, true, false);
            }
        })
    ;

    // add a single field
    $("input#yes3-fmapr-rapidentry-object-add-field")
        .off()
        .on("click", function(){

            const objectType  = "field";
            const objectEvent = $("select#yes3-fmapr-rapidentry-object-event").val();
            const objectName  = $("input#yes3-fmapr-rapidentry-field_name").val();

            FMAPR.addRapidEntryItem(objectType, objectName, objectEvent, false, false);
        })
    ;

    /*
    $("input#yes3-fmapr-rapidentry-object-cancel")
        .off()
        .on("click", function(){

            FMAPR.renderNewItemOptionsSectionDefault();
        })
    ;
    */

    // display the default object type inputs
    $("input[type=radio][name=new_object_type]:checked").trigger("change");

}

FMAPR.setNewItemOptionsFieldAutocomplete = function(){

    const objectEvent=$("select#yes3-fmapr-rapidentry-object-event").val();
    const $fieldName = $("input#yes3-fmapr-rapidentry-field_name");

    $fieldName
    .val("")
    .autocomplete({
        source: FMAPR.getFieldAutoCompleteSource(objectEvent),
        minLength: 1,
        position: { my: "left top", at: "left bottom", collision: "flipfit" },
        select: function(event, ui) {

            if (!ui.item) {
                $(this)
                    .val("")
                    .prop("title", "")
                ;
                return false;
            }

            $(this)
                .val(ui.item.value)
                .prop("title", ui.item.label)
            ;

            FMAPR.rapidEntrySkipper();
            return false;
        }
    })
    .off("change")
    .on("change", function(){

        let object_name = $(this).val();

        object_name = object_name.trim();

        if ( !object_name ){

            FMAPR.rapidEntrySkipper();

            return false;
        }

        if ( typeof FMAPR.project.field_index[object_name] !== 'number' ) {

            YES3.hello(`'${$(this).val()}' is not a valid field name.`);
            $(this).val("");
        } 
        
        FMAPR.rapidEntrySkipper();
    });
}


FMAPR.setNewItemOptionsSingletonListeners_deprecated = function()
{
    $("input[type=radio][name=new_object_type], select#yes3-fmapr-rapidentry-object-event")
        .off()
        .on("change", function(){

            const object_type =$("input[type=radio][name=new_object_type]:checked").val();

            const object_event=$("select#yes3-fmapr-rapidentry-object-event").val();

            const objectTypeClass = ( object_type==="field" ) ? "yes3-object-field" : "yes3-object-form";

            const $nameInput = $("input#yes3-fmapr-rapidentry-object-name");

            // the 'object class' is used to style the input differently for fields vs forms
            $nameInput
                .removeClass("yes3-object-field yes3-object-form")
                .addClass( objectTypeClass )
            ;

            FMAPR.setNewItemModeLabel(); // update the mode label to reflect the object type

            // update the tooltip for the mode label
            FMAPR.setBsToolTipsForNewItemOptions();

            $("input#yes3-fmapr-rapidentry-object-name")
                .val("")
                .autocomplete({
                    source: ( object_type==="field" ) ? FMAPR.getFieldAutoCompleteSource(object_event) : FMAPR.getFormAutoCompleteSource(object_event, false),
                    minLength: 1,
                    position: { my: "left top", at: "left bottom", collision: "flipfit" },
                    select: function(event, ui) {

                        if (!ui.item) {
                            $(this)
                                .val("")
                                .prop("title", "")
                            ;
                            return false;
                        }

                        $(this)
                            .val(ui.item.value)
                            .prop("title", ui.item.label)
                        ;
                        return false;
                    }
                })
                .off("change")
                .on("change", function(){

                    let object_name = $(this).val();
                    //let object_type =$("select#yes3-fmapr-rapidentry-object-type").val();

                    object_name = object_name.trim();

                    if ( !object_name ){

                        FMAPR.rapidEntrySkipper();

                        return false;
                    }

                    //if ( object_name === ALL_FORMS ) object_name = ALL_OF_THEM; // handle the alias

                    if ( object_type==="form" && object_name !== ALL_OF_THEM ){

                        if ( typeof FMAPR.project.form_index[object_name] !== 'number' ) {

                            YES3.hello(`'${$(this).val()}' is not a valid form name.`);
                            $(this).val("");
                        }                        
                    }
                    else if ( object_type==="field" ){

                        if ( typeof FMAPR.project.field_index[object_name] !== 'number' ) {

                            YES3.hello(`'${$(this).val()}' is not a valid field name.`);
                            $(this).val("");
                        }                        
                    }

                    FMAPR.rapidEntrySkipper();
                })
            ;

            FMAPR.rapidEntrySkipper();
        })
    ;

    $("input#yes3-fmapr-rapidentry-object-add")
        .off()
        .on("click", function(){

            let object_type =$("input[type=radio][name=new_object_type]:checked").val();
            let object_event=$("select#yes3-fmapr-rapidentry-object-event").val() || "";
            let object_name=$("input#yes3-fmapr-rapidentry-object-name").val() || "";

            if ( !object_name.trim() ){

                YES3.hello("Please enter a valid name for the item to be added.");
                return false;
            }

            //if ( object_name === ALL_FORMS ) object_name = ALL_OF_THEM; // handle the alias

            // special case: all forms and events
            if ( object_type==="form" && object_name===ALL_OF_THEM && ( !FMAPR.project.is_longitudinal || object_event===ALL_OF_THEM ) ){

                FMAPR.addEverything();
                return true;
            }

            FMAPR.addRapidEntryItem(object_type, object_name, object_event);

            FMAPR.postMessage(`${object_type} '${object_name}' added to export.`, true);

            // set the focus back to the name input
            $("input#yes3-fmapr-rapidentry-object-name")
                .val("")
                .focus()
            ;
        })
    ;

    $("input#yes3-fmapr-rapidentry-object-add-fields")
        .off()
        .on("click", function(){

            let object_type =$("input[type=radio][name=new_object_type]:checked").val();
            let object_event=$("select#yes3-fmapr-rapidentry-object-event").val() || "";
            let object_name=$("input#yes3-fmapr-rapidentry-object-name").val() || "";

            if ( !object_name.trim() ){

                YES3.hello("Please enter a valid name for the item to be added.");
                return false;
            }

            //if ( object_name === ALL_FORMS ) object_name = ALL_OF_THEM; // handle the alias

            // special case: all forms and events
            if ( object_type==="form" && object_name===ALL_OF_THEM && ( !FMAPR.project.is_longitudinal || object_event===ALL_OF_THEM ) ){

                FMAPR.addEverything( true );
                return true;
            }

            FMAPR.addRapidEntryItem(object_type, object_name, object_event, true);

            FMAPR.postMessage(`${object_type} '${object_name}' added to export.`, true);

            // set the focus back to the name input
            $("input#yes3-fmapr-rapidentry-object-name")
                .val("")
                .focus()
            ;
        })
    ;

    $("input#yes3-fmapr-rapidentry-object-cancel")
        .off()
        .on("click", function(){

            FMAPR.renderNewItemOptionsSectionDefault();
        })
    ;

    // set the autocomplete source for the name input
    $("input[type=radio][name=new_object_type]:checked").trigger("change");
}

FMAPR.rapidEntrySkipper = function(){

    const objectType =$("input[type=radio][name=new_object_type]:checked").val();
 
    const $fieldName = $("input#yes3-fmapr-rapidentry-field_name");
    const $formName = $("select#yes3-fmapr-rapidentry-form_name");

    const objectName = ( objectType==="field" ) ? $fieldName.val() : $formName.val();

    const objectEvent=$("select#yes3-fmapr-rapidentry-object-event").val();

    const $addAsFormButton = $("input#yes3-fmapr-rapidentry-object-add-form");
    const $addAsFormsButton = $("input#yes3-fmapr-rapidentry-object-add-forms");
    const $addAsFieldButton = $("input#yes3-fmapr-rapidentry-object-add-field");
    const $addAsFieldsButton = $("input#yes3-fmapr-rapidentry-object-add-fields");

    //console.warn("rapidEntrySkipper", objectType, objectName, objectEvent);

    if ( !objectName || !objectType || (FMAPR.project.is_longitudinal && !objectEvent) ){

        $addAsFormsButton.hide();
        $addAsFieldsButton.hide();
        $addAsFormButton.hide();
        $addAsFieldButton.hide();

        return false;
    }

    if ( objectType==="form" && objectName !== ALL_OF_THEM ){

        $addAsFieldButton.hide();
        $addAsFormsButton.hide();
        $addAsFormButton.show();
        $addAsFieldsButton.show();
    }
    else if ( objectType==="form" && objectName === ALL_OF_THEM ){

        $addAsFieldButton.hide();
        $addAsFormButton.show();
        $addAsFormsButton.show();
        $addAsFieldsButton.show();
    }
    else if ( objectType==="field" ){

        $addAsFormsButton.hide();
        $addAsFieldsButton.hide();
        $addAsFormButton.hide();
        $addAsFieldButton.show();
    }
}

FMAPR.addRapidEntryItem = function( object_type, object_name, object_event, allFieldsForForm, allFormsForEvent ){

    allFieldsForForm = allFieldsForForm || false;
    allFormsForEvent = allFormsForEvent || false;

    //if ( object_name === ALL_FORMS ) object_name = ALL_OF_THEM; // handle the alias

    // output all parms (by name) to the console
    //console.warn("addRapidEntryItem parms:", {
    //    object_type: object_type,
    //    object_name: object_name,
    //    object_event: object_event,
    //    allFieldsForForm: allFieldsForForm,
    //    allFormsForEvent: allFormsForEvent
    //});

    let saveResult = null;

    // parms for inserting above the editor row
    const mode = FMAPR.getNewItemMode(); // "insert" or "append" depending on the selected row count
    const yes3_fmapr_data_element_name = "";
    const theRowBeforeWhich = (mode === "insert") ? $('tr.yes3-row-selected') : null;
    const itemsContainer = $('tbody#yes3-fmapr-export-items-tbody')[0];

    if ( !object_type || (FMAPR.project.is_longitudinal && !object_event) || !object_name ){

        YES3.hello("Please enter all fields for this item (type, event, name).");
        return false;
    }

    if ( FMAPR.exportItemAlreadyExists(object_type, object_name, object_event) ){

        YES3.hello("No can do: this item is already included in this export.");
        return false;
    }

    // clear any tooltips
    YES3.clearBsTooltipsForContainer( itemsContainer );

    if ( object_type === "field" ){

        saveResult = FMAPR.exportItemEditorSave_field(object_name, object_event, theRowBeforeWhich, yes3_fmapr_data_element_name, mode);
    }
    else if ( object_type === "form" ){

        if ( allFieldsForForm ) {

            saveResult = FMAPR.exportItemEditorSave_fields(object_name, object_event, theRowBeforeWhich, yes3_fmapr_data_element_name, mode);

        } 
        else if ( allFormsForEvent ) {

            saveResult = FMAPR.exportItemEditorSave_forms(object_name, object_event, theRowBeforeWhich, yes3_fmapr_data_element_name, mode);
        }
        else {

            saveResult = FMAPR.exportItemEditorSave_form(object_name, object_event, theRowBeforeWhich, yes3_fmapr_data_element_name, mode);
        }
    }

    //YES3.debugMessage("save result:", saveResult);

    FMAPR.renumberRows();

    FMAPR.establishItemTableColumns(); // hide the dummy row if indicated

    FMAPR.markAsDirty("Be sure to save your changes ( * - unsaved).");

    // populate the table header, now that we have items
    FMAPR.populateExportItemsTableHead();

    FMAPR.resizeExportItemsTable();

    FMAPR.scrollExportItemsTableToBottom();

    FMAPR.resetExportItemEditor();

    FMAPR.warnIfMixedRepeating();

    // rebuild any tooltips
    YES3.setBsTooltipListenersForContainer( itemsContainer );

    //YES3.notBusy();
}

FMAPR.everythingIsAdded = function(){

    let itemRows = FMAPR.getExportItemRows();

    if ( itemRows.length !== 1 ) return false;

    let theItemRow = itemRows.eq(0);

    let object_type = theItemRow.data('object_type');
    let object_name = theItemRow.data('object_name');
    let object_event = ALL_OF_THEM;

    //if ( object_name === ALL_FORMS ) object_name = ALL_OF_THEM; // handle the alias

    if ( FMAPR.project.is_longitudinal ){

        object_event = theItemRow.data('object_event');
    }

    return ( object_type==="form" && object_name===ALL_OF_THEM ) ? true : false;
}

FMAPR.addEverything = function( asFields, asForms )
{
    let K = FMAPR.getExportItemRowCount() || 0;

    asFields = asFields || false;
    asForms = asForms || false;

    if ( K > 0 ){

        if ( asFields ){
            YES3.YesNo(
                `WARNING: You are about to add all fields individually (and events if applicable) to the export, but this will cause your current ${K} item(s) to be discarded. Are you sure that you would like to proceed?`,
                FMAPR.addEverything_Yes_Fields
            );
        } 
        else if ( asForms ){
            YES3.YesNo(
                `WARNING: You are about to add all forms individually (and events if applicable) to the export, but this will cause your current ${K} item(s) to be discarded. Are you sure that you would like to proceed?`,
                FMAPR.addEverything_Yes_Forms
            );
        }
        else {
            YES3.YesNo(
                `WARNING: You are about to add all forms (and events if applicable) to the export, but this will cause your current ${K} item(s) to be discarded. Are you sure that you would like to proceed?`,
                FMAPR.addEverything_Yes
            );
        }
    }
    else {

        FMAPR.addEverything_Yes( asFields, asForms );
    }
}

FMAPR.addEverything_Yes_Fields = function(){

    FMAPR.addEverything_Yes( true, false );
}

FMAPR.addEverything_Yes_Forms = function(){

    FMAPR.addEverything_Yes( false, true );
}

FMAPR.addEverything_Yes = function( asFields, asForms )
{
    asFields = asFields || false;
    asForms = asForms || false;
    
    FMAPR.getExportItemRows().remove();

    FMAPR.addRapidEntryItem("form", ALL_OF_THEM, ALL_OF_THEM, asFields, asForms );

    FMAPR.markColumnCountChanged();
    
    //FMAPR.ensureNewItemRowAtEndV2(); // to ensure the rapid entry form is suppressed
}

FMAPR.getNewFieldRow = function()
{
    return $(`tr#${FMAPR.rapidEntryFormRowId}`);
}

FMAPR.exportItemRowEventLabel = function(event)
{
    if ( !FMAPR.project.is_longitudinal ) {

        return "--"; // n/a
    }

    if ( event===ALL_OF_THEM ){

        return "all events";
    }

    // event may have been deleted
    if ( !FMAPR.project.event_metadata[event] ){

        return `event missing!`;
    }

    let event_label = FMAPR.project.event_metadata[event].event_label || "event " + FMAPR.project.event_metadata[event].event_id;

    if ( FMAPR.project.event_metadata[event].event_repeating ){

        event_label += " R";
    }

    return event_label;
}


FMAPR.exportItemRowEventTooltip = function(event)
{
    if ( !FMAPR.project.is_longitudinal ) {

        return "--"; // n/a
    }

    if ( event===ALL_OF_THEM ){

        return "This export item is from ALL events.";
    }

    // event may have been deleted
    if ( !FMAPR.project.event_metadata[event] ){

        return 'Error constructing event tooltip: event metadata missing.';
    }

    let tooltip = "event name: " + FMAPR.project.event_metadata[event].event_name + "<br/>" +
                  "event id: " + event
    ;

    if ( FMAPR.project.event_metadata[event].event_repeating ) {

        tooltip += "<br />Note: This event is repeating.";
    }

    return tooltip;
}

FMAPR.addREDCapForm = function( form_name, event, theRowBeforeWhich )
{   
    theRowBeforeWhich = theRowBeforeWhich || {};

    if ( form_name === ALL_FORMS ) form_name = ALL_OF_THEM; // handle the alias

    let form_index = FMAPR.project.form_index[form_name];

    if ( typeof form_index !== "number" && form_name !== ALL_OF_THEM ){
        return false;
    }
     
    let $fmaprBody = $('table.yes3-fmapr-specification').first().find('tbody');

    let yes3_fmapr_data_element_name = FMAPR.RawREDCapDataElementName(0);

    let rowId = FMAPR.dataElementRowId(yes3_fmapr_data_element_name);

    let eventSelectHtml  = FMAPR.getFormEventHtml( form_name, yes3_fmapr_data_element_name );

    let field_count = (form_name===ALL_OF_THEM) ? FMAPR.project.field_metadata.length : FMAPR.project.form_metadata[form_index].form_fields.length;

    //let elementInputHtml = FMAPR.getElementInputHtml( yes3_fmapr_data_element_name, 'redcap');
    //let eventSelectHtml  = FMAPR.getElementEventHtml( yes3_fmapr_data_element_name, 'redcap');

    let html = `<tr class='yes3-fmapr-redcap-form yes3-fmapr-data-element yes3-fmapr-sortable' data-yes3_fmapr_data_element_name="${yes3_fmapr_data_element_name}" data-yes3_fmapr_data_element_description="REDCap form" id="${rowId}" data-required="0" data-element_origin="redcap" data-object_type="form" data-form_name="${form_name}">`;
    html += `<td class='yes3-fmapr-row-number' title='${FMAPR.tooltips.row_selector}'>&nbsp;</td>`;
    html += `<td class='yes3-3 yes3-td-left' title="REDcap form"><span class='yes3-fmapr-redcap-element'>${yes3_fmapr_data_element_name}</span></td>`;
    html += `<td class='yes3-3 yes3-td-middle'>up to ${field_count} fields</td>`;
    html += `<td class='yes3-3 yes3-td-middle'><span class="yes3-fmapr-horizontal-only-xxx">${eventSelectHtml}</span></td>`;
    html += `<td class='yes3-gutter-right-top yes3-td-right'><i class='far fa-trash-alt' onclick='FMAPR.removeDataElement("${yes3_fmapr_data_element_name}");' title='${FMAPR.tooltips.row_trashcan}'></i></td>`;
    html += "</tr>";

    /**
     * If there is no "row before" the table has no rows so we append.
     */
    if ( $.isEmptyObject(theRowBeforeWhich) ){
        $fmaprBody.append( html );
    }
    else {
        $( html ).insertAfter( theRowBeforeWhich );
    }

    let elementRow = $(`tr.yes3-fmapr-data-element[data-yes3_fmapr_data_element_name='${yes3_fmapr_data_element_name}']`);

    let itemREDCapEvent = elementRow.find('select[data-mapitem=redcap_event_id]:first');

    FMAPR.REDCapEventOnChange(itemREDCapEvent);

    /**
     * handling for case where 'all events' is requested but there is just one event for the form
     */
    if ( event===ALL_OF_THEM && itemREDCapEvent.find('option').length===1 ){

        itemREDCapEvent.val( itemREDCapEvent.find('option').first().val() );
    }
    else {

        itemREDCapEvent.val(event);
    }

    return yes3_fmapr_data_element_name;
}

YES3.Functions.saveExportSpecification = function(auditOnly) 
{
    auditOnly = auditOnly || false;
    
    let settingsContainer = FMAPR.getExportSettingsContainer();

    FMAPR.reloadParms.export_uuid = FMAPR.getExportUUID();

    let specItems = {};

    let setting = "";

    let postParams = {
        "request": "saveExportSpecification",
        "export_uuid": FMAPR.getExportUUID(),
        "export_items_json": FMAPR.getExportItemsJSONV2(),
        "column_count": FMAPR.column_count,
        "export_has_repeatables": FMAPR.export_has_repeatables,
        "column_count_changed": FMAPR.columnCountChanged,
        "convert_to_vertical": FMAPR.convertToVertical, // to accomodate deprecated repeating layouts
    }

    FMAPR.markAllGood(); // clear errors (yes3-error)

    FMAPR.clearMessage();

    settingsContainer.find('input, select, textarea:not([data-setting=export_uspec_json])').each( function(){

        if ( $(this).attr('data-setting') ){

            setting = $(this).attr('data-setting');

            if ( typeof specItems[setting] === 'undefined' ){

                specItems[setting] = {
                    "completed": false, 
                    "visible": $(this).is(":visible"), 
                    "optional": $(this).hasClass("yes3-optional"),
                    "value": ""
                };
            }

            if ( $(this).attr('type') === 'checkbox' ) {

                specItems[setting].completed = true;
                specItems[setting].value = ( $(this).is(':checked') ) ? "1" : "0";
            }
            else if ( $(this).attr('type') !== 'radio' || $(this).is(':checked') ){

                specItems[setting].value = $(this).val() || '';
                specItems[setting].completed = ( specItems[setting].value.length );
             }
        }
    });

    /**
     * each setting becomes a POST parameter for the save request
     */
    for (const settingName in specItems){

        if ( typeof postParams[settingName] === "undefined" ){

            postParams[settingName] =  specItems[settingName].value;
        }

        if ( specItems[settingName].visible && !specItems[settingName].optional && !specItems[settingName].completed ) {

            FMAPR.markAsBad( settingsContainer.find(`[data-setting=${settingName}]`) );
        }
    }

    if ( FMAPR.someBadSettings() ){

        YES3.hello("WARNING: at least one error was detected (a required entry is blank, an invalid entry etc). You must fix the indicated error(s) before you may save the export specification.");

        FMAPR.postMessage("You must fix the indicated error(s) before adding fields or saving.", true);

        FMAPR.markAsDirty("Please fix the indicated error(s) before saving.");

        return true;
    }
 
    if ( !auditOnly ){

        YES3.isBusy( YES3.captions.wait_saving_specification );

        //console.warn( 'saveExportSpecification: postParams', postParams );
        //console.warn( 'saveExportSpecification: specItems', specItems );
       
        YES3.requestService( postParams, FMAPR.saveExportSpecificationCallback, false );
    }
 }
 
FMAPR.saveExportSpecificationCallback = function( response ){
  
    //console.warn( 'saveExportSpecificationCallback', response );

    //YES3.notBusy();

    FMAPR.postMessage( response, true );

    if ( response.indexOf('Success') > -1 ){

        FMAPR.markAsClean(true);
    }

    // reload the specification
    FMAPR.getExportNames( FMAPR.loadSpecification );
}

/*** WAYBACK ***/

YES3.Functions.Wayback_openForm = function()
{
    YES3.openPanel('yes3-fmapr-wayback-panel');

    let wrapper = FMAPR.getExportSettingsContainer();
    //let wrapper = $("div#yes3-container");

    let y = wrapper.offset().top;

    let wayback = $('div#yes3-fmapr-wayback-panel');

    wayback.css('opacity', 0).animate(
        {
            top: y,
            opacity: 1
        }, 
        2000, 
        function() {

        }
    );

    $({deg: 0}).animate({deg: 360}, {
        duration: 2000,
        step: function(now) {
            // in the step-callback (that is fired each step of the animation),
            // you can use the `now` paramter which contains the current
            // animation-position (`0` up to `angle`)
            wayback.css({
                transform: 'rotate(-' + now + 'deg)'
            });

            wrapper.css({
                transform: 'rotate(' + now + 'deg)'
            });
        }
    });

    YES3.requestService(
        {
            'request': 'get_wayback_html',
            'export_uuid': FMAPR.getExportUUID()
        }, FMAPR.Wayback_openFormCallback
    );
}

FMAPR.Wayback_openFormCallback = function( response )
{
    //YES3.debugMessage('Wayback_openFormCallback', response);
    $("select#yes3-fmapr-wayback-select").empty().append(response);

    FMAPR.Wayback_Buttons();
}

FMAPR.Wayback_Buttons = function(){

    let log_id = $("select#yes3-fmapr-wayback-select").val();
    if ( !log_id ){

        $("input#yes3-fmapr-wayback-restore").css('visibility', 'hidden');
    }
    else {

        $("input#yes3-fmapr-wayback-restore").css('visibility', 'visible');
    }
}

FMAPR.Wayback_Execute = function()
{
    let log_id = $("select#yes3-fmapr-wayback-select").val();

    FMAPR.reloadParms.wayback = true;

    FMAPR.loadSpecification(log_id);

    FMAPR.Wayback_closeForm();
}

FMAPR.Wayback_closeForm = function(log_id)
{
    log_id = log_id || 0;

    let y = $(window).innerHeight()/2;

    let wayback = $('div#yes3-fmapr-wayback-panel');

    wayback.animate(
        {
            top: y,
            opacity: 0
        }, 
        2000, 
        function() {
            YES3.closePanel('yes3-fmapr-wayback-panel');

        }
    );

    $({deg: 0}).animate({deg: 360}, {
        duration: 2000,
        step: function(now) {
            // in the step-callback (that is fired each step of the animation),
            // you can use the `now` paramter which contains the current
            // animation-position (`0` up to `angle`)
            wayback.css({
                transform: 'rotate(' + now + 'deg)'
            });
        }
    });

    if ( log_id ){

        YES3.hello("The selected backup has been loaded. You must now save it if you want to accept it as the export specification.");
    }
}
 
 FMAPR.specificationTableBodyId = function(export_uuid)
 {
     return 'yes3-fmapr-tbody-' + export_uuid;
 }
 
FMAPR.specificationSelect = function()
{
    let export_uuid = $('select#export_uuid').val();

    if ( export_uuid.length ){

        //YES3.debugMessage('specificationSelect', FMAPR.specification_index, FMAPR.exportSpecification, export_uuid);

    }
}
 
FMAPR.specificationSave = function()
{
    let timestamp = new Date().ymdhms();
    FMAPR.postMessage("Specification saved at " + timestamp + ".");
}

FMAPR.dataElementRowId = function(data_element_name)
{
    return `yes3_fmapr_data_element-${data_element_name}`;
}

FMAPR.markAsBuildInProgress = function()
{
    YES3.contentLoaded = false;
    FMAPR.markAsClean();
    FMAPR.clearMessage();
}

FMAPR.markAsBuildCompleted = function()
{
    YES3.contentLoaded = true;
}

FMAPR.buildIsInProgress = function()
{
    return !YES3.contentLoaded;
}

FMAPR.rowsToMove = [];

FMAPR.makeSortable = function( parentElement )
{
    parentElement.sortable({
        items: 'tr.yes3-fmapr-sortable:not(.yes3-fmapr-new-field)',
        cursor: 'grab',
        axis: 'y',
        dropOnEmpty: false,
        start: function (e, ui) {
            ui.item.addClass("yes3-fmapr-row-selected");
            let data_element_name = ui.item.data('yes3_fmapr_data_element_name');
            FMAPR.rowsToMove = $(`tr.yes3-fmapr-lov[data-yes3_fmapr_data_element_name='${data_element_name}']`);
        },
        stop: function (e, ui) {
            ui.item.removeClass("yes3-fmapr-row-selected");
            //YES3.debugMessage('sortable', FMAPR.rowsToMove);
            for (let j=FMAPR.rowsToMove.length; j>0; j--){
                ui.item.after(FMAPR.rowsToMove[j-1]);
            }
            FMAPR.markAsDirty();
            FMAPR.renumberRows();
            // for some drag/drop/remove discombobulates the new item row
            //FMAPR.ensureNewItemRowAtEndV2();
            FMAPR.resizeExportItemsTable();
        }
    });

}

FMAPR.nowLoaded = function()
{
    $('.yes3-when-loaded').show();
}

FMAPR.nowUnLoaded = function()
{
    $('.yes3-when-loaded').hide();
}
  
FMAPR.RawREDCapDataElementName = function(n) 
{
    if ( n===0 ){
        FMAPR.maxRawREDCapDataElementNumber++;
        n = FMAPR.maxRawREDCapDataElementNumber;
    }
    return 'redcap_element_' + n;
}

FMAPR.isRawREDCapDataElement = function( elementName )
{
    return ( elementName.substring(0, 15) === 'redcap_element_'  );
}
  
FMAPR.RawREDCapDataElementNumber = function( elementName ) 
{
    if ( !FMAPR.isRawREDCapDataElement(elementName)) {
        return 0;
    }

    return parseInt(elementName.split('_')[2]);
}

FMAPR.doExportItemsTableHousekeeping = function( isClean )
{
    isClean = isClean || false;

    FMAPR.resizeExportItemsTable();

    if ( isClean ){
        FMAPR.markAsClean();
        FMAPR.markAsBuildCompleted();
        FMAPR.clearMessage();
        YES3.displayActionIcons();
        FMAPR.displayActionIcons();
    }
}

FMAPR.removeDataElement = function(element_name)
{
    // remove any attache tooltips
    $(`tr[data-yes3_fmapr_data_element_name='${element_name}']`).find('[data-bs-toggle="tooltip"]').tooltip('dispose');
    
    $(`tr[data-yes3_fmapr_data_element_name='${element_name}']`).remove();

    FMAPR.markAsDirty();

    FMAPR.markColumnCountChanged();
    // enforce a refresh in case this is a repeating export layout

    YES3.displayActionIcons();
    FMAPR.displayActionIcons();
    FMAPR.renumberRows();
    FMAPR.setRepeatLayoutConstraints();
    FMAPR.warnIfMixedRepeating();
    FMAPR.renderNewItemOptionsSection( true ); // re-render the new item section
}

FMAPR.scrollExportItemsTableToBottom = function()
{
    let tbl = FMAPR.getExportItemsTable();

    let bodyId = tbl.find('tbody').attr('id');

    let domObj = document.getElementById(bodyId);
    domObj.scrollTop = domObj.scrollHeight; 
}

FMAPR.scrollExportItemsTableToNewField = function()
{
    FMAPR.scrollExportItemsTableToBottom();

    $('tr.yes3-fmapr-new-field input.yes3-fmapr-input-element').focus();
}

FMAPR.resizeExportItemsTable = function()
{
    const $fmaprTable = FMAPR.getExportItemsTable();

    if ( !$fmaprTable.length ){

        return false;
    }

    if ( !$fmaprTable.is(':visible') ){

        return true;
    }

    const gutterWidth = 30;
    const scrollbarWidth = 20;

    const $fmaprFooter = FMAPR.getExportEditorFooter();
    const $fmaprWrapper = FMAPR.getExportItemsTableWrapper();

    const $newItemsSection = FMAPR.getExportNewItemOptionsSection();

    const $fmaprTableBody = $fmaprTable.find('tbody');
    const $fmaprTableHeader = $fmaprTable.find('thead');

    const windowHeight = $(window).innerHeight();

    //const $pageFooter = $('div#yes3-fmapr-page-footer');

    const bodyHeight = windowHeight 
        - $fmaprTableBody.offset().top
        - $fmaprFooter.outerHeight()
        - $newItemsSection.outerHeight()
        //- $pageFooter.outerHeight()
        - scrollbarWidth  
    ;

    const tableWrapperHeight = windowHeight 
        - $fmaprWrapper.offset().top
        - $fmaprFooter.outerHeight()
        //- $newItemsSection.outerHeight()
        - scrollbarWidth
    ;

    //let tableWidth = $('div#yes3-fmapr-wrapper').width();
    const tableWidth = $fmaprTable.width();

    const rowNumberWidth = 40;
    const editorLinkWidth = 30;
    const trashcanWidth = 30;
    const objectTypeWidth = 60;
    const remainingWidth = tableWidth - scrollbarWidth - rowNumberWidth - editorLinkWidth - objectTypeWidth - trashcanWidth;
    const objectEventNameWidth = (FMAPR.project.is_longitudinal) ? 0.4 * remainingWidth : 0;
    const objectNameWidth = remainingWidth - objectEventNameWidth;

    $fmaprTableBody.css({'height': 'auto', 'max-height': bodyHeight+'px'});

    $fmaprWrapper.css({'height': tableWrapperHeight+'px'});

    let c = 5; // the default number of columns

    let thWidths = [rowNumberWidth, editorLinkWidth, objectTypeWidth];

    $fmaprTable.find('.yes3-fmapr-row-number').css({'width': rowNumberWidth+'px', 'min-width': rowNumberWidth+'px', 'max-width': rowNumberWidth+'px'});
    $fmaprTable.find('.yes3-fmapr-redcap-object-editor').css({'width': editorLinkWidth+'px', 'min-width': editorLinkWidth+'px', 'max-width': editorLinkWidth+'px'});
    $fmaprTable.find('.yes3-fmapr-redcap-object-type').css({'width': objectTypeWidth+'px', 'min-width': objectTypeWidth+'px', 'max-width': objectTypeWidth+'px'});

    if ( FMAPR.project.is_longitudinal ){
        c++; // one more column for event
        thWidths.push(objectEventNameWidth);
        $fmaprTable.find('.yes3-fmapr-redcap-object-event').css({'width': objectEventNameWidth+'px', 'min-width': objectEventNameWidth+'px', 'max-width': objectEventNameWidth+'px'});
    }

    thWidths.push(objectNameWidth);
    thWidths.push(trashcanWidth);

    $fmaprTable.find('.yes3-fmapr-redcap-object-name').css({'width': objectNameWidth+'px', 'min-width': objectNameWidth+'px', 'max-width': objectNameWidth+'px'});
    $fmaprTable.find('.yes3-fmapr-trashcan').css({'width': trashcanWidth+'px', 'min-width': trashcanWidth+'px', 'max-width': trashcanWidth+'px'});
}

FMAPR.resizeExportSettingsContainer = function(){

    const $fmaprContainer = FMAPR.getExportSettingsContainer();

    if ( !$fmaprContainer.length ){

        return false;
    }

    if ( !$fmaprContainer.is(':visible') ){

        return true;
    }

    const scrollbarWidth = 20;

    const $fmaprFooter = FMAPR.getExportEditorFooter();

    const windowHeight = $(window).innerHeight();

    const conHeight = windowHeight 
        - $fmaprContainer.offset().top
        - $fmaprFooter.outerHeight()
        - scrollbarWidth  
    ;

    $fmaprContainer.css({'height': conHeight+'px'});
}

 FMAPR.normalizeString = function( s ){
    if ( typeof s !== 'string' ) {
       return s;
    }
    return s.replace(/[^a-zA-Z0-9]/g,'_').toLowerCase();
 }
 
 FMAPR.specificationTableId = function( export_uuid )
 {
    return 'specification-' + export_uuid;
 }
 
 FMAPR.elementInputId = function( yes3_fmapr_data_element_name ){
    return 'element-' + FMAPR.normalizeString(yes3_fmapr_data_element_name);
 }
 
 FMAPR.elementEventId = function( yes3_fmapr_data_element_name ){
    return 'element-' + FMAPR.normalizeString(yes3_fmapr_data_element_name + '-event');
 }
 
 FMAPR.lovInputId = function( yes3_fmapr_data_element_name, value ){
    return 'lov' +
       '-' + FMAPR.normalizeString(yes3_fmapr_data_element_name) +
       '-' + FMAPR.normalizeString(value)
       ;
 }
 
 FMAPR.pickerWrapperId = function(yes3_fmapr_data_element_name, yes3_fmapr_lov_value ){
    return 'picker' + 
       '-' + FMAPR.normalizeString(yes3_fmapr_data_element_name) +
       '-' + FMAPR.normalizeString(yes3_fmapr_lov_value)
       ;
 }
 
 FMAPR.typeInputId = function(){
    return 'type';
 }

 FMAPR.itemTypeClass = function(element_origin)
 {
     return ( element_origin==='redcap' ) ? 'yes3-fmapr-redcap' : 'yes3-fmapr-specification';
 }
 
 FMAPR.getElementInputHtml = function( yes3_fmapr_data_element_name, element_origin ){
    element_origin = element_origin || 'specification';
    let typeClass = FMAPR.itemTypeClass(element_origin);
    let id = FMAPR.elementInputId( yes3_fmapr_data_element_name );
    let html =`<input type='text' id=${id} class='yes3_fmapr_field_autocomplete ${typeClass} yes3-fmapr-input-element yes3-fmapr-item' data-yes3_fmapr_data_element_name='${yes3_fmapr_data_element_name}' data-mapitem='redcap_field_name' data-element_origin='${element_origin}' placeholder='Start typing or spacebar for all' />`;
    return html;
 }
 
FMAPR.getElementEventHtml = function( yes3_fmapr_data_element_name, element_origin ){
    element_origin = element_origin || 'specification';
    let typeClass = FMAPR.itemTypeClass(element_origin);
    let id = FMAPR.elementEventId( yes3_fmapr_data_element_name );
    //let html = `<select id=${id} class='yes3-fmapr-event-select yes3-fmapr-item' data-export_uuid='${export_uuid}' data-yes3_fmapr_data_element_name='${yes3_fmapr_data_element_name}' data-mapitem='redcap_event_id'/>${FMAPR.project.event_select_options_html}</select>`;
    let html = `<select id=${id} class='yes3-fmapr-event-select ${typeClass} yes3-fmapr-item yes3-fmapr-field-event' data-yes3_fmapr_data_element_name='${yes3_fmapr_data_element_name}' data-mapitem='redcap_event_id' data-element_origin='${element_origin}' title="${FMAPR.tooltips['event_select']}"/></select>`;
    return html;
}
 
FMAPR.getFormEventHtml = function( form_name, yes3_fmapr_data_element_name ){

    let element_origin = 'redcap';

    let optionsHtml = '';

    let formEvents = [];

    if ( form_name === ALL_FORMS ) form_name = ALL_OF_THEM; // handle the alias

    if ( form_name === ALL_OF_THEM ){

        formEvents = FMAPR.project.project_event_metadata;
    }
    else {

        let form_index = FMAPR.project.form_index[form_name];

        if ( typeof form_index === "number" ){

            formEvents = FMAPR.project.form_metadata[form_index].form_events;
        }
    }

    if ( formEvents.length > 1 ){

        optionsHtml = `<option value="${ALL_OF_THEM}">all events for form</option>`;
    }

    for ( let e=0; e<formEvents.length; e++ ){
        optionsHtml += `<option value=${formEvents[e].event_id}>${formEvents[e].event_label}</option>`;                 
    }

    let typeClass = FMAPR.itemTypeClass(element_origin);

    let id = FMAPR.elementEventId( form_name );

    let html = `<select id=${id} class='yes3-fmapr-event-select ${typeClass} yes3-fmapr-item yes3-fmapr-form-event' data-export_uuid='${FMAPR.getExportUUID()}' data-yes3_fmapr_data_element_name='${yes3_fmapr_data_element_name}' data-mapitem='redcap_event_id' data-element_origin='${element_origin}'/>${optionsHtml}</select>`;
    
    return html;
}

/**
 * returns select options html for all events
 */
FMAPR.getAllEventOptionsHtml = function()
{
    let events = FMAPR.project.project_event_metadata;
    let optionsHtml = "";

    if ( !events || !Array.isArray(events) || events.length === 0 ){
        return optionsHtml;
    }

    if ( events.length > 1 ){

        optionsHtml = `<option value="${ALL_OF_THEM}">ALL EVENTS</option>`;
    }

    for ( let e=0; e<events.length; e++ ){
        optionsHtml += `<option value=${events[e].event_id}>${events[e].event_label}</option>`;                 
    }

    return optionsHtml;
}

FMAPR.formApprovedForLayout = function( form_name )
{
    try {

        let form_index = FMAPR.project.form_index[form_name];
        let form = FMAPR.project.form_metadata[form_index];
        
        // repeating layout
        if ( FMAPR.exportSpecification.export_layout==="r" ){

            if ( !form.form_repeating ){

                return false;
            }

            // okay, form is repeating but is it the right form for this export?
            if ( FMAPR.formNameConstraint.length > 0 && FMAPR.formNameConstraint !== form_name ){

                return false;
            }

            return true;
        }
        // not repeating layout, only pass non-repeating forms
        else {

            if ( form.form_repeating ){

                // return false;
            }

            return true;
        }

    } catch (e) {

        console.error("formApprovedForLayout ERROR", form_name, e);

        return false;
    }
}

FMAPR.getFormOptionsHtmlForEvent = function( event )
{
    event = event || ALL_OF_THEM;

    let forms = FMAPR.project.form_metadata;
    let optionsHtml = "";
    let accepted = false;
    let j = 0;
    let kount = 0;

    //YES3.debugMessage("getFormOptionsHtmlForEvent", event);

    for ( let i=0; i<forms.length; i++ ){

        // first make sure the form is appropriate for the layout
        if ( !FMAPR.formApprovedForLayout( forms[i].form_name )){

            //YES3.debugMessage("-->failed by formApprovedForLayout: ", event, forms[i].form_name);
            continue;
        }

        accepted = false;

        // bypass event confirmation is project is not longitudinal
        if ( event===ALL_OF_THEM || !FMAPR.project.is_longitudinal ){

            accepted = true;
        }
        // accept if form is defined on the event 
        else {

            for (j=0; j<forms[i].form_events.length; j++){

                if ( forms[i].form_events[j].event_id === event ){

                    accepted = true;
                    break;
                }
            }
        }

        if ( accepted ){

            kount++;
            optionsHtml += `<option value=${forms[i].form_name}>${forms[i].form_label}</option>`;    
        }             
    }

    if ( kount > 1 && !FMAPR.isRepeatedLayout() ){

        optionsHtml = `<option value='${ALL_OF_THEM}'>all forms</option>` + optionsHtml;
    }

    return optionsHtml;
}

FMAPR.getFieldAutoCompleteSource = function(event)
{
    event = event || ALL_OF_THEM;

    let accepted = false;
    let form_name = "";
    let field_label = "";
    let form_index = 0;
    let j = 0;
    let events = [];
    let acSource = [];

    for (let i=0; i<FMAPR.project.field_metadata.length; i++){

        form_name = FMAPR.project.field_metadata[i].form_name;

        // first make sure the form is appropriate for the layout
        if ( !FMAPR.formApprovedForLayout( form_name )){

            continue;
        }

        accepted = false;

        try {

            form_index = FMAPR.project.form_index[form_name];
            events = FMAPR.project.form_metadata[form_index].form_events;

            // no need to check events if project is not longitudinal, or if events is "all"
            if ( event === ALL_OF_THEM || !FMAPR.project.is_longitudinal ){

                accepted = true;
            }
            else {
                for (j=0; j<events.length; j++){

                    if ( events[j].event_id===event ){

                        accepted = true;
                        break;
                    }
                }
            }
        } catch(e){

            console.error("getFieldAutoCompleteSource ERROR", event, e);
            accepted = false;
        }

        if ( accepted ){

            field_label = "[" + FMAPR.project.field_metadata[i].field_name + "]";

            if ( FMAPR.project.form_metadata[form_index].form_repeating ){
                field_label += "R";
            }

            field_label += " " + FMAPR.project.field_metadata[i].field_label;

            acSource.push({
                "value": FMAPR.project.field_metadata[i].field_name,
                "label": field_label
            });
        }
    }

    return acSource;
}


FMAPR.getFormAutoCompleteSource = function(event, suppressAllForms)
{
    event = event || ALL_OF_THEM;

    suppressAllForms = suppressAllForms || false;

    let accepted = false;
    let form_name = "";
    let form_label = "";
    let j = 0;
    let events = [];
    let acSource = [];

    let allFormsLabel = "ALL FORMS";

    if ( FMAPR.project.is_longitudinal && event !== ALL_OF_THEM ){
        allFormsLabel = "ALL FORMS FOR EVENT";
    }

    for (let form_index=0; form_index<FMAPR.project.form_metadata.length; form_index++){

        form_name = FMAPR.project.form_metadata[form_index].form_name;
        form_label = FMAPR.project.form_metadata[form_index].form_label;

        if ( FMAPR.project.form_metadata[form_index].form_repeating ){
            form_label += " R";
        }

        // first make sure the form is appropriate for the layout
        if ( !FMAPR.formApprovedForLayout( form_name )){

            continue;
        }

        accepted = false;

        try {

            events = FMAPR.project.form_metadata[form_index].form_events;

            // no need to check events if project is not longitudinal, or if events is "all"
            if ( event === ALL_OF_THEM || !FMAPR.project.is_longitudinal ){

                accepted = true;
            }
            else {
                for (j=0; j<events.length; j++){

                    if ( events[j].event_id===event ){

                        accepted = true;
                        break;
                    }
                }
            }
        } catch(e){

            console.error("getFormAutoCompleteSource ERROR", event, e);
            accepted = false;
        }

        if ( accepted ){

            acSource.push({
                "value": form_name,
                "label":form_label
            });
        }
    }

    if ( acSource.length > 1 && !suppressAllForms ){

        acSource.unshift({
            "value": ALL_OF_THEM,
            "label": allFormsLabel
        });
    }

    //console.warn("getFormAutoCompleteSource", acSource);

    return acSource;
}

FMAPR.getNewItemOptionsFormSelectOptionsHtml = function(){

    const $formSelect = $("select#yes3-fmapr-rapidentry-form_name");
    const objectEvent=$("select#yes3-fmapr-rapidentry-object-event").val();

    const formsAcSource = FMAPR.getFormAutoCompleteSource( objectEvent, false );
    
    let optionsHtml = "<option value=''>-- select form --</option>";
    for ( let i=0; i<formsAcSource.length; i++ ){
        optionsHtml += `<option value='${formsAcSource[i].value}'>${formsAcSource[i].label}</option>`;
    }

    return optionsHtml;
}

FMAPR.getFormForField = function( field_name )
{
    let field_index = FMAPR.project.field_index[field_name];

    if ( typeof field_index === "number" ){

        return FMAPR.project.field_metadata[field_index].form_name;
    }

    return "";
}

FMAPR.rowIsCut = function(theRow)
{
    return theRow.hasClass('yes3-row-cut');
}

FMAPR.cutRow = function(theRow)
{
    theRow.addClass('yes3-row-cut');
}

FMAPR.setRowSelectorListenersV2 = function(){
    FMAPR.getExportItemRows().each(function(){

        FMAPR.setRowSelectorListenerV2( $(this) );
    });
}

FMAPR.setRowSelectorListenerV2 = function( theRow )
{
    theRow.find('td.yes3-fmapr-row-number')
        .off()
        .on("contextmenu", function(e){
            //YES3.debugMessage( 'contextmenu', e );

            FMAPR.REDCapFieldContextMenu($(this), e);

            return false;
        })
        .on("click", function(e){

            //YES3.debugMessage( 'click', e );

            let theRow = $(this).parent();

            if ( e.shiftKey ){

                e.preventDefault();

                //FMAPR.toggleSelected( $(this) );

                if ( FMAPR.isSelectionRangeStart(theRow) && FMAPR.isSelectionRangeEnd(theRow) ) {

                    FMAPR.clearSelectionRange( true );
                }
                else {

                    FMAPR.markRowSelected( theRow );
                    
                    if ( !FMAPR.selectionRangeStartDefined() ){
                        FMAPR.markSelectionRangeStart( theRow );
                    }
                    else {
                        FMAPR.markSelectionRangeEnd( theRow );
                    }

                    FMAPR.markSelectionRange();
                }

                e.stopPropagation();

                //YES3.contextMenuClose();

                return false;
            }
            else if ( e.ctrlKey ){

                e.preventDefault();

                FMAPR.toggleSelected( theRow, true );

                //YES3.contextMenuClose();

                e.stopPropagation();

                return false;
            }
            else {

                if ( FMAPR.cutRowCount() + FMAPR.copiedRowCount() > 0 ) {

                    FMAPR.markInsertionRow( theRow );
                }
                else {

                    // ignore the last 'new field' row
                    if ( !theRow.hasClass('yes3-fmapr-new-field') ){

                        FMAPR.clearSelections( true );
                        FMAPR.markRowSelected( theRow );
                        FMAPR.markSelectionRangeStart( theRow );
                    }
                }
            }

            return true;
            //return false;
        })
    ;

}

FMAPR.setSpecialKeyListeners = function() {

    $(document).keydown(function(e) {

        if ( !e.ctrlKey ){ 
            
            return  true; 
        }

        var ch = String.fromCharCode(e.which).toUpperCase();

        if ( ch === 'X') {

            if ( FMAPR.selectedRowCount()===0 ){

                return true;
            }

            FMAPR.contextMenuCutRowSelections();
        }
        else if ( ch === 'V') {

            if ( FMAPR.selectedRowCount()===0 ){

                return true;
            }

            FMAPR.contextMenuPasteRowSelections();

            //YES3.debugMessage('Ctrl+V pressed');
        }

        else if ( ch === 'Z') {

            if ( FMAPR.selectedRowCount()===0 ){

                return true;
            }

            FMAPR.contextMenuClearRowSelections();

            //YES3.debugMessage('Ctrl+Z pressed');
        }

        else {

            return true;
        }

        e.preventDefault();
    });
}

FMAPR.markSelectionRange = function()
{

    // start by clearing the range while preserving boundaries
    FMAPR.clearSelectionRange();

    let allRows = $('tr.yes3-fmapr-data-element');

    let startRow = $('tr.yes3-selection-range-start').first();
    let endRow = $('tr.yes3-selection-range-end').first();

    if ( !startRow.length || !endRow.length ) {
        return 0;
        //YES3.debugMessage('markSelectionRange: bailing');
    }
    
    let inRange = false;
    let endOfRange = false;
    let row = null;

    for (i=0; i<allRows.length; i++) {
 
        row = $(allRows[i]);

        if ( FMAPR.isSelectionRangeBoundary(row) ){
            if ( !inRange ) {
                inRange = true;
                if ( FMAPR.isSelectionRangeStart(row) && FMAPR.isSelectionRangeEnd(row) ) {
                    endOfRange = true;
                }
            }
            else {
                endOfRange = true;
            }
        }

        if ( inRange ){
            //YES3.debugMessage('markSelectionRange: marking as selected:', row);
            FMAPR.markRowSelected( row );
        }

        if ( endOfRange ){
            break;
        }
       
    }

    return FMAPR.selectedRowCount();
}

FMAPR.clearSelectionRange = function( boundariesToo )
{
    boundariesToo = boundariesToo || false;

    let theSelector = "tr.yes3-row-selected:not(.yes3-row-sticky)";

    if ( !boundariesToo ){
        theSelector += ":not(.yes3-selection-range-start):not(.yes3-selection-range-end)";
    }

    $(theSelector).removeClass('yes3-row-selected');

    if ( boundariesToo ){
        $('tr.yes3-selection-range-start').removeClass('yes3-selection-range-start');
        $('tr.yes3-selection-range-end').removeClass('yes3-selection-range-end');
    }

    FMAPR.clearInsertionRow();
}

FMAPR.removeSelections = function( stickyToo )
{
    $("tr.yes3-row-selected").off().remove();

    FMAPR.clearInsertionRow();

    FMAPR.renumberRows();

    FMAPR.markAsDirty();
    
    FMAPR.setRepeatLayoutConstraints();
}

FMAPR.clearSelections = function( stickyToo )
{
    stickyToo = stickyToo || false;

    FMAPR.clearSelectionRangeBoundaries();

    $("tr.yes3-row-selected")
    .removeClass("yes3-row-selected")
    .removeClass("yes3-row-cut")
    .removeClass("yes3-row-copied")
    ;  

    if ( stickyToo ){

        $("tr.yes3-row-sticky").removeClass("yes3-row-sticky");       
    }

    FMAPR.clearInsertionRow();
    FMAPR.setNewItemModeLabel();
}

FMAPR.setNewItemModeLabel = function(){

    const $newItemActionLabel = $('span.yes3-fmapr-rapidentry-object-mode-label');
    const $newItemAddFieldsButton = $('input#yes3-fmapr-rapidentry-object-add-fields');
    const $newItemAddFormsButton = $('input#yes3-fmapr-rapidentry-object-add-forms');
    const $newItemAddFieldButton = $('input#yes3-fmapr-rapidentry-object-add-field');
    const $newItemAddFormButton = $('input#yes3-fmapr-rapidentry-object-add-form');

    const mode = FMAPR.getNewItemMode().toUpperCase();

    const objectType = FMAPR.getNewObjectType();

    const modeClass = ( mode === "APPEND" ) ? "yes3-fmapr-mode-append" : "yes3-fmapr-mode-insert";

    $newItemActionLabel.text(mode);

    $newItemActionLabel.removeClass("yes3-fmapr-mode-append yes3-fmapr-mode-insert").addClass(modeClass);

    $newItemAddFieldsButton.val(mode+" as individual fields");
    $newItemAddFormsButton.val(mode+" as individual forms");
    $newItemAddFieldButton.val(mode);
    $newItemAddFormButton.val(mode);

    FMAPR.setBsToolTipsForNewItemOptions(); // a tooltip is attached to the label
}

FMAPR.clearSelectionRangeBoundaries = function() {

    $("tr.yes3-selection-range-start").removeClass("yes3-selection-range-start");
    $("tr.yes3-selection-range-end").removeClass("yes3-selection-range-end");
}

FMAPR.clearRowTagsButLeaveSelected = function()
{
    $("tr.yes3-selection-range-start").removeClass("yes3-selection-range-start");
    $("tr.yes3-selection-range-end").removeClass("yes3-selection-range-end");
    $("tr.yes3-row-cut").removeClass("yes3-row-cut");
    $("tr.yes3-row-copied").removeClass("yes3-row-copied");
}

FMAPR.selectionRangeStartDefined = function()
{
    return $('tr.yes3-selection-range-start').length;
}

FMAPR.selectionRangeEndDefined = function()
{
    return $('tr.yes3-selection-range-end').length;
}

FMAPR.isSelectionRangeBoundary = function( ele )
{
    return ( FMAPR.isSelectionRangeStart( ele ) || FMAPR.isSelectionRangeEnd( ele ) );
}

FMAPR.isSelectionRangeStart = function( ele )
{
    return ( ele.hasClass("yes3-selection-range-start") );
}

FMAPR.isSelectionRangeEnd = function( ele )
{
    return ( ele.hasClass("yes3-selection-range-end") );
}

FMAPR.removeSelectionRangeBoundaryMark = function( ele )
{
    if ( ele.hasClass("yes3-selection-range-start") ) {
        ele.removeClass("yes3-selection-range-start")
    }
    if ( ele.hasClass("yes3-selection-range-end") ) {
        ele.removeClass("yes3-selection-range-end")
    }
}

FMAPR.markSelectionRangeStart = function( ele )
{
    $("tr.yes3-selection-range-start").removeClass("yes3-selection-range-start");

    ele.addClass("yes3-selection-range-start");

    //YES3.debugMessage('markSelectionRangeStart', ele);
}

FMAPR.markSelectionRangeEnd = function( ele )
{
    $("tr.yes3-selection-range-end").removeClass("yes3-selection-range-end");

    ele.addClass("yes3-selection-range-end");

    //YES3.debugMessage('markSelectionRangeEnd', ele);
}

FMAPR.toggleSelected = function( ele, sticky )
{
    sticky = sticky || false;

    if ( FMAPR.rowIsSelected( ele ) ){

        FMAPR.markRowUnSelected( ele, sticky );
    }
    else {

        FMAPR.markRowSelected( ele, sticky );
    }
}

FMAPR.rowIsSticky = function( ele )
{
    return ele.hasClass( 'yes3-row-sticky' );
}

FMAPR.rowIsSelected = function( ele )
{  
    if ( !ele ){
        return false;
    }

    return ele.hasClass( 'yes3-row-selected' );
}

FMAPR.clearInsertionRow = function() 
{
    $('tr.yes3-fmapr-insertion-row').removeClass('yes3-fmapr-insertion-row');
    FMAPR.insertionRowId = "";
}

FMAPR.markInsertionRow = function( theRow )
{
    if ( FMAPR.rowIsSelected(theRow) ){

        return true;
    }
    
    FMAPR.clearInsertionRow();
    theRow.addClass('yes3-fmapr-insertion-row');
    FMAPR.insertionRowId = theRow.attr('id');
}

FMAPR.markRowSelected = function( ele, sticky )
{
    sticky = sticky || false;

    ele.addClass( 'yes3-row-selected' );

    if ( sticky ){
        ele.addClass( 'yes3-row-sticky' );
    }

    FMAPR.clearInsertionRow();
    FMAPR.setNewItemModeLabel();
}

FMAPR.markRowUnSelected = function( ele, sticky )
{
    sticky = sticky || false;

    FMAPR.removeSelectionRangeBoundaryMark( ele );

    if ( !FMAPR.rowIsSticky( ele ) || sticky ) {

        ele.removeClass('yes3-row-selected');
        ele.removeClass('yes3-row-sticky');
    }
    
    FMAPR.setNewItemModeLabel();

}

FMAPR.isVerticalLayout = function()
{
    if ( typeof FMAPR.exportSpecification === "undefined" ){

        return false;
    }
    else return ( FMAPR.exportSpecification.export_layout === "v" );
}

FMAPR.isHorizontalLayout = function()
{
    if ( typeof FMAPR.exportSpecification === "undefined" ){

        return false;
    }
    else return ( FMAPR.exportSpecification.export_layout === "h" );
}

// note: the repeating layout is DEPRECATED
FMAPR.isRepeatedLayout = function()
{
    if ( typeof FMAPR.exportSpecification === "undefined" ){

        return false;
    }
    else return ( FMAPR.exportSpecification.export_layout === "r" );
}

FMAPR.isSpecificationElement = function(field_name, event_id)
{
    for (let i=0; i<FMAPR.specificationElements.length; i++){

        if ( FMAPR.specificationElements[i].redcap_field_name === field_name
                && FMAPR.specificationElements[i].redcap_event_id === event_id ){

            return true;
        }
    }

    return false;
}

/**
 * populates the FMAPR.specificationElements array (1 row for each output column)
 * 
 * Only one of form_name and field_name are provided,
 * e.g. either 1 field or all fields for the specified form
 * 
 * returns #items added
 * 
 * @param {*} data_element_name 
 * @param {*} form_name 
 * @param {*} field_name 
 * @param {*} event_id_option 
 * @returns 
 */
FMAPR.addREDcapObjectToSpecification = function(data_element_name, form_name, field_name, event_id_option)
{
    let j = 0;
    let k = 0;
    let items = 0;

    //YES3.debugMessage('addREDcapObjectToSpecification', data_element_name, form_name, field_name, event_id_option);
    if ( form_name === ALL_FORMS ) form_name = ALL_OF_THEM; // handle the alias

    /**
     * add a single item (redcap field)
     */
    if ( !form_name ){

        if ( typeof FMAPR.project.field_index[field_name] === "number" ){

            form_name = FMAPR.project.field_metadata[FMAPR.project.field_index[field_name]].form_name;
        }

        if (form_name && field_name){

            FMAPR.addREDcapItemToSpecification(data_element_name, form_name, field_name, event_id_option);
        }
        
        return 1;
    }

    if ( form_name !== ALL_OF_THEM ){

        let form_index = FMAPR.project.form_index[form_name];

        if ( typeof form_index === "number" ){

            for (k=0; k<FMAPR.project.form_metadata[form_index].form_fields.length; k++){

                items++;
                FMAPR.addREDcapItemToSpecification(data_element_name, form_name, FMAPR.project.form_metadata[form_index].form_fields[k], event_id_option);
            }
        }

        return items;
    }

    /**
     * Add one or more entire forms
     * note: the repeating layout is deprecated
     */
    for (j=0; j<FMAPR.project.form_metadata.length; j++){

        // form allowed for layout?
        if  ( !FMAPR.isRepeatedLayout() || FMAPR.project.form_metadata[j].form_repeating )  {

            // gotta fix this; the entire field list is processed for each form
            for (k=0; k<FMAPR.project.field_metadata.length; k++){

                if ( FMAPR.project.field_metadata[k].form_name === FMAPR.project.form_metadata[j].form_name ){

                    items++;
                    FMAPR.addREDcapItemToSpecification(data_element_name, FMAPR.project.field_metadata[k].form_name, FMAPR.project.field_metadata[k].field_name, event_id_option);
                }
            }
        }
    }

    return items;
}

FMAPR.addREDcapItemToSpecification = function(data_element_name, form_name, field_name, event_id_option)
{
    let form_index = FMAPR.project.form_index[form_name];

    if ( typeof form_index === "number" ){

        if ( FMAPR.exportSpecification.export_layout !== "h") {

            FMAPR.specificationElements.push({
                data_element_name: data_element_name,
                data_element_origin: "redcap",
                redcap_field_name: field_name,
                redcap_event_id: ""
            });
        }
        else {
            for (let j=0; j<FMAPR.project.form_metadata[form_index].form_events.length; j++){

                if ( event_id_option === ALL_OF_THEM || event_id_option == FMAPR.project.form_metadata[form_index].form_events[j].event_id || FMAPR.exportSpecification.export_layout !== "h" ){

                    if ( !FMAPR.isSpecificationElement(field_name, FMAPR.project.form_metadata[form_index].form_events[j].event_id) ){

                        FMAPR.specificationElements.push({
                            data_element_name: data_element_name,
                            data_element_origin: "redcap",
                            redcap_field_name: field_name,
                            redcap_event_id: FMAPR.project.form_metadata[form_index].form_events[j].event_id
                        });
                    }
                }
            }
        }
    }
}

FMAPR.getExportItemRows = function()
{
    return $("tr.yes3-fmapr-data-element");
}

FMAPR.getExportItemRowCount = function()
{
    return $("tr.yes3-fmapr-data-element").length;
}

FMAPR.exportItemAlreadyExists = function( object_type, object_name, object_event, yes3_fmapr_data_element_name )
{
    let allRows = FMAPR.getExportItemRows();

    let this_object_event = "";
    let this_object_type = "";
    let this_object_name = "";
    let this_object_id = "";
    let exists = false;

    yes3_fmapr_data_element_name = yes3_fmapr_data_element_name || "null";

    if ( !FMAPR.project.is_longitudinal ){

        object_event = "";
    }

    //YES3.debugMessage("exportItemAlreadyExists", object_type, object_name, object_event, typeof object_event);

    for (let i=0; i<allRows.length; i++){

        try {

            this_object_id = allRows.eq(i).attr("id");

            // do not check the item being edited
            if ( yes3_fmapr_data_element_name !== allRows.eq(i).data('yes3_fmapr_data_element_name') ) {

                this_object_event = ( FMAPR.project.is_longitudinal ) ? allRows.eq(i).attr("data-object_event") : "";
                this_object_type = allRows.eq(i).attr("data-object_type");
                this_object_name = allRows.eq(i).attr("data-object_name");

                //YES3.debugMessage("---> checking", this_object_type, this_object_name, this_object_event, typeof this_object_event);
                
                if ( this_object_event===object_event && this_object_name===object_name && this_object_type===object_type ){

                    return true;
                }
                
                if ( this_object_event===ALL_OF_THEM && this_object_name===object_name && this_object_type===object_type ){

                    return true;
                }
            }
        } catch(e) {

            console.error("exportItemAlreadyExists ERROR", e);
        }
    }

    return false;
}

FMAPR.postNotifications = function()
{
    let s = "";

    if ( YES3.contentLoaded ){

        s += `Timestamp: ${FMAPR.exportSpecification.timestamp}`;

        s += "<br>";

        s += `User: ${FMAPR.exportSpecification.export_username}`;
        
        s += "<br>";

        s += `Export LogId: ${FMAPR.exportSpecification.log_id}`;
        
        s += "<br>";

        s += `Export UUID: ${FMAPR.exportSpecification.export_uuid}`;

        s += `<br />${FMAPR.rowCount} item(s), ${FMAPR.column_count} export column(s)`; 
    }

    $('div#yes3-fmapr-status').html(s);
}

FMAPR.showLayoutItems = function()
{
    if ( FMAPR.isHorizontalLayout() ){
        $(".yes3-fmapr-horizontal-only").css("visibility", "visible");
    }
    else {
        $(".yes3-fmapr-horizontal-only").css("visibility", "hidden");
    }
}

FMAPR.formsAllowedForEvent = function(event_id)
{
    event_id = event_id || ALL_OF_THEM;

    if ( !FMAPR.project.is_longitudinal ){

        event_id = ALL_OF_THEM;
    }
    let allowed = true;
    let j = 0;
    let forms = [];

    for (let i=0; i<FMAPR.project.form_metadata.length; i++){

        allowed = false;

        if ( !FMAPR.isRepeatedLayout() || FMAPR.project.form_metadata[i].form_repeating ){

            if ( event_id === ALL_OF_THEM ){

                allowed = true;
            }
            else {

                for (j=0; j<FMAPR.project.form_metadata[i].form_events.length; j++){

                    if ( FMAPR.project.form_metadata[i].form_events[j].event_id==event_id ){

                        allowed = true;
                        break;
                    }
                }
            }
        }
        
        if ( allowed ){

            forms.push(FMAPR.project.form_metadata[i].form_name);
        }
    }
    return forms;
}

FMAPR.fieldsAllowedForForm = function(form_name)
{
    let fields = [];

    let form_index = FMAPR.project.form_index[form_name];

    if ( typeof form_index !== "number") {

        //YES3.debugMessage("fieldsAllowedForForm: invalid form name", form_name)
        return [];
    }

    for (let i=0; i<FMAPR.project.form_metadata[form_index].form_fields.length; i++){

        fields.push(FMAPR.project.form_metadata[form_index].form_fields[i]);
    }

    return fields;
}

FMAPR.getFormOptionsHtml = function(event_id)
{
    event_id = event_id || ALL_OF_THEM;

    //YES3.debugMessage('getFormOptionsHtml', 'event_id='+event_id)

    let allowed = true;
    let j = 0;
    let optionHtml = "";

    if ( FMAPR.exportSpecification.export_layout !== "r" ){

        optionHtml = `\n<option value='${ALL_OF_THEM}'>all forms</option>`;
    }
    else {

        optionHtml = "\n<option value='' disabled>select a form</option>";
    }

    for (let i=0; i<FMAPR.project.form_metadata.length; i++){

        if ( FMAPR.project.form_metadata[i].form_repeating && FMAPR.exportSpecification.export_layout !== "r"){

            allowed = false;
        }
        else if ( !FMAPR.project.form_metadata[i].form_repeating && FMAPR.exportSpecification.export_layout === "r" ){
            allowed = false;
        }
        else {

            allowed = ( event_id===ALL_OF_THEM );

            if ( !allowed ){
    
                for (j=0; j<FMAPR.project.form_metadata[i].form_events.length; j++){

                    if ( FMAPR.project.form_metadata[i].form_events[j].event_id==event_id ){
                        allowed = true;
                        //break;
                    }
                }
            }
        }

        if ( allowed ){

            optionHtml += `\n<option value='${FMAPR.project.form_metadata[i].form_name}'>${FMAPR.project.form_metadata[i].form_label}</option>`;
        }
    }

    return optionHtml;
}

FMAPR.getEventOptionsHtml = function(form_name)
{
    form_name = form_name || ALL_OF_THEM;

    if ( form_name === ALL_FORMS ) form_name = ALL_OF_THEM;

    let optionHtml = `<option value='${ALL_OF_THEM}'>all events for form</option>`;

    if ( form_name===ALL_OF_THEM ) {

        let eventIDs = Object.getOwnPropertyNames( FMAPR.project.event_metadata );
        
        for (let i=0; i<eventIDs.length; i++){
    
            optionHtml += `\n<option value='${eventIDs[i]}'>${FMAPR.project.event_metadata[eventIDs[i]].event_label}</option>`;
    
        }
    }
    else {

        let form_index = FMAPR.project.form_index[form_name];

        if ( typeof form_index === "number" ){

            if ( FMAPR.project.form_metadata[form_index].form_events.length < 2 ){

                optionHtml = "";
            }

            for (let i=0; i<FMAPR.project.form_metadata[form_index].form_events.length; i++){

                optionHtml += `\n<option value='${FMAPR.project.form_metadata[form_index].form_events[i].event_id}'>${FMAPR.project.form_metadata[form_index].form_events[i].descrip}</option>`;
            }
        }
    }

    return optionHtml;
}

FMAPR.setREDCapElementListeners = function()
{
    $('span.yes3-fmapr-redcap-element').on("click", function(e){
        e.stopPropagation();
        FMAPR.REDCapFieldContextMenu($(this), e);
    })
}

FMAPR.REDCapFieldContextMenu = function( element, e )
{
    //YES3.debugMessage( e );

    let theMenuPanel = YES3.getContextMenuElement();

    let theParent = theMenuPanel.parent();
    let theParentOffset = theParent.offset();
    let theParentWidth = theParent.width();

    let thisRow = element.closest('tr');

    let theMenu = YES3.getContextMenuContentElement();

    let field_name = thisRow.find('input.yes3-fmapr-input-element').first().val();

    let element_name = thisRow.find('span.yes3-fmapr-redcap-element').first().text();

    let event_name = thisRow.find('select.yes3-fmapr-event-select option:selected').first().text();

    let rowSelected = thisRow.hasClass('yes3-row-selected');

    let html = "";

    e.preventDefault();

    html = FMAPR.REDCapFieldContextMenuContent( thisRow.prop('id'), field_name, element_name, event_name, rowSelected );

    if ( !html.length ){

        return false;
    }

    theMenuPanel.css({'top': 0, 'left': 0});

    theMenu.html(html);

    let h = theMenuPanel.outerHeight();
    let w = theMenuPanel.outerWidth();
    let x = e.pageX - theParentOffset.left + 10; // just to the right of the cursor
    let y = e.pageY - theParentOffset.top - h - 3; // just above the cursor

    //YES3.debugMessage('contextmenu: h=' + h + '; w=' + w);

    if ( x+w > theParentWidth ) {
        x = theParentWidth - w - 10; // right-justified
    }

    if ( y < h ) {
        y = e.pageY - theParentOffset.top + 3; // just below the cursor
    }

    YES3.contextMenuOpen(x,y);

    //YES3.showRedPointer( thisRow );

    return false;
}

FMAPR.REDCapFieldContextMenuContent = function( rowId, field_name, element_name, event_name, rowSelected )
{
    let k = FMAPR.cutRowCount();

    if ( !rowSelected && rowId !== FMAPR.insertionRowId ){

        return "";
    }

    // hide all tooltips
    $('.yes3-tooltip').hide();

    let theRow = $(`tr#${rowId}`);

    let theNexRow = theRow.next('tr');

    let html = "";

    html += "<div id='yes3-contextmenu-panel-title' class='yes3-contextmenu-panel-row yes3-drag-handle'>";

    //html += "<div class='yes3-float-left'>" + redcap_objname + "</div><div class='yes3-float-right yes3-ellipsis' style='max-width: 150px'>" + event_name + "</div>";

    html += "command menu</div>";

    html += "<div class='yes3-contextmenu-panel-row'>";

    html += "<table><tbody>";

    html += "<tr><td>&nbsp;</td><td>&nbsp;</td></tr>";

    if ( !rowSelected ){

        html += `<tr class='yes3-command-disabled'><td>cut selection</td><td>ctrl+x</td></tr>`;
        html += `<tr><td><a href="javascript:FMAPR.contextMenuPasteRowSelections();">paste selection</a></td><td>ctrl+v</td></tr>`;
        html += `<tr class='yes3-command-disabled'><td>delete selection</td><td>&nbsp;</td></tr>`;
        html += `<tr class='yes3-command-disabled'><td>clear selection</td><td>ctrl+z</td></tr>`;
    }
    else {
        if ( k===0 ){
            html += `<tr><td><a href="javascript:FMAPR.contextMenuCutRowSelections();">cut selection</a></td><td>ctrl+x</td></tr>`;
        }
        else {

            html += `<tr class='yes3-command-disabled'><td>cut selection</td><td>ctrl+x</td></tr>`;
        }
        html += `<tr class='yes3-command-disabled'><td>paste cut rows</td><td>ctrl+v</td></tr>`;
        html += `<tr><td><a href="javascript:FMAPR.contextMenuRemoveRowSelections();">delete selection</a></td><td>&nbsp;</td></tr>`;
        html += `<tr><td><a href="javascript:FMAPR.contextMenuClearRowSelections();">clear selection</a></td><td>ctrl+z</td></tr>`;
    }

    html += "</tbody></table>";

    html += "</div>";

    return html;   
}


FMAPR.REDCapFieldContextMenuContent_v050 = function( rowId, field_name, element_name, event_name, rowSelected )
{
    let k = FMAPR.selectedRowCount();

    let theRow = $(`tr#${rowId}`);

    let theNexRow = theRow.next('tr');

    let theNextRowIsSelected = FMAPR.rowIsSelected( theNexRow );

    let redcap_objname = ( field_name ) ? field_name : element_name;

    let html = "";

    html += "<div id='yes3-contextmenu-panel-title' class='yes3-contextmenu-panel-row yes3-drag-handle'>";

    html += "<div class='yes3-float-left'>" + redcap_objname + "</div><div class='yes3-float-right yes3-ellipsis' style='max-width: 150px'>" + event_name + "</div>";

    html += "</div>";

    html += "<div class='yes3-contextmenu-panel-row'>";

    html += "<table><tbody>";

    html += "<tr><td>&nbsp;</td><td>&nbsp;</td></tr>";

    if ( rowSelected){

        html += `<tr><td><a href="javascript:FMAPR.contextMenuDeSelectRow('${rowId}');">unselect field</a></td><td>ctrl+click</td></tr>`;
    }
    else {

        html += `<tr><td><a href="javascript:FMAPR.contextMenuSelectRow('${rowId}', true);">select field</a></td><td>ctrl+click</td></tr>`;
    }

    if ( FMAPR.selectionRangeStartDefined() ) {
        html += `<tr><td><a href="javascript:FMAPR.contextMenuSetRangeEnd('${rowId}');">select field range end</a></td><td>shift+click</td></tr>`;
    }
    else {
        html += `<tr><td><a href="javascript:FMAPR.contextMenuSetRangeStart('${rowId}');">select field range start</td><td>shift+click</td></tr>`;
    }

    if ( k > 0 ){


        if ( !theNextRowIsSelected ){

            html += "<tr><td>&nbsp;</td><td>&nbsp;</td></tr>";
            html += `<tr><td><a href="javascript:FMAPR.contextMenuPasteRowSelections('${rowId}');">move ${k} selected field(s)</a></td><td>&nbsp;</td></tr>`;
         
            //html += "<tr><td>&nbsp;</td><td>&nbsp;</td></tr>";
            //html += `<tr><td>remove ${k} selected field(s)</td><td>&nbsp;</td></tr>`;
        }

        html += "<tr><td>&nbsp;</td><td>&nbsp;</td></tr>";
        html += `<tr><td><a href="javascript:FMAPR.contextMenuRemoveRowSelections();">remove ${k} field selection(s)</a></td><td>&nbsp;</td></tr>`;
        html += "<tr><td>&nbsp;</td><td>&nbsp;</td></tr>";
        html += `<tr><td><a href="javascript:FMAPR.contextMenuClearRowSelections();">clear ${k} field selection(s)</a></td><td>&nbsp;</td></tr>`;
    }
    else {

        if ( !rowSelected ){

            html += "<tr><td>&nbsp;</td><td>&nbsp;</td></tr>";
            //html += `<tr><td><a href="javascript:YES3.Functions.openFieldInsertionForm('${rowId}', '${field_name}', '${event_name}', 'above');">insert form fields above</a></td><td>&nbsp;</td></tr>`;
            html += `<tr><td><a href="javascript:YES3.Functions.openFieldInsertionForm('${rowId}', '${redcap_objname}', '${event_name}', 'below');">insert form fields</a></td><td>&nbsp;</td></tr>`;

        }
    }

    html += "</tbody></table>";

    html += "</div>";

    return html;   
}

FMAPR.contextMenuPasteRowSelections = function(rowId)
{
    let theRows = $('tr.yes3-row-selected');

    let theRow = null;

    let theRowAfter = $(`tr#${FMAPR.insertionRowId}`);

    for (let i=0; i<theRows.length; i++){

        theRow = $(theRows[i]);
        theRow.insertBefore( theRowAfter );
    }

    FMAPR.clearInsertionRow();

    // clear range, cut and copied tags bu leave rows selected
    FMAPR.clearRowTagsButLeaveSelected();

    //YES3.debugMessage('contextMenuPasteRowSelections', theRows);

    FMAPR.markAsDirty();

    YES3.contextMenuClose();

    FMAPR.renumberRows();
}

FMAPR.contextMenuRemoveRowSelections = function()
{
    FMAPR.removeSelections();

    FMAPR.showLayoutItems();

    YES3.contextMenuClose();

    FMAPR.setNewItemModeLabel();
}

FMAPR.contextMenuClearRowSelections = function()
{
    FMAPR.clearSelections(true);

    YES3.contextMenuClose();

    FMAPR.setNewItemModeLabel();
}

FMAPR.contextMenuCutRowSelections = function()
{
    $('tr.yes3-row-selected')
    .removeClass('yes3-row-copied')
    .addClass('yes3-row-cut');

    YES3.contextMenuClose();
    FMAPR.setNewItemModeLabel();
}

FMAPR.contextMenuCopyRowSelections = function()
{
    $('tr.yes3-row-selected')
    .removeClass('yes3-row-cut')
    .addClass('yes3-row-copied');

    YES3.contextMenuClose();
}

FMAPR.contextMenuSelectRow = function( rowId )
{
    let theRow = $(`tr#${rowId}`);

    FMAPR.selectRow( theRow, true );

    YES3.contextMenuClose();
}

FMAPR.contextMenuDeSelectRow = function( rowId )
{
    let theRow = $( `tr#${rowId}` );

    FMAPR.deSelectRow( theRow );

    YES3.contextMenuClose();
    FMAPR.setNewItemModeLabel();
}

FMAPR.contextMenuSetRangeStart = function( rowId )
{
    let theRow = $(`tr#${rowId}`);

    FMAPR.selectRow( theRow );

    FMAPR.markSelectionRangeStart( theRow );

    FMAPR.markSelectionRange();

    YES3.contextMenuClose();
}

FMAPR.contextMenuSetRangeEnd = function( rowId )
{
    let theRow = $(`tr#${rowId}`);

    FMAPR.selectRow( theRow );

    FMAPR.markSelectionRangeEnd( theRow );

    FMAPR.markSelectionRange();

    YES3.contextMenuClose();
}

FMAPR.selectRow = function( theRow, sticky )
{
    theRow.addClass('yes3-row-selected');
     
    if ( sticky ) {

        theRow.addClass('yes3-row-sticky');
    }

    FMAPR.setNewItemModeLabel();
}

FMAPR.deSelectRow = function( theRow )
{
    theRow.removeClass('yes3-row-selected');

    if ( theRow.hasClass('yes3-row-sticky' )){

        theRow.addClass('yes3-row-sticky');
    }

    YES3.contextMenuClose();

    FMAPR.setNewItemModeLabel();
}

FMAPR.firstSelectedRow = function()
{
    return $('tr.yes3-row-selected:first');
}

FMAPR.selectedRowCount = function()
{
    return $('tr.yes3-row-selected').length;
}

FMAPR.cutRowCount = function()
{
    return $('tr.yes3-row-cut').length;
}

FMAPR.copiedRowCount = function()
{
    return $('tr.yes3-row-copied').length;
}

 FMAPR.setValuePickers = function( yes3_fmapr_data_element_name, redcap_field_name ) {
 
    let tbl = FMAPR.getExportItemsTable();

    //YES3.debugMessage('setValuePickers', yes3_fmapr_data_element_name, redcap_field_name);
 
    if ( !FMAPR.project.field_metadata[FMAPR.project.field_index[redcap_field_name]].field_valueset.length ) {
       tbl.find(`tr[data-yes3_fmapr_data_element_name=${yes3_fmapr_data_element_name}].yes3-fmapr-lov a.yes3-fmapr-value-picker-toggler`).hide();
       return true;
    }
 
    let value = "";
    let label = "";
 
    let picker_wrappers = tbl.find(`tr[data-yes3_fmapr_data_element_name=${yes3_fmapr_data_element_name}].yes3-fmapr-lov div.yes3-fmapr-value-picker-wrapper`);

    //YES3.debugMessage('setValuePickers:picker_wrappers=', picker_wrappers);
 
    let pkrHtml = "";
 
    for (let i = 0; i < FMAPR.project.field_metadata[FMAPR.project.field_index[redcap_field_name]].field_valueset.length; i++) {
       value = FMAPR.project.field_metadata[FMAPR.project.field_index[redcap_field_name]].field_valueset[i].value;
       label = FMAPR.project.field_metadata[FMAPR.project.field_index[redcap_field_name]].field_valueset[i].label;
       if (i) {
          pkrHtml += "<br />";
       }
       pkrHtml += `<label><input type='checkbox' value='${value}' />${value}. ${label}</label>`;
    }

    //YES3.debugMessage('setValuePickers:pkrHtml=', pkrHtml);
 
    picker_wrappers.html(pkrHtml);
 
    picker_wrappers.find('input[type=checkbox]')
       .off()
       .on('click', function () {
 
          let ctl = $(this).closest("tr").find("input[type=text].yes3-fmapr-input-lov").first();
          let thisVal = $(this).val();
          let checked = $(this).is(':checked');
          let ctlVal = ctl.val();
          let ctlValues = ctlVal.split(',');
 
          if (checked) {
             if (!ctlVal.length) {
                ctlVal = thisVal;
             } else {
                ctlValues.push(thisVal)
                ctlVal = ctlValues.join(',');
             }
          } else {
             if (ctlVal === thisVal) {
                ctlVal = "";
             } else {
                if (ctlValues.length > 1) {
                   let i = ctlValues.indexOf(thisVal);
                   if (i > -1) {
                      ctlValues.splice(i, 1);
                      ctlVal = ctlValues.join(',');
                   }
                }
             }
          }
 
          ctl.val(ctlVal).trigger('change');
 
          //YES3.debugMessage('CLICK', checked, ctlVal, ctlValues);
 
       })
    ;
    tbl.find(`tr[data-yes3_fmapr_data_element_name=${yes3_fmapr_data_element_name}].yes3-fmapr-lov a.yes3-fmapr-value-picker-toggler`).show();
 
 }

 FMAPR.eventPrefixForEventId = function(event_id)
 {
    for ( let j=0; j<FMAPR.event_settings.length; j++) {
        if ( event_id===FMAPR.event_settings[j].event_id ) {
            return FMAPR.event_settings[j].event_prefix;
        }
     }

     return "?";
 }

 FMAPR.eventNameForEventId = function(event_id)
 {
    for ( let j=0; j<FMAPR.event_settings.length; j++) {
        if ( event_id===FMAPR.event_settings[j].event_id ) {
            return FMAPR.event_settings[j].event_name;
        }
     }

     return "?";
 }

 FMAPR.setEventSelectListeners = function()
 {
     $('select.yes3-fmapr-event-select:not(.yes3-fmapr-listener-set)')
     .addClass('yes3-fmapr-listener-set')
     .off().on("change", function(){

        FMAPR.REDCapEventOnChange( $(this) );
     })
 }

 FMAPR.REDCapEventOnChange = function(evnt)
 {
    let yes3_fmapr_data_element_name = evnt.attr('data-yes3_fmapr_data_element_name');

    FMAPR.setRawREDCapPseudoElementName( yes3_fmapr_data_element_name );

    // block this call while building the page
    if ( !FMAPR.buildIsInProgress() ){
        FMAPR.markAsDirty();
    }
 }

 FMAPR.REDCapFieldOnChange = function( fld )
 {
 
    let yes3_fmapr_data_element_name = fld.data('yes3_fmapr_data_element_name');

    let field_name = fld.val();

    let theRow = fld.parent().parent();
    
    let isRawREDCapField = ( theRow.find('span.yes3-fmapr-redcap-element').length > 0 );

    let eventSelect = theRow.find('select.yes3-fmapr-event-select');

    if ( isRawREDCapField ){

        eventSelect.empty();
    }

    if ( !field_name ){

        return false;
    }

    // this is no longer a new field
    if ( theRow.hasClass('yes3-fmapr-new-field') ) {

        theRow.removeClass('yes3-fmapr-new-field');
        theRow.find('i.yes3-invisible').removeClass('yes3-invisible').addClass('yes3-visible');
    }

    // if this hasn't been triggered by an export load then signal the 'save' alert
    if ( !FMAPR.buildIsInProgress()) {

        FMAPR.markAsDirty();
    }

    let field_index = FMAPR.project.field_index[field_name];

    if ( typeof field_index !== "number" ){
        return false;
    }

    let form_name = FMAPR.project.field_metadata[field_index].form_name;

    if ( !form_name ){
        return false;
    }

    let form_index = FMAPR.project.form_index[form_name];

    if ( typeof form_index !== "number" ){
        return false;
    }

    //-xxxif ( !isRawREDCapField || FMAPR.exportSpecification.export_layout==="h" ){
    
        let formEvents = FMAPR.project.form_metadata[form_index].form_events;

        let optionsHtml = "";

        if ( isRawREDCapField && formEvents.length > 1 ){
            optionsHtml += `<option value="${ALL_OF_THEM}">all events for field</option>`;
        }

        for ( let e=0; e<formEvents.length; e++ ){
            optionsHtml += `<option value=${formEvents[e].event_id}>${formEvents[e].descrip}</option>`;                 
        }

        eventSelect.append(optionsHtml);

        if ( !isRawREDCapField ) {

            FMAPR.setValuePickers( yes3_fmapr_data_element_name, field_name );
        }
    //}

    if ( isRawREDCapField ){

        FMAPR.setRawREDCapPseudoElementName( yes3_fmapr_data_element_name );
    }
 }

/**
 * displays or hides elements that depend on whether there are exports defined
 */
FMAPR.displayInitializationElements = function()
{
    let select = FMAPR.getExportUUIDSelect();

    // there is always at least one option element
    if ( select.find('option').length > 0 ){

        $(".yes3-fmapr-when-initialized").show();
        $(".yes3-fmapr-when-uninitialized").hide(); 
    }
    else {

        $(".yes3-fmapr-when-initialized").hide();
        $(".yes3-fmapr-when-uninitialized").show(); 
    }
}

FMAPR.displayDashboardOption = function( yes3_dashboard_option )
{
    $(`input[name=yes3-dashboard-options][value=${yes3_dashboard_option}]`).trigger('click');
}

FMAPR.countExportItemsFromSpecification = function( specification )
{
    if ( !specification.export_items_json ){

        return 0;
    }

    try {

        items = JSON.parse( specification.export_items_json );

    } catch (e) {

        return 0;
    }

    return items.length;
}

FMAPR.dashboardOptionHandler = function()
{
    let yes3_dashboard_option = $("input[name=yes3-dashboard-options]:checked").val();

    FMAPR.dashboard_option = yes3_dashboard_option;

    let optionTitle = {
        "settings": "",
        "items": "",
    }
    
    $('div.yes3-dashboard-title').html( optionTitle[yes3_dashboard_option] );

    if ( typeof FMAPR.exportSpecification !== "undefined" && typeof FMAPR.exportSpecification.export_layout !== "undefined" ){

        YES3.displayActionIcons();
        FMAPR.displayActionIcons();
    }

    FMAPR.disableIconsWhenEverythingAdded();

    if ( yes3_dashboard_option==="items" ){

        FMAPR.showExportItems();
        FMAPR.hideExportSettings();
        $('i.yes3-fmapr-option-items-only').removeClass('yes3-action-disabled');
        $('i.yes3-fmapr-option-settings-only').addClass('yes3-action-disabled');
        FMAPR.resizeExportItemsTable();        
        FMAPR.scrollExportItemsTableToBottom();
    }
    else if ( yes3_dashboard_option==="settings" ){

        FMAPR.hideExportItems();
        FMAPR.showExportSettings();
        $('i.yes3-fmapr-option-items-only').addClass('yes3-action-disabled');
        $('i.yes3-fmapr-option-settings-only').removeClass('yes3-action-disabled');
        FMAPR.resizeExportSettingsContainer();
    }
}

FMAPR.showDashboardHead = function() {
    $("div#yes3-fmapr-dashboard-head").show();
}

FMAPR.hideDashboardHead = function() {
    $("div#yes3-fmapr-dashboard-head").hide();
}

FMAPR.showExportItems = function() {
    FMAPR.getExportItemsTableWrapper().show();
    FMAPR.getExportItemsTable().show();
    FMAPR.getExportNewItemOptionsSection().show();
}

FMAPR.hideExportItems = function() {
    FMAPR.getExportItemsTable().hide();
    FMAPR.getExportNewItemOptionsSection().hide();
    FMAPR.getExportItemsTableWrapper().hide();
}

FMAPR.showExportSettings = function() {
    FMAPR.getExportSettingsContainer().show();
}

FMAPR.hideExportSettings = function() {
    FMAPR.getExportSettingsContainer().hide();
}

FMAPR.setSasCodeDefaults = function(){

    const $export_sascode = $('input[name=export_sascode]');
    const $exportSascodeLibref = $('input[name=export_sascode_libref]');
    const $exportSascodePath = $('input[name=export_sascode_libref_path]');
    const $exportSascodeDsname = $('input[name=export_sascode_dsname]');
    const export_name = $('input[name=export_name]').val();

    if ( $export_sascode.is(':checked') ){
            
        if ( !$exportSascodeLibref.val() && FMAPR.defaults.export_sascode_libref ){

            $exportSascodeLibref.val( FMAPR.defaults.export_sascode_libref );
            FMAPR.markAsDirty();
        }

        if ( !$exportSascodePath.val() && FMAPR.defaults.export_sascode_libref_path ){

            $exportSascodePath.val( FMAPR.defaults.export_sascode_libref_path );
            FMAPR.markAsDirty();
        }

        if ( !$exportSascodeDsname.val() && export_name && typeof export_name === "string" ){

            $exportSascodeDsname.val( export_name.toSASName() );
            FMAPR.markAsDirty();
        }
    }
}

FMAPR.populateSpecificationTables = function( specification )
{
    let errors = 0;
    
    FMAPR.markAsBuildInProgress();

    FMAPR.setConstrainedAutocompleteSource(); // alternate source for repeating_forms

    // settingsAreDirty will be true if any settings were blank and set to default values
    const settingsAreDirty = FMAPR.populateSettingsTable( specification );

    /**
     * Use the save function to audit the settings just loaded, and mark any blank or bad entries
     * (no save request will be issued)
     */        
    YES3.Functions.saveExportSpecification(true);

    FMAPR.populateExportItemsTable( specification );
    FMAPR.markAsClean(true);

    FMAPR.showLayoutItems(); // show or hide longitudinal project items (events)
    FMAPR.resizeExportItemsTable();
    FMAPR.resizeExportSettingsContainer();
    FMAPR.markAsBuildCompleted();
    FMAPR.setSasCodeDefaults();

    FMAPR.postExportName( FMAPR.exportSpecification.export_name );

    FMAPR.postNotifications(); // displays specification timestamp, uuid etc

    if ( errors ) {

        YES3.hello("Error loading the export specification. Please use the Wayback (undo) to restore an earlier copy.");
        FMAPR.postMessage("Error loading export specification.", true);
    }
    else {

        if ( FMAPR.reloadParms.wayback ){

            FMAPR.markAsDirty();
            FMAPR.reloadParms.wayback=false;          
        }
    }

    /**
     * disable element insertion actions if there are settings errors
     */
    if ( FMAPR.someBadSettings() || errors ){

        $('i.yes3-fmapr-settings-okay').addClass('yes3-action-disabled');
    }
    else {

        FMAPR.scrollExportItemsTableToNewField();
    }

    /** a little help for the help panel */
    $('span[name=export_code_filename_base]').html( FMAPR.exportSpecification.export_code_filename_base );
    
    YES3.displayActionIcons(); // global action icons
    FMAPR.displayActionIcons(); // action icons specific to this page
}

FMAPR.postExportName = function( export_name ){

    export_name = ( export_name ) ? export_name : "?? (no export name)";
    
    $('.yes3-fmapr-export-name').html( export_name );
}

FMAPR.displayActionIcons = function()
{
    
    let yes3_dashboard_option = $("input[name=yes3-dashboard-options]:checked").val();

    if ( FMAPR.exportSpecification.export_layout==="r" ){

        $('i.yes3-fmapr-display-when-not-repeating').addClass('yes3-action-disabled');
    }
    else {

        $('i.yes3-fmapr-display-when-not-repeating').removeClass('yes3-action-disabled');
    }

    if ( FMAPR.exportSpecification.export_layout==="r" && $("tr.yes3-fmapr-data-element:not(.yes3-fmapr-new-field)").length > 0 ){

        $('i.yes3-fmapr-bulk-insert').addClass('yes3-action-disabled');
    }
    else {

        $('i.yes3-fmapr-bulk-insert').removeClass('yes3-action-disabled');
    }

    // disable based on permissions
    if ( !YES3.userRights.isDesigner ){

        $('i.yes3-designer-only:not(.yes3-action-disabled)').addClass('yes3-action-disabled');
    }

    // disable based on global EM setting
    if ( !FMAPR.project.host_filesystem_exports_enabled ){

        $('i[action=exportToHost]:not(.yes3-action-disabled)').addClass('yes3-action-disabled');
    }

    // disable based on which tab is open
    if ( yes3_dashboard_option!=="items" ){

        $('i.yes3-fmapr-item-view').addClass('yes3-action-disabled');
    }

    // disable based on v12-style export permissions
    if ( !YES3.userRights.exporter ){

        $('i.yes3-exporter-only:not(.yes3-action-disabled)').addClass('yes3-action-disabled');
    }

    // disable based on specific export permissions
    if ( !FMAPR.userPermissions.export ){

        $('i.yes3-exporter-only:not(.yes3-action-disabled)').addClass('yes3-action-disabled');
    }

    // squelch any controls not allowed when all items already selected
    FMAPR.disableIconsWhenEverythingAdded();

    //console.log("FMAPR.displayActionIcons: dirty = ", FMAPR.dirty);

    // make sure disabled icons are unbound
    YES3.setActionIconListeners( YES3.container() );
}

FMAPR.disableIconsWhenEverythingAdded = function() {

    if ( FMAPR.everythingIsAdded() ){

        $('i.yes3-fmapr-not-everything').addClass('yes3-action-disabled');
    }
}

FMAPR.someBadSettings = function()
{
    
    let exportSettingsContainer = FMAPR.getExportSettingsContainer();

    return exportSettingsContainer.find(".yes3-error").length;
}

FMAPR.clearSettingsTables = function()
{
    let exportSettingsContainer = FMAPR.getExportSettingsContainer();

    let setting = '';

    exportSettingsContainer.find('input, select, textarea').each(function(){

        if ( $(this).attr('data-setting') ) {

            setting = $(this).attr('data-setting');
            
            if ( $(this).attr('type')==='checkbox' ) {

                $(this).prop('checked', false);
            }

            else if( $(this).attr('type') === "radio" ){

                $(this).prop('checked', false);
            }

            else if( $(this).attr('type') !== "radio" ){

                $(this).val('');

                if ( setting==="export_criterion_event" ){

                    $(this).empty();
                }
            }
        }
    })
}

FMAPR.populateSettingsTable = function( specification )
{
    FMAPR.clearSettingsTables();

    //console.warn('populateSettingsTable', specification);
    
    let exportSettingsContainer = FMAPR.getExportSettingsContainer();

    let setting = "";

    $('input[data-setting=export_uuid]').val( FMAPR.getExportUUID() );

    exportSettingsContainer.find('input, select, textarea').each(function(){

        if ( $(this).attr('data-setting') ) {

            setting = $(this).attr('data-setting');

            if ( $(this).attr('type')==='checkbox' ) {

                if ( specification[setting]==="1" ){

                    $(this).prop('checked', true);
                }
            }

            else if( $(this).attr('type') === "radio" && $(this).val() === specification[setting] ){

                $(this).prop('checked', true);
            }

            else if( $(this).attr('type') !== "radio" ){

                $(this).val( specification[setting] );

                if ( setting==="export_criterion_field" ){

                    $(this).trigger('change');
                }
            }
        }
    })
    
    exportSettingsContainer.find('.yes3-fmapr-settings-block').show();
    exportSettingsContainer.find('.hidden').hide();

    let export_layout_text = "";
    let isDirty = false;

    if ( specification['export_layout'] === "h" ){
        export_layout_text = "Horizontal";
    }
    if ( specification['export_layout'] === "v" ){
        export_layout_text = "Vertical";
    }
    if ( specification['export_layout'] === "r" ){

        YES3.hello("Note: this layout will be converted to a vertical layout when the export specification is saved.");

        FMAPR.convertToVertical = 1;

        isDirty = true;

        export_layout_text = "Vertical (converted from Repeating)";
    }
    $(".yes3-fmapr-export-layout-text").html(export_layout_text);

    // set some defaults
    if ( FMAPR.setDefaultOptionValue( "export_file_type", "csv" ) ) isDirty = true;
    if ( FMAPR.setDefaultOptionValue( "export_multiselect", "1" ) ) isDirty = true;

    FMAPR.exportSettingsTableSkipper();

    return isDirty;
}

FMAPR.setDefaultOptionValue = function( setting_name, defaultOptionSelection )
{
    const $ctl = $(`input[type=radio][data-setting=${setting_name}]`);

    let optionSet = false;

    if ( $ctl.length && !$(`input[type=radio][data-setting=${setting_name}]:checked`).length ) {
        $ctl.each(function(){
            if ( $(this).val() === defaultOptionSelection ) {
                $(this).prop('checked', true);
                optionSet = true;
            }
        });
    }

    return optionSet;
}

FMAPR.emptyExportItemsTable = function()
{
    const $fmaprTable = FMAPR.getExportItemsTable();
    const fmaprTable = $fmaprTable.get(0); // DOM object
    const $fmaprHead  = $fmaprTable.find('thead');
    const $fmaprBody  = $fmaprTable.find('tbody');

    // clear any existing tooltip objects
    YES3.clearBsTooltipsForContainer( fmaprTable );

    // empty the head and body
    // use jQuery empty() rather than html("") to avoid orphaned jQuery-assigned event handlers
    $fmaprHead.empty();
    $fmaprBody.empty();
}

FMAPR.populateExportItemsTableHead = function(){

    const table = FMAPR.getExportItemsTable().get(0); // DOM object
    const $tbody = FMAPR.getExportItemsTable().find('tbody');
    const $thead = FMAPR.getExportItemsTable().find('thead');
    const thead = $thead.get(0); // DOM object

    let html = '';

    const ncols = YES3.getTableColumnCount( table );

    if ( ncols === 0 ){

        html = `<tr><th>Click the <strong>form or field</strong> below to start adding items to this export specification, or the <strong>everything</strong> button to add all fields.</th></tr>`;

        $thead.append(html);

        return html;
    }

    html = '<tr>';

    // row number column
    html += '<th class="yes3-fmapr-row-number yes3-text-center" style="opacity: 1.0"><i class="far fa-question-circle yes3-action-icon yes3-action-icon-inline-large" data-bs-toggle="tooltip" title="Click to obtain detailed instructions for selecting, relocating and removing export items." action="Help_rowselector"></i></th>';

    // object type column
    html += '<th class="yes3-fmapr-redcap-object-type yes3-text-left">Type</th>';

    if ( FMAPR.project.is_longitudinal ){
        // event column
        html += '<th class="yes3-fmapr-redcap-object-event yes3-text-left">Event name (hover for details)</th>';
    }

    // object name column
    html += '<th class="yes3-fmapr-redcap-object-name yes3-text-left">Form or field name (hover for details)</th>';

    // trashcan column
    html += '<th class="yes3-fmapr-trashcan yes3-text-right">&nbsp;</th>';

    html += '</tr>';

    // clear any existing tooltip objects in the thead
    YES3.clearBsTooltipsForContainer( thead );

    $thead.empty().append(html);

    // set the tooltip handlers
    YES3.setBsTooltipListenersForContainer( thead );

    YES3.setActionIconListeners( $thead );

    //console.log("FMAPR.populateExportItemsTableHead: ncols=", ncols, html);

    return html;
}

FMAPR.populateExportItemsTable = function( specification )
{
    FMAPR.markAsBuildInProgress();

    FMAPR.emptyExportItemsTable(); // empties the table and clears any tooltips

    FMAPR.populateExportItemRowsV2( specification );

    FMAPR.populateExportItemsTableHead(); // must be after rows are added, because head depends on layout

    FMAPR.setRepeatLayoutConstraints();

    FMAPR.resizeExportItemsTable();

    FMAPR.markAsBuildCompleted();

    FMAPR.renumberRows();

    YES3.displayActionIcons();

    FMAPR.displayActionIcons();

    FMAPR.renderNewItemOptionsSection();

    FMAPR.warnIfMixedRepeating();

    return true;
}

FMAPR.populateExportItemRowsV2 = function( specification )
{
    let items = null;
    let item = null;
    let row = null;
    let itemREDCapField = null;
    let itemREDCapValue = null;
    let lovRows = null;
    let lovRow = null;
    let i = 0;
    let j = 0;
    let k = 0;

    if ( !specification.export_items_json ){

        return true;
    }

    try {

        items = JSON.parse( specification.export_items_json );

    } catch (e) {

        return false;
    }

    const $fmaprTable = FMAPR.getExportItemsTable();
    const $fmaprHead = $fmaprTable.find('thead');
    const $fmaprBody = $fmaprTable.find('tbody');
    const fmaprBody = $fmaprBody.get(0); // DOM object

    html = "";

    for (i=0; i<items.length; i++){

        item = items[i];

        //YES3.debugMessage("populateExportItemRowsV2", item.redcap_object_type, item.redcap_field_name, item.redcap_form_name);

        if ( item.export_item_origin==="redcap" ){

            if ( FMAPR.exportItemAlreadyExists(
                item.redcap_object_type,
                (item.redcap_object_type==="field") ? item.redcap_field_name:item.redcap_form_name,
                item.redcap_event_id )){

                //YES3.debugMessage("Duplicate item not added: ", item.redcap_object_type, item.redcap_form_name, item.redcap_field_name, item.redcap_event_id);
            }
            else {
                //html function parameters: object_name, event, theRowBeforeWhich, yes3_fmapr_data_element_name, mode, unsaved, batch
                if ( item.redcap_object_type === "field" ){

                    html += FMAPR.exportItemFieldHtml(item.redcap_field_name, item.redcap_event_id, {}, "", "append", false, true);
                }
                else if ( item.redcap_object_type === "form" ){

                    html += FMAPR.exportItemFormHtml(item.redcap_form_name, item.redcap_event_id, {}, "", "append", false, true);
                }
            }
        }
    }

    $fmaprBody.append(html);

    FMAPR.setRowSelectorListenersV2();

    // set the html-defined tooltip handlers
    YES3.setBsTooltipListenersForContainer( fmaprBody );
}

/**
 * Resets the export item editor form to its initial state
 * Prepares the editor for adding a new item or editing an existing one.
 */
FMAPR.resetExportItemEditor = function(mode, yes3_fmapr_data_element_name)
{
    mode = mode || "unknown";
    yes3_fmapr_data_element_name = yes3_fmapr_data_element_name || ""; // item being edited, if applicable

    let ed = FMAPR.getExportItemEditor();

    let mode_message = "";

    ed.find("input[type='text'], input[type='hidden'], select").val("");
    ed.find("input[type=radio]").prop("checked", false);
    ed.find("input[name=mode]").val(mode);
    ed.find(".yes3-save-button").css("visibility", "hidden");

    if ( mode === "edit" ){

        mode_message = "Editing the selected item.";
    }
    else if ( mode === "append" ){

        mode_message = "Items(s) will be appended to the export.";
    }
    else if ( mode === "insert" ){

        mode_message = "Items(s) will be inserted above the selected row.";
    }

    ed.find("div#yes3-fmapr-item-editor-mode").html(mode_message);

    if ( yes3_fmapr_data_element_name ){

        ed.find("input[name=yes3_fmapr_data_element_name]").val(yes3_fmapr_data_element_name);
    }
}

/**
 * Opens the export item editor form for editing a new export item.
 */
FMAPR.editNewExportItem = function()
{
    let mode = FMAPR.getNewItemMode(); // "insert" or "append"

    let yes3_fmapr_data_element_name = ""; // a global variable used to hold the name of the selected data element

    if ( mode === "insert" ) {

        yes3_fmapr_data_element_name = $('tr.yes3-row-selected').attr('data-yes3_fmapr_data_element_name');
    }

    FMAPR.resetExportItemEditor(mode, yes3_fmapr_data_element_name);

    let theForm = YES3.openPanel("yes3-fmapr-item-editor-panel");

    theForm.find(".yes3-hide-on-open").hide();
}

FMAPR.editExistingExportItem = function( yes3_fmapr_data_element_name )
{
    FMAPR.clearSelections(true); // deselect all rows

    FMAPR.resetExportItemEditor("edit");

    let theRow = $(`tr[data-yes3_fmapr_data_element_name='${yes3_fmapr_data_element_name}']`);

    let object_type  = theRow.attr("data-object_type");
    let object_name  = theRow.attr("data-object_name");
    let object_event = theRow.attr("data-object_event");

    FMAPR.selectRow(theRow);

    //YES3.debugMessage("editREDCapExportItem", object_type, object_event, object_name);

    let theForm = YES3.openPanel("yes3-fmapr-item-editor-panel");

    theForm.find(".yes3-hide-on-open").hide();

    let row_number = theRow.find("td.yes3-fmapr-row-number").text();

    theForm.find("input[name='yes3_fmapr_data_element_name']").val(yes3_fmapr_data_element_name);
    theForm.find("input[name='row_number']").val(row_number);

    theForm.find(`input[type=radio][name='object_type'][value='${object_type}']`)
        .trigger('click')
    ;

    theForm.find(`select[name='object_event']`).val(object_event)
        .trigger('change')
    ;

    theForm.find(`[name='redcap_${object_type}']`).val(object_name).trigger('change');
}

FMAPR.closeItemEditorForm = function()
{
    YES3.closePanel("yes3-fmapr-item-editor-panel");
}

FMAPR.markAsRemoved = function( theRow ) 
{
    theRow.addClass('yes3-fmapr-remove');
}

FMAPR.removeMarkedRows = function() 
{
    $('tr.yes3-fmapr-remove').remove();
}

FMAPR.setExportItemEditorListeners = function()
{
    let theForm = FMAPR.getExportItemEditor();

    // save button

    theForm.find("input[type='button'].yes3-save-button").on("click", function(){

        let theForm = FMAPR.getExportItemEditor();

        let object_type = theForm.find("input[name=object_type]:checked").val();
        let object_event = theForm.find("select[name=object_event]").val();
        let object_name = theForm.find(`[name='redcap_${object_type}']`).val();
        let yes3_fmapr_data_element_name = theForm.find(`input[name='yes3_fmapr_data_element_name']`).val();
        let row_number = theForm.find(`input[name='row_number']`).val();
        let mode = theForm.find(`input[name='mode']`).val();
        let insert_as = theForm.find("input[name=insert_as]:checked").val();

        let theRow = {};
        let theRowBeforeWhich = {};

        let saveResult = "";

        // set the 'insert_as' option for field
        if ( object_type==="field" ){

            insert_as = "item";
        }

        /*console.log( "FMAPR.saveExportItem: ",
            "object_type=" + object_type,
            "object_event=" + object_event,
            "object_name=" + object_name,
            "object_name=" + object_name,
            "yes3_fmapr_data_element_name=" + yes3_fmapr_data_element_name,
            "row_number=" + row_number,
            "insert_as=" + insert_as,
            "mode=" + mode
        )*/

        if ( FMAPR.exportItemAlreadyExists(object_type, object_name, object_event, yes3_fmapr_data_element_name) ) {

            YES3.hello("No can do: this item is already in the export specification.");

            return false;
        }

        FMAPR.closeItemEditorForm();

        if ( insert_as==="item" && object_type==="form" && object_name===ALL_OF_THEM && (!FMAPR.project.is_longitudinal || object_event===ALL_OF_THEM) ){

            FMAPR.addEverything();
            return true;
        }

        YES3.isBusy();

        //return true;

        /**
         * In edit mode the same row tag is used for the generated html,
         * so 'theRowBeforeWhich' is removed after insertion, effectively replacing it.
         */
         if ( yes3_fmapr_data_element_name && mode==="edit"){

            theRowBeforeWhich = $(`tr[data-yes3_fmapr_data_element_name='${yes3_fmapr_data_element_name}']`);

            if ( theRowBeforeWhich.length ){

                FMAPR.markAsRemoved( theRowBeforeWhich );
            }
            else {
                // error fallback is to append with new row tag
                theRowBeforeWhich = {};
                yes3_fmapr_data_element_name = "";
            }
        }

        /**
         * in insert mode the 'theRowBeforeWhich' row tag is not used in the generated html,
         * so it's emptied after being used to establish the insertion point.
         */
        else if ( yes3_fmapr_data_element_name && mode==="insert"){

            theRowBeforeWhich = $(`tr[data-yes3_fmapr_data_element_name='${yes3_fmapr_data_element_name}']`);

            if ( !theRowBeforeWhich.length ){

                theRowBeforeWhich = {}; // now we are appending
            }
            yes3_fmapr_data_element_name = "";
        }
        // the fallback is append row with a new row tag
        else {

            mode = "append";
            yes3_fmapr_data_element_name = "";
            theRowBeforeWhich = {};
        }

        //return true;

        if ( insert_as==="item" && object_type==="form" ){

            saveResult = FMAPR.exportItemEditorSave_form(object_name, object_event, theRowBeforeWhich, yes3_fmapr_data_element_name, mode);
        }
        else if ( insert_as==="item" && object_type==="field" ){

            saveResult = FMAPR.exportItemEditorSave_field(object_name, object_event, theRowBeforeWhich, yes3_fmapr_data_element_name, mode);
        }
        else if ( insert_as==="forms" ){

            saveResult = FMAPR.exportItemEditorSave_forms(object_name, object_event, theRowBeforeWhich, yes3_fmapr_data_element_name, mode);
        }
        else if ( insert_as==="fields" ){

            saveResult = FMAPR.exportItemEditorSave_fields(object_name, object_event, theRowBeforeWhich, yes3_fmapr_data_element_name, mode);
        }

        //YES3.debugMessage("save result:", saveResult);
        
        FMAPR.removeMarkedRows();

        FMAPR.renumberRows();

        FMAPR.clearSelections();

        FMAPR.markAsDirty("Be sure to save your changes ( * - unsaved).");

        FMAPR.resizeExportItemsTable();

        if ( mode==="append" ){

            FMAPR.scrollExportItemsTableToBottom();
        }

        FMAPR.resetExportItemEditor();

        FMAPR.markColumnCountChanged();

        FMAPR.warnIfMixedRepeating();

        YES3.notBusy();
    })

    // cancel button

    theForm.find("input[type='button'].yes3-button-caption-cancel").on("click", function(){

        FMAPR.closeItemEditorForm();
    })

    // redcap object type selector

    theForm.find("input[type='radio'][name=object_type]").on("click", function(){

        let theTable = $(this).closest("table");
        let object_type = $(this).val();

        if ( object_type==="form" ){

            theTable.find(".yes3-fmapr-form-only").show();
            theTable.find(".yes3-fmapr-field-only").hide();
            theTable.find("input#yes3-fmapr-insert-as-item").prop("checked", true);
        }
        else if ( object_type==="field" ){

            theTable.find(".yes3-fmapr-form-only").hide();
            theTable.find(".yes3-fmapr-field-only").show();
        }

        if ( FMAPR.project.is_longitudinal ){
            theTable.find("tr.yes3-longitudinal-only").show();
        }

        $(this).closest("div.yes3-panel").find(".yes3-save-button").css("visibility", "visible");

        theTable.find("select[name=object_event]")
            .empty()
            .append(FMAPR.getAllEventOptionsHtml())
            .trigger('change')
        ;

        if ( theTable.find(`[name=redcap_${object_type}]`).val() ){

            theTable.find(`[name=redcap_${object_type}]`).trigger("change");
        }
    })

    // event selector
    theForm.find("select[name=object_event]").on("change", function(){

        let objectType = $(this)
            .closest("table")
            .find("input[name=object_type]:checked").val()
        ;

        if ( typeof objectType !== "string" ) return false;

        let event = $(this).val();

        //YES3.debugMessage(objectType, event);

        if ( objectType==="form") {
        
            $(this).closest("table").find("select[name=redcap_form]")
                .empty().append(FMAPR.getFormOptionsHtmlForEvent(event));
        }
        else if ( objectType==="field") {
        
            $(this).closest("table").find("input[name=redcap_field]")
                .autocomplete({
                    source: FMAPR.getFieldAutoCompleteSource(event),
                    minLength: 1,
                    select: function(event, ui) {

                        if (!ui.item) {
                            $(this)
                                .val("")
                                .prop("title", "")
                            ;
       
                            return false;
                        }

                        $(this)
                            .val(ui.item.value)
                            .prop("title", ui.item.label)
                        ;

                        return false;
                    }
                });
        }   
    })

    // field selector
    theForm.find("input[name=redcap_field]").on("change", function(){

        let fieldName = $(this).val();

        if ( !fieldName ){

            return false;
        }

        if ( !FMAPR.project.field_index[fieldName] ) {

            YES3.hello(`'${$(this).val()}' is not a valid field name.`);
            $(this).val("");
        }
   })

    // form selector
    theForm.find("select[name=redcap_form]").on("change", function(){

        let thePanel = $(this).closest("div.yes3-panel");
        let form_name = $(this).val();

        if ( form_name === ALL_FORMS ) form_name = ALL_OF_THEM; // handle alias

        if ( !form_name ){

            thePanel.find(".yes3-save-button").css("visibility", "hidden");
            thePanel.find(".yes3-fmapr-all-forms-indicated").hide();   
            return false;
        }
        else {

            if ( form_name===ALL_OF_THEM ){

                thePanel.find(".yes3-fmapr-all-forms-indicated").show();   
            }
            else {

                thePanel.find(".yes3-fmapr-all-forms-indicated").hide();   
            }
        }
    })
}

FMAPR.exportItemEditorSave_form = function(object_name, object_event, theRowBeforeWhich, yes3_fmapr_data_element_name, mode)
{           
    yes3_fmapr_data_element_name = FMAPR.exportItemFormHtml(object_name, object_event, theRowBeforeWhich, yes3_fmapr_data_element_name, mode, true);
    return "form item saved as " + yes3_fmapr_data_element_name;
}

FMAPR.exportItemEditorSave_field = function(object_name, object_event, theRowBeforeWhich, yes3_fmapr_data_element_name, mode, batch)
{           
    return FMAPR.exportItemFieldHtml(object_name, object_event, theRowBeforeWhich, yes3_fmapr_data_element_name, mode, true, batch);
}

FMAPR.exportItemEditorSave_forms = function(object_name, object_event, theRowBeforeWhich, yes3_fmapr_data_element_name, mode)
{     
    let forms = FMAPR.formsAllowedForEvent(object_event);
    let k = 0;
    let d = 0;
    let result = "";

    YES3.isBusy();

    for (let i=0; i<forms.length; i++){
        if ( FMAPR.exportItemAlreadyExists("form", forms[i], object_event) ){
            d++;
            //YES3.debugMessage("Duplicate form item not added: ", forms[i], object_event);
        }
        else {
            k++;
            FMAPR.exportItemEditorSave_form(forms[i], object_event, theRowBeforeWhich, yes3_fmapr_data_element_name, mode);
        }
    }

    YES3.notBusy();

    result = `${k} form(s) were added to the specification. ${d} were ignored because they were already in the export specification.`;

    YES3.hello(result);

    return k;
}

FMAPR.exportItemEditorSave_fields = function(object_name, object_event, theRowBeforeWhich, yes3_fmapr_data_element_name, mode)
{     
    let forms = [];
    let k = 0;
    let d = 0;
    let result = "";
    let j=0;
    let fields = [];
    let html = "";

    let $fmaprBody = $('table.yes3-fmapr-specification').first().find('tbody');

    if ( object_name===ALL_FORMS ) object_name = ALL_OF_THEM; // handle alias

    if ( object_name===ALL_OF_THEM ) {

        forms = FMAPR.formsAllowedForEvent(object_event);
    }
    else {

        forms = [object_name];
    }

    for (let i=0; i<forms.length; i++){

        if ( FMAPR.formApprovedForLayout( forms[i]) ){

            fields = FMAPR.fieldsAllowedForForm(forms[i]);

            for (j=0; j<fields.length; j++){

                object_name = fields[j];

                if ( FMAPR.exportItemAlreadyExists("field", object_name, object_event) ){
                    d++;
                    //YES3.debugMessage("Duplicate field item not added: ", object_name, object_event);
                }
                else {
                    k++;
                    html += FMAPR.exportItemFieldHtml(object_name, object_event, theRowBeforeWhich, yes3_fmapr_data_element_name, mode, true, true);
                }
            }
        }
    }
    
    if ( YES3.isEmpty( theRowBeforeWhich ) ){
        theRowBeforeWhich = FMAPR.getNewFieldRow();
    }
    mode = ( YES3.isEmpty(theRowBeforeWhich) ) ? "append" : "insert";

    if ( mode==="insert" ){
        $( html ).insertBefore( theRowBeforeWhich );
    }
    else {
       $fmaprBody.append( html );
    }

    FMAPR.setRowSelectorListenersV2();

    FMAPR.setRepeatLayoutConstraints();

    result = `${k} fields(s) were added to the specification. ${d} was/were ignored because they were already in the export specification.`;

    YES3.hello(result);

    return k;
}

FMAPR.isValidSasLibref = function(libref) {

  // Libref must be 1 to 8 characters
  if (typeof libref !== 'string' || libref.length < 1 || libref.length > 8) {
    return false;
  }

  // Must start with a letter or underscore
  if (!/^[A-Za-z_]/.test(libref)) {
    return false;
  }

  // Can only contain letters, numbers, or underscores
  if (!/^[A-Za-z_][A-Za-z0-9_]*$/.test(libref)) {
    return false;
  }

  return true;
}


FMAPR.setExportSettingsListeners = function()
{
    let settings = FMAPR.getExportSettingsContainer();
    
    settings.find('input, select, textarea')
        .off()
        .on('change', function(){

            FMAPR.markAsDirty();

            FMAPR.exportSettingsTableSkipper();
        })
    ;

    FMAPR.setExportSettingCriterionFieldListener(); 
    FMAPR.setExportSettingsLayoutListeners();
    FMAPR.setExportNameListener();
    FMAPR.setExportSasCodeListener();
    FMAPR.setSASLibrefListener();
}

FMAPR.setExportSasCodeListener = function()
{
    let $export_sascode = $('input[name=export_sascode]');

    $export_sascode.on('change', function(){

        if ( $(this).is(':checked') ){

            FMAPR.setSasCodeDefaults();
        }
        else {

            $('input[name=export_sascode_libref]').val("");
            $('input[name=export_sascode_libref_path]').val("");
        }
    });
}

FMAPR.setSASLibrefListener = function()
{
    $('input[name=export_sascode_libref]').on('change', function(){

        let libref = $(this).val();

        if ( !FMAPR.isValidSasLibref(libref) ){

            YES3.hello(`Invalid SAS libref '${libref}'. A libref must be 1 to 8 characters long, start with a letter or underscore, and contain only letters, numbers, or underscores.`);
            $(this).val("");
            return false;
        }

        $(this).val(libref.toLowerCase());

        FMAPR.markAsDirty();
    })
}

FMAPR.setExportSettingCriterionFieldListener = function()
{
    $(`input[data-setting=export_criterion_field]`)
    .autocomplete({
        source: FMAPR.project.field_autoselect_source,
        minLength: 1,
        select: function(event, ui) {

          if (!ui.item) {
              $(this).val("");
              return false;
          }

          $(this).val(ui.item.value);

           return false;
        }
     })
     .change(function(){

        let specificationParent = $(this).closest("table");

        FMAPR.setExportSettingCriterionEventSelectOptions( specificationParent );

        //FMAPR.markAsDirty();
     })
     ;
}

FMAPR.setExportSettingCriterionEventSelectOptions = function()
{
    let eventSelect = $("select#export_criterion_event");

    eventSelect.empty();

    let export_criterion_field = $('input#export_criterion_field').val() || "";

    if ( export_criterion_field ) {

        let optionsHtml = FMAPR.eventSelectOptionsForField( export_criterion_field );

        eventSelect.append(optionsHtml);
    }
}

FMAPR.setExportNameListener = function()
{
    $('input[name=export_name]').on("change", function(){

        let new_export_name = $(this).val();

        // name has changed and exists in the specs list
        
        if ( new_export_name !== FMAPR.exportSpecification.export_name 
            && FMAPR.dupeFilenameForExportName(new_export_name, FMAPR.exportSpecification.export_uuid) ){

            YES3.hello(`No can do: the proposed export name '${new_export_name}' has a conflict with another export defined for this project.`);
            $(this).val(FMAPR.exportSpecification.export_name);
            return true;
        }
        

        if ( !new_export_name.isValidFilename() ) {

            YES3.hello(`Invalid export name '${new_export_name}'. An export name must begin with an alphabetic character, end with an alphanumeric character and contain only alphanumeric characters, spaces, underscores and hyphens in between.`);
            $(this).val(FMAPR.exportSpecification.export_name);
            return false;
        }   

        FMAPR.postExportName( new_export_name );
        return true;
    })
    return false;
}

FMAPR.setExportSettingsLayoutListeners = function() {

    $('input[data-setting=export_layout]')
        .off()
        .on('click', function(){

            //YES3.debugMessage('setExportSettingsLayoutListeners', $(this).val());

            if ( $(this).val()==="r" ){

                $('.yes3-fmapr-repeating-only').show();
            }
            else {

                $('.yes3-fmapr-repeating-only').hide();
            }
    
            FMAPR.markAsDirty();
        })
    ;
}

FMAPR.exportSettingsTableSkipper = function ()
{
    let export_selection = $('input[data-setting=export_selection]:checked').val() || "0";

    $('table[name=yes3-fmapr-settings').each(function(){
    
        if ( export_selection==="2" ) {
    
            $(this).find(".yes3-fmapr-if-selected").show().removeClass('yes3-fmapr-skipped-over');
        }
        else {
    
            $(this).find(".yes3-fmapr-if-selected").hide().addClass('yes3-fmapr-skipped-over');
        }
    }) 
    
    $('tr.yes3-fmapr-longitudinal-only:not(.yes3-fmapr-skipped-over)').each(function(){
    
        if ( FMAPR.project.is_longitudinal ){
    
            $(this).show().removeClass('yes3-fmapr-skipped-over');
        }
        else {
    
            $(this).hide().addClass('yes3-fmapr-skipped-over');
        }
    });

    let export_hash_recordid = $('input[name=export_hash_recordid]').is(':checked') ;

    if ( export_hash_recordid ){
        
        $('.yes3-fmapr-if-hash-recordid').show();
    }
    else {

        $('.yes3-fmapr-if-hash-recordid').hide();
    }

    let export_sascode = $('input[name=export_sascode]').is(':checked') ;

    if ( export_sascode ){

        $('.yes3-fmapr-if-sascode').show();
    }
    else {

        $('.yes3-fmapr-if-sascode').hide();
    }

    if ( YES3.EMSettings['enable-host-filesystem-exports'] ){
        $('.yes3-fmapr-export-options').show();
    }
    else {
        $('.yes3-fmapr-export-options').hide();
    }

    if ( !YES3.EMSettings['enable-host-filesystem-exports'] || YES3.EMSettings['enable-user-data-downloads'] ){
        $('.yes3-fmapr-download-options').show();
    }
    else {
        $('.yes3-fmapr-download-options').hide();
    }

}

FMAPR.renumberRows = function()
{   
    FMAPR.rowCount = 0;

    $('tr.yes3-fmapr-data-element:not(.yes3-fmapr-new-field)').each(function(){

        FMAPR.rowCount++;
        $(this).find('td.yes3-fmapr-row-number').html(FMAPR.rowCount);
    })

    // show or hide ui elements based on the number of rows
    if ( FMAPR.rowCount > 0 ){
        $('span.yes3-fmapr-nonempty').show();
    } else {
        $('span.yes3-fmapr-nonempty').hide();
    }
}

FMAPR.itemCount = function()
{
    return $('tr.yes3-fmapr-data-element:not(.yes3-fmapr-new-field)').length;
}

FMAPR.getExportUUID = function()
{
    // before the export spec is loaded, FMAPR.export_uuid is empty so we use the GET setting
    if ( FMAPR.export_uuid.length > 0 ) return FMAPR.export_uuid;

    return (new URLSearchParams(document.location.search)).get('export_uuid');

}

FMAPR.getExportItemsJSONV2 = function()
{
    let items = [];

    const itemProto = {

        export_item_name: "",
        export_item_description: "",
        export_item_origin: "",

        redcap_object_type: "",
        redcap_field_name: "",
        redcap_form_name: "",
        redcap_event_id: "",

        uspec_element_name: "",
        uspec_element_value_map: []
        
    };

    const vMapProto = {
        uspec_value: "",
        redcap_field_value: ""
    }

    let item = null;
    let tbl = FMAPR.getExportItemsTable();
    let itemRows = FMAPR.getExportItemRows();
    let itemRow = null;
    let lovRows = null;
    let lovRow = null;
    let i = 0;
    let j = 0;
    let vMap = null;
    let origin = "";
    let itemError = false;

    //YES3.debugMessage("getExportItemsJSONV2: itemRows", itemRows);

    itemRows.each(function(){

        //YES3.debugMessage("getExportItemsJSONV2:itemRow", $(this));

        item = Object.create(itemProto);
        itemError = false;
        item.export_item_name = $(this).data("yes3_fmapr_data_element_name");
        item.export_item_origin = $(this).data("element_origin");
        item.uspec_element_value_map = [];

        if ( item.export_item_origin==="redcap" ){

            item.redcap_object_type = $(this).data("object_type");

            if ( item.redcap_object_type==="form" ){

                item.redcap_form_name = $(this).data("object_name");

                // must have a form name
                if ( !item.redcap_form_name ){

                    itemError = true;
                }
            }
            else if ( item.redcap_object_type==="field" ){

                item.redcap_field_name =  $(this).data("object_name");

                // must have a form name
                if ( !item.redcap_field_name ){

                    itemError = true;
                }
            }

            item.redcap_event_id =  $(this).data("object_event");

            if ( !item.redcap_event_id && FMAPR.project.is_longitudinal ){

                    itemError = true;
            }

            if ( !itemError ){

                items.push( item );
            }
        }
    });

    //YES3.debugMessage('getExportItemsJSONV2', items);

    return JSON.stringify( items );
}

FMAPR.eventSelectOptionsForField = function( field_name )
{
    let field_index = FMAPR.project.field_index[field_name];

    let html = "";

    if ( typeof field_index === "number" ){

        let form_name = FMAPR.project.field_metadata[field_index].form_name;
        let form_index = FMAPR.project.form_index[form_name];

        let form_events = FMAPR.project.form_metadata[form_index].form_events;

        for (let i=0; i<form_events.length; i++){

            html += `<option value='${form_events[i].event_id}'>${FMAPR.project.event_metadata[form_events[i].event_id].event_name}</option>`;
        }
    }

    return html;
}

FMAPR.getExportSettingsContainer = function(){

    return $('div#yes3-fmapr-settings');
}

FMAPR.getExportItemsTable = function()
{
    return $("table#yes3-fmapr-export-items-table");
}

FMAPR.getExportItemsTableWrapper = function()
{
    return $("div#yes3-fmapr-wrapper");
}

FMAPR.getExportEditorFooter = function()
{
    return $("div#yes3-fmapr-footer");
}

FMAPR.getExportNewItemOptionsSection = function()
{
    return $("div#yes3-fmapr-new-item-options");
}

FMAPR.getExportItemsTableBody = function()
{
    return $("tbody#yes3-fmapr-export-items-tbody");
}

FMAPR.getExportItemEditor = function()
{
    return $("div#yes3-fmapr-item-editor-panel");
}

FMAPR.getExportRapidEntryEditor = function()
{
    return $(`tr#${FMAPR.rapidEntryFormRowId}`);
}

/**
 * The export 'removed' checkbox handler (see yes3_export_manager.php)
 */
FMAPR.highlightRemovedIfSelected = function(){

    $removed = $('input#yes3-export-removed');

    if ( $removed.is(':checked') ) {
        $removed.parent().addClass('yes3-fmapr-removed');
    } else {
        $removed.parent().removeClass('yes3-fmapr-removed');
    }
}


$(window).on('resize', YES3.deBounce(function() {

    FMAPR.resizeExportItemsTable();
    FMAPR.resizeExportSettingsContainer();

}));

/**
 * things to do after the project settings are loaded
 * The event 'yes3-fmapr.settings' is triggered by FMAPR.getProjectSettings()
 */
$(document).on('yes3-fmapr.settings', function(){

    YES3.debugMessage("on.yes3-fmapr.settings");

    /**
     * set the settings input listeners
     */
    FMAPR.setExportSettingsListeners();

    /**
     * set the export items listeners
     */
    FMAPR.setExportItemEditorListeners();

    /**
     * enable tooltips
     */
    YES3.setBsTooltipPropertiesFromSpecs( FMAPR.tooltipSpecs );
    YES3.setBsTooltipListenersForContainer( document.querySelector('#yes3-container') );

    /**
     * Start the AJAX chain by loading the event prefixes
     */
     FMAPR.loadEventSettings();
})

FMAPR.loadEventSettings = function()
{     
    YES3.requestService( { 
        "request": "getEventSettings"
    }, FMAPR.loadloadEventSettingsCallback, true );
}

FMAPR.loadloadEventSettingsCallback = function( response )
{
    //YES3.debugMessage('loadloadEventSettingsCallback', response);

    FMAPR.event_settings = response;

    /**
     * now we load all the export names, for export_name uniqueness checking
     */
    FMAPR.getExportNames( FMAPR.loadSpecification );
}

FMAPR.loadSpecification = function( log_id )
{  
    log_id = parseInt(log_id) || 0;

    YES3.isBusy();
    
    // set permissions to false
    FMAPR.userPermissions.design = false;
    FMAPR.userPermissions.export = false;

    if ( FMAPR.reloadParms.export_uuid.length ) {

        FMAPR.reloadParms.export_uuid = "";
    }

    //console.warn('loadSpecification', log_id, FMAPR.getExportUUID() );

    FMAPR.clearSystemMessage();

    FMAPR.ErrReport_closePanel();
    
    YES3.requestService( { 
        "request": "getExportSpecification", 
        "export_uuid": FMAPR.getExportUUID(),
        "log_id": log_id
    }, FMAPR.loadSpecificationCallback, true );
}

FMAPR.loadSpecificationCallback = function( response )
{
    YES3.notBusy();

    YES3.debugMessage("loadSpecificationCallback", response);

    if ( YES3.isEmptyObject(response) ){

        YES3.hello("ERROR: This export specification is empty or corrupted. Cannot open.");
        return false;
    }

    if ( typeof response === "object" ){

        if ( response.permission !== "allowed" ){

            YES3.hello("PERMISSION DENIED: You do not have permission to export at least one form or field that is included in this export specification.");
            return false;
        }

        // if no items then...
        if ( !response.export_items_json ){

            response.permission_export = false;
            FMAPR.postExportMessage("This export specification has as yet no export items defined, so the download/export features are disabled.");
        }

        FMAPR.exportSpecification = response;

        // redundancies for convenience
        FMAPR.log_id = parseInt(FMAPR.exportSpecification.log_id) || 0;
        FMAPR.export_uuid = FMAPR.exportSpecification.export_uuid || "";
        FMAPR.column_count = parseInt(FMAPR.exportSpecification.column_count) || 0;
        FMAPR.export_has_repeatables = FMAPR.exportSpecification.export_has_repeatables || "";

        // set permissions
        FMAPR.userPermissions.design = FMAPR.exportSpecification.permission_design;
 
        FMAPR.userPermissions.export = FMAPR.exportSpecification.permission_export;

        FMAPR.showDashboardHead();

        FMAPR.displayDashboardOption(FMAPR.dashboard_option);

        FMAPR.populateSpecificationTables( FMAPR.exportSpecification );

        FMAPR.handleExportErrorMessages(response.errmsg, response.permission_design);

        FMAPR.postMessage(`Export specification '${FMAPR.exportSpecification.export_name}' loaded with ${FMAPR.column_count} export column(s).`);
    }

    return true;
}


/**
 * DOWNLOAD 
 */

FMAPR.openDownloadForm = function()
{
    let thePanel = YES3.openPanel("yes3-fmapr-download-panel");
}

FMAPR.displayCopyright = function(){

    $('div#yes3-fmapr-copyright').html(YES3.copyright);
}

FMAPR.removeTheIrrelevantElements = function()
{

    // remove the batch export column if not enabled
    if ( !YES3.EMSettings['enable-host-filesystem-exports'] || !YES3.EMSettings['enable-cron-batch-exports'] ){
        $('.yes3-fmapr-batch-export-options').hide();
    }
}

FMAPR.displayCopyright = function(){

    $('div#yes3-fmapr-copyright').html(YES3.copyright);
}

/**
 * -------------------------------------------
 * STARTUP ACTIONS - yes3_export_editor plugin
 * -------------------------------------------
 * 
 * getCodeFor() actions
 * --------------------
 * The $module->getCodeFor() function is found in Yes3Trait.php
 * and is applied to all plugin pages
 * 
 * (1) Outputs JS and CSS code to the page in the following orders:
 *     yes3.js, common.js, [plugin name].js
 *     yes3.css, common.css, [plugin name].css
 * 
 * (2) Outputs JS code to create YES3.moduleObject,
 *     which is the the REDCap 'JavaScriptModuleObject' (see REDCap EM dev documentation). 
 *     Includes JS equivalents to the EM Framework getUrl(), log(), tt() and tt_add() functions.
 * 
 * (3) Outputs JS code to create YES3.moduleProperties, which is an
 *     object that includes all public properties from the instantiated 
 *     EM class (Yes3Exporter). Mainly useful for service and readme Urls, username,
 *     EM version.
 * 
 * (4) Outputs html/yes3.html, YES3 html relevant to most plugins.
 *     Mainly the standard dialogs hello, yesno and contextmenu.
 * 
 * --------------
 * ONLOAD ACTIONS
 * --------------
 * 
 * (IIFEs from the JS libraries loaded by getCodeFor())
 * 
 * yes3.js
 * -------
 * (1) Color scheme detected, theme toggle listener established
 * (2) csrf token added to jQuery ajax header
 * (3) jQuery draggables set up
 * 
 * common.js
 * ---------
 * (no onload actions)
 * 
 * yes3_export_editor.js (this plugin's script)
 * ------------------------------------------
 * (1) getProjectSettings (located in common.js )
 *      - YES3 startup housekeeping
 *      - triggers 'yes3-fmapr.settings' event
 * 
 * (2) 'yes3-fmapr.settings' event handler (this script)
 *      - local (FMAPR) startup housekeeping
 *      - starts an AJAX chain that retrieves event prefixes
 *        and export specifications, then populates the editors
 *     
 */
$( function () {

    YES3.isBusy(); // will be cleared by the loadspecification callback

    // set the static, html-defined tooltip handlers
    YES3.setBsTooltipListenersForContainer( document.body );

    FMAPR.removeTheIrrelevantElements(); // remove irrelevant UI elements, depending on EM settings

    YES3.RegisterApplicationNameSpace('FMAPR');

    YES3.hideContextMenuOnClickOutside();

    FMAPR.makeSortable( FMAPR.getExportItemsTableBody() );

    FMAPR.setSpecialKeyListeners();

    YES3.contentLoaded = false;

    YES3.RegisterApplicationNameSpace('FMAPR_Editor');

    YES3.Functions.Help_openPanel( true );

    /**
     * located in common.js:
     * (1) Populates FMAPR.project (project, form and field metadata)
     * (2) runs YES3.displayActionIcons
     * (3) Triggers 'yes3-fmapr.settings' event
     */
    FMAPR.getProjectSettings(); 
})
 

 