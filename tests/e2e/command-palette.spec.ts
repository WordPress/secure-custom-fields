/**
 * Internal dependencies
 */
const { test, expect, wpVersionAtLeast } = require( './fixtures' );

const getCommandPaletteInput = ( page ) =>
	page.locator( '.commands-command-menu input[cmdk-input]' ).first();

const openCommandPalette = async ( page ) => {
	await page.evaluate( () =>
		window.wp.data.dispatch( 'core/commands' ).open()
	);
	await getCommandPaletteInput( page ).waitFor( { state: 'visible' } );
};

test.describe( 'Command Palette', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( 'secure-custom-fields' );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deactivatePlugin( 'secure-custom-fields' );
	} );

	test.beforeEach( async ( { admin, page } ) => {
		await admin.visitAdminPage( 'index.php' );

		test.skip(
			! ( await wpVersionAtLeast( page, 6, 3 ) ),
			'Command Palette requires WordPress 6.3+'
		);
	} );

	[ 'post-editor', 'wp-admin' ].forEach( ( context ) => {
		test.describe( `opened in ${ context }`, () => {
			test.beforeEach( async ( { admin, page } ) => {
				if ( context === 'post-editor' ) {
					await admin.createNewPost( {
						title: 'Command palette test',
					} );
				} else if ( context === 'wp-admin' ) {
					await admin.visitAdminPage( 'index.php' );

					test.skip(
						! ( await wpVersionAtLeast( page, 6, 9 ) ),
						'Command Palette in wp-admin requires WordPress 6.9+'
					);
				}
			} );

			test.describe( 'Admin-level commands', () => {
				test( 'should register SCF create commands', async ( {
					page,
				} ) => {
					await openCommandPalette( page );

					const input = getCommandPaletteInput( page );

					// Search for a create command — these are always registered by SCF.
					await input.fill( 'Create New Field Group' );
					await expect(
						page.getByRole( 'option', {
							name: /Create New Field Group/,
						} )
					).toBeVisible();
				} );

				test( 'should register SCF view commands without duplicates', async ( {
					page,
				} ) => {
					await openCommandPalette( page );

					const input = getCommandPaletteInput( page );

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
					await openCommandPalette( page );

					const input = getCommandPaletteInput( page );

					await input.fill( 'Field Groups' );
					await page
						.getByRole( 'option', { name: /Field Groups/ } )
						.click();

					await expect( page ).toHaveURL(
						/edit\.php\?post_type=acf-field-group/
					);
				} );
			} );

			test.describe( 'Post type-specific commands', () => {
				test.beforeAll( async ( { requestUtils } ) => {
					await requestUtils.activatePlugin(
						'scf-test-setup-post-types'
					);
				} );

				test.afterAll( async ( { requestUtils } ) => {
					await requestUtils.deactivatePlugin(
						'scf-test-setup-post-types'
					);
				} );

				test( 'should register "View All" command for SCF post type without duplicates', async ( {
					page,
				} ) => {
					await openCommandPalette( page );

					const input = getCommandPaletteInput( page );

					// The "View All" command uses the post type's `all_items` label.
					await input.fill( 'All SCF E2E Test Type Items' );

					const options = page.getByRole( 'option', {
						name: /All SCF E2E Test Type Items/,
					} );

					// Should appear exactly once (no duplicate from WP's auto-registered
					// admin menu commands).
					await expect( options ).toHaveCount( 1 );
				} );

				test( 'should register "Add New" command for SCF post type without duplicates', async ( {
					page,
				} ) => {
					await openCommandPalette( page );

					const input = getCommandPaletteInput( page );

					await input.fill( 'Add New SCF E2E Test Type Item' );

					const options = page.getByRole( 'option', {
						name: /Add New SCF E2E Test Type Item/,
					} );

					// Should appear exactly once (no duplicate).
					await expect( options ).toHaveCount( 1 );
				} );

				test( 'should register "Edit post type" command for SCF post type', async ( {
					page,
				} ) => {
					await openCommandPalette( page );

					const input = getCommandPaletteInput( page );

					// The edit command label uses the post type's `name` label.
					await input.fill( 'Edit post type' );

					await page
						.getByRole( 'option', {
							name: /Edit post type: SCF E2E Test Type/,
						} )
						.click();
					await expect(
						page.getByRole( 'textbox', { name: /Plural Label/ } )
					).toHaveValue( 'SCF E2E Test Type' );
				} );
			} );

			test.describe( 'Options page-specific commands', () => {
				test.beforeAll( async ( { requestUtils } ) => {
					await requestUtils.activatePlugin(
						'scf-test-setup-options-page'
					);
				} );

				test.afterAll( async ( { requestUtils } ) => {
					await requestUtils.deactivatePlugin(
						'scf-test-setup-options-page'
					);
				} );

				test( 'should register a command for the registered options page', async ( {
					page,
				} ) => {
					await openCommandPalette( page );

					const input = getCommandPaletteInput( page );

					await input.fill( 'SCF E2E Test Options' );

					const option = page.getByRole( 'option', {
						name: /SCF E2E Test Options/,
					} );

					await expect( option ).toHaveCount( 1 );
				} );

				test( 'should navigate to the options page via command palette', async ( {
					page,
				} ) => {
					await openCommandPalette( page );

					const input = getCommandPaletteInput( page );

					await input.fill( 'SCF E2E Test Options' );

					await page
						.getByRole( 'option', {
							name: /SCF E2E Test Options/,
						} )
						.click();

					await expect( page ).toHaveURL(
						/admin\.php\?page=scf-e2e-test-options/
					);
				} );
			} );
		} );
	} );
} );
