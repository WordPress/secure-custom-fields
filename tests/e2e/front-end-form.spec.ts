/**
 * E2E tests for front-end forms rendered via acf_form().
 *
 * Covers the opt-in Interactivity API view bundle
 * (`frontend_interactivity_form` setting):
 *
 * a) Flag OFF (default): the classic jQuery stack renders, validates, and
 *    persists submissions.
 * b) Flag ON with only simple fields: the page loads without jQuery, the
 *    `scf-form-view` script module validates required fields, and valid
 *    submissions persist.
 * c) Flag ON with a complex field (date_picker): the form automatically
 *    falls back to the classic jQuery stack and still works.
 *
 * Front-end pages are visited in a fresh, logged-out browser context so the
 * "no jQuery" assertion is not polluted by admin-bar assets.
 */
const { test, expect } = require( './fixtures' );

const PLUGIN_SLUG = 'secure-custom-fields';
const TEST_PLUGIN_SLUG = 'scf-test-front-form';
const DEFAULT_TIMEOUT = 10000;

/**
 * Toggles the frontend_interactivity_form flag via the test plugin's REST route.
 *
 * @param {Object}  requestUtils The request utils fixture.
 * @param {boolean} enabled      Whether the flag should be enabled.
 */
async function setInteractivityFlag( requestUtils, enabled ) {
	await requestUtils.rest( {
		path: '/scf-test/v1/frontend-interactivity',
		method: 'POST',
		data: { enabled },
	} );
}

/**
 * Returns the front-end URL of a fixture page created by the test plugin.
 *
 * @param {Object} requestUtils The request utils fixture.
 * @param {string} slug         The page slug.
 * @return {Promise<string>} The page link.
 */
async function getPageLink( requestUtils, slug ) {
	const pages = await requestUtils.rest( {
		path: '/wp/v2/pages',
		params: { slug, per_page: 1 },
	} );
	expect( pages.length ).toBe( 1 );
	return pages[ 0 ].link;
}

/**
 * Opens a URL in a fresh logged-out context.
 *
 * @param {Object} browser The browser fixture.
 * @param {string} url     The URL to open.
 * @return {Promise<{context: Object, page: Object}>} The new context and page.
 */
async function openLoggedOut( browser, url ) {
	const context = await browser.newContext();
	const page = await context.newPage();
	await page.goto( url );
	return { context, page };
}

test.describe( 'Front-end form (acf_form)', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( PLUGIN_SLUG );
		await requestUtils.activatePlugin( TEST_PLUGIN_SLUG );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await setInteractivityFlag( requestUtils, false );
		await requestUtils.deactivatePlugin( TEST_PLUGIN_SLUG );
		await requestUtils.deactivatePlugin( PLUGIN_SLUG );
	} );

	test.describe( 'classic stack (flag off)', () => {
		test.beforeAll( async ( { requestUtils } ) => {
			await setInteractivityFlag( requestUtils, false );
		} );

		test( 'renders with jQuery, blocks empty required field, persists valid submission', async ( {
			browser,
			requestUtils,
		} ) => {
			const link = await getPageLink( requestUtils, 'scf-front-form' );
			const { context, page } = await openLoggedOut( browser, link );

			try {
				const form = page.locator( 'form#scf-front-form' );
				await expect( form ).toBeVisible();

				// Classic stack loads jQuery and acf-input.
				expect(
					await page.locator( 'script[src*="jquery"]' ).count()
				).toBeGreaterThan( 0 );
				expect(
					await page.locator( 'script[src*="acf-input"]' ).count()
				).toBeGreaterThan( 0 );

				// Wait for the classic JS to be ready.
				await page.waitForFunction( () => !! window.acf );

				const requiredInput = form.locator(
					'.acf-field[data-name="ff_required_text"] input[type="text"]'
				);
				await requiredInput.fill( '' );
				await form.locator( '[type="submit"]' ).click();

				// Required validation blocks the submit and renders an error.
				await expect(
					page.locator( '.acf-field.acf-error' ).first()
				).toBeVisible( { timeout: DEFAULT_TIMEOUT } );
				await expect( page ).not.toHaveURL( /updated=true/ );

				// Fix the field and submit for real.
				const value = `Classic ${ Date.now() }`;
				await requiredInput.fill( value );
				await form.locator( '[type="submit"]' ).click();
				await page.waitForURL( /updated=true/, {
					timeout: DEFAULT_TIMEOUT,
				} );
				await expect( page.locator( '#message' ) ).toContainText(
					'Post updated'
				);

				// Reload the form page: the saved value is loaded back in.
				await page.goto( link );
				await expect(
					page.locator(
						'.acf-field[data-name="ff_required_text"] input[type="text"]'
					)
				).toHaveValue( value );
			} finally {
				await context.close();
			}
		} );
	} );

	test.describe( 'Interactivity API view bundle (flag on)', () => {
		test.beforeAll( async ( { requestUtils } ) => {
			await setInteractivityFlag( requestUtils, true );
		} );

		test( 'renders without jQuery using the scf-form-view module', async ( {
			browser,
			requestUtils,
		} ) => {
			const link = await getPageLink( requestUtils, 'scf-front-form' );
			const { context, page } = await openLoggedOut( browser, link );

			try {
				await expect(
					page.locator( 'form#scf-front-form' )
				).toBeVisible();

				// No jQuery and no classic SCF scripts anywhere on the page.
				expect(
					await page.locator( 'script[src*="jquery"]' ).count()
				).toBe( 0 );
				expect(
					await page.locator( 'script[src*="acf-input"]' ).count()
				).toBe( 0 );

				// The view bundle is loaded as a script module with directives.
				expect(
					await page
						.locator(
							'script[type="module"][src*="scf-form-view"]'
						)
						.count()
				).toBeGreaterThan( 0 );
				await expect(
					page.locator( 'form[data-wp-interactive="scf/form"]' )
				).toBeVisible();
			} finally {
				await context.close();
			}
		} );

		test( 'blocks empty required field and clears the error on input', async ( {
			browser,
			requestUtils,
		} ) => {
			const link = await getPageLink( requestUtils, 'scf-front-form' );
			const { context, page } = await openLoggedOut( browser, link );

			try {
				const form = page.locator( 'form#scf-front-form' );
				await expect( form ).toBeVisible();
				await page.waitForLoadState( 'networkidle' );

				const requiredInput = form.locator(
					'.acf-field[data-name="ff_required_text"] input[type="text"]'
				);
				await requiredInput.fill( '' );
				await form.locator( '[type="submit"]' ).click();

				// Field-level error in classic markup, no navigation.
				const erroredField = page.locator( '.acf-field.acf-error' );
				await expect( erroredField.first() ).toBeVisible( {
					timeout: DEFAULT_TIMEOUT,
				} );
				await expect(
					page.locator( '.acf-field .acf-notice.acf-error-message' )
				).toBeVisible();
				await expect( page ).not.toHaveURL( /updated=true/ );

				// Typing into the field clears its error state.
				await requiredInput.fill( 'fixing it' );
				await expect( erroredField ).toHaveCount( 0 );
			} finally {
				await context.close();
			}
		} );

		test( 'persists a valid submission', async ( {
			browser,
			requestUtils,
		} ) => {
			const link = await getPageLink( requestUtils, 'scf-front-form' );
			const { context, page } = await openLoggedOut( browser, link );

			try {
				const form = page.locator( 'form#scf-front-form' );
				await expect( form ).toBeVisible();
				await page.waitForLoadState( 'networkidle' );

				const value = `Slim ${ Date.now() }`;
				await form
					.locator(
						'.acf-field[data-name="ff_required_text"] input[type="text"]'
					)
					.fill( value );
				await form
					.locator(
						'.acf-field[data-name="ff_email"] input[type="email"]'
					)
					.fill( 'tester@example.com' );

				await form.locator( '[type="submit"]' ).click();
				await page.waitForURL( /updated=true/, {
					timeout: DEFAULT_TIMEOUT,
				} );
				await expect( page.locator( '#message' ) ).toContainText(
					'Post updated'
				);

				// Reload the form page: the saved values are loaded back in.
				await page.goto( link );
				await expect(
					page.locator(
						'.acf-field[data-name="ff_required_text"] input[type="text"]'
					)
				).toHaveValue( value );
				await expect(
					page.locator(
						'.acf-field[data-name="ff_email"] input[type="email"]'
					)
				).toHaveValue( 'tester@example.com' );
			} finally {
				await context.close();
			}
		} );

		test( 'falls back to the classic stack for complex fields', async ( {
			browser,
			requestUtils,
		} ) => {
			const link = await getPageLink(
				requestUtils,
				'scf-front-form-complex'
			);
			const { context, page } = await openLoggedOut( browser, link );

			try {
				const form = page.locator( 'form#scf-front-form-complex' );
				await expect( form ).toBeVisible();

				// The complex field forces the classic jQuery stack.
				expect(
					await page.locator( 'script[src*="jquery"]' ).count()
				).toBeGreaterThan( 0 );
				expect(
					await page
						.locator(
							'script[type="module"][src*="scf-form-view"]'
						)
						.count()
				).toBe( 0 );
				await expect(
					page.locator( 'form[data-wp-interactive]' )
				).toHaveCount( 0 );

				// The classic stack still drives the form: the date picker
				// field renders and a valid submission persists.
				await page.waitForFunction( () => !! window.acf );
				await expect(
					form.locator( '.acf-field[data-name="ff_date"]' )
				).toBeVisible();

				const value = `Complex ${ Date.now() }`;
				await form
					.locator(
						'.acf-field[data-name="ff_complex_text"] input[type="text"]'
					)
					.fill( value );
				await form.locator( '[type="submit"]' ).click();
				await page.waitForURL( /updated=true/, {
					timeout: DEFAULT_TIMEOUT,
				} );

				await page.goto( link );
				await expect(
					page.locator(
						'.acf-field[data-name="ff_complex_text"] input[type="text"]'
					)
				).toHaveValue( value );
			} finally {
				await context.close();
			}
		} );
	} );
} );
