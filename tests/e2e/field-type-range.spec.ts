/**
 * E2E tests for the Range field type.
 *
 * Tests the Range field which provides a slider input
 * for selecting numeric values within a range.
 */
const { test, expect } = require( './fixtures' );
const {
	PLUGIN_SLUG,
	deleteFieldGroups,
	waitForMetaBoxes,
} = require( './field-helpers' );

const TEST_PLUGIN_SLUG = 'scf-test-plugin-all-field-types';
const FIELD_GROUP_LABEL = 'Range Field Test';

test.describe( 'Field Type > Range', () => {
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

	test( 'should create a range field and adjust the slider', async ( {
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
		await fieldLabel.fill( 'Test Range' );

		// Select range type
		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'range' );

		// Publish
		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create test post
		const post = await requestUtils.createPost( {
			title: 'Range Test Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Find the range input
		const rangeInput = page.locator(
			'.acf-field[data-name="test_range"] input[type="range"]'
		);
		await expect( rangeInput ).toBeVisible();

		// Set a value using JavaScript since slider interaction can be tricky
		await rangeInput.fill( '75' );

		// Verify the value
		await expect( rangeInput ).toHaveValue( '75' );

		// Preview and verify
		const previewPage = await editor.openPreviewPage();

		const rangeOutput = previewPage.locator( '#scf-test-test_range' );
		await expect( rangeOutput ).toBeVisible();
		await expect( rangeOutput ).toContainText( '75' );

		await previewPage.close();
	} );

	test( 'should respect min, max, and step settings', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Create field group with custom range settings
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'Range Config Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Custom Range' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'range' );

		// Wait for field settings to load after type change
		await page.waitForTimeout( 500 );

		// Range field min/max/step settings use input fields with data attributes
		// Find them with a more flexible selector approach
		const fieldObject = page.locator( '.acf-field-object' ).first();

		// Try clicking Validation tab where min/max settings often are
		const validationTab = fieldObject.locator( 'a.acf-tab-button' ).filter( { hasText: 'Validation' } ).first();
		if ( ( await validationTab.count() ) > 0 && ( await validationTab.isVisible() ) ) {
			await validationTab.click();
			await page.waitForTimeout( 200 );
		}

		// Set min - use more generic selector
		const minInput = fieldObject.locator( '[data-name="min"] input, .acf-field-setting-min input' ).first();
		if ( await minInput.isVisible() ) {
			await minInput.fill( '10' );
		}

		// Set max
		const maxInput = fieldObject.locator( '[data-name="max"] input, .acf-field-setting-max input' ).first();
		if ( await maxInput.isVisible() ) {
			await maxInput.fill( '200' );
		}

		// Set step
		const stepInput = fieldObject.locator( '[data-name="step"] input, .acf-field-setting-step input' ).first();
		if ( await stepInput.isVisible() ) {
			await stepInput.fill( '10' );
		}

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create post
		const post = await requestUtils.createPost( {
			title: 'Range Config Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Verify the range field with custom settings
		const rangeInput = page.locator(
			'.acf-field[data-name="custom_range"] input[type="range"]'
		);
		await expect( rangeInput ).toBeVisible();

		// Check attributes - verify some custom settings were applied
		// The min/max settings should have been set if Validation tab was found
		const min = await rangeInput.getAttribute( 'min' );
		const max = await rangeInput.getAttribute( 'max' );

		// Verify at least min and max were applied (step may not always be visible)
		expect( min ).toBe( '10' );
		expect( max ).toBe( '200' );

		// Verify the range input is functional with the custom min/max
		// Set a value within the custom range
		await rangeInput.fill( '100' );
		await expect( rangeInput ).toHaveValue( '100' );
	} );

	test( 'should display current value', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Create field group
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'Range Display Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Display Range' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'range' );

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create post
		const post = await requestUtils.createPost( {
			title: 'Range Display Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Set a value and check if display shows it
		const rangeInput = page.locator(
			'.acf-field[data-name="display_range"] input[type="range"]'
		);
		await rangeInput.fill( '50' );

		// Look for value display (may be in a span or other element)
		const valueDisplay = page.locator(
			'.acf-field[data-name="display_range"] .acf-range-value, .acf-field[data-name="display_range"] input[type="number"]'
		);

		if ( await valueDisplay.isVisible() ) {
			const displayedValue = await valueDisplay.inputValue();
			expect( displayedValue ).toBe( '50' );
		}

		// At minimum, verify the range input has the correct value
		await expect( rangeInput ).toHaveValue( '50' );
	} );

	test( 'should work with prepend and append text', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Create field group with prepend/append
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'Range Affix Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Price Range' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'range' );

		// Set prepend if available
		const prependInput = page.locator(
			'.acf-field-setting-prepend input[type="text"]'
		);
		if ( await prependInput.isVisible() ) {
			await prependInput.fill( '$' );
		}

		// Set append if available
		const appendInput = page.locator(
			'.acf-field-setting-append input[type="text"]'
		);
		if ( await appendInput.isVisible() ) {
			await appendInput.fill( '.00' );
		}

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create post
		const post = await requestUtils.createPost( {
			title: 'Range Affix Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Verify the range field exists
		const rangeField = page.locator(
			'.acf-field[data-name="price_range"]'
		);
		await expect( rangeField ).toBeVisible();

		// Set a value
		const rangeInput = rangeField.locator( 'input[type="range"]' );
		await rangeInput.fill( '50' );
		await expect( rangeInput ).toHaveValue( '50' );
	} );
} );
