# Installing Secure Custom Fields

This guide walks you through installing Secure Custom Fields (SCF) on your WordPress site.

## Requirements

Before installing, ensure your site meets these requirements:

- WordPress 6.0 or later
- PHP 7.4 or later
- WordPress memory limit of 40MB or greater (64MB recommended)

## Installation Methods

### Via WordPress Admin (Recommended)

1. Log in to your WordPress admin panel
2. Navigate to Plugins → Add New
3. Search for "Secure Custom Fields"
4. Click "Install Now"
5. Click "Activate"

### Manual Installation

1. Download the latest release from WordPress.org
2. Extract the plugin files
3. Upload the plugin folder to `/wp-content/plugins/`
4. Activate through the WordPress admin interface
---

### Composer Installation

To install and manage Secure Custom Fields in your WordPress theme or plugin, it is recommended to use Composer.

This ensures that SCF is properly versioned, loaded automatically, and easy to update.

### Why integrate **Secure Custom Fields (SCF)** with Composer?

Integrating SCF using Composer offers several important advantages for the professional development of WordPress plugins and themes:

- **Centralized dependency management:**  
  Composer allows you to declare, install, and update SCF easily along with other libraries needed in your project.

- **Version control:**  
  You can lock a specific version of SCF in your `composer.json`, ensuring that all development and production environments use exactly the same version, avoiding unexpected errors.

- **Simplified deployment and automation (CI/CD):**  
  Composer installs SCF automatically during deployment processes (`composer install`), eliminating the need to manually copy files.

- **Automatic autoloading:**  
  Composer handles PHP class autoloading. Integrating SCF this way makes your code cleaner, safer, and PSR-4 compliant.

- **Reduced repository size:**  
  By installing SCF as an external dependency, you avoid having redundant copies of the plugin in your project, keeping your repository lighter and easier to maintain.

- **License compliance:**  
  Composer records the licenses of all dependencies used, which is very useful if you distribute your plugins or themes and need to meet legal or auditing requirements.

- **Facilitates project changes:**  
  By managing SCF as a dependency, any theme or plugin can change its structure or installation system without affecting its functionality, ensuring greater flexibility and compatibility in development.

---

### How to Load and Use **Secure Custom Fields (SCF)** with Composer

Add the following configuration to your `composer.json` file:

```json
{
  "repositories": [
    {
      "type": "composer",
      "url": "https://wpackagist.org"
    }
  ],
  "extra": {
    "installer-paths": {
      "vendor/{$name}/": ["type:wordpress-plugin"]
    }
  },
  "require": {
    "wpackagist-plugin/secure-custom-fields": "^6.4"
  },
  "config": {
    "allow-plugins": {
      "composer/installers": true
    }
  }
}
```
Once the configuration is set, run the following command in your terminal to install the dependencies:
```shell
composer install
```
or
```shell
composer i
```
---
### Add the Composer Autoloader
To ensure Composer dependencies are loaded correctly, add the following line in your plugin or theme:
```php
require_once plugin_dir_path(dirname(__FILE__)) . 'vendor/autoload.php';
```
###  Load Secure Custom Fields
Now you need to manually load the Secure Custom Fields plugin and define its paths. Adjust the paths according to the structure of your plugin or theme:
```php
if (! class_exists('ACF')) {
    // Define the path and URL to the Secure Custom Fields plugin.
    define('MY_SCF_PATH', plugin_dir_path(dirname(__FILE__)) . 'vendor/secure-custom-fields/');
    define('MY_SCF_URL', plugin_dir_url(dirname(__FILE__)) . 'vendor/secure-custom-fields/');

    // Include the plugin main file.
    require_once MY_SCF_PATH . 'secure-custom-fields.php';
}
```
⚠️ **Note:** Replace MY_SCF_PATH and MY_SCF_URL with constants that match your plugin/theme structure if necessary.

### Done!
You have successfully installed and integrated Secure Custom Fields via Composer. You can now use it as you would with a normal installation, but with all the benefits of Composer-based dependency management.

---

### Optional: Hide SCF Admin Menu and Updates

If you want to hide the **Secure Custom Fields (SCF)** admin menu from the WordPress dashboard and prevent the plugin's update notifications from appearing, you can use the following code:

```php
// Hide the SCF admin menu item.
add_filter( 'acf/settings/show_admin', '__return_false' );

// Hide the SCF Updates menu.
add_filter( 'acf/settings/show_updates', '__return_false', 100 );
```
#### What does this do?

- **Hide Admin Menu:**  
  The first filter disables the SCF menu in the WordPress admin area, preventing users from accessing SCF field groups or settings.

- **Hide Update Notifications:**  
  The second filter disables the SCF update notices, so users won't see update prompts for the plugin inside the admin dashboard.

#### When should you use it?

- If you are bundling SCF inside your plugin or theme and want to **control all the custom fields yourself** without allowing clients or users to modify them.
- If you want to **maintain full control** over SCF versions and updates to avoid compatibility issues caused by manual updates.

> **Note:** Hiding updates means you are responsible for manually updating SCF when necessary to keep your project secure and compatible.


## Verify Your Installation

After installation:

1. Navigate to **Secure Custom Fields** in your WordPress admin menu.
2. Verify that you can access all **Secure Custom Fields** plugin features.
3. Create a **test field group** to ensure that fields are saved and displayed correctly.
