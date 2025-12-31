/**
 * E2E tests for the File field type.
 *
 * Tests the File field which allows users to upload and select
 * files from the WordPress media library.
 */
const { test, expect } = require( './fixtures' );
const {
	PLUGIN_SLUG,
	deleteFieldGroups,
	waitForMetaBoxes,
} = require( './field-helpers' );
const path = require( 'path' );

const TEST_PLUGIN_SLUG = 'scf-test-plugin-all-field-types';
const FIELD_GROUP_LABEL = 'File Field Test';
// We'll use the test image as a file for upload
const TEST_FILE_PATH = path.join( __dirname, 'assets', 'test-image.png' );

test.describe( 'Field Type > File', () => {
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

	test( 'should create a file field and upload a file', async ( {
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
		await fieldLabel.fill( 'Test File' );

		// Select file type
		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'file' );

		// Publish
		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create test post
		const post = await requestUtils.createPost( {
			title: 'File Field Test Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Click "Add File" button
		const addFileButton = page.locator(
			'.acf-field[data-name="test_file"] .acf-file-uploader a[data-name="add"]'
		);
		await addFileButton.click();

		// Wait for media modal
		await page.waitForSelector( '.media-modal', { state: 'visible' } );

		// Click "Upload files" tab
		const uploadTab = page.locator( '.media-modal #menu-item-upload' );
		if ( await uploadTab.isVisible() ) {
			await uploadTab.click();
		}

		// Upload the file
		const fileInput = page.locator( '.media-modal input[type="file"]' );
		await fileInput.setInputFiles( TEST_FILE_PATH );

		// Wait for upload to complete
		await page.waitForSelector( '.media-modal .attachment.selected', {
			state: 'visible',
			timeout: 30000,
		} );

		// Click "Select" button
		const selectButton = page.locator(
			'.media-modal .media-toolbar-primary .media-button-select'
		);
		await selectButton.click();

		// Wait for modal to close
		await page.waitForSelector( '.media-modal', { state: 'hidden' } );

		// Verify file is displayed in the field
		const fileInfo = page.locator(
			'.acf-field[data-name="test_file"] .acf-file-uploader .file-info'
		);
		await expect( fileInfo ).toBeVisible();
		await expect( fileInfo ).toContainText( 'test-image' );

		// Preview and verify
		const previewPage = await editor.openPreviewPage();

		const fileOutput = previewPage.locator( '#scf-test-test_file' );
		await expect( fileOutput ).toBeVisible();

		const fileLink = fileOutput.locator( 'a.scf-test-file' );
		await expect( fileLink ).toBeVisible();
		await expect( fileLink ).toContainText( 'test-image' );

		await previewPage.close();
	} );

	test( 'should allow removing a file', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Create field group
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'File Remove Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Removable File' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'file' );

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create post and add file
		const post = await requestUtils.createPost( {
			title: 'File Remove Test Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Add file
		const addFileButton = page.locator(
			'.acf-field[data-name="removable_file"] .acf-file-uploader a[data-name="add"]'
		);
		await addFileButton.click();

		await page.waitForSelector( '.media-modal', { state: 'visible' } );

		const uploadTab = page.locator( '.media-modal #menu-item-upload' );
		if ( await uploadTab.isVisible() ) {
			await uploadTab.click();
		}

		const fileInput = page.locator( '.media-modal input[type="file"]' );
		await fileInput.setInputFiles( TEST_FILE_PATH );

		await page.waitForSelector( '.media-modal .attachment.selected', {
			state: 'visible',
			timeout: 30000,
		} );

		const selectButton = page.locator(
			'.media-modal .media-toolbar-primary .media-button-select'
		);
		await selectButton.click();

		await page.waitForSelector( '.media-modal', { state: 'hidden' } );

		// Verify file is there
		const fileInfo = page.locator(
			'.acf-field[data-name="removable_file"] .acf-file-uploader .file-info'
		);
		await expect( fileInfo ).toBeVisible();

		// Remove the file
		const removeButton = page.locator(
			'.acf-field[data-name="removable_file"] .acf-file-uploader a[data-name="remove"]'
		);
		await removeButton.click();

		// Verify file is removed
		await expect( addFileButton ).toBeVisible();
		await expect( fileInfo ).not.toBeVisible();
	} );
} );
