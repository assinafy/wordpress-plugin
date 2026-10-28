const { test, expect } = require( '@playwright/test' );
const path = require( 'node:path' );
const { execFileSync } = require( 'node:child_process' );

function wp( ...args ) {
	return execFileSync( process.execPath, [ 'node_modules/@wordpress/env/bin/wp-env', 'run', 'cli', '--env-cwd=wp-content/plugins/assinafy', 'wp', ...args ], { encoding: 'utf8' } );
}

// Uses only wp-env's local site. Requests are intercepted before any API traffic.
test.use( { baseURL: 'http://localhost:8888' } );

test( 'PDF selection, signer validation and contiguous submitted rows', async ( { page } ) => {
	test.setTimeout( 60000 );
	await page.goto( '/wp-login.php' );
	await page.getByLabel( 'Username or Email Address' ).fill( 'admin' );
	await page.getByLabel( 'Password', { exact: true } ).fill( 'password' );
	await page.getByRole( 'button', { name: 'Log In', exact: true } ).click();
	await page.waitForURL( '**/wp-admin/**' );
	await page.goto( '/wp-admin/admin.php?page=assinafy-send' );
	await expect( page.getByRole( 'heading', { name: 'Send for signature' } ) ).toBeVisible();
	await page.getByRole( 'button', { name: 'Choose a PDF', exact: true } ).click();
	await expect( page.locator( '.media-modal' ) ).toBeVisible();
	const uploadTab = page.getByRole( 'tab', { name: 'Upload files' } );
	if ( await uploadTab.isVisible() ) {
		await uploadTab.click();
	}
	await page.locator( 'input[type=file]' ).setInputFiles( path.join( __dirname, '../fixtures/sample.pdf' ) );
	await page.getByRole( 'button', { name: 'Use this PDF', exact: true } ).click();
	await expect( page.locator( '#assinafy-attachment-id' ) ).not.toHaveValue( '' );
	const attachmentId = await page.locator( '#assinafy-attachment-id' ).inputValue();
	try {
	await expect( page.locator( '#assinafy-attachment-name' ) ).toHaveValue( /sample.*\.pdf/ );

	const rows = page.locator( '.assinafy-signer-row' );
	await rows.nth( 0 ).getByLabel( 'Signer name', { exact: true } ).fill( 'Jane Example' );
	await rows.nth( 0 ).getByLabel( 'Signer email', { exact: true } ).fill( 'jane@example.com' );
	await page.getByRole( 'button', { name: 'Add another signer' } ).click();
	await expect( rows ).toHaveCount( 2 );
	await rows.nth( 1 ).getByLabel( 'Signer name', { exact: true } ).fill( 'Sam Example' );
	await rows.nth( 1 ).getByLabel( 'Signer email', { exact: true } ).fill( 'JANE@example.com' );
	await expect( rows.nth( 1 ).locator( '.assinafy-signer-error' ) ).toHaveText( 'Each signer must have a different email address.' );
	await expect( rows.nth( 1 ).getByLabel( 'Signer email', { exact: true } ) ).toHaveJSProperty( 'validationMessage', 'Each signer must have a different email address.' );
	await rows.nth( 1 ).getByLabel( 'Signer email', { exact: true } ).fill( 'sam@example.com' );
	await page.getByRole( 'button', { name: 'Add another signer' } ).click();
	await rows.nth( 1 ).getByRole( 'button', { name: 'Remove', exact: true } ).click();
	await expect( rows ).toHaveCount( 2 );
	await rows.nth( 1 ).getByLabel( 'Signer name', { exact: true } ).fill( 'Alex Example' );
	await rows.nth( 1 ).getByLabel( 'Signer email', { exact: true } ).fill( 'alex@example.com' );
	await expect( rows.nth( 1 ).getByLabel( 'Signer email', { exact: true } ) ).toHaveAttribute( 'name', 'assinafy_signers[1][email]' );

	let submitted;
	await page.route( '**/wp-admin/admin-post.php', async route => {
		submitted = new URLSearchParams( route.request().postData() );
		await route.fulfill( { body: 'Form captured without sending.' } );
	} );
	await page.getByRole( 'button', { name: 'Send for signature', exact: true } ).click();
	await expect( page.getByText( 'Form captured without sending.' ) ).toBeVisible();
	expect( submitted.get( 'assinafy_signers[0][email]' ) ).toBe( 'jane@example.com' );
	expect( submitted.get( 'assinafy_signers[1][email]' ) ).toBe( 'alex@example.com' );
	expect( submitted.has( 'assinafy_signers[2][email]' ) ).toBe( false );
	expect( submitted.get( '_wpnonce' ) ).toBeTruthy();
	} finally {
		wp( 'post', 'delete', attachmentId, '--force' );
	}
} );


test( 'document action submits its own form and nonce inside the WordPress editor', async ( { page } ) => {
	const output = wp( 'eval', '$id = wp_insert_post(array("post_type" => "assinafy_document", "post_status" => "private", "post_title" => "E2E contract")); (new Assinafy\\WP\\Documents\\DocumentRecord())->hydrate_from_api($id, array("id" => "104618d0d63884bc446c534e5ff5", "status" => "metadata_ready", "is_closed" => false)); echo "ASSINAFY_E2E_POST=" . $id;' );
	const postId = output.match( /ASSINAFY_E2E_POST=(\d+)/ )[ 1 ];
	try {
		await page.goto( '/wp-login.php' );
		await page.getByLabel( 'Username or Email Address' ).fill( 'admin' );
		await page.getByLabel( 'Password', { exact: true } ).fill( 'password' );
		await page.getByRole( 'button', { name: 'Log In', exact: true } ).click();
		await page.waitForURL( '**/wp-admin/**' );
		await page.goto( `/wp-admin/post.php?post=${ postId }&action=edit` );
		const rename = page.getByRole( 'button', { name: 'Rename', exact: true } );
		await expect( rename ).toBeVisible();
		const ownership = await rename.evaluate( button => ( { owner: button.form.id, action: button.form.getAttribute( 'action' ), parent: button.closest( 'form' ).id } ) );
		expect( ownership.owner ).not.toBe( 'post' );
		expect( ownership.parent ).toBe( 'post' );
		expect( ownership.action ).toBe( 'http://localhost:8888/wp-admin/admin-post.php' );
		await expect( page.locator( 'form#post' ) ).toHaveCount( 1 );
		await page.getByLabel( 'Document name', { exact: true } ).fill( 'Renamed contract' );
		let submitted;
		await page.route( '**/wp-admin/admin-post.php', async route => {
			submitted = new URLSearchParams( route.request().postData() );
			await route.fulfill( { body: 'Action captured without sending.' } );
		} );
		await rename.click();
		await expect( page.getByText( 'Action captured without sending.' ) ).toBeVisible();
		expect( submitted.getAll( 'action' ) ).toEqual( [ 'assinafy_rename' ] );
		expect( submitted.getAll( 'post' ) ).toEqual( [ postId ] );
		expect( submitted.getAll( '_wpnonce' ) ).toHaveLength( 1 );
		expect( submitted.get( 'assinafy_name' ) ).toBe( 'Renamed contract' );
	} finally {
		wp( 'post', 'delete', postId, '--force' );
	}
} );

test( 'contract editor preserves separate forms, null values and renewal revision', async ( { page } ) => {
	test.setTimeout( 120000 );
	const fixtureKey = `assinafy_e2e_contract_${ process.pid }_${ Date.now() }`;
	const accountId = '104618a0000000000000000001';
	const documentId = '104618d0d63884bc446c534e5ff5';
	const contractPath = `/v1/accounts/${ accountId }/documents/${ documentId }/contract`;
	const revision = '2026-10-23T12:00:00Z';
	const contract = {
		resource: 'document_contract', document_id: documentId, status: 'active',
		counterparty_name: 'Example Company', counterparty_taxpayer_id: null,
		value: 125.5, currency: 'BRL', start_date: '2026-10-01', end_date: '2027-10-01',
		auto_renew: true, renewal_term_months: 12, notice_period_days: 30,
		term: 3, updated_at: revision, amendments: [],
	};
	// This temporary MU plugin blocks every outbound HTTP request except its local fixture.
	const fixture = `<?php
add_filter('pre_http_request', static function ($preempt, $args, $url) {
	$path = wp_parse_url($url, PHP_URL_PATH);
	$method = $args['method'];
	$expected = '${ contractPath }';
	if (wp_parse_url($url, PHP_URL_HOST) !== 'api.assinafy.com.br'
		|| !in_array($path, array($expected, $expected . '/renew'), true)
		|| !in_array($method, array('GET', 'PUT', 'POST'), true)) {
		return new WP_Error('e2e_network_blocked', 'External HTTP is disabled by the local contract fixture.');
	}
	$state = get_option('${ fixtureKey }_state');
	$status = 200;
	if ($method !== 'GET') {
		$body = json_decode($args['body'], true);
		$requests = get_option('${ fixtureKey }_requests', array());
		$requests[] = array('method' => $method, 'path' => $path, 'body' => $body);
		update_option('${ fixtureKey }_requests', $requests, false);
		if (($body['updated_at'] ?? null) !== $state['updated_at']) {
			$status = 409;
		} elseif ($method === 'PUT' && $path === $expected) {
			$state = array_replace($state, $body, array('updated_at' => '2026-10-23T12:00:01Z'));
		} elseif ($method === 'POST' && $path === $expected . '/renew' && ($body['term'] ?? null) === $state['term']) {
			$state['term']++;
			$state['end_date'] = '2028-10-01';
			$state['updated_at'] = '2026-10-23T12:00:02Z';
		} else {
			$status = 400;
		}
		update_option('${ fixtureKey }_state', $state, false);
	}
	return array('headers' => array('content-type' => 'application/json'),
		'body' => wp_json_encode(array('status' => $status, 'data' => $state)),
		'response' => array('code' => $status, 'message' => $status === 200 ? 'OK' : 'Invalid fixture mutation'), 'cookies' => array());
}, PHP_INT_MAX, 3);`;
	try {
		wp( 'eval', `
			if (defined('ASSINAFY_API_KEY') || defined('ASSINAFY_ACCOUNT_ID')) { throw new RuntimeException('Use wp-env without credential constants.'); }
			$names = array('assinafy_auth_mode', 'assinafy_account_id', 'assinafy_api_key_enc', 'assinafy_environment');
			$backup = array();
			foreach ($names as $name) { $backup[$name] = get_option($name, null); }
			add_option('${ fixtureKey }_backup', $backup, '', false);
			wp_mkdir_p(WPMU_PLUGIN_DIR);
			if (file_put_contents(WPMU_PLUGIN_DIR . '/${ fixtureKey }.php', base64_decode('${ Buffer.from( fixture ).toString( 'base64' ) }')) === false) { throw new RuntimeException('Could not install fixture.'); }
			update_option('assinafy_auth_mode', 'api_key');
			update_option('assinafy_account_id', '${ accountId }');
			update_option('assinafy_environment', 'production');
			(new Assinafy\\WP\\Credentials())->set_api_key('local-contract-fixture-key');
			update_option('${ fixtureKey }_state', json_decode(base64_decode('${ Buffer.from( JSON.stringify( contract ) ).toString( 'base64' ) }'), true), false);
		` );
		await page.goto( '/wp-login.php' );
		await page.getByLabel( 'Username or Email Address' ).fill( 'admin' );
		await page.getByLabel( 'Password', { exact: true } ).fill( 'password' );
		await page.getByRole( 'button', { name: 'Log In', exact: true } ).click();
		await page.waitForURL( '**/wp-admin/**' );
		await page.goto( `/wp-admin/admin.php?page=assinafy-contracts&document=${ documentId }` );
		const forms = page.locator( 'form:has(input[name="action"][value="assinafy_contract"])' );
		await expect( forms ).toHaveCount( 3 );
		for ( const [ index, operation ] of [ 'update', 'renew', 'terminate' ].entries() ) {
			const form = forms.nth( index );
			await expect( form.locator( 'input[name="operation"]' ) ).toHaveValue( operation );
			await expect( form.locator( 'input[name="_wpnonce"]' ) ).toHaveCount( 1 );
			await expect( form.locator( 'input[name="_wpnonce"]' ) ).not.toHaveValue( '' );
			await expect( form.locator( 'input[name="terms[updated_at]"]' ) ).toHaveValue( revision );
			await expect( form.locator( 'input[name="account"]' ) ).toHaveValue( accountId );
			await expect( form.locator( 'form' ) ).toHaveCount( 0 );
		}
		await expect( forms.nth( 1 ).locator( 'input[name="terms[term]"]' ) ).toHaveValue( '3' );
		await page.getByLabel( 'Counterparty', { exact: true } ).fill( '' );
		await page.getByLabel( 'Contract value', { exact: true } ).fill( '0' );
		await page.getByLabel( 'Notice period (days)', { exact: true } ).fill( '' );
		await page.getByLabel( 'Renew automatically after the end date' ).uncheck();
		await page.getByRole( 'button', { name: 'Save contract terms', exact: true } ).click();
		await expect( page.getByText( 'Contract saved.', { exact: true } ) ).toBeVisible();
		await expect( page.getByLabel( 'Contract value', { exact: true } ) ).toHaveValue( '0' );
		await expect( page.getByLabel( 'Counterparty', { exact: true } ) ).toHaveValue( '' );
		await expect( forms.nth( 1 ).locator( 'input[name="terms[updated_at]"]' ) ).toHaveValue( '2026-10-23T12:00:01Z' );
		await expect( page.getByLabel( 'New end date (optional)', { exact: true } ) ).toHaveValue( '' );
		await page.getByRole( 'button', { name: 'Renew contract', exact: true } ).click();
		await expect( page.getByText( 'Contract saved.', { exact: true } ) ).toBeVisible();
		await expect( forms.nth( 1 ).locator( 'input[name="terms[term]"]' ) ).toHaveValue( '4' );
		await expect( forms.nth( 1 ).locator( 'input[name="terms[updated_at]"]' ) ).toHaveValue( '2026-10-23T12:00:02Z' );
		await expect( page.getByLabel( 'End date', { exact: true } ) ).toHaveValue( '2028-10-01' );
		const output = wp( 'eval', `echo 'ASSINAFY_E2E_REQUESTS=' . wp_json_encode(get_option('${ fixtureKey }_requests'));` );
		const requests = JSON.parse( output.match( /ASSINAFY_E2E_REQUESTS=(\[[^\n]*\])/ )[ 1 ] );
		expect( requests ).toEqual( [
			{ method: 'PUT', path: contractPath, body: {
				counterparty_name: null, counterparty_taxpayer_id: null, value: 0, currency: 'BRL',
				start_date: '2026-10-01', end_date: '2027-10-01', renewal_term_months: 12,
				notice_period_days: null, auto_renew: false, updated_at: revision,
			} },
			{ method: 'POST', path: `${ contractPath }/renew`, body: { term: 3, updated_at: '2026-10-23T12:00:01Z' } },
		] );
	} finally {
		wp( 'eval', `
			$backup = get_option('${ fixtureKey }_backup', array());
			foreach ($backup as $name => $value) { if ($value === null) { delete_option($name); } else { update_option($name, $value); } }
			foreach (array('backup', 'state', 'requests') as $suffix) { delete_option('${ fixtureKey }_' . $suffix); }
			$file = WPMU_PLUGIN_DIR . '/${ fixtureKey }.php';
			if (file_exists($file)) { unlink($file); }
		` );
	}
} );
