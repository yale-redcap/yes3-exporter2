FMAPR.renderUI = function( data ){

    //console.log( 'FMAPR.renderUI', data );

    if ( !data ) {
        return;
    }
}

FMAPR.formData = {};
FMAPR.fileToValidate = null;
FMAPR.selectedFile = null;

FMAPR.loadSpecifications = function( get_removed )
{
    get_removed = get_removed || 0;

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

    YES3.notBusy();

    performance.mark('FMAPR.loadSpecifications.end');

    performance.measure('FMAPR.loadSpecifications', 'FMAPR.loadSpecifications.start', 'FMAPR.loadSpecifications.end');

    const timeToLoad = (performance.getEntriesByName('FMAPR.loadSpecifications')[0].duration/1000).toFixed(4);

    let msg = `${response.length} export specification(s) loaded in ${timeToLoad} seconds.`;

    FMAPR.postMessage( msg );

    FMAPR.populateExportSelection( response );
}

FMAPR.populateExportSelection = function( exports )
{
    let container = $( '#yes3-fmapr-export-selection' );

    container.empty();

    if ( !exports || exports.length === 0 ){
        return;
    }

    exports.forEach( function( exp ){
        container.append( `<div id='${exp.export_uuid}'><span class="export_name" title="click to select this export">${exp.export_name}</span><span class="export_label">${exp.export_label}</span></div>` );
    });

    FMAPR.resize();

    FMAPR.setValidatorListeners();
}

FMAPR.showUploadOptionsContainer = function()
{
    const $uploadOptionsContainer = $( '#yes3-fmapr-export-upload' );

    $uploadOptionsContainer.show();
}

FMAPR.hideUploadOptionsContainer = function()
{
    const $uploadOptionsContainer = $( '#yes3-fmapr-export-upload' );

    $uploadOptionsContainer.hide();
}

FMAPR.showValidateOptionsContainer = function()
{
    if ( !FMAPR.fileToValidate ) {
        return false;
    }
    
    const $validateOptionsContainer = $( '#yes3-fmapr-export-validate' );
    const $validateButton = $( '#yes3-fmapr-export-validate-button' );

    $validateButton.val( `validate: ${FMAPR.fileToValidate}` );

    $validateOptionsContainer.show();
}

FMAPR.hideValidateOptionsContainer = function()
{
    const $validateOptionsContainer = $( '#yes3-fmapr-export-validate' );

    $validateOptionsContainer.hide();
}

FMAPR.showValidationResultsTable = function()
{
    const $resultsTable = $( '#yes3-fmapr-export-validate-results-container' );

    $resultsTable.css('display', 'block');
}   

FMAPR.hideValidationResultsTable = function()
{
    const $resultsTable = $( '#yes3-fmapr-export-validate-results-container' );

    $resultsTable.css('display', 'none');
}

FMAPR.isEmptyFormData = function(fd) {
  if (!(fd instanceof FormData)) return true;
  for (const _ of fd.entries()) return false;
  return true;
}

FMAPR.setValidatorListeners = function()
{

    $( '#yes3-fmapr-export-selection span.export_name' ).off('click').on('click', function(){

        FMAPR.selectedFile = null;

        const $uploadOptionsContainer = $( '#yes3-fmapr-export-upload' );
        const $uploadButton = $( '#yes3-fmapr-export-upload-button' );
        const $parentDiv = $( this ).closest( 'div' );
        let $selectedExport = $( '#yes3-fmapr-export-selection div.selected' );

        if ( $parentDiv.hasClass( 'selected' ) ) {
            $parentDiv.removeClass( 'selected' );
            FMAPR.hideUploadOptionsContainer();
            FMAPR.hideValidateOptionsContainer();
            FMAPR.hideValidationResultsTable();
        } else {
            $selectedExport.removeClass( 'selected' );
            $parentDiv.addClass( 'selected' );
            FMAPR.showUploadOptionsContainer();
        }
        
        FMAPR.clearValidationMessage();
        FMAPR.fileToValidate = null;
        FMAPR.clearFileSelectionForm();

        if ( $selectedExport.length > 0 ) {
        }
        else {
        }
    });

    $(document).on('change', '#yes3-fmapr-export-upload-file', function(){
        FMAPR.selectedFile = this.files?.[0] || null;
        FMAPR.fileToValidate = FMAPR.selectedFile.name;
        FMAPR.hideUploadOptionsContainer();
        FMAPR.showValidateOptionsContainer();
    });

    $(document).on('click', '#yes3-fmapr-export-validate-button', function(){
        if (!FMAPR.selectedFile) { YES3.hello('Please select a file to upload.'); return; }

        const fd = new FormData();
        fd.append('datasheetToValidate', FMAPR.selectedFile);
        fd.append('request', 'validateExportFile');
        fd.append('redcap_csrf_token', redcap_csrf_token);
        fd.append('export_name', $('#yes3-fmapr-export-selection .selected .export_name').text());
        fd.append('export_uuid', $('#yes3-fmapr-export-selection .selected').attr('id'));


        YES3.isBusy();
        performance.mark('FMAPR.upload.start');

        FMAPR.hideValidateOptionsContainer();
        FMAPR.hideValidationResultsTable();
        FMAPR.postValidationMessage( 'Validating <span class="yes3-bold">' + FMAPR.fileToValidate + '</span>' );

        $.ajax({
            url: YES3.serviceUrl,
            method: 'POST',
            data: fd,
            contentType: false,
            processData: false,
            dataType: 'json',
            xhrFields: { withCredentials: true }, // if cross-site cookies/sessions are required
        })
        .done(FMAPR.uploadCallback)
        .fail((jq,x,e)=>console.error('upload fail', {status:jq.status, url:jq.responseURL, x,e, resp:jq.responseText}));
    });

    /*

    // file select button
    $( '#yes3-fmapr-export-upload-file' ).off('change').on('change', function(){

        // Get the file from the input
        const file = this.files && this.files[0];
        if (!file) return;

        FMAPR.formData = new FormData();

        FMAPR.formData.append('datasheetToValidate', file);

        
        // dev-only: verify file presence
        for (const [k, v] of FMAPR.formData.entries()) {
            console.log('FormData entry:', k, (v instanceof File) ? { name: v.name, size: v.size, type: v.type } : v);
        }

        FMAPR.fileToValidate = file.name;
        FMAPR.hideUploadOptionsContainer();
        FMAPR.showValidateOptionsContainer();
    });

    // validate button
    $( '#yes3-fmapr-export-validate-button' ).off('click').on('click', function(){

        if (!FMAPR.formData || FMAPR.isEmptyFormData(FMAPR.formData)) {
            YES3.hello('Please select a file to upload.');
            return;
        }

        YES3.isBusy();

        performance.mark('FMAPR.upload.start');

        FMAPR.formData.append( 'export_name', $( '#yes3-fmapr-export-selection div.selected span.export_name' ).text() );
        FMAPR.formData.append( 'export_uuid', $( '#yes3-fmapr-export-selection div.selected' ).attr('id') );
        
        FMAPR.validator_ajax( "validateExportFile", FMAPR.formData, FMAPR.uploadCallback );

        FMAPR.hideValidateOptionsContainer();

        FMAPR.hideValidationResultsTable();

        FMAPR.postValidationMessage( 'Validating <span class="yes3-bold">' + FMAPR.fileToValidate + '</span>' );
    });
    */
}

FMAPR.clearFileSelectionForm = function() {

    $('#yes3-fmapr-export-upload-form')[0].reset();
}

FMAPR.uploadCallback = function( response )
{
    console.log('FMAPR.uploadCallback', response);

    FMAPR.clearFileSelectionForm();

    YES3.notBusy();

    FMAPR.hideValidateOptionsContainer();

    performance.mark('FMAPR.upload.end');

    performance.measure('FMAPR.upload', 'FMAPR.upload.start', 'FMAPR.upload.end');

    const timeToUpload = (performance.getEntriesByName('FMAPR.upload')[0].duration/1000).toFixed(4);

    const errorcount = response.data.counts.errors || 0;

    let msg = `Export file uploaded and processed in ${timeToUpload} seconds. ${errorcount} discrepancies found.`;

    if ( response && response.message ) {
        msg += ` ${response.message}`;
    }

    FMAPR.postMessage( msg );
    
    FMAPR.postValidationMessage( 'Finished validating <span class="yes3-bold">' + FMAPR.fileToValidate + '</span>' );

    FMAPR.buildValidationResultsTable( response.data.error_report );

}

FMAPR.buildValidationResultsTable = function( validationResults )
{
    const $resultsTableBody = $( '#yes3-fmapr-export-validate-results-body' );
    $resultsTableBody.empty();

    if ( validationResults && validationResults.length > 0 ) {
        validationResults.forEach( function( result ) {
            const $row = $( '<tr>' );
            $row.append( $( '<td>' ).addClass('yes3-text-center').text( result.row ) );
            $row.append( $( '<td>' ).addClass('breakwrap').text( result.error_type ) );
            $row.append( $( '<td>' ).addClass('breakwrap').text( result.record ) );
            $row.append( $( '<td>' ).addClass('breakwrap').text( result.event_name ) );
            $row.append( $( '<td>' ).addClass('yes3-text-center').text( result.instance ) );
            $row.append( $( '<td>' ).addClass('breakwrap').text( result.field ) );
            $row.append( $( '<td>' ).addClass('breakwrap').text( result.message ) );
            $resultsTableBody.append( $row );
        });
        FMAPR.showValidationResultsTable();
        FMAPR.resize();
    }
}
/*
FMAPR.validator_ajax = function( request, data, callback ) {

    const isFormData = data instanceof FormData;

    // add redcap_csrf_token and request to the data object, according to object type
    if (isFormData) {
        data.append('request', request);
        data.append('redcap_csrf_token', redcap_csrf_token);
    } else {
        data = Object.assign({}, data, { request, redcap_csrf_token });
    }

    const ajaxOpts = {
        url: YES3.serviceUrl,
        method: 'POST',
        cache: false,
        data: data
    };

    if (isFormData) {
        ajaxOpts.contentType = false;
        ajaxOpts.processData = false;
    }

    // Only enforce JSON if the server guarantees valid JSON (content + headers)
    ajaxOpts.dataType = 'json';

    // If cross-origin + cookies/session needed:
    // ajaxOpts.xhrFields = { withCredentials: true };

    $.ajax(ajaxOpts)
        .done(callback)
        .fail(function (jqXHR, textStatus, errorThrown) {
            console.error('AJAX error:', { textStatus, errorThrown, status: jqXHR.status, response: jqXHR.responseText });
            alert('AJAX error: ' + (errorThrown || textStatus));
        })
    ;
}
*/
FMAPR.postValidationMessage = function( msg ){

    $('#yes3-fmapr-export-validate-message').html( msg );
}

FMAPR.clearValidationMessage = function(){

    $('#yes3-fmapr-export-validate-message').html( '' );
}

FMAPR.resize = function() {

    const $resultsSection = $('#yes3-fmapr-export-validate-results-section');
    const $resultsContainer = $('#yes3-fmapr-export-validate-results-container');
    const $table = $('table#yes3-fmapr-export-validate-results-table');
    const $footer = $('#yes3-fmapr-validate-footer');
    const newHeight = $(window).innerHeight() - $resultsSection.offset().top - $footer.outerHeight() - 20;
    let newWidth = Math.max($(window).innerWidth() - $resultsSection.offset().left - 30, 800);
    //const newTableWidth = newWidth - 60; // Adjust table width
    $resultsSection.css({'height': newHeight+'px', 'width': newWidth+'px'});
    $resultsContainer.css({'height': newHeight+'px', 'max-height': newHeight+'px'});
    //$table.width(newTableWidth);

    const tW = newWidth - 20;

    const colRowWidth = 50;
    const colInstanceWidth = 75;
    let colMessageWidth = tW / 3; // a first offer
    let remainder = tW - (colRowWidth + colInstanceWidth + colMessageWidth);

    let colErrorWidth   = Math.max(remainder * 2 / 12, 100);
    let colRecordWidth  = Math.max(remainder * 2 / 12, 100);
    let colEventWidth   = Math.max(remainder * 4 / 12, 100);
    let colFieldWidth   = Math.max(remainder * 4 / 12, 100);

    colMessageWidth = tW - (colRowWidth + colInstanceWidth + colErrorWidth + colRecordWidth + colEventWidth + colFieldWidth);

    $table.find('colgroup .col-row').css('width', colRowWidth + 'px');
    $table.find('colgroup .col-instance').css('width', colInstanceWidth + 'px');
    $table.find('colgroup .col-error').css('width', colErrorWidth + 'px');
    $table.find('colgroup .col-record').css('width', colRecordWidth + 'px');
    $table.find('colgroup .col-event').css('width', colEventWidth + 'px');
    $table.find('colgroup .col-field').css('width', colFieldWidth + 'px');
    $table.find('colgroup .col-message').css('width', colMessageWidth + 'px');
    /*
    console.log('FMAPR.resize', {
        tW,
        newWidth,
        colRowWidth,
        colInstanceWidth,
        colErrorWidth,
        colRecordWidth,
        colEventWidth,
        colFieldWidth,
        colMessageWidth
    });
    */

}

$(window).on('resize', YES3.deBounce(function() {

    FMAPR.resize();
}));

$( function(){

    YES3.contentLoaded = false;

    YES3.displayActionIcons();

    FMAPR.loadSpecifications(); 
})