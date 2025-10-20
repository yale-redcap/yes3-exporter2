let FMAPR = {
    specification_index: 0,
    maxRawREDCapDataElementNumber: 0,
    dirty: false,
    buildInProgress: false,
    mapperLoaded: false,
    intervalId: -1,
    intervalCounter: 0,
    EXPORTER_DOWNLOAD_COOKIE_NAME: "yes3-exporter-download", // Name of the cookie used to communicate export results
    specificationElements: [],
    insertionElements: [],
    insertionForms: [],
    insertionRowId: "",
    formNameConstraint: "",
    specifications: [],
    map_record: {},
    settings: {},
    reloadParms: {
        "export_uuid": ""
    },
    export_specification: {
        "export_uuid" : ""
    }
}
 
FMAPR.conditionUserInput = function( s ){
    //return s.replace(/</g, "&lt;").replace(/>/g, "&gt;");
    return s;
}

 
FMAPR.noop = function(){}
 
FMAPR.isEmptyArray = function( x ){
    if ( typeof x === "undefined" ) return true;
    return !x.length;
 
}
 
FMAPR.isTruthy = function( x ){
    if ( typeof x === "undefined" ) return false;
    return x;
 
}

FMAPR.displayHelpPanel = function()
{
    YES3.hello("Sorry bud, you're on your own.");
}
 
 
FMAPR.postMessage = function( msg, urgent, keepForever ) {

    urgent = urgent || false;
    keepForever = keepForever || false;

    let msgDiv = $('div#yes3-message');
    
    if (msgDiv) {

        let msgClass = ["yes3-message-color", "yes3-alert-color"];
        let msgClassIndex = (urgent) ? 1 : 0;

        if (!msgDiv.hasClass(msgClass[msgClassIndex])) {
            msgDiv.removeClass(msgClass[1 - msgClassIndex]).addClass(msgClass[msgClassIndex]);
        }

        msgDiv.html(msg).show();

        // Clear any existing timeout
        if (FMAPR.messageTimeoutId) {
            clearTimeout(FMAPR.messageTimeoutId);
        }

        // Clear message after 30 seconds
        if (!keepForever) {
            FMAPR.messageTimeoutId = setTimeout(function() {
                msgDiv.html("").hide();
            }, 30000);
        }

    } else {
        alert(msg);
    }
}

FMAPR.clearMessage = function(){
    if ( $('div#yes3-message') ) {
       $('#yes3-message').html("").show();
    }
 }
 
FMAPR.postAjaxMessage = function( msg )
{
FMAPR.postMessage(msg);
}

FMAPR.postAPIResponse = function(response)
{
FMAPR.postMessage(response);
}

FMAPR.getProjectSettings = function( reduced ) 
{
    //console.log( 'getProjectSettings' );
    reduced = reduced || 0;

    YES3.requestService({'request':'get_project_settings', 'reduced': reduced}, FMAPR.getProjectSettingsCallback, true);
}

FMAPR.getProjectSettingsCallback = function(response) 
{
    //console.log( 'getProjectSettingsCallback', response );

    FMAPR.project = response;

    YES3.displayActionIcons();

    // handler must defined in plugin JS
    $(document).trigger('yes3-fmapr.settings');
}

FMAPR.getExportSettings = function()
{

    YES3.requestService({"request": "getExportSettings"}, FMAPR.getExportSettingsCallback, true);  
}

FMAPR.getExportSettingsCallback = function(response)
{
    //YES3.debugMessage('getExportSettingsCallback:', response);

    FMAPR.stored_export_settings = response;
    
    FMAPR.populateExportSpecificationSelect();
}

FMAPR.populateExportSpecificationSelect = function()
{
    let html = "<option value='' disabled selected>select a specification</option>";
    let spec = {};

    for (let s=0; s<FMAPR.stored_export_settings.specification_settings.length; s++){

        spec = FMAPR.stored_export_settings.specification_settings[s];

        spec.export_name = escapeHTML( spec.export_name );

        if ( spec.removed === "0" ){

            html += `<option value='${spec.export_uuid}'>${spec.export_name} (${spec.export_layout})</option>`;
        }
        $("select#export_uuid").empty().append(html);
    }
}

/*** ACTION ICONS ***/

FMAPR.displayActionIconsAndInputs = function()
{
    if ( !FMAPR.mapperLoaded ){

        $('i.yes3-loaded').addClass('yes3-action-disabled');
    }
    else {

        $('i.yes3-action-icon:not(.yes3-fmapr-clean)').removeClass('yes3-action-disabled');

        if ( YES3.dirty ){
            $('i.yes3-fmapr-display-when-clean').addClass('yes3-action-disabled');
            $('i.yes3-fmapr-display-when-dirty').removeClass('yes3-action-disabled');
            $('i#yes3-fmapr-save-control').addClass('yes3-fmapr-dirty');
        }
        else {
            $('i.yes3-fmapr-display-when-clean').removeClass('yes3-action-disabled');
            $('i.yes3-fmapr-display-when-dirty').addClass('yes3-action-disabled');
            $('i#yes3-fmapr-save-control').removeClass('yes3-fmapr-dirty');
        }

        if ( FMAPR.export_specification && FMAPR.export_specification.export_layout=== "r" ){

            $('i.yes3-fmapr-display-when-not-repeating').addClass('yes3-action-disabled');

            /**
             * only one repeating form allowed
             */
            if ( $('tr.yes3-fmapr-data-element').length > 1 ){

                $('i.yes3-fmapr-bulk-insert').addClass('yes3-action-disabled');
            }
            else {

                $('i.yes3-fmapr-bulk-insert').removeClass('yes3-action-disabled');
            }
        }
    }

    FMAPR.disableIconsWhenEverythingAdded();

    YES3.setActionIconListeners( YES3.container() );
}

// RENAME
FMAPR.displayActionInputs = function()
{
    if ( YES3.dirty ){
        $('input.yes3-fmapr-display-when-clean').hide();
        $('input.yes3-fmapr-display-when-dirty').show();
    }
    else {
        $('input.yes3-fmapr-display-when-clean').show();
        $('input.yes3-fmapr-display-when-dirty').hide();
    }

    if ( FMAPR.export_specification && FMAPR.export_specification.export_layout=== "r" ){

        $('i.yes3-fmapr-display-when-not-repeating').addClass('yes3-action-disabled');

        /**
         * only one repeating form allowed
         */
        if ( $('tr.yes3-fmapr-data-element').length > 1 ){

            $('i.yes3-fmapr-bulk-insert').addClass('yes3-action-disabled');
        }
        else {

            $('i.yes3-fmapr-bulk-insert').removeClass('yes3-action-disabled');
        }
    }
}

FMAPR.markAsClean = function( forceRedisplay )
{
    forceRedisplay = forceRedisplay | false;
    
    if ( YES3.dirty || forceRedisplay ) {

        YES3.dirty = false;

        //FMAPR.displayActionIconsAndInputs();
        YES3.displayActionIcons();
        FMAPR.displayActionInputs();

        window.onbeforeunload = null;

    }
}

FMAPR.markAsDirty = function( message )
{
    if ( FMAPR.buildInProgress ){
        return true;
    }

    if ( !YES3.userRights.isDesigner ){

        YES3.hello("Because you are not a designer on this project, this editor is READ-ONLY and you will not be able to save any changes to the specification.");
        FMAPR.postMessage("READ ONLY", true);
        return false;
    }

    message = message || "Be sure to save your changes!";
    
    if ( !YES3.dirty ) {

        //console.log("FMAPR.markAsDirty: Setting dirty to true");

        YES3.dirty = true;
        //FMAPR.displayActionIconsAndInputs();

        YES3.displayActionIcons();

        FMAPR.displayActionInputs();

        FMAPR.postMessage(message, true);

        window.onbeforeunload = function() {
            return "";
        }
    }

    //console.log("FMAPR.markAsDirty: YES3.dirty = " + YES3.dirty);

    return true;

    //FMAPR.reportStatus();
}

FMAPR.markAsBad = function( element )
{
    element.closest('tr').find('td:not(.yes3-gutter-right-center), input, select, span, label, i').addClass('yes3-error');
}

FMAPR.markAsGood = function( element )
{
    element.closest('tr').find('td, input, select, span, label, i').removeClass('yes3-error');
}

FMAPR.markAllGood = function()
{
    $(".yes3-error").removeClass('yes3-error');
}

FMAPR.someBad = function()
{
    return $(".yes3-error").length;
}

FMAPR.pointAt = function( theRow )
{
    let theContainer = $("div#yes3-container").parent();

    let x = theRow.offset().left - theParent.offset().left;
    let y = theRow.offset().top - theParent.offset().top;

    let py = theRow.outerHeight() + y - 2;

}

FMAPR.getParentTable = function( that )
{
    return that.closest("table");
}

/**
 * DOWNLOADS AND EXPORTS (shared twixt maneger and editor)
 */

/**
 * DOWNLOAD 
 */

FMAPR.closeDownloadForm = function(retainModalState)
{
    retainModalState = retainModalState || false;
    YES3.closePanel('yes3-fmapr-download-panel', retainModalState);
}

FMAPR.downloadExecute = function()
{
    let exportOption = $("input[type=radio][name=yes3-fmapr-export]:checked").val();

    /**
     * start listening for export cookies
     */
    FMAPR.awakenTheCookieMonster();

    YES3.isBusy( YES3.captions.wait );

    if ( exportOption==="datadictionary"){
        FMAPR.downloadDataDictionary();
    }

    else if ( exportOption==="data"){
        //console.log('downloadData will be called.');
        FMAPR.downloadData();
    }

    else if ( exportOption==="zip"){
        FMAPR.downloadZip();
    }

    else if ( exportOption==="dmsp"){
        FMAPR.downloadDmsp();
    }

    FMAPR.closeDownloadForm(true);
}

FMAPR.downloadDataDictionary = function()
{
    YES3.postServiceRequest({

        request: "downloadDataDictionary",
        export_uuid: FMAPR.getExportUUID()
    });
}

FMAPR.downloadData = function()
{
    //console.log('downloadData called.');
    
    YES3.postServiceRequest({

        request: "downloadDataRecords",
        export_uuid: FMAPR.getExportUUID()
    });
}

FMAPR.downloadZip = function()
{
    YES3.postServiceRequest({

        request: "downloadZip",
        export_uuid: FMAPR.getExportUUID()
    });
}

FMAPR.downloadDmsp = function()
{
    YES3.postServiceRequest({

        request: "downloadDmsp",
        export_uuid: FMAPR.getExportUUID()
    });
}

/**
 * EXPORT TO FILESYSTEM
 * May be called from the export manager (log_id > 0 ) or export editor ( log_id = 0 )
 */
FMAPR.exportToHost = function( log_id)
{       
    if ( log_id ){
        // make the selected row the current spec
        FMAPR.selectRow( log_id );
        FMAPR.exportSpecification = FMAPR.getExportSpecification( log_id );
    }

    const warningMessage = "Preparing to export data to: <br><br><strong>" + FMAPR.project.host_filesystem_target 
        + "</strong><br><br>This will replace any existing data for this export on the file system.<br><br>Are you sure you want to proceed?";

    YES3.YesNo(warningMessage, FMAPR.exportToHostGo);
}

FMAPR.exportToHostGo = function()
{     
    FMAPR.postMessage("Export underway...");

    YES3.isBusy( YES3.captions.wait_exporting_data );

    YES3.requestService(
        {
            'request': 'exportData',
            'export_uuid': FMAPR.getExportUUID()
        }, FMAPR.exportDataCallback
    );  
}

FMAPR.exportDataCallback = function( response )
{
    // convert newline characters to <br> tags
    response = response.replace(/\n/g, "<br />");
    YES3.hello(response);
    FMAPR.clearMessage();
    YES3.notBusy();
}

/**
 * 
 * The cookie monster waits for the server to send an export cookie
 * (#records exported).
 * 
 * This seems to be the only way to communicate download info
 * back to the browser
 * 
 * Stops when cookie is handled or 5 minutes elapsed
 * 
 * @returns 
 * 
 */

/**
 * 
 * The cookie monster waits for the server to send an export cookie
 * (#records exported).
 * 
 * This seems to be the only way to communicate download info
 * back to the browser
 * 
 * Stops when cookie is handled or 5 minutes elapsed
 * 
 * @returns 
 * 
 */

FMAPR.awakenTheCookieMonster = function(){

    FMAPR.intervalCounter = 0;

    // delete any existing cookie
    //YES3.deleteCookie( FMAPR.EXPORTER_DOWNLOAD_COOKIE_NAME );

    FMAPR.intervalId = window.setInterval(function(){

        FMAPR.intervalCounter++;

        if ( FMAPR.cookieMonster() || FMAPR.intervalCounter > 300 ){

            FMAPR.killTheCookieMonster();
        }

    }, 1000);
}

FMAPR.killTheCookieMonster = function(){

    clearInterval( FMAPR.intervalId );
    FMAPR.intervalId = -1;
    YES3.notBusy();
}

FMAPR.cookieMonster = function() {

    if ( !document.cookie ) return false;
    /*
    if ( typeof FMAPR.exportSpecification === "undefined" ) return false;

    if ( typeof FMAPR.exportSpecification.export_uuid === "undefined" ) return false;

    if ( !FMAPR.exportSpecification.export_uuid.length ) return false;
    */
    const cookieData = YES3.getCookie( FMAPR.EXPORTER_DOWNLOAD_COOKIE_NAME );

    if ( cookieData !== undefined ){
        
        //const cookieData = JSON.parse(cookieDataJson);

        //console.log("cookieMonster! cookieData: ", cookieData);

        /*
        let msg = `Download complete for export <strong>${cookieData.export_name}</strong>.`;

        if ( cookieData.export_rows == 0 ){

            msg += " No records were exported.";
        }
        else if (( cookieData.export_rows == -1 )){

            msg += ` A data dictionary for ${cookieData.export_columns} variables was downloaded.`;
        }
        else if ( cookieData.export_rows > 0 ){

            msg += ` ${cookieData.export_rows} records having ${cookieData.export_columns} columns were downloaded.`;
        }
        */

        YES3.deleteCookie( FMAPR.EXPORTER_DOWNLOAD_COOKIE_NAME );

        YES3.hello(cookieData);

        YES3.notBusy();
        
        return true;
    }
}

FMAPR.sanitizeForFilename = function(input) {
  if (typeof input !== "string") return "";

  return input
    .toLowerCase()                              // (1) convert to lowercase
    .replace(/^[^a-z]+/, "")                    // (2) strip leading non-alpha
    .replace(/[^a-z0-9 _:\.-]/g, "")            // (3) allow space, dash, underscore, digits, letters, . and :
    .replace(/[ .:-]/g, "_")                    // (4) convert space, dash, period, colon -> underscore
    .replace(/_+/g, "_")                        // (5) collapse multiple underscores
    .replace(/[^a-z0-9]+$/, "");                // (6) strip trailing non-alphanumeric
}

/**
 * Check if an export name already exists in the list of export specifications.
 * 
 * Name is rejected if the sanitized version of the name matches the sanitized version of an existing name,
 * because we need to ensure unique filenames when exporting.
 * 
 * @param {*} export_name 
 * @returns 
 */
FMAPR.dupeFilenameForExportName = function(export_name, except_uuid)
{   
    except_uuid = except_uuid || "";
    
    let thatName = FMAPR.sanitizeForFilename(export_name);

    // iterate over FMAPR.allExportNames
    for (let i=0; i<FMAPR.allExportNames.length; i++){

        if ( FMAPR.allExportNames[i].export_uuid === except_uuid ){
            continue;
        }

        let thisName = FMAPR.sanitizeForFilename(FMAPR.allExportNames[i].export_name);

        if ( thisName === thatName ){

            return true;
        }
    }
    
    return false;
}

FMAPR.getExportNames = function( thenCall )
{     
    YES3.requestService( { 
        "request": "getExportNames"
    }, function( response ){

        FMAPR.allExportNames = response || [];

        if ( typeof thenCall === "function" ){

            thenCall();
        }

    }, true );
}

$( function () {
})


