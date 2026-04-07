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

	[ 'post-editor', 'wp-admin' ].forEach( ( context ) => {
		test.describe( `opened in ${ context }`, () => {
			test.beforeEach( async ( { admin, page } ) => {
				if ( context === 'post-editor' ) {
					await admin.createNewPost( { title: 'Command palette test' } );
				} else if ( context === 'wp-admin' ) {
					await admin.visitAdminPage( 'index.php' );

					test.skip(
						! ( await wpVersionAtLeast( page, 6, 9 ) ),
						'Command Palette in wp-admin requires WordPress 6.9+'
					);
				}
			} );

			test.describe( 'Admin-level commands', () => {
				test( 'should register SCF create commands', async ( { page } ) => {
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

			test.describe( 'Post type-specific commands', () => {
				test.beforeAll( async ( { requestUtils } ) => {
					await requestUtils.activatePlugin( 'scf-test-setup-post-types' );
				} );

				test.afterAll( async ( { requestUtils } ) => {
					await requestUtils.deactivatePlugin( 'scf-test-setup-post-types' );
				} );

				test( 'should register "View All" command for SCF post type without duplicates', async ( {
					page,
				} ) => {
					await page.keyboard.press( 'ControlOrMeta+k' );

					const input = page.getByRole( 'combobox', {
						name: 'Search commands and settings',
					} );
					await expect( input ).toBeVisible();

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
					await page.keyboard.press( 'ControlOrMeta+k' );

					const input = page.getByRole( 'combobox', {
						name: 'Search commands and settings',
					} );
					await expect( input ).toBeVisible();

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
					await page.keyboard.press( 'ControlOrMeta+k' );

					const input = page.getByRole( 'combobox', {
						name: 'Search commands and settings',
					} );
					await expect( input ).toBeVisible();

					// The edit command label uses the post type's `name` label.
					await input.fill( 'Edit post type' );

					await expect(
						page.getByRole( 'option', {
							name: /Edit post type: SCF E2E Test Type/,
						} )
					).toBeVisible();
				} );

			} );
		} );
	} );
} );
