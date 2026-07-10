/**
 * WordPress dependencies
 */
const { test, expect, wpVersionAtLeast } = require( './fixtures' );

const PLUGIN_SLUG = 'secure-custom-fields';
const TEST_PLUGIN_SLUG = 'scf-test-plugin-testimonial-block';
const BLOCK_NAME = 'scf/testimonial';

const getVisibleBlockField = ( page, name ) =>
	page.locator( `.acf-field[data-name="${ name }"]:visible` ).first();

const getEditorCanvas = async ( page, editor ) => {
	// The editor mounts asynchronously, so right after a navigation the
	// iframed canvas may not exist yet and a bare count() check would wrongly
	// fall back to the top document. Wait for either the iframed canvas or
	// the non-iframed block list to appear before deciding which root to use.
	await page
		.locator(
			'iframe[name="editor-canvas"], .block-editor-block-list__layout'
		)
		.first()
		.waitFor( { timeout: 15000 } );

	if ( await page.locator( 'iframe[name="editor-canvas"]' ).count() ) {
		return editor.canvas;
	}

	return page;
};

const openBlockFields = async ( page ) => {
	const modal = page.locator( '.acf-block-form-modal' );
	const blockFields = page.locator( '.acf-block-fields:visible' ).first();

	if ( ( await modal.isVisible() ) && ( await blockFields.isVisible() ) ) {
		return true;
	}

	if ( await blockFields.isVisible() ) {
		return false;
	}

	await page.evaluate( () => {
		const editPostDispatch = window.wp?.data?.dispatch( 'core/edit-post' );
		const interfaceDispatch = window.wp?.data?.dispatch( 'core/interface' );

		if ( editPostDispatch?.openGeneralSidebar ) {
			editPostDispatch.openGeneralSidebar( 'edit-post/block' );
		} else if ( interfaceDispatch?.enableComplementaryArea ) {
			interfaceDispatch.enableComplementaryArea(
				'core/edit-post',
				'edit-post/block'
			);
		}
	} );

	const expandedEditorButton = page
		.locator( '.acf-blocks-open-expanded-editor-btn:visible' )
		.first();

	await expandedEditorButton.waitFor( {
		state: 'visible',
		timeout: 10000,
	} );
	await expandedEditorButton.click();
	await page
		.locator( '.acf-block-form-modal .acf-block-fields:visible' )
		.waitFor( { state: 'visible', timeout: 10000 } );

	return true;
};

const closeBlockFields = async ( page, isExpandedEditorOpen ) => {
	if ( ! isExpandedEditorOpen ) {
		return;
	}

	const modal = page.locator( '.acf-block-form-modal' );

	if ( ! ( await modal.isVisible() ) ) {
		return;
	}

	const fallbackDoneButton = modal
		.locator( '.acf-block-form-modal__done-button:visible' )
		.first();

	try {
		await fallbackDoneButton.click( { timeout: 1000 } );
	} catch {
		if ( ! ( await modal.isVisible() ) ) {
			return;
		}

		await modal
			.getByRole( 'button', { name: 'Done' } )
			.click( { timeout: 5000 } );
	}

	await expect( modal ).toHaveCount( 0 );
};

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

		// Wait for the editor canvas to mount before inserting. On newer
		// WordPress versions the editor boots asynchronously and an early
		// insert can be wiped by editor setup.
		await getEditorCanvas( page, editor );

		// Add the testimonial block
		await editor.insertBlock( { name: BLOCK_NAME } );

		let isExpandedEditorOpen = await openBlockFields( page );

		// Fill in the quote field.
		const quoteField = getVisibleBlockField( page, 'quote' ).locator(
			'textarea'
		);
		await quoteField.fill( 'Amazing Quote' );
		await quoteField.blur();

		// Wait for the preview to update
		await page.waitForTimeout( 1000 );

		isExpandedEditorOpen = await openBlockFields( page );

		// Fill in the author field
		const authorField = getVisibleBlockField( page, 'author' ).locator(
			'input[type="text"]'
		);
		await authorField.fill( 'John Doe' );
		// Blur to trigger update
		await authorField.blur();

		// Wait for the preview to update
		await page.waitForTimeout( 500 );

		isExpandedEditorOpen = await openBlockFields( page );

		// Fill in the role field
		const roleField = getVisibleBlockField( page, 'role' ).locator(
			'input[type="text"]'
		);
		await roleField.fill( 'CEO, Example Company' );
		// Blur to trigger update
		await roleField.blur();

		await closeBlockFields( page, isExpandedEditorOpen );

		// Wait for block preview to update
		await page.waitForTimeout( 1000 );

		// Get the editor canvas (content is in an iframe in newer WordPress versions)
		const canvas = await getEditorCanvas( page, editor );

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

		// Wait for the editor canvas to mount before inserting. On newer
		// WordPress versions the editor boots asynchronously and an early
		// insert can be wiped by editor setup.
		await getEditorCanvas( page, editor );

		// Add the testimonial block
		await editor.insertBlock( { name: BLOCK_NAME } );

		const isExpandedEditorOpen = await openBlockFields( page );

		// Clear the quote field to trigger validation
		const quoteTextarea = getVisibleBlockField( page, 'quote' ).locator(
			'textarea'
		);
		await quoteTextarea.clear();

		// Blur to trigger field validation
		await quoteTextarea.blur();
		await closeBlockFields( page, isExpandedEditorOpen );

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
