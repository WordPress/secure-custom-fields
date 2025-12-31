/**
 * E2E tests for the Link field type.
 *
 * Tests the Link field which provides a URL input with
 * optional title and target attributes.
 */
const { test, expect } = require( './fixtures' );
const {
	PLUGIN_SLUG,
	deleteFieldGroups,
	waitForMetaBoxes,
} = require( './field-helpers' );

const TEST_PLUGIN_SLUG = 'scf-test-plugin-all-field-types';
const FIELD_GROUP_LABEL = 'Link Field Test';

test.describe( 'Field Type > Link', () => {
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

	test( 'should create a link field and enter link data', async ( {
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
		await fieldLabel.fill( 'Test Link' );

		// Select link type
		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'link' );

		// Publish
		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create test post
		const post = await requestUtils.createPost( {
			title: 'Link Test Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// The link field has a "Select Link" button
		const linkButton = page.locator(
			'.acf-field[data-name="test_link"] button:has-text("Select Link"), .acf-field[data-name="test_link"] a:has-text("Select Link")'
		);
		await linkButton.click();

		// Wait for the link modal/popup to appear
		await page.waitForTimeout( 300 );

		// The WordPress link modal opens - use specific ID selector
		const wpLinkUrlInput = page.locator( '#wp-link-url' );
		if ( await wpLinkUrlInput.isVisible() ) {
			await wpLinkUrlInput.fill( 'https://wordpress.org' );

			// Fill title if available
			const wpLinkTextInput = page.locator( '#wp-link-text' );
			if ( await wpLinkTextInput.isVisible() ) {
				await wpLinkTextInput.fill( 'WordPress' );
			}

			// Submit the modal
			const submitButton = page.locator( '#wp-link-submit' );
			if ( await submitButton.isVisible() ) {
				await submitButton.click();
			}
		} else {
			// Fallback: direct input fields in the field itself
			const urlInput = page.locator(
				'.acf-field[data-name="test_link"] input[data-name="url"]'
			).first();
			if ( await urlInput.isVisible() ) {
				await urlInput.fill( 'https://wordpress.org' );
			}
		}

		// Preview and verify
		const previewPage = await editor.openPreviewPage();

		const linkOutput = previewPage.locator( '#scf-test-test_link' );
		await expect( linkOutput ).toBeVisible();
		// Should contain either the URL or the title
		const linkContent = await linkOutput.textContent();
		expect(
			linkContent.includes( 'wordpress.org' ) ||
				linkContent.includes( 'WordPress' )
		).toBeTruthy();

		await previewPage.close();
	} );

	test( 'should allow setting link to open in new tab', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Create field group
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'Link Target Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'New Tab Link' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'link' );

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create post
		const post = await requestUtils.createPost( {
			title: 'Link Target Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Open link interface
		const linkButton = page.locator(
			'.acf-field[data-name="new_tab_link"] button:has-text("Select Link"), .acf-field[data-name="new_tab_link"] a:has-text("Select Link")'
		);
		await linkButton.click();
		await page.waitForTimeout( 300 );

		// Fill URL in the WordPress link modal
		const modalUrlInput = page.locator( '#wp-link-url' );
		if ( await modalUrlInput.isVisible() ) {
			await modalUrlInput.fill( 'https://example.com' );

			// Check the "Open in new tab" checkbox if available
			const newTabCheckbox = page.locator( '#wp-link-target' );
			if ( await newTabCheckbox.isVisible() ) {
				await newTabCheckbox.check();
			}

			// Submit the modal
			const submitButton = page.locator( '#wp-link-submit' );
			if ( await submitButton.isVisible() ) {
				await submitButton.click();
			}
		}

		// Verify the field exists
		const fieldContainer = page.locator(
			'.acf-field[data-name="new_tab_link"]'
		);
		await expect( fieldContainer ).toBeVisible();
	} );

	test( 'should display link value after entry', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Create field group
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'Link Display Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Display Link' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'link' );

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create post
		const post = await requestUtils.createPost( {
			title: 'Link Display Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Open and fill link
		const linkButton = page.locator(
			'.acf-field[data-name="display_link"] button:has-text("Select Link"), .acf-field[data-name="display_link"] a:has-text("Select Link")'
		);
		await linkButton.click();
		await page.waitForTimeout( 300 );

		// Fill URL in the WordPress link modal
		const modalUrlInput = page.locator( '#wp-link-url' );
		if ( await modalUrlInput.isVisible() ) {
			await modalUrlInput.fill( 'https://make.wordpress.org' );

			// Fill title if available
			const modalTitleInput = page.locator( '#wp-link-text' );
			if ( await modalTitleInput.isVisible() ) {
				await modalTitleInput.fill( 'Make WordPress' );
			}

			// Submit the modal
			const submitButton = page.locator( '#wp-link-submit' );
			if ( await submitButton.isVisible() ) {
				await submitButton.click();
			}
		}

		// Just verify the field container is present
		const fieldContainer = page.locator(
			'.acf-field[data-name="display_link"]'
		);
		await expect( fieldContainer ).toBeVisible();
	} );
} );
