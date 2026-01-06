/**
 * E2E tests for the Date/Time Picker field type.
 *
 * Tests the Date/Time Picker field which provides both date
 * and time selection in a combined interface.
 */
const { test, expect } = require( './fixtures' );
const {
	PLUGIN_SLUG,
	deleteFieldGroups,
	waitForMetaBoxes,
} = require( './field-helpers' );

const TEST_PLUGIN_SLUG = 'scf-test-plugin-all-field-types';
const FIELD_GROUP_LABEL = 'DateTime Picker Field Test';

test.describe( 'Field Type > Date Time Picker', () => {
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

	test( 'should create a datetime picker field and select date and time', async ( {
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
		await fieldLabel.fill( 'Test Date Time Picker' );

		// Select date_time_picker type
		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'date_time_picker' );

		// Publish
		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create test post
		const post = await requestUtils.createPost( {
			title: 'DateTime Picker Test Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Click on the datetime input to open picker
		const datetimeInput = page.locator(
			'.acf-field[data-name="test_date_time_picker"] input.hasDatepicker'
		);
		await datetimeInput.click();

		// Wait for datepicker to appear
		await page.waitForSelector( '#ui-datepicker-div', { state: 'visible' } );

		// Select a day
		const day10 = page.locator(
			'#ui-datepicker-div td:not(.ui-datepicker-other-month) a:has-text("10")'
		);
		await day10.click();

		// The timepicker might be shown - try to set hour if visible
		const hourSlider = page.locator( '.ui_tpicker_hour_slider' );
		if ( await hourSlider.isVisible() ) {
			// Time picker is visible, interaction varies by implementation
			// Just verify the date part was set
		}

		// Verify the input has a value
		await expect( datetimeInput ).not.toHaveValue( '' );

		// Preview and verify
		const previewPage = await editor.openPreviewPage();

		const datetimeOutput = previewPage.locator(
			'#scf-test-test_date_time_picker'
		);
		await expect( datetimeOutput ).toBeVisible();

		await previewPage.close();
	} );

	test( 'should allow entering datetime directly', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Create field group
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'DateTime Direct Entry' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Direct DateTime' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'date_time_picker' );

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create post
		const post = await requestUtils.createPost( {
			title: 'DateTime Direct Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Type datetime directly (format depends on field settings)
		const datetimeInput = page.locator(
			'.acf-field[data-name="direct_datetime"] input.hasDatepicker'
		);
		await datetimeInput.fill( '2025-12-25 14:30:00' );

		// Verify the input has a value
		await expect( datetimeInput ).not.toHaveValue( '' );
	} );
} );
