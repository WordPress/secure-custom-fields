/**
 * E2E tests for the Group field type.
 *
 * Tests the Group field which groups multiple sub-fields together,
 * storing their values as a nested array.
 */
const { test, expect } = require( './fixtures' );
const {
	PLUGIN_SLUG,
	deleteFieldGroups,
	waitForMetaBoxes,
} = require( './field-helpers' );

const TEST_PLUGIN_SLUG = 'scf-test-plugin-all-field-types';
const FIELD_GROUP_LABEL = 'Group Field Test';

test.describe( 'Field Type > Group', () => {
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

	test( 'should create a group field with sub-fields and verify values', async ( {
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
		await fieldLabel.fill( 'Test Group' );

		// Select group field type
		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'group' );

		// Add first sub-field (text)
		const addSubFieldButton = page.locator(
			'.acf-field-setting-sub_fields a.add-first-field'
		);
		await addSubFieldButton.click();

		const subFieldLabel = page
			.locator( '.acf-field-object input.field-label' )
			.last();
		await subFieldLabel.waitFor();
		await subFieldLabel.fill( 'Name' );

		// Add second sub-field (email)
		const addAnotherSubFieldButton = page.locator(
			'.acf-field-setting-sub_fields .acf-is-subfields a.add-field.acf-btn-secondary'
		);
		await addAnotherSubFieldButton.click();

		const secondSubFieldLabel = page
			.locator( '.acf-field-object input.field-label' )
			.last();
		await secondSubFieldLabel.waitFor();
		await secondSubFieldLabel.fill( 'Email' );

		// Change second field type to email
		const secondSubFieldType = page
			.locator( '.acf-field-object' )
			.last()
			.locator( 'select.field-type' );
		await secondSubFieldType.selectOption( 'email' );

		// Publish
		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create a test post
		const post = await requestUtils.createPost( {
			title: 'Group Field Test Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Fill in group sub-fields
		const nameField = page.locator(
			'.acf-field[data-name="name"] input[type="text"]'
		);
		await nameField.fill( 'John Doe' );

		const emailField = page.locator(
			'.acf-field[data-name="email"] input[type="email"]'
		);
		await emailField.fill( 'john@example.com' );

		// Preview and verify
		const previewPage = await editor.openPreviewPage();

		const groupOutput = previewPage.locator( '#scf-test-test_group' );
		await expect( groupOutput ).toBeVisible();
		await expect( groupOutput ).toContainText( 'name: John Doe' );
		await expect( groupOutput ).toContainText( 'email: john@example.com' );

		await previewPage.close();
	} );

	test( 'should support nested groups', async ( {
		page,
		admin,
		editor,
		requestUtils,
	} ) => {
		// Create field group with nested group
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'Nested Group Test' );

		// Set outer group field
		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Test Group' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'group' );

		// Add a text sub-field
		const addSubFieldButton = page.locator(
			'.acf-field-setting-sub_fields a.add-first-field'
		);
		await addSubFieldButton.click();

		const subFieldLabel = page
			.locator( '.acf-field-object input.field-label' )
			.last();
		await subFieldLabel.waitFor();
		await subFieldLabel.fill( 'Title' );

		// Publish
		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create and test post
		const post = await requestUtils.createPost( {
			title: 'Nested Group Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		const titleField = page.locator(
			'.acf-field[data-name="title"] input[type="text"]'
		);
		await titleField.fill( 'Nested Title' );

		const previewPage = await editor.openPreviewPage();

		const groupOutput = previewPage.locator( '#scf-test-test_group' );
		await expect( groupOutput ).toBeVisible();
		await expect( groupOutput ).toContainText( 'title: Nested Title' );

		await previewPage.close();
	} );
} );
