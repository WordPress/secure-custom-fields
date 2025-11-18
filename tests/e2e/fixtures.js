/**
 * Extend WordPress test with Istanbul coverage collection
 *
 * Collects coverage from babel-plugin-istanbul instrumented code
 * and exposes it via window.__coverage__ during test execution.
 *
 * Ideally, this should be part of wordpress e2e test utils.
 */

const {
	test: wpTest,
	expect,
} = require( '@wordpress/e2e-test-utils-playwright' );
const fs = require( 'fs' );
const path = require( 'path' );

const coverageDir = process.env.COVERAGE_DIR
	? path.resolve( process.env.COVERAGE_DIR )
	: path.join( process.cwd(), '.nyc_output' );

// Helper to save coverage data
async function saveCoverage( coverage ) {
	await fs.promises.mkdir( coverageDir, { recursive: true } );
	const coverageFile = path.join(
		coverageDir,
		`coverage-${ Date.now() }-${ Math.random()
			.toString( 36 )
			.slice( 2 ) }.json`
	);
	await fs.promises.writeFile(
		coverageFile,
		JSON.stringify( coverage, null, 2 )
	);
}

// Extend WordPress test with Istanbul coverage collection
const test = wpTest.extend( {
	page: async ( { page }, use ) => {
		await use( page );

		if ( process.env.COVERAGE_ENABLED ) {
			// Collect coverage after test completes
			const coverage = await page.evaluate( () => window.__coverage__ );
			if ( coverage ) {
				await saveCoverage( coverage );
			}
		}
	},
} );

module.exports = { test, expect };
