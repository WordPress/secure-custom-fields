/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe('Plugin Activation', () => {
  test.beforeEach(async ({ page, requestUtils }) => {
    // Login to WordPress admin
    await page.goto('/wp-admin');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'password');
    await page.click('#wp-submit');
    await requestUtils.activatePlugin(
			'secure-custom-fields'
		);
  });

  test.afterAll(async ({ requestUtils }) => {
    await requestUtils.deactivatePlugin(
			'secure-custom-fields'
		);
  });

  test('should be able to access plugin settings', async ({ page }) => {
  
    // Navigate to plugins page
    await page.goto('/wp-admin/plugins.php');
    
    // Check if our plugin is active
    const pluginRow = page.locator('tr[data-plugin="secure-custom-fields/secure-custom-fields.php"]');
    await expect(pluginRow).toBeVisible();
    
    // Check if plugin is activated
    const isActive = await pluginRow.locator('.deactivate span').isVisible();
    expect(isActive).toBeTruthy();
  });

  test('should have correct plugin name in admin', async ({ page }) => {
    // Navigate to plugins page
    await page.goto('/wp-admin/plugins.php');
    
    // Check plugin name
    const pluginName = page.locator('tr[data-plugin="secure-custom-fields/secure-custom-fields.php"] .plugin-title strong');
    await expect(pluginName).toHaveText('Secure Custom Fields');
  });
});   