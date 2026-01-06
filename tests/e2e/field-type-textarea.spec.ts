/**
 * E2E tests for the Textarea field type.
 *
 * Tests the Textarea field which provides a multi-line text input
 * for longer text content.
 */
const { test, expect } = require( './fixtures' );
const {
	PLUGIN_SLUG,
	deleteFieldGroups,
	waitForMetaBoxes,
} = require( './field-helpers' );

const TEST_PLUGIN_SLUG = 'scf-test-plugin-all-field-types';
const FIELD_GROUP_LABEL = 'Textarea Field Test';

test.describe( 'Field Type > Textarea', () => {
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

	test( 'should create a textarea field and enter multi-line text', async ( {
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
		await fieldLabel.fill( 'Test Textarea' );

		// Select textarea type
		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'textarea' );

		// Publish
		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create test post
		const post = await requestUtils.createPost( {
			title: 'Textarea Test Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Fill the textarea with multi-line content
		const textarea = page.locator(
			'.acf-field[data-name="test_textarea"] textarea'
		);
		await textarea.fill( 'Line 1\nLine 2\nLine 3' );

		// Verify the input
		await expect( textarea ).toHaveValue( 'Line 1\nLine 2\nLine 3' );

		// Preview and verify
		const previewPage = await editor.openPreviewPage();

		const textareaOutput = previewPage.locator( '#scf-test-test_textarea' );
		await expect( textareaOutput ).toBeVisible();
		await expect( textareaOutput ).toContainText( 'Line 1' );

		await previewPage.close();
	} );

	test( 'should respect rows setting', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Create field group with specific rows
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'Textarea Rows Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Large Textarea' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'textarea' );

		// Set rows if setting is available
		const rowsInput = page.locator(
			'.acf-field-setting-rows input[type="number"]'
		);
		if ( await rowsInput.isVisible() ) {
			await rowsInput.fill( '10' );
		}

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create post
		const post = await requestUtils.createPost( {
			title: 'Textarea Rows Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Verify the textarea exists
		const textarea = page.locator(
			'.acf-field[data-name="large_textarea"] textarea'
		);
		await expect( textarea ).toBeVisible();
	} );

	test( 'should support placeholder text', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Create field group with placeholder
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'Textarea Placeholder Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Placeholder Text' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'textarea' );

		// Set placeholder
		const placeholderInput = page.locator(
			'.acf-field-setting-placeholder input[type="text"]'
		);
		if ( await placeholderInput.isVisible() ) {
			await placeholderInput.fill( 'Enter your text here...' );
		}

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create post
		const post = await requestUtils.createPost( {
			title: 'Textarea Placeholder Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Verify textarea has placeholder
		const textarea = page.locator(
			'.acf-field[data-name="placeholder_text"] textarea'
		);
		await expect( textarea ).toBeVisible();

		const placeholder = await textarea.getAttribute( 'placeholder' );
		if ( placeholder ) {
			expect( placeholder ).toBe( 'Enter your text here...' );
		}
	} );

	test( 'should support max length', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Create field group with max length
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'Textarea MaxLength Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Limited Text' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'textarea' );

		// Set max length
		const maxLengthInput = page.locator(
			'.acf-field-setting-maxlength input[type="number"]'
		);
		if ( await maxLengthInput.isVisible() ) {
			await maxLengthInput.fill( '100' );
		}

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create post
		const post = await requestUtils.createPost( {
			title: 'Textarea MaxLength Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Verify textarea exists
		const textarea = page.locator(
			'.acf-field[data-name="limited_text"] textarea'
		);
		await expect( textarea ).toBeVisible();

		// Enter some text
		await textarea.fill( 'This is a test of the textarea field.' );
		await expect( textarea ).not.toHaveValue( '' );
	} );
} );
