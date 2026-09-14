/* global jQuery, wp */
( function ( $ ) {
	'use strict';
	$( document ).on( 'click', '#assinafy-wpforms-pdf', function () {
		const picker = wp.media( { multiple: false, library: { type: 'application/pdf' } } );
		picker.on( 'select', function () {
			const attachment = picker.state().get( 'selection' ).first().toJSON();
			$( '[name="settings[assinafy][attachment_id]"]' ).val( attachment.id ).trigger( 'change' );
		} );
		picker.open();
	} );
} )( jQuery );
