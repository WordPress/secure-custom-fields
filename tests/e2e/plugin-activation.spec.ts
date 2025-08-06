/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const PLUGIN_SLUG = 'secure-custom-fields';
const PLUGIN_PATH = `${ PLUGIN_SLUG }/${ PLUGIN_SLUG }.php`;

test.describe( 'Plugin Activation', () => {
	test.beforeEach( async ( { page, requestUtils } ) => {
		// Login to WordPress admin
		await requestUtils.activatePlugin( PLUGIN_SLUG );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deactivatePlugin( PLUGIN_SLUG );
	} );

	test( 'should be able to access plugin settings', async ( {
		page,
		admin,
	} ) => {
		// Navigate to plugins page
		await admin.visitAdminPage( 'plugins.php' );

		// Check if our plugin is active (exclude update notification rows)
		const pluginRow = page.locator( `tr[data-plugin="${ PLUGIN_PATH }"]:not(.plugin-update-tr)` );
		await expect( pluginRow ).toBeVisible();

		// Check if plugin is activated
		await expect( pluginRow.locator( '.deactivate a' ) ).toBeVisible();
	} );

	test( 'should have correct plugin name in admin', async ( {
		page,
		admin,
	} ) => {
		// Navigate to plugins page
		await admin.visitAdminPage( 'plugins.php' );

		// Check plugin name (exclude update notification rows)
		const pluginName = page.locator(
			`tr[data-plugin="${ PLUGIN_PATH }"]:not(.plugin-update-tr) .plugin-title strong`
		);
		await expect( pluginName ).toHaveText( 'Secure Custom Fields' );
	} );
} );
