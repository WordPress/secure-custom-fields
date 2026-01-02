/**
 * E2E tests for Field Validation functionality.
 *
 * Tests required field validation, pattern validation,
 * custom validation rules, error message display, and fix-resubmit flow.
 */
const { test, expect } = require( './fixtures' );
const {
	PLUGIN_SLUG,
	deleteFieldGroups,
	waitForMetaBoxes,
	toggleFieldSetting,
} = require( './field-helpers' );

// Constants
const DEFAULT_TIMEOUT = 5000;

test.describe( 'Field Validation', () => {
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

	test.describe( 'Required Field Validation', () => {
		test( 'should show error when required text field is empty', async ( {
			page,
			admin,
			requestUtils,
		} ) => {
			// Create field group with required text field
			await createFieldGroupWithValidation( page, admin, {
				fieldLabel: 'Required Text',
				fieldType: 'text',
				required: true,
			} );

			// Create and edit a post
			const post = await requestUtils.createPost( {
				title: 'Validation Test Post',
				status: 'draft',
			} );

			await admin.editPost( post.id );
			await waitForMetaBoxes( page );

			// Try to publish without filling required field
			// First click opens the publish panel
			await page.click( 'button.editor-post-publish-panel__toggle' );
			// Then click the actual Publish button in the panel
			await page.click( 'button.editor-post-publish-button' );

			// Wait for validation - the form should show an error
			// SCF shows validation errors either inline or via alert
			const validationError = page.locator( '.acf-error-message, .acf-field.acf-error, .acf-notice.-error' );
			await expect( validationError.first() ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );
		} );

		test( 'should allow submission when required field is filled', async ( {
			page,
			admin,
			requestUtils,
		} ) => {
			// Create field group with required text field
			await createFieldGroupWithValidation( page, admin, {
				fieldLabel: 'Required Text',
				fieldType: 'text',
				required: true,
			} );

			// Create and edit a post
			const post = await requestUtils.createPost( {
				title: 'Validation Success Post',
				status: 'draft',
			} );

			await admin.editPost( post.id );
			await waitForMetaBoxes( page );

			// Fill the required field
			const textInput = page.locator( '.acf-field[data-name="required_text"] input[type="text"]' );
			await textInput.fill( 'Valid content' );

			// Click save/update
			await page.click( 'button.editor-post-save-draft, button.editor-post-publish-button' );

			// Wait for save to complete
			await page.waitForTimeout( 1000 );

			// Should not show validation errors
			const validationError = page.locator( '.acf-error-message, .acf-notice.-error' );
			await expect( validationError ).not.toBeVisible();
		} );

		test( 'should validate required select field', async ( {
			page,
			admin,
			requestUtils,
		} ) => {
			// Create field group with required select field
			await createFieldGroupWithValidation( page, admin, {
				fieldLabel: 'Required Select',
				fieldType: 'select',
				required: true,
				configureField: async () => {
					// Add choices
					const choicesTextarea = page.locator(
						'textarea[id^="acf_fields-field_"][id$="-choices"]'
					);
					await choicesTextarea.fill( 'option1 : Option 1\noption2 : Option 2' );

					// Enable "Allow Null" to allow empty selection - this is in the Validation tab
					// Navigate to Validation tab where allow_null setting is
					const validationTab = page.locator( '.acf-field-object .acf-tab-wrap a.acf-tab-button' ).filter( { hasText: 'Validation' } ).first();
					if ( await validationTab.isVisible() ) {
						await validationTab.click();
						await page.waitForTimeout( 200 );
					}

					// Enable Allow Null toggle
					const allowNullToggle = page.locator( '.acf-field-setting-allow_null .acf-switch' );
					await allowNullToggle.waitFor( { state: 'visible', timeout: 5000 } );
					const isOn = ( await allowNullToggle.getAttribute( 'class' ) )?.includes( '-on' );
					if ( ! isOn ) {
						await allowNullToggle.click();
						await page.waitForTimeout( 200 );
					}
				},
			} );

			// Create and edit a post
			const post = await requestUtils.createPost( {
				title: 'Select Validation Post',
				status: 'draft',
			} );

			await admin.editPost( post.id );
			await waitForMetaBoxes( page );

			// Try to publish without selecting an option
			await page.click( 'button.editor-post-publish-panel__toggle' );
			await page.click( 'button.editor-post-publish-button' );

			// Should show validation error
			const validationError = page.locator( '.acf-error-message, .acf-field.acf-error, .acf-notice.-error' );
			await expect( validationError.first() ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );
		} );

		test( 'should validate required checkbox field', async ( {
			page,
			admin,
			requestUtils,
		} ) => {
			// Create field group with required checkbox field
			await createFieldGroupWithValidation( page, admin, {
				fieldLabel: 'Required Checkbox',
				fieldType: 'checkbox',
				required: true,
				configureField: async () => {
					const choicesTextarea = page.locator(
						'textarea[id^="acf_fields-field_"][id$="-choices"]'
					);
					await choicesTextarea.fill( 'check1 : Check 1\ncheck2 : Check 2' );
				},
			} );

			// Create and edit a post
			const post = await requestUtils.createPost( {
				title: 'Checkbox Validation Post',
				status: 'draft',
			} );

			await admin.editPost( post.id );
			await waitForMetaBoxes( page );

			// Try to publish without checking any option
			await page.click( 'button.editor-post-publish-panel__toggle' );
			await page.click( 'button.editor-post-publish-button' );

			// Should show validation error
			const validationError = page.locator( '.acf-error-message, .acf-field.acf-error, .acf-notice.-error' );
			await expect( validationError.first() ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );
		} );
	} );

	test.describe( 'Number Validation', () => {
		test( 'should validate minimum value', async ( {
			page,
			admin,
			requestUtils,
		} ) => {
			// Create field group with number field and min value
			await createFieldGroupWithValidation( page, admin, {
				fieldLabel: 'Min Number',
				fieldType: 'number',
				configureField: async () => {
					// Click Validation tab to find min setting
					const validationTab = page.locator( '.acf-field-object .acf-tab-wrap a.acf-tab-button' ).filter( { hasText: 'Validation' } ).first();
					if ( await validationTab.isVisible() ) {
						await validationTab.click();
						await page.waitForTimeout( 200 );
					}

					const minInput = page.locator( '.acf-field-setting-min input[type="number"]' );
					await minInput.waitFor( { state: 'visible', timeout: 5000 } );
					await minInput.fill( '10' );
				},
			} );

			// Create and edit a post
			const post = await requestUtils.createPost( {
				title: 'Min Value Test Post',
				status: 'draft',
			} );

			await admin.editPost( post.id );
			await waitForMetaBoxes( page );

			// Enter a value below minimum
			const numberInput = page.locator( '.acf-field[data-name="min_number"] input[type="number"]' );
			await numberInput.fill( '5' );

			// Verify the min attribute was set correctly
			const minAttr = await numberInput.getAttribute( 'min' );
			expect( minAttr ).toBe( '10' );

			// The HTML5 validation should mark the input as invalid
			const inputElement = await numberInput.elementHandle();
			const isValid = await page.evaluate( ( el ) => el.checkValidity(), inputElement );
			expect( isValid ).toBe( false );
		} );

		test( 'should validate maximum value', async ( {
			page,
			admin,
			requestUtils,
		} ) => {
			// Create field group with number field and max value
			await createFieldGroupWithValidation( page, admin, {
				fieldLabel: 'Max Number',
				fieldType: 'number',
				configureField: async () => {
					const validationTab = page.locator( '.acf-field-object .acf-tab-wrap a.acf-tab-button' ).filter( { hasText: 'Validation' } ).first();
					if ( await validationTab.isVisible() ) {
						await validationTab.click();
						await page.waitForTimeout( 200 );
					}

					const maxInput = page.locator( '.acf-field-setting-max input[type="number"]' );
					await maxInput.waitFor( { state: 'visible', timeout: 5000 } );
					await maxInput.fill( '100' );
				},
			} );

			// Create and edit a post
			const post = await requestUtils.createPost( {
				title: 'Max Value Test Post',
				status: 'draft',
			} );

			await admin.editPost( post.id );
			await waitForMetaBoxes( page );

			// Enter a value above maximum
			const numberInput = page.locator( '.acf-field[data-name="max_number"] input[type="number"]' );
			await numberInput.fill( '150' );

			// Verify the max attribute was set correctly
			const maxAttr = await numberInput.getAttribute( 'max' );
			expect( maxAttr ).toBe( '100' );

			// The HTML5 validation should mark the input as invalid
			const inputElement = await numberInput.elementHandle();
			const isValid = await page.evaluate( ( el ) => el.checkValidity(), inputElement );
			expect( isValid ).toBe( false );
		} );
	} );

	test.describe( 'URL Validation', () => {
		test( 'should validate URL format', async ( {
			page,
			admin,
			requestUtils,
		} ) => {
			// Create field group with URL field
			await createFieldGroupWithValidation( page, admin, {
				fieldLabel: 'Website URL',
				fieldType: 'url',
			} );

			// Create and edit a post
			const post = await requestUtils.createPost( {
				title: 'URL Validation Post',
				status: 'draft',
			} );

			await admin.editPost( post.id );
			await waitForMetaBoxes( page );

			// Enter an invalid URL
			const urlInput = page.locator( '.acf-field[data-name="website_url"] input[type="url"]' );
			await urlInput.fill( 'not-a-valid-url' );

			// Check HTML5 validation
			const inputElement = await urlInput.elementHandle();
			const isValid = await page.evaluate( ( el ) => el.checkValidity(), inputElement );
			expect( isValid ).toBe( false );

			// Now enter a valid URL
			await urlInput.fill( 'https://example.com' );
			const isValidNow = await page.evaluate( ( el ) => el.checkValidity(), inputElement );
			expect( isValidNow ).toBe( true );
		} );
	} );

	test.describe( 'Email Validation', () => {
		test( 'should validate email format', async ( {
			page,
			admin,
			requestUtils,
		} ) => {
			// Create field group with email field
			await createFieldGroupWithValidation( page, admin, {
				fieldLabel: 'Contact Email',
				fieldType: 'email',
			} );

			// Create and edit a post
			const post = await requestUtils.createPost( {
				title: 'Email Validation Post',
				status: 'draft',
			} );

			await admin.editPost( post.id );
			await waitForMetaBoxes( page );

			// Enter an invalid email
			const emailInput = page.locator( '.acf-field[data-name="contact_email"] input[type="email"]' );
			await emailInput.fill( 'not-an-email' );

			// Check HTML5 validation
			const inputElement = await emailInput.elementHandle();
			const isValid = await page.evaluate( ( el ) => el.checkValidity(), inputElement );
			expect( isValid ).toBe( false );

			// Now enter a valid email
			await emailInput.fill( 'valid@example.com' );
			const isValidNow = await page.evaluate( ( el ) => el.checkValidity(), inputElement );
			expect( isValidNow ).toBe( true );
		} );
	} );

	test.describe( 'Character Limit Validation', () => {
		test( 'should enforce maxlength on text field', async ( {
			page,
			admin,
			requestUtils,
		} ) => {
			// Create field group with text field and maxlength
			await createFieldGroupWithValidation( page, admin, {
				fieldLabel: 'Limited Text',
				fieldType: 'text',
				configureField: async () => {
					// Navigate to Validation tab where maxlength setting is located
					const validationTab = page.locator( '.acf-field-object .acf-tab-wrap a.acf-tab-button' ).filter( { hasText: 'Validation' } );
					if ( await validationTab.isVisible() ) {
						await validationTab.click();
						await page.waitForTimeout( 200 );
					}
					const maxlengthInput = page.locator( '.acf-field-setting-maxlength input' );
					await maxlengthInput.waitFor( { state: 'visible', timeout: 5000 } );
					await maxlengthInput.fill( '10' );
				},
			} );

			// Create and edit a post
			const post = await requestUtils.createPost( {
				title: 'Maxlength Test Post',
				status: 'draft',
			} );

			await admin.editPost( post.id );
			await waitForMetaBoxes( page );

			// Verify the maxlength attribute was set correctly
			const textInput = page.locator( '.acf-field[data-name="limited_text"] input[type="text"]' );
			const maxlengthAttr = await textInput.getAttribute( 'maxlength' );
			expect( maxlengthAttr ).toBe( '10' );
		} );

		test( 'should enforce maxlength on textarea field', async ( {
			page,
			admin,
			requestUtils,
		} ) => {
			// Create field group with textarea field and maxlength
			await createFieldGroupWithValidation( page, admin, {
				fieldLabel: 'Limited Textarea',
				fieldType: 'textarea',
				configureField: async () => {
					// Navigate to Validation tab where maxlength setting is located
					const validationTab = page.locator( '.acf-field-object .acf-tab-wrap a.acf-tab-button' ).filter( { hasText: 'Validation' } );
					if ( await validationTab.isVisible() ) {
						await validationTab.click();
						await page.waitForTimeout( 200 );
					}
					const maxlengthInput = page.locator( '.acf-field-setting-maxlength input' );
					await maxlengthInput.waitFor( { state: 'visible', timeout: 5000 } );
					await maxlengthInput.fill( '100' );
				},
			} );

			// Create and edit a post
			const post = await requestUtils.createPost( {
				title: 'Textarea Maxlength Post',
				status: 'draft',
			} );

			await admin.editPost( post.id );
			await waitForMetaBoxes( page );

			// Verify the maxlength attribute was set correctly
			const textarea = page.locator( '.acf-field[data-name="limited_textarea"] textarea' );
			const maxlengthAttr = await textarea.getAttribute( 'maxlength' );
			expect( maxlengthAttr ).toBe( '100' );
		} );
	} );

	test.describe( 'Error Message Display', () => {
		test( 'should display custom error message for required field', async ( {
			page,
			admin,
			requestUtils,
		} ) => {
			// Create field group with required field
			await createFieldGroupWithValidation( page, admin, {
				fieldLabel: 'Important Field',
				fieldType: 'text',
				required: true,
			} );

			// Create and edit a post
			const post = await requestUtils.createPost( {
				title: 'Error Message Test Post',
				status: 'draft',
			} );

			await admin.editPost( post.id );
			await waitForMetaBoxes( page );

			// Try to submit without filling
			await page.click( 'button.editor-post-publish-panel__toggle' );
			await page.click( 'button.editor-post-publish-button' );

			// Wait for error to appear
			const errorField = page.locator( '.acf-field.acf-error, .acf-field[data-name="important_field"].acf-error' );
			await expect( errorField ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );

			// Error message should be visible
			const errorMessage = page.locator( '.acf-error-message, .acf-notice.-error' );
			await expect( errorMessage.first() ).toBeVisible();
		} );

		test( 'should clear error when field is fixed', async ( {
			page,
			admin,
			requestUtils,
		} ) => {
			// Create field group with required field
			await createFieldGroupWithValidation( page, admin, {
				fieldLabel: 'Fixable Field',
				fieldType: 'text',
				required: true,
			} );

			// Create and edit a post
			const post = await requestUtils.createPost( {
				title: 'Fix Error Test Post',
				status: 'draft',
			} );

			await admin.editPost( post.id );
			await waitForMetaBoxes( page );

			// Try to submit to trigger error
			await page.click( 'button.editor-post-publish-panel__toggle' );
			await page.click( 'button.editor-post-publish-button' );

			// Wait for error
			const errorField = page.locator( '.acf-field.acf-error' );
			await expect( errorField ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );

			// Fill the field to fix the error
			const textInput = page.locator( '.acf-field[data-name="fixable_field"] input[type="text"]' );
			await textInput.fill( 'Fixed value' );

			// Trigger blur to validate
			await textInput.blur();
			await page.waitForTimeout( 500 );

			// The field should no longer have the error class
			const fieldWithError = page.locator( '.acf-field[data-name="fixable_field"].acf-error' );
			await expect( fieldWithError ).not.toBeVisible();
		} );
	} );

	test.describe( 'Fix and Resubmit Flow', () => {
		test( 'should allow resubmission after fixing validation errors', async ( {
			page,
			admin,
			requestUtils,
		} ) => {
			// Create field group with multiple required fields
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
			const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
			await addNewButton.click();

			await page.waitForSelector( '#title' );
			await page.fill( '#title', 'Multi Field Validation Group' );

			// Add first field
			const fieldLabel1 = page.locator( 'input[id^="acf_fields-field_"][id$="-label"]' );
			await fieldLabel1.fill( 'Field One' );

			// Make it required via the Validation tab
			await toggleFieldSetting( page, '.acf-field-setting-required', true );

			// Publish
			const publishButton = page.locator( 'button.acf-btn.acf-publish[type="submit"]' );
			await publishButton.click();

			const successNotice = page.locator( '.updated.notice' );
			await expect( successNotice ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );

			// Create and edit a post
			const post = await requestUtils.createPost( {
				title: 'Resubmit Test Post',
				status: 'draft',
			} );

			await admin.editPost( post.id );
			await waitForMetaBoxes( page );

			// First submission attempt without filling required field
			await page.click( 'button.editor-post-publish-panel__toggle' );
			await page.click( 'button.editor-post-publish-button' );

			// Wait for validation error
			const validationError = page.locator( '.acf-error-message, .acf-field.acf-error, .acf-notice.-error' );
			await expect( validationError.first() ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );

			// Fill the required field
			const textInput = page.locator( '.acf-field[data-name="field_one"] input[type="text"]' );
			await textInput.fill( 'Now I am filled' );

			// Wait a moment for validation to clear
			await page.waitForTimeout( 500 );

			// The field should no longer have the error class after being filled
			const fieldWithError = page.locator( '.acf-field[data-name="field_one"].acf-error' );
			await expect( fieldWithError ).not.toBeVisible();

			// Second submission should work - click publish button again
			await page.click( 'button.editor-post-publish-button' );

			// Wait for the publish confirmation to appear (successful submission)
			// Or verify no new validation errors appeared
			await page.waitForTimeout( 2000 );

			// Verify no validation errors are shown after successful submission
			const validationErrorAfterSave = page.locator( '.acf-error-message, .acf-field.acf-error, .acf-notice.-error' );
			await expect( validationErrorAfterSave ).not.toBeVisible();
		} );
	} );

	test.describe( 'Multiple Fields Validation', () => {
		test( 'should validate multiple required fields at once', async ( {
			page,
			admin,
			requestUtils,
		} ) => {
			// Create field group with multiple fields
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
			const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
			await addNewButton.click();

			await page.waitForSelector( '#title' );
			await page.fill( '#title', 'Multi Required Fields' );

			// Add first required field
			const fieldLabel1 = page.locator( 'input[id^="acf_fields-field_"][id$="-label"]' ).first();
			await fieldLabel1.fill( 'Required One' );
			await toggleFieldSetting( page, '.acf-field-setting-required', true );

			// Close the first field before adding second
			await page.click( 'button:has-text("Close Field"), a:has-text("Close Field")' );
			await page.waitForTimeout( 300 );

			// Add second field - click the secondary Add Field button (not the primary one in empty state)
			await page.click( '#acf-field-group-fields a.acf-btn-secondary.add-field' );
			await page.waitForTimeout( 500 );

			const fieldLabel2 = page.locator( 'input[id^="acf_fields-field_"][id$="-label"]' ).last();
			await fieldLabel2.fill( 'Required Two' );

			// Make second field required
			const fieldObjects = page.locator( '.acf-field-object' );
			const secondField = fieldObjects.last();
			const requiredToggle = secondField.locator( '.acf-field-setting-required .acf-switch' );
			if ( await requiredToggle.isVisible() ) {
				await requiredToggle.click( { force: true } );
			}

			// Publish
			const publishButton = page.locator( 'button.acf-btn.acf-publish[type="submit"]' );
			await publishButton.click();

			const successNotice = page.locator( '.updated.notice' );
			await expect( successNotice ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );

			// Create and edit a post
			const post = await requestUtils.createPost( {
				title: 'Multi Validation Post',
				status: 'draft',
			} );

			await admin.editPost( post.id );
			await waitForMetaBoxes( page );

			// Try to submit without filling either field
			await page.click( 'button.editor-post-publish-panel__toggle' );
			await page.click( 'button.editor-post-publish-button' );

			// Should show validation errors for both fields
			const errorFields = page.locator( '.acf-field.acf-error' );
			// Wait for at least one error field
			await expect( errorFields.first() ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );
		} );

		test( 'should display correct number of validation errors for multiple fields', async ( {
			page,
			admin,
			requestUtils,
		} ) => {
			// Create field group with 3 required fields
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
			const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
			await addNewButton.click();

			await page.waitForSelector( '#title' );
			await page.fill( '#title', 'Triple Required Fields' );

			// Add first required field
			const fieldLabel1 = page.locator( 'input[id^="acf_fields-field_"][id$="-label"]' ).first();
			await fieldLabel1.fill( 'Field One' );

			// First field - navigate to validation tab and set required
			const firstFieldObject = page.locator( '.acf-field-object' ).first();
			const validationTab1 = firstFieldObject.locator( '.acf-tab-wrap a.acf-tab-button' ).filter( { hasText: 'Validation' } ).first();
			await validationTab1.click();
			await page.waitForTimeout( 200 );
			const requiredToggle1 = firstFieldObject.locator( '.acf-field-setting-required .acf-switch' );
			await requiredToggle1.click( { force: true } );
			await page.waitForTimeout( 200 );

			// Close first field
			await firstFieldObject.locator( 'a.close-field' ).click();
			await page.waitForTimeout( 300 );

			// Add second field
			await page.click( '#acf-field-group-fields a.acf-btn-secondary.add-field' );
			await page.waitForTimeout( 500 );

			const fieldLabel2 = page.locator( 'input[id^="acf_fields-field_"][id$="-label"]' ).last();
			await fieldLabel2.fill( 'Field Two' );

			// Second field - navigate to validation tab and set required
			const secondFieldObject = page.locator( '.acf-field-object' ).last();
			const validationTab2 = secondFieldObject.locator( '.acf-tab-wrap a.acf-tab-button' ).filter( { hasText: 'Validation' } ).first();
			await validationTab2.click();
			await page.waitForTimeout( 200 );
			const requiredToggle2 = secondFieldObject.locator( '.acf-field-setting-required .acf-switch' );
			await requiredToggle2.click( { force: true } );
			await page.waitForTimeout( 200 );

			// Close second field
			await secondFieldObject.locator( 'a.close-field' ).click();
			await page.waitForTimeout( 300 );

			// Add third field
			await page.click( '#acf-field-group-fields a.acf-btn-secondary.add-field' );
			await page.waitForTimeout( 500 );

			const fieldLabel3 = page.locator( 'input[id^="acf_fields-field_"][id$="-label"]' ).last();
			await fieldLabel3.fill( 'Field Three' );

			// Third field - navigate to validation tab and set required
			const thirdFieldObject = page.locator( '.acf-field-object' ).last();
			const validationTab3 = thirdFieldObject.locator( '.acf-tab-wrap a.acf-tab-button' ).filter( { hasText: 'Validation' } ).first();
			await validationTab3.click();
			await page.waitForTimeout( 200 );
			const requiredToggle3 = thirdFieldObject.locator( '.acf-field-setting-required .acf-switch' );
			await requiredToggle3.click( { force: true } );
			await page.waitForTimeout( 200 );

			// Publish
			const publishButton = page.locator( 'button.acf-btn.acf-publish[type="submit"]' );
			await publishButton.click();

			const successNotice = page.locator( '.updated.notice' );
			await expect( successNotice ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );

			// Create and edit a post
			const post = await requestUtils.createPost( {
				title: 'Triple Validation Post',
				status: 'draft',
			} );

			await admin.editPost( post.id );
			await waitForMetaBoxes( page );

			// Try to submit without filling any field
			await page.click( 'button.editor-post-publish-panel__toggle' );
			await page.click( 'button.editor-post-publish-button' );

			// Wait for validation errors
			const errorFields = page.locator( '.acf-field.acf-error' );
			await expect( errorFields.first() ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );

			// Verify we have exactly 3 error fields
			const errorCount = await errorFields.count();
			expect( errorCount ).toBe( 3 );

			// Fill one field and verify error count decreases
			const textInput1 = page.locator( '.acf-field[data-name="field_one"] input[type="text"]' );
			await textInput1.fill( 'Filled value' );
			await textInput1.blur();
			await page.waitForTimeout( 500 );

			// After filling one, should have 2 errors
			const remainingErrors = page.locator( '.acf-field.acf-error' );
			const remainingCount = await remainingErrors.count();
			expect( remainingCount ).toBe( 2 );
		} );
	} );
} );

/**
 * Helper function to create a field group with validation settings.
 */
async function createFieldGroupWithValidation( page, admin, options ) {
	const { fieldLabel, fieldType = 'text', required = false, configureField } = options;

	await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
	const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
	await addNewButton.click();

	await page.waitForSelector( '#title' );
	await page.fill( '#title', `${ fieldLabel } Validation Group` );

	// Set field label
	const fieldLabelInput = page.locator( 'input[id^="acf_fields-field_"][id$="-label"]' );
	await fieldLabelInput.fill( fieldLabel );

	// Set field type
	if ( fieldType !== 'text' ) {
		const fieldTypeSelect = page.locator( 'select[id^="acf_fields-field_"][id$="-type"]' );
		await fieldTypeSelect.selectOption( fieldType );
		await page.waitForTimeout( 500 );
	}

	// Run custom configuration first (before switching tabs)
	if ( configureField ) {
		await configureField();
	}

	// Set required if needed (this switches to Validation tab)
	if ( required ) {
		await toggleFieldSetting( page, '.acf-field-setting-required', true );
	}

	// Publish
	const publishButton = page.locator( 'button.acf-btn.acf-publish[type="submit"]' );
	await publishButton.click();

	const successNotice = page.locator( '.updated.notice' );
	await expect( successNotice ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );
}
