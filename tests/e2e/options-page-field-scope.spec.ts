/**
 * E2E regression coverage for Options Page field scoping.
 *
 * Two local Options Pages intentionally share `options` storage but require
 * different capabilities. An editor may save the low-capability page without
 * being allowed to submit fields assigned only to the protected page.
 */
const { test, expect } = require( './fixtures' );
const { PLUGIN_SLUG } = require( './field-helpers' );

const TEST_PLUGIN_SLUG = 'scf-test-options-page-field-scope';
const FIXTURE_REST_PATH = '/scf-test/v1/options-page-field-scope';

const LOW_PAGE_SLUG = 'scf-test-options-page-field-scope-low';
const PROTECTED_PAGE_SLUG = 'scf-test-options-page-field-scope-protected';
const LOW_FIELD_KEY = 'field_scf_test_options_page_field_scope_low';
const PROTECTED_FIELD_KEY = 'field_scf_test_options_page_field_scope_protected';

const EDITOR_PASSWORD = 'scfE2Epassword!42';

/**
 * Resets the fixture without accepting arbitrary option names or values.
 *
 * @param {Object} requestUtils Playwright WordPress request utilities.
 * @param {string} mode         Fixed reset mode.
 * @return {Promise<Object>} Fixture state.
 */
async function resetFixture( requestUtils, mode ) {
	return requestUtils.rest( {
		method: 'POST',
		path: FIXTURE_REST_PATH,
		data: { mode },
	} );
}

/**
 * Reads the fixture's raw values and hidden field references.
 *
 * @param {Object} requestUtils Playwright WordPress request utilities.
 * @return {Promise<Object>} Fixture state.
 */
async function getFixtureState( requestUtils ) {
	return requestUtils.rest( {
		path: FIXTURE_REST_PATH,
	} );
}

/**
 * Logs a dedicated browser context in as the editor fixture.
 *
 * @param {import('@playwright/test').Browser} browser  Playwright browser.
 * @param {string}                             baseUrl  WordPress origin.
 * @param {string}                             username Editor username.
 * @return {Promise<{context: import('@playwright/test').BrowserContext, page: import('@playwright/test').Page}>} Logged-in context and page.
 */
async function createEditorContext( browser, baseUrl, username ) {
	const context = await browser.newContext();
	const page = await context.newPage();

	const loginResponse = await context.request.post(
		`${ baseUrl }/wp-login.php`,
		{
			form: {
				log: username,
				pwd: EDITOR_PASSWORD,
			},
		}
	);
	expect( loginResponse.ok() ).toBe( true );

	const adminResponse = await page.goto( `${ baseUrl }/wp-admin/` );
	expect( adminResponse.status() ).toBe( 200 );
	await expect( page.locator( '#wpadminbar' ) ).toBeVisible();

	return { context, page };
}

/**
 * Fetches the low page's nonce and submits explicit field roots to that URL.
 *
 * @param {import('@playwright/test').BrowserContext} context Playwright browser context.
 * @param {import('@playwright/test').Page}           page    Editor page.
 * @param {string}                                    baseUrl WordPress origin.
 * @param {Object<string, string>}                    fields  Top-level ACF field values.
 */
async function submitLowOptionsPage( context, page, baseUrl, fields ) {
	const lowPageUrl = `${ baseUrl }/wp-admin/admin.php?page=${ LOW_PAGE_SLUG }`;

	const pageResponse = await page.goto( lowPageUrl );
	expect( pageResponse.status() ).toBe( 200 );
	await expect(
		page.locator( `.acf-field[data-key="${ LOW_FIELD_KEY }"]` )
	).toBeVisible();
	await expect(
		page.locator( `.acf-field[data-key="${ PROTECTED_FIELD_KEY }"]` )
	).toHaveCount( 0 );

	const nonce = await page.locator( 'input[name="_acf_nonce"]' ).inputValue();
	const form = {
		_acf_nonce: nonce,
		_acf_screen: 'options',
		_acf_post_id: 'options',
		_acf_validation: '1',
		_acf_changed: '1',
		publish: 'Update',
	};

	for ( const [ fieldKey, value ] of Object.entries( fields ) ) {
		form[ `acf[${ fieldKey }]` ] = value;
	}

	const response = await context.request.post( lowPageUrl, { form } );
	expect( response.ok() ).toBe( true );
	expect( new URL( response.url() ).searchParams.get( 'message' ) ).toBe(
		'1'
	);
}

test.describe( 'Options Page field scope', () => {
	let editorUserId;
	let editorUsername;

	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( PLUGIN_SLUG );
		await requestUtils.activatePlugin( TEST_PLUGIN_SLUG );
		await resetFixture( requestUtils, 'cleanup' );

		const uniqueSuffix = Date.now().toString();
		editorUsername = `scf_options_scope_${ uniqueSuffix }`;
		const editor = await requestUtils.createUser( {
			username: editorUsername,
			email: `${ editorUsername }@example.com`,
			password: EDITOR_PASSWORD,
			roles: [ 'editor' ],
		} );
		editorUserId = editor.id;
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await resetFixture( requestUtils, 'cleanup' ).catch( () => {} );

		if ( editorUserId ) {
			await requestUtils
				.rest( {
					method: 'DELETE',
					path: `/wp/v2/users/${ editorUserId }`,
					params: {
						force: true,
						reassign: 1,
					},
				} )
				.catch( () => {} );
		}

		await requestUtils
			.deactivatePlugin( TEST_PLUGIN_SLUG )
			.catch( () => {} );
		await requestUtils.deactivatePlugin( PLUGIN_SLUG ).catch( () => {} );
	} );

	test( 'denies an editor access to the protected page', async ( {
		browser,
	}, testInfo ) => {
		const baseUrl = new URL( testInfo.project.use.baseURL ).origin;
		const { context } = await createEditorContext(
			browser,
			baseUrl,
			editorUsername
		);

		try {
			const response = await context.request.get(
				`${ baseUrl }/wp-admin/admin.php?page=${ PROTECTED_PAGE_SLUG }`
			);
			expect( response.status() ).toBe( 403 );
		} finally {
			await context.close();
		}
	} );

	test( 'saves an allowed root and discards a protected root', async ( {
		browser,
		requestUtils,
	}, testInfo ) => {
		const baseUrl = new URL( testInfo.project.use.baseURL ).origin;
		await resetFixture( requestUtils, 'baseline' );
		const { context, page } = await createEditorContext(
			browser,
			baseUrl,
			editorUsername
		);

		try {
			await submitLowOptionsPage( context, page, baseUrl, {
				[ LOW_FIELD_KEY ]: 'low-updated',
				[ PROTECTED_FIELD_KEY ]: 'attempted-overwrite',
			} );
		} finally {
			await context.close();
		}

		const state = await getFixtureState( requestUtils );
		expect( state.low ).toEqual( {
			value_exists: true,
			value: 'low-updated',
			reference_exists: true,
			reference: LOW_FIELD_KEY,
		} );
		expect( state.protected ).toEqual( {
			value_exists: true,
			value: 'protected-original',
			reference_exists: true,
			reference: PROTECTED_FIELD_KEY,
		} );
	} );

	test( 'removes a foreign required root before validation', async ( {
		browser,
		requestUtils,
	}, testInfo ) => {
		const baseUrl = new URL( testInfo.project.use.baseURL ).origin;
		await resetFixture( requestUtils, 'baseline' );
		const { context, page } = await createEditorContext(
			browser,
			baseUrl,
			editorUsername
		);

		try {
			await submitLowOptionsPage( context, page, baseUrl, {
				[ LOW_FIELD_KEY ]: 'low-updated',
				[ PROTECTED_FIELD_KEY ]: '',
			} );
		} finally {
			await context.close();
		}

		const state = await getFixtureState( requestUtils );
		expect( state.low.value ).toBe( 'low-updated' );
		expect( state.protected ).toEqual( {
			value_exists: true,
			value: 'protected-original',
			reference_exists: true,
			reference: PROTECTED_FIELD_KEY,
		} );
	} );

	test( 'does not create a value or reference from a foreign-only root', async ( {
		browser,
		requestUtils,
	}, testInfo ) => {
		const baseUrl = new URL( testInfo.project.use.baseURL ).origin;
		await resetFixture( requestUtils, 'without-protected' );
		const { context, page } = await createEditorContext(
			browser,
			baseUrl,
			editorUsername
		);

		try {
			await submitLowOptionsPage( context, page, baseUrl, {
				[ PROTECTED_FIELD_KEY ]: 'attempted-overwrite',
			} );
		} finally {
			await context.close();
		}

		const state = await getFixtureState( requestUtils );
		expect( state.low ).toEqual( {
			value_exists: true,
			value: 'low-original',
			reference_exists: true,
			reference: LOW_FIELD_KEY,
		} );
		expect( state.protected ).toEqual( {
			value_exists: false,
			value: null,
			reference_exists: false,
			reference: null,
		} );
	} );

	test( 'allows an administrator to save the protected page', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		await resetFixture( requestUtils, 'baseline' );
		await admin.visitAdminPage(
			'admin.php',
			`page=${ PROTECTED_PAGE_SLUG }`
		);

		const protectedField = page.locator(
			`.acf-field[data-key="${ PROTECTED_FIELD_KEY }"] input[type="text"]`
		);
		await expect( protectedField ).toHaveValue( 'protected-original' );
		await protectedField.fill( 'protected-authorized' );
		await Promise.all( [
			page.waitForURL( /[?&]message=1/ ),
			page.locator( '#publish' ).click(),
		] );

		const state = await getFixtureState( requestUtils );
		expect( state.protected ).toEqual( {
			value_exists: true,
			value: 'protected-authorized',
			reference_exists: true,
			reference: PROTECTED_FIELD_KEY,
		} );
	} );
} );
