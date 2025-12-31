/**
 * E2E tests for the Gallery field type.
 *
 * Tests the Gallery field which allows users to upload and manage
 * multiple images from the WordPress media library.
 */
const { test, expect } = require( './fixtures' );
const {
	PLUGIN_SLUG,
	deleteFieldGroups,
	waitForMetaBoxes,
} = require( './field-helpers' );
const path = require( 'path' );

const TEST_PLUGIN_SLUG = 'scf-test-plugin-all-field-types';
const FIELD_GROUP_LABEL = 'Gallery Field Test';
const TEST_IMAGE_PATH = path.join( __dirname, 'assets', 'test-image.png' );

test.describe( 'Field Type > Gallery', () => {
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

	test( 'should create a gallery field and add images', async ( {
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
		await fieldLabel.fill( 'Test Gallery' );

		// Select gallery type
		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'gallery' );

		// Publish
		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create test post
		const post = await requestUtils.createPost( {
			title: 'Gallery Field Test Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Click "Add to gallery" button
		const addGalleryButton = page.locator(
			'.acf-field[data-name="test_gallery"] .acf-gallery-add'
		);
		await addGalleryButton.click();

		// Wait for media modal
		await page.waitForSelector( '.media-modal', { state: 'visible' } );

		// Click "Upload files" tab
		const uploadTab = page.locator( '.media-modal #menu-item-upload' );
		if ( await uploadTab.isVisible() ) {
			await uploadTab.click();
		}

		// Upload the first image
		const fileInput = page.locator( '.media-modal input[type="file"]' );
		await fileInput.setInputFiles( TEST_IMAGE_PATH );

		// Wait for upload to complete
		await page.waitForSelector( '.media-modal .attachment.selected', {
			state: 'visible',
			timeout: 30000,
		} );

		// Click "Add to gallery" button in modal
		const addToGalleryButton = page.locator(
			'.media-modal .media-toolbar-primary .media-button-gallery, .media-modal .media-toolbar-primary .media-button-select'
		);
		await addToGalleryButton.click();

		// Wait for modal to close or transition
		await page.waitForTimeout( 500 );

		// If there's a second step (Edit Gallery), click Insert
		const insertButton = page.locator(
			'.media-modal .media-button-insert'
		);
		if ( await insertButton.isVisible() ) {
			await insertButton.click();
		}

		// Wait for modal to close
		await page.waitForSelector( '.media-modal', {
			state: 'hidden',
			timeout: 5000,
		} );

		// Verify image appears in gallery
		const galleryImage = page.locator(
			'.acf-field[data-name="test_gallery"] .acf-gallery-attachment'
		);
		await expect( galleryImage.first() ).toBeVisible();

		// Preview and verify
		const previewPage = await editor.openPreviewPage();

		const galleryOutput = previewPage.locator( '#scf-test-test_gallery' );
		await expect( galleryOutput ).toBeVisible();
		await expect( galleryOutput ).toHaveAttribute( 'data-count', /[1-9]/ );

		const galleryImages = galleryOutput.locator( 'img.scf-test-gallery-image' );
		await expect( galleryImages.first() ).toBeVisible();

		await previewPage.close();
	} );

	test( 'should allow removing images from gallery', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Create field group
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'Gallery Remove Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Removable Gallery' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'gallery' );

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create post and add image
		const post = await requestUtils.createPost( {
			title: 'Gallery Remove Test Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Add image to gallery
		const addGalleryButton = page.locator(
			'.acf-field[data-name="removable_gallery"] .acf-gallery-add'
		);
		await addGalleryButton.click();

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

		const addToGalleryButton = page.locator(
			'.media-modal .media-toolbar-primary .media-button-gallery, .media-modal .media-toolbar-primary .media-button-select'
		);
		await addToGalleryButton.click();

		await page.waitForTimeout( 500 );

		const insertButton = page.locator( '.media-modal .media-button-insert' );
		if ( await insertButton.isVisible() ) {
			await insertButton.click();
		}

		await page.waitForSelector( '.media-modal', {
			state: 'hidden',
			timeout: 5000,
		} );

		// Verify image is in gallery
		const galleryImage = page.locator(
			'.acf-field[data-name="removable_gallery"] .acf-gallery-attachment'
		);
		await expect( galleryImage.first() ).toBeVisible();

		// Remove the image by clicking the remove button on hover
		await galleryImage.first().hover();
		const removeButton = galleryImage
			.first()
			.locator( 'a[data-name="remove"], .acf-icon.-cancel' );
		await removeButton.click();

		// Verify image is removed
		await expect( galleryImage ).toHaveCount( 0 );
	} );
} );
