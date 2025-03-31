/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const PLUGIN_SLUG = 'secure-custom-fields';
const PLUGIN_PATH = `${PLUGIN_SLUG}/${PLUGIN_SLUG}.php`;
const FIELD_GROUP_LABEL = 'Movies';
const MOVIE_FIELD_LABEL = 'Movie Title';

test.describe('Field Group > Input Text', () => {
  
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

  test('should be able to create a new field group with a text field', async ({ page, admin }) => {
    // Navigate to Custom Fields → Post Types.
    await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
    
    // Click "Add New" using the specific ACF button.
    const addNewButton = page.locator('a.acf-btn.acf-btn-sm:has-text("Add New")');
    await addNewButton.click();
    
    // Fill in basic settings.
    await page.fill('#title', FIELD_GROUP_LABEL);
    
    // Fill in labels using a more robust selector that ignores dynamic IDs.
    const fieldLabel = page.locator('input[id^="acf_fields-field_"][id$="-label"]');
    await fieldLabel.fill(MOVIE_FIELD_LABEL);
    
    // Save and publish using the correct ACF button selector.
    const submitButton = page.locator('button.acf-btn.acf-publish[type="submit"]');
    await submitButton.click();
    
    // Verify field group and field were created.
    await expect(page.locator('.notice-success')).toBeVisible();
  });

}); 