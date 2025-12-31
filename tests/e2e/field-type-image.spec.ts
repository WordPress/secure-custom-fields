/**
 * E2E tests for the Image field type.
 *
 * Tests the Image field which allows users to upload and select
 * images from the WordPress media library.
 */
const { test, expect } = require( './fixtures' );
const {
	PLUGIN_SLUG,
	deleteFieldGroups,
	waitForMetaBoxes,
} = require( './field-helpers' );
const path = require( 'path' );

const TEST_PLUGIN_SLUG = 'scf-test-plugin-all-field-types';
const FIELD_GROUP_LABEL = 'Image Field Test';
const TEST_IMAGE_PATH = path.join( __dirname, 'assets', 'test-image.png' );

test.describe( 'Field Type > Image', () => {
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

	test( 'should create an image field and upload an image', async ( {
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
		await fieldLabel.fill( 'Test Image' );

		// Select image type
		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'image' );

		// Publish
		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create test post
		const post = await requestUtils.createPost( {
			title: 'Image Field Test Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Click "Add Image" button
		const addImageButton = page.locator(
			'.acf-field[data-name="test_image"] .acf-image-uploader[data-uploader="wp"] .acf-button-edit, .acf-field[data-name="test_image"] .acf-image-uploader a[data-name="add"]'
		);
		await addImageButton.click();

		// Wait for media modal
		await page.waitForSelector( '.media-modal', { state: 'visible' } );

		// Click "Upload files" tab
		const uploadTab = page.locator( '.media-modal #menu-item-upload' );
		if ( await uploadTab.isVisible() ) {
			await uploadTab.click();
		}

		// Upload the file
		const fileInput = page.locator( '.media-modal input[type="file"]' );
		await fileInput.setInputFiles( TEST_IMAGE_PATH );

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

		// Verify image is displayed in the field
		const imagePreview = page.locator(
			'.acf-field[data-name="test_image"] .acf-image-uploader img'
		);
		await expect( imagePreview ).toBeVisible();

		// Preview and verify
		const previewPage = await editor.openPreviewPage();

		const imageOutput = previewPage.locator( '#scf-test-test_image' );
		await expect( imageOutput ).toBeVisible();

		const outputImage = imageOutput.locator( 'img.scf-test-image' );
		await expect( outputImage ).toBeVisible();
		await expect( outputImage ).toHaveAttribute( 'src', /test-image/ );

		await previewPage.close();
	} );

	test( 'should allow removing an image', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Create field group
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'Image Remove Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Removable Image' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'image' );

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create post and add image
		const post = await requestUtils.createPost( {
			title: 'Image Remove Test Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Add image
		const addImageButton = page.locator(
			'.acf-field[data-name="removable_image"] .acf-image-uploader a[data-name="add"]'
		);
		await addImageButton.click();

		await page.waitForSelector( '.media-modal', { state: 'visible' } );

		const uploadTab = page.locator( '.media-modal #menu-item-upload' );
		if ( await uploadTab.isVisible() ) {
			await uploadTab.click();
		}

		const fileInput = page.locator( '.media-modal input[type="file"]' );
		await fileInput.setInputFiles( TEST_IMAGE_PATH );

		await page.waitForSelector( '.media-modal .attachment.selected', {
			state: 'visible',
			timeout: 30000,
		} );

		const selectButton = page.locator(
			'.media-modal .media-toolbar-primary .media-button-select'
		);
		await selectButton.click();

		await page.waitForSelector( '.media-modal', { state: 'hidden' } );

		// Verify image is there
		const imagePreview = page.locator(
			'.acf-field[data-name="removable_image"] .acf-image-uploader img'
		);
		await expect( imagePreview ).toBeVisible();

		// Remove the image
		const removeButton = page.locator(
			'.acf-field[data-name="removable_image"] .acf-image-uploader a[data-name="remove"]'
		);
		await removeButton.click();

		// Verify image is removed (add button should be visible again)
		await expect( addImageButton ).toBeVisible();
		await expect( imagePreview ).not.toBeVisible();
	} );
} );
