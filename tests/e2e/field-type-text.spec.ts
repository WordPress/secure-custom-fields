/**
 * WordPress dependencies
 */
const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

// Constants
const PLUGIN_SLUG = 'secure-custom-fields';
const TEST_PLUGIN_SLUG = 'scf-test-plugin-get-field-movie-title';
const FIELD_GROUP_LABEL = 'Movie Details';
const FIELD_LABEL = 'Movie Title';

test.describe('Field Type > Text', () => {
  test.beforeEach(async ({ requestUtils }) => {
    // Activate plugin
    await requestUtils.activatePlugin(PLUGIN_SLUG);
    await requestUtils.activatePlugin(TEST_PLUGIN_SLUG);
  });

  test.afterAll(async ({ requestUtils }) => {
    await requestUtils.deactivatePlugin(PLUGIN_SLUG);
    await requestUtils.deactivatePlugin(TEST_PLUGIN_SLUG);
  });

  
  test('should create a text field and verify it in admin', async ({ page, admin, editor, requestUtils }) => {
    // Navigate to Field Groups and create new.
    await admin.visitAdminPage('edit.php', 'post_type=acf-field-group');
    const addNewButton = page.locator('a.acf-btn:has-text("Add New")');
    await addNewButton.click();

    // Fill field group title.
    await page.waitForSelector('#title');
    await page.fill('#title', FIELD_GROUP_LABEL);

    // Add text field.
    const fieldLabel = page.locator('input[id^="acf_fields-field_"][id$="-label"]');
    await fieldLabel.fill(FIELD_LABEL);
    // The field name is generated automatically.

    // Select field type as text (it's default, but let's be explicit).
    const fieldType = page.locator('select[id^="acf_fields-field_"][id$="-type"]');
    await fieldType.selectOption('text');

    // Set location rule to post type: post (it's default, but let's be explicit).
    await page.selectOption('select[id^="acf_field_group-location-group"][id$="-param"]', 'post_type');
    await page.selectOption('select[id^="acf_field_group-location-group"][id$="-value"]', 'post');

    // Submit form.
    const publishButton = page.locator('button.acf-btn.acf-publish[type="submit"]');
    await publishButton.click();

    // Verify success message.
    const successNotice = page.locator('.updated.notice');
    await expect(successNotice).toBeVisible();
    await expect(successNotice).toContainText('Field group published');

    // Verify field group appears in the list.
    await admin.visitAdminPage('edit.php', 'post_type=acf-field-group');
    const fieldGroupRow = page.locator(`tr:has-text("${FIELD_GROUP_LABEL}")`);
    await expect(fieldGroupRow).toBeVisible();
    await createAndVerifyMoviePost(page, admin, editor, requestUtils);

    // Clean up - delete the field group
    await deleteFieldGroup(page, admin);

    // Empty trash
    await emptyTrash(page, admin);
  });
});

/**
 * Helper function to delete the field group
 */
async function deleteFieldGroup(page, admin) {
  await admin.visitAdminPage('edit.php', 'post_type=acf-field-group');
  
  // Find and select the field group row
  const fieldGroupRow = page.locator(`tr.type-acf-field-group:has(a.row-title:text("${FIELD_GROUP_LABEL}"))`);
  await expect(fieldGroupRow).toBeVisible({ timeout: 5000 });
  await fieldGroupRow.locator('th.check-column input[type="checkbox"]').check();
  
  // Use bulk actions to trash the field group
  await page.selectOption('#bulk-action-selector-bottom', 'trash');
  await page.click('#doaction2');
  
  // Verify deletion success message
  const deleteMessage = page.locator('.updated.notice');
  await expect(deleteMessage).toBeVisible({ timeout: 5000 });
  await expect(deleteMessage).toContainText('moved to the Trash');
}

/**
 * Helper function to empty trash
 */
async function emptyTrash(page, admin) {
  await admin.visitAdminPage('edit.php', 'post_status=trash&post_type=acf-field-group');
  const emptyTrashButton = page.locator('.tablenav.bottom input[name="delete_all"][value="Empty Trash"]');
  await emptyTrashButton.waitFor({ state: 'visible' });
  await emptyTrashButton.click();
  
  // Verify success notice
  const successNotice = page.locator('.notice.updated p');
  await expect(successNotice).toBeVisible();
  await expect(successNotice).toHaveText(/post permanently deleted/);
}


/**
 * Helper function to create a post with movie title and verify it on frontend
 */
async function createAndVerifyMoviePost(page, admin, editor, requestUtils) {
  // Create a new post
  const post = await requestUtils.createPost({
    title: 'Movie 1',
    status: 'draft',
  });

  // Navigate to edit post page
  await admin.visitAdminPage('post.php', `post=${post.id}&action=edit`);

  // Fill in the movie title field using data-name attribute
  const movieTitleField = page.locator('.acf-field[data-name="movie_title"] input[type="text"]');
  await movieTitleField.fill('The Shawshank Redemption');

  // Save Draft
  await editor.saveDraft();

  // Verify the movie title is displayed
  const previewPage = await editor.openPreviewPage();

  const movieTitleElement = previewPage.locator('#scf-test-movie-title');
  await expect(movieTitleElement).toBeVisible();
  await expect(movieTitleElement).toContainText('Movie title: The Shawshank Redemption');

  // Close the preview tab
  await previewPage.close();
}
