const { readFileSync, writeFileSync } = require( 'node:fs' );
const { dirname, join } = require( 'node:path' );
const assert = require( 'node:assert/strict' );
const root = dirname( require.resolve( '@wordpress/env/package.json' ) );

// wp-env still uses the CommonJS default export removed by simple-git 4.
for ( const file of [ 'lib/download-sources.js', 'lib/runtime/docker/download-wp-phpunit.js' ] ) {
	const target = join( root, file );
	const source = readFileSync( target, 'utf8' );
	const before = "const SimpleGit = require( 'simple-git' );";
	const after = "const SimpleGit = require( 'simple-git' ).simpleGit;";
	assert( source.includes( before ) || source.includes( after ), `Check the simple-git import in ${ file }` );
	writeFileSync( target, source.replace( before, after ) );
}

// js-yaml 4 made load safe by default and removed safeLoad.
const target = join( root, 'lib/runtime/docker/missing-images.js' );
const source = readFileSync( target, 'utf8' );
assert( source.includes( 'yaml.safeLoad(' ) || source.includes( 'yaml.load(' ), 'Check the js-yaml call in missing-images.js' );
writeFileSync( target, source.replace( 'yaml.safeLoad(', 'yaml.load(' ) );
