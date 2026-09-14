/** Assinafy media selection and signer repeater. Strings are supplied by SendScreen. */
( function () {
	'use strict';

	var data = window.assinafySendScreen || {};
	var i18n = data.i18n || {};

	var list = document.querySelector( '#assinafy-signers tbody' );

	if ( ! list ) {
		return;
	}

	var addButton = document.getElementById( 'assinafy-add-signer' );
	var chooseButton = document.getElementById( 'assinafy-choose-file' );
	var mediaFrame;

	if ( chooseButton ) {
		chooseButton.addEventListener( 'click', function () {
			if ( ! mediaFrame ) {
				mediaFrame = window.wp.media( {
					title: i18n.chooseFile,
					button: { text: i18n.useFile },
					library: { type: 'application/pdf' },
					multiple: false
				} );
				mediaFrame.on( 'select', function () {
					var file = mediaFrame.state().get( 'selection' ).first().toJSON();
					if ( 'application/pdf' !== file.mime ) {
						window.alert( i18n.notPdf );
						return;
					}
					document.getElementById( 'assinafy-attachment-id' ).value = file.id;
					document.getElementById( 'assinafy-attachment-name' ).value = file.filename;
				} );
			}
			mediaFrame.open();
		} );
	}

	/**
	 * Every signer row, in document order.
	 *
	 * @return {Array} Row elements.
	 */
	function rows() {
		return Array.prototype.slice.call( list.querySelectorAll( '.assinafy-signer-row' ) );
	}

	/**
	 * Every named control inside one row.
	 *
	 * @param {Element} row Signer row.
	 * @return {Array} Form controls.
	 */
	function controls( row ) {
		return Array.prototype.slice.call( row.querySelectorAll( '[data-assinafy-field]' ) );
	}

	/**
	 * The row's message node, created on first use so the markup stays minimal.
	 *
	 * @param {Element} row Signer row.
	 * @return {Element} Message node.
	 */
	function errorNode( row ) {
		var node = row.querySelector( '.assinafy-signer-error' );

		if ( ! node ) {
			node = document.createElement( 'p' );
			node.className = 'assinafy-signer-error';
			node.setAttribute( 'aria-live', 'polite' );
			row.querySelector( 'td:nth-child(2)' ).appendChild( node );
		}

		return node;
	}

	/**
	 * Return one row's controls to the values the server rendered.
	 *
	 * @param {Element} row Signer row.
	 */
	function reset( row ) {
		controls( row ).forEach( function ( control ) {
			control.value = control.defaultValue;
			control.setCustomValidity( '' );
			control.classList.remove( 'assinafy-field-invalid' );
		} );

		errorNode( row ).textContent = '';
	}

	/**
	 * Renumber every row by its position in the DOM.
	 *
	 * This runs after every add and every remove. Skipping it after a remove leaves
	 * a gap or a duplicate index, and PHP keeps only the last value for a repeated
	 * name, so a signer disappears between the browser and the API.
	 */
	function reindex() {
		rows().forEach( function ( row, index ) {
			controls( row ).forEach( function ( control ) {
				var field = control.getAttribute( 'data-assinafy-field' );
				var previous = control.id;
				var label;

				control.name = 'assinafy_signers[' + index + '][' + field + ']';
				control.id = 'assinafy-signer-' + index + '-' + field;

				if ( previous && previous !== control.id ) {
					label = row.querySelector( 'label[for="' + previous + '"]' );

					if ( label ) {
						label.htmlFor = control.id;
					}
				}
			} );
		} );
	}

	/**
	 * Validate every address and flag duplicates.
	 *
	 * Validity comes from the browser's own e-mail parser rather than a regular
	 * expression. Messages are attached with setCustomValidity so the form cannot
	 * be submitted while one stands, and mirrored into the row as visible text.
	 */
	function validate() {
		var seen = Object.create( null );

		rows().forEach( function ( row ) {
			var email = row.querySelector( '[data-assinafy-field="email"]' );
			var message = '';
			var value = '';

			if ( email ) {
				email.setCustomValidity( '' );
				value = email.value.trim().toLowerCase();

				if ( '' !== value && ! email.checkValidity() ) {
					message = i18n.invalidEmail || '';
				} else if ( '' !== value && seen[ value ] ) {
					message = i18n.duplicateEmail || '';
				}

				if ( '' !== value ) {
					seen[ value ] = true;
				}

				email.setCustomValidity( message );
				email.classList.toggle( 'assinafy-field-invalid', '' !== message );
			}

			errorNode( row ).textContent = message;

		} );
	}

	/**
	 * Append a row cloned from the first one, so it carries whatever markup the
	 * server rendered without this file having to know it.
	 */
	function addRow() {
		var source = rows()[ 0 ];
		var row;
		var first;

		if ( ! source ) {
			return;
		}

		row = source.cloneNode( true );
		reset( row );
		list.appendChild( row );
		reindex();
		validate();

		first = row.querySelector( '[data-assinafy-field]' );

		if ( first ) {
			first.focus();
		}
	}

	/**
	 * Remove a row, or empty it when it is the last one left.
	 *
	 * @param {Element} row Signer row.
	 */
	function removeRow( row ) {
		if ( 1 < rows().length ) {
			row.parentNode.removeChild( row );
		} else {
			reset( row );
		}

		reindex();
		validate();
	}

	list.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.assinafy-remove-signer' );

		if ( ! button ) {
			return;
		}

		event.preventDefault();
		removeRow( button.closest( '.assinafy-signer-row' ) );
	} );

	list.addEventListener( 'input', validate );
	list.addEventListener( 'change', validate );

	if ( addButton ) {
		addButton.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			addRow();
		} );
	}

	reindex();
	validate();
}() );
