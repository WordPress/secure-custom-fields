/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const PLUGIN_SLUG = 'secure-custom-fields';
const TEST_PLUGIN_SLUG = 'scf-test-plugin-get-field-comment-title';
const FIELD_GROUP_LABEL = 'Comment Details';
const FIELD_LABEL = 'Comment Title';

test.describe( 'Field Type > Text', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( PLUGIN_SLUG );
		await requestUtils.activatePlugin( TEST_PLUGIN_SLUG );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deactivatePlugin( PLUGIN_SLUG );
		await requestUtils.deactivatePlugin( TEST_PLUGIN_SLUG );
		await requestUtils.deleteAllPosts();
	} );

	test.beforeEach( async ( { page, admin } ) => {
		await deleteFieldGroups( page, admin );
	} );

	test( 'should create a text field for comments, add content to it, and verify it displays on the frontend', async ( {
		page,
		admin,
		editor,
		requestUtils,
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
		await fieldLabel.fill( FIELD_LABEL );
		// The field name is generated automatically.

		// Select field type as text (it's default, but let's be explicit).
		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'text' );

		// Set comment as post type.
		await page.selectOption(
			'select[id^="acf_field_group-location-group_0-rule_0-param"]',
			'comment'
		);
		await page.selectOption(
			'select[id^="acf_field_group-location-group_0-rule_0-operator"]',
			'=='
		);
		await page.selectOption(
			'select[id^="acf_field_group-location-group_0-rule_0-value"]',
			'all'
		);

		// Submit form.
		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		// Verify success message.
		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();
		await expect( successNotice ).toContainText( 'Field group published' );

		// Verify field group appears in the list.
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const fieldGroupRow = page.locator(
			`tr:has-text("${ FIELD_GROUP_LABEL }")`
		);
		await expect( fieldGroupRow ).toBeVisible();

		const post = await requestUtils.createPost( {
			title: 'Movie 1',
			status: 'publish',
			showWelcomeGuide: false,
		} );

		await admin.editPost( post.id );

		const previewPage = await editor.openPreviewPage();

		await previewPage.waitForSelector(
			'.acf-field[data-name="comment_title"] input'
		);
		await previewPage.fill(
			'.acf-field[data-name="comment_title"] input',
			'Awesome movie'
		);

		await previewPage.fill( 'textarea#comment', 'This is a test comment' );
		await previewPage.click( 'input#submit' );

		// Verify the custom field value appears in the comment
		await previewPage.waitForSelector( '#scf-test-comment-title' );
		await expect(
			previewPage.locator( '#scf-test-comment-title' )
		).toContainText( 'Comment title: Awesome movie' );
	} );
} );

/**
 * Helper function to delete the field group
 */
async function deleteFieldGroups( page, admin ) {
	await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );

	// Find and select the field group row
	const allFieldGroupsCheckbox = page.locator( 'input#cb-select-all-1' );

	if ( await allFieldGroupsCheckbox.isVisible() ) {
		await allFieldGroupsCheckbox.check();
		// Use bulk actions to trash the field group
		await page.selectOption( '#bulk-action-selector-bottom', 'trash' );
		await page.click( '#doaction2' );

		// Verify deletion success message
		const deleteMessage = page.locator( '.updated.notice' );
		await expect( deleteMessage ).toBeVisible( { timeout: 5000 } );
		await expect( deleteMessage ).toContainText( 'moved to the Trash' );

		await emptyTrash( page, admin );
	}
}


/**
 * Helper function to empty trash
 */
async function emptyTrash( page, admin ) {
	await admin.visitAdminPage(
		'edit.php',
		'post_status=trash&post_type=acf-field-group'
	);
	const emptyTrashButton = page.locator(
		'.tablenav.bottom input[name="delete_all"][value="Empty Trash"]'
	);
	await emptyTrashButton.waitFor( { state: 'visible' } );
	await emptyTrashButton.click();

	// Verify success notice
	const successNotice = page.locator( '.notice.updated p' );
	await expect( successNotice ).toBeVisible();
	await expect( successNotice ).toHaveText( /permanently deleted/ );
}