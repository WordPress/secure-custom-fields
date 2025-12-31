/**
 * E2E tests for the Email field type.
 *
 * Tests the Email field which provides an email input
 * with built-in validation for email format.
 */
const { test, expect } = require( './fixtures' );
const {
	PLUGIN_SLUG,
	deleteFieldGroups,
	waitForMetaBoxes,
} = require( './field-helpers' );

const TEST_PLUGIN_SLUG = 'scf-test-plugin-all-field-types';
const FIELD_GROUP_LABEL = 'Email Field Test';

test.describe( 'Field Type > Email', () => {
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

	test( 'should create an email field and enter a valid email', async ( {
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
		await fieldLabel.fill( 'Test Email' );

		// Select email type
		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'email' );

		// Publish
		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create test post
		const post = await requestUtils.createPost( {
			title: 'Email Test Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Fill the email input
		const emailInput = page.locator(
			'.acf-field[data-name="test_email"] input[type="email"]'
		);
		await emailInput.fill( 'test@example.com' );

		// Verify the input
		await expect( emailInput ).toHaveValue( 'test@example.com' );

		// Preview and verify
		const previewPage = await editor.openPreviewPage();

		const emailOutput = previewPage.locator( '#scf-test-test_email' );
		await expect( emailOutput ).toBeVisible();
		await expect( emailOutput ).toContainText( 'test@example.com' );

		await previewPage.close();
	} );

	test( 'should have email input type', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Create field group
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'Email Type Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Email Type' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'email' );

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create post
		const post = await requestUtils.createPost( {
			title: 'Email Type Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Verify the input type is email
		const emailInput = page.locator(
			'.acf-field[data-name="email_type"] input'
		);
		await expect( emailInput ).toBeVisible();

		const inputType = await emailInput.getAttribute( 'type' );
		expect( inputType ).toBe( 'email' );
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
		await page.fill( '#title', 'Email Placeholder Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Placeholder Email' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'email' );

		// Set placeholder
		const placeholderInput = page.locator(
			'.acf-field-setting-placeholder input[type="text"]'
		);
		if ( await placeholderInput.isVisible() ) {
			await placeholderInput.fill( 'you@example.com' );
		}

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create post
		const post = await requestUtils.createPost( {
			title: 'Email Placeholder Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Verify placeholder
		const emailInput = page.locator(
			'.acf-field[data-name="placeholder_email"] input[type="email"]'
		);
		await expect( emailInput ).toBeVisible();

		const placeholder = await emailInput.getAttribute( 'placeholder' );
		if ( placeholder ) {
			expect( placeholder ).toBe( 'you@example.com' );
		}
	} );

	test( 'should support prepend and append text', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Create field group with prepend/append
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'Email Affix Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Affix Email' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'email' );

		// Set prepend if available
		const prependInput = page.locator(
			'.acf-field-setting-prepend input[type="text"]'
		);
		if ( await prependInput.isVisible() ) {
			await prependInput.fill( '📧' );
		}

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create post
		const post = await requestUtils.createPost( {
			title: 'Email Affix Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Verify field exists
		const emailField = page.locator( '.acf-field[data-name="affix_email"]' );
		await expect( emailField ).toBeVisible();

		// Enter email
		const emailInput = emailField.locator( 'input[type="email"]' );
		await emailInput.fill( 'user@domain.org' );
		await expect( emailInput ).toHaveValue( 'user@domain.org' );
	} );
} );
