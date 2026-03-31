/**
 * Internal dependencies
 */
const { test, expect, wpVersionAtLeast } = require( './fixtures' );

test.describe( 'Command Palette', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( 'secure-custom-fields' );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deactivatePlugin( 'secure-custom-fields' );
	} );

	test.describe( 'opened in wp-admin', () => {
		test.beforeEach( async ( { admin } ) => {
			await admin.visitAdminPage( 'index.php' );
		} );

		test( 'should register SCF create commands', async ( { page } ) => {
			test.skip(
				! ( await wpVersionAtLeast( page, 6, 9 ) ),
				'Command Palette in wp-admin requires WordPress 6.9+'
			);

			// Open the command palette via keyboard shortcut.
			await page.keyboard.press( 'ControlOrMeta+k' );

			const input = page.getByRole( 'combobox', {
				name: 'Search commands and settings',
			} );
			await expect( input ).toBeVisible();

			// Search for a create command — these are always registered by SCF.
			await input.fill( 'Create New Field Group' );
			await expect(
				page.getByRole( 'option', { name: /Create New Field Group/ } )
			).toBeVisible();
		} );

		test( 'should register SCF view commands without duplicates', async ( {
			page
		} ) => {
			test.skip(
				! ( await wpVersionAtLeast( page, 6, 9 ) ),
				'Command Palette in wp-admin requires WordPress 6.9+'
			);

			await page.keyboard.press( 'ControlOrMeta+k' );

			const input = page.getByRole( 'combobox', {
				name: 'Search commands and settings',
			} );

			// Search for a view command that exists in both WP's auto-registered
			// admin menu commands and SCF's view commands list.
			await input.fill( 'Field Groups' );

			const options = page.getByRole( 'option', {
				name: /Field Groups/,
			} );

			// There should be exactly one match, not two (no duplicate).
			await expect( options ).toHaveCount( 1 );
		} );

		test( 'should navigate to field groups via command palette', async ( {
			page,
		} ) => {
			test.skip(
				! ( await wpVersionAtLeast( page, 6, 9 ) ),
				'Command Palette in wp-admin requires WordPress 6.9+'
			);

			await page.keyboard.press( 'ControlOrMeta+k' );

			const input = page.getByRole( 'combobox', {
				name: 'Search commands and settings',
			} );

			await input.fill( 'Field Groups' );
			await page
				.getByRole( 'option', { name: /Field Groups/ } )
				.click();

			await expect( page ).toHaveURL(
				/edit\.php\?post_type=acf-field-group/
			);
		} );
	} );
} );
