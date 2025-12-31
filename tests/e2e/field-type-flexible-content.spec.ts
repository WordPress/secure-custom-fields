/**
 * E2E tests for the Flexible Content field type.
 *
 * Tests the Flexible Content field which allows users to add
 * multiple layouts with different sub-fields in any order.
 */
const { test, expect } = require( './fixtures' );
const {
	PLUGIN_SLUG,
	deleteFieldGroups,
	waitForMetaBoxes,
	expandFCLayout,
} = require( './field-helpers' );

const TEST_PLUGIN_SLUG = 'scf-test-plugin-all-field-types';
const FIELD_GROUP_LABEL = 'Flexible Content Test';

test.describe( 'Field Type > Flexible Content', () => {
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

	test( 'should create flexible content with multiple layouts', async ( {
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
		await fieldLabel.fill( 'Test Flexible Content' );

		// Select flexible content type
		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'flexible_content' );

		// Wait for the field settings to update
		await page.waitForTimeout( 500 );

		// The first layout is created automatically when selecting flexible_content type
		// We need to scroll to see it and configure it
		const firstLayout = page.locator( '.acf-field-setting-fc_layout' ).first();
		await firstLayout.scrollIntoViewIfNeeded();
		await firstLayout.waitFor( { state: 'visible', timeout: 10000 } );

		// Expand layout if collapsed
		await expandFCLayout( firstLayout );

		// Fill first layout label using the input with class layout-label
		const firstLayoutLabel = firstLayout.locator( 'input.layout-label' );
		await firstLayoutLabel.fill( 'Text Block' );

		// Add sub-field to first layout - the "Add Field" button is inside the layout
		const addFirstSubFieldButton = firstLayout.locator(
			'a:has-text("Add Field"), button:has-text("Add Field")'
		);
		await addFirstSubFieldButton.first().click();

		// Wait for sub-field to be added - a field object will appear
		await page.waitForTimeout( 500 );
		const firstSubFieldLabel = firstLayout.locator(
			'.acf-field-object input[id$="-label"]'
		);
		await firstSubFieldLabel.first().waitFor( { timeout: 5000 } );
		await firstSubFieldLabel.first().fill( 'Content' );

		// Add second layout by clicking the "Add Layout" button
		const addLayoutButton = firstLayout.locator( 'button.add-layout' );
		await addLayoutButton.click();

		// Wait for second layout to appear
		await page.waitForTimeout( 300 );
		const secondLayout = page.locator( '.acf-field-setting-fc_layout' ).nth( 1 );
		await secondLayout.waitFor( { state: 'visible', timeout: 5000 } );

		// Expand second layout if collapsed
		await expandFCLayout( secondLayout );

		// Fill second layout label
		const secondLayoutLabel = secondLayout.locator( 'input.layout-label' );
		await secondLayoutLabel.fill( 'Quote Block' );

		// Add sub-field to second layout
		const addSecondSubFieldButton = secondLayout.locator(
			'a:has-text("Add Field"), button:has-text("Add Field")'
		);
		await addSecondSubFieldButton.first().click();

		// Wait for sub-field to be added and fill label
		await page.waitForTimeout( 500 );
		const secondSubFieldLabel = secondLayout.locator(
			'.acf-field-object input[id$="-label"]'
		);
		await secondSubFieldLabel.first().waitFor( { timeout: 5000 } );
		await secondSubFieldLabel.first().fill( 'Quote Text' );

		// Publish
		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create test post
		const post = await requestUtils.createPost( {
			title: 'Flexible Content Test Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Add a Text Block layout - scroll to the field and click the visible Add Row button
		const fcField = page.locator(
			'.acf-field[data-name="test_flexible_content"]'
		);
		await fcField.scrollIntoViewIfNeeded();

		// Click the visible Add Row button (bottom one is usually visible)
		const addRowButton = fcField.locator( 'a:has-text("Add Row")' );
		await addRowButton.last().click();

		// Select "Text Block" from popup
		const textBlockOption = page.locator(
			'.acf-fc-popup a[data-layout="text_block"]'
		);
		await textBlockOption.click();

		// Fill in content - use visible input (not the template)
		const contentField = page.locator(
			'.acf-field[data-name="content"] input[type="text"]:not([disabled])'
		);
		await contentField.fill( 'This is a text block content.' );

		// Add a Quote Block layout
		await addRowButton.last().click();
		const quoteBlockOption = page.locator(
			'.acf-fc-popup a[data-layout="quote_block"]'
		);
		await quoteBlockOption.click();

		// Fill in quote - use visible input (not the template)
		const quoteField = page.locator(
			'.acf-field[data-name="quote_text"] input[type="text"]:not([disabled])'
		);
		await quoteField.fill( 'To be or not to be.' );

		// Preview and verify
		const previewPage = await editor.openPreviewPage();

		const fcOutput = previewPage.locator(
			'#scf-test-test_flexible_content'
		);
		await expect( fcOutput ).toBeVisible();

		// Check first layout
		const textBlockLayout = fcOutput.locator(
			'.scf-test-fc-layout[data-layout="text_block"]'
		);
		await expect( textBlockLayout ).toBeVisible();
		await expect( textBlockLayout ).toContainText(
			'This is a text block content.'
		);

		// Check second layout
		const quoteBlockLayout = fcOutput.locator(
			'.scf-test-fc-layout[data-layout="quote_block"]'
		);
		await expect( quoteBlockLayout ).toBeVisible();
		await expect( quoteBlockLayout ).toContainText( 'To be or not to be.' );

		await previewPage.close();
	} );

	test( 'should add multiple layout rows with correct order', async ( {
		page,
		admin,
		editor,
		requestUtils,
	} ) => {
		// Create a simple flexible content field
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'FC Reorder Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Test Flexible Content' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'flexible_content' );

		// Wait for the field settings to update
		await page.waitForTimeout( 500 );

		// The first layout is created automatically - scroll to it
		const firstLayout = page.locator( '.acf-field-setting-fc_layout' ).first();
		await firstLayout.scrollIntoViewIfNeeded();
		await firstLayout.waitFor( { state: 'visible', timeout: 10000 } );

		// Expand layout if collapsed
		await expandFCLayout( firstLayout );

		// Fill layout label
		const layoutLabelInput = firstLayout.locator( 'input.layout-label' );
		await layoutLabelInput.fill( 'Item' );

		// Add text sub-field
		const addSubFieldButton = firstLayout.locator(
			'a:has-text("Add Field"), button:has-text("Add Field")'
		);
		await addSubFieldButton.first().click();

		// Wait for sub-field and fill label
		await page.waitForTimeout( 500 );
		const subFieldLabel = firstLayout.locator(
			'.acf-field-object input[id$="-label"]'
		);
		await subFieldLabel.first().waitFor( { timeout: 5000 } );
		await subFieldLabel.first().fill( 'Title' );

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create test post and add multiple layouts
		const post = await requestUtils.createPost( {
			title: 'FC Reorder Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Scroll to FC field and use the Add Row button
		const fcField = page.locator(
			'.acf-field[data-name="test_flexible_content"]'
		);
		await fcField.scrollIntoViewIfNeeded();
		const addRowButton = fcField.locator( 'a:has-text("Add Row")' );

		// Add first item
		await addRowButton.last().click();
		const itemOption = page.locator( '.acf-fc-popup a[data-layout="item"]' );
		await itemOption.click();
		await page
			.locator(
				'.acf-field[data-name="title"] input[type="text"]:not([disabled])'
			)
			.first()
			.fill( 'First' );

		// Add second item
		await addRowButton.last().click();
		await itemOption.click();
		await page
			.locator(
				'.acf-field[data-name="title"] input[type="text"]:not([disabled])'
			)
			.last()
			.fill( 'Second' );

		// Verify order in preview
		const previewPage = await editor.openPreviewPage();

		const layouts = previewPage.locator( '.scf-test-fc-layout' );
		await expect( layouts ).toHaveCount( 2 );

		// Check order by index
		const firstLayoutPreview = previewPage.locator(
			'.scf-test-fc-layout[data-index="0"]'
		);
		await expect( firstLayoutPreview ).toContainText( 'First' );

		const secondLayoutPreview = previewPage.locator(
			'.scf-test-fc-layout[data-index="1"]'
		);
		await expect( secondLayoutPreview ).toContainText( 'Second' );

		await previewPage.close();
	} );
} );
