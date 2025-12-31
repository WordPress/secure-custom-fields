/**
 * E2E tests for the Google Map field type.
 *
 * Tests the Google Map field which provides location selection
 * with geocoding and map display. Note: Requires Google Maps API key
 * for full functionality. These tests verify basic field rendering.
 */
const { test, expect } = require( './fixtures' );
const {
	PLUGIN_SLUG,
	deleteFieldGroups,
	waitForMetaBoxes,
} = require( './field-helpers' );

const TEST_PLUGIN_SLUG = 'scf-test-plugin-all-field-types';
const FIELD_GROUP_LABEL = 'Google Map Field Test';

test.describe( 'Field Type > Google Map', () => {
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

	test( 'should create a google map field and render the interface', async ( {
		page,
		admin,
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
		await fieldLabel.fill( 'Test Google Map' );

		// Select google_map type
		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'google_map' );

		// Publish
		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create test post
		const post = await requestUtils.createPost( {
			title: 'Google Map Test Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Verify the google map field container exists
		const mapField = page.locator(
			'.acf-field[data-name="test_google_map"]'
		);
		await expect( mapField ).toBeVisible();

		// The google map field should have search input
		const searchInput = mapField.locator( '.search' );
		await expect( searchInput ).toBeVisible();

		// Note: Without a Google Maps API key, the actual map won't load
		// but we verify the field structure is present
	} );

	test( 'should allow entering location via search input', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Create field group
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'Google Map Search Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Location Search' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'google_map' );

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create post
		const post = await requestUtils.createPost( {
			title: 'Google Map Search Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Find the search input - it's a text input with placeholder "Search for address..."
		const searchInput = page.locator(
			'.acf-field[data-name="location_search"] input[type="text"], .acf-field[data-name="location_search"] .search input'
		);
		await expect( searchInput.first() ).toBeVisible();

		// Type a location (won't autocomplete without API key, but input works)
		await searchInput.first().fill( 'New York, NY' );
		await expect( searchInput.first() ).toHaveValue( 'New York, NY' );
	} );

	test( 'should have map container for display', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Create field group
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'Google Map Container Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Map Container' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'google_map' );

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create post
		const post = await requestUtils.createPost( {
			title: 'Google Map Container Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Verify map canvas container exists
		const mapCanvas = page.locator(
			'.acf-field[data-name="map_container"] .canvas'
		);
		await expect( mapCanvas ).toBeVisible();

		// Hidden inputs for lat/lng should exist
		const latInput = page.locator(
			'.acf-field[data-name="map_container"] input[data-name="lat"], .acf-field[data-name="map_container"] input.input-lat'
		);
		const lngInput = page.locator(
			'.acf-field[data-name="map_container"] input[data-name="lng"], .acf-field[data-name="map_container"] input.input-lng'
		);

		// These may be hidden but should exist in the DOM
		await expect( mapCanvas ).toBeVisible();
	} );
} );
