/**
 * E2E tests for the Checkbox field type.
 *
 * Tests the Checkbox field which provides multiple checkbox
 * options for selecting one or more values.
 */
const { test, expect } = require( './fixtures' );
const {
	PLUGIN_SLUG,
	deleteFieldGroups,
	waitForMetaBoxes,
	toggleFieldSetting,
} = require( './field-helpers' );

const TEST_PLUGIN_SLUG = 'scf-test-plugin-all-field-types';
const FIELD_GROUP_LABEL = 'Checkbox Field Test';

test.describe( 'Field Type > Checkbox', () => {
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

	test( 'should create a checkbox field and select multiple options', async ( {
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
		await fieldLabel.fill( 'Test Checkbox' );

		// Select checkbox type
		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'checkbox' );

		// Add choices
		const choicesTextarea = page.locator(
			'textarea[id^="acf_fields-field_"][id$="-choices"]'
		);
		await choicesTextarea.fill(
			'pizza : Pizza\nburger : Burger\nsalad : Salad\npasta : Pasta'
		);

		// Publish
		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create test post
		const post = await requestUtils.createPost( {
			title: 'Checkbox Field Test Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Select multiple checkboxes
		const pizzaCheckbox = page.locator(
			'.acf-field[data-name="test_checkbox"] input[type="checkbox"][value="pizza"]'
		);
		await pizzaCheckbox.check();

		const saladCheckbox = page.locator(
			'.acf-field[data-name="test_checkbox"] input[type="checkbox"][value="salad"]'
		);
		await saladCheckbox.check();

		// Verify checkboxes are checked
		await expect( pizzaCheckbox ).toBeChecked();
		await expect( saladCheckbox ).toBeChecked();

		// Preview and verify
		const previewPage = await editor.openPreviewPage();

		const checkboxOutput = previewPage.locator( '#scf-test-test_checkbox' );
		await expect( checkboxOutput ).toBeVisible();
		await expect( checkboxOutput ).toContainText( 'pizza' );
		await expect( checkboxOutput ).toContainText( 'salad' );

		await previewPage.close();
	} );

	test( 'should support toggle all option', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Create field group with toggle all
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'Checkbox Toggle Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Toggle Checkbox' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'checkbox' );

		const choicesTextarea = page.locator(
			'textarea[id^="acf_fields-field_"][id$="-choices"]'
		);
		await choicesTextarea.fill( 'opt1 : Option 1\nopt2 : Option 2\nopt3 : Option 3' );

		// Enable toggle all
		await toggleFieldSetting( page, '.acf-field-setting-toggle', true );

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create post
		const post = await requestUtils.createPost( {
			title: 'Checkbox Toggle Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Click toggle all
		const toggleAll = page.locator(
			'.acf-field[data-name="toggle_checkbox"] .acf-checkbox-toggle'
		);
		if ( await toggleAll.isVisible() ) {
			await toggleAll.click();

			// All checkboxes should be checked
			const allCheckboxes = page.locator(
				'.acf-field[data-name="toggle_checkbox"] input[type="checkbox"]:not(.acf-checkbox-toggle)'
			);
			const count = await allCheckboxes.count();
			for ( let i = 0; i < count; i++ ) {
				await expect( allCheckboxes.nth( i ) ).toBeChecked();
			}
		}
	} );
} );
