/**
 * Shared helper functions for field type E2E tests.
 *
 * Provides common utilities for creating field groups, managing fields,
 * and cleaning up test data across all field type tests.
 */

const PLUGIN_SLUG = 'secure-custom-fields';

/**
 * Delete all field groups and empty trash.
 *
 * @param {import('@playwright/test').Page} page  Playwright page object.
 * @param {Object}                          admin Admin utilities.
 */
async function deleteFieldGroups( page, admin ) {
	await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );

	// Check if there are any field groups in the table (not just the checkbox)
	const fieldGroupRows = page.locator(
		'table.wp-list-table tbody tr:not(.no-items)'
	);
	const rowCount = await fieldGroupRows.count();

	if ( rowCount > 0 ) {
		const allFieldGroupsCheckbox = page.locator( 'input#cb-select-all-1' );
		await allFieldGroupsCheckbox.check();
		await page.selectOption( '#bulk-action-selector-bottom', 'trash' );
		await page.click( '#doaction2' );

		// Wait for deletion
		const deleteMessage = page.locator( '.updated.notice' );
		await deleteMessage.waitFor( { state: 'visible', timeout: 5000 } );

		await emptyTrash( page, admin );
	}
}

/**
 * Empty the field groups trash.
 *
 * @param {import('@playwright/test').Page} page  Playwright page object.
 * @param {Object}                          admin Admin utilities.
 */
async function emptyTrash( page, admin ) {
	await admin.visitAdminPage(
		'edit.php',
		'post_status=trash&post_type=acf-field-group'
	);
	const emptyTrashButton = page.locator(
		'.tablenav.bottom input[name="delete_all"][value="Empty Trash"]'
	);

	if ( await emptyTrashButton.isVisible() ) {
		await emptyTrashButton.click();
		// Wait for either the success notice or page to reload
		// The notice selector may vary between WordPress versions
		const successNotice = page.locator( '.notice.updated, .updated.notice' );
		await successNotice.first().waitFor( { state: 'visible', timeout: 5000 } ).catch( () => {
			// If no notice appears, the page might have redirected, which is also valid
		} );
	}
}

/**
 * Create a new field group with a single field.
 *
 * @param {import('@playwright/test').Page} page            Playwright page object.
 * @param {Object}                          admin           Admin utilities.
 * @param {Object}                          options         Field options.
 * @param {string}                          options.groupTitle   Field group title.
 * @param {string}                          options.fieldLabel   Field label.
 * @param {string}                          options.fieldType    Field type (e.g., 'text', 'image').
 * @param {Function}                        [options.configure]  Optional callback for field configuration.
 */
async function createFieldGroup( page, admin, options ) {
	const { groupTitle, fieldLabel, fieldType, configure } = options;

	await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
	const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
	await addNewButton.click();

	// Fill field group title
	await page.waitForSelector( '#title' );
	await page.fill( '#title', groupTitle );

	// Set field label
	const fieldLabelInput = page.locator(
		'input[id^="acf_fields-field_"][id$="-label"]'
	);
	await fieldLabelInput.fill( fieldLabel );

	// Select field type
	const fieldTypeSelect = page.locator(
		'select[id^="acf_fields-field_"][id$="-type"]'
	);
	await fieldTypeSelect.selectOption( fieldType );

	// Run custom configuration if provided
	if ( configure ) {
		await configure( page );
	}

	// Publish
	const publishButton = page.locator(
		'button.acf-btn.acf-publish[type="submit"]'
	);
	await publishButton.click();

	// Verify success
	const successNotice = page.locator( '.updated.notice' );
	await successNotice.waitFor( { state: 'visible', timeout: 5000 } );
}

/**
 * Wait for meta boxes to load and expand if collapsed.
 *
 * @param {import('@playwright/test').Page} page Playwright page object.
 */
async function waitForMetaBoxes( page ) {
	// Wait for at least one ACF postbox to be attached
	// Using locator instead of waitForSelector to handle multiple matches
	const postboxes = page.locator( '.acf-postbox' );
	await postboxes.first().waitFor( { state: 'attached' } );

	const metaBoxPanel = page.getByRole( 'button', { name: 'Meta Boxes' } );
	if (
		( await metaBoxPanel.count() ) > 0 &&
		( await metaBoxPanel.getAttribute( 'aria-expanded' ) ) === 'false'
	) {
		await metaBoxPanel.focus();
		await metaBoxPanel.press( 'Enter' );
	}
}

/**
 * Upload an image to the media library via the media modal.
 *
 * @param {import('@playwright/test').Page} page      Playwright page object.
 * @param {string}                          imagePath Path to the image file.
 */
async function uploadImageViaModal( page, imagePath ) {
	// Wait for media modal to open
	await page.waitForSelector( '.media-modal', { state: 'visible' } );

	// Click "Upload files" tab if not already there
	const uploadTab = page.locator( '.media-modal #menu-item-upload' );
	if ( await uploadTab.isVisible() ) {
		await uploadTab.click();
	}

	// Upload the file
	const fileInput = page.locator( '.media-modal input[type="file"]' );
	await fileInput.setInputFiles( imagePath );

	// Wait for upload to complete
	await page.waitForSelector( '.media-modal .attachment.selected', {
		state: 'visible',
		timeout: 15000,
	} );

	// Click "Select" button
	const selectButton = page.locator(
		'.media-modal .media-toolbar-primary .media-button-select'
	);
	await selectButton.click();

	// Wait for modal to close
	await page.waitForSelector( '.media-modal', { state: 'hidden' } );
}

/**
 * Add choices to a select/checkbox/radio field.
 *
 * @param {import('@playwright/test').Page} page    Playwright page object.
 * @param {string[]}                        choices Array of choices in "value : label" format.
 */
async function addFieldChoices( page, choices ) {
	const choicesTextarea = page.locator(
		'textarea[id^="acf_fields-field_"][id$="-choices"]'
	);
	await choicesTextarea.fill( choices.join( '\n' ) );
}

/**
 * Add a subfield to a repeater, group, or flexible content field.
 *
 * @param {import('@playwright/test').Page} page       Playwright page object.
 * @param {Object}                          options    Subfield options.
 * @param {string}                          options.label     Subfield label.
 * @param {string}                          options.type      Subfield type.
 * @param {boolean}                         [options.isFirst] Whether this is the first subfield.
 */
async function addSubfield( page, options ) {
	const { label, type, isFirst = false } = options;

	if ( isFirst ) {
		// Click "Add Field" button for first subfield
		const addFirstButton = page.locator(
			'.acf-field-setting-sub_fields a.add-first-field'
		);
		await addFirstButton.click();
	} else {
		// Click "Add Field" button for additional subfields
		const addFieldButton = page.locator(
			'.acf-field-setting-sub_fields .acf-is-subfields a.add-field.acf-btn-secondary'
		);
		await addFieldButton.click();
	}

	// Wait for subfield to appear and set properties
	const subFieldLabel = page
		.locator( '.acf-field-object input.field-label' )
		.last();
	await subFieldLabel.waitFor();
	await subFieldLabel.fill( label );

	// Set type if not text (default)
	if ( type !== 'text' ) {
		const subFieldType = page
			.locator( '.acf-field-object' )
			.last()
			.locator( 'select.field-type' );
		await subFieldType.selectOption( type );
	}
}

/**
 * Add a layout to a flexible content field.
 *
 * @param {import('@playwright/test').Page} page    Playwright page object.
 * @param {Object}                          options Layout options.
 * @param {string}                          options.label Layout label.
 */
async function addFlexibleContentLayout( page, options ) {
	const { label } = options;

	// Click "Add Layout" button
	const addLayoutButton = page.locator(
		'.acf-field-setting-fc_layouts a.add-layout'
	);
	await addLayoutButton.click();

	// Fill layout label
	const layoutLabel = page.locator(
		'.acf-field-setting-fc_layouts .acf-fc-layout-label input'
	);
	await layoutLabel.last().fill( label );
}

/**
 * Toggle an SCF switch/checkbox setting.
 *
 * SCF uses custom toggle switches (`.acf-switch`) that overlay the actual checkbox.
 * This helper properly handles clicking either the switch or falling back to force-click.
 * It also handles settings that may be on different tabs (General, Validation, Presentation, Conditional Logic).
 *
 * @param {import('@playwright/test').Page} page     Playwright page object.
 * @param {string}                          selector Selector for the setting container (e.g., '.acf-field-setting-multiple').
 * @param {boolean}                         checked  Whether to check (true) or uncheck (false).
 */
async function toggleFieldSetting( page, selector, checked = true ) {
	const settingContainer = page.locator( selector );

	// First, wait for the setting container to be attached (it might be loading after field type change)
	await settingContainer.waitFor( { state: 'attached', timeout: 5000 } ).catch( () => {} );

	// Check if the setting container is visible (not just attached)
	let isVisible = await settingContainer.isVisible().catch( () => false );

	// If not visible, try clicking through different tabs to find it
	if ( ! isVisible ) {
		const tabs = [
			'General',
			'Validation',
			'Presentation',
			'Conditional Logic',
			'Advanced',
		];
		for ( const tabName of tabs ) {
			// Use more specific selector that matches the exact tab link text
			const tab = page.locator(
				`.acf-field-object .acf-tab-wrap a.acf-tab-button`
			).filter( { hasText: tabName } );
			if ( ( await tab.count() ) > 0 && ( await tab.isVisible() ) ) {
				await tab.click();
				await page.waitForTimeout( 150 );

				// Check if the setting is now visible
				isVisible = await settingContainer.isVisible().catch( () => false );
				if ( isVisible ) {
					break;
				}
			}
		}
	}

	// Wait for the setting container to be visible
	await settingContainer.waitFor( { state: 'visible', timeout: 5000 } );

	// Scroll into view using evaluate (works even when element is hidden by overflow)
	await settingContainer.evaluate( ( el ) => {
		el.scrollIntoView( { block: 'center', behavior: 'instant' } );
	} );
	await page.waitForTimeout( 100 );

	// First try to click the ACF switch if it exists (even if not visible due to overlay)
	const acfSwitch = settingContainer.locator( '.acf-switch' );
	if ( ( await acfSwitch.count() ) > 0 ) {
		const isCurrentlyOn =
			( await acfSwitch.getAttribute( 'class' ) )?.includes( '-on' ) ??
			false;
		if ( isCurrentlyOn !== checked ) {
			await acfSwitch.click( { force: true } );
		}
		return;
	}

	// Fallback to checkbox with force click (for non-switch toggles)
	const checkbox = settingContainer.locator( 'input[type="checkbox"]' );
	if ( ( await checkbox.count() ) > 0 ) {
		const isChecked = await checkbox.isChecked();
		if ( isChecked !== checked ) {
			await checkbox.click( { force: true } );
		}
	}
}

/**
 * Scroll an element into view to avoid header interception.
 *
 * @param {import('@playwright/test').Page}    page    Playwright page object.
 * @param {import('@playwright/test').Locator} element Element locator.
 */
async function scrollIntoViewWithOffset( page, element ) {
	await element.evaluate( ( el ) => {
		el.scrollIntoView( { block: 'center', behavior: 'instant' } );
	} );
	// Small delay to ensure scroll completes
	await page.waitForTimeout( 100 );
}

/**
 * Select an option in a Select2 AJAX dropdown.
 *
 * @param {import('@playwright/test').Page}    page          Playwright page object.
 * @param {import('@playwright/test').Locator} containerSelector Selector for the Select2 container parent.
 * @param {string}                             searchText    Text to search for.
 * @param {string}                             optionText    Text of the option to select.
 */
async function selectSelect2Option( page, containerSelector, searchText, optionText ) {
	const container = page.locator( containerSelector );
	const select2 = container.locator( '.select2-container' );
	await select2.click();
	await page.waitForTimeout( 200 );

	const searchField = page.locator( '.select2-search__field' ).first();
	await searchField.fill( searchText );
	await page.waitForTimeout( 500 ); // Wait for AJAX results

	const option = page
		.locator( `.select2-results__option:has-text("${ optionText }")` )
		.first();
	await option.waitFor( { timeout: 5000 } );
	await option.click();
}

/**
 * Expand a collapsed Flexible Content layout section.
 *
 * @param {import('@playwright/test').Locator} layout The layout locator.
 */
async function expandFCLayout( layout ) {
	const layoutSettings = layout.locator( '.acf-field-layout-settings' );
	if ( ! ( await layoutSettings.isVisible() ) ) {
		await layout.locator( '.acf-field-settings-fc_head' ).click();
		await layoutSettings.waitFor( { state: 'visible', timeout: 5000 } );
	}
}

module.exports = {
	PLUGIN_SLUG,
	deleteFieldGroups,
	emptyTrash,
	createFieldGroup,
	waitForMetaBoxes,
	uploadImageViaModal,
	addFieldChoices,
	addSubfield,
	addFlexibleContentLayout,
	toggleFieldSetting,
	scrollIntoViewWithOffset,
	selectSelect2Option,
	expandFCLayout,
};
