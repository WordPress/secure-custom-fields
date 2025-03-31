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
    await page.waitForLoadState('networkidle');
    
    // Wait for login form to be ready
    await page.waitForSelector('#user_login');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'password');
    
    // Wait for submit button and click it
    await page.waitForSelector('#wp-submit');
    await page.click('#wp-submit');
    
    // Wait for admin dashboard to load
    await page.waitForSelector('.wp-admin');
    
    // Activate plugin and wait for success
    await requestUtils.activatePlugin(PLUGIN_SLUG);
  });

  test.afterAll(async ({ requestUtils }) => {
    await requestUtils.deactivatePlugin(PLUGIN_SLUG);
  });

  test('should be able to create a new field group with a text field', async ({ page, admin }) => {
    // Navigate to Custom Fields → Post Types and wait for page load
    await admin.visitAdminPage('edit.php', 'post_type=acf-field-group');
    await page.waitForLoadState('networkidle');
    
    // Wait for and click "Add New" button
    const addNewButton = page.locator('a.acf-btn.acf-btn-sm:has-text("Add New")');
    await addNewButton.waitFor({ state: 'visible' });
    await addNewButton.click();
    
    // Wait for navigation and new page to load
    await page.waitForLoadState('networkidle');
    await page.waitForLoadState('domcontentloaded');
    
    // Wait for title field and fill it using a more reliable selector
    const titleInput = page.locator('input#title');
    await titleInput.waitFor({ state: 'visible', timeout: 10000 });
    await titleInput.fill(FIELD_GROUP_LABEL);
    
    // Wait for and fill field label
    const fieldLabel = page.locator('input[id^="acf_fields-field_"][id$="-label"]');
    await fieldLabel.waitFor({ state: 'visible' });
    await fieldLabel.fill(MOVIE_FIELD_LABEL);
    
    // Wait for and click submit button
    const submitButton = page.locator('button.acf-btn.acf-publish[type="submit"]');
    await submitButton.waitFor({ state: 'visible' });
    await submitButton.click();
    
    // Wait for success message
    await page.waitForSelector('.notice-success');
    // Verify field group was created by checking multiple elements
    await expect(page.locator('.notice-success')).toBeVisible();
  });
}); 