/**
 * E2E tests for the Button Group field type.
 *
 * Tests the Button Group field which provides a group of buttons
 * for selecting a single value (styled radio buttons).
 */
const { test, expect } = require( './fixtures' );
const {
	PLUGIN_SLUG,
	deleteFieldGroups,
	waitForMetaBoxes,
} = require( './field-helpers' );

const TEST_PLUGIN_SLUG = 'scf-test-plugin-all-field-types';
const FIELD_GROUP_LABEL = 'Button Group Field Test';

test.describe( 'Field Type > Button Group', () => {
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

	test( 'should create a button group field and select an option', async ( {
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
		await fieldLabel.fill( 'Test Button Group' );

		// Select button_group type
		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'button_group' );

		// Add choices
		const choicesTextarea = page.locator(
			'textarea[id^="acf_fields-field_"][id$="-choices"]'
		);
		await choicesTextarea.fill( 'left : Left\ncenter : Center\nright : Right' );

		// Publish
		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create test post
		const post = await requestUtils.createPost( {
			title: 'Button Group Test Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Click "Center" button
		const centerButton = page.locator(
			'.acf-field[data-name="test_button_group"] label:has-text("Center")'
		);
		await centerButton.click();

		// Verify it's selected (the input should be checked)
		const centerInput = page.locator(
			'.acf-field[data-name="test_button_group"] input[value="center"]'
		);
		await expect( centerInput ).toBeChecked();

		// Preview and verify
		const previewPage = await editor.openPreviewPage();

		const buttonGroupOutput = previewPage.locator(
			'#scf-test-test_button_group'
		);
		await expect( buttonGroupOutput ).toBeVisible();
		await expect( buttonGroupOutput ).toContainText( 'center' );

		await previewPage.close();
	} );

	test( 'should allow changing selection', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Create field group
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'Button Group Change Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Changeable Buttons' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'button_group' );

		const choicesTextarea = page.locator(
			'textarea[id^="acf_fields-field_"][id$="-choices"]'
		);
		await choicesTextarea.fill( 'on : On\noff : Off' );

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create post
		const post = await requestUtils.createPost( {
			title: 'Button Group Change Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Select "On" - button group uses label elements that wrap inputs
		// Use getByRole or getByText for better reliability
		const onButton = page.locator(
			'.acf-field[data-name="changeable_buttons"]'
		).getByText( 'On', { exact: true } );
		await onButton.click();

		const onInput = page.locator(
			'.acf-field[data-name="changeable_buttons"] input[value="on"]'
		);
		await expect( onInput ).toBeChecked();

		// Change to "Off"
		const offButton = page.locator(
			'.acf-field[data-name="changeable_buttons"]'
		).getByText( 'Off', { exact: true } );
		await offButton.click();

		const offInput = page.locator(
			'.acf-field[data-name="changeable_buttons"] input[value="off"]'
		);
		await expect( offInput ).toBeChecked();

		// "On" should no longer be checked
		await expect( onInput ).not.toBeChecked();
	} );
} );
