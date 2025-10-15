YES3.Functions.Page_refresh = function() {

    FMAPR.getExportNames( FMAPR.loadSpecifications );
}

YES3.Functions.Open_validator = function() {

    FMAPR.openValidator();
}

YES3.Functions.Help_openDocumentation = function() {

    FMAPR.openDocumentation();
}

FMAPR.useYes3Functions = true; // override FMAPR function if function of same name exists in YES3 namespace

FMAPR.exportSpecification = {};

FMAPR.exportSpecifications = [];

FMAPR.windowNumber = 0;

FMAPR.openWindows = [];

FMAPR.SHOW_REMOVED = false;

FMAPR.exportTable = function(){

    return $("table#yes3-fmapr-export-table");
}

FMAPR.whatIsItYesOrNo = function( whatIsIt ){

    if ( YES3.isEmpty( whatIsIt ) ) return "no";

    if ( whatIsIt ===  1  ) return "yes";
    if ( whatIsIt === "1" ) return "yes";
    if ( whatIsIt === "y" ) return "yes";
    if ( whatIsIt === "Y" ) return "yes";

    return "no";
}

/** labels for tooltips and table columns */

FMAPR.tooltipForLayout = function( layout, details ){

    details = details || false;

    if ( details ) {
        if ( layout === "v" ) return "Export Layout: <strong>Vertical</strong><br />One row per record[, event][, instance]";
        if ( layout === "h" ) return "Export Layout: <strong>Horizontal</strong><br />One row per record[, instance]";
        if ( layout === "r" ) return "Export Layout: <strong>Repeating</strong><br />(DEPRECATED: equivalent to vertical with a single repeating form)";
    }

    if ( !layout ) return "?";
    if ( layout === "v" ) return "vertical";
    if ( layout === "h" ) return "horizontal";
    if ( layout === "r" ) return "repeating*";

    return "?";
}

FMAPR.tooltipForExportName = function( export_name ){
    return `Edit the export specification for '${export_name}'.`;
}

FMAPR.tooltipForDownload = function( export_name ){
    return `Download the data dictionary, dataset or full zipped payload for <strong>'${export_name}'</strong> to your computer.<br><br>The full payload includes the data dictionary (excel-friendly csv), the datasheet (csv or tsv), the export information file (json) and, if requested, generated SAS code`;
}

FMAPR.tooltipForRefresh = function( export_name ){
    return `Refresh the export specification for <strong>'${export_name}'</strong>.`;
}

FMAPR.tooltipForExportToHost = function( export_name ){
    return `Export the full payload for <strong>'${export_name}'</strong> to the host file system.<br><br>The full payload includes the data dictionary (excel-friendly csv), the datasheet (csv or tsv), the export information file (json) and, if requested, generated SAS code`;
}

FMAPR.toolTipForTrashcan = function( export_name, removed ){

    if ( removed ) {
        return `Restore the export specification <strong>'${export_name}'</strong>.`;
    }

    return `Remove the export specification <strong>'${export_name}'</strong>.`;
}

FMAPR.toolTipForEditIcon = function( export_name ){
    return `Edit the export specification for <strong>'${export_name}'</strong>.`;
}

/** tooltips */

FMAPR.setStaticToolTips = function(){

    const $pageControls = YES3.container().find('div.yes3-fmapr-controls');
    const $tableControls =FMAPR.exportTable().find('thead');

    YES3.setBsTooltipListenersForContainer ( $tableControls[0] );
    YES3.setBsTooltipListenersForContainer ( $pageControls[0] );
}

FMAPR.setDynamicToolTips = function(){

    const $exportTableBody = FMAPR.exportTable().find('tbody');
    const $tableFooter = FMAPR.exportTable().find('tfoot');

    YES3.setBsTooltipListenersForContainer ( $exportTableBody[0] );
    YES3.setBsTooltipListenersForContainer ( $tableFooter[0] );
}

FMAPR.setToolTips = function(){

    const $exportTableBody = FMAPR.exportTable().find('tbody');

     YES3.setBsTooltipListenersForContainer ( $exportTableBody[0] );
}

/* export table load/refresh */

FMAPR.refreshExportTable = function( response )
{
    console.warn('refreshExportTable', response);

    if ( !YES3.userRights.isDesigner ) FMAPR.exportTable().find('.yes3-designer-only').remove();
    
    let $exportTableBody = FMAPR.exportTable().find("tbody");

    let repeaters = 0;

    YES3.clearBsTooltipsForContainer( $exportTableBody[0] ); // remove any existing tooltips

    $exportTableBody.empty();

    for (let i=0; i<response.length; i++){

        response[i].export_order = i+1;

        if ( response[i].export_layout === "r" ) repeaters++;

        const $tr = $( FMAPR.exportTableRowHtml( response[i] ) );

        // the trash cell is rendered separately because it needs to be updated when the export is removed or restored
        FMAPR.renderExportTrashcanCell( $tr );

        $exportTableBody.append( $tr );
    }

    FMAPR.setExportTableSortHandler();

    FMAPR.renderExportVisibilityCell();

    FMAPR.setDynamicToolTips();

    //exportTableRowCount.html( `${response.exportTable.length} exports` );

    FMAPR.exportTable().show();

    $(window).trigger('resize');

    if (repeaters > 0) {
        YES3.hello(`<p>${repeaters} export specification(s) for this project use(s) the Repeating Form layout.</p>`+
            `<p>This layout is deprecated and will be converted to the Vertical layout when the export specification is edited and saved.</p>`+
            `<p>Until then, you will not be able to export data for these specifications.</p>`
        );
    }   
}

FMAPR.setExportTableSortHandler = function(){

    FMAPR.exportTable().find("tbody")
    .sortable({
        items: 'tr',
        cursor: 'grab',
        axis: 'y',
        dropOnEmpty: false,
        start: function (e, ui) {
            ui.item.addClass("yes3-fmapr-row-selected");
        },
        stop: function (e, ui) {
            ui.item.removeClass("yes3-fmapr-row-selected");
            FMAPR.exportTableAfterUpdate();
        }
    });
}

/**
 * called when an export is removed, restored or re-ordered
 */
FMAPR.exportTableAfterUpdate = function(){

    // get the collection of rows in the table
    const $rows = FMAPR.exportTable().find("tbody tr");

    const updates = [];

    // iterate through the rows and update the export_order attribute

    let unremovedOrder = 1;
    let removedOrder = 9001;

    $rows.each( function(i, row){

        const removed = $(row).attr('data-removed') || "0";

        const order = removed === "0" ? unremovedOrder++ : removedOrder++;

        const log_id = parseInt($(row).data('log_id'));

        $(row).attr('data-export_order', order.toString());

        updates.push({"log_id": log_id, "key": "export_order", "value": order});
        updates.push({"log_id": log_id, "key": "removed", "value": removed});
    });

    YES3.debugMessage('exportTableAfterUpdate', updates);
  
    YES3.requestService( { 
        "request": "update_export_table_settings", 
        "updates": updates
    }, FMAPR.exportTableAfterUpdateCallback, true );
}

FMAPR.exportTableAfterUpdateCallback = function( response ){

    YES3.debugMessage('exportTableAfterUpdateCallback', response);

    if ( response.result === YES3_RESULT_FAIL){

        YES3.hello('There was a problem updating the export table. Reloading the page.');

        FMAPR.getExportNames( FMAPR.loadSpecifications );
    }
}

FMAPR.exportTableRowHtml = function( data ){

    data.removed = data.removed || "0";

    const trashIcon = data.removed === "0" ? "far fa-trash-alt" : "fas fa-trash-restore-alt";
    const trashAction = data.removed === "0" ? "1" : "0";
    const repeater = data.export_layout === "r" ? true : false;
    const repeater_title = "Edit this export specification to convert it to the Vertical layout";
    
    const layout_tooltip = FMAPR.tooltipForLayout(data.export_layout, true);
    const edit_tooltip = (repeater) ? repeater_title : FMAPR.toolTipForEditIcon(data.export_name);
    const refresh_tooltip = FMAPR.tooltipForRefresh(data.export_name);
    const download_tooltip = FMAPR.tooltipForDownload(data.export_name);
    const exporttohost_tooltip = FMAPR.tooltipForExportToHost(data.export_name);

    let rowHtml = `<tr id="yes3-fmapr-export-${data.log_id}" data-export_uuid="${data.export_uuid}" data-log_id="${data.log_id}" data-export_order="${data.export_order}" data-removed="${data.removed}" class="yes3-fmapr-export">`;

    if ( YES3.userRights.isDesigner ) {

        rowHtml += `<td class="yes3-col-sm yes3-halign-center yes3-required-column" data-bs-toggle="tooltip" title="${refresh_tooltip}"><i class="fas fa-refresh" onclick="FMAPR.refreshSpecification('${data.export_uuid}')" ></i></td>`;
        rowHtml += `<td class="yes3-col-sm yes3-halign-center yes3-required-column" data-bs-toggle="tooltip" title="${edit_tooltip}"><i class="fas fa-edit" onclick="FMAPR.editExport('${data.log_id}')" ></i></td>`;
    }

    if ( data.permission_export ) {
        let cellHtml = "";
        if ( !repeater ) {
            if ( !YES3.EMSettings['enable-host-filesystem-exports'] || YES3.EMSettings['enable-user-data-downloads'] ) cellHtml += `<i class="fas fa-download" data-bs-toggle="tooltip" title="${download_tooltip}" onclick="FMAPR.openDownloadForm('${data.log_id}')" ></i>`;
            if ( YES3.EMSettings['enable-host-filesystem-exports'] ) cellHtml += `<i class="fas fa-file-export" data-bs-toggle="tooltip" title="${exporttohost_tooltip}" onclick="FMAPR.exportToHost('${data.log_id}')" ></i>`;
        }
        rowHtml += `<td class="yes3-col-sm yes3-halign-center yes3-required-column yes3-icon-container">${cellHtml}</td>`;
    }
    else {
        let banClass = "";
        //if ( data.error_messages && data.error_messages.length > 0 ) banClass = "yes3-error";
        //else if ( data.warning_messages && data.warning_messages.length > 0 ) banClass = "yes3-warning";

        rowHtml += `<td class="yes3-col-sm yes3-halign-center yes3-required-column"><i class="fas fa-ban yes3-warning" data-bs-toggle="tooltip" onclick="FMAPR.reportWhyBanned('${data.log_id}')" title="Either you do not have permission to download or export this specification, or the specification is incomplete. Click for more information."></i></td>`;
    }

    rowHtml += `<td class="yes3-col-md yes3-halign-left yes3-required-column" data-name="export_name"  >${data.export_name}</td>`;
    rowHtml += `<td class="yes3-col-lg yes3-halign-left"                      data-name="export_label" >${data.export_label}</td>`;
    rowHtml += `<td class="yes3-col-sm yes3-halign-center"  data-bs-toggle="tooltip" data-name="export_layout" title="${layout_tooltip}">${FMAPR.tooltipForLayout(data.export_layout)}</td>`;
    if ( YES3.EMSettings['enable-cron-batch-exports'] && YES3.EMSettings['enable-host-filesystem-exports'] ) rowHtml += `<td class="yes3-col-sm yes3-halign-center" data-name="export_batch" >${FMAPR.whatIsItYesOrNo(data.export_batch)}</td>`;
    rowHtml += `<td class="yes3-col-sm yes3-halign-center"                    data-name="column_count" >${data.column_count}</td>`;

    // if ( YES3.EMSettings['enable-validator'] === 'Y' ) {
    //     rowHtml += `<td class="yes3-col-sm yes3-halign-center"><i class="fas fa-file-import" onclick="FMAPR.openValidator('${data.log_id}')" title="Open the validator/importer"></i></td>`;
    // }


    if ( YES3.userRights.isDesigner ) {
        
        rowHtml += `<td class="yes3-col-sm yes3-halign-center yes3-required-column yes3-fmapr-trash-cell"></td>`;
    }

    rowHtml += `</tr>`;

    return rowHtml;
}

FMAPR.renderExportTrashcanCell = function( $tr ){

    const log_id = $tr.data('log_id');
    const removed = $tr.attr('data-removed') || "0";

    const trashIcon = removed === "0" ? "far fa-trash-alt" : "fas fa-trash-restore-alt";
    const trashAction = removed === "0" ? "1" : "0";

    const tooltip = FMAPR.toolTipForTrashcan( $tr.find('td[data-name="export_name"]').text(), removed === "1" );

    //YES3.debugMessage('renderExportTrashcanCell', log_id, removed, trashIcon, trashAction);

    $tr.find('td.yes3-fmapr-trash-cell').html(`<i class="${trashIcon}" title="${tooltip}" data-bs-toggle="tooltip" onclick="FMAPR.toggleRemovedState('${log_id}', '${trashAction}')"></i>`);
}

FMAPR.renderExportVisibilityCell = function(){

    const $visibilityCell = $("td#yes3-fmapr-visibility-control");
    const removedExportCount = FMAPR.exportTable().find('tr[data-removed="1"]').length;

    $visibilityCell.empty();

    if ( removedExportCount > 0 && !FMAPR.SHOW_REMOVED ){

        $visibilityCell.html(`<i class="fas fa-eye" data-bs-toggle="tooltip" title="show ${removedExportCount} removed export(s)" onclick="FMAPR.showRemovedExports(true)"></i>`);
    }
    else if ( removedExportCount > 0 && FMAPR.SHOW_REMOVED ){

        $visibilityCell.html(`<i class="fas fa-eye-slash" data-bs-toggle="tooltip" title="hide ${removedExportCount} removed export(s)" onclick="FMAPR.showRemovedExports(false)"></i>`);
    }
    else {

        $visibilityCell.html('');
    }
}

FMAPR.showRemovedExports = function( show ){

    FMAPR.SHOW_REMOVED = show;
    FMAPR.renderExportTable();
    FMAPR.renderExportVisibilityCell();
    FMAPR.setDynamicToolTips();
    if ( show ) FMAPR.postMessage(`The display now includes the removed export(s).`);
    else FMAPR.postMessage(`The display no longer includes the removed export(s).`);
}

FMAPR.toggleRemovedState = function( log_id ){

    const $tr = $(`tr#yes3-fmapr-export-${log_id}`);

    // toggle the 'removed' attribute of the export table row
    const removed = $tr.attr('data-removed') === "0" ? "1" : "0";

    // set the 'removed' attribute of the export table row to the specified value
    $tr.attr('data-removed', removed);

    // update the trashcan icon in the row
    FMAPR.renderExportTrashcanCell( $tr );

    // potentially hide the row if the 'show removed' option is not enabled
    FMAPR.renderExportTable();

    // update the contens of the visibility control cell (icon to show or hide removed exports)
    FMAPR.renderExportVisibilityCell();

    // renumber the export_order attribute of each row, and save the new order to the database
    FMAPR.exportTableAfterUpdate();

    // set dynamic tooltips for all relevant elements
    FMAPR.setDynamicToolTips();

    if ( removed === "1" ) FMAPR.postMessage(`Export removed but not deleted. Click <i class="fas fa-eye" title="show removed export(s)" onclick="FMAPR.showRemovedExports(true)"></i> to show removed exports.`);
    else FMAPR.postMessage(`Export restored.`);
}

FMAPR.getExportSpecification = function( log_id ){
    
    const exportSpec = FMAPR.exportSpecifications.find( spec => parseInt(spec.log_id) === parseInt(log_id) );

    return exportSpec;
}

FMAPR.getExportUUID = function(){

    return FMAPR.exportSpecification.export_uuid;
}

FMAPR.selectRow = function( log_id ){

    $tr = $(`tr#yes3-fmapr-export-${log_id}`);

    $("tr.yes3-fmapr-selected-row").removeClass("yes3-fmapr-selected-row");

    $tr.addClass("yes3-fmapr-selected-row");
}

FMAPR.postExportEditedMesage = function(){

    const n = $("i.fas.fa-refresh.yes3-fmapr-edited").length;

    if ( n === 0 ) return;

    FMAPR.postMessage(`* At least 1 export has been opened for editing. Please click <i class="fas fa-refresh yes3-fmapr-edited"></i> to refresh the edited export(s)`);
}


FMAPR.reportWhyBanned = function( log_id ){

    FMAPR.selectRow( log_id );

    const exportSpec = FMAPR.getExportSpecification( parseInt(log_id) );

    let msgHtml = `<p class='yes3-bold'>${exportSpec.export_name}</p>`;

    if ( exportSpec.error_messages && exportSpec.error_messages.length > 0 ){

        exportSpec.error_messages.forEach( msg => msgHtml += `<li class="yes3-semibold">${msg}</li>` );
    }
    if ( exportSpec.warning_messages && exportSpec.warning_messages.length > 0 ){

        exportSpec.warning_messages.forEach( msg => msgHtml += `<li class="yes3-subdued">${msg}</li>` );
    }

    if ( msgHtml === "" ) msgHtml = "No error or warning messages were reported.";
    else msgHtml = `<ul>${msgHtml}</ul>`;

    YES3.hello( msgHtml, null, true, "Ban report" );   
}

FMAPR.renderExportTable = function()
{
    if ( !FMAPR.exportTable().is(":visible") ) return;

    const ROW_WIDTH_CUTOFF = 700;

    const scrollbarWidth = 20;

    const $exportTable = FMAPR.exportTable();

    const $exportTableBody = $("tbody#yes3-fmapr-export-tbody");

    const $exportTableFooter = $("tfoot#yes3-fmapr-export-tfoot");

    const $parentSection = $("div#yes3-container").parent();

    const $footer = $("div#yes3-fmapr-footer");

    //$exportTable.show();

    // optionally show or hide the removed exports
    if ( !FMAPR.SHOW_REMOVED ) $exportTableBody.find('tr[data-removed="1"]').hide();
    else $exportTableBody.find('tr[data-removed="1"]').show();

    $exportTableBody.css({'height': 'auto'});

    const windowHeight = $(window).innerHeight();

    // position() returns offset relative to parent object (the table)
    //let bodyHeight = tableHeight - $exportTableBody.position().top;

    // nah, let's just use the height of the table body
    let bodyHeight =$exportTableBody.outerHeight();
    let footerHeight = $exportTableFooter.outerHeight() + $footer.outerHeight();
    let bodyY = $exportTableBody.offset().top;
    let overflow = windowHeight - bodyY - bodyHeight - footerHeight - scrollbarWidth;

    //let tableWidth = $('div#yes3-fmapr-wrapper').width();
    const tableWidth = $exportTable.width();
    const rowWidth = tableWidth - scrollbarWidth;

    const $exportRowsToDisplay = FMAPR.SHOW_REMOVED ? $exportTableBody.find('tr') : $exportTableBody.find('tr[data-removed="0"]');

    const $requiredHeaderCells = FMAPR.exportTable().find('thead tr th.yes3-required-column');
    const $requiredFooterCells = FMAPR.exportTable().find('tfoot tr td.yes3-required-column');
    const $requiredTbodyCells  = $exportRowsToDisplay.find('td.yes3-required-column');
$
    const $notRequiredHeaderCells = FMAPR.exportTable().find('thead tr th:not(.yes3-required-column)');
    const $notRequiredFooterCells = FMAPR.exportTable().find('tfoot tr td:not(.yes3-required-column)');
    const $notRequiredTbodyCells  = $exportRowsToDisplay.find('td:not(.yes3-required-column)');

    const requiredColumnCount = $requiredHeaderCells.length;

    //$exportTable.css({'height': tableHeight+'px'});

    if ( overflow < 0 )  $exportTableBody.css({'height': bodyHeight + overflow + 'px'});
    
    //$exportTableBody.css({'height': bodyHeight+'px'});

    if ( rowWidth < ROW_WIDTH_CUTOFF ){

        const cellWidth = rowWidth / requiredColumnCount;
            
        $notRequiredHeaderCells.hide();
        $notRequiredTbodyCells.hide();
        $notRequiredFooterCells.hide();
$
        $requiredHeaderCells.css({'width': cellWidth+'px', 'max-width': cellWidth+'px'}).show();
        $requiredTbodyCells.css({'width': cellWidth+'px', 'max-width': cellWidth+'px'}).show();
        $requiredFooterCells.css({'width': cellWidth+'px', 'max-width': cellWidth+'px'}).show();
    }
    else {

        const smColumns = FMAPR.exportTable().find('thead>tr>th.yes3-col-sm').length;
        const mdColumns = FMAPR.exportTable().find('thead>tr>th.yes3-col-md').length;
        const lgColumns = FMAPR.exportTable().find('thead>tr>th.yes3-col-lg').length;

        const units = smColumns + 2*mdColumns + 3*lgColumns;

        const smWidth = rowWidth / units;
        const mdWidth = 2 * smWidth;
        const lgWidth = 3 * smWidth;

        $exportTable.find('.yes3-col-sm').css({'width': smWidth+'px', 'max-width': smWidth+'px'}).show();
        $exportTable.find('.yes3-col-md').css({'width': mdWidth+'px', 'max-width': mdWidth+'px'}).show();
        $exportTable.find('.yes3-col-lg').css({'width': lgWidth+'px', 'max-width': lgWidth+'px'}).show();
    }

    //exportTableBody.scrollTop(exportTableBody.prop('scrollHeight') - exportTableBody.height());
}

FMAPR.refreshSpecification = function( export_uuid ){

    YES3.debugMessage('FMAPR.refreshSpecification', export_uuid);

    if ( !export_uuid ) return;

    //YES3.isBusy(YES3.captions.wait);

    performance.mark('FMAPR.refreshSpecification.start');

    YES3.requestService( { 
        "request": "getExportSpecificationList", 
        "get_removed": 1, // this might be a removed export
        "export_uuid": export_uuid
    }, FMAPR.refreshSpecificationCallback, true );
}

FMAPR.refreshSpecificationCallback = function( response ){

    let export_specification = response[0];

    if ( !export_specification ){
        console.error('refreshSpecificationCallback: no export specification found for export_uuid', response.export_uuid);
        return false;
    }

    const $tr = $(`tr[data-export_uuid="${export_specification.export_uuid}"]`);

    if ( !$tr.length ){

        console.error('refreshSpecificationCallback: no row found for export_uuid', export_specification.export_uuid);
        return false;
    }

    // update the row with the new export specification data
    $tr.replaceWith( FMAPR.exportTableRowHtml( export_specification ) );

    // re-select the row
    const $newTr = $(`tr#yes3-fmapr-export-${export_specification.log_id}`)   ;

    // re-render the trashcan cell
    FMAPR.renderExportTrashcanCell( $newTr );

    FMAPR.renderExportVisibilityCell();

    // resize in case of enfarglement
    $(window).trigger('resize');

    // update the export specification in the exportSpecifications array
    const index = FMAPR.exportSpecifications.findIndex( spec => spec.export_uuid === export_specification.export_uuid );
    if ( index !== -1 ){
        FMAPR.exportSpecifications[index] = export_specification;
    }

    FMAPR.postMessage(`Export specification <strong>${export_specification.export_name}</strong> has been refreshed.`);
}

//FMAPR.getExportNames( FMAPR.loadSpecifications );

FMAPR.loadSpecifications = function( get_removed )
{
    get_removed = get_removed || 1;

    YES3.isBusy();

    performance.mark('FMAPR.loadSpecifications.start');
    
    YES3.requestService( { 
        "request": "getExportSpecificationList", 
        "get_removed": get_removed
    }, FMAPR.loadSpecificationsCallback, true );
}

FMAPR.loadSpecificationsCallback = function( response )
{
    console.log('loadSpecificationsCallback', response, typeof response);

        FMAPR.refreshExportTable( response );
        FMAPR.exportSpecifications = response;

    // count the nuumber of specifications that are removed
    const removedCount = response.filter( spec => spec.removed === "1" ).length;

    const totalDisplayed = FMAPR.exportTable().find('tr.yes3-fmapr-export:visible').length;

    const removedDisplayed = FMAPR.exportTable().find('tr.yes3-fmapr-export[data-removed="1"]:visible').length;

    YES3.notBusy();
    performance.mark('FMAPR.loadSpecifications.end');

    performance.measure('FMAPR.loadSpecifications', 'FMAPR.loadSpecifications.start', 'FMAPR.loadSpecifications.end');

    const timeToLoad = (performance.getEntriesByName('FMAPR.loadSpecifications')[0].duration/1000).toFixed(4);

    let msg = `${totalDisplayed} export specification(s) loaded in ${timeToLoad} seconds.`;

    if ( removedDisplayed > 0 ) msg += `, including ${removedDisplayed} removed export(s).`;

    if ( removedCount > 0 && removedDisplayed === 0) msg += `. Click <i class="fas fa-eye" title="show ${removedCount} removed export(s)" onclick="FMAPR.showRemovedExports(true)"></i> to display ${removedCount} removed export(s).`;

    FMAPR.postMessage( msg );
}

/**
 * DOWNLOAD 
 */

FMAPR.openDownloadForm = function( log_id )
{
    FMAPR.selectRow( log_id );

    FMAPR.exportSpecification = FMAPR.getExportSpecification( log_id );

    let thePanel = YES3.openPanel("yes3-fmapr-download-panel");
}

/**
 * NEW EXPORT
 */
FMAPR.NewExport_openPanel = function()
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

FMAPR.NewExport_execute = function()
{
    let new_export_uuid = YES3.uuidv4();
    let new_export_name = $("input#new_export_name").val().trim();
    let new_export_layout = $("input[type=radio][name=new_export_layout]:checked").val();

    if ( !new_export_name || !new_export_layout ){

        YES3.hello("Please enter both the export name and the export layout.");
        return false;
    }

    if ( !new_export_name.isValidFilename() ) {

        YES3.hello(`Invalid export name '${new_export_name}'. An export name must begin with an alphabetic character, end with an alphanumeric character and contain only alphanumeric characters, spaces, underscores and hyphens in between.`);
        return false;
    }

    if ( FMAPR.dupeFilenameForExportName(new_export_name) ){

        YES3.hello(`No can do: the proposed export name '${new_export_name}' has a conflict with another export defined for this project.`);
        return false;
    }
    
    FMAPR.reloadParms.export_uuid = new_export_uuid

    /**
     * Note that the same callback function is shared by 
     * saveExportSpecification() and newExportSpecification().
     * 
     * This callback will load the specification identified in FMAPR.reloadParms,
     * and perform the required UI prep.
     */
    YES3.requestService( 
        {
            "request": "addExportSpecification",
            "export_uuid": new_export_uuid,
            "export_name": new_export_name,
            "export_layout": new_export_layout
        }, 
        FMAPR.saveExportSpecificationCallback, 
        false 
    );

    FMAPR.NewExport_closePanel();
}

FMAPR.saveExportSpecificationCallback = function( response ){
  
    YES3.debugMessage( 'saveExportSpecificationCallback', response );

    FMAPR.postMessage( response );

    FMAPR.getExportNames( FMAPR.loadSpecifications ); // reload the list of export names and the specifications
}

/*
 * DOCUMENTATION
 */

FMAPR.openDocumentation = function(){

    window.open(YES3.documentationUrl, "_blank");
}

FMAPR.openValidator = function()
{

    const _MAX_WIDTH_ = 1500;
    const _MIN_WIDTH_ = 500;
    const _MAX_HEIGHT_ = 1000;
    const _MIN_HEIGHT_ = 450;
    
    let w = $(window).outerWidth() * 0.9; if ( w > _MAX_WIDTH_ ) w = _MAX_WIDTH_; else if ( w < _MIN_WIDTH_ ) w = _MIN_WIDTH_;  
    let h = $(window).outerHeight() * 0.9; if ( h > _MAX_HEIGHT_ ) h = _MAX_HEIGHT_; else if ( h < _MIN_HEIGHT_ ) h = _MIN_HEIGHT_;

    let url = YES3.moduleObject.getUrl("plugins/yes3_export_validator.php");

    const windowName = "yes3-export-validator";

    const handle = window.open( url, windowName, FMAPR.popupWindowFeatures(w, h) );

    if ( !handle ){

        console.error("oops: export editor wouldn't open");
        return false;
    }
    else {
        FMAPR.logOpenWindow(handle, windowName);
        return true;
    }

}

/**
 * EDIT EXPORT
 */
FMAPR.editExport = function( log_id ){

    const _MAX_WIDTH_ = 1500;
    const _MIN_WIDTH_ = 500;
    const _MAX_HEIGHT_ = 1000;
    const _MIN_HEIGHT_ = 450;

    FMAPR.selectRow( log_id );

    // mark the row as edited, then post a message 

    $(`tr#yes3-fmapr-export-${log_id}`).find('i.fas.fa-refresh').addClass("yes3-fmapr-edited");

    FMAPR.postExportEditedMesage();

    FMAPR.exportSpecification = FMAPR.getExportSpecification( log_id );

    const windowName = "yes3Export-" + FMAPR.windowNumber++;

    const paramsObj = new URLSearchParams({
        export_uuid: FMAPR.exportSpecification.export_uuid
    });
    
    let w = $(window).outerWidth() * 0.9; if ( w > _MAX_WIDTH_ ) w = _MAX_WIDTH_; else if ( w < _MIN_WIDTH_ ) w = _MIN_WIDTH_;  
    let h = $(window).outerHeight() * 0.9; if ( h > _MAX_HEIGHT_ ) h = _MAX_HEIGHT_; else if ( h < _MIN_HEIGHT_ ) h = _MIN_HEIGHT_;

    let url = YES3.moduleObject.getUrl("plugins/yes3_export_editor.php") + "&" + paramsObj.toString();

    const windowId = FMAPR.exportSpecification.export_uuid;

    const handle = window.open( url, windowName, FMAPR.popupWindowFeatures(w, h) );

    if ( !handle ){

        console.error("oops: export editor wouldn't open");
        return false;
    }
    else {
        FMAPR.logOpenWindow(handle, windowId);
        return true;
    }
}

FMAPR.popupWindowFeatures = function(w, h){

    w=w||800;
    h=h||800;

    // make all popups the same height for now..
    //h = $(window).innerHeight() * 0.8;

    const refX = window.screenX;
    const refY = window.screenY;

    const openWindowCount = FMAPR.countOpenWindows();

    const left = refX + 100*openWindowCount;
    const top  = refY + 50*openWindowCount;
    
    return `left=${left},top=${top},width=${w},height=${h}`;
}


FMAPR.countOpenWindows = function(){

    let k = 0;

    for (w of FMAPR.openWindows){

        if ( !w.closed ) k++;
    }

    return k;
}

FMAPR.windowIsOpen = function(windowId){

    for (w of FMAPR.openWindows){

        if ( w.yes3WindowId===windowId && !w.closed ){
            return true;
        }
    }
    return false;
}

FMAPR.logOpenWindow = function(w, yes3WindowId){

    $(w).prop('yes3WindowId', yes3WindowId);
    FMAPR.openWindows.push(w);
}

FMAPR.removeTheIrrelevantElements = function()
{
    // remove the batch export column if not enabled
    if ( !YES3.EMSettings['enable-host-filesystem-exports'] || !YES3.EMSettings['enable-cron-batch-exports'] ){
        $('.yes3-fmapr-batch-exports').remove();
    }
}


/**
 * things to do when the settings are loaded
 */
$(document).on('yes3-fmapr.settings', function(){

    FMAPR.getExportNames( FMAPR.loadSpecifications ); // reload the list of export names and the specifications

    $(window).resize( function(){

        FMAPR.renderExportTable();
    });
})

$( function(){

    YES3.contentLoaded = false;

    FMAPR.setStaticToolTips(); // tooltips that don't change

    FMAPR.removeTheIrrelevantElements();

    //YES3.displayActionIcons();

    FMAPR.getProjectSettings( 1 ); // will fire 'yes3-fmapr-settings' event
})