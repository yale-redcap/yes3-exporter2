FMAPR.renderUI = function( data ){

    //console.log( 'FMAPR.renderUI', data );

    if ( !data ) {
        return;
    }
}


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
        container.append( `<div><span class="export_name" title="click to select this export">${exp.export_name}</span><span class="export_label">${exp.export_label}</span></div>` );
    });

    FMAPR.setValidatorListeners();
}

FMAPR.setValidatorListeners = function()
{
    $( '#yes3-fmapr-export-selection span.export_name' ).off('click').on('click', function(){
        $( '#yes3-fmapr-export-selection div.selected' ).removeClass( 'selected' );
        $( this ).closest( 'div' ).addClass( 'selected' );
        console.log( 'selected', $( this ).text() );
        $( '#yes3-fmapr-export-button' ).val( `upload exported '${$( this ).text()}' datasheet` ).show();
    });
}


$( function(){

    YES3.contentLoaded = false;

    YES3.displayActionIcons();

    FMAPR.loadSpecifications(); 
})