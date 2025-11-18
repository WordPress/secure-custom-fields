/**
 * WordPress dependencies
 */
const { test, expect } = require( './fixtures' );

const PLUGIN_SLUG = 'secure-custom-fields';
const TEST_PLUGIN_SLUG = 'scf-test-plugin-get-field-term-title';
const FIELD_GROUP_LABEL = 'Term Details';
const FIELD_LABEL = 'Term Title';

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

    test( 'should create a text field for taxonomy terms and verify it on frontend', async ( {
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

        // Set taxonomy as location
        await page.selectOption(
            'select[id^="acf_field_group-location-group_0-rule_0-param"]',
            'taxonomy'
        );
        await page.selectOption(
            'select[id^="acf_field_group-location-group_0-rule_0-operator"]',
            '=='
        );
        await page.selectOption(
            'select[id^="acf_field_group-location-group_0-rule_0-value"]',
            'category'
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

        // Navigate to the default category term edit page
        await admin.visitAdminPage( 'term.php', 'taxonomy=category&tag_ID=1' );
        
        // Fill the custom field - using a more resilient selector that doesn't depend on exact data-name
        await page.waitForSelector( 'tr.acf-field label:has-text("Term Title")' );
        
        // Find the input associated with the Term Title field
        const termTitleField = page.locator( 'tr.acf-field:has(label:has-text("Term Title")) input[type="text"]' );
        await termTitleField.fill( 'Custom Term Value' );
        
        // Save the term - using the correct selector for the Update button
        await page.click( '.edit-tag-actions .button-primary' );
        
        // Verify success message
        const termUpdateNotice = page.locator( '.notice.notice-success' );
        await expect( termUpdateNotice ).toBeVisible();
        await expect( termUpdateNotice ).toContainText( 'Category updated' );

		// Create a post to use the category field on the post content through the plugin.
		await requestUtils.createPost( {
			title: 'Movie 1',
			status: 'publish',
			showWelcomeGuide: false,
		} );
        
        // Visit the category archive page
        await page.goto( '/?cat=1' );
        
        // Verify the custom field value appears on the frontend
        await page.waitForSelector( '#scf-test-term-title' );
        await expect(
            page.locator( '#scf-test-term-title' )
        ).toContainText( 'Term title: Custom Term Value' );
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