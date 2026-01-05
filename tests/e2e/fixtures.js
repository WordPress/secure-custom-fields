/**
 * Extend WordPress test with Istanbul coverage collection
 *
 * Collects coverage from babel-plugin-istanbul instrumented code
 * and exposes it via window.__coverage__ during test execution.
 *
 * Also supports PHP code coverage collection when PHP_COVERAGE_ENABLED is set.
 * PHP coverage is collected by sending X-PHP-Coverage headers to the server,
 * which triggers the php-coverage-collector.php mu-plugin.
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

/**
 * Generate a coverage ID for PHP coverage tracking.
 * Format: testfile-testname-timestamp
 *
 * @param {import('@playwright/test').TestInfo} testInfo - Playwright test info.
 * @return {string} Coverage ID.
 */
function generateCoverageId( testInfo ) {
	const testFile = path.basename( testInfo.file, '.spec.ts' );
	const testName = testInfo.title
		.replace( /[^a-zA-Z0-9]/g, '_' )
		.slice( 0, 50 );
	return `${ testFile }-${ testName }-${ Date.now() }`;
}

// Extend WordPress test with Istanbul coverage collection and WP version compatibility
const test = wpTest.extend( {
	page: async ( { page }, use, testInfo ) => {
		// Set up PHP coverage headers if enabled.
		if ( process.env.PHP_COVERAGE_ENABLED ) {
			const coverageId = generateCoverageId( testInfo );

			// Add extra HTTP headers to all requests for PHP coverage.
			await page.setExtraHTTPHeaders( {
				'X-PHP-Coverage': coverageId,
			} );
		}

		await use( page );

		// Collect JS coverage after test completes.
		if ( process.env.COVERAGE_ENABLED ) {
			const coverage = await page.evaluate( () => window.__coverage__ );
			if ( coverage ) {
				await saveCoverage( coverage );
			}
		}
	},

	// Extend requestUtils to add PHP coverage headers to REST API calls.
	requestUtils: async ( { requestUtils }, use, testInfo ) => {
		if ( process.env.PHP_COVERAGE_ENABLED ) {
			const coverageId = generateCoverageId( testInfo );
			const originalRest = requestUtils.rest.bind( requestUtils );

			// Wrap the rest method to add coverage headers.
			requestUtils.rest = async ( options ) => {
				const headers = options.headers || {};
				return originalRest( {
					...options,
					headers: {
						...headers,
						'X-PHP-Coverage': coverageId,
					},
				} );
			};
		}

		await use( requestUtils );
	},

	// Override editor fixture to provide version-compatible methods.
	// WP 6.3+ has "View" button and iframe canvas, WP 6.2 has "Preview" button and no iframe.
	editor: async ( { editor, page, context }, use ) => {
		// Create extended editor object with version-compatible overrides
		const extendedEditor = Object.create( editor, {
			// WP 6.2 doesn't have iframe canvas. Return a Promise so
			// `await editor.canvas` works correctly for both versions.
			canvas: {
				get() {
					return ( async () => {
						const isWP62 = await page.evaluate( () =>
							document.body.classList.contains( 'branch-6-2' )
						);
						if ( isWP62 ) {
							return page;
						}
						return page.frameLocator(
							'iframe[name="editor-canvas"]'
						);
					} )();
				},
			},
			// WP 6.2 has "Preview" button, WP 6.3+ has "View" button.
			openPreviewPage: {
				value: async () => {
					const isWP62 = await page.evaluate( () =>
						document.body.classList.contains( 'branch-6-2' )
					);

					if ( ! isWP62 ) {
						return editor.openPreviewPage();
					}

					const editorTopBar = page.locator(
						'role=region[name="Editor top bar"i]'
					);
					await editorTopBar
						.locator( 'role=button[name="Preview"i]' )
						.click();

					const [ previewPage ] = await Promise.all( [
						context.waitForEvent( 'page' ),
						page.click(
							'role=menuitem[name="Preview in new tab"i]'
						),
					] );

					return previewPage;
				},
			},
		} );

		await use( extendedEditor );
	},
} );

/**
 * Check if WordPress version is at least the specified version.
 * Must be called after navigating to an admin page.
 *
 * @param {import('@playwright/test').Page} page  Playwright page object.
 * @param {number}                          major Major version number.
 * @param {number}                          minor Minor version number.
 * @return {Promise<boolean>} True if WP version >= specified version.
 */
async function wpVersionAtLeast( page, major, minor ) {
	return page.evaluate(
		( [ maj, min ] ) => {
			const branchClass = [ ...document.body.classList ].find( ( c ) =>
				c.startsWith( 'branch-' )
			);
			if ( ! branchClass ) {
				return true;
			}
			const match = branchClass.match( /branch-(\d+)-(\d+)/ );
			if ( ! match ) {
				return true;
			}
			const [ , wpMajor, wpMinor ] = match.map( Number );
			return wpMajor > maj || ( wpMajor === maj && wpMinor >= min );
		},
		[ major, minor ]
	);
}

/**
 * Check if WordPress version is below the specified version.
 * Must be called after navigating to an admin page.
 *
 * @param {import('@playwright/test').Page} page  Playwright page object.
 * @param {number}                          major Major version number.
 * @param {number}                          minor Minor version number.
 * @return {Promise<boolean>} True if WP version < specified version.
 */
async function wpVersionBelow( page, major, minor ) {
	return ! ( await wpVersionAtLeast( page, major, minor ) );
}

module.exports = { test, expect, wpVersionAtLeast, wpVersionBelow };
