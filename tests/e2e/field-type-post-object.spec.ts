/**
 * E2E tests for the Post Object field type.
 *
 * Tests the Post Object field which allows users to select
 * one or more posts from a dropdown/search interface.
 */
const { test, expect } = require( './fixtures' );
const {
	PLUGIN_SLUG,
	deleteFieldGroups,
	waitForMetaBoxes,
	toggleFieldSetting,
} = require( './field-helpers' );

const TEST_PLUGIN_SLUG = 'scf-test-plugin-all-field-types';
const FIELD_GROUP_LABEL = 'Post Object Field Test';

test.describe( 'Field Type > Post Object', () => {
	let targetPost;

	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( PLUGIN_SLUG );
		await requestUtils.activatePlugin( TEST_PLUGIN_SLUG );

		// Create a post to select
		targetPost = await requestUtils.createPost( {
			title: 'Target Post for Selection',
			status: 'publish',
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deactivatePlugin( TEST_PLUGIN_SLUG );
		await requestUtils.deactivatePlugin( PLUGIN_SLUG );
		await requestUtils.deleteAllPosts();
	} );

	test.beforeEach( async ( { page, admin } ) => {
		await deleteFieldGroups( page, admin );
	} );

	test( 'should create a post object field and select a post', async ( {
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
		await fieldLabel.fill( 'Test Post Object' );

		// Select post_object type
		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'post_object' );

		// Publish
		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create test post
		const post = await requestUtils.createPost( {
			title: 'Post Object Test Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// The post object field uses Select2
		const selectInput = page.locator(
			'.acf-field[data-name="test_post_object"] .select2-selection'
		);
		await selectInput.click();

		// Search for the target post
		const searchInput = page.locator( '.select2-search__field' );
		await searchInput.fill( 'Target Post' );

		// Wait for results and click - use role="option" to avoid matching the group header
		const option = page.locator(
			'.select2-results__option[role="option"]:has-text("Target Post for Selection")'
		);
		await option.waitFor();
		await option.click();

		// Verify selection
		const selectedValue = page.locator(
			'.acf-field[data-name="test_post_object"] .select2-selection__rendered'
		);
		await expect( selectedValue ).toContainText( 'Target Post for Selection' );

		// Preview and verify
		const previewPage = await editor.openPreviewPage();

		const postObjectOutput = previewPage.locator(
			'#scf-test-test_post_object'
		);
		await expect( postObjectOutput ).toBeVisible();
		await expect( postObjectOutput ).toContainText(
			'Target Post for Selection'
		);

		await previewPage.close();
	} );

	test( 'should support multiple post selection', async ( {
		page,
		admin,
		editor,
		requestUtils,
	} ) => {
		// Create another target post
		const secondPost = await requestUtils.createPost( {
			title: 'Second Target Post',
			status: 'publish',
		} );

		// Create field group with multiple selection
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'Multi Post Object Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Multi Post Object' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'post_object' );

		// Enable multiple selection
		await toggleFieldSetting( page, '.acf-field-setting-multiple', true );

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create test post
		const post = await requestUtils.createPost( {
			title: 'Multi Post Object Test Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Select first post
		const selectInput = page.locator(
			'.acf-field[data-name="multi_post_object"] .select2-selection'
		);
		await selectInput.click();

		let searchInput = page.locator( '.select2-search__field' );
		await searchInput.fill( 'Target Post for Selection' );

		// Use role="option" to avoid matching the group header
		let option = page.locator(
			'.select2-results__option[role="option"]:has-text("Target Post for Selection")'
		);
		await option.waitFor();
		await option.click();

		// Select second post
		await selectInput.click();
		searchInput = page.locator( '.select2-search__field' );
		await searchInput.fill( 'Second Target' );

		option = page.locator(
			'.select2-results__option[role="option"]:has-text("Second Target Post")'
		);
		await option.waitFor();
		await option.click();

		// Verify both are selected
		const selectedValues = page.locator(
			'.acf-field[data-name="multi_post_object"] .select2-selection__choice'
		);
		await expect( selectedValues ).toHaveCount( 2 );
	} );
} );
