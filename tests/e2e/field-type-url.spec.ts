/**
 * E2E tests for the URL field type.
 *
 * Tests the URL field which provides a URL input
 * with built-in validation for URL format.
 */
const { test, expect } = require( './fixtures' );
const {
	PLUGIN_SLUG,
	deleteFieldGroups,
	waitForMetaBoxes,
} = require( './field-helpers' );

const TEST_PLUGIN_SLUG = 'scf-test-plugin-all-field-types';
const FIELD_GROUP_LABEL = 'URL Field Test';

test.describe( 'Field Type > URL', () => {
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

	test( 'should create a url field and enter a valid URL', async ( {
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
		await fieldLabel.fill( 'Test URL' );

		// Select url type
		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'url' );

		// Publish
		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create test post
		const post = await requestUtils.createPost( {
			title: 'URL Test Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Fill the URL input
		const urlInput = page.locator(
			'.acf-field[data-name="test_url"] input[type="url"]'
		);
		await urlInput.fill( 'https://wordpress.org' );

		// Verify the input
		await expect( urlInput ).toHaveValue( 'https://wordpress.org' );

		// Preview and verify
		const previewPage = await editor.openPreviewPage();

		const urlOutput = previewPage.locator( '#scf-test-test_url' );
		await expect( urlOutput ).toBeVisible();
		await expect( urlOutput ).toContainText( 'wordpress.org' );

		await previewPage.close();
	} );

	test( 'should have url input type', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Create field group
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'URL Type Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'URL Type' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'url' );

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create post
		const post = await requestUtils.createPost( {
			title: 'URL Type Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Verify the input type is url
		const urlInput = page.locator( '.acf-field[data-name="url_type"] input' );
		await expect( urlInput ).toBeVisible();

		const inputType = await urlInput.getAttribute( 'type' );
		expect( inputType ).toBe( 'url' );
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
		await page.fill( '#title', 'URL Placeholder Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Placeholder URL' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'url' );

		// Set placeholder
		const placeholderInput = page.locator(
			'.acf-field-setting-placeholder input[type="text"]'
		);
		if ( await placeholderInput.isVisible() ) {
			await placeholderInput.fill( 'https://example.com' );
		}

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create post
		const post = await requestUtils.createPost( {
			title: 'URL Placeholder Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Verify placeholder
		const urlInput = page.locator(
			'.acf-field[data-name="placeholder_url"] input[type="url"]'
		);
		await expect( urlInput ).toBeVisible();

		const placeholder = await urlInput.getAttribute( 'placeholder' );
		if ( placeholder ) {
			expect( placeholder ).toBe( 'https://example.com' );
		}
	} );

	test( 'should accept various URL formats', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Create field group
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'URL Format Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Format URL' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'url' );

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create post
		const post = await requestUtils.createPost( {
			title: 'URL Format Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		const urlInput = page.locator(
			'.acf-field[data-name="format_url"] input[type="url"]'
		);

		// Test HTTPS URL
		await urlInput.fill( 'https://www.example.com/path?query=value' );
		await expect( urlInput ).toHaveValue(
			'https://www.example.com/path?query=value'
		);

		// Clear and test HTTP URL
		await urlInput.fill( '' );
		await urlInput.fill( 'http://example.org' );
		await expect( urlInput ).toHaveValue( 'http://example.org' );
	} );
} );
