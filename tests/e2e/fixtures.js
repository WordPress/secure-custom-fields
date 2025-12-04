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

// Extend WordPress test with Istanbul coverage collection and WP version compatibility
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
						const isWP62 = await page.evaluate(
							() => document.body.classList.contains( 'branch-6-2' )
						);
						if ( isWP62 ) {
							return page;
						}
						return page.frameLocator( 'iframe[name="editor-canvas"]' );
					} )();
				},
			},
			// WP 6.2 has "Preview" button, WP 6.3+ has "View" button.
			openPreviewPage: {
				value: async () => {
					const isWP62 = await page.evaluate(
						() => document.body.classList.contains( 'branch-6-2' )
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
						page.click( 'role=menuitem[name="Preview in new tab"i]' ),
					] );

					return previewPage;
				},
			},
		} );

		await use( extendedEditor );
	},
} );

module.exports = { test, expect };
