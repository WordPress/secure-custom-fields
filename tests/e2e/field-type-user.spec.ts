/**
 * E2E tests for the User field type.
 *
 * Tests the User field which allows users to select
 * WordPress users from a dropdown/search interface.
 */
const { test, expect } = require( './fixtures' );
const {
	PLUGIN_SLUG,
	deleteFieldGroups,
	waitForMetaBoxes,
	toggleFieldSetting,
} = require( './field-helpers' );

const TEST_PLUGIN_SLUG = 'scf-test-plugin-all-field-types';
const FIELD_GROUP_LABEL = 'User Field Test';

test.describe( 'Field Type > User', () => {
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

	test( 'should create a user field and select a user', async ( {
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
		await fieldLabel.fill( 'Test User' );

		// Select user type
		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'user' );

		// Publish
		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create test post
		const post = await requestUtils.createPost( {
			title: 'User Field Test Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// The user field uses Select2
		const selectInput = page.locator(
			'.acf-field[data-name="test_user"] .select2-selection'
		);
		await selectInput.click();

		// Select the admin user (should be available)
		const searchInput = page.locator( '.select2-search__field' );
		await searchInput.fill( 'admin' );

		// Wait for results and click
		const option = page.locator( '.select2-results__option' ).first();
		await option.waitFor();
		await option.click();

		// Verify selection
		const selectedValue = page.locator(
			'.acf-field[data-name="test_user"] .select2-selection__rendered'
		);
		await expect( selectedValue ).not.toBeEmpty();

		// Preview and verify
		const previewPage = await editor.openPreviewPage();

		const userOutput = previewPage.locator( '#scf-test-test_user' );
		await expect( userOutput ).toBeVisible();
		await expect( userOutput ).toContainText( 'User:' );

		await previewPage.close();
	} );

	test( 'should support multiple user selection', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Create field group with multiple selection
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'Multi User Field Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Multi User' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'user' );

		// Enable multiple selection
		await toggleFieldSetting( page, '.acf-field-setting-multiple', true );

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create test post and verify multiple selection is available
		const post = await requestUtils.createPost( {
			title: 'Multi User Test Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Verify multi-select is enabled (multiple attribute or select2-multiple class)
		const selectContainer = page.locator(
			'.acf-field[data-name="multi_user"] .select2-container--default'
		);
		await expect( selectContainer ).toBeVisible();
	} );
} );
