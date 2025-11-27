/**
 * WordPress dependencies
 */
const { test, expect } = require( './fixtures' );

const PLUGIN_SLUG = 'secure-custom-fields';
const TEST_PLUGIN_SLUG = 'scf-test-plugin-get-repeater-field';
const FIELD_GROUP_LABEL = 'Movie Repeater Details';
const REPEATER_FIELD_LABEL = 'Actors';

test.describe( 'Field Type > Repeater', () => {
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

    test( 'should create a repeater field with subfields and verify it in admin', async ( {
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

        // Add repeater field
        const fieldType = page.locator(
            'select[id^="acf_fields-field_"][id$="-type"]'
        );
        await fieldType.selectOption( 'repeater' );

        // Set repeater field label
        const fieldLabel = page.locator(
            'input[id^="acf_fields-field_"][id$="-label"]'
        );
        await fieldLabel.fill( REPEATER_FIELD_LABEL );
        
        // Find and click the "Add Field" button inside the repeater's sub fields section
        const addSubFieldButton = page.locator('.acf-field-setting-sub_fields a.add-first-field')
        await addSubFieldButton.click();
        
        // Wait for the subfield to be added and set properties
        const subFieldLabel = page.locator( 
            '.acf-field-object.acf-field-object-text input.field-label' 
        ).last();
        await subFieldLabel.waitFor();
        await subFieldLabel.fill( 'Actor Name' );
        
        // Add another subfield
		const addAnotherSubFieldButton = page.locator(
			'.acf-field-setting-sub_fields .acf-is-subfields a.add-field.acf-btn-secondary'
		);
		await addAnotherSubFieldButton.click();
        
        // Wait for the second subfield to be added
        const secondSubFieldLabel = page.locator( 
            '.acf-field-object.acf-field-object-text input.field-label' 
        ).last();
        await secondSubFieldLabel.waitFor();
        await secondSubFieldLabel.fill( 'Actor Email' );

        // Change the second subfield type from text to email
        const secondSubFieldType = page.locator(
            '.acf-field-object'
        ).last().locator('select.field-type');
        await secondSubFieldType.selectOption('email');

        // Submit form
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

        // Create a new post
        const post = await requestUtils.createPost( {
            title: 'Movie with Actors',
            status: 'draft',
            showWelcomeGuide: false,
        } );

        // Navigate to edit post page
        await admin.editPost( post.id );

        // Wait for meta boxes to load and expand the panel if collapsed.
        // @see https://github.com/WordPress/gutenberg/issues/72185
        await page.waitForSelector( '.acf-postbox', { state: 'attached' } );
        await page.evaluate( () => {
            document.querySelectorAll( 'button' ).forEach( ( btn ) => {
                if ( btn.textContent?.includes( 'Meta Boxes' ) && btn.getAttribute( 'aria-expanded' ) === 'false' ) {
                    btn.dispatchEvent( new MouseEvent( 'click', { bubbles: true } ) );
                }
            } );
        } );

        // Add a repeater row - using the correct selector matching the actual DOM element
        const addRowButton = page.locator(
            'a.acf-button.acf-repeater-add-row[data-event="add-row"]'
        );
        await addRowButton.click();

        // Fill in the fields in the repeater
        const actorNameField = page.locator(
            '.acf-field[data-name="actor_name"] input[type="text"]'
        ).first();
        await actorNameField.fill( 'Morgan Freeman' );

        const characterEmailField = page.locator(
            '.acf-field[data-name="actor_email"] input[type="email"]'
        ).first();
        await characterEmailField.fill( 'red@shawshank.com' );

        // Add another row
        await addRowButton.click();

        // Fill in the second row
        const actorNameField2 = page.locator(
            '.acf-field[data-name="actor_name"] input[type="text"]'
        ).nth(1);
        await actorNameField2.fill( 'Tim Robbins' );

        const characterEmailField2 = page.locator(
            '.acf-field[data-name="actor_email"] input[type="email"]'
        ).nth(1);
        await characterEmailField2.fill( 'andy@shawshank.com' );

        // Verify the movie title is displayed
		const previewPage = await editor.openPreviewPage();

		// Check for repeater field output
		const actorsList = previewPage.locator('#scf-test-actors');
		await expect(actorsList).toBeVisible();

		// Check the heading
		await expect(actorsList.locator('h3')).toContainText('Movie Cast');
		// Verify the first actor's information
		const firstActorItem = actorsList.locator('li').nth(0);
		await expect(firstActorItem.locator('.actor-name')).toContainText('Actor: Morgan Freeman');
		await expect(firstActorItem.locator('.actor-email')).toContainText('Email: red@shawshank.com');

		// Verify the second actor's information
		const secondActorItem = actorsList.locator('li').nth(1);
		await expect(secondActorItem.locator('.actor-name')).toContainText('Actor: Tim Robbins');
		await expect(secondActorItem.locator('.actor-email')).toContainText('Email: andy@shawshank.com');

		// Close the preview tab
		await previewPage.close();
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