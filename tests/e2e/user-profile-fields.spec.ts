/**
 * E2E tests for field groups on user profile forms.
 *
 * Covers field groups with a "User Form" location rule rendering on the
 * own-profile screen (profile.php) and on another user's edit screen
 * (user-edit.php), saving and persisting values on both, and reading the
 * value back via the scf-test get_field() user plugin on the frontend.
 */
const { test, expect } = require( './fixtures' );
const { PLUGIN_SLUG, purgeScfInternalPosts } = require( './field-helpers' );

const UTILITIES_PLUGIN_SLUG = 'scf-test-utilities';
// Renders `get_field( 'user_title', 'user_<ID>' )` on the frontend via
// the_content. The target user is passed via the `scf_test_user_id` query arg.
const GET_FIELD_PLUGIN_SLUG = 'scf-test-plugin-get-field-user-title';

const FIELD_GROUP_TITLE = 'E2E User Profile Fields';
const FIELD_LABEL = 'User Title';
// Field name is auto-generated from the label by the field group editor.
const FIELD_NAME = 'user_title';
const PROFILE_VALUE = 'E2E Own Profile Value';
const OTHER_USER_VALUE = 'E2E Other User Value';

const DEFAULT_TIMEOUT = 5000;

/**
 * Create a field group with a text field located on user forms.
 *
 * @param {import('@playwright/test').Page} page          Playwright page object.
 * @param {Object}                          admin         Admin utilities.
 * @param {string}                          locationValue User Form rule value ('all' or 'edit').
 */
async function createUserFieldGroup( page, admin, locationValue ) {
	await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
	await page.locator( 'a.acf-btn:has-text("Add New")' ).click();

	await page.waitForSelector( '#title' );
	await page.fill( '#title', FIELD_GROUP_TITLE );

	const fieldLabelInput = page.locator(
		'input[id^="acf_fields-field_"][id$="-label"]'
	);
	await fieldLabelInput.fill( FIELD_LABEL );

	// Location rule: User Form == locationValue.
	await page.selectOption(
		'select[id^="acf_field_group-location-group_0-rule_0-param"]',
		'user_form'
	);
	await page.selectOption(
		'select[id^="acf_field_group-location-group_0-rule_0-operator"]',
		'=='
	);
	await page.selectOption(
		'select[id^="acf_field_group-location-group_0-rule_0-value"]',
		locationValue
	);

	await page.click( 'button.acf-btn.acf-publish[type="submit"]' );
	const successNotice = page.locator( '.updated.notice' );
	await expect( successNotice ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );
	await expect( successNotice ).toContainText( 'Field group published' );
}

test.describe( 'User Profile Fields', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( PLUGIN_SLUG );
		await requestUtils.activatePlugin( UTILITIES_PLUGIN_SLUG );
		await requestUtils.activatePlugin( GET_FIELD_PLUGIN_SLUG );
		// Remove leftover users from previous runs (keeps the admin user).
		await requestUtils.deleteAllUsers();
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await purgeScfInternalPosts( requestUtils, [
			'acf-field-group',
			'acf-field',
		] );
		await requestUtils.deleteAllPosts();
		await requestUtils.deleteAllUsers();
		await requestUtils.deactivatePlugin( GET_FIELD_PLUGIN_SLUG );
		await requestUtils.deactivatePlugin( UTILITIES_PLUGIN_SLUG );
		await requestUtils.deactivatePlugin( PLUGIN_SLUG );
	} );

	test.beforeEach( async ( { requestUtils } ) => {
		await purgeScfInternalPosts( requestUtils, [
			'acf-field-group',
			'acf-field',
		] );
	} );

	test( 'should render, save, and persist a field on the own profile screen', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		await createUserFieldGroup( page, admin, 'all' );

		// SECTION 1: Verify the field renders on profile.php.
		await admin.visitAdminPage( 'profile.php', '' );
		const profileField = page.locator(
			`.acf-field[data-name="${ FIELD_NAME }"] input`
		);
		await expect( profileField ).toBeVisible( {
			timeout: DEFAULT_TIMEOUT,
		} );

		// SECTION 2: Fill and save.
		await profileField.fill( PROFILE_VALUE );
		await page.click( '#submit' );

		const updateNotice = page.locator( '.updated.notice' );
		await expect( updateNotice ).toBeVisible( {
			timeout: DEFAULT_TIMEOUT,
		} );
		await expect( updateNotice ).toContainText( 'Profile updated' );

		// SECTION 3: Reload and verify persistence.
		await admin.visitAdminPage( 'profile.php', '' );
		await expect(
			page.locator( `.acf-field[data-name="${ FIELD_NAME }"] input` )
		).toHaveValue( PROFILE_VALUE );

		// SECTION 4: Verify the value is readable via get_field() on the
		// frontend (the test plugin appends the target user's field value
		// to post content on the author archive). Derive the admin's actual
		// user ID instead of assuming user 1, and pass it to the plugin via
		// the scf_test_user_id query arg.
		const adminUser = await requestUtils.rest( {
			path: '/wp/v2/users/me',
		} );
		await requestUtils.createPost( {
			title: 'User Field Frontend Check',
			status: 'publish',
		} );
		await page.goto(
			`/?author=${ adminUser.id }&scf_test_user_id=${ adminUser.id }`
		);
		const frontendValue = page.locator( '#scf-test-user-title' ).first();
		await expect( frontendValue ).toBeVisible( {
			timeout: DEFAULT_TIMEOUT,
		} );
		await expect( frontendValue ).toContainText(
			`User title: ${ PROFILE_VALUE }`
		);
	} );

	test( 'should render, save, and persist a field on another user edit screen', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		await createUserFieldGroup( page, admin, 'edit' );

		// Create a second user to edit.
		const user = await requestUtils.createUser( {
			username: 'scfe2euser',
			email: 'scfe2euser@example.com',
			password: 'scfE2Epassword!42',
			roles: [ 'editor' ],
		} );

		// SECTION 1: Verify the field renders on user-edit.php.
		await admin.visitAdminPage( 'user-edit.php', `user_id=${ user.id }` );
		const userField = page.locator(
			`.acf-field[data-name="${ FIELD_NAME }"] input`
		);
		await expect( userField ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );

		// SECTION 2: Fill and save.
		await userField.fill( OTHER_USER_VALUE );
		await page.click( '#submit' );

		const updateNotice = page.locator( '.updated.notice' );
		await expect( updateNotice ).toBeVisible( {
			timeout: DEFAULT_TIMEOUT,
		} );
		await expect( updateNotice ).toContainText( 'User updated' );

		// SECTION 3: Reload and verify persistence.
		await admin.visitAdminPage( 'user-edit.php', `user_id=${ user.id }` );
		await expect(
			page.locator( `.acf-field[data-name="${ FIELD_NAME }"] input` )
		).toHaveValue( OTHER_USER_VALUE );
	} );
} );
