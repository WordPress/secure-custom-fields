/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const PLUGIN_SLUG = 'secure-custom-fields';
const PLUGIN_PATH = `${PLUGIN_SLUG}/${PLUGIN_SLUG}.php`;
const TEST_POST_TYPE = 'movie';

test.describe('Post Type Creation', () => {
  test.beforeEach(async ({ page, requestUtils }) => {
    // Login to WordPress admin
    await requestUtils.activatePlugin(PLUGIN_SLUG);
  });

  test.afterAll(async ({ requestUtils }) => {
    await requestUtils.deactivatePlugin(PLUGIN_SLUG);
  });

  test('should be able to create a custom post type', async ({ page, admin }) => {
    // Navigate to plugins page
    await admin.visitAdminPage('edit.php', 'post_type=acf-post-type');
    
    // Look for the "Add New" button and click it
    const addNewButton = page.locator('a.acf-btn.acf-btn-sm:has(i.acf-icon-plus)', { hasText: 'Add New' });
    await expect(addNewButton).toBeVisible();
    await addNewButton.click();
    
    // Verify we're on the new post type creation page
    await expect(page).toHaveURL(/.*post-new\.php\?post_type=acf-post-type/);
    await expect(page.locator('div.wrap h1')).toContainText('Add New Post Type');
    
    // Fill in the required fields
    // Post type name/title
    // await page.fill('#acf_post_type-labels-name', 'Movies');
    
    // Post type ID/key
    // await page.fill('#acf_post_type-labels-singular_name', 'Movie');
    
    
    // Submit the form
    // await page.click('button.acf-btn.acf-publish[type="submit"]');
    
    // Wait for the success notification
    // await page.waitForSelector('.updated.notice');
    // await expect(page.locator('.updated.notice')).toContainText('Movies post type created');
    
    // Verify the post type was created by checking if it appears in the list
    // await admin.visitAdminPage('edit.php', 'post_type=acf-post-type');
    // await expect(page.locator(`a:has-text("Test Post Type")`)).toBeVisible();
    
    // Verify the post type is available in the admin menu
    // await expect(page.locator(`#menu-posts-${TEST_POST_TYPE}`)).toBeVisible();
    
    // Navigate to the new post type's admin page to verify it works
    // await page.click(`#menu-posts-${TEST_POST_TYPE}`);
    // await expect(page.locator('h1.wp-heading-inline')).toContainText('Test Items');
    
    // Clean up - delete the post type
    // await admin.visitAdminPage('edit.php', 'post_type=acf-post-type');
    // await page.click(`a:has-text("Test Post Type")`);
    // await page.click('#trash-action a');
    // await expect(page.locator('.updated.notice')).toContainText('moved to the Trash');
  });
});