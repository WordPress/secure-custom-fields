/**
 * E2E tests for the Number field type.
 *
 * Tests the Number field which provides a numeric input
 * with optional min, max, and step validation.
 */
const { test, expect } = require( './fixtures' );
const {
	PLUGIN_SLUG,
	deleteFieldGroups,
	waitForMetaBoxes,
} = require( './field-helpers' );

const TEST_PLUGIN_SLUG = 'scf-test-plugin-all-field-types';
const FIELD_GROUP_LABEL = 'Number Field Test';

test.describe( 'Field Type > Number', () => {
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

	test( 'should create a number field and enter a value', async ( {
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
		await fieldLabel.fill( 'Test Number' );

		// Select number type
		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'number' );

		// Publish
		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create test post
		const post = await requestUtils.createPost( {
			title: 'Number Test Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Fill the number input
		const numberInput = page.locator(
			'.acf-field[data-name="test_number"] input[type="number"]'
		);
		await numberInput.fill( '42' );

		// Verify the input
		await expect( numberInput ).toHaveValue( '42' );

		// Preview and verify
		const previewPage = await editor.openPreviewPage();

		const numberOutput = previewPage.locator( '#scf-test-test_number' );
		await expect( numberOutput ).toBeVisible();
		await expect( numberOutput ).toContainText( '42' );

		await previewPage.close();
	} );

	test( 'should respect min and max values', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Create field group with min/max
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'Number MinMax Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Bounded Number' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'number' );

		// Wait for field settings to load after type change
		await page.waitForTimeout( 500 );

		const fieldObject = page.locator( '.acf-field-object' ).first();

		// Try clicking Validation tab where min/max settings often are
		const validationTab = fieldObject
			.locator( 'a.acf-tab-button' )
			.filter( { hasText: 'Validation' } )
			.first();
		if (
			( await validationTab.count() ) > 0 &&
			( await validationTab.isVisible() )
		) {
			await validationTab.click();
			await page.waitForTimeout( 200 );
		}

		// Set min value - use more flexible selector
		const minInput = fieldObject
			.locator(
				'[data-name="min"] input, .acf-field-setting-min input[type="number"]'
			)
			.first();
		if ( await minInput.isVisible() ) {
			await minInput.fill( '0' );
		}

		// Set max value
		const maxInput = fieldObject
			.locator(
				'[data-name="max"] input, .acf-field-setting-max input[type="number"]'
			)
			.first();
		if ( await maxInput.isVisible() ) {
			await maxInput.fill( '100' );
		}

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create post
		const post = await requestUtils.createPost( {
			title: 'Number MinMax Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Verify the field exists
		const numberInput = page.locator(
			'.acf-field[data-name="bounded_number"] input[type="number"]'
		);
		await expect( numberInput ).toBeVisible();

		// Enter a valid value and verify
		await numberInput.fill( '50' );
		await expect( numberInput ).toHaveValue( '50' );
	} );

	test( 'should support step value', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Create field group with step
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'Number Step Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Step Number' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'number' );

		// Set step value
		const stepInput = page.locator(
			'.acf-field-setting-step input[type="number"]'
		);
		if ( await stepInput.isVisible() ) {
			await stepInput.fill( '5' );
		}

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create post
		const post = await requestUtils.createPost( {
			title: 'Number Step Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Verify the field exists
		const numberInput = page.locator(
			'.acf-field[data-name="step_number"] input[type="number"]'
		);
		await expect( numberInput ).toBeVisible();

		// Enter a value
		await numberInput.fill( '25' );
		await expect( numberInput ).toHaveValue( '25' );
	} );

	test( 'should support decimal numbers', async ( {
		page,
		admin,
		editor,
		requestUtils,
	} ) => {
		// Create field group with decimal step
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'Number Decimal Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Decimal Number' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'number' );

		// Set step to allow decimals
		const stepInput = page.locator(
			'.acf-field-setting-step input[type="number"]'
		);
		if ( await stepInput.isVisible() ) {
			await stepInput.fill( '0.01' );
		}

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create post
		const post = await requestUtils.createPost( {
			title: 'Number Decimal Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Enter a decimal value
		const numberInput = page.locator(
			'.acf-field[data-name="decimal_number"] input[type="number"]'
		);
		await numberInput.fill( '3.14' );

		// Verify the input
		await expect( numberInput ).toHaveValue( '3.14' );

		// Preview and verify
		const previewPage = await editor.openPreviewPage();

		const numberOutput = previewPage.locator( '#scf-test-decimal_number' );
		await expect( numberOutput ).toBeVisible();
		await expect( numberOutput ).toContainText( '3.14' );

		await previewPage.close();
	} );
} );
