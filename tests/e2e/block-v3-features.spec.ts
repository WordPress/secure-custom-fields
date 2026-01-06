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

		// Add the v3 block
		await editor.insertBlock( { name: BLOCK_NAME } );

		// Wait for the block fields to appear in the sidebar
		await page.waitForSelector( '.acf-block-fields', {
			state: 'visible',
			timeout: 10000,
		} );

		// Verify that there is NO edit/preview mode toggle button
		// V3 blocks should always be in preview mode
		const modeToggle = page.locator(
			'[data-type="scf/v3-block"] button[aria-label*="mode"]'
		);
		await expect( modeToggle ).toHaveCount( 0 );

		// Verify the block preview is visible in the canvas
		const canvas = await editor.canvas;
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

		// Add the v3 block
		await editor.insertBlock( { name: BLOCK_NAME } );

		// Wait for the block fields to appear in the sidebar
		await page.waitForSelector( '.acf-block-fields', {
			state: 'visible',
			timeout: 10000,
		} );

		// Get the editor canvas
		const canvas = await editor.canvas;

		// Fill in the title field
		const titleField = page.locator(
			'.acf-field[data-name="title"] input[type="text"]'
		);
		await titleField.fill( 'Featured Product' );
		await titleField.blur();

		// Verify preview shows the title
		const blockTitle = canvas.locator(
			'[data-type="scf/v3-block"] .v3-block__title'
		);
		await expect( blockTitle ).toContainText( 'Featured Product' );

		// Fill in the description field
		const descriptionField = page.locator(
			'.acf-field[data-name="description"] textarea'
		);
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

		// Toggle the show_badge field
		const badgeSwitch = page.locator(
			'.acf-field[data-name="show_badge"] .acf-switch'
		);
		await badgeSwitch.click();

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

		// Add the v3 block
		await editor.insertBlock( { name: BLOCK_NAME } );

		// Wait for the block fields to appear in the sidebar
		await page.waitForSelector( '.acf-block-fields', {
			state: 'visible',
			timeout: 10000,
		} );

		// Clear the title field to ensure it's empty (title is required)
		const titleField = page.locator(
			'.acf-field[data-name="title"] input[type="text"]'
		);
		await titleField.clear();
		await titleField.blur();

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

		// Add the v3 block
		await editor.insertBlock( { name: BLOCK_NAME } );

		// Wait for the block fields to appear in the sidebar
		await page.waitForSelector( '.acf-block-fields', {
			state: 'visible',
			timeout: 10000,
		} );

		// Get the editor canvas
		const canvas = await editor.canvas;

		// Fill in all fields with blur to commit changes
		const titleField = page.locator(
			'.acf-field[data-name="title"] input[type="text"]'
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

		const descriptionField = page.locator(
			'.acf-field[data-name="description"] textarea'
		);
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

		const badgeSwitch = page.locator(
			'.acf-field[data-name="show_badge"] .acf-switch'
		);
		await badgeSwitch.click();

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
		const canvasAfterReload = await editor.canvas;
		const blockAfterReload = canvasAfterReload.locator(
			'[data-type="scf/v3-block"]'
		);
		await blockAfterReload.waitFor( { state: 'visible', timeout: 10000 } );
		await blockAfterReload.click();

		// Wait for block fields to load again
		await page.waitForSelector( '.acf-block-fields', {
			state: 'visible',
			timeout: 10000,
		} );

		// Verify the fields retained their values
		const titleFieldAfterReload = page.locator(
			'.acf-field[data-name="title"] input[type="text"]'
		);
		await expect( titleFieldAfterReload ).toHaveValue( 'Saved Title' );

		const descriptionFieldAfterReload = page.locator(
			'.acf-field[data-name="description"] textarea'
		);
		await expect( descriptionFieldAfterReload ).toHaveValue(
			'Saved description text.'
		);

		// Verify the badge switch is on
		const badgeSwitchAfterReload = page.locator(
			'.acf-field[data-name="show_badge"] .acf-switch'
		);
		const badgeClass = await badgeSwitchAfterReload.getAttribute( 'class' );
		expect( badgeClass ).toContain( '-on' );
	} );
} );
