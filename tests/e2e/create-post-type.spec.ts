/**
 * WordPress dependencies
 */
const { test, expect } = require( './fixtures' );

// Constants
const PLUGIN_SLUG = 'secure-custom-fields';
const TEST_POST_TYPE = 'movie';
const POST_TYPE_NAME = 'Movies';
const POST_TYPE_SINGULAR = 'Movie';

/**
 * Post Type Creation Test Suite
 */
test.describe( 'Post Type Creation', () => {
	test.beforeEach( async ( { requestUtils } ) => {
		// Activate plugin and login to WordPress admin
		await requestUtils.activatePlugin( PLUGIN_SLUG );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deactivatePlugin( PLUGIN_SLUG );
	} );

	test( 'should create, verify and delete a custom post type', async ( {
		page,
		admin,
	} ) => {
		// SECTION: Create new post type
		await createCustomPostType( page, admin );

		// SECTION: Verify post type creation
		await verifyPostTypeCreated( page, admin );

		// SECTION: Verify post type in admin menu
		await verifyPostTypeInAdminMenu( page );

		// SECTION: Clean up - delete the post type
		await deletePostType( page, admin );

		// SECTION: Trash all post types created
		await trashAllPostTypes( page, admin );
	} );

	test( 'should update an existing post type without creating duplicates', async ( {
		page,
		admin,
	} ) => {
		// SECTION: Create new post type
		await createCustomPostType( page, admin );

		// SECTION: Update the post type
		await updatePostType( page, admin, 'Games', 'Game' );

		// SECTION: Verify post type was updated, not duplicated
		await verifyPostTypeUpdated( page, admin, 'Games' );

		// SECTION: Clean up - delete the post type
		await deletePostType( page, admin, 'Games' );

		// SECTION: Trash all post types created
		await trashAllPostTypes( page, admin );
	} );
} );

/**
 * Helper function to create a custom post type
 */
async function createCustomPostType( page, admin ) {
	// Navigate to post types admin page
	await admin.visitAdminPage( 'edit.php', 'post_type=acf-post-type' );

	// Click "Add New" button
	const addNewButton = page.locator(
		'a.acf-btn.acf-btn-sm:has(i.acf-icon-plus)',
		{ hasText: 'Add New' }
	);
	await expect( addNewButton ).toBeVisible( { timeout: 5000 } );
	await addNewButton.click();

	// Verify we're on the creation page
	await expect( page ).toHaveURL(
		/.*post-new\.php\?post_type=acf-post-type/
	);
	await expect( page.locator( 'div.wrap h1' ) ).toContainText(
		'Add New Post Type'
	);

	// Fill required fields
	await page.fill( '#acf_post_type-labels-name', POST_TYPE_NAME );
	await page.fill(
		'#acf_post_type-labels-singular_name',
		POST_TYPE_SINGULAR
	);

	// Submit form
	await page.click( 'button.acf-btn.acf-publish[type="submit"]' );

	// Verify success notification
	const successNotice = page.locator( '.updated.notice' );
	await expect( successNotice ).toBeVisible( { timeout: 5000 } );
	await expect( successNotice ).toContainText(
		`${ POST_TYPE_NAME } post type created`
	);
}

/**
 * Helper function to verify post type was created
 */
async function verifyPostTypeCreated( page, admin ) {
	// Check post type appears in the list
	await admin.visitAdminPage( 'edit.php', 'post_type=acf-post-type' );
	const postTypeLink = page.locator(
		`#the-list a:has-text("${ POST_TYPE_NAME }")`
	);
	await expect( postTypeLink ).toBeVisible( { timeout: 5000 } );
}

/**
 * Helper function to verify post type shows in admin menu and works
 */
async function verifyPostTypeInAdminMenu( page ) {
	// Check post type appears in admin menu
	const menuItem = page.locator( `#menu-posts-${ TEST_POST_TYPE }` );
	await expect( menuItem ).toBeVisible( { timeout: 5000 } );

	// Navigate to post type admin page
	await menuItem.click();
	await expect( page.locator( 'h1.wp-heading-inline' ) ).toContainText(
		POST_TYPE_NAME
	);
}

/**
 * Helper function to update an existing post type
 */
async function updatePostType( page, admin, newName, newSingular ) {
	await admin.visitAdminPage( 'edit.php', 'post_type=acf-post-type' );

	// Click on the post type to edit it
	const postTypeLink = page.locator(
		`#the-list a.row-title:has-text("${ POST_TYPE_NAME }")`
	);
	await expect( postTypeLink ).toBeVisible( { timeout: 5000 } );
	await postTypeLink.click();

	// Verify we're on the edit page
	await expect( page ).toHaveURL( /.*post\.php\?post=\d+&action=edit/ );

	// Update the fields
	await page.fill( '#acf_post_type-labels-name', newName );
	await page.fill( '#acf_post_type-labels-singular_name', newSingular );

	// Submit the update
	await page.click( 'button.acf-btn.acf-publish[type="submit"]' );

	// Verify success notification
	const successNotice = page.locator( '.updated.notice' );
	await expect( successNotice ).toBeVisible( { timeout: 5000 } );
	await expect( successNotice ).toContainText(
		`${ newName } post type updated`
	);
}

/**
 * Helper function to verify post type was updated, not duplicated
 */
async function verifyPostTypeUpdated( page, admin, updatedName ) {
	await admin.visitAdminPage( 'edit.php', 'post_type=acf-post-type' );

	// Verify the updated post type exists
	const updatedPostTypeLink = page.locator(
		`#the-list a:has-text("${ updatedName }")`
	);
	await expect( updatedPostTypeLink ).toBeVisible( { timeout: 5000 } );

	// Verify the original post type no longer exists
	const originalPostTypeLink = page.locator(
		`#the-list a:has-text("${ POST_TYPE_NAME }")`
	);
	await expect( originalPostTypeLink ).not.toBeVisible();

	// Count how many post types with the updated name exist (should be exactly 1)
	const postTypeCount = await page
		.locator( `#the-list a:has-text("${ updatedName }")` )
		.count();
	expect( postTypeCount ).toBe(
		1,
		`Should have exactly 1 post type named "${ updatedName }", but found ${ postTypeCount }`
	);
}

/**
 * Helper function to delete the post type
 */
async function deletePostType( page, admin, postTypeName = POST_TYPE_NAME ) {
	await admin.visitAdminPage( 'edit.php', 'post_type=acf-post-type' );

	// Find and select the post type row
	const postTypeRow = page.locator(
		`tr.type-acf-post-type:has(a.row-title:text("${ postTypeName }"))`
	);
	await expect( postTypeRow ).toBeVisible( { timeout: 5000 } );
	await postTypeRow
		.locator( 'th.check-column input[type="checkbox"]' )
		.check();

	// Use bulk actions to trash the post type
	await page.selectOption( '#bulk-action-selector-bottom', 'trash' );
	await page.click( '#doaction2' );

	// Verify deletion success message
	const deleteMessage = page.locator( '.updated.notice' );
	await expect( deleteMessage ).toBeVisible( { timeout: 5000 } );
	await expect( deleteMessage ).toContainText( 'moved to the Trash' );
}

/**
 * Helper function to trash all post types created.
 */
async function trashAllPostTypes( page, admin ) {
	await admin.visitAdminPage(
		'edit.php',
		'post_status=trash&post_type=acf-post-type'
	);
	const emptyTrashButton = page.locator(
		'.tablenav.bottom input[name="delete_all"][value="Empty Trash"]'
	);
	await emptyTrashButton.waitFor( { state: 'visible' } );
	await emptyTrashButton.click();
	const successNotice = page.locator( '.notice.updated p' );
	await expect( successNotice ).toBeVisible();
	await expect( successNotice ).toHaveText( /posts? permanently deleted/ );
}
