/**
 * E2E tests for Import/Export functionality.
 *
 * Tests JSON export of field groups, import of field groups,
 * PHP code generation, and conflict resolution during import.
 */
const { test, expect } = require( './fixtures' );
const {
	PLUGIN_SLUG,
	deleteFieldGroups,
} = require( './field-helpers' );
const path = require( 'path' );
const fs = require( 'fs' );
const os = require( 'os' );

// Constants
const TEST_FIELD_GROUP_NAME = 'Export Test Field Group';
const TEST_FIELD_LABEL = 'Export Test Field';
const TEST_POST_TYPE_NAME = 'Export Test Post Type';
const TEST_TAXONOMY_NAME = 'Export Test Taxonomy';
const DEFAULT_TIMEOUT = 5000;

test.describe( 'Import/Export', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( PLUGIN_SLUG );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deactivatePlugin( PLUGIN_SLUG );
	} );

	test.beforeEach( async ( { page, admin } ) => {
		await deleteFieldGroups( page, admin );
		// Clean up any test post types and taxonomies
		await cleanupTestEntities( page, admin );
	} );

	test.describe( 'JSON Export', () => {
		test( 'should export a field group as JSON', async ( { page, admin } ) => {
			// Create a field group to export
			await createTestFieldGroup( page, admin );

			// Navigate to tools page
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group&page=acf-tools' );

			// Wait for export section to be visible
			const exportSection = page.locator( '.acf-postbox-header:has-text("Export")' );
			await expect( exportSection ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );

			// Select the field group
			const fieldGroupCheckbox = page.locator( `input[type="checkbox"][value*="group_"]` ).first();
			await expect( fieldGroupCheckbox ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );
			await fieldGroupCheckbox.check();

			// Verify checkbox is checked
			await expect( fieldGroupCheckbox ).toBeChecked();

			// Click Export As JSON button and wait for download
			const exportButton = page.locator( 'button[value="download"]:has-text("Export As JSON")' );
			await expect( exportButton ).toBeVisible();

			// Start waiting for download before clicking
			const downloadPromise = page.waitForEvent( 'download' );
			await exportButton.click();
			const download = await downloadPromise;

			// Verify the download
			expect( download.suggestedFilename() ).toMatch( /\.json$/ );

			// Read and verify the JSON content
			const downloadPath = path.join( os.tmpdir(), 'scf-export-test-download.json' );
			await download.saveAs( downloadPath );

			try {
				const content = fs.readFileSync( downloadPath, 'utf-8' );
				const json = JSON.parse( content );

				// Verify it's an array with at least one field group
				expect( Array.isArray( json ) ).toBe( true );
				expect( json.length ).toBeGreaterThan( 0 );

				// Verify the exported field group has expected structure
				const fieldGroup = json[ 0 ];
				expect( fieldGroup ).toHaveProperty( 'key' );
				expect( fieldGroup ).toHaveProperty( 'title' );
				expect( fieldGroup ).toHaveProperty( 'fields' );
				expect( fieldGroup.key ).toMatch( /^group_/ );
			} finally {
				if ( fs.existsSync( downloadPath ) ) {
					fs.unlinkSync( downloadPath );
				}
			}
		} );

		test( 'should display warning when no field groups selected for export', async ( { page, admin } ) => {
			// Create a field group (so the export section has content)
			await createTestFieldGroup( page, admin );

			// Navigate to tools page
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group&page=acf-tools' );

			// Click Export As JSON without selecting anything
			const exportButton = page.locator( 'button[value="download"]:has-text("Export As JSON")' );
			await expect( exportButton ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );
			await exportButton.click();

			// Should show warning notice
			const warningNotice = page.locator( '.acf-admin-notice.notice-warning, .notice.notice-warning' );
			await expect( warningNotice ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );
			await expect( warningNotice ).toContainText( 'No field groups selected' );
		} );

		test( 'should export multiple field groups at once', async ( { page, admin } ) => {
			// Create first field group
			const firstGroupName = `Multi Export Group 1 ${ Date.now() }`;
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
			const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
			await addNewButton.click();

			await page.waitForSelector( '#title' );
			await page.fill( '#title', firstGroupName );

			const fieldLabel1 = page.locator( 'input[id^="acf_fields-field_"][id$="-label"]' );
			await fieldLabel1.fill( 'First Field' );

			const publishButton = page.locator( 'button.acf-btn.acf-publish[type="submit"]' );
			await publishButton.click();
			await expect( page.locator( '.updated.notice' ) ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );

			// Create second field group
			const secondGroupName = `Multi Export Group 2 ${ Date.now() }`;
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
			await addNewButton.click();

			await page.waitForSelector( '#title' );
			await page.fill( '#title', secondGroupName );

			const fieldLabel2 = page.locator( 'input[id^="acf_fields-field_"][id$="-label"]' );
			await fieldLabel2.fill( 'Second Field' );

			await publishButton.click();
			await expect( page.locator( '.updated.notice' ) ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );

			// Navigate to tools page
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group&page=acf-tools' );

			// Select both field groups
			const fieldGroupCheckboxes = page.locator( 'input[type="checkbox"][value*="group_"]' );
			const checkboxCount = await fieldGroupCheckboxes.count();
			expect( checkboxCount ).toBeGreaterThanOrEqual( 2 );

			// Check first two checkboxes
			await fieldGroupCheckboxes.nth( 0 ).check();
			await fieldGroupCheckboxes.nth( 1 ).check();

			// Export
			const exportButton = page.locator( 'button[value="download"]:has-text("Export As JSON")' );
			const downloadPromise = page.waitForEvent( 'download' );
			await exportButton.click();
			const download = await downloadPromise;

			// Verify the download contains multiple field groups
			const downloadPath = path.join( os.tmpdir(), 'scf-multi-export-test.json' );
			await download.saveAs( downloadPath );

			try {
				const content = fs.readFileSync( downloadPath, 'utf-8' );
				const json = JSON.parse( content );

				// Verify it's an array with at least 2 field groups
				expect( Array.isArray( json ) ).toBe( true );
				expect( json.length ).toBeGreaterThanOrEqual( 2 );

				// Verify each exported item has the expected structure
				for ( const fieldGroup of json ) {
					expect( fieldGroup ).toHaveProperty( 'key' );
					expect( fieldGroup ).toHaveProperty( 'title' );
					expect( fieldGroup ).toHaveProperty( 'fields' );
					expect( fieldGroup.key ).toMatch( /^group_/ );
				}
			} finally {
				if ( fs.existsSync( downloadPath ) ) {
					fs.unlinkSync( downloadPath );
				}
			}
		} );
	} );

	test.describe( 'PHP Export', () => {
		test( 'should generate PHP code for field group', async ( { page, admin } ) => {
			// Create a field group
			await createTestFieldGroup( page, admin );

			// Navigate to tools page
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group&page=acf-tools' );

			// Select the field group
			const fieldGroupCheckbox = page.locator( `input[type="checkbox"][value*="group_"]` ).first();
			await expect( fieldGroupCheckbox ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );
			await fieldGroupCheckbox.check();

			// Click Generate PHP button
			const generateButton = page.locator( 'button[value="generate"]:has-text("Generate PHP")' );
			await expect( generateButton ).toBeVisible();
			await generateButton.click();

			// Wait for the PHP export page to load
			await expect( page ).toHaveURL( /.*page=acf-tools.*keys=/ );

			// Verify the PHP code textarea is present
			const phpTextarea = page.locator( '#acf-export-textarea' );
			await expect( phpTextarea ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );

			// Verify it contains PHP code with acf_add_local_field_group
			const phpContent = await phpTextarea.inputValue();
			expect( phpContent ).toContain( 'acf_add_local_field_group' );
			expect( phpContent ).toContain( TEST_FIELD_GROUP_NAME );

			// Verify the success notice
			const successNotice = page.locator( '.acf-admin-notice.notice-success, .notice.notice-success' );
			await expect( successNotice ).toBeVisible();
			await expect( successNotice ).toContainText( 'Exported' );

			// Verify copy to clipboard button exists
			const copyButton = page.locator( '#acf-export-copy' );
			await expect( copyButton ).toBeVisible();
		} );

		test( 'should include all field settings in PHP export', async ( { page, admin } ) => {
			// Create a field group with specific settings
			await createTestFieldGroup( page, admin, {
				fieldType: 'number',
				configureField: async () => {
					// Set min/max values
					const minInput = page.locator( '.acf-field-setting-min input' );
					if ( await minInput.isVisible() ) {
						await minInput.fill( '10' );
					}
					const maxInput = page.locator( '.acf-field-setting-max input' );
					if ( await maxInput.isVisible() ) {
						await maxInput.fill( '100' );
					}
				},
			} );

			// Navigate to tools and generate PHP
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group&page=acf-tools' );

			const fieldGroupCheckbox = page.locator( `input[type="checkbox"][value*="group_"]` ).first();
			await fieldGroupCheckbox.check();

			const generateButton = page.locator( 'button[value="generate"]:has-text("Generate PHP")' );
			await generateButton.click();

			await expect( page ).toHaveURL( /.*page=acf-tools.*keys=/ );

			const phpTextarea = page.locator( '#acf-export-textarea' );
			const phpContent = await phpTextarea.inputValue();

			// Verify field settings are included
			expect( phpContent ).toContain( 'number' );
		} );
	} );

	test.describe( 'JSON Import', () => {
		test( 'should show error for invalid JSON file', async ( { page, admin } ) => {
			// Create an invalid JSON file
			const invalidJsonPath = path.join( os.tmpdir(), 'scf-invalid-test.json' );
			fs.writeFileSync( invalidJsonPath, 'this is not valid json' );

			try {
				await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group&page=acf-tools' );

				const fileInput = page.locator( 'input[type="file"][name="acf_import_file"]' );
				await fileInput.setInputFiles( invalidJsonPath );

				const importButton = page.locator( 'button[name="import_type"][value="json"]:has-text("Import JSON")' );
				await importButton.click();

				// Should show warning notice
				const warningNotice = page.locator( '.acf-admin-notice.notice-warning, .notice.notice-warning' );
				await expect( warningNotice ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );
				await expect( warningNotice ).toContainText( 'empty' );
			} finally {
				if ( fs.existsSync( invalidJsonPath ) ) {
					fs.unlinkSync( invalidJsonPath );
				}
			}
		} );

		test( 'should show error when no file selected', async ( { page, admin } ) => {
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group&page=acf-tools' );

			// Click import without selecting a file
			const importButton = page.locator( 'button[name="import_type"][value="json"]:has-text("Import JSON")' );
			await importButton.click();

			// Should show warning notice
			const warningNotice = page.locator( '.acf-admin-notice.notice-warning, .notice.notice-warning' );
			await expect( warningNotice ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );
			await expect( warningNotice ).toContainText( 'No file selected' );
		} );

		test( 'should show error for empty JSON array', async ( { page, admin } ) => {
			// Create JSON with valid syntax but no content to import
			const emptyArrayPath = path.join( os.tmpdir(), 'scf-empty-array.json' );
			fs.writeFileSync( emptyArrayPath, JSON.stringify( [], null, 2 ) );

			try {
				await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group&page=acf-tools' );

				const fileInput = page.locator( 'input[type="file"][name="acf_import_file"]' );
				await fileInput.setInputFiles( emptyArrayPath );

				const importButton = page.locator( 'button[name="import_type"][value="json"]:has-text("Import JSON")' );
				await importButton.click();

				// Should show warning notice about empty import
				const warningNotice = page.locator( '.acf-admin-notice.notice-warning, .notice.notice-warning' );
				await expect( warningNotice ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );
				await expect( warningNotice ).toContainText( 'empty' );
			} finally {
				if ( fs.existsSync( emptyArrayPath ) ) {
					fs.unlinkSync( emptyArrayPath );
				}
			}
		} );

		test( 'should update existing field group on import with same key', async ( { page, admin } ) => {
			const uniqueKey = 'group_conflict_test_' + Date.now();
			const fieldKey = 'field_conflict_test_' + Date.now();

			// First, import a field group
			const originalJson = [
				{
					key: uniqueKey,
					title: 'Original Field Group',
					fields: [
						{
							key: fieldKey,
							label: 'Original Field',
							name: 'original_field',
							type: 'text',
						},
					],
					location: [
						[ { param: 'post_type', operator: '==', value: 'post' } ],
					],
				},
			];

			const jsonFilePath = path.join( os.tmpdir(), 'scf-conflict-test.json' );
			fs.writeFileSync( jsonFilePath, JSON.stringify( originalJson, null, 2 ) );

			try {
				// Import original
				await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group&page=acf-tools' );
				const fileInput = page.locator( 'input[type="file"][name="acf_import_file"]' );
				await fileInput.setInputFiles( jsonFilePath );

				const importButton = page.locator( 'button[name="import_type"][value="json"]:has-text("Import JSON")' );
				await importButton.click();

				await expect( page.locator( '.notice.notice-success, .acf-admin-notice.notice-success' ) ).toBeVisible();

				// Now import an updated version with the same key
				const updatedJson = [
					{
						key: uniqueKey,
						title: 'Updated Field Group',
						fields: [
							{
								key: fieldKey,
								label: 'Updated Field',
								name: 'updated_field',
								type: 'textarea',
							},
						],
						location: [
							[ { param: 'post_type', operator: '==', value: 'post' } ],
						],
					},
				];

				fs.writeFileSync( jsonFilePath, JSON.stringify( updatedJson, null, 2 ) );

				await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group&page=acf-tools' );
				await fileInput.setInputFiles( jsonFilePath );
				await importButton.click();

				await expect( page.locator( '.notice.notice-success, .acf-admin-notice.notice-success' ) ).toBeVisible();

				// Verify only one field group exists (not duplicated)
				await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );

				const fieldGroups = page.locator( '#the-list tr' );
				const originalCount = await fieldGroups.filter( { hasText: 'Original Field Group' } ).count();
				const updatedCount = await fieldGroups.filter( { hasText: 'Updated Field Group' } ).count();

				expect( originalCount ).toBe( 0 );
				expect( updatedCount ).toBe( 1 );
			} finally {
				if ( fs.existsSync( jsonFilePath ) ) {
					fs.unlinkSync( jsonFilePath );
				}
			}
		} );
	} );


	test.describe( 'Export/Import Round Trip', () => {
		test( 'should preserve field group data through export and re-import', async ( { page, admin } ) => {
			// Step 1: Create a field group with specific settings
			const uniqueGroupName = `Round Trip Test ${ Date.now() }`;
			const uniqueFieldLabel = 'Round Trip Field';

			await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
			const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
			await addNewButton.click();

			await page.waitForSelector( '#title' );
			await page.fill( '#title', uniqueGroupName );

			const fieldLabel = page.locator( 'input[id^="acf_fields-field_"][id$="-label"]' );
			await fieldLabel.fill( uniqueFieldLabel );

			// Get the field key for later comparison
			const fieldKeyInput = page.locator( 'input[id^="acf_fields-field_"][id$="-key"]' );
			const originalFieldKey = await fieldKeyInput.inputValue();

			const publishButton = page.locator( 'button.acf-btn.acf-publish[type="submit"]' );
			await publishButton.click();
			const successNotice = page.locator( '.updated.notice' );
			await expect( successNotice ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );

			// Step 2: Export the field group
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group&page=acf-tools' );

			const fieldGroupCheckbox = page.locator( `input[type="checkbox"][value*="group_"]` ).first();
			await expect( fieldGroupCheckbox ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );
			await fieldGroupCheckbox.check();

			const exportButton = page.locator( 'button[value="download"]:has-text("Export As JSON")' );
			const downloadPromise = page.waitForEvent( 'download' );
			await exportButton.click();
			const download = await downloadPromise;

			const exportPath = path.join( os.tmpdir(), 'scf-round-trip-export.json' );
			await download.saveAs( exportPath );

			// Read and store the exported data
			const exportedContent = fs.readFileSync( exportPath, 'utf-8' );
			const exportedJson = JSON.parse( exportedContent );

			expect( Array.isArray( exportedJson ) ).toBe( true );
			expect( exportedJson.length ).toBe( 1 );

			const exportedGroup = exportedJson[ 0 ];
			expect( exportedGroup.title ).toBe( uniqueGroupName );
			expect( exportedGroup.fields ).toHaveLength( 1 );
			expect( exportedGroup.fields[ 0 ].label ).toBe( uniqueFieldLabel );

			// Step 3: Delete the field group
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
			const groupRow = page.locator( `#the-list tr:has-text("${ uniqueGroupName }")` );
			await expect( groupRow ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );

			await groupRow.locator( 'th.check-column input[type="checkbox"]' ).check();
			await page.selectOption( '#bulk-action-selector-bottom', 'trash' );
			await page.click( '#doaction2' );
			await page.waitForLoadState( 'networkidle' );

			// Verify it's deleted
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
			const deletedGroupRow = page.locator( `#the-list tr:has-text("${ uniqueGroupName }")` );
			await expect( deletedGroupRow ).not.toBeVisible();

			// Step 4: Import the exported JSON
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group&page=acf-tools' );

			const importSection = page.locator( '.acf-postbox-header:has-text("Import")' );
			await expect( importSection ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );

			// Upload the exported file
			const fileInput = page.locator( 'input[type="file"][name="acf_import_file"]' );
			await fileInput.setInputFiles( exportPath );

			const importButton = page.locator( 'button[name="import_type"][value="json"]:has-text("Import JSON")' );
			await importButton.click();

			// Wait for success message
			const importSuccess = page.locator( '.notice-success, .updated:has-text("Imported"), .acf-notice.-success' );
			await expect( importSuccess.first() ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );

			// Step 5: Verify the imported field group matches the original
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
			const importedGroupRow = page.locator( `#the-list tr:has-text("${ uniqueGroupName }")` );
			await expect( importedGroupRow ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );

			// Click to edit the imported field group
			await importedGroupRow.locator( 'a.row-title' ).click();
			await page.waitForSelector( '#title' );

			// Verify the title matches
			const importedTitle = await page.locator( '#title' ).inputValue();
			expect( importedTitle ).toBe( uniqueGroupName );

			// Verify the field exists in the list with correct label
			const fieldRow = page.locator( `.acf-field-object:has-text("${ uniqueFieldLabel }")` );
			await expect( fieldRow ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );

			// Click to expand the field to access its settings
			await fieldRow.locator( 'a.edit-field, button.edit-field' ).first().click();
			await page.waitForTimeout( 500 );

			// Verify the field label matches - use the field setting container
			const labelSetting = fieldRow.locator( '.acf-field-setting-label input[type="text"]' );
			await labelSetting.waitFor( { state: 'visible', timeout: DEFAULT_TIMEOUT } );
			const importedLabelValue = await labelSetting.inputValue();
			expect( importedLabelValue ).toBe( uniqueFieldLabel );

			// Verify the field type is preserved
			const fieldTypeSelect = fieldRow.locator( '.acf-field-setting-type select' );
			const importedFieldType = await fieldTypeSelect.inputValue();
			expect( importedFieldType ).toBe( 'text' );

			// Cleanup
			if ( fs.existsSync( exportPath ) ) {
				fs.unlinkSync( exportPath );
			}
		} );

		test( 'should preserve post type data through export and re-import', async ( { page, admin } ) => {
			// Step 1: Create a post type
			const uniqueName = `RT Post Type ${ Date.now() }`;
			const uniqueSlug = 'rt_pt_' + Date.now().toString().slice( -8 );

			await admin.visitAdminPage( 'edit.php', 'post_type=acf-post-type' );
			const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
			await addNewButton.click();

			await page.fill( '#acf_post_type-labels-name', uniqueName );
			// Fill slug BEFORE singular_name to prevent auto-generation by JS
			await page.fill( '#acf_post_type-post_type', uniqueSlug );
			await page.fill( '#acf_post_type-labels-singular_name', 'RT Post' );

			await page.click( 'button.acf-btn.acf-publish[type="submit"]' );
			await expect( page.locator( '.updated.notice' ) ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );

			// Step 2: Export the post type
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group&page=acf-tools' );

			const postTypeSection = page.locator( '.acf-field:has-text("Select Post Types")' );
			await expect( postTypeSection ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );

			// Find and select our post type
			const ourCheckbox = postTypeSection.locator( `label:has-text("${ uniqueName }")` ).locator( 'input[type="checkbox"]' );
			await ourCheckbox.first().check();

			const exportButton = page.locator( 'button[value="download"]:has-text("Export As JSON")' );
			const downloadPromise = page.waitForEvent( 'download' );
			await exportButton.click();
			const download = await downloadPromise;

			const exportPath = path.join( os.tmpdir(), 'scf-round-trip-pt-export.json' );
			await download.saveAs( exportPath );

			// Verify exported JSON contains the post type
			const exportedContent = fs.readFileSync( exportPath, 'utf-8' );
			const exportedJson = JSON.parse( exportedContent );
			expect( Array.isArray( exportedJson ) ).toBe( true );
			expect( exportedJson.length ).toBeGreaterThan( 0 );
			// Check that the exported item has post type structure (post_type property exists)
			const exportedPt = exportedJson.find( ( item ) => item.post_type === uniqueSlug || item.title === uniqueName );
			expect( exportedPt ).toBeDefined();
			// Verify the exported JSON has the correct slug
			expect( exportedPt.post_type ).toBe( uniqueSlug );

			// Step 3: Delete the post type
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-post-type' );
			const ptRow = page.locator( `#the-list tr:has-text("${ uniqueName }")` );
			await ptRow.locator( 'th.check-column input[type="checkbox"]' ).check();
			await page.selectOption( '#bulk-action-selector-bottom', 'trash' );
			await page.click( '#doaction2' );
			await page.waitForLoadState( 'networkidle' );

			// Verify deleted
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-post-type' );
			await expect( page.locator( `#the-list tr:has-text("${ uniqueName }")` ) ).not.toBeVisible();

			// Step 4: Import the exported JSON
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group&page=acf-tools' );

			const fileInput = page.locator( 'input[type="file"][name="acf_import_file"]' );
			await fileInput.setInputFiles( exportPath );

			const importButton = page.locator( 'button[name="import_type"][value="json"]:has-text("Import JSON")' );
			await importButton.click();

			const importSuccess = page.locator( '.notice-success, .updated:has-text("Imported"), .acf-notice.-success' );
			await expect( importSuccess.first() ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );

			// Step 5: Verify post type was restored
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-post-type' );
			const importedRow = page.locator( `#the-list tr:has-text("${ uniqueName }")` );
			await expect( importedRow ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );

			// Click to edit and verify settings were preserved
			await importedRow.locator( 'a.row-title' ).click();
			await page.waitForSelector( '#acf_post_type-labels-name' );

			// Verify the plural name is preserved
			const importedPluralName = await page.locator( '#acf_post_type-labels-name' ).inputValue();
			expect( importedPluralName ).toBe( uniqueName );

			// Verify the singular name is preserved
			const importedSingularName = await page.locator( '#acf_post_type-labels-singular_name' ).inputValue();
			expect( importedSingularName ).toBe( 'RT Post' );

			// Cleanup: Delete the imported post type
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-post-type' );
			const cleanupRow = page.locator( `#the-list tr:has-text("${ uniqueName }")` );
			if ( await cleanupRow.isVisible().catch( () => false ) ) {
				await cleanupRow.locator( 'th.check-column input[type="checkbox"]' ).check();
				await page.selectOption( '#bulk-action-selector-bottom', 'trash' );
				await page.click( '#doaction2' );
				await page.waitForLoadState( 'networkidle' );
			}

			// Cleanup temp file
			if ( fs.existsSync( exportPath ) ) {
				fs.unlinkSync( exportPath );
			}
		} );

		test( 'should preserve taxonomy data through export and re-import', async ( { page, admin } ) => {
			// Step 1: Create a taxonomy
			const uniqueName = `RT Taxonomy ${ Date.now() }`;
			const uniqueSlug = 'rt_tax_' + Date.now().toString().slice( -8 );

			await admin.visitAdminPage( 'edit.php', 'post_type=acf-taxonomy' );
			const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
			await addNewButton.click();

			await page.fill( '#acf_taxonomy-labels-name', uniqueName );
			// Fill slug BEFORE singular_name to prevent auto-generation by JS
			await page.fill( '#acf_taxonomy-taxonomy', uniqueSlug );
			await page.fill( '#acf_taxonomy-labels-singular_name', 'RT Term' );

			await page.click( 'button.acf-btn.acf-publish[type="submit"]' );
			await expect( page.locator( '.updated.notice' ) ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );

			// Step 2: Export the taxonomy
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group&page=acf-tools' );

			const taxonomySection = page.locator( '.acf-field:has-text("Select Taxonomies")' );
			await expect( taxonomySection ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );

			// Find and select our taxonomy
			const ourCheckbox = taxonomySection.locator( `label:has-text("${ uniqueName }")` ).locator( 'input[type="checkbox"]' );
			await ourCheckbox.first().check();

			const exportButton = page.locator( 'button[value="download"]:has-text("Export As JSON")' );
			const downloadPromise = page.waitForEvent( 'download' );
			await exportButton.click();
			const download = await downloadPromise;

			const exportPath = path.join( os.tmpdir(), 'scf-round-trip-tax-export.json' );
			await download.saveAs( exportPath );

			// Verify exported JSON contains the taxonomy
			const exportedContent = fs.readFileSync( exportPath, 'utf-8' );
			const exportedJson = JSON.parse( exportedContent );
			expect( Array.isArray( exportedJson ) ).toBe( true );
			expect( exportedJson.length ).toBeGreaterThan( 0 );
			// Check that the exported item has taxonomy structure (taxonomy property exists)
			const exportedTax = exportedJson.find( ( item ) => item.taxonomy === uniqueSlug || item.title === uniqueName );
			expect( exportedTax ).toBeDefined();
			// Verify the exported JSON has the correct slug
			expect( exportedTax.taxonomy ).toBe( uniqueSlug );

			// Step 3: Delete the taxonomy
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-taxonomy' );
			const taxRow = page.locator( `#the-list tr:has-text("${ uniqueName }")` );
			await taxRow.locator( 'th.check-column input[type="checkbox"]' ).check();
			await page.selectOption( '#bulk-action-selector-bottom', 'trash' );
			await page.click( '#doaction2' );
			await page.waitForLoadState( 'networkidle' );

			// Verify deleted
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-taxonomy' );
			await expect( page.locator( `#the-list tr:has-text("${ uniqueName }")` ) ).not.toBeVisible();

			// Step 4: Import the exported JSON
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group&page=acf-tools' );

			const fileInput = page.locator( 'input[type="file"][name="acf_import_file"]' );
			await fileInput.setInputFiles( exportPath );

			const importButton = page.locator( 'button[name="import_type"][value="json"]:has-text("Import JSON")' );
			await importButton.click();

			const importSuccess = page.locator( '.notice-success, .updated:has-text("Imported"), .acf-notice.-success' );
			await expect( importSuccess.first() ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );

			// Step 5: Verify taxonomy was restored
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-taxonomy' );
			const importedRow = page.locator( `#the-list tr:has-text("${ uniqueName }")` );
			await expect( importedRow ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );

			// Click to edit and verify settings were preserved
			await importedRow.locator( 'a.row-title' ).click();
			await page.waitForSelector( '#acf_taxonomy-labels-name' );

			// Verify the plural name is preserved
			const importedPluralName = await page.locator( '#acf_taxonomy-labels-name' ).inputValue();
			expect( importedPluralName ).toBe( uniqueName );

			// Verify the singular name is preserved
			const importedSingularName = await page.locator( '#acf_taxonomy-labels-singular_name' ).inputValue();
			expect( importedSingularName ).toBe( 'RT Term' );

			// Cleanup: Delete the imported taxonomy
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-taxonomy' );
			const cleanupRow = page.locator( `#the-list tr:has-text("${ uniqueName }")` );
			if ( await cleanupRow.isVisible().catch( () => false ) ) {
				await cleanupRow.locator( 'th.check-column input[type="checkbox"]' ).check();
				await page.selectOption( '#bulk-action-selector-bottom', 'trash' );
				await page.click( '#doaction2' );
				await page.waitForLoadState( 'networkidle' );
			}

			// Cleanup temp file
			if ( fs.existsSync( exportPath ) ) {
				fs.unlinkSync( exportPath );
			}
		} );

		test( 'should preserve options page data through export and re-import', async ( { page, admin } ) => {
			// Step 1: Create an options page
			const uniqueName = `RT Options ${ Date.now() }`;
			const uniqueSlug = 'rt_opt_' + Date.now().toString().slice( -8 );

			await admin.visitAdminPage( 'edit.php', 'post_type=acf-ui-options-page' );
			const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
			await addNewButton.click();

			// Fill slug BEFORE page_title to prevent auto-generation by JS
			await page.fill( '#acf_ui_options_page-menu_slug', uniqueSlug );
			await page.fill( '#acf_ui_options_page-page_title', uniqueName );

			await page.click( 'button.acf-btn.acf-publish[type="submit"]' );
			await expect( page.locator( '.updated.notice' ) ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );

			// Step 2: Export the options page
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group&page=acf-tools' );

			const optionsSection = page.locator( '.acf-field:has-text("Select Options Pages")' );
			await expect( optionsSection ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );

			// Find and select our options page
			const ourCheckbox = optionsSection.locator( `label:has-text("${ uniqueName }")` ).locator( 'input[type="checkbox"]' );
			await ourCheckbox.first().check();

			const exportButton = page.locator( 'button[value="download"]:has-text("Export As JSON")' );
			const downloadPromise = page.waitForEvent( 'download' );
			await exportButton.click();
			const download = await downloadPromise;

			const exportPath = path.join( os.tmpdir(), 'scf-round-trip-opt-export.json' );
			await download.saveAs( exportPath );

			// Verify exported JSON contains the options page
			const exportedContent = fs.readFileSync( exportPath, 'utf-8' );
			const exportedJson = JSON.parse( exportedContent );
			expect( Array.isArray( exportedJson ) ).toBe( true );
			expect( exportedJson.length ).toBeGreaterThan( 0 );
			// Check that the exported item has options page structure (menu_slug property exists)
			const exportedOpt = exportedJson.find( ( item ) => item.menu_slug === uniqueSlug || item.title === uniqueName );
			expect( exportedOpt ).toBeDefined();
			// Verify the exported JSON has the correct slug
			expect( exportedOpt.menu_slug ).toBe( uniqueSlug );

			// Step 3: Delete the options page
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-ui-options-page' );
			const optRow = page.locator( `#the-list tr:has-text("${ uniqueName }")` );
			await optRow.locator( 'th.check-column input[type="checkbox"]' ).check();
			await page.selectOption( '#bulk-action-selector-bottom', 'trash' );
			await page.click( '#doaction2' );
			await page.waitForLoadState( 'networkidle' );

			// Verify deleted
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-ui-options-page' );
			await expect( page.locator( `#the-list tr:has-text("${ uniqueName }")` ) ).not.toBeVisible();

			// Step 4: Import the exported JSON
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group&page=acf-tools' );

			const fileInput = page.locator( 'input[type="file"][name="acf_import_file"]' );
			await fileInput.setInputFiles( exportPath );

			const importButton = page.locator( 'button[name="import_type"][value="json"]:has-text("Import JSON")' );
			await importButton.click();

			const importSuccess = page.locator( '.notice-success, .updated:has-text("Imported"), .acf-notice.-success' );
			await expect( importSuccess.first() ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );

			// Step 5: Verify options page was restored
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-ui-options-page' );
			const importedRow = page.locator( `#the-list tr:has-text("${ uniqueName }")` );
			await expect( importedRow ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );

			// Click to edit and verify settings were preserved
			await importedRow.locator( 'a.row-title' ).click();
			await page.waitForSelector( '#acf_ui_options_page-page_title' );

			// Verify the page title is preserved
			const importedPageTitle = await page.locator( '#acf_ui_options_page-page_title' ).inputValue();
			expect( importedPageTitle ).toBe( uniqueName );

			// Verify the menu slug is preserved
			const importedMenuSlug = await page.locator( '#acf_ui_options_page-menu_slug' ).inputValue();
			expect( importedMenuSlug ).toBe( uniqueSlug );

			// Cleanup: Delete the imported options page
			await admin.visitAdminPage( 'edit.php', 'post_type=acf-ui-options-page' );
			const cleanupRow = page.locator( `#the-list tr:has-text("${ uniqueName }")` );
			if ( await cleanupRow.isVisible().catch( () => false ) ) {
				await cleanupRow.locator( 'th.check-column input[type="checkbox"]' ).check();
				await page.selectOption( '#bulk-action-selector-bottom', 'trash' );
				await page.click( '#doaction2' );
				await page.waitForLoadState( 'networkidle' );
			}

			// Cleanup temp file
			if ( fs.existsSync( exportPath ) ) {
				fs.unlinkSync( exportPath );
			}
		} );
	} );

	test.describe( 'Import Overwrite', () => {
		test( 'should update existing post type on import with same key', async ( { page, admin } ) => {
			const uniqueKey = 'post_type_overwrite_' + Date.now().toString().slice( -6 );
			const originalName = 'Original Post Type';
			const updatedName = 'Updated Post Type';

			// First, create and import a post type
			const originalJson = [
				{
					key: uniqueKey,
					title: originalName,
					post_type: 'ovr_pt_orig',
					labels: {
						name: originalName,
						singular_name: 'Original PT',
					},
					public: true,
					active: true,
				},
			];

			const jsonFilePath = path.join( os.tmpdir(), 'scf-overwrite-pt-test.json' );
			fs.writeFileSync( jsonFilePath, JSON.stringify( originalJson, null, 2 ) );

			try {
				// Import original
				await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group&page=acf-tools' );
				const fileInput = page.locator( 'input[type="file"][name="acf_import_file"]' );
				await fileInput.setInputFiles( jsonFilePath );

				const importButton = page.locator( 'button[name="import_type"][value="json"]:has-text("Import JSON")' );
				await importButton.click();

				await expect( page.locator( '.notice.notice-success, .acf-admin-notice.notice-success' ) ).toBeVisible();

				// Verify original was imported
				await admin.visitAdminPage( 'edit.php', 'post_type=acf-post-type' );
				await expect( page.locator( `#the-list tr:has-text("${ originalName }")` ) ).toBeVisible();

				// Now import an updated version with the same key
				const updatedJson = [
					{
						key: uniqueKey,
						title: updatedName,
						post_type: 'ovr_pt_upd',
						labels: {
							name: updatedName,
							singular_name: 'Updated PT',
						},
						public: true,
						active: true,
					},
				];

				fs.writeFileSync( jsonFilePath, JSON.stringify( updatedJson, null, 2 ) );

				await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group&page=acf-tools' );
				await fileInput.setInputFiles( jsonFilePath );
				await importButton.click();

				await expect( page.locator( '.notice.notice-success, .acf-admin-notice.notice-success' ) ).toBeVisible();

				// Verify only one post type exists (not duplicated) and it has the updated name
				await admin.visitAdminPage( 'edit.php', 'post_type=acf-post-type' );

				const postTypes = page.locator( '#the-list tr' );
				const originalCount = await postTypes.filter( { hasText: originalName } ).count();
				const updatedCount = await postTypes.filter( { hasText: updatedName } ).count();

				expect( originalCount ).toBe( 0 );
				expect( updatedCount ).toBe( 1 );

				// Cleanup
				const cleanupRow = page.locator( `#the-list tr:has-text("${ updatedName }")` );
				if ( await cleanupRow.isVisible().catch( () => false ) ) {
					await cleanupRow.locator( 'th.check-column input[type="checkbox"]' ).check();
					await page.selectOption( '#bulk-action-selector-bottom', 'trash' );
					await page.click( '#doaction2' );
					await page.waitForLoadState( 'networkidle' );
				}
			} finally {
				if ( fs.existsSync( jsonFilePath ) ) {
					fs.unlinkSync( jsonFilePath );
				}
			}
		} );

		test( 'should update existing taxonomy on import with same key', async ( { page, admin } ) => {
			const uniqueKey = 'taxonomy_overwrite_' + Date.now().toString().slice( -6 );
			const originalName = 'Original Taxonomy';
			const updatedName = 'Updated Taxonomy';

			// First, create and import a taxonomy
			const originalJson = [
				{
					key: uniqueKey,
					title: originalName,
					taxonomy: 'ovr_tax_orig',
					labels: {
						name: originalName,
						singular_name: 'Original Tax',
					},
					public: true,
					active: true,
				},
			];

			const jsonFilePath = path.join( os.tmpdir(), 'scf-overwrite-tax-test.json' );
			fs.writeFileSync( jsonFilePath, JSON.stringify( originalJson, null, 2 ) );

			try {
				// Import original
				await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group&page=acf-tools' );
				const fileInput = page.locator( 'input[type="file"][name="acf_import_file"]' );
				await fileInput.setInputFiles( jsonFilePath );

				const importButton = page.locator( 'button[name="import_type"][value="json"]:has-text("Import JSON")' );
				await importButton.click();

				await expect( page.locator( '.notice.notice-success, .acf-admin-notice.notice-success' ) ).toBeVisible();

				// Verify original was imported
				await admin.visitAdminPage( 'edit.php', 'post_type=acf-taxonomy' );
				await expect( page.locator( `#the-list tr:has-text("${ originalName }")` ) ).toBeVisible();

				// Now import an updated version with the same key
				const updatedJson = [
					{
						key: uniqueKey,
						title: updatedName,
						taxonomy: 'ovr_tax_upd',
						labels: {
							name: updatedName,
							singular_name: 'Updated Tax',
						},
						public: true,
						active: true,
					},
				];

				fs.writeFileSync( jsonFilePath, JSON.stringify( updatedJson, null, 2 ) );

				await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group&page=acf-tools' );
				await fileInput.setInputFiles( jsonFilePath );
				await importButton.click();

				await expect( page.locator( '.notice.notice-success, .acf-admin-notice.notice-success' ) ).toBeVisible();

				// Verify only one taxonomy exists (not duplicated) and it has the updated name
				await admin.visitAdminPage( 'edit.php', 'post_type=acf-taxonomy' );

				const taxonomies = page.locator( '#the-list tr' );
				const originalCount = await taxonomies.filter( { hasText: originalName } ).count();
				const updatedCount = await taxonomies.filter( { hasText: updatedName } ).count();

				expect( originalCount ).toBe( 0 );
				expect( updatedCount ).toBe( 1 );

				// Cleanup
				const cleanupRow = page.locator( `#the-list tr:has-text("${ updatedName }")` );
				if ( await cleanupRow.isVisible().catch( () => false ) ) {
					await cleanupRow.locator( 'th.check-column input[type="checkbox"]' ).check();
					await page.selectOption( '#bulk-action-selector-bottom', 'trash' );
					await page.click( '#doaction2' );
					await page.waitForLoadState( 'networkidle' );
				}
			} finally {
				if ( fs.existsSync( jsonFilePath ) ) {
					fs.unlinkSync( jsonFilePath );
				}
			}
		} );

		test( 'should update existing options page on import with same key', async ( { page, admin } ) => {
			// Key must start with 'ui_options_page_' to be recognized as a valid options page key
			const uniqueKey = 'ui_options_page_' + Date.now().toString().slice( -8 );
			const originalName = 'Original Options Page';
			const updatedName = 'Updated Options Page';

			// First, create and import an options page
			const originalJson = [
				{
					key: uniqueKey,
					title: originalName,
					page_title: originalName,
					menu_slug: 'ovr_opt_orig',
					capability: 'manage_options',
					active: true,
				},
			];

			const jsonFilePath = path.join( os.tmpdir(), 'scf-overwrite-opt-test.json' );
			fs.writeFileSync( jsonFilePath, JSON.stringify( originalJson, null, 2 ) );

			try {
				// Import original
				await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group&page=acf-tools' );
				const fileInput = page.locator( 'input[type="file"][name="acf_import_file"]' );
				await fileInput.setInputFiles( jsonFilePath );

				const importButton = page.locator( 'button[name="import_type"][value="json"]:has-text("Import JSON")' );
				await importButton.click();

				await expect( page.locator( '.notice.notice-success, .acf-admin-notice.notice-success' ) ).toBeVisible();

				// Verify original was imported
				await admin.visitAdminPage( 'edit.php', 'post_type=acf-ui-options-page' );
				await expect( page.locator( `#the-list tr:has-text("${ originalName }")` ) ).toBeVisible();

				// Now import an updated version with the same key
				const updatedJson = [
					{
						key: uniqueKey,
						title: updatedName,
						page_title: updatedName,
						menu_slug: 'ovr_opt_upd',
						capability: 'manage_options',
						active: true,
					},
				];

				fs.writeFileSync( jsonFilePath, JSON.stringify( updatedJson, null, 2 ) );

				await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group&page=acf-tools' );
				await fileInput.setInputFiles( jsonFilePath );
				await importButton.click();

				await expect( page.locator( '.notice.notice-success, .acf-admin-notice.notice-success' ) ).toBeVisible();

				// Verify only one options page exists (not duplicated) and it has the updated name
				await admin.visitAdminPage( 'edit.php', 'post_type=acf-ui-options-page' );

				const optionsPages = page.locator( '#the-list tr' );
				const originalCount = await optionsPages.filter( { hasText: originalName } ).count();
				const updatedCount = await optionsPages.filter( { hasText: updatedName } ).count();

				expect( originalCount ).toBe( 0 );
				expect( updatedCount ).toBe( 1 );

				// Cleanup
				const cleanupRow = page.locator( `#the-list tr:has-text("${ updatedName }")` );
				if ( await cleanupRow.isVisible().catch( () => false ) ) {
					await cleanupRow.locator( 'th.check-column input[type="checkbox"]' ).check();
					await page.selectOption( '#bulk-action-selector-bottom', 'trash' );
					await page.click( '#doaction2' );
					await page.waitForLoadState( 'networkidle' );
				}
			} finally {
				if ( fs.existsSync( jsonFilePath ) ) {
					fs.unlinkSync( jsonFilePath );
				}
			}
		} );
	} );
} );

/**
 * Helper function to create a test field group.
 */
async function createTestFieldGroup( page, admin, options = {} ) {
	const { fieldType = 'text', configureField } = options;

	await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
	const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
	await addNewButton.click();

	await page.waitForSelector( '#title' );
	await page.fill( '#title', TEST_FIELD_GROUP_NAME );

	const fieldLabel = page.locator( 'input[id^="acf_fields-field_"][id$="-label"]' );
	await fieldLabel.fill( TEST_FIELD_LABEL );

	if ( fieldType !== 'text' ) {
		const fieldTypeSelect = page.locator( 'select[id^="acf_fields-field_"][id$="-type"]' );
		await fieldTypeSelect.selectOption( fieldType );
		await page.waitForTimeout( 500 );
	}

	if ( configureField ) {
		await configureField();
	}

	const publishButton = page.locator( 'button.acf-btn.acf-publish[type="submit"]' );
	await publishButton.click();

	const successNotice = page.locator( '.updated.notice' );
	await expect( successNotice ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );
}

/**
 * Helper to clean up test entities.
 */
async function cleanupTestEntities( page, admin ) {
	// Clean up test post types
	await admin.visitAdminPage( 'edit.php', 'post_type=acf-post-type' );
	const ptRow = page.locator( `#the-list tr:has-text("${ TEST_POST_TYPE_NAME }")` );
	if ( await ptRow.isVisible().catch( () => false ) ) {
		await ptRow.locator( 'th.check-column input[type="checkbox"]' ).check();
		await page.selectOption( '#bulk-action-selector-bottom', 'trash' );
		await page.click( '#doaction2' );
		await page.waitForLoadState( 'networkidle' );

		// Empty trash
		await admin.visitAdminPage( 'edit.php', 'post_status=trash&post_type=acf-post-type' );
		const emptyTrash = page.locator( 'input[name="delete_all"][value="Empty Trash"]' );
		if ( await emptyTrash.isVisible().catch( () => false ) ) {
			await emptyTrash.click();
			await page.waitForLoadState( 'networkidle' );
		}
	}

	// Clean up test taxonomies
	await admin.visitAdminPage( 'edit.php', 'post_type=acf-taxonomy' );
	const taxRow = page.locator( `#the-list tr:has-text("${ TEST_TAXONOMY_NAME }")` );
	if ( await taxRow.isVisible().catch( () => false ) ) {
		await taxRow.locator( 'th.check-column input[type="checkbox"]' ).check();
		await page.selectOption( '#bulk-action-selector-bottom', 'trash' );
		await page.click( '#doaction2' );
		await page.waitForLoadState( 'networkidle' );

		// Empty trash
		await admin.visitAdminPage( 'edit.php', 'post_status=trash&post_type=acf-taxonomy' );
		const emptyTrash = page.locator( 'input[name="delete_all"][value="Empty Trash"]' );
		if ( await emptyTrash.isVisible().catch( () => false ) ) {
			await emptyTrash.click();
			await page.waitForLoadState( 'networkidle' );
		}
	}

	// Also clean up any imported field groups
	await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
	const importedRows = page.locator( '#the-list tr:has-text("Imported")' );
	if ( await importedRows.first().isVisible().catch( () => false ) ) {
		const selectAll = page.locator( 'input#cb-select-all-1' );
		await selectAll.check();
		await page.selectOption( '#bulk-action-selector-bottom', 'trash' );
		await page.click( '#doaction2' );
		await page.waitForLoadState( 'networkidle' );
	}
}
