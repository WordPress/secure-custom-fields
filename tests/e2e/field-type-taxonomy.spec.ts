/**
 * E2E tests for the Taxonomy field type.
 *
 * Tests the Taxonomy field which allows users to select
 * taxonomy terms (categories, tags, custom taxonomies).
 */
const { test, expect } = require( './fixtures' );
const {
	PLUGIN_SLUG,
	deleteFieldGroups,
	waitForMetaBoxes,
} = require( './field-helpers' );

const TEST_PLUGIN_SLUG = 'scf-test-plugin-all-field-types';
const FIELD_GROUP_LABEL = 'Taxonomy Field Test';

test.describe( 'Field Type > Taxonomy', () => {
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

	test( 'should create a taxonomy field and select categories', async ( {
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
		await fieldLabel.fill( 'Test Taxonomy' );

		// Select taxonomy type
		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'taxonomy' );

		// Keep default taxonomy (category) and appearance (checkbox)

		// Publish
		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create test post
		const post = await requestUtils.createPost( {
			title: 'Taxonomy Field Test Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// The taxonomy field shows checkboxes by default for categories
		// Look for "Uncategorized" which is the default category
		const uncategorizedCheckbox = page.locator(
			'.acf-field[data-name="test_taxonomy"] input[type="checkbox"]'
		).first();

		// If checkboxes are visible, check one
		if ( await uncategorizedCheckbox.isVisible() ) {
			await uncategorizedCheckbox.check();
		} else {
			// May be using select2 appearance
			const selectInput = page.locator(
				'.acf-field[data-name="test_taxonomy"] .select2-selection'
			);
			if ( await selectInput.isVisible() ) {
				await selectInput.click();
				const option = page.locator( '.select2-results__option' ).first();
				await option.waitFor();
				await option.click();
			}
		}

		// Preview and verify
		const previewPage = await editor.openPreviewPage();

		const taxonomyOutput = previewPage.locator( '#scf-test-test_taxonomy' );
		await expect( taxonomyOutput ).toBeVisible();
		await expect( taxonomyOutput ).toContainText( 'Terms:' );

		await previewPage.close();
	} );

	test( 'should support select appearance', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Create field group with select appearance
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'Taxonomy Select Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Select Taxonomy' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'taxonomy' );

		// Change appearance to select
		const appearanceSelect = page.locator(
			'.acf-field-setting-field_type select'
		);
		await appearanceSelect.selectOption( 'select' );

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create test post
		const post = await requestUtils.createPost( {
			title: 'Taxonomy Select Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Verify select2 dropdown is present
		const selectInput = page.locator(
			'.acf-field[data-name="select_taxonomy"] .select2-selection'
		);
		await expect( selectInput ).toBeVisible();

		// Click and select a term
		await selectInput.click();
		const option = page.locator( '.select2-results__option' ).first();
		await option.waitFor();
		await option.click();

		// Verify selection is made
		const selectedValue = page.locator(
			'.acf-field[data-name="select_taxonomy"] .select2-selection__rendered'
		);
		await expect( selectedValue ).not.toBeEmpty();
	} );

	test( 'should support multi-select appearance', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Create field group with multi_select appearance
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'Taxonomy Multi Select Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Multi Taxonomy' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'taxonomy' );

		// Change appearance to multi_select
		const appearanceSelect = page.locator(
			'.acf-field-setting-field_type select'
		);
		await appearanceSelect.selectOption( 'multi_select' );

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create test post
		const post = await requestUtils.createPost( {
			title: 'Taxonomy Multi Select Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Verify multi-select is available
		const selectContainer = page.locator(
			'.acf-field[data-name="multi_taxonomy"] .select2-container'
		);
		await expect( selectContainer ).toBeVisible();
	} );
} );
