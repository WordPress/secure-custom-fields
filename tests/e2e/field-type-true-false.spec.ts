/**
 * E2E tests for the True/False field type.
 *
 * Tests the True/False field which provides a toggle/checkbox
 * for boolean values.
 */
const { test, expect } = require( './fixtures' );
const {
	PLUGIN_SLUG,
	deleteFieldGroups,
	waitForMetaBoxes,
	toggleFieldSetting,
} = require( './field-helpers' );

const TEST_PLUGIN_SLUG = 'scf-test-plugin-all-field-types';
const FIELD_GROUP_LABEL = 'True False Field Test';

test.describe( 'Field Type > True/False', () => {
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

	test( 'should create a true/false field and toggle it on', async ( {
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
		await fieldLabel.fill( 'Test True False' );

		// Select true_false type
		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'true_false' );

		// Publish
		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create test post
		const post = await requestUtils.createPost( {
			title: 'True False Test Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Toggle on
		const toggleInput = page.locator(
			'.acf-field[data-name="test_true_false"] input[type="checkbox"]'
		);
		await toggleInput.check();

		// Verify it's checked
		await expect( toggleInput ).toBeChecked();

		// Preview and verify
		const previewPage = await editor.openPreviewPage();

		const trueFalseOutput = previewPage.locator(
			'#scf-test-test_true_false'
		);
		await expect( trueFalseOutput ).toBeVisible();
		await expect( trueFalseOutput ).toContainText( 'Yes' );

		await previewPage.close();
	} );

	test( 'should default to false (unchecked)', async ( {
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
		await page.fill( '#title', 'True False Default Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Default False' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'true_false' );

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create test post (don't change the toggle)
		const post = await requestUtils.createPost( {
			title: 'True False Default Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Verify it's unchecked by default
		const toggleInput = page.locator(
			'.acf-field[data-name="default_false"] input[type="checkbox"]'
		);
		await expect( toggleInput ).not.toBeChecked();

		// Preview and verify
		const previewPage = await editor.openPreviewPage();

		const trueFalseOutput = previewPage.locator( '#scf-test-default_false' );
		await expect( trueFalseOutput ).toBeVisible();
		await expect( trueFalseOutput ).toContainText( 'No' );

		await previewPage.close();
	} );

	test( 'should support custom on/off text', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Create field group with custom text
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'True False Custom Text' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Custom Toggle' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'true_false' );

		// Set custom on/off text
		const onTextInput = page.locator(
			'.acf-field-setting-ui_on_text input[type="text"]'
		);
		if ( await onTextInput.isVisible() ) {
			await onTextInput.fill( 'Enabled' );
		}

		const offTextInput = page.locator(
			'.acf-field-setting-ui_off_text input[type="text"]'
		);
		if ( await offTextInput.isVisible() ) {
			await offTextInput.fill( 'Disabled' );
		}

		// Enable stylized UI
		await toggleFieldSetting( page, '.acf-field-setting-ui', true );

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create post
		const post = await requestUtils.createPost( {
			title: 'Custom Toggle Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Verify the field exists
		const toggleField = page.locator(
			'.acf-field[data-name="custom_toggle"]'
		);
		await expect( toggleField ).toBeVisible();
	} );
} );
