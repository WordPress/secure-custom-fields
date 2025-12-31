/**
 * E2E tests for the Date Picker field type.
 *
 * Tests the Date Picker field which provides a date selection
 * interface using jQuery UI datepicker.
 */
const { test, expect } = require( './fixtures' );
const {
	PLUGIN_SLUG,
	deleteFieldGroups,
	waitForMetaBoxes,
} = require( './field-helpers' );

const TEST_PLUGIN_SLUG = 'scf-test-plugin-all-field-types';
const FIELD_GROUP_LABEL = 'Date Picker Field Test';

test.describe( 'Field Type > Date Picker', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( PLUGIN_SLUG );
		await requestUtils.activatePlugin( TEST_PLUGIN_SLUG );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deactivatePlugin( TEST_PLUGIN_SLUG );
		await requestUtils.deactivatePlugin( PLUGIN_SLUG );
		await requestUtils.deleteAllPosts();
	} );

	test.beforeEach( async ( { page, admin } ) => {
		await deleteFieldGroups( page, admin );
	} );

	test( 'should create a date picker field and select a date', async ( {
		page,
		admin,
		editor,
		requestUtils,
	} ) => {
		// Create field group
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', FIELD_GROUP_LABEL );

		// Set field label
		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Test Date Picker' );

		// Select date_picker type
		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'date_picker' );

		// Publish
		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create test post
		const post = await requestUtils.createPost( {
			title: 'Date Picker Test Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Click on the date input to open picker
		const dateInput = page.locator(
			'.acf-field[data-name="test_date_picker"] input.hasDatepicker'
		);
		await dateInput.click();

		// Wait for datepicker to appear
		await page.waitForSelector( '#ui-datepicker-div', { state: 'visible' } );

		// Select a day (click on day 15)
		const day15 = page.locator(
			'#ui-datepicker-div td:not(.ui-datepicker-other-month) a:has-text("15")'
		);
		await day15.click();

		// Verify the input has a value
		await expect( dateInput ).not.toHaveValue( '' );

		// Preview and verify
		const previewPage = await editor.openPreviewPage();

		const dateOutput = previewPage.locator( '#scf-test-test_date_picker' );
		await expect( dateOutput ).toBeVisible();
		// Date should contain "15"
		await expect( dateOutput ).toContainText( '15' );

		await previewPage.close();
	} );

	test( 'should allow typing a date directly', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Create field group
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'Date Picker Type Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Typed Date' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'date_picker' );

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create post
		const post = await requestUtils.createPost( {
			title: 'Date Picker Type Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Type date directly
		const dateInput = page.locator(
			'.acf-field[data-name="typed_date"] input.hasDatepicker'
		);
		await dateInput.fill( '20251225' );

		// Verify the input has the value
		await expect( dateInput ).toHaveValue( /2025/ );
	} );
} );
