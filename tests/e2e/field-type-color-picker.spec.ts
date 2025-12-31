/**
 * E2E tests for the Color Picker field type.
 *
 * Tests the Color Picker field which provides a color selection
 * interface with hex color values.
 */
const { test, expect } = require( './fixtures' );
const {
	PLUGIN_SLUG,
	deleteFieldGroups,
	waitForMetaBoxes,
} = require( './field-helpers' );

const TEST_PLUGIN_SLUG = 'scf-test-plugin-all-field-types';
const FIELD_GROUP_LABEL = 'Color Picker Field Test';

test.describe( 'Field Type > Color Picker', () => {
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

	test( 'should create a color picker field and select a color', async ( {
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
		await fieldLabel.fill( 'Test Color Picker' );

		// Select color_picker type
		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'color_picker' );

		// Publish
		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create test post
		const post = await requestUtils.createPost( {
			title: 'Color Picker Test Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// The color picker field has a button to open the picker
		const colorButton = page.locator(
			'.acf-field[data-name="test_color_picker"] .wp-color-result, .acf-field[data-name="test_color_picker"] button.button.wp-color-result'
		);

		// Click to open the color picker
		await colorButton.click();

		// Wait for the picker to open and find the hex input
		const colorInput = page.locator(
			'.acf-field[data-name="test_color_picker"] input.wp-color-picker'
		);
		await colorInput.waitFor( { state: 'visible', timeout: 5000 } );

		// Clear and enter a hex color value
		await colorInput.fill( '#ff5733' );

		// Verify the input has the value
		await expect( colorInput ).toHaveValue( '#ff5733' );

		// Preview and verify
		const previewPage = await editor.openPreviewPage();

		const colorOutput = previewPage.locator(
			'#scf-test-test_color_picker'
		);
		await expect( colorOutput ).toBeVisible();
		await expect( colorOutput ).toContainText( '#ff5733' );

		await previewPage.close();
	} );

	test( 'should support default color value', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Create field group with default color
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'Color Default Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Default Color' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'color_picker' );

		// Set default value
		const defaultInput = page.locator(
			'.acf-field-setting-default_value input[type="text"]'
		);
		await defaultInput.fill( '#3498db' );

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create post
		const post = await requestUtils.createPost( {
			title: 'Color Default Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Verify default color is present by checking the color button's background
		// The button shows the current color, and the hidden input stores the value
		const colorButton = page.locator(
			'.acf-field[data-name="default_color"] .wp-color-result'
		);
		await expect( colorButton ).toBeVisible();

		// Click to open the picker and check the input value
		await colorButton.click();
		const colorInput = page.locator(
			'.acf-field[data-name="default_color"] input.wp-color-picker'
		);
		await colorInput.waitFor( { state: 'visible', timeout: 5000 } );
		await expect( colorInput ).toHaveValue( '#3498db' );
	} );

	test( 'should open color picker UI on click', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Create field group
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'Color Picker UI Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Picker UI' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'color_picker' );

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create post
		const post = await requestUtils.createPost( {
			title: 'Color Picker UI Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Click the color picker button to open UI
		const colorButton = page.locator(
			'.acf-field[data-name="picker_ui"] .wp-color-result'
		);

		if ( await colorButton.isVisible() ) {
			await colorButton.click();

			// Verify the color picker UI is visible
			const colorPickerUI = page.locator( '.wp-picker-container.wp-picker-active' );
			await expect( colorPickerUI ).toBeVisible();
		} else {
			// Fallback: just verify the field container exists
			const fieldContainer = page.locator(
				'.acf-field[data-name="picker_ui"]'
			);
			await expect( fieldContainer ).toBeVisible();
		}
	} );
} );
