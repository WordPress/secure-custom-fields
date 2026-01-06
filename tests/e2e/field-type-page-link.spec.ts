/**
 * E2E tests for the Page Link field type.
 *
 * Tests the Page Link field which allows users to select
 * pages/posts and returns their permalink URLs.
 */
const { test, expect } = require( './fixtures' );
const {
	PLUGIN_SLUG,
	deleteFieldGroups,
	waitForMetaBoxes,
	toggleFieldSetting,
} = require( './field-helpers' );

const TEST_PLUGIN_SLUG = 'scf-test-plugin-all-field-types';
const FIELD_GROUP_LABEL = 'Page Link Field Test';

test.describe( 'Field Type > Page Link', () => {
	let targetPage;

	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( PLUGIN_SLUG );
		await requestUtils.activatePlugin( TEST_PLUGIN_SLUG );

		// Create a page to link to
		targetPage = await requestUtils.createPage( {
			title: 'Target Page for Linking',
			status: 'publish',
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deactivatePlugin( TEST_PLUGIN_SLUG );
		await requestUtils.deactivatePlugin( PLUGIN_SLUG );
		await requestUtils.deleteAllPosts();
		await requestUtils.deleteAllPages();
	} );

	test.beforeEach( async ( { page, admin } ) => {
		await deleteFieldGroups( page, admin );
	} );

	test( 'should create a page link field and select a page', async ( {
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
		await fieldLabel.fill( 'Test Page Link' );

		// Select page_link type
		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'page_link' );

		// Publish
		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create test post
		const post = await requestUtils.createPost( {
			title: 'Page Link Test Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// The page link field uses Select2
		const selectInput = page.locator(
			'.acf-field[data-name="test_page_link"] .select2-selection'
		);
		await selectInput.click();

		// Search for the target page
		const searchInput = page.locator( '.select2-search__field' );
		await searchInput.fill( 'Target Page' );

		// Wait for results and click - use role="option" to avoid matching the group header
		const option = page.locator(
			'.select2-results__option[role="option"]:has-text("Target Page for Linking")'
		);
		await option.waitFor();
		await option.click();

		// Verify selection
		const selectedValue = page.locator(
			'.acf-field[data-name="test_page_link"] .select2-selection__rendered'
		);
		await expect( selectedValue ).toContainText( 'Target Page for Linking' );

		// Preview and verify - page link returns URL
		const previewPage = await editor.openPreviewPage();

		const pageLinkOutput = previewPage.locator( '#scf-test-test_page_link' );
		await expect( pageLinkOutput ).toBeVisible();
		// Page link should contain a URL
		await expect( pageLinkOutput ).toContainText( 'http' );

		await previewPage.close();
	} );

	test( 'should support multiple page selection', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Create another page
		const secondPage = await requestUtils.createPage( {
			title: 'Second Target Page',
			status: 'publish',
		} );

		// Create field group with multiple selection
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'Multi Page Link Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Multi Page Link' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'page_link' );

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
			title: 'Multi Page Link Test Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Select first page
		const selectInput = page.locator(
			'.acf-field[data-name="multi_page_link"] .select2-selection'
		);
		await selectInput.click();

		let searchInput = page.locator( '.select2-search__field' );
		await searchInput.fill( 'Target Page for Linking' );

		// Use role="option" to avoid matching the group header
		let option = page.locator(
			'.select2-results__option[role="option"]:has-text("Target Page for Linking")'
		);
		await option.waitFor();
		await option.click();

		// Select second page
		await selectInput.click();
		searchInput = page.locator( '.select2-search__field' );
		await searchInput.fill( 'Second Target' );

		option = page.locator(
			'.select2-results__option[role="option"]:has-text("Second Target Page")'
		);
		await option.waitFor();
		await option.click();

		// Verify both are selected
		const selectedValues = page.locator(
			'.acf-field[data-name="multi_page_link"] .select2-selection__choice'
		);
		await expect( selectedValues ).toHaveCount( 2 );
	} );
} );
