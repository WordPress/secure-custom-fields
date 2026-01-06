/**
 * E2E tests for the Select field type.
 *
 * Tests the Select field which provides a dropdown selection
 * with support for single and multiple selections.
 */
const { test, expect } = require( './fixtures' );
const {
	PLUGIN_SLUG,
	deleteFieldGroups,
	waitForMetaBoxes,
	toggleFieldSetting,
} = require( './field-helpers' );

const TEST_PLUGIN_SLUG = 'scf-test-plugin-all-field-types';
const FIELD_GROUP_LABEL = 'Select Field Test';

test.describe( 'Field Type > Select', () => {
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

	test( 'should create a select field with choices and select an option', async ( {
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
		await fieldLabel.fill( 'Test Select' );

		// Select type
		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'select' );

		// Add choices
		const choicesTextarea = page.locator(
			'textarea[id^="acf_fields-field_"][id$="-choices"]'
		);
		await choicesTextarea.fill( 'red : Red\nblue : Blue\ngreen : Green' );

		// Publish
		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create test post
		const post = await requestUtils.createPost( {
			title: 'Select Field Test Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Select field may use Select2 or native select depending on settings
		const select2Input = page.locator(
			'.acf-field[data-name="test_select"] .select2-selection'
		);
		const nativeSelect = page.locator(
			'.acf-field[data-name="test_select"] select'
		);

		if ( await select2Input.isVisible() ) {
			// Select2 UI
			await select2Input.click();
			const option = page.locator(
				'.select2-results__option:has-text("Blue")'
			);
			await option.click();

			// Verify selection
			const selectedValue = page.locator(
				'.acf-field[data-name="test_select"] .select2-selection__rendered'
			);
			await expect( selectedValue ).toContainText( 'Blue' );
		} else if ( await nativeSelect.isVisible() ) {
			// Native select
			await nativeSelect.selectOption( 'blue' );
			await expect( nativeSelect ).toHaveValue( 'blue' );
		}

		// Preview and verify
		const previewPage = await editor.openPreviewPage();

		const selectOutput = previewPage.locator( '#scf-test-test_select' );
		await expect( selectOutput ).toBeVisible();
		await expect( selectOutput ).toContainText( 'blue' );

		await previewPage.close();
	} );

	test( 'should support multiple selection', async ( {
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
		await page.fill( '#title', 'Multi Select Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Multi Select' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'select' );

		// Add choices
		const choicesTextarea = page.locator(
			'textarea[id^="acf_fields-field_"][id$="-choices"]'
		);
		await choicesTextarea.fill( 'a : Option A\nb : Option B\nc : Option C' );

		// Enable multiple selection
		await toggleFieldSetting( page, '.acf-field-setting-multiple', true );

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create post and select multiple options
		const post = await requestUtils.createPost( {
			title: 'Multi Select Test Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Multi-select can appear as either Select2 or native multi-select
		const select2Input = page.locator(
			'.acf-field[data-name="multi_select"] .select2-selection'
		);
		const nativeSelect = page.locator(
			'.acf-field[data-name="multi_select"] select[multiple]'
		);

		if ( await select2Input.isVisible() ) {
			// Select2 UI - click to open dropdown
			await select2Input.click();
			let option = page.locator(
				'.select2-results__option:has-text("Option A")'
			);
			await option.click();

			await select2Input.click();
			option = page.locator(
				'.select2-results__option:has-text("Option B")'
			);
			await option.click();

			// Verify both are selected
			const selectedChoices = page.locator(
				'.acf-field[data-name="multi_select"] .select2-selection__choice'
			);
			await expect( selectedChoices ).toHaveCount( 2 );
		} else if ( await nativeSelect.isVisible() ) {
			// Native multi-select - use selectOption
			await nativeSelect.selectOption( [ 'a', 'b' ] );

			// Verify both are selected
			const selectedOptions = nativeSelect.locator( 'option:checked' );
			await expect( selectedOptions ).toHaveCount( 2 );
		}
	} );
} );
