/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const PLUGIN_SLUG = 'secure-custom-fields';
const PLUGIN_PATH = `${PLUGIN_SLUG}/${PLUGIN_SLUG}.php`;

test.describe('Custom Post Type', () => {
  const POST_TYPE_KEY = 'movie';
  const POST_TYPE_LABEL = 'Movie';
  const POST_TYPE_PLURAL = 'Movies';

  test.beforeEach(async ({ page, requestUtils }) => {
    // Login to WordPress admin
    await page.goto('/wp-admin');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'password');
    await page.click('#wp-submit');
    await requestUtils.activatePlugin(PLUGIN_SLUG);
  });

  test.afterAll(async ({ requestUtils }) => {
    await requestUtils.deactivatePlugin(PLUGIN_SLUG);
  });

  test('should be able to create a custom post type', async ({ page, admin }) => {
    // Navigate to Custom Fields → Post Types
    await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
    
    // Click "Add New" using the specific ACF button
    const addNewButton = page.locator('a.acf-btn.acf-btn-sm:has-text("Add New")');
    await addNewButton.click();
    
    // Fill in basic settings
    await page.fill('#title', POST_TYPE_LABEL);
    
    // Fill in post type settings using ACF field structure
    const settingsMain = page.locator('.acf-field-settings-main-general');
    
    // Fill in post type key
    const postTypeField = settingsMain.locator('.acf-field-setting-post_type .acf-input-wrap input');
    await postTypeField.fill(POST_TYPE_KEY);
    
    // Fill in labels
    const singularNameField = settingsMain.locator('.acf-field-setting-labels .acf-field-setting-singular_name .acf-input-wrap input');
    await singularNameField.fill(POST_TYPE_LABEL);
    
    const pluralNameField = settingsMain.locator('.acf-field-setting-labels .acf-field-setting-name .acf-input-wrap input');
    await pluralNameField.fill(POST_TYPE_PLURAL);
    
    // Enable public visibility
    await page.check('#acf_post_type_public');
    
    // Enable basic supports
    await page.check('#acf_post_type_supports_title');
    await page.check('#acf_post_type_supports_editor');
    await page.check('#acf_post_type_supports_thumbnail');
    
    // Save and publish
    await page.click('#publish');
    
    // Verify post type was created
    await expect(page.locator('.notice-success')).toBeVisible();
    
    // Verify post type appears in admin menu
    await page.goto('/wp-admin');
    await expect(page.locator(`#menu-posts-${POST_TYPE_KEY}`)).toBeVisible();
  });

  test('should be able to create a post in the custom post type', async ({ page }) => {
    const POST_TITLE = 'Test Movie';
    
    // Navigate to the custom post type admin page
    await page.goto(`/wp-admin/post-new.php?post_type=${POST_TYPE_KEY}`);
    
    // Fill in post details
    await page.fill('#title', POST_TITLE);
    await page.fill('.editor-post-title__input', POST_TITLE);
    
    // Add some content
    await page.click('.editor-default-block-appender');
    await page.keyboard.type('This is a test movie content.');
    
    // Publish the post
    await page.click('.editor-post-publish-button');
    
    // Verify post was created
    await expect(page.locator('.notice-success')).toBeVisible();
    
    // Verify post appears in the list
    await page.goto(`/wp-admin/edit.php?post_type=${POST_TYPE_KEY}`);
    await expect(page.locator(`.row-title:has-text("${POST_TITLE}")`)).toBeVisible();
  });

  test('should display custom post type on frontend', async ({ page }) => {
    // Create a test post
    const POST_TITLE = 'Frontend Test Movie';
    await page.goto(`/wp-admin/post-new.php?post_type=${POST_TYPE_KEY}`);
    await page.fill('#title', POST_TITLE);
    await page.click('.editor-post-publish-button');
    
    // Visit the post on frontend
    await page.goto(`/${POST_TYPE_KEY}/${POST_TITLE.toLowerCase().replace(/\s+/g, '-')}`);
    
    // Verify post content is visible
    await expect(page.locator('h1')).toHaveText(POST_TITLE);
  });
}); 