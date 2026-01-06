/**
 * E2E tests for the Clone field type.
 *
 * Tests the Clone field which allows reusing fields from other
 * field groups, supporting both seamless and group display modes.
 */
const { test, expect } = require( './fixtures' );
const {
	PLUGIN_SLUG,
	deleteFieldGroups,
	waitForMetaBoxes,
	selectSelect2Option,
} = require( './field-helpers' );

const TEST_PLUGIN_SLUG = 'scf-test-plugin-all-field-types';
const SOURCE_GROUP_LABEL = 'Clone Source Group';
const CLONE_GROUP_LABEL = 'Clone Test Group';

test.describe( 'Field Type > Clone', () => {
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

	test( 'should clone fields from another field group in group display mode', async ( {
		page,
		admin,
		editor,
		requestUtils,
	} ) => {
		// First, create a source field group to clone from
		// Important: Set it to show on Pages, not Posts, to avoid duplicate fields
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		let addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', SOURCE_GROUP_LABEL );

		// Add a text field to source
		const sourceFieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await sourceFieldLabel.fill( 'Source Text' );

		// Keep as text type (default)
		const sourceFieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await sourceFieldType.selectOption( 'text' );

		// Set location to Pages (not Posts) so it doesn't conflict with Clone group
		// The location rule value dropdown has class "location-rule-value"
		const postTypeSelect = page.locator( 'select.location-rule-value' );
		await postTypeSelect.selectOption( 'page' );

		// Publish source group
		let publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		let successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Now create the clone field group
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', CLONE_GROUP_LABEL );

		// Add clone field
		const cloneFieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await cloneFieldLabel.fill( 'Test Clone' );

		const cloneFieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await cloneFieldType.selectOption( 'clone' );

		// Wait for clone settings to load
		await page.waitForSelector( '.acf-field-setting-clone', { timeout: 5000 } );
		await page.waitForTimeout( 500 ); // Give Select2 time to initialize

		// Select the source field group to clone using Select2
		await selectSelect2Option(
			page,
			'.acf-field-setting-clone',
			SOURCE_GROUP_LABEL,
			SOURCE_GROUP_LABEL
		);

		// Set display mode to "Group"
		const displaySelect = page.locator( '.acf-field-setting-display select' );
		if ( await displaySelect.isVisible() ) {
			await displaySelect.selectOption( 'group' );
		}

		// Publish clone group
		publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create test post
		const post = await requestUtils.createPost( {
			title: 'Clone Field Test Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// The cloned field should appear under the clone field wrapper
		// In group display mode, the source_text field is nested inside test_clone
		const textField = page.locator(
			'.acf-field[data-name="test_clone"] .acf-field[data-name="source_text"] input[type="text"]'
		);
		await expect( textField ).toBeVisible( { timeout: 10000 } );
		await textField.fill( 'Cloned value!' );

		// Preview and verify
		const previewPage = await editor.openPreviewPage();

		// Check for the clone field output in the preview
		const cloneOutput = previewPage.locator( '#scf-test-test_clone' );
		await expect( cloneOutput ).toBeVisible();
		await expect( cloneOutput ).toContainText( 'Cloned value!' );

		await previewPage.close();
	} );

	test( 'should clone individual fields seamlessly', async ( {
		page,
		admin,
		editor,
		requestUtils,
	} ) => {
		// Create source field group - set to pages so it doesn't conflict
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		let addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'Seamless Clone Source' );

		const sourceFieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await sourceFieldLabel.fill( 'Shared Field' );

		// Set location to Pages (not Posts) so it doesn't conflict
		const postTypeSelect = page.locator( 'select.location-rule-value' );
		await postTypeSelect.selectOption( 'page' );

		let publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		let successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create clone field group with seamless display
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'Seamless Clone Group' );

		const cloneFieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await cloneFieldLabel.fill( 'Seamless Clone' );

		const cloneFieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await cloneFieldType.selectOption( 'clone' );

		// Wait for clone settings to load
		await page.waitForSelector( '.acf-field-setting-clone', { timeout: 5000 } );
		await page.waitForTimeout( 500 ); // Give Select2 time to initialize

		// Select the source group using Select2
		await selectSelect2Option(
			page,
			'.acf-field-setting-clone',
			'Seamless Clone Source',
			'Seamless Clone Source'
		);

		// Keep display as "Seamless" (default)
		const displaySelect = page.locator( '.acf-field-setting-display select' );
		if ( await displaySelect.isVisible() ) {
			await displaySelect.selectOption( 'seamless' );
		}

		publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create test post
		const post = await requestUtils.createPost( {
			title: 'Seamless Clone Test',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// In seamless mode, the cloned field appears directly without nesting
		// The field is rendered directly with its original data-name
		const sharedField = page.locator(
			'.acf-field[data-name="shared_field"] input[type="text"]'
		);
		await expect( sharedField ).toBeVisible( { timeout: 10000 } );
		await sharedField.fill( 'Seamless value' );

		// Save and verify
		const previewPage = await editor.openPreviewPage();

		// In seamless mode, the clone field renders its sub-fields under the clone field name
		// Check for the seamless_clone clone field output
		const cloneOutput = previewPage.locator( '#scf-test-seamless_clone' );
		await expect( cloneOutput ).toBeVisible();
		await expect( cloneOutput ).toContainText( 'Seamless value' );

		await previewPage.close();
	} );
} );
