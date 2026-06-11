/**
 * E2E tests for field group duplication.
 *
 * Covers duplicating a field group (with a mix of field types and field
 * settings) from the field group list "Duplicate" row action, verifying
 * that the duplicate copies the title, fields, and field settings with
 * new unique field keys, and that both field groups render independently
 * on a post edit screen.
 */
const { test, expect } = require( './fixtures' );
const { PLUGIN_SLUG, addFieldChoices } = require( './field-helpers' );

const UTILITIES_PLUGIN_SLUG = 'scf-test-utilities';

const SOURCE_GROUP_TITLE = 'Duplication Source';
const DUPLICATE_GROUP_TITLE = 'Duplication Source (copy)';
const TEXT_FIELD_LABEL = 'Dup Text Field';
const TEXT_FIELD_NAME = 'dup_text_field';
const SELECT_FIELD_LABEL = 'Dup Select Field';
const SELECT_CHOICES = [ 'red : Red', 'blue : Blue', 'green : Green' ];

const DEFAULT_TIMEOUT = 5000;

/**
 * Purge SCF internal posts via the scf-test-utilities REST endpoint.
 *
 * @param {Object}   requestUtils Playwright request utilities.
 * @param {string[]} types        SCF internal post types to purge.
 */
async function purgeScfPosts( requestUtils, types ) {
	await requestUtils.rest( {
		method: 'POST',
		path: '/scf-test/v1/purge-fields',
		data: { types },
	} );
}

/**
 * Collect the data-key attributes of all field objects in the currently
 * open field group editor.
 *
 * @param {import('@playwright/test').Page} page Playwright page object.
 * @return {Promise<string[]>} Array of field keys.
 */
async function getFieldKeys( page ) {
	const fieldObjects = page.locator( '.acf-field-list > .acf-field-object' );
	const count = await fieldObjects.count();
	const keys = [];
	for ( let i = 0; i < count; i++ ) {
		keys.push( await fieldObjects.nth( i ).getAttribute( 'data-key' ) );
	}
	return keys;
}

test.describe( 'Field Group Duplication', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( PLUGIN_SLUG );
		await requestUtils.activatePlugin( UTILITIES_PLUGIN_SLUG );
		await purgeScfPosts( requestUtils, [ 'acf-field-group', 'acf-field' ] );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await purgeScfPosts( requestUtils, [ 'acf-field-group', 'acf-field' ] );
		await requestUtils.deleteAllPosts();
		await requestUtils.deactivatePlugin( UTILITIES_PLUGIN_SLUG );
		await requestUtils.deactivatePlugin( PLUGIN_SLUG );
	} );

	test( 'should duplicate a field group copying fields and settings with new field keys', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// SECTION 1: Create a field group with a text field and a select
		// field with choices. The default location rule (Post Type == Post)
		// is kept so both groups can later render on a post edit screen.
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		await page.locator( 'a.acf-btn:has-text("Add New")' ).click();

		await page.waitForSelector( '#title' );
		await page.fill( '#title', SOURCE_GROUP_TITLE );

		// First (auto-added) field: text.
		const firstFieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await firstFieldLabel.fill( TEXT_FIELD_LABEL );
		const firstFieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await firstFieldType.selectOption( 'text' );

		// Second field: select with choices.
		await page
			.locator( '.acf-field-list-wrap > .acf-tfoot a.add-field' )
			.click();
		const newFieldObject = page
			.locator( '.acf-field-list > .acf-field-object' )
			.last();
		const newFieldLabel = newFieldObject.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await expect( newFieldLabel ).toBeVisible( {
			timeout: DEFAULT_TIMEOUT,
		} );
		await newFieldLabel.fill( SELECT_FIELD_LABEL );
		await newFieldObject
			.locator( 'select[id^="acf_fields-field_"][id$="-type"]' )
			.selectOption( 'select' );

		// The select field is the only one with a choices setting.
		await addFieldChoices( page, SELECT_CHOICES );

		await page.click( 'button.acf-btn.acf-publish[type="submit"]' );
		const publishedNotice = page.locator( '.updated.notice' );
		await expect( publishedNotice ).toBeVisible( {
			timeout: DEFAULT_TIMEOUT,
		} );
		await expect( publishedNotice ).toContainText(
			'Field group published'
		);

		// Collect the original field keys from the editor.
		const originalKeys = await getFieldKeys( page );
		expect( originalKeys ).toHaveLength( 2 );
		for ( const key of originalKeys ) {
			expect( key ).toMatch( /^field_/ );
		}

		// SECTION 2: Duplicate the field group from the list row action.
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		const sourceRow = page.locator( '#the-list tr', {
			hasText: SOURCE_GROUP_TITLE,
		} );
		await expect( sourceRow ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );
		await sourceRow.hover();
		await sourceRow.locator( '.row-actions span.acfduplicate a' ).click();

		// The list reloads with a success notice linking to the duplicate.
		const duplicatedNotice = page.locator( '.acf-admin-notice' );
		await expect( duplicatedNotice ).toBeVisible( {
			timeout: DEFAULT_TIMEOUT,
		} );
		await expect( duplicatedNotice ).toContainText(
			'field group duplicated'
		);

		// Both groups now appear in the list.
		await expect(
			page.locator(
				`#the-list a.row-title:has-text("${ DUPLICATE_GROUP_TITLE }")`
			)
		).toBeVisible( { timeout: DEFAULT_TIMEOUT } );

		// SECTION 3: Open the duplicate and verify title, fields, settings,
		// and that all field keys are new.
		await page
			.locator(
				`#the-list a.row-title:has-text("${ DUPLICATE_GROUP_TITLE }")`
			)
			.click();
		await page.waitForSelector( '#title' );
		await expect( page.locator( '#title' ) ).toHaveValue(
			DUPLICATE_GROUP_TITLE
		);

		// Both fields were copied with their labels and types.
		const duplicateFieldObjects = page.locator(
			'.acf-field-list > .acf-field-object'
		);
		await expect( duplicateFieldObjects ).toHaveCount( 2 );
		await expect(
			duplicateFieldObjects.nth( 0 ).locator( '.li-field-label' )
		).toContainText( TEXT_FIELD_LABEL );
		await expect(
			duplicateFieldObjects.nth( 1 ).locator( '.li-field-label' )
		).toContainText( SELECT_FIELD_LABEL );
		expect(
			await duplicateFieldObjects.nth( 0 ).getAttribute( 'data-type' )
		).toBe( 'text' );
		expect(
			await duplicateFieldObjects.nth( 1 ).getAttribute( 'data-type' )
		).toBe( 'select' );

		// The select field's choices were copied. Field settings are
		// rendered lazily, so expand the select field row first.
		await duplicateFieldObjects
			.nth( 1 )
			.locator( 'a.edit-field, button.edit-field' )
			.first()
			.click();
		// Note: saved fields use their numeric ID in input ids, so target
		// the setting class instead of the id pattern used for new fields.
		const duplicateChoices = duplicateFieldObjects
			.nth( 1 )
			.locator( '.acf-field-setting-choices textarea' );
		await expect( duplicateChoices ).toBeVisible( {
			timeout: DEFAULT_TIMEOUT,
		} );
		expect( await duplicateChoices.inputValue() ).toBe(
			SELECT_CHOICES.join( '\n' )
		);

		// All duplicated field keys are valid and differ from the originals.
		const duplicateKeys = await getFieldKeys( page );
		expect( duplicateKeys ).toHaveLength( 2 );
		for ( const key of duplicateKeys ) {
			expect( key ).toMatch( /^field_/ );
			expect( originalKeys ).not.toContain( key );
		}

		// SECTION 4: Verify the original group is untouched.
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		await page
			.locator(
				`#the-list a.row-title:has-text("${ SOURCE_GROUP_TITLE }")`
			)
			.first()
			.click();
		await page.waitForSelector( '#title' );
		await expect( page.locator( '#title' ) ).toHaveValue(
			SOURCE_GROUP_TITLE
		);
		const sourceKeysAfter = await getFieldKeys( page );
		expect( sourceKeysAfter ).toEqual( originalKeys );

		// SECTION 5: Verify both groups function independently by rendering
		// their own metaboxes on a post edit screen.
		const post = await requestUtils.createPost( {
			title: 'Duplication Check Post',
			status: 'draft',
		} );
		await admin.editPost( post.id );

		// Wait for SCF metaboxes to be attached in the editor.
		await page
			.locator( '.acf-postbox' )
			.first()
			.waitFor( { state: 'attached' } );

		// One metabox per field group, each with its own copy of the fields.
		const duplicatePostbox = page.locator(
			`.acf-postbox:has-text("${ DUPLICATE_GROUP_TITLE }")`
		);
		await expect( duplicatePostbox ).toBeAttached( {
			timeout: DEFAULT_TIMEOUT,
		} );
		await expect( page.locator( '.acf-postbox' ) ).toHaveCount( 2 );
		await expect(
			page.locator( `.acf-field[data-name="${ TEXT_FIELD_NAME }"]` )
		).toHaveCount( 2 );
	} );
} );
