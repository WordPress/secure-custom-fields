/**
 * E2E tests for post-content placeholders.
 */
const { test, expect } = require( './fixtures' );
const {
	PLUGIN_SLUG,
	deleteFieldGroups,
	waitForMetaBoxes,
	toggleFieldSetting,
} = require( './field-helpers' );

const FIELD_GROUPS = {
	movieTitle: 'Placeholder Movie Title',
	secretNote: 'Placeholder Secret Note',
	bodyHtml: 'Placeholder Body HTML',
};

test.describe( 'Post Content Placeholders', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( PLUGIN_SLUG );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deactivatePlugin( PLUGIN_SLUG );
		await requestUtils.deleteAllPosts();
	} );

	test.beforeEach( async ( { page, admin } ) => {
		await deleteFieldGroups( page, admin );
	} );

	test( 'renders supported placeholders on the frontend while preserving raw editor content', async ( {
		page,
		admin,
		editor,
		requestUtils,
	} ) => {
		await createPlaceholderFieldGroup( page, admin, {
			groupTitle: FIELD_GROUPS.movieTitle,
			fieldLabel: 'Movie Title',
			fieldType: 'text',
			enableBindings: true,
		} );

		await createPlaceholderFieldGroup( page, admin, {
			groupTitle: FIELD_GROUPS.secretNote,
			fieldLabel: 'Secret Note',
			fieldType: 'text',
			enableBindings: false,
		} );

		await createPlaceholderFieldGroup( page, admin, {
			groupTitle: FIELD_GROUPS.bodyHtml,
			fieldLabel: 'Body HTML',
			fieldType: 'wysiwyg',
			enableBindings: true,
		} );

		const content = [
			'<!-- wp:heading -->',
			'<h2>[[movie_title]]</h2>',
			'<!-- /wp:heading -->',
			'<!-- wp:paragraph -->',
			'<p>Now showing [[movie_title]] [[secret_note]] [[body_html]] [[movie title]]</p>',
			'<!-- /wp:paragraph -->',
		].join( '\n\n' );

		const post = await requestUtils.createPost( {
			title: 'Placeholder Frontend Post',
			status: 'publish',
			content,
			showWelcomeGuide: false,
		} );

		await admin.editPost( post.id );
		await waitForMetaBoxes( page );

		const movieTitleField = page.locator(
			'.acf-field[data-name="movie_title"] input[type="text"]'
		);
		const secretNoteField = page.locator(
			'.acf-field[data-name="secret_note"] input[type="text"]'
		);

		await movieTitleField.waitFor( { state: 'visible', timeout: 30000 } );
		await secretNoteField.waitFor( { state: 'visible', timeout: 30000 } );

		await movieTitleField.fill( 'The Matrix' );
		await secretNoteField.fill( 'Classified' );

		const textTabButton = page.locator(
			'.acf-field[data-name="body_html"] .wp-switch-editor.switch-html'
		);
		await textTabButton.click();
		await page.waitForTimeout( 150 );
		await page
			.locator( '.acf-field[data-name="body_html"] textarea.wp-editor-area' )
			.fill( '<p><strong>Bold</strong> <span class="bad">Span</span></p>' );

		await savePostChanges( page );

		await admin.editPost( post.id );
		const canvas = await editor.canvas;
		await expect(
			canvas.locator( '[data-type="core/heading"]' ).first()
		).toContainText( '[[movie_title]]' );
		await expect(
			canvas.locator( '[data-type="core/paragraph"]' ).first()
		).toContainText( '[[movie_title]]' );
		await expect(
			canvas.locator( '[data-type="core/paragraph"]' ).first()
		).toContainText( '[[movie title]]' );

		await page.goto( post.link );

		await expect( page.locator( 'h2.wp-block-heading' ) ).toContainText(
			'The Matrix'
		);

		const paragraph = page.locator( 'p' ).filter( {
			hasText: 'Now showing',
		} );
		await expect( paragraph ).toContainText( 'Now showing The Matrix' );
		await expect( paragraph ).toContainText( '[[movie title]]' );
		await expect( paragraph ).not.toContainText( 'Classified' );
		await expect( paragraph.locator( 'strong' ) ).toHaveText( 'Bold' );
		expect( await paragraph.locator( 'span' ).count() ).toBe( 0 );

		const restPost = await requestUtils.rest( {
			path: `/wp/v2/posts/${ post.id }`,
		} );
		expect( restPost.content.rendered ).toContain( '[[movie_title]]' );
		expect( restPost.content.rendered ).toContain( '[[movie title]]' );

		await requestUtils.deactivatePlugin( PLUGIN_SLUG );
		await page.goto( post.link );
		await expect( page.locator( 'h2.wp-block-heading' ) ).toContainText(
			'[[movie_title]]'
		);
		await expect( paragraph ).toContainText( '[[movie_title]]' );

		await requestUtils.activatePlugin( PLUGIN_SLUG );
	} );
} );

/**
 * Create a field group containing a single placeholder field.
 *
 * @param {import('@playwright/test').Page} page    Playwright page object.
 * @param {Object}                          admin   Admin utilities.
 * @param {Object}                          options Field group options.
 */
async function createPlaceholderFieldGroup( page, admin, options ) {
	const { groupTitle, fieldLabel, fieldType, enableBindings } = options;

	await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
	await page.locator( 'a.acf-btn:has-text("Add New")' ).click();

	await page.waitForSelector( '#title' );
	await page.fill( '#title', groupTitle );

	await page
		.locator( 'a.acf-btn-secondary.add-field' )
		.filter( { hasText: 'Add Field' } )
		.first()
		.click();
	await page.waitForTimeout( 500 );

	const fieldObject = page.locator( '.acf-field-object' ).last();
	const fieldLabelInput = fieldObject.locator( 'input.field-label' );
	const fieldTypeSelect = fieldObject.locator( 'select.field-type' );

	await fieldLabelInput.waitFor( { state: 'visible', timeout: 30000 } );
	await fieldLabelInput.fill( fieldLabel );
	await fieldTypeSelect.selectOption( fieldType );

	if ( enableBindings ) {
		await toggleFieldSetting( page, '.acf-field-setting-allow_in_bindings', true );
	}

	await page
		.locator( 'button.acf-btn.acf-publish[type="submit"]' )
		.click();
	await expect( page.locator( '.updated.notice' ) ).toBeVisible();

	await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
	await expect( page.locator( `tr:has-text("${ groupTitle }")` ) ).toBeVisible();
}

/**
 * Save post changes from the block editor.
 *
 * @param {import('@playwright/test').Page} page Playwright page object.
 */
async function savePostChanges( page ) {
	const saveButton = page.getByRole( 'button', {
		name: /^(Save|Save draft|Update)$/,
	} );

	await saveButton.first().click();
	await expect( page.locator( '.components-snackbar, .editor-post-saved-state' ) ).toContainText(
		/saved|updated/i
	);
}
