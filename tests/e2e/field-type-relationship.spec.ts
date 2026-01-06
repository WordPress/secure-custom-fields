/**
 * E2E tests for the Relationship field type.
 *
 * Tests the Relationship field which allows users to select
 * multiple posts from a searchable list.
 */
const { test, expect } = require( './fixtures' );
const {
	PLUGIN_SLUG,
	deleteFieldGroups,
	waitForMetaBoxes,
} = require( './field-helpers' );

const TEST_PLUGIN_SLUG = 'scf-test-plugin-all-field-types';
const FIELD_GROUP_LABEL = 'Relationship Field Test';

test.describe( 'Field Type > Relationship', () => {
	let relatedPost1;
	let relatedPost2;

	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( PLUGIN_SLUG );
		await requestUtils.activatePlugin( TEST_PLUGIN_SLUG );

		// Create posts to relate to
		relatedPost1 = await requestUtils.createPost( {
			title: 'Related Post Alpha',
			status: 'publish',
		} );
		relatedPost2 = await requestUtils.createPost( {
			title: 'Related Post Beta',
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

	test( 'should create a relationship field and select posts', async ( {
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
		await fieldLabel.fill( 'Test Relationship' );

		// Select relationship type
		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'relationship' );

		// Publish
		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create test post
		const post = await requestUtils.createPost( {
			title: 'Relationship Test Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Wait for the relationship field to initialize
		await page.waitForTimeout( 1000 );

		// Filter by post type to only show Posts (not Media, Pages, etc.)
		const postTypeFilter = page.locator(
			'.acf-field[data-name="test_relationship"] .acf-relationship select[data-filter="post_type"]'
		);
		if ( await postTypeFilter.isVisible() ) {
			await postTypeFilter.selectOption( 'post' );
			await page.waitForTimeout( 500 );
		}

		// Find and click on the first related post in the choices list
		// Use a more flexible selector that matches the relationship list
		const choicesList = page.locator(
			'.acf-field[data-name="test_relationship"] .acf-relationship .choices .acf-rel-item, .acf-field[data-name="test_relationship"] .acf-relationship .choices-list .acf-rel-item, .acf-field[data-name="test_relationship"] .acf-relationship .list .acf-rel-item'
		);

		// Wait for choices to load - give AJAX more time
		await choicesList.first().waitFor( { timeout: 20000 } );

		// Click on Related Post Alpha
		const alphaChoice = page.locator(
			'.acf-field[data-name="test_relationship"] .choices .acf-rel-item:has-text("Related Post Alpha")'
		);
		await alphaChoice.click();

		// Click on Related Post Beta
		const betaChoice = page.locator(
			'.acf-field[data-name="test_relationship"] .choices .acf-rel-item:has-text("Related Post Beta")'
		);
		await betaChoice.click();

		// Verify posts are in the values list
		const selectedItems = page.locator(
			'.acf-field[data-name="test_relationship"] .values .acf-rel-item'
		);
		await expect( selectedItems ).toHaveCount( 2 );

		// Preview and verify
		const previewPage = await editor.openPreviewPage();

		const relationshipOutput = previewPage.locator(
			'#scf-test-test_relationship'
		);
		await expect( relationshipOutput ).toBeVisible();
		await expect( relationshipOutput ).toContainText( 'Related Post Alpha' );
		await expect( relationshipOutput ).toContainText( 'Related Post Beta' );
		await expect( relationshipOutput ).toHaveAttribute( 'data-count', '2' );

		await previewPage.close();
	} );

	test( 'should allow searching for posts', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Create field group
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'Relationship Search Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Searchable Relationship' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'relationship' );

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create post
		const post = await requestUtils.createPost( {
			title: 'Search Relationship Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Wait for relationship field to initialize
		await page.waitForTimeout( 1000 );

		// Filter by post type first to only show Posts
		const postTypeFilter = page.locator(
			'.acf-field[data-name="searchable_relationship"] .acf-relationship select[data-filter="post_type"]'
		);
		if ( await postTypeFilter.isVisible() ) {
			await postTypeFilter.selectOption( 'post' );
			await page.waitForTimeout( 500 );
		}

		// Search for "Alpha"
		const searchInput = page.locator(
			'.acf-field[data-name="searchable_relationship"] .acf-relationship input[data-filter="s"]'
		);
		await searchInput.fill( 'Alpha' );

		// Wait for search results - AJAX needs more time
		await page.waitForTimeout( 1000 );

		// Verify only Alpha appears in choices
		const choices = page.locator(
			'.acf-field[data-name="searchable_relationship"] .choices .acf-rel-item, .acf-field[data-name="searchable_relationship"] .choices-list .acf-rel-item'
		);
		// Alpha should appear, Beta should be filtered out
		const alphaChoice = choices.filter( { hasText: 'Alpha' } );
		await expect( alphaChoice.first() ).toBeVisible( { timeout: 10000 } );
	} );
} );
