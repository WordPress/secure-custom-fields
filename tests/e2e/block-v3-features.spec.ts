/**
 * E2E tests for SCF Block V3 features
 *
 * Tests the block version 3 specific functionality:
 * - Preview-only rendering (no edit mode toggle)
 * - Real-time sidebar field updates to preview
 * - Required field validation
 */
const { test, expect, wpVersionAtLeast } = require( './fixtures' );

const PLUGIN_SLUG = 'secure-custom-fields';
const TEST_PLUGIN_SLUG = 'scf-test-plugin-v3-block';
const BLOCK_NAME = 'scf/v3-block';

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

test.describe( 'SCF Block V3 Features', () => {
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
		// ACF block version 3 requires WordPress 6.3+
		await page.goto( '/wp-admin/' );
		test.skip(
			! ( await wpVersionAtLeast( page, 6, 3 ) ),
			'ACF block version 3 requires WordPress 6.3+'
		);
	} );

	test( 'should render v3 block in preview-only mode', async ( {
		page,
		admin,
		editor,
		requestUtils,
	} ) => {
		// Create a new post
		const post = await requestUtils.createPost( {
			title: 'V3 Block Preview Test',
			status: 'draft',
		} );

		// Navigate to edit post page
		await admin.editPost( post.id );

		// Wait for the editor canvas to mount before inserting. On newer
		// WordPress versions the editor boots asynchronously and an early
		// insert can be wiped by editor setup.
		await getEditorCanvas( page, editor );

		// Add the v3 block
		await editor.insertBlock( { name: BLOCK_NAME } );

		const isExpandedEditorOpen = await openBlockFields( page );
		await closeBlockFields( page, isExpandedEditorOpen );

		// Verify that there is NO edit/preview mode toggle button
		// V3 blocks should always be in preview mode
		const modeToggle = page.locator(
			'[data-type="scf/v3-block"] button[aria-label*="mode"]'
		);
		await expect( modeToggle ).toHaveCount( 0 );

		// Verify the block preview is visible in the canvas
		const canvas = await getEditorCanvas( page, editor );
		const blockPreview = canvas.locator(
			'[data-type="scf/v3-block"] .v3-block'
		);
		await blockPreview.waitFor( { state: 'visible', timeout: 5000 } );

		// Verify the placeholder title is shown (since we haven't filled the title yet)
		const placeholderTitle = canvas.locator(
			'[data-type="scf/v3-block"] .v3-block__title--placeholder'
		);
		await expect( placeholderTitle ).toBeVisible();
	} );

	test( 'should update preview when sidebar fields change', async ( {
		page,
		admin,
		editor,
		requestUtils,
	} ) => {
		// Create a new post
		const post = await requestUtils.createPost( {
			title: 'V3 Block Live Update Test',
			status: 'draft',
		} );

		// Navigate to edit post page
		await admin.editPost( post.id );

		// Wait for the editor canvas to mount before inserting. On newer
		// WordPress versions the editor boots asynchronously and an early
		// insert can be wiped by editor setup.
		await getEditorCanvas( page, editor );

		// Add the v3 block
		await editor.insertBlock( { name: BLOCK_NAME } );

		let isExpandedEditorOpen = await openBlockFields( page );

		// Get the editor canvas
		const canvas = await getEditorCanvas( page, editor );

		// Fill in the title field
		const titleField = getVisibleBlockField( page, 'title' ).locator(
			'input[type="text"]'
		);
		await titleField.fill( 'Featured Product' );
		await titleField.blur();

		// Verify preview shows the title
		const blockTitle = canvas.locator(
			'[data-type="scf/v3-block"] .v3-block__title'
		);
		await expect( blockTitle ).toContainText( 'Featured Product' );

		isExpandedEditorOpen = await openBlockFields( page );

		// Fill in the description field
		const descriptionField = getVisibleBlockField(
			page,
			'description'
		).locator( 'textarea' );
		await descriptionField.fill(
			'This is an amazing product description.'
		);
		await descriptionField.blur();

		// Verify preview shows the description
		const blockDescription = canvas.locator(
			'[data-type="scf/v3-block"] .v3-block__description'
		);
		await expect( blockDescription ).toContainText(
			'This is an amazing product description.'
		);

		isExpandedEditorOpen = await openBlockFields( page );

		// Toggle the show_badge field
		const badgeSwitch = getVisibleBlockField( page, 'show_badge' ).locator(
			'.acf-switch'
		);
		await badgeSwitch.click();

		await closeBlockFields( page, isExpandedEditorOpen );

		// Verify badge appears in preview
		const blockBadge = canvas.locator(
			'[data-type="scf/v3-block"] .v3-block__badge'
		);
		await expect( blockBadge ).toBeVisible();
		await expect( blockBadge ).toContainText( 'Featured' );

		// Save the post
		await page.click( '.editor-post-save-draft' );

		// Wait for save to complete
		const savedNotice = page.locator(
			'.components-snackbar:has-text("Draft saved")'
		);
		await expect( savedNotice ).toBeVisible( { timeout: 5000 } );
	} );

	test( 'should validate required fields on publish', async ( {
		page,
		admin,
		editor,
		requestUtils,
	} ) => {
		// Create a new post
		const post = await requestUtils.createPost( {
			title: 'V3 Block Validation Test',
			status: 'draft',
		} );

		// Navigate to edit post page
		await admin.editPost( post.id );

		// Wait for the editor canvas to mount before inserting. On newer
		// WordPress versions the editor boots asynchronously and an early
		// insert can be wiped by editor setup.
		await getEditorCanvas( page, editor );

		// Add the v3 block
		await editor.insertBlock( { name: BLOCK_NAME } );

		const isExpandedEditorOpen = await openBlockFields( page );

		// Clear the title field to ensure it's empty (title is required)
		const titleField = getVisibleBlockField( page, 'title' ).locator(
			'input[type="text"]'
		);
		await titleField.clear();
		await titleField.blur();
		await closeBlockFields( page, isExpandedEditorOpen );

		// Try to publish to trigger full validation
		const publishToggle = page.locator(
			'.editor-post-publish-panel__toggle'
		);
		await publishToggle.click();

		// Click publish button
		const publishButton = page.locator(
			'.editor-post-publish-panel .editor-post-publish-button'
		);
		await publishButton.waitFor( { state: 'visible' } );
		await publishButton.click();

		// Wait for the error notice to appear
		const errorNotice = page.locator(
			'.components-notice.is-error .components-notice__content'
		);
		await expect( errorNotice ).toBeVisible( { timeout: 3000 } );
	} );

	test( 'should save v3 block data correctly', async ( {
		page,
		admin,
		editor,
		requestUtils,
	} ) => {
		// Create a new post
		const post = await requestUtils.createPost( {
			title: 'V3 Block Save Test',
			status: 'draft',
		} );

		// Navigate to edit post page
		await admin.editPost( post.id );

		// Wait for the editor canvas to mount before inserting. On newer
		// WordPress versions the editor boots asynchronously and an early
		// insert can be wiped by editor setup.
		await getEditorCanvas( page, editor );

		// Add the v3 block
		await editor.insertBlock( { name: BLOCK_NAME } );

		let isExpandedEditorOpen = await openBlockFields( page );

		// Get the editor canvas
		const canvas = await getEditorCanvas( page, editor );

		// Fill in all fields with blur to commit changes
		const titleField = getVisibleBlockField( page, 'title' ).locator(
			'input[type="text"]'
		);
		await titleField.fill( 'Saved Title' );
		await titleField.blur();

		// Wait for preview to update with the title
		const blockTitle = canvas.locator(
			'[data-type="scf/v3-block"] .v3-block__title'
		);
		await expect( blockTitle ).toContainText( 'Saved Title', {
			timeout: 5000,
		} );

		isExpandedEditorOpen = await openBlockFields( page );

		const descriptionField = getVisibleBlockField(
			page,
			'description'
		).locator( 'textarea' );
		await descriptionField.fill( 'Saved description text.' );
		await descriptionField.blur();

		// Wait for preview to update with the description
		const blockDescription = canvas.locator(
			'[data-type="scf/v3-block"] .v3-block__description'
		);
		await expect( blockDescription ).toContainText(
			'Saved description text.',
			{ timeout: 5000 }
		);

		isExpandedEditorOpen = await openBlockFields( page );

		const badgeSwitch = getVisibleBlockField( page, 'show_badge' ).locator(
			'.acf-switch'
		);
		await badgeSwitch.click();

		await closeBlockFields( page, isExpandedEditorOpen );

		// Wait for badge to appear in preview
		const blockBadge = canvas.locator(
			'[data-type="scf/v3-block"] .v3-block__badge'
		);
		await expect( blockBadge ).toBeVisible( { timeout: 5000 } );

		// Save the post
		await page.click( '.editor-post-save-draft' );

		// Wait for save to complete
		const savedNotice = page.locator(
			'.components-snackbar:has-text("Draft saved")'
		);
		await expect( savedNotice ).toBeVisible( { timeout: 5000 } );

		// Reload the page
		await page.reload();

		// Wait for editor to fully load
		await page.waitForLoadState( 'domcontentloaded' );

		// Get the editor canvas and select the block
		const canvasAfterReload = await getEditorCanvas( page, editor );
		const blockAfterReload = canvasAfterReload.locator(
			'[data-type="scf/v3-block"]'
		);
		await blockAfterReload.waitFor( { state: 'visible', timeout: 10000 } );
		await blockAfterReload.click();

		await openBlockFields( page );

		// Verify the fields retained their values
		const titleFieldAfterReload = getVisibleBlockField(
			page,
			'title'
		).locator( 'input[type="text"]' );
		await expect( titleFieldAfterReload ).toHaveValue( 'Saved Title' );

		const descriptionFieldAfterReload = getVisibleBlockField(
			page,
			'description'
		).locator( 'textarea' );
		await expect( descriptionFieldAfterReload ).toHaveValue(
			'Saved description text.'
		);

		// Verify the badge switch is on
		const badgeSwitchAfterReload = getVisibleBlockField(
			page,
			'show_badge'
		).locator( '.acf-switch' );
		const badgeClass = await badgeSwitchAfterReload.getAttribute( 'class' );
		expect( badgeClass ).toContain( '-on' );
	} );
} );
