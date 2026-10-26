/**
 * E2E tests for SCF Block V3 inline text editing
 *
 * Typing into an inline editable element updates the field, and the block
 * preview is fetched again and re-rendered. The caret must stay where the
 * user is typing through that re-render.
 */
const { test, expect, wpVersionAtLeast } = require( './fixtures' );

const PLUGIN_SLUG = 'secure-custom-fields';
const TEST_PLUGIN_SLUG = 'scf-test-plugin-inline-editing-block';
const BLOCK_NAME = 'scf/inline-editing-block';

const BLOCK_CONTENT = `<!-- wp:${ BLOCK_NAME } ${ JSON.stringify( {
	name: BLOCK_NAME,
	data: {
		title: 'Hello',
		_title: 'field_inline_editing_title',
		subtitle: 'World',
		_subtitle: 'field_inline_editing_subtitle',
	},
	mode: 'preview',
} ) } /-->`;

test.describe( 'SCF Block V3 Inline Text Editing', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( PLUGIN_SLUG );
		await requestUtils.activatePlugin( TEST_PLUGIN_SLUG );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deactivatePlugin( TEST_PLUGIN_SLUG );
		await requestUtils.deactivatePlugin( PLUGIN_SLUG );
		await requestUtils.deleteAllPosts();
	} );

	test.beforeEach( async ( { page, admin, requestUtils } ) => {
		// ACF block version 3 requires WordPress 6.3+
		await page.goto( '/wp-admin/' );
		test.skip(
			! ( await wpVersionAtLeast( page, 6, 3 ) ),
			'ACF block version 3 requires WordPress 6.3+'
		);

		const post = await requestUtils.createPost( {
			title: 'V3 Block Inline Editing Test',
			status: 'draft',
			content: BLOCK_CONTENT,
		} );
		await admin.editPost( post.id );
	} );

	test( 'keeps the caret at the end of the text when the preview re-renders', async ( {
		page,
		editor,
	} ) => {
		const canvas = await editor.canvas;
		const block = canvas.locator( '.inline-editing-block' );
		const title = canvas.locator(
			'.inline-editing-block__title[contenteditable="true"]'
		);
		await expect( title ).toHaveText( 'Hello' );

		await title.click();
		await page.keyboard.press( 'End' );
		await page.keyboard.type( 'AAA' );

		// Wait for the preview rendered for the typed value.
		await expect( block ).toHaveAttribute(
			'data-rendered-title',
			'HelloAAA'
		);

		await page.keyboard.type( 'BBB' );

		await expect( block ).toHaveAttribute(
			'data-rendered-title',
			'HelloAAABBB'
		);
		await expect( title ).toHaveText( 'HelloAAABBB' );
	} );

	test( 'keeps the caret in the middle of another field', async ( {
		page,
		editor,
	} ) => {
		const canvas = await editor.canvas;
		const block = canvas.locator( '.inline-editing-block' );
		const subtitle = canvas.locator(
			'.inline-editing-block__subtitle[contenteditable="true"]'
		);
		await expect( subtitle ).toHaveText( 'World' );

		await subtitle.click();
		await page.keyboard.press( 'Home' );
		await page.keyboard.press( 'ArrowRight' );
		await page.keyboard.press( 'ArrowRight' );
		await page.keyboard.type( 'X' );

		await expect( block ).toHaveAttribute(
			'data-rendered-subtitle',
			'WoXrld'
		);

		await page.keyboard.type( 'Y' );

		await expect( block ).toHaveAttribute(
			'data-rendered-subtitle',
			'WoXYrld'
		);
		await expect( subtitle ).toHaveText( 'WoXYrld' );
		await expect( block ).toHaveAttribute( 'data-rendered-title', 'Hello' );
	} );
} );
