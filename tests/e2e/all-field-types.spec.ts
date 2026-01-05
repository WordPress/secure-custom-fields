/**
 * E2E tests for all SCF field types
 *
 * Tests all major field types in a single field group to verify:
 * - Field rendering in the editor
 * - Value input and persistence
 * - Frontend output via the_content filter
 */
const { test, expect } = require( './fixtures' );
const {
	waitForMetaBoxes,
	uploadImageViaModal,
	selectSelect2Option,
} = require( './field-helpers' );
const path = require( 'path' );

const PLUGIN_SLUG = 'secure-custom-fields';
const TEST_PLUGIN_SLUG = 'scf-test-plugin-all-field-types';

// Test data for each field type
const TEST_DATA = {
	text_field: 'Sample text value',
	textarea_field: 'Multi-line\ntext content',
	email_field: 'test@example.com',
	url_field: 'https://example.com',
	password_field: 'secret123',
	number_field: '42',
	range_field: '75',
	select_field: 'option_2',
	checkbox_values: [ 'check_a', 'check_b' ],
	radio_field: 'radio_2',
	button_group_field: 'button_2',
	true_false_field: true,
	date_picker_field: '2024-06-15',
	time_picker_field: '14:30:00',
	date_time_picker_field: '2024-06-15 14:30:00',
	color_picker_field: '#ff5733',
	wysiwyg_field: 'Rich text content',
	link_url: 'https://wordpress.org',
	link_title: 'WordPress',
	group_text: 'Group text value',
	group_number: '99',
	repeater_row_1_text: 'Row 1 text',
	repeater_row_1_number: '10',
};

test.describe( 'All Field Types', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( PLUGIN_SLUG );
		await requestUtils.activatePlugin( TEST_PLUGIN_SLUG );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deactivatePlugin( TEST_PLUGIN_SLUG );
		await requestUtils.deactivatePlugin( PLUGIN_SLUG );
		await requestUtils.deleteAllPosts();
	} );

	test( 'should save and display all field types correctly', async ( {
		page,
		admin,
		editor,
		requestUtils,
	} ) => {
		// Create a new post
		const post = await requestUtils.createPost( {
			title: 'All Field Types Test',
			status: 'draft',
		} );

		// Navigate to edit post page
		await admin.editPost( post.id );

		// Wait for meta boxes to load
		await waitForMetaBoxes( page );

		// === Text-based fields ===

		// Text field
		const textField = page.locator(
			'.acf-field[data-name="text_field"] input[type="text"]'
		);
		await textField.fill( TEST_DATA.text_field );
		await textField.blur();

		// Textarea field
		const textareaField = page.locator(
			'.acf-field[data-name="textarea_field"] textarea'
		);
		await textareaField.fill( TEST_DATA.textarea_field );
		await textareaField.blur();

		// Email field
		const emailField = page.locator(
			'.acf-field[data-name="email_field"] input[type="email"]'
		);
		await emailField.fill( TEST_DATA.email_field );
		await emailField.blur();

		// URL field
		const urlField = page.locator(
			'.acf-field[data-name="url_field"] input[type="url"]'
		);
		await urlField.fill( TEST_DATA.url_field );
		await urlField.blur();

		// Password field
		const passwordField = page.locator(
			'.acf-field[data-name="password_field"] input[type="password"]'
		);
		await passwordField.fill( TEST_DATA.password_field );
		await passwordField.blur();

		// Number field
		const numberField = page.locator(
			'.acf-field[data-name="number_field"] input[type="number"]'
		);
		await numberField.fill( TEST_DATA.number_field );
		await numberField.blur();

		// Range field
		const rangeField = page.locator(
			'.acf-field[data-name="range_field"] input[type="range"]'
		);
		await rangeField.fill( TEST_DATA.range_field );
		await rangeField.blur();

		// === Selection fields ===

		// Select field
		const selectField = page.locator(
			'.acf-field[data-name="select_field"] select'
		);
		await selectField.selectOption( TEST_DATA.select_field );

		// Checkbox field - check two options
		for ( const value of TEST_DATA.checkbox_values ) {
			const checkbox = page.locator(
				`.acf-field[data-name="checkbox_field"] input[type="checkbox"][value="${ value }"]`
			);
			await checkbox.check();
		}

		// Radio field
		const radioButton = page.locator(
			`.acf-field[data-name="radio_field"] input[type="radio"][value="${ TEST_DATA.radio_field }"]`
		);
		await radioButton.check();

		// Button group field - click the label element (styled as button)
		const buttonGroupLabel = page.locator(
			`.acf-field[data-name="button_group_field"] label:has-text("Button 2")`
		);
		await buttonGroupLabel.click();

		// True/false field
		if ( TEST_DATA.true_false_field ) {
			const trueFalseSwitch = page.locator(
				'.acf-field[data-name="true_false_field"] .acf-switch'
			);
			await trueFalseSwitch.click();
		}

		// === Date/time fields ===

		// Date picker field - click to open and set value
		const datePickerInput = page.locator(
			'.acf-field[data-name="date_picker_field"] input[type="text"]'
		);
		await datePickerInput.click();
		// Wait for datepicker to open
		await page.waitForSelector( '.ui-datepicker', { state: 'visible' } );
		// Select a date (June 15, 2024)
		await page.locator( '.ui-datepicker-year' ).selectOption( '2024' );
		await page.locator( '.ui-datepicker-month' ).selectOption( '5' ); // June is 5 (0-indexed)
		await page
			.locator( '.ui-datepicker-calendar td a:has-text("15")' )
			.click();

		// Note: time_picker_field and date_time_picker_field are skipped as they
		// use complex jQuery UI timepicker widgets that are difficult to automate.
		// These can be tested manually or in a separate focused test.

		// === Content fields ===

		// Color picker field - click the button to open picker, then fill input
		const colorPickerButton = page.locator(
			'.acf-field[data-name="color_picker_field"] .wp-color-result, .acf-field[data-name="color_picker_field"] button.wp-color-result'
		);
		await colorPickerButton.click();
		const colorPickerInput = page.locator(
			'.acf-field[data-name="color_picker_field"] input.wp-color-picker'
		);
		await colorPickerInput.fill( TEST_DATA.color_picker_field );
		await page.click( 'body' ); // Close picker

		// WYSIWYG field - need to interact with TinyMCE
		// Switch to text mode for easier input
		const wysiwygTextTab = page.locator(
			'.acf-field[data-name="wysiwyg_field"] .wp-switch-editor.switch-html'
		);
		if ( await wysiwygTextTab.isVisible() ) {
			await wysiwygTextTab.click();
		}
		const wysiwygTextarea = page.locator(
			'.acf-field[data-name="wysiwyg_field"] textarea.wp-editor-area'
		);
		await wysiwygTextarea.fill( `<p>${ TEST_DATA.wysiwyg_field }</p>` );

		// Link field - click button to open WordPress link modal
		const linkButton = page.locator(
			'.acf-field[data-name="link_field"] button:has-text("Select Link"), .acf-field[data-name="link_field"] a:has-text("Select Link")'
		);
		await linkButton.click();
		// Wait for WordPress link modal to open
		const wpLinkUrlInput = page.locator( '#wp-link-url' );
		await wpLinkUrlInput.waitFor( { state: 'visible' } );
		await wpLinkUrlInput.fill( TEST_DATA.link_url );
		const wpLinkTextInput = page.locator( '#wp-link-text' );
		await wpLinkTextInput.fill( TEST_DATA.link_title );
		const submitButton = page.locator( '#wp-link-submit' );
		await submitButton.click();

		// === Media field ===

		// Image field - upload test image
		const imageButton = page.locator(
			'.acf-field[data-name="image_field"] .acf-image-uploader a[data-name="add"]'
		);
		await imageButton.click();
		const imagePath = path.join( __dirname, 'assets', 'test-image.png' );
		await uploadImageViaModal( page, imagePath );

		// === Relationship fields ===

		// User field - select the current user (admin)
		await selectSelect2Option(
			page,
			'.acf-field[data-name="user_field"]',
			'admin',
			'admin'
		);

		// Taxonomy field - use Select2 to select Uncategorized
		await selectSelect2Option(
			page,
			'.acf-field[data-name="taxonomy_field"]',
			'Uncategorized',
			'Uncategorized'
		);

		// === Container fields ===

		// Group field - fill sub-fields
		const groupTextField = page.locator(
			'.acf-field[data-name="group_field"] .acf-field[data-name="group_text"] input[type="text"]'
		);
		await groupTextField.fill( TEST_DATA.group_text );
		await groupTextField.blur();

		const groupNumberField = page.locator(
			'.acf-field[data-name="group_field"] .acf-field[data-name="group_number"] input[type="number"]'
		);
		await groupNumberField.fill( TEST_DATA.group_number );
		await groupNumberField.blur();

		// Repeater field - add a row and fill it
		const addRowButton = page.locator(
			'.acf-field[data-name="repeater_field"] .acf-button[data-event="add-row"]'
		);
		await addRowButton.click();

		// Wait for new row to appear
		const repeaterTextField = page.locator(
			'.acf-field[data-name="repeater_field"] .acf-row:not(.acf-clone) .acf-field[data-name="repeater_text"] input[type="text"]'
		);
		await repeaterTextField.waitFor( { state: 'visible' } );
		await repeaterTextField.fill( TEST_DATA.repeater_row_1_text );
		await repeaterTextField.blur();

		const repeaterNumberField = page.locator(
			'.acf-field[data-name="repeater_field"] .acf-row:not(.acf-clone) .acf-field[data-name="repeater_number"] input[type="number"]'
		);
		await repeaterNumberField.fill( TEST_DATA.repeater_row_1_number );
		await repeaterNumberField.blur();

		// === Verify on frontend ===

		// Open preview page
		const previewPage = await editor.openPreviewPage();

		// Verify text field
		const textOutput = previewPage.locator( '#scf-test-text_field' );
		await expect( textOutput ).toContainText( TEST_DATA.text_field );

		// Verify textarea field
		const textareaOutput = previewPage.locator(
			'#scf-test-textarea_field'
		);
		await expect( textareaOutput ).toContainText( 'Multi-line' );

		// Verify email field
		const emailOutput = previewPage.locator( '#scf-test-email_field' );
		await expect( emailOutput ).toContainText( TEST_DATA.email_field );

		// Verify URL field
		const urlOutput = previewPage.locator( '#scf-test-url_field' );
		await expect( urlOutput ).toContainText( TEST_DATA.url_field );

		// Verify number field
		const numberOutput = previewPage.locator( '#scf-test-number_field' );
		await expect( numberOutput ).toContainText( TEST_DATA.number_field );

		// Verify range field
		const rangeOutput = previewPage.locator( '#scf-test-range_field' );
		await expect( rangeOutput ).toContainText( TEST_DATA.range_field );

		// Verify select field
		const selectOutput = previewPage.locator( '#scf-test-select_field' );
		await expect( selectOutput ).toContainText( TEST_DATA.select_field );

		// Verify checkbox field
		const checkboxOutput = previewPage.locator(
			'#scf-test-checkbox_field'
		);
		await expect( checkboxOutput ).toContainText( 'check_a' );
		await expect( checkboxOutput ).toContainText( 'check_b' );

		// Verify radio field
		const radioOutput = previewPage.locator( '#scf-test-radio_field' );
		await expect( radioOutput ).toContainText( TEST_DATA.radio_field );

		// Verify button group field
		const buttonGroupOutput = previewPage.locator(
			'#scf-test-button_group_field'
		);
		await expect( buttonGroupOutput ).toContainText(
			TEST_DATA.button_group_field
		);

		// Verify true/false field
		const trueFalseOutput = previewPage.locator(
			'#scf-test-true_false_field'
		);
		await expect( trueFalseOutput ).toContainText( 'Yes' );

		// Verify date picker field
		const datePickerOutput = previewPage.locator(
			'#scf-test-date_picker_field'
		);
		await expect( datePickerOutput ).toContainText( '2024-06-15' );

		// Note: time_picker_field and date_time_picker_field verification skipped
		// as these fields are not filled in the test (complex jQuery UI widgets)

		// Verify color picker field
		const colorOutput = previewPage.locator(
			'#scf-test-color_picker_field'
		);
		await expect( colorOutput ).toContainText(
			TEST_DATA.color_picker_field
		);

		// Verify WYSIWYG field
		const wysiwygOutput = previewPage.locator( '#scf-test-wysiwyg_field' );
		await expect( wysiwygOutput ).toContainText( TEST_DATA.wysiwyg_field );

		// Verify link field
		const linkOutput = previewPage.locator( '#scf-test-link_field a' );
		await expect( linkOutput ).toHaveAttribute(
			'href',
			TEST_DATA.link_url
		);
		await expect( linkOutput ).toContainText( TEST_DATA.link_title );

		// Verify image field
		const imageOutput = previewPage.locator(
			'#scf-test-image_field img.scf-test-image'
		);
		await expect( imageOutput ).toBeVisible();

		// Verify user field
		const userOutput = previewPage.locator( '#scf-test-user_field' );
		await expect( userOutput ).toContainText( 'admin' );

		// Verify taxonomy field
		const taxonomyOutput = previewPage.locator(
			'#scf-test-taxonomy_field'
		);
		await expect( taxonomyOutput ).toContainText( 'Uncategorized' );

		// Verify group field
		const groupOutput = previewPage.locator( '#scf-test-group_field' );
		await expect( groupOutput ).toContainText( TEST_DATA.group_text );
		await expect( groupOutput ).toContainText( TEST_DATA.group_number );

		// Verify repeater field
		const repeaterOutput = previewPage.locator(
			'#scf-test-repeater_field'
		);
		await expect( repeaterOutput ).toContainText(
			TEST_DATA.repeater_row_1_text
		);
		await expect( repeaterOutput ).toContainText(
			TEST_DATA.repeater_row_1_number
		);

		// Close preview page
		await previewPage.close();
	} );
} );
