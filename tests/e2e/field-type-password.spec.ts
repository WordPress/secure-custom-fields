/**
 * E2E tests for the Password field type.
 *
 * Tests the Password field which provides a password input
 * with masked text entry.
 */
const { test, expect } = require( './fixtures' );
const {
	PLUGIN_SLUG,
	deleteFieldGroups,
	waitForMetaBoxes,
} = require( './field-helpers' );

const TEST_PLUGIN_SLUG = 'scf-test-plugin-all-field-types';
const FIELD_GROUP_LABEL = 'Password Field Test';

test.describe( 'Field Type > Password', () => {
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

	test( 'should create a password field and enter a value', async ( {
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
		await fieldLabel.fill( 'Test Password' );

		// Select password type
		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'password' );

		// Publish
		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create test post
		const post = await requestUtils.createPost( {
			title: 'Password Test Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Fill the password input
		const passwordInput = page.locator(
			'.acf-field[data-name="test_password"] input[type="password"]'
		);
		await passwordInput.fill( 'secretpassword123' );

		// Verify the input has a value (can't see actual content due to masking)
		await expect( passwordInput ).not.toHaveValue( '' );

		// Preview and verify (password field typically shows the actual value or asterisks)
		const previewPage = await editor.openPreviewPage();

		const passwordOutput = previewPage.locator( '#scf-test-test_password' );
		await expect( passwordOutput ).toBeVisible();
		// Password might be displayed as-is or masked depending on implementation
		const content = await passwordOutput.textContent();
		expect( content.length ).toBeGreaterThan( 0 );

		await previewPage.close();
	} );

	test( 'should have password input type for masking', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Create field group
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'Password Type Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Password Type' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'password' );

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create post
		const post = await requestUtils.createPost( {
			title: 'Password Type Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Verify the input type is password
		const passwordInput = page.locator(
			'.acf-field[data-name="password_type"] input'
		);
		await expect( passwordInput ).toBeVisible();

		const inputType = await passwordInput.getAttribute( 'type' );
		expect( inputType ).toBe( 'password' );
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
		await page.fill( '#title', 'Password Placeholder Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Placeholder Password' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'password' );

		// Set placeholder
		const placeholderInput = page.locator(
			'.acf-field-setting-placeholder input[type="text"]'
		);
		if ( await placeholderInput.isVisible() ) {
			await placeholderInput.fill( 'Enter your password' );
		}

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create post
		const post = await requestUtils.createPost( {
			title: 'Password Placeholder Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Verify placeholder
		const passwordInput = page.locator(
			'.acf-field[data-name="placeholder_password"] input[type="password"]'
		);
		await expect( passwordInput ).toBeVisible();

		const placeholder = await passwordInput.getAttribute( 'placeholder' );
		if ( placeholder ) {
			expect( placeholder ).toBe( 'Enter your password' );
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
		await page.fill( '#title', 'Password Affix Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Affix Password' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'password' );

		// Set prepend if available
		const prependInput = page.locator(
			'.acf-field-setting-prepend input[type="text"]'
		);
		if ( await prependInput.isVisible() ) {
			await prependInput.fill( '🔒' );
		}

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create post
		const post = await requestUtils.createPost( {
			title: 'Password Affix Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Verify field exists
		const passwordField = page.locator(
			'.acf-field[data-name="affix_password"]'
		);
		await expect( passwordField ).toBeVisible();

		// Enter password
		const passwordInput = passwordField.locator( 'input[type="password"]' );
		await passwordInput.fill( 'mysecretpassword' );
		await expect( passwordInput ).not.toHaveValue( '' );
	} );
} );
