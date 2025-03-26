/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const PLUGIN_SLUG = 'secure-custom-fields';
const PLUGIN_PATH = `${PLUGIN_SLUG}/${PLUGIN_SLUG}.php`;

test.describe('Plugin Activation', () => {
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

  test('should be able to access plugin settings', async ({ page }) => {
    // Navigate to plugins page
    await page.goto('/wp-admin/plugins.php');
    
    // Check if our plugin is active
    const pluginRow = page.locator(`tr[data-plugin="${PLUGIN_PATH}"]`);
    await expect(pluginRow).toBeVisible();
    
    // Check if plugin is activated
    await expect(pluginRow.locator('.deactivate a')).toBeVisible();
  });

  test('should have correct plugin name in admin', async ({ page }) => {
    // Navigate to plugins page
    await page.goto('/wp-admin/plugins.php');
    
    // Check plugin name
    const pluginName = page.locator(`tr[data-plugin="${PLUGIN_PATH}"] .plugin-title strong`);
    await expect(pluginName).toHaveText('Secure Custom Fields');
  });
});   