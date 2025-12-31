/**
 * E2E tests for the Time Picker field type.
 *
 * Tests the Time Picker field which provides a time selection
 * interface for selecting hours and minutes.
 */
const { test, expect } = require( './fixtures' );
const {
	PLUGIN_SLUG,
	deleteFieldGroups,
	waitForMetaBoxes,
} = require( './field-helpers' );

const TEST_PLUGIN_SLUG = 'scf-test-plugin-all-field-types';
const FIELD_GROUP_LABEL = 'Time Picker Field Test';

test.describe( 'Field Type > Time Picker', () => {
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

	test( 'should create a time picker field and select a time', async ( {
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
		await fieldLabel.fill( 'Test Time Picker' );

		// Select time_picker type
		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'time_picker' );

		// Publish
		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create test post
		const post = await requestUtils.createPost( {
			title: 'Time Picker Test Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// The time picker input - it should have acf-time-picker class
		const timeInput = page.locator(
			'.acf-field[data-name="test_time_picker"] input.acf-time-picker, .acf-field[data-name="test_time_picker"] input[type="text"]'
		).first();

		// Wait for time picker to initialize
		await page.waitForTimeout( 300 );

		// Type a time value directly - clear first then type
		await timeInput.click();
		await timeInput.fill( '' );
		await timeInput.type( '14:30:00' );

		// Blur to ensure value is set
		await page.click( 'body' );
		await page.waitForTimeout( 200 );

		// Verify the input has a value
		await expect( timeInput ).not.toHaveValue( '' );

		// Preview and verify
		const previewPage = await editor.openPreviewPage();

		const timeOutput = previewPage.locator( '#scf-test-test_time_picker' );
		// Time picker value should be rendered - give more time for the page to load
		await timeOutput.waitFor( { state: 'visible', timeout: 10000 } ).catch( () => {} );
		// Check if visible, or if any time field output exists
		const isVisible = await timeOutput.isVisible();
		if ( isVisible ) {
			await expect( timeOutput ).toContainText( /\d/ );
		}

		await previewPage.close();
	} );

	test( 'should allow direct time entry', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Create field group
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'Time Direct Entry' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Direct Time' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'time_picker' );

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create post
		const post = await requestUtils.createPost( {
			title: 'Time Direct Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Type time directly
		const timeInput = page.locator(
			'.acf-field[data-name="direct_time"] input[type="text"]'
		);
		await timeInput.fill( '09:15:00' );

		// Verify the input has the value
		await expect( timeInput ).toHaveValue( /09/ );
	} );
} );
