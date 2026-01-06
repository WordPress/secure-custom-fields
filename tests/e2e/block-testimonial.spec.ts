/**
 * WordPress dependencies
 */
const { test, expect, wpVersionAtLeast } = require( './fixtures' );

const PLUGIN_SLUG = 'secure-custom-fields';
const TEST_PLUGIN_SLUG = 'scf-test-plugin-testimonial-block';
const BLOCK_NAME = 'scf/testimonial';

test.describe( 'SCF Block > Testimonial', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( PLUGIN_SLUG );
		await requestUtils.activatePlugin( TEST_PLUGIN_SLUG );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deactivatePlugin( TEST_PLUGIN_SLUG );
		await requestUtils.deactivatePlugin( PLUGIN_SLUG );
		await requestUtils.deleteAllPosts();
	} );

	test.beforeEach( async ( { page } ) => {
		// ACF block version 3 with sidebar fields requires WordPress 6.3+
		await page.goto( '/wp-admin/' );
		test.skip(
			! ( await wpVersionAtLeast( page, 6, 3 ) ),
			'ACF block version 3 with sidebar fields requires WordPress 6.3+'
		);
	} );

	test( 'should use testimonial block with fields', async ( {
		page,
		admin,
		editor,
		requestUtils,
	} ) => {
		// The block and field group are already registered via the plugin
		// No need to create anything in the UI

		// Create a new post
		const post = await requestUtils.createPost( {
			title: 'Testimonial Test Post',
			status: 'draft',
		} );

		// Navigate to edit post page
		await admin.editPost( post.id );

		// Add the testimonial block
		await editor.insertBlock( { name: BLOCK_NAME } );

		// Wait for the block fields to appear in the sidebar
		await page.waitForSelector( '.acf-block-fields', {
			state: 'visible',
			timeout: 10000,
		} );

		// Fill in the quote field (in the sidebar)
		const quoteField = page.locator(
			'.acf-field[data-name="quote"] textarea'
		);
		await quoteField.fill( 'Amazing Quote' );

		// Wait for the preview to update
		await page.waitForTimeout( 1000 );

		// Fill in the author field
		const authorField = page.locator(
			'.acf-field[data-name="author"] input[type="text"]'
		);
		await authorField.fill( 'John Doe' );
		// Blur to trigger update
		await authorField.blur();

		// Wait for the preview to update
		await page.waitForTimeout( 500 );

		// Fill in the role field
		const roleField = page.locator(
			'.acf-field[data-name="role"] input[type="text"]'
		);
		await roleField.fill( 'CEO, Example Company' );
		// Blur to trigger update
		await roleField.blur();

		// Wait for block preview to update
		await page.waitForTimeout( 1000 );

		// Get the editor canvas (content is in an iframe in newer WordPress versions)
		const canvas = await editor.canvas;

		// Verify the preview shows the content (within the selected block in canvas)
		const blockPreview = canvas.locator(
			'[data-type="scf/testimonial"] .testimonial__blockquote'
		);
		await blockPreview.waitFor( { state: 'visible', timeout: 5000 } );
		await expect( blockPreview ).toContainText( 'Amazing Quote' );

		// Author and role should appear in the attribution footer
		const authorCite = canvas.locator(
			'[data-type="scf/testimonial"] .testimonial__author'
		);
		await expect( authorCite ).toBeVisible();
		await expect( authorCite ).toContainText( 'John Doe' );

		const roleSpan = canvas.locator(
			'[data-type="scf/testimonial"] .testimonial__role'
		);
		await expect( roleSpan ).toBeVisible();
		await expect( roleSpan ).toContainText( 'CEO, Example Company' );

		// Save the post
		await page.click( '.editor-post-save-draft' );

		// Wait for save to complete
		const savedNotice = page.locator(
			'.components-snackbar:has-text("Draft saved")'
		);
		await expect( savedNotice ).toBeVisible( { timeout: 5000 } );
	} );

	test( 'should validate required quote field in testimonial block', async ( {
		page,
		admin,
		editor,
		requestUtils,
	} ) => {
		// The block and field group are already registered via the plugin
		// The quote field is already set as required in the plugin

		// Create a new post
		const post = await requestUtils.createPost( {
			title: 'Testimonial Validation Test',
			status: 'draft',
		} );

		// Navigate to edit post page
		await admin.editPost( post.id );

		// Add the testimonial block
		await editor.insertBlock( { name: BLOCK_NAME } );

		// Wait for the block fields to appear in the sidebar
		await page.waitForSelector( '.acf-block-fields', {
			state: 'visible',
			timeout: 10000,
		} );

		// Clear the quote field to trigger validation
		const quoteTextarea = page.locator(
			'.acf-field[data-name="quote"] textarea'
		);
		await quoteTextarea.clear();

		// Blur to trigger field validation
		await quoteTextarea.blur();

		// Wait a moment for blur validation
		await page.waitForTimeout( 500 );

		// Try to publish to trigger full validation
		const publishToggle = page.locator(
			'.editor-post-publish-panel__toggle'
		);
		await publishToggle.click();

		// Wait for publish panel to open
		await page.waitForTimeout( 500 );

		// Click the actual publish button in the panel
		const publishButton = page.locator(
			'.editor-post-publish-panel .editor-post-publish-button'
		);
		await publishButton.click();

		// Wait for the error notice to appear at the top of the editor
		const errorNotice = page.locator(
			'.components-notice.is-error .components-notice__content'
		);
		await expect( errorNotice ).toBeVisible( { timeout: 1000 } );
	} );
} );
