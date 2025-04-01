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

### Composer Installation

This guide explains how to install and integrate the **Secure Custom Fields** plugin in your WordPress theme or plugin using Composer.

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
    "wpackagist-plugin/secure-custom-fields": "6.4.1"
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

### Hide SCF Admin Menu and Updates

```php
// Hide the SCF admin menu item.
add_filter( 'acf/settings/show_admin', '__return_false' );

// Hide the SCF Updates menu.
add_filter( 'acf/settings/show_updates', '__return_false', 100 );
```

## Verification

After installation:

1. Navigate to Custom Fields in your admin menu
2. Verify you can access all plugin features
3. Create a test field group to ensure functionality
