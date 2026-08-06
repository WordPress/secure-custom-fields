/**
 * E2E tests for UI Options Page admin lifecycle.
 *
 * Covers creating a UI options page via the SCF admin, verifying it is
 * registered in the admin menu, attaching a field group via the
 * "Options Page" location rule, saving and persisting a field value on
 * the options page, and finally deleting the options page and verifying
 * it is removed from the admin menu.
 */
const { test, expect } = require( './fixtures' );
const { PLUGIN_SLUG, purgeScfInternalPosts } = require( './field-helpers' );

const UTILITIES_PLUGIN_SLUG = 'scf-test-utilities';

const OPTIONS_PAGE_TITLE = 'E2E Site Settings';
const OPTIONS_PAGE_SLUG = 'e2e-site-settings';
const FIELD_GROUP_TITLE = 'E2E Options Page Fields';
const FIELD_LABEL = 'Site Tagline';
// Field name is auto-generated from the label by the field group editor.
const FIELD_NAME = 'site_tagline';
const FIELD_VALUE = 'Hello from the E2E options page';

const DEFAULT_TIMEOUT = 5000;

/**
 * Locate the test field's text input on the options page screen.
 *
 * @param {import('@playwright/test').Page} page Playwright page object.
 * @return {import('@playwright/test').Locator} The field input locator.
 */
function optionsFieldInput( page ) {
	return page.locator(
		`.acf-field[data-name="${ FIELD_NAME }"] input[type="text"]`
	);
}

/**
 * Locate the options page link in the admin menu.
 *
 * @param {import('@playwright/test').Page} page Playwright page object.
 * @return {import('@playwright/test').Locator} The admin menu link locator.
 */
function optionsPageMenuLink( page ) {
	return page.locator( '#adminmenu' ).getByRole( 'link', {
		name: OPTIONS_PAGE_TITLE,
		exact: true,
	} );
}

test.describe( 'Options Page Admin', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( PLUGIN_SLUG );
		await requestUtils.activatePlugin( UTILITIES_PLUGIN_SLUG );
		// Wipe any leftover SCF internal posts so menu/location assertions
		// are not affected by state from previous runs.
		await purgeScfInternalPosts( requestUtils, [
			'acf-field-group',
			'acf-field',
			'acf-ui-options-page',
		] );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await purgeScfInternalPosts( requestUtils, [
			'acf-field-group',
			'acf-field',
			'acf-ui-options-page',
		] );
		await requestUtils.deactivatePlugin( UTILITIES_PLUGIN_SLUG );
		await requestUtils.deactivatePlugin( PLUGIN_SLUG );
	} );

	test( 'should create, use, and delete a UI options page', async ( {
		page,
		admin,
	} ) => {
		await test.step( 'Create a UI options page via the SCF admin', async () => {
			await admin.visitAdminPage(
				'post-new.php',
				'post_type=acf-ui-options-page'
			);

			// Fill slug BEFORE page title to prevent auto-generation by JS.
			await page.fill(
				'#acf_ui_options_page-menu_slug',
				OPTIONS_PAGE_SLUG
			);
			await page.fill(
				'#acf_ui_options_page-page_title',
				OPTIONS_PAGE_TITLE
			);

			await page.click( 'button.acf-btn.acf-publish[type="submit"]' );
			const createdNotice = page.locator( '.updated.notice' );
			await expect( createdNotice ).toBeVisible( {
				timeout: DEFAULT_TIMEOUT,
			} );
			await expect( createdNotice ).toContainText(
				'options page created'
			);
		} );

		await test.step( 'Verify the options page is registered in the admin menu', async () => {
			await admin.visitAdminPage( 'index.php', '' );
			await expect( optionsPageMenuLink( page ) ).toBeVisible( {
				timeout: DEFAULT_TIMEOUT,
			} );
		} );

		await test.step( 'Create a field group located on the options page', async () => {
			await admin.visitAdminPage(
				'edit.php',
				'post_type=acf-field-group'
			);
			await page.locator( 'a.acf-btn:has-text("Add New")' ).click();

			await page.waitForSelector( '#title' );
			await page.fill( '#title', FIELD_GROUP_TITLE );

			const fieldLabelInput = page.locator(
				'input[id^="acf_fields-field_"][id$="-label"]'
			);
			await fieldLabelInput.fill( FIELD_LABEL );

			// Location rule: Options Page == our new options page.
			const paramSelect = page.locator(
				'select[id^="acf_field_group-location-group_0-rule_0-param"]'
			);
			await paramSelect.scrollIntoViewIfNeeded();
			await paramSelect.selectOption( 'options_page' );

			const valueSelect = page.locator(
				'select[id^="acf_field_group-location-group_0-rule_0-value"]'
			);
			await expect(
				valueSelect.locator(
					`option:has-text("${ OPTIONS_PAGE_TITLE }")`
				)
			).toBeAttached( { timeout: DEFAULT_TIMEOUT } );
			await valueSelect.selectOption( OPTIONS_PAGE_SLUG );

			await page.click( 'button.acf-btn.acf-publish[type="submit"]' );
			const publishedNotice = page.locator( '.updated.notice' );
			await expect( publishedNotice ).toBeVisible( {
				timeout: DEFAULT_TIMEOUT,
			} );
			await expect( publishedNotice ).toContainText(
				'Field group published'
			);
		} );

		await test.step( 'Fill the field on the options page and save', async () => {
			await admin.visitAdminPage(
				'admin.php',
				`page=${ OPTIONS_PAGE_SLUG }`
			);
			await expect(
				page.locator( '.acf-settings-wrap h1' )
			).toContainText( OPTIONS_PAGE_TITLE );

			const optionsField = optionsFieldInput( page );
			await expect( optionsField ).toBeVisible( {
				timeout: DEFAULT_TIMEOUT,
			} );
			await optionsField.fill( FIELD_VALUE );

			await page.click( '#publish' );

			const savedNotice = page.locator(
				'.acf-admin-notice.notice-success'
			);
			await expect( savedNotice ).toBeVisible( {
				timeout: DEFAULT_TIMEOUT,
			} );
			await expect( savedNotice ).toContainText( 'Options Updated' );
		} );

		await test.step( 'Reload the options page and verify persistence', async () => {
			await admin.visitAdminPage(
				'admin.php',
				`page=${ OPTIONS_PAGE_SLUG }`
			);
			await expect( optionsFieldInput( page ) ).toHaveValue(
				FIELD_VALUE
			);
		} );

		await test.step( 'Delete the options page', async () => {
			await admin.visitAdminPage(
				'edit.php',
				'post_type=acf-ui-options-page'
			);
			const optionsPageRow = page.locator( '#the-list tr', {
				hasText: OPTIONS_PAGE_TITLE,
			} );
			await expect( optionsPageRow ).toBeVisible( {
				timeout: DEFAULT_TIMEOUT,
			} );
			await optionsPageRow
				.locator( '.check-column input[type="checkbox"]' )
				.check();
			await page.selectOption( '#bulk-action-selector-bottom', 'trash' );
			await page.click( '#doaction2' );

			const trashNotice = page.locator( '.updated.notice' );
			await expect( trashNotice ).toBeVisible( {
				timeout: DEFAULT_TIMEOUT,
			} );
			await expect( trashNotice ).toContainText( 'moved to the Trash' );
		} );

		await test.step( 'Verify the options page no longer appears in the menu', async () => {
			await admin.visitAdminPage( 'index.php', '' );
			await expect( optionsPageMenuLink( page ) ).not.toBeVisible();
		} );
	} );
} );
