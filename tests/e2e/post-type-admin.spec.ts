/**
 * WordPress dependencies
 */
const { test, expect } = require( './fixtures' );

// Constants
const PLUGIN_SLUG = 'secure-custom-fields';

// SCF internal post type slugs
const SCF_POST_TYPE_SLUG = 'acf-post-type';
const SCF_TAXONOMY_SLUG = 'acf-taxonomy';
const SCF_FIELD_GROUP_SLUG = 'acf-field-group';

// Test entity constants
const TEST_POST_TYPE = 'movie';
const POST_TYPE_NAME = 'Movies';
const POST_TYPE_SINGULAR = 'Movie';

// Integration test constants - using short keys to avoid 20-char limit
const INTEGRATION_POST_TYPE_KEY = 'e2eproject';
const INTEGRATION_POST_TYPE_NAME = 'E2E Projects';
const INTEGRATION_POST_TYPE_SINGULAR = 'E2E Project';
const INTEGRATION_TAXONOMY_KEY = 'e2estatus';
const INTEGRATION_TAXONOMY_NAME = 'E2E Statuses';
const INTEGRATION_TAXONOMY_SINGULAR = 'E2E Status';
const INTEGRATION_FIELD_GROUP_NAME = 'E2E Project Fields';
const INTEGRATION_FIELD_LABEL = 'Project Notes';

// Standard timeout for UI operations
const DEFAULT_TIMEOUT = 5000;

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

	test( 'should trash and restore a post type', async ( { page, admin } ) => {
		// SECTION: Create new post type
		await createCustomPostType( page, admin );

		// SECTION: Trash the post type
		await deletePostType( page, admin );

		// SECTION: Verify not in main list
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-post-type' );
		const postTypeLink = page.locator(
			`#the-list a:has-text("${ POST_TYPE_NAME }")`
		);
		await expect( postTypeLink ).not.toBeVisible();

		// SECTION: Restore from trash
		await restorePostType( page, admin );

		// SECTION: Verify restored to main list
		await verifyPostTypeCreated( page, admin );

		// SECTION: Clean up
		await deletePostType( page, admin );
		await trashAllPostTypes( page, admin );
	} );

	test( 'should create hierarchical post type with taxonomy and field group', async ( {
		page,
		admin,
	} ) => {
		// Clean up any leftover entities from previous test runs
		await cleanupIntegrationTestEntities( page, admin );

		await test.step( 'Create a hierarchical taxonomy', async () => {
			await admin.visitAdminPage(
				'edit.php',
				`post_type=${ SCF_TAXONOMY_SLUG }`
			);
			await page.locator( 'a.acf-btn:has-text("Add New")' ).click();

			await page.fill(
				'#acf_taxonomy-labels-name',
				INTEGRATION_TAXONOMY_NAME
			);
			await page.fill(
				'#acf_taxonomy-labels-singular_name',
				INTEGRATION_TAXONOMY_SINGULAR
			);
			await page.fill(
				'#acf_taxonomy-taxonomy',
				INTEGRATION_TAXONOMY_KEY
			);

			await page.click( 'button.acf-btn.acf-publish[type="submit"]' );
			await expectSuccessNotice( page, 'taxonomy created' );
		} );

		await test.step( 'Create hierarchical post type linked to the taxonomy', async () => {
			await admin.visitAdminPage(
				'edit.php',
				`post_type=${ SCF_POST_TYPE_SLUG }`
			);
			await page.locator( 'a.acf-btn:has-text("Add New")' ).click();

			await page.fill(
				'#acf_post_type-labels-name',
				INTEGRATION_POST_TYPE_NAME
			);
			await page.fill(
				'#acf_post_type-labels-singular_name',
				INTEGRATION_POST_TYPE_SINGULAR
			);
			await page.fill(
				'#acf_post_type-post_type',
				INTEGRATION_POST_TYPE_KEY
			);

			// Enable hierarchical (like pages)
			const hierarchicalToggle = page.locator(
				'.acf-field[data-name="hierarchical"] .acf-switch'
			);
			await hierarchicalToggle.click();

			// Verify hierarchical toggle is now active
			await expect( hierarchicalToggle ).toHaveClass(
				/acf-switch-on|-on/
			);

			// Link to our taxonomy
			const taxonomiesField = page.locator(
				'.acf-field[data-name="taxonomies"]'
			);
			await taxonomiesField.locator( '.select2-selection' ).click();
			await page
				.locator(
					`.select2-results__option:has-text("${ INTEGRATION_TAXONOMY_SINGULAR }")`
				)
				.click();

			await page.click( 'button.acf-btn.acf-publish[type="submit"]' );
			await expectSuccessNotice( page, 'post type created' );

			// Verify taxonomy link persisted by checking select2 shows it
			await expect(
				taxonomiesField.locator(
					`.select2-selection__choice:has-text("${ INTEGRATION_TAXONOMY_SINGULAR }")`
				)
			).toBeVisible();

			// Verify hierarchical setting persisted
			await expect( hierarchicalToggle ).toHaveClass(
				/acf-switch-on|-on/
			);
		} );

		await test.step( 'Verify post type-taxonomy integration on the post type screen', async () => {
			// Navigate to the custom post type's "Add New" screen via the
			// admin menu (a dashboard visit triggers fresh WordPress init
			// so the post type is registered).
			await visitPostTypeAddNewScreen( page, admin );

			// Verify the taxonomy panel appears in the sidebar (block
			// editor displays taxonomies in sidebar). The panel uses the
			// taxonomy's plural label (INTEGRATION_TAXONOMY_NAME).
			const taxonomyPanel = page.getByRole( 'button', {
				name: INTEGRATION_TAXONOMY_NAME,
			} );
			await expect( taxonomyPanel ).toBeVisible( {
				timeout: DEFAULT_TIMEOUT,
			} );
		} );

		await test.step( 'Create field group with location rule for the custom post type', async () => {
			// Fresh page load ensures post type cache is updated
			await admin.visitAdminPage(
				'edit.php',
				`post_type=${ SCF_FIELD_GROUP_SLUG }`
			);
			await page.locator( 'a.acf-btn:has-text("Add New")' ).click();

			await page.fill( '#title', INTEGRATION_FIELD_GROUP_NAME );

			// Add a text field
			const fieldLabelInput = page.locator(
				'input[id^="acf_fields-field_"][id$="-label"]'
			);
			await fieldLabelInput.fill( INTEGRATION_FIELD_LABEL );

			// Set location rule: Post Type == our custom post type
			const paramSelect = page.locator(
				'select[id^="acf_field_group-location-group_0-rule_0-param"]'
			);
			await paramSelect.scrollIntoViewIfNeeded();
			await paramSelect.selectOption( 'post_type' );

			// Wait for our custom post type to appear in the dropdown
			// (by label text)
			const valueSelect = page.locator(
				'select[id^="acf_field_group-location-group_0-rule_0-value"]'
			);
			await expect(
				valueSelect.locator(
					`option:has-text("${ INTEGRATION_POST_TYPE_SINGULAR }")`
				)
			).toBeAttached( { timeout: DEFAULT_TIMEOUT } );

			// Select by label text since the value may be auto-generated
			await valueSelect.selectOption( {
				label: INTEGRATION_POST_TYPE_SINGULAR,
			} );

			await page.click( 'button.acf-btn.acf-publish[type="submit"]' );
			await expectSuccessNotice( page, 'Field group published' );
		} );

		await test.step( 'Verify field group appears on the custom post type edit screen', async () => {
			// Navigate via menu to ensure post type is fully registered
			await visitPostTypeAddNewScreen( page, admin, {
				prepareEditor: false,
			} );

			await prepareBlockEditorForMetabox( page );
			await verifyFieldInMetabox(
				page,
				INTEGRATION_FIELD_GROUP_NAME,
				INTEGRATION_FIELD_LABEL
			);
		} );

		await test.step( 'Verify all entities appear in their admin lists', async () => {
			await expectEntityInAdminList(
				page,
				admin,
				SCF_POST_TYPE_SLUG,
				INTEGRATION_POST_TYPE_NAME
			);
			await expectEntityInAdminList(
				page,
				admin,
				SCF_TAXONOMY_SLUG,
				INTEGRATION_TAXONOMY_NAME
			);
			await expectEntityInAdminList(
				page,
				admin,
				SCF_FIELD_GROUP_SLUG,
				INTEGRATION_FIELD_GROUP_NAME
			);
		} );

		await test.step( 'Clean up integration entities', async () => {
			await cleanupIntegrationTestEntities( page, admin );
		} );
	} );
} );

/**
 * Helper function to create a custom post type
 * @param page
 * @param admin
 */
async function createCustomPostType( page, admin ) {
	// Navigate to post types admin page
	await admin.visitAdminPage(
		'edit.php',
		`post_type=${ SCF_POST_TYPE_SLUG }`
	);

	// Click "Add New" button
	const addNewButton = page.locator( 'a.acf-btn:has-text("Add New")' );
	await expect( addNewButton ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );
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
	await expectSuccessNotice( page, `${ POST_TYPE_NAME } post type created` );
}

/**
 * Helper function to verify post type was created
 * @param page
 * @param admin
 */
async function verifyPostTypeCreated( page, admin ) {
	// Check post type appears in the list
	await admin.visitAdminPage(
		'edit.php',
		`post_type=${ SCF_POST_TYPE_SLUG }`
	);
	const postTypeLink = page.locator(
		`#the-list a:has-text("${ POST_TYPE_NAME }")`
	);
	await expect( postTypeLink ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );
}

/**
 * Helper function to verify post type shows in admin menu and works
 * @param page
 */
async function verifyPostTypeInAdminMenu( page ) {
	// Check post type appears in admin menu
	const menuItem = page.locator( `#menu-posts-${ TEST_POST_TYPE }` );
	await expect( menuItem ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );

	// Navigate to post type admin page
	await menuItem.click();
	await expect( page.locator( 'h1.wp-heading-inline' ) ).toContainText(
		POST_TYPE_NAME
	);
}

/**
 * Helper function to update an existing post type
 * @param page
 * @param admin
 * @param newName
 * @param newSingular
 */
async function updatePostType( page, admin, newName, newSingular ) {
	await admin.visitAdminPage(
		'edit.php',
		`post_type=${ SCF_POST_TYPE_SLUG }`
	);

	// Click on the post type to edit it
	const postTypeLink = page.locator(
		`#the-list a.row-title:has-text("${ POST_TYPE_NAME }")`
	);
	await expect( postTypeLink ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );
	await postTypeLink.click();

	// Verify we're on the edit page
	await expect( page ).toHaveURL( /.*post\.php\?post=\d+&action=edit/ );

	// Update the fields
	await page.fill( '#acf_post_type-labels-name', newName );
	await page.fill( '#acf_post_type-labels-singular_name', newSingular );

	// Submit the update
	await page.click( 'button.acf-btn.acf-publish[type="submit"]' );

	// Verify success notification
	await expectSuccessNotice( page, `${ newName } post type updated` );
}

/**
 * Helper function to verify post type was updated, not duplicated
 * @param page
 * @param admin
 * @param updatedName
 */
async function verifyPostTypeUpdated( page, admin, updatedName ) {
	await admin.visitAdminPage(
		'edit.php',
		`post_type=${ SCF_POST_TYPE_SLUG }`
	);

	// Verify the updated post type exists
	const updatedPostTypeLink = page.locator(
		`#the-list a:has-text("${ updatedName }")`
	);
	await expect( updatedPostTypeLink ).toBeVisible( {
		timeout: DEFAULT_TIMEOUT,
	} );

	// Verify the original post type no longer exists
	const originalPostTypeLink = page.locator(
		`#the-list a:has-text("${ POST_TYPE_NAME }")`
	);
	await expect( originalPostTypeLink ).not.toBeVisible();

	// Count how many post types with the updated name exist (should be exactly 1)
	const postTypeCount = await page
		.locator( `#the-list a:has-text("${ updatedName }")` )
		.count();
	expect( postTypeCount ).toBe( 1 );
}

/**
 * Helper function to delete the post type
 * @param page
 * @param admin
 * @param postTypeName
 */
async function deletePostType( page, admin, postTypeName = POST_TYPE_NAME ) {
	await admin.visitAdminPage(
		'edit.php',
		`post_type=${ SCF_POST_TYPE_SLUG }`
	);

	// Find and select the post type row
	const postTypeRow = page.locator(
		`tr.type-acf-post-type:has(a.row-title:text("${ postTypeName }"))`
	);
	await expect( postTypeRow ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );
	await postTypeRow
		.locator( 'th.check-column input[type="checkbox"]' )
		.check();

	// Use bulk actions to trash the post type
	await page.selectOption( '#bulk-action-selector-bottom', 'trash' );
	await page.click( '#doaction2' );

	// Verify deletion success message
	await expectSuccessNotice( page, 'moved to the Trash' );
}

/**
 * Helper function to restore a post type from trash.
 * @param page
 * @param admin
 * @param postTypeName
 */
async function restorePostType( page, admin, postTypeName = POST_TYPE_NAME ) {
	await admin.visitAdminPage(
		'edit.php',
		`post_status=trash&post_type=${ SCF_POST_TYPE_SLUG }`
	);

	// Find the trashed post type row
	const postTypeRow = page.locator( '#the-list tr', {
		hasText: postTypeName,
	} );
	await expect( postTypeRow ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );
	await postTypeRow
		.locator( 'th.check-column input[type="checkbox"]' )
		.check();

	// Use bulk actions to restore
	await page.selectOption( '#bulk-action-selector-bottom', 'untrash' );
	await page.click( '#doaction2' );

	// Verify restore success
	const restoreMessage = page.locator( '.updated.notice' );
	await expect( restoreMessage ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );
}

/**
 * Helper function to trash all post types created.
 * @param page
 * @param admin
 */
async function trashAllPostTypes( page, admin ) {
	await admin.visitAdminPage(
		'edit.php',
		`post_status=trash&post_type=${ SCF_POST_TYPE_SLUG }`
	);

	const emptyTrashButton = page.locator(
		'.tablenav.bottom input[name="delete_all"][value="Empty Trash"]'
	);
	await emptyTrashButton.waitFor( {
		state: 'visible',
		timeout: DEFAULT_TIMEOUT,
	} );
	await emptyTrashButton.click();

	const successNotice = page.locator( '.notice.updated p' );
	await expect( successNotice ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );
	await expect( successNotice ).toHaveText( /posts? permanently deleted/ );
}

/**
 * Helper function to empty trash for a given post type.
 * @param page
 * @param admin
 * @param postType
 */
async function emptyTrashForPostType( page, admin, postType ) {
	await admin.visitAdminPage(
		'edit.php',
		`post_status=trash&post_type=${ postType }`
	);
	const emptyTrashButton = page.locator(
		'.tablenav.bottom input[name="delete_all"][value="Empty Trash"]'
	);
	if (
		await emptyTrashButton
			.isVisible( { timeout: 1000 } )
			.catch( () => false )
	) {
		await emptyTrashButton.click();
		await page.waitForLoadState( 'networkidle' );
	}
}

/**
 * Helper function to trash an entity by name.
 * @param page
 * @param admin
 * @param postType
 * @param entityName
 */
async function trashEntityByName( page, admin, postType, entityName ) {
	await admin.visitAdminPage( 'edit.php', `post_type=${ postType }` );

	const entityRow = page.locator( '#the-list tr', { hasText: entityName } );
	if ( await entityRow.isVisible( { timeout: 1000 } ).catch( () => false ) ) {
		await entityRow
			.locator( 'th.check-column input[type="checkbox"]' )
			.check();
		await page.selectOption( '#bulk-action-selector-bottom', 'trash' );
		await page.click( '#doaction2' );
		await page.waitForLoadState( 'networkidle' );
	}
}

/**
 * Helper function to clean up integration test entities.
 * @param page
 * @param admin
 */
async function cleanupIntegrationTestEntities( page, admin ) {
	// Delete field group
	await trashEntityByName(
		page,
		admin,
		SCF_FIELD_GROUP_SLUG,
		INTEGRATION_FIELD_GROUP_NAME
	);
	await emptyTrashForPostType( page, admin, SCF_FIELD_GROUP_SLUG );

	// Delete post type
	await trashEntityByName(
		page,
		admin,
		SCF_POST_TYPE_SLUG,
		INTEGRATION_POST_TYPE_NAME
	);
	await emptyTrashForPostType( page, admin, SCF_POST_TYPE_SLUG );

	// Delete taxonomy
	await trashEntityByName(
		page,
		admin,
		SCF_TAXONOMY_SLUG,
		INTEGRATION_TAXONOMY_NAME
	);
	await emptyTrashForPostType( page, admin, SCF_TAXONOMY_SLUG );
}

/**
 * Helper to navigate to the integration post type's "Add New" screen via the
 * admin menu. Visiting the dashboard first triggers fresh WordPress init so
 * the custom post type is registered, and the visible menu link proves it.
 * @param page
 * @param admin
 * @param options
 * @param options.prepareEditor Whether to wait for the block editor and close
 *                              any blocking modal after navigating.
 */
async function visitPostTypeAddNewScreen(
	page,
	admin,
	{ prepareEditor = true } = {}
) {
	await admin.visitAdminPage( 'index.php', '' );

	// Verify custom post type appears in admin menu (proves it's registered)
	const customPostTypeMenu = page.locator( '#adminmenu' ).getByRole( 'link', {
		name: INTEGRATION_POST_TYPE_NAME,
		exact: true,
	} );
	await expect( customPostTypeMenu ).toBeVisible( {
		timeout: DEFAULT_TIMEOUT,
	} );
	await customPostTypeMenu.click();

	// Click "Add E2E Project" button (WordPress uses singular label)
	await page
		.locator(
			`.wrap a:has-text("Add ${ INTEGRATION_POST_TYPE_SINGULAR }")`
		)
		.click();

	if ( prepareEditor ) {
		// Wait for block editor to load
		await page.waitForTimeout( 1000 );
		await closeEditorModal( page );
	}
}

/**
 * Helper to assert an entity appears in its admin list table.
 * @param page
 * @param admin
 * @param postType
 * @param entityName
 */
async function expectEntityInAdminList( page, admin, postType, entityName ) {
	await admin.visitAdminPage( 'edit.php', `post_type=${ postType }` );
	await expect(
		page.locator( `#the-list a:has-text("${ entityName }")` )
	).toBeVisible();
}

/**
 * Helper to verify a success notice appears with expected text.
 * @param page
 * @param textContains
 */
async function expectSuccessNotice( page, textContains ) {
	const notice = page.locator( '.updated.notice' );
	await expect( notice ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );
	await expect( notice ).toContainText( textContains );
}

/**
 * Helper to prepare block editor for metabox testing.
 * Closes pattern modal and expands Meta Boxes panel if needed.
 * @param page
 */
async function prepareBlockEditorForMetabox( page ) {
	// Wait for page to stabilize
	await page.waitForTimeout( 1000 );

	await closeEditorModal( page );

	// Scroll to bottom to ensure meta boxes area is visible
	await page.evaluate( () =>
		window.scrollTo( 0, document.body.scrollHeight )
	);
	await page.waitForTimeout( 500 );

	// Expand Meta Boxes section if collapsed
	const metaBoxesToggle = page.locator( 'button:has-text("Meta Boxes")' );
	if ( await metaBoxesToggle.isVisible() ) {
		const isExpanded =
			await metaBoxesToggle.getAttribute( 'aria-expanded' );
		if ( isExpanded === 'false' ) {
			await metaBoxesToggle.evaluate( ( el ) => el.click() );
			await page.waitForTimeout( 500 );
		}
	}
}

/**
 * Helper to close WordPress editor modals that hide the editor from the
 * accessible tree, such as the first-run welcome guide or pattern picker.
 * @param page
 */
async function closeEditorModal( page ) {
	const editorModal = page.locator( '.components-modal__frame' );
	if (
		await editorModal.isVisible( { timeout: 1000 } ).catch( () => false )
	) {
		await page.keyboard.press( 'Escape' );
		await expect( editorModal ).not.toBeVisible( { timeout: 3000 } );
	}
}

/**
 * Helper to expand a collapsed metabox and verify field visibility.
 * @param page
 * @param metaboxName
 * @param fieldLabel
 */
async function verifyFieldInMetabox( page, metaboxName, fieldLabel ) {
	const metabox = page.locator( `.acf-postbox:has-text("${ metaboxName }")` );
	await expect( metabox ).toBeAttached( { timeout: DEFAULT_TIMEOUT } );

	// Expand if collapsed
	const isCollapsed = await metabox.evaluate( ( el ) =>
		el.classList.contains( 'closed' )
	);
	if ( isCollapsed ) {
		await metabox.locator( '.hndle, .handlediv' ).first().click();
		await page.waitForTimeout( 300 );
	}

	// Verify field is visible
	const field = metabox.locator( `.acf-field:has-text("${ fieldLabel }")` );
	await expect( field ).toBeVisible( { timeout: DEFAULT_TIMEOUT } );
}
