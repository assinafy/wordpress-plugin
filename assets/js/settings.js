/**
 * Assinafy settings screen: Test connection and Register webhook.
 *
 * Both controls post to admin-ajax with the `assinafy_admin` nonce that
 * Settings::enqueue_assets() localises as `assinafySettings`. Both handlers answer
 * with wp_send_json_success/wp_send_json_error, so the payload is always
 * `{ success: bool, data: { message: string } }`.
 *
 * Every message here was produced by the server, so it is written with textContent.
 */
( function () {
	'use strict';

	var data = window.assinafySettings || {};
	var i18n = data.i18n || {};

	var entities = {
		'&amp;': '&',
		'&lt;': '<',
		'&gt;': '>',
		'&quot;': '"',
		'&#039;': '\'',
		'&#39;': '\''
	};

	/**
	 * Undo the esc_html() the AJAX handlers apply, because these strings are shown
	 * as text and never parsed as markup.
	 *
	 * @param {string} text Escaped text.
	 * @return {string} Plain text.
	 */
	function decode( text ) {
		return String( text ).replace( /&(?:amp|lt|gt|quot|#0?39);/g, function ( entity ) {
			return entities[ entity ];
		} );
	}

	/**
	 * The message carried by an admin-ajax envelope.
	 *
	 * @param {Object|number} payload Decoded JSON response.
	 * @return {string} Message, or the generic failure string.
	 */
	function messageOf( payload ) {
		// check_ajax_referer() answers a stale nonce with the bare body `-1`, and the
		// screen's nonce is baked in once at page load, so a tab left open hits this.
		if ( -1 === payload ) {
			return i18n.expired || i18n.failed || '';
		}

		var body = payload && payload.data ? payload.data : {};

		return 'string' === typeof body.message && '' !== body.message
			? decode( body.message )
			: ( i18n.failed || '' );
	}

	/**
	 * Write a status line. Text only, never markup.
	 *
	 * @param {Element} node    Result element.
	 * @param {string}  message Text to show.
	 * @param {string}  state   busy|success|error|warning.
	 */
	function say( node, message, state ) {
		node.textContent = message;
		node.className = 'assinafy-result' + ( state ? ' is-' + state : '' );
	}

	/**
	 * POST one admin-ajax action with the screen's nonce.
	 *
	 * @param {string} action Action name.
	 * @param {Object} extra  Additional body fields.
	 * @return {Promise} Resolves with the decoded envelope.
	 */
	function post( action, extra ) {
		var body = new URLSearchParams();

		body.set( 'action', action );
		body.set( 'nonce', data.nonce || '' );

		Object.keys( extra || {} ).forEach( function ( key ) {
			body.set( key, extra[ key ] );
		} );

		return fetch( data.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		} ).then( function ( response ) {
			return response.json();
		} );
	}

	/**
	 * Wire a button to an action, disabling it for the duration.
	 *
	 * @param {string}   buttonId Button element id.
	 * @param {string}   resultId Result element id.
	 * @param {Function} run      Receives ( button, result ) and returns a Promise.
	 */
	function wire( buttonId, resultId, run ) {
		var button = document.getElementById( buttonId );
		var result = document.getElementById( resultId );

		if ( ! button || ! result ) {
			return;
		}

		button.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			button.disabled = true;

			run( button, result ).catch( function () {
				say( result, i18n.failed || '', 'error' );
			} ).then( function () {
				button.disabled = false;
			} );
		} );
	}

	wire( 'assinafy-test-connection', 'assinafy-test-connection-result', function ( button, result ) {
		say( result, i18n.testing || '', 'busy' );

		return post( 'assinafy_test_connection' ).then( function ( payload ) {
			say( result, messageOf( payload ), payload && payload.success ? 'success' : 'error' );
		} );
	} );

	wire( 'assinafy-register-webhook', 'assinafy-register-webhook-result', function ( button, result ) {
		say( result, i18n.working || '', 'busy' );

		return post( 'assinafy_register_webhook' ).then( function ( payload ) {
			say( result, messageOf( payload ), payload && payload.success ? 'success' : 'error' );
		} );
	} );
}() );
