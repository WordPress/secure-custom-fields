/**
 * E2E tests for Conditional Logic functionality.
 *
 * Tests show/hide based on field values, multi-condition combinations,
 * nested conditional logic, and cross-field dependencies.
 */
const { test, expect } = require( './fixtures' );
const {
	PLUGIN_SLUG,
	deleteFieldGroups,
	waitForMetaBoxes,
	toggleFieldSetting,
	addFieldChoices,
} = require( './field-helpers' );

// Constants
const DEFAULT_TIMEOUT = 5000;

test.describe( 'Conditional Logic', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( PLUGIN_SLUG );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deactivatePlugin( PLUGIN_SLUG );
		await requestUtils.deleteAllPosts();
	} );

	test.beforeEach( async ( { page, admin } ) => {
		await deleteFieldGroups( page, admin );
	} );

	test.describe( 'Basic Show/Hide', () => {
		test( 'should show field when condition is met', async ( {
			page,
			admin,
			requestUtils,
		} ) => {
			// Create field group with conditional logic
			await createConditionalFieldGroup( page, admin, {
				triggerField: {
					label: 'Show Details',
					type: 'true_false',
				},
				conditionalField: {
					label: 'Details',
					type: 'text',
					condition: {
						field: 'show_details',
						operator: '==',
						value: '1',
					},
				},
			} );

			// Create and edit a post
			const post = await requestUtils.createPost( {
				title: 'Conditional Show Post',
				status: 'draft',
			} );

			await admin.editPost( post.id );
			await waitForMetaBoxes( page );

			// Initially, the conditional field should be hidden
			const detailsField = page.locator( '.acf-field[data-name="details"]' );
			await expect( detailsField ).not.toBeVisible();

			// Enable the trigger field (true/false toggle)
			const triggerToggle = page.locator( '.acf-field[data-name="show_details"] .acf-switch, .acf-field[data-name="show_details"] input[type="checkbox"]' );
			await triggerToggle.click( { force: true } );

			// Wait for conditional logic to process
			await page.waitForTimeout( 500 );

			// Now the conditional field should be visible
			await expect( detailsField ).toBeVisible();
		} );

		test( 'should hide field when condition is not met', async ( {
			page,
			admin,
			requestUtils,
		} ) => {
			// Create field group with conditional logic
			await createConditionalFieldGroup( page, admin, {
				triggerField: {
					label: 'Enable Feature',
					type: 'true_false',
				},
				conditionalField: {
					label: 'Feature Settings',
					type: 'text',
					condition: {
						field: 'enable_feature',
						operator: '==',
						value: '1',
					},
				},
			} );

			// Create and edit a post
			const post = await requestUtils.createPost( {
				title: 'Conditional Hide Post',
				status: 'draft',
			} );

			await admin.editPost( post.id );
			await waitForMetaBoxes( page );

			// Enable the trigger first
			const triggerToggle = page.locator( '.acf-field[data-name="enable_feature"] .acf-switch, .acf-field[data-name="enable_feature"] input[type="checkbox"]' );
			await triggerToggle.click( { force: true } );
			await page.waitForTimeout( 500 );

			// Field should be visible
			const featureField = page.locator( '.acf-field[data-name="feature_settings"]' );
			await expect( featureField ).toBeVisible();

			// Disable the trigger
			await triggerToggle.click( { force: true } );
			await page.waitForTimeout( 500 );

			// Field should be hidden again
			await expect( featureField ).not.toBeVisible();
		} );
	} );

	test.describe( 'Select Field Conditions', () => {
		test( 'should show field based on select value', async ( {
			page,
			admin,
			requestUtils,
		} ) => {
			// Create field group with select-based conditional logic
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
			const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
			await addNewButton.click();

			await page.waitForSelector( '#title' );
			await page.fill( '#title', 'Select Conditional Group' );

			// First field: Select
			const fieldLabel1 = page.locator( 'input[id^="acf_fields-field_"][id$="-label"]' );
			await fieldLabel1.fill( 'Content Type' );

			const fieldType1 = page.locator( 'select[id^="acf_fields-field_"][id$="-type"]' );
			await fieldType1.selectOption( 'select' );
			await page.waitForTimeout( 500 );

			// Add choices
			await addFieldChoices( page, [
				'text : Text Content',
				'video : Video Content',
				'gallery : Gallery Content',
			] );

			// Add second field
			const addFieldButton = page.locator( '#acf-field-group-fields a.acf-btn-secondary.add-field' );
			await addFieldButton.click();
			await page.waitForTimeout( 500 );

			// Second field: Video URL (conditional on content_type == video)
			const fieldLabel2 = page.locator( 'input[id^="acf_fields-field_"][id$="-label"]' ).last();
			await fieldLabel2.fill( 'Video URL' );

			const fieldType2 = page.locator( 'select[id^="acf_fields-field_"][id$="-type"]' ).last();
			await fieldType2.selectOption( 'url' );
			await page.waitForTimeout( 500 );

			// Enable conditional logic on the second field
			const secondFieldObject = page.locator( '.acf-field-object' ).last();
			await enableConditionalLogic( page, secondFieldObject, {
				fieldName: 'content_type',
				operator: '==',
				value: 'video',
			} );

			// Publish
			const publishButton = page.locator( 'button.acf-btn.acf-publish[type="submit"]' );
			await publishButton.click();

			const successNotice = page.locator( '.updated.notice' );
			await expect( successNotice ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );

			// Create and test
			const post = await requestUtils.createPost( {
				title: 'Select Condition Post',
				status: 'draft',
			} );

			await admin.editPost( post.id );
			await waitForMetaBoxes( page );

			// Initially video URL should be hidden
			const videoField = page.locator( '.acf-field[data-name="video_url"]' );
			await expect( videoField ).not.toBeVisible();

			// Select 'video' option
			const selectField = page.locator( '.acf-field[data-name="content_type"] select' );
			await selectField.selectOption( 'video' );
			await page.waitForTimeout( 500 );

			// Video URL should now be visible
			await expect( videoField ).toBeVisible();

			// Select a different option
			await selectField.selectOption( 'text' );
			await page.waitForTimeout( 500 );

			// Video URL should be hidden again
			await expect( videoField ).not.toBeVisible();
		} );
	} );

	test.describe( 'Radio Button Conditions', () => {
		test( 'should show different fields based on radio selection', async ( {
			page,
			admin,
			requestUtils,
		} ) => {
			// Create field group with radio-based conditional logic
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
			const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
			await addNewButton.click();

			await page.waitForSelector( '#title' );
			await page.fill( '#title', 'Radio Conditional Group' );

			// First field: Radio
			const fieldLabel1 = page.locator( 'input[id^="acf_fields-field_"][id$="-label"]' );
			await fieldLabel1.fill( 'Contact Method' );

			const fieldType1 = page.locator( 'select[id^="acf_fields-field_"][id$="-type"]' );
			await fieldType1.selectOption( 'radio' );
			await page.waitForTimeout( 500 );

			// Add choices
			await addFieldChoices( page, [
				'email : Email',
				'phone : Phone',
			] );

			// Add email field
			let addFieldButton = page.locator( '#acf-field-group-fields a.acf-btn-secondary.add-field' );
			await addFieldButton.click();
			await page.waitForTimeout( 500 );

			const emailLabel = page.locator( 'input[id^="acf_fields-field_"][id$="-label"]' ).last();
			await emailLabel.fill( 'Email Address' );

			const emailType = page.locator( 'select[id^="acf_fields-field_"][id$="-type"]' ).last();
			await emailType.selectOption( 'email' );
			await page.waitForTimeout( 500 );

			// Enable conditional logic for email field
			const emailFieldObject = page.locator( '.acf-field-object' ).last();
			await enableConditionalLogic( page, emailFieldObject, {
				fieldName: 'contact_method',
				operator: '==',
				value: 'email',
			} );

			// Add phone field
			addFieldButton = page.locator( '#acf-field-group-fields a.acf-btn-secondary.add-field' );
			await addFieldButton.click();
			await page.waitForTimeout( 500 );

			const phoneLabel = page.locator( 'input[id^="acf_fields-field_"][id$="-label"]' ).last();
			await phoneLabel.fill( 'Phone Number' );

			const phoneType = page.locator( 'select[id^="acf_fields-field_"][id$="-type"]' ).last();
			await phoneType.selectOption( 'text' );
			await page.waitForTimeout( 500 );

			// Enable conditional logic for phone field
			const phoneFieldObject = page.locator( '.acf-field-object' ).last();
			await enableConditionalLogic( page, phoneFieldObject, {
				fieldName: 'contact_method',
				operator: '==',
				value: 'phone',
			} );

			// Publish
			const publishButton = page.locator( 'button.acf-btn.acf-publish[type="submit"]' );
			await publishButton.click();

			const successNotice = page.locator( '.updated.notice' );
			await expect( successNotice ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );

			// Create and test
			const post = await requestUtils.createPost( {
				title: 'Radio Condition Post',
				status: 'draft',
			} );

			await admin.editPost( post.id );
			await waitForMetaBoxes( page );

			const emailField = page.locator( '.acf-field[data-name="email_address"]' );
			const phoneField = page.locator( '.acf-field[data-name="phone_number"]' );

			// Select email option
			const emailRadio = page.locator( '.acf-field[data-name="contact_method"] input[value="email"]' );
			await emailRadio.check( { force: true } );
			await page.waitForTimeout( 500 );

			// Email field should be visible, phone should be hidden
			await expect( emailField ).toBeVisible();
			await expect( phoneField ).not.toBeVisible();

			// Select phone option
			const phoneRadio = page.locator( '.acf-field[data-name="contact_method"] input[value="phone"]' );
			await phoneRadio.check( { force: true } );
			await page.waitForTimeout( 500 );

			// Phone field should be visible, email should be hidden
			await expect( phoneField ).toBeVisible();
			await expect( emailField ).not.toBeVisible();
		} );
	} );

	test.describe( 'Checkbox Conditions', () => {
		test( 'should show field when checkbox is checked', async ( {
			page,
			admin,
			requestUtils,
		} ) => {
			// Create field group with checkbox-based conditional logic
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
			const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
			await addNewButton.click();

			await page.waitForSelector( '#title' );
			await page.fill( '#title', 'Checkbox Conditional Group' );

			// First field: Checkbox
			const fieldLabel1 = page.locator( 'input[id^="acf_fields-field_"][id$="-label"]' );
			await fieldLabel1.fill( 'Features' );

			const fieldType1 = page.locator( 'select[id^="acf_fields-field_"][id$="-type"]' );
			await fieldType1.selectOption( 'checkbox' );
			await page.waitForTimeout( 500 );

			// Add choices
			await addFieldChoices( page, [
				'premium : Premium Features',
				'newsletter : Newsletter Signup',
			] );

			// Add premium details field
			const addFieldButton = page.locator( '#acf-field-group-fields a.acf-btn-secondary.add-field' );
			await addFieldButton.click();
			await page.waitForTimeout( 500 );

			const premiumLabel = page.locator( 'input[id^="acf_fields-field_"][id$="-label"]' ).last();
			await premiumLabel.fill( 'Premium Details' );

			// Enable conditional logic
			const premiumFieldObject = page.locator( '.acf-field-object' ).last();
			await enableConditionalLogic( page, premiumFieldObject, {
				fieldName: 'features',
				operator: '==',
				value: 'premium',
			} );

			// Publish
			const publishButton = page.locator( 'button.acf-btn.acf-publish[type="submit"]' );
			await publishButton.click();

			const successNotice = page.locator( '.updated.notice' );
			await expect( successNotice ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );

			// Create and test
			const post = await requestUtils.createPost( {
				title: 'Checkbox Condition Post',
				status: 'draft',
			} );

			await admin.editPost( post.id );
			await waitForMetaBoxes( page );

			const premiumField = page.locator( '.acf-field[data-name="premium_details"]' );

			// Initially hidden
			await expect( premiumField ).not.toBeVisible();

			// Check premium checkbox
			const premiumCheckbox = page.locator( '.acf-field[data-name="features"] input[value="premium"]' );
			await premiumCheckbox.check( { force: true } );
			await page.waitForTimeout( 500 );

			// Premium details should be visible
			await expect( premiumField ).toBeVisible();

			// Uncheck premium checkbox
			await premiumCheckbox.uncheck( { force: true } );
			await page.waitForTimeout( 500 );

			// Premium details should be hidden again
			await expect( premiumField ).not.toBeVisible();
		} );
	} );

	test.describe( 'Not Equal Conditions', () => {
		test( 'should show field when value is not equal', async ( {
			page,
			admin,
			requestUtils,
		} ) => {
			// Create field group with != condition
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
			const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
			await addNewButton.click();

			await page.waitForSelector( '#title' );
			await page.fill( '#title', 'Not Equal Conditional Group' );

			// First field: Select
			const fieldLabel1 = page.locator( 'input[id^="acf_fields-field_"][id$="-label"]' );
			await fieldLabel1.fill( 'Status' );

			const fieldType1 = page.locator( 'select[id^="acf_fields-field_"][id$="-type"]' );
			await fieldType1.selectOption( 'select' );
			await page.waitForTimeout( 500 );

			await addFieldChoices( page, [
				'active : Active',
				'archived : Archived',
				'draft : Draft',
			] );

			// Add edit button field (shown when NOT archived)
			const addFieldButton = page.locator( '#acf-field-group-fields a.acf-btn-secondary.add-field' );
			await addFieldButton.click();
			await page.waitForTimeout( 500 );

			const editLabel = page.locator( 'input[id^="acf_fields-field_"][id$="-label"]' ).last();
			await editLabel.fill( 'Edit Notes' );

			// Enable conditional logic with != operator
			const editFieldObject = page.locator( '.acf-field-object' ).last();
			await enableConditionalLogic( page, editFieldObject, {
				fieldName: 'status',
				operator: '!=',
				value: 'archived',
			} );

			// Publish
			const publishButton = page.locator( 'button.acf-btn.acf-publish[type="submit"]' );
			await publishButton.click();

			const successNotice = page.locator( '.updated.notice' );
			await expect( successNotice ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );

			// Create and test
			const post = await requestUtils.createPost( {
				title: 'Not Equal Condition Post',
				status: 'draft',
			} );

			await admin.editPost( post.id );
			await waitForMetaBoxes( page );

			const editField = page.locator( '.acf-field[data-name="edit_notes"]' );
			const statusSelect = page.locator( '.acf-field[data-name="status"] select' );

			// Select active - edit field should be visible (active != archived)
			await statusSelect.selectOption( 'active' );
			await page.waitForTimeout( 500 );
			await expect( editField ).toBeVisible();

			// Select archived - edit field should be hidden (archived == archived)
			await statusSelect.selectOption( 'archived' );
			await page.waitForTimeout( 500 );
			await expect( editField ).not.toBeVisible();

			// Select draft - edit field should be visible (draft != archived)
			await statusSelect.selectOption( 'draft' );
			await page.waitForTimeout( 500 );
			await expect( editField ).toBeVisible();
		} );
	} );

	test.describe( 'Empty/Not Empty Conditions', () => {
		test( 'should show field when another field is not empty', async ( {
			page,
			admin,
			requestUtils,
		} ) => {
			// Create field group with empty/not empty condition
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
			const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
			await addNewButton.click();

			await page.waitForSelector( '#title' );
			await page.fill( '#title', 'Empty Check Conditional Group' );

			// First field: Text
			const fieldLabel1 = page.locator( 'input[id^="acf_fields-field_"][id$="-label"]' );
			await fieldLabel1.fill( 'Title Input' );

			// Add description field (shown when title is not empty)
			const addFieldButton = page.locator( '#acf-field-group-fields a.acf-btn-secondary.add-field' );
			await addFieldButton.click();
			await page.waitForTimeout( 500 );

			const descLabel = page.locator( 'input[id^="acf_fields-field_"][id$="-label"]' ).last();
			await descLabel.fill( 'Description' );

			const descType = page.locator( 'select[id^="acf_fields-field_"][id$="-type"]' ).last();
			await descType.selectOption( 'textarea' );
			await page.waitForTimeout( 500 );

			// Enable conditional logic with != empty
			const descFieldObject = page.locator( '.acf-field-object' ).last();
			await enableConditionalLogic( page, descFieldObject, {
				fieldName: 'title_input',
				operator: '!=empty',
				value: '',
			} );

			// Publish
			const publishButton = page.locator( 'button.acf-btn.acf-publish[type="submit"]' );
			await publishButton.click();

			const successNotice = page.locator( '.updated.notice' );
			await expect( successNotice ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );

			// Create and test
			const post = await requestUtils.createPost( {
				title: 'Empty Check Post',
				status: 'draft',
			} );

			await admin.editPost( post.id );
			await waitForMetaBoxes( page );

			const descField = page.locator( '.acf-field[data-name="description"]' );
			const titleInput = page.locator( '.acf-field[data-name="title_input"] input[type="text"]' );

			// Initially hidden (title is empty)
			await expect( descField ).not.toBeVisible();

			// Type something in title
			await titleInput.fill( 'Some title' );
			await titleInput.blur();
			await page.waitForTimeout( 500 );

			// Description should be visible
			await expect( descField ).toBeVisible();

			// Clear title
			await titleInput.fill( '' );
			await titleInput.blur();
			await page.waitForTimeout( 500 );

			// Description should be hidden again
			await expect( descField ).not.toBeVisible();
		} );
	} );

	test.describe( 'Multiple Conditions (AND)', () => {
		test( 'should require all conditions to be met', async ( {
			page,
			admin,
			requestUtils,
		} ) => {
			// This test verifies AND logic between conditions in the same group
			// Create field group with multiple conditions
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
			const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
			await addNewButton.click();

			await page.waitForSelector( '#title' );
			await page.fill( '#title', 'Multi Condition AND Group' );

			// First field: True/False - Has Permission
			const fieldLabel1 = page.locator( 'input[id^="acf_fields-field_"][id$="-label"]' );
			await fieldLabel1.fill( 'Has Permission' );

			const fieldType1 = page.locator( 'select[id^="acf_fields-field_"][id$="-type"]' );
			await fieldType1.selectOption( 'true_false' );
			await page.waitForTimeout( 500 );

			// Second field: True/False - Is Active
			let addFieldButton = page.locator( '#acf-field-group-fields a.acf-btn-secondary.add-field' );
			await addFieldButton.click();
			await page.waitForTimeout( 500 );

			const fieldLabel2 = page.locator( 'input[id^="acf_fields-field_"][id$="-label"]' ).last();
			await fieldLabel2.fill( 'Is Active' );

			const fieldType2 = page.locator( 'select[id^="acf_fields-field_"][id$="-type"]' ).last();
			await fieldType2.selectOption( 'true_false' );
			await page.waitForTimeout( 500 );

			// Third field: Sensitive Data (requires both conditions)
			addFieldButton = page.locator( '#acf-field-group-fields a.acf-btn-secondary.add-field' );
			await addFieldButton.click();
			await page.waitForTimeout( 500 );

			const fieldLabel3 = page.locator( 'input[id^="acf_fields-field_"][id$="-label"]' ).last();
			await fieldLabel3.fill( 'Sensitive Data' );
			await page.waitForTimeout( 500 );

			// Enable conditional logic with multiple conditions (AND)
			const sensitiveFieldObject = page.locator( '.acf-field-object' ).last();
			await enableMultipleConditions( page, sensitiveFieldObject, [
				{ fieldName: 'has_permission', operator: '==', value: '1' },
				{ fieldName: 'is_active', operator: '==', value: '1' },
			] );

			// Publish
			const publishButton = page.locator( 'button.acf-btn.acf-publish[type="submit"]' );
			await publishButton.click();

			const successNotice = page.locator( '.updated.notice' );
			await expect( successNotice ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );

			// Create and test
			const post = await requestUtils.createPost( {
				title: 'Multi Condition AND Post',
				status: 'draft',
			} );

			await admin.editPost( post.id );
			await waitForMetaBoxes( page );

			const sensitiveField = page.locator( '.acf-field[data-name="sensitive_data"]' );
			const permissionToggle = page.locator( '.acf-field[data-name="has_permission"] .acf-switch, .acf-field[data-name="has_permission"] input[type="checkbox"]' );
			const activeToggle = page.locator( '.acf-field[data-name="is_active"] .acf-switch, .acf-field[data-name="is_active"] input[type="checkbox"]' );

			// Initially hidden (neither condition met)
			await expect( sensitiveField ).not.toBeVisible();

			// Enable only permission - still hidden
			await permissionToggle.click( { force: true } );
			await page.waitForTimeout( 500 );
			await expect( sensitiveField ).not.toBeVisible();

			// Enable active as well - now visible (both conditions met)
			await activeToggle.click( { force: true } );
			await page.waitForTimeout( 500 );
			await expect( sensitiveField ).toBeVisible();

			// Disable permission - hidden again
			await permissionToggle.click( { force: true } );
			await page.waitForTimeout( 500 );
			await expect( sensitiveField ).not.toBeVisible();
		} );
	} );

	test.describe( 'Multiple Conditions (OR)', () => {
		test( 'should show field when any OR condition is met', async ( {
			page,
			admin,
			requestUtils,
		} ) => {
			// This test verifies OR logic between rule groups
			// Create field group with multiple rule groups (OR)
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
			const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
			await addNewButton.click();

			await page.waitForSelector( '#title' );
			await page.fill( '#title', 'Multi Condition OR Group' );

			// First field: Select - User Type
			const fieldLabel1 = page.locator( 'input[id^="acf_fields-field_"][id$="-label"]' );
			await fieldLabel1.fill( 'User Type' );

			const fieldType1 = page.locator( 'select[id^="acf_fields-field_"][id$="-type"]' );
			await fieldType1.selectOption( 'select' );
			await page.waitForTimeout( 500 );

			// Add choices for user type
			await addFieldChoices( page, [
				'admin : Administrator',
				'editor : Editor',
				'subscriber : Subscriber',
			] );

			// Second field: True/False - Override Access
			let addFieldButton = page.locator( '#acf-field-group-fields a.acf-btn-secondary.add-field' );
			await addFieldButton.click();
			await page.waitForTimeout( 500 );

			const fieldLabel2 = page.locator( 'input[id^="acf_fields-field_"][id$="-label"]' ).last();
			await fieldLabel2.fill( 'Override Access' );

			const fieldType2 = page.locator( 'select[id^="acf_fields-field_"][id$="-type"]' ).last();
			await fieldType2.selectOption( 'true_false' );
			await page.waitForTimeout( 500 );

			// Third field: Admin Panel (shown when user_type == admin OR override_access == true)
			addFieldButton = page.locator( '#acf-field-group-fields a.acf-btn-secondary.add-field' );
			await addFieldButton.click();
			await page.waitForTimeout( 500 );

			const fieldLabel3 = page.locator( 'input[id^="acf_fields-field_"][id$="-label"]' ).last();
			await fieldLabel3.fill( 'Admin Panel' );
			await page.waitForTimeout( 500 );

			// Enable conditional logic with OR (multiple rule groups)
			const adminFieldObject = page.locator( '.acf-field-object' ).last();
			await enableOrConditions( page, adminFieldObject, [
				// First rule group: user_type == admin
				[ { fieldName: 'user_type', operator: '==', value: 'admin' } ],
				// Second rule group: override_access == 1 (OR)
				[ { fieldName: 'override_access', operator: '==', value: '1' } ],
			] );

			// Publish
			const publishButton = page.locator( 'button.acf-btn.acf-publish[type="submit"]' );
			await publishButton.click();

			const successNotice = page.locator( '.updated.notice' );
			await expect( successNotice ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );

			// Create and test
			const post = await requestUtils.createPost( {
				title: 'Multi Condition OR Post',
				status: 'draft',
			} );

			await admin.editPost( post.id );
			await waitForMetaBoxes( page );

			const adminPanel = page.locator( '.acf-field[data-name="admin_panel"]' );
			const userTypeSelect = page.locator( '.acf-field[data-name="user_type"] select' );
			const overrideToggle = page.locator( '.acf-field[data-name="override_access"] .acf-switch, .acf-field[data-name="override_access"] input[type="checkbox"]' );

			// Initially hidden (neither condition met, user_type defaults to first option which is admin)
			// Select subscriber to start in a hidden state
			await userTypeSelect.selectOption( 'subscriber' );
			await page.waitForTimeout( 500 );
			await expect( adminPanel ).not.toBeVisible();

			// Select admin - should show (first OR condition met)
			await userTypeSelect.selectOption( 'admin' );
			await page.waitForTimeout( 500 );
			await expect( adminPanel ).toBeVisible();

			// Change to subscriber, enable override - should still show (second OR condition met)
			await userTypeSelect.selectOption( 'subscriber' );
			await page.waitForTimeout( 500 );
			await expect( adminPanel ).not.toBeVisible();

			await overrideToggle.click( { force: true } );
			await page.waitForTimeout( 500 );
			await expect( adminPanel ).toBeVisible();

			// Disable override with subscriber selected - should hide (neither condition met)
			await overrideToggle.click( { force: true } );
			await page.waitForTimeout( 500 );
			await expect( adminPanel ).not.toBeVisible();
		} );
	} );
} );

/**
 * Helper function to create a field group with conditional logic.
 */
async function createConditionalFieldGroup( page, admin, options ) {
	const { triggerField, conditionalField } = options;

	await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
	const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
	await addNewButton.click();

	await page.waitForSelector( '#title' );
	await page.fill( '#title', `${ triggerField.label } Conditional Group` );

	// Add trigger field
	const fieldLabel1 = page.locator( 'input[id^="acf_fields-field_"][id$="-label"]' );
	await fieldLabel1.fill( triggerField.label );

	if ( triggerField.type !== 'text' ) {
		const fieldType1 = page.locator( 'select[id^="acf_fields-field_"][id$="-type"]' );
		await fieldType1.selectOption( triggerField.type );
		await page.waitForTimeout( 500 );
	}

	// Add conditional field
	const addFieldButton = page.locator( '#acf-field-group-fields a.acf-btn-secondary.add-field' );
	await addFieldButton.click();
	await page.waitForTimeout( 500 );

	const fieldLabel2 = page.locator( 'input[id^="acf_fields-field_"][id$="-label"]' ).last();
	await fieldLabel2.fill( conditionalField.label );

	if ( conditionalField.type !== 'text' ) {
		const fieldType2 = page.locator( 'select[id^="acf_fields-field_"][id$="-type"]' ).last();
		await fieldType2.selectOption( conditionalField.type );
		await page.waitForTimeout( 500 );
	}

	// Enable conditional logic on the second field
	const secondFieldObject = page.locator( '.acf-field-object' ).last();
	await enableConditionalLogic( page, secondFieldObject, conditionalField.condition );

	// Publish
	const publishButton = page.locator( 'button.acf-btn.acf-publish[type="submit"]' );
	await publishButton.click();

	const successNotice = page.locator( '.updated.notice' );
	await expect( successNotice ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );
}

/**
 * Helper function to enable conditional logic on a field.
 */
async function enableConditionalLogic( page, fieldObject, condition ) {
	// Click on Conditional Logic tab
	const condLogicTab = fieldObject.locator( 'a.acf-tab-button' ).filter( { hasText: 'Conditional Logic' } ).first();
	if ( await condLogicTab.isVisible() ) {
		await condLogicTab.click();
		await page.waitForTimeout( 200 );
	}

	// Enable conditional logic toggle
	const condLogicToggle = fieldObject.locator( '.acf-field-setting-conditional_logic .acf-switch' );
	if ( await condLogicToggle.isVisible() ) {
		const isOn = ( await condLogicToggle.getAttribute( 'class' ) )?.includes( '-on' );
		if ( ! isOn ) {
			await condLogicToggle.click( { force: true } );
			await page.waitForTimeout( 500 );
		}
	}

	// Wait for the rule groups container to be visible
	const ruleGroups = fieldObject.locator( '.acf-field-setting-conditional_logic .rule-groups' );
	await ruleGroups.waitFor( { state: 'visible', timeout: DEFAULT_TIMEOUT } );

	// Select field using the condition-rule-field select
	const fieldSelect = ruleGroups.locator( 'select.condition-rule-field' ).first();
	await fieldSelect.waitFor( { state: 'visible', timeout: DEFAULT_TIMEOUT } );

	// The field name in condition uses underscore, but label uses spaces - try both
	// Support both 'field' and 'fieldName' properties for flexibility
	const fieldName = condition.field || condition.fieldName;
	const fieldLabel = fieldName.replace( /_/g, ' ' );
	try {
		await fieldSelect.selectOption( { label: new RegExp( fieldLabel, 'i' ) } );
	} catch {
		// Try selecting by value pattern if label doesn't work
		const options = await fieldSelect.locator( 'option' ).all();
		for ( const option of options ) {
			const text = await option.textContent();
			if ( text && text.toLowerCase().includes( fieldLabel.toLowerCase() ) ) {
				await fieldSelect.selectOption( { label: text } );
				break;
			}
		}
	}
	await page.waitForTimeout( 300 );

	// Select operator
	const operatorSelect = ruleGroups.locator( 'select.condition-rule-operator' ).first();
	await operatorSelect.selectOption( condition.operator );
	await page.waitForTimeout( 200 );

	// Set value (for operators that need it)
	if ( condition.value !== undefined && condition.operator !== '==empty' && condition.operator !== '!=empty' ) {
		const valueSelect = ruleGroups.locator( 'select.condition-rule-value' ).first();
		if ( await valueSelect.isVisible() ) {
			try {
				await valueSelect.selectOption( condition.value );
			} catch {
				// Try selecting by label if value doesn't work
				await valueSelect.selectOption( { label: condition.value } );
			}
		}
	}
	await page.waitForTimeout( 200 );
}

/**
 * Helper function to enable multiple conditional logic rules (AND).
 */
async function enableMultipleConditions( page, fieldObject, conditions ) {
	// Click on Conditional Logic tab
	const condLogicTab = fieldObject.locator( 'a.acf-tab-button' ).filter( { hasText: 'Conditional Logic' } ).first();
	if ( await condLogicTab.isVisible() ) {
		await condLogicTab.click();
		await page.waitForTimeout( 200 );
	}

	// Enable conditional logic toggle
	const condLogicToggle = fieldObject.locator( '.acf-field-setting-conditional_logic .acf-switch' ).first();
	if ( await condLogicToggle.isVisible() ) {
		const isOn = ( await condLogicToggle.getAttribute( 'class' ) )?.includes( '-on' );
		if ( ! isOn ) {
			await condLogicToggle.click( { force: true } );
			await page.waitForTimeout( 500 );
		}
	}

	// Wait for the rule groups container to be visible
	const ruleGroups = fieldObject.locator( '.acf-field-setting-conditional_logic .rule-groups' ).first();
	await ruleGroups.waitFor( { state: 'visible', timeout: DEFAULT_TIMEOUT } );

	for ( let i = 0; i < conditions.length; i++ ) {
		const condition = conditions[ i ];

		// For subsequent conditions, click "and" button to add new rule
		if ( i > 0 ) {
			const addRuleButton = ruleGroups.locator( 'a.add-conditional-rule' ).first();
			if ( await addRuleButton.isVisible() ) {
				await addRuleButton.click();
				await page.waitForTimeout( 300 );
			}
		}

		// Get the rows for conditions
		const rows = ruleGroups.locator( 'tr.rule' );
		const row = rows.nth( i );

		// Select field - support both 'field' and 'fieldName' properties
		const fieldName = condition.field || condition.fieldName;
		const fieldLabel = fieldName.replace( /_/g, ' ' );
		const fieldSelect = row.locator( 'select.condition-rule-field' );
		try {
			await fieldSelect.selectOption( { label: new RegExp( fieldLabel, 'i' ) } );
		} catch {
			// Try selecting by finding matching option
			const options = await fieldSelect.locator( 'option' ).all();
			for ( const option of options ) {
				const text = await option.textContent();
				if ( text && text.toLowerCase().includes( fieldLabel.toLowerCase() ) ) {
					await fieldSelect.selectOption( { label: text } );
					break;
				}
			}
		}
		await page.waitForTimeout( 200 );

		// Select operator
		const operatorSelect = row.locator( 'select.condition-rule-operator' );
		await operatorSelect.selectOption( condition.operator );
		await page.waitForTimeout( 200 );

		// Set value
		if ( condition.value !== undefined && condition.operator !== '==empty' && condition.operator !== '!=empty' ) {
			const valueSelect = row.locator( 'select.condition-rule-value' );
			if ( await valueSelect.isVisible() ) {
				try {
					await valueSelect.selectOption( condition.value );
				} catch {
					await valueSelect.selectOption( { label: condition.value } );
				}
			}
		}
	}
}

/**
 * Helper function to enable multiple conditional logic rule groups (OR).
 * Each rule group is an array of conditions that must all be true (AND within group).
 * Groups are combined with OR logic - if any group is satisfied, the field is shown.
 */
async function enableOrConditions( page, fieldObject, ruleGroups ) {
	// Click on Conditional Logic tab
	const condLogicTab = fieldObject.locator( 'a.acf-tab-button' ).filter( { hasText: 'Conditional Logic' } ).first();
	if ( await condLogicTab.isVisible() ) {
		await condLogicTab.click();
		await page.waitForTimeout( 200 );
	}

	// Enable conditional logic toggle
	const condLogicToggle = fieldObject.locator( '.acf-field-setting-conditional_logic .acf-switch' ).first();
	if ( await condLogicToggle.isVisible() ) {
		const isOn = ( await condLogicToggle.getAttribute( 'class' ) )?.includes( '-on' );
		if ( ! isOn ) {
			await condLogicToggle.click( { force: true } );
			await page.waitForTimeout( 500 );
		}
	}

	// Wait for the rule groups container to be visible
	const ruleGroupsContainer = fieldObject.locator( '.acf-field-setting-conditional_logic .rule-groups' ).first();
	await ruleGroupsContainer.waitFor( { state: 'visible', timeout: DEFAULT_TIMEOUT } );

	for ( let groupIndex = 0; groupIndex < ruleGroups.length; groupIndex++ ) {
		const group = ruleGroups[ groupIndex ];

		// For second+ groups, click "or" button to add new rule group
		if ( groupIndex > 0 ) {
			const addGroupButton = ruleGroupsContainer.locator( 'a.add-conditional-group' ).first();
			if ( await addGroupButton.isVisible() ) {
				await addGroupButton.click();
				await page.waitForTimeout( 500 );
			}
		}

		// Get the current rule group
		const groupElements = ruleGroupsContainer.locator( '.rule-group' );
		const currentGroup = groupElements.nth( groupIndex );

		for ( let ruleIndex = 0; ruleIndex < group.length; ruleIndex++ ) {
			const condition = group[ ruleIndex ];

			// For second+ rules in this group, click "and" button to add new rule
			if ( ruleIndex > 0 ) {
				const addRuleButton = currentGroup.locator( 'a.add-conditional-rule' ).first();
				if ( await addRuleButton.isVisible() ) {
					await addRuleButton.click();
					await page.waitForTimeout( 300 );
				}
			}

			// Get the rows for conditions in this group
			const rows = currentGroup.locator( 'tr.rule' );
			const row = rows.nth( ruleIndex );

			// Select field
			const fieldName = condition.field || condition.fieldName;
			const fieldLabel = fieldName.replace( /_/g, ' ' );
			const fieldSelect = row.locator( 'select.condition-rule-field' );
			try {
				await fieldSelect.selectOption( { label: new RegExp( fieldLabel, 'i' ) } );
			} catch {
				// Try selecting by finding matching option
				const options = await fieldSelect.locator( 'option' ).all();
				for ( const option of options ) {
					const text = await option.textContent();
					if ( text && text.toLowerCase().includes( fieldLabel.toLowerCase() ) ) {
						await fieldSelect.selectOption( { label: text } );
						break;
					}
				}
			}
			await page.waitForTimeout( 200 );

			// Select operator
			const operatorSelect = row.locator( 'select.condition-rule-operator' );
			await operatorSelect.selectOption( condition.operator );
			await page.waitForTimeout( 200 );

			// Set value
			if ( condition.value !== undefined && condition.operator !== '==empty' && condition.operator !== '!=empty' ) {
				const valueSelect = row.locator( 'select.condition-rule-value' );
				if ( await valueSelect.isVisible() ) {
					try {
						await valueSelect.selectOption( condition.value );
					} catch {
						await valueSelect.selectOption( { label: condition.value } );
					}
				}
			}
		}
	}
}
