/**
 * E2E tests for block bindings in the site editor
 */
/* eslint-disable @typescript-eslint/no-explicit-any */
const { test, expect } = require( './fixtures' );

const PLUGIN_SLUG = 'secure-custom-fields';
const TEST_PLUGIN_SLUG = 'scf-test-setup-post-types';
const FIELD_GROUP_LABEL = 'Product Details';
const TEXT_FIELD_LABEL = 'Product Name';
const IMAGE_FIELD_LABEL = 'Product Image';

test.describe( 'Block Bindings in Site Editor', () => {
	test.beforeAll( async ( { requestUtils }: any ) => {
		await requestUtils.activatePlugin( PLUGIN_SLUG );
		await requestUtils.activatePlugin( TEST_PLUGIN_SLUG );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deactivatePlugin( TEST_PLUGIN_SLUG );
		await requestUtils.deactivatePlugin( PLUGIN_SLUG );
		await requestUtils.deleteAllPosts();
	} );

	test( 'should bind text field to paragraph block in site editor', async ( {
		page,
		admin,
	} ) => {
		// Navigate to Field Groups and create new.
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		// Fill field group title.
		await page.waitForSelector( '#title' );
		await page.fill( '#title', FIELD_GROUP_LABEL );

		// Add text field.
		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( TEXT_FIELD_LABEL );
		// The field name is generated automatically.

		// Select field type as text (it's default, but let's be explicit).
		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'text' );

		// Select Group Settings > Enable REST API
		const groupSettingsTab = page.getByRole( 'link', { name: 'Group Settings' } );
		await groupSettingsTab.click();

		// Enable Show in REST API
		const showInRestCheckbox = page.locator( '#acf_field_group-show_in_rest' );
		await showInRestCheckbox.check( { force: true } );

		// Submit form.
		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		// Verify success message.
		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();
		await expect( successNotice ).toContainText( 'Field group published' );

		// Navigate to site editor
		await admin.visitAdminPage( 'site-editor.php?p=%2Fwp_template%2Ftwentytwentyfive%2F%2Fsingle&canvas=edit' );

		// Wait for the site editor to load - wait for the iframe or editor container
		await page.waitForSelector('iframe[name="editor-canvas"]', { timeout: 10000 });
		await page.waitForTimeout(2000); // Give the editor a moment to fully load

		// Close the welcome guide modal if it appears by pressing Escape
		await page.keyboard.press( 'Escape' );
		await page.waitForTimeout( 500 );

		// Get the iframe and work within it
		const frameLocator = page.frameLocator('iframe[name="editor-canvas"]');
		
		// Click on the content block within the iframe
		const contentBlock = frameLocator.locator('[data-type="core/post-content"]').first();
		await contentBlock.click();

		// Press Enter to add a new paragraph block
		await page.keyboard.press('Enter');

		// Wait for the newly created empty paragraph block
		await frameLocator.locator('[data-type="core/paragraph"][data-empty="true"]').waitFor({ timeout: 5000 });

		// Click on the empty paragraph block to select it
		const emptyParagraph = frameLocator.locator('[data-type="core/paragraph"][data-empty="true"]');
		await emptyParagraph.click();

		// Wait for the "Connect to a field" panel to appear in the block inspector
		await page.waitForSelector('input[id^="components-form-token-input-combobox-control-"]', { timeout: 5000 });

		// Click on the combobox input to open suggestions
		const comboboxInput = page.locator('input[id^="components-form-token-input-combobox-control-"]').first();
		await comboboxInput.click();

		// Wait for suggestions to appear
		await page.waitForSelector('ul[id^="components-form-token-suggestions-combobox-control-"]', { timeout: 5000 });

		// Select "Product Name" from the dropdown
		await page.getByRole('option', { name: 'Product Name' }).click();

		// Verify the paragraph block now contains "Product Name"
		const boundParagraph = frameLocator.locator('[data-type="core/paragraph"]:has-text("Product Name")');
		await expect( boundParagraph ).toBeVisible();

	} );
} );
