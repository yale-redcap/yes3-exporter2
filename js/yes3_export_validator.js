FMAPR.renderUI = function( data ){

    //console.log( 'FMAPR.renderUI', data );

    if ( !data ) {
        return;
    }
}

FMAPR.formData = {};

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

FMAPR.showValidateOptionsContainer = function( uploadedFileName )
{
    if ( !uploadedFileName ) {
        return false;
    }
    
    const $validateOptionsContainer = $( '#yes3-fmapr-export-validate' );
    const $validateButton = $( '#yes3-fmapr-export-validate-button' );

    $validateButton.val( `validate: ${uploadedFileName}` );

    $validateOptionsContainer.show();
}

FMAPR.hideValidateOptionsContainer = function()
{
    const $validateOptionsContainer = $( '#yes3-fmapr-export-validate' );

    $validateOptionsContainer.hide();
}

FMAPR.setValidatorListeners = function()
{
    $( '#yes3-fmapr-export-selection span.export_name' ).off('click').on('click', function(){

        const $uploadOptionsContainer = $( '#yes3-fmapr-export-upload' );
        const $uploadButton = $( '#yes3-fmapr-export-upload-button' );

        $( '#yes3-fmapr-export-selection div.selected' ).removeClass( 'selected' );
        $( this ).closest( 'div' ).addClass( 'selected' );

        console.log( 'selected', $( this ).text() );

        FMAPR.showUploadOptionsContainer();
    });

    // file select button
    $( '#yes3-fmapr-export-upload-file' ).off('change').on('change', function(){

        // Get the file from the input
        var fileInput = $(this)[0];
        var file = fileInput.files[0];

        if ( file ) {

            FMAPR.formData = new FormData();

            FMAPR.formData.append('datasheetToValidate', file);

            // inspect the FormData object
            const obj = {};
            for (const [key, value] of FMAPR.formData.entries()) {
                obj[key] = value;
            }
            console.log('FMAPR.formData:', obj);

            FMAPR.hideUploadOptionsContainer();
            FMAPR.showValidateOptionsContainer( file.name );
        }
    });

    // validate button
    $( '#yes3-fmapr-export-validate-button' ).off('click').on('click', function(){

        if ( !FMAPR.formData || FMAPR.formData.size === 0 ) {
            YES3.hello( 'Please select a file to upload.' );
            return;
        }

        YES3.isBusy();

        performance.mark('FMAPR.upload.start');

        FMAPR.formData.append( 'export_name', $( '#yes3-fmapr-export-selection div.selected span.export_name' ).text() );
        FMAPR.formData.append( 'export_uuid', $( '#yes3-fmapr-export-selection div.selected' ).attr('id') );

        // inspect the FormData object
        const obj = {};
        for (const [key, value] of FMAPR.formData.entries()) {
            obj[key] = value;
        }
        console.log('upload: FMAPR.formData:', obj);
        
        FMAPR.validator_ajax( "validateExportFile", FMAPR.formData, FMAPR.uploadCallback );
    });
}

FMAPR.uploadCallback = function( response )
{
    console.log('FMAPR.uploadCallback', response);

    YES3.notBusy();

    FMAPR.hideValidateOptionsContainer();

    performance.mark('FMAPR.upload.end');

    performance.measure('FMAPR.upload', 'FMAPR.upload.start', 'FMAPR.upload.end');

    const timeToUpload = (performance.getEntriesByName('FMAPR.upload')[0].duration/1000).toFixed(4);

    let msg = `File uploaded successfully in ${timeToUpload} seconds.`;

    if ( response && response.message ) {
        msg += ` Message: ${response.message}`;
    }

    FMAPR.postMessage( msg );
}

FMAPR.validator_ajax = function( request, data, callback ) {

    const isFormData = data instanceof FormData;

    // add redcap_csrf_token and request to the data object, according to object type
    if ( isFormData ) {

        data.append('request', request);
        data.append('redcap_csrf_token', redcap_csrf_token);

    } else {

        data.request = request;
        data.redcap_csrf_token = redcap_csrf_token;
    }

    $.ajax({
        url: YES3.serviceUrl,
        type: "POST",
        dataType: "json",
        cache: false,
        contentType: isFormData ? false : $.ajaxSettings.contentType,
        processData: !isFormData,                       
        data: data
    })
    .done( callback )
    .fail(function(jqXHR, textStatus, errorThrown) 
    {
        console.error('AJAX error: ' + errorThrown, 'jqXHR:', jqXHR);

        alert('AJAX error (check console for more info): ' + errorThrown);
    });
}

$( function(){

    YES3.contentLoaded = false;

    YES3.displayActionIcons();

    FMAPR.loadSpecifications(); 
})