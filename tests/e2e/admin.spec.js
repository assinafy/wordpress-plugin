const { test, expect } = require( '@playwright/test' );
const path = require( 'node:path' );
const { execFileSync } = require( 'node:child_process' );

function wp( ...args ) {
	return execFileSync( process.execPath, [ 'node_modules/@wordpress/env/bin/wp-env', 'run', 'cli', '--env-cwd=wp-content/plugins/assinafy', 'wp', ...args ], { encoding: 'utf8' } );
}

// Uses only wp-env's local site. Form submission is intercepted before any API send.
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
