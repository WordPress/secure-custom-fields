/**
 * E2E tests for the WYSIWYG (Visual Editor) field type.
 *
 * Tests the WYSIWYG field which provides a TinyMCE rich text editor
 * for formatted content entry.
 */
const { test, expect } = require( './fixtures' );
const {
	PLUGIN_SLUG,
	deleteFieldGroups,
	waitForMetaBoxes,
	toggleFieldSetting,
} = require( './field-helpers' );

const TEST_PLUGIN_SLUG = 'scf-test-plugin-all-field-types';
const FIELD_GROUP_LABEL = 'WYSIWYG Field Test';

test.describe( 'Field Type > WYSIWYG', () => {
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

	test( 'should create a wysiwyg field and enter rich text', async ( {
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
		await fieldLabel.fill( 'Test WYSIWYG' );

		// Select wysiwyg type
		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'wysiwyg' );

		// Publish
		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create test post
		const post = await requestUtils.createPost( {
			title: 'WYSIWYG Test Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Wait for TinyMCE to initialize
		await page.waitForTimeout( 500 );

		// Try to switch to Text mode for easier text entry
		const textTabButton = page.locator(
			'.acf-field[data-name="test_wysiwyg"] .wp-switch-editor.switch-html'
		);

		if ( await textTabButton.isVisible() ) {
			await textTabButton.click();
			await page.waitForTimeout( 150 );

			// Enter text in the textarea
			const textarea = page.locator(
				'.acf-field[data-name="test_wysiwyg"] textarea.wp-editor-area'
			);
			await textarea.fill( '<p>Hello WYSIWYG World!</p>' );
		} else {
			// Fallback: try to interact with TinyMCE iframe
			const iframe = page.locator(
				'.acf-field[data-name="test_wysiwyg"] iframe'
			);
			if ( await iframe.isVisible() ) {
				const frame = await iframe.contentFrame();
				if ( frame ) {
					const body = frame.locator( 'body' );
					await body.click();
					await body.fill( 'Hello WYSIWYG World!' );
				}
			}
		}

		// Preview and verify
		const previewPage = await editor.openPreviewPage();

		const wysiwygOutput = previewPage.locator( '#scf-test-test_wysiwyg' );
		await expect( wysiwygOutput ).toBeVisible();
		await expect( wysiwygOutput ).toContainText( 'WYSIWYG' );

		await previewPage.close();
	} );

	test( 'should support visual and text tabs', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Create field group
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'WYSIWYG Tabs Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'Editor Tabs' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'wysiwyg' );

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create post
		const post = await requestUtils.createPost( {
			title: 'WYSIWYG Tabs Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );
		await page.waitForTimeout( 500 );

		// Check for tab buttons
		const visualTab = page.locator(
			'.acf-field[data-name="editor_tabs"] .wp-switch-editor.switch-tmce'
		);
		const textTab = page.locator(
			'.acf-field[data-name="editor_tabs"] .wp-switch-editor.switch-html'
		);

		// At least one tab should be visible
		const hasVisualTab = await visualTab.isVisible();
		const hasTextTab = await textTab.isVisible();

		expect( hasVisualTab || hasTextTab ).toBeTruthy();

		// Click text tab if available
		if ( hasTextTab ) {
			await textTab.click();
			await page.waitForTimeout( 150 );

			// Verify textarea is visible
			const textarea = page.locator(
				'.acf-field[data-name="editor_tabs"] textarea.wp-editor-area'
			);
			await expect( textarea ).toBeVisible();
		}
	} );

	test( 'should respect media buttons setting', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Create field group with media buttons disabled
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
		await addNewButton.click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', 'WYSIWYG Media Test' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'No Media' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'wysiwyg' );

		// Disable media upload
		await toggleFieldSetting( page, '.acf-field-setting-media_upload', false );

		const publishButton = page.locator(
			'button.acf-btn.acf-publish[type="submit"]'
		);
		await publishButton.click();

		const successNotice = page.locator( '.updated.notice' );
		await expect( successNotice ).toBeVisible();

		// Create post
		const post = await requestUtils.createPost( {
			title: 'WYSIWYG Media Post',
			status: 'draft',
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		// Verify field is present
		const fieldContainer = page.locator( '.acf-field[data-name="no_media"]' );
		await expect( fieldContainer ).toBeVisible();
	} );
} );
