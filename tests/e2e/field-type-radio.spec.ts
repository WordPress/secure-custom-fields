/**
 * E2E tests for the Radio Button field type.
 *
 * Tests the Radio field which provides radio button options
 * for selecting a single value from multiple choices.
 */
const { test, expect } = require( './fixtures' );
const {
	PLUGIN_SLUG,
	deleteFieldGroups,
	waitForMetaBoxes,
	toggleFieldSetting,
} = require( './field-helpers' );

const TEST_PLUGIN_SLUG = 'scf-test-plugin-all-field-types';
const FIELD_GROUP_LABEL = 'Radio Field Test';

test.describe( 'Field Type > Radio', () => {
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

	test( 'should create a radio field and select an option', async ( {
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
		await fieldLabel.fill( 'Test Radio' );

		// Select radio type
		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'radio' );

		// Add choices
		const choicesTextarea = page.locator(
			'textarea[id^="acf_fields-field_"][id$="-choices"]'
		);
		await choicesTextarea.fill( 'small : Small\nmedium : Medium\nlarge : Large' );

		// Publish
		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create test post
		const post = await requestUtils.createPost( {
			title: 'Radio Field Test Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Select "Medium" option
		const mediumRadio = page.locator(
			'.acf-field[data-name="test_radio"] input[type="radio"][value="medium"]'
		);
		await mediumRadio.check();

		// Verify it's checked
		await expect( mediumRadio ).toBeChecked();

		// Verify other options are not checked
		const smallRadio = page.locator(
			'.acf-field[data-name="test_radio"] input[type="radio"][value="small"]'
		);
		await expect( smallRadio ).not.toBeChecked();

		// Preview and verify
		const previewPage = await editor.openPreviewPage();

		const radioOutput = previewPage.locator( '#scf-test-test_radio' );
		await expect( radioOutput ).toBeVisible();
		await expect( radioOutput ).toContainText( 'medium' );

		await previewPage.close();
	} );

	test( 'should support other option', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Create field group with "other" option
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'Radio Other Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Radio With Other' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'radio' );

		const choicesTextarea = page.locator(
			'textarea[id^="acf_fields-field_"][id$="-choices"]'
		);
		await choicesTextarea.fill( 'yes : Yes\nno : No' );

		// Enable "other" option
		await toggleFieldSetting( page, '.acf-field-setting-other_choice', true );

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create post
		const post = await requestUtils.createPost( {
			title: 'Radio Other Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// The "other" option should be present
		const otherRadio = page.locator(
			'.acf-field[data-name="radio_with_other"] input[type="radio"][value="other"]'
		);
		await expect( otherRadio ).toBeVisible();

		// Select "other" and enter custom value
		await otherRadio.check();

		const otherInput = page.locator(
			'.acf-field[data-name="radio_with_other"] input[type="text"]'
		);
		if ( await otherInput.isVisible() ) {
			await otherInput.fill( 'Maybe' );
		}
	} );
} );
