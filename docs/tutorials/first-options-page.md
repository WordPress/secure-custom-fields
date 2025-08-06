# Creating Your First Options Page

A step-by-step guide to building a global settings page using Secure Custom Fields (SCF).

---

## What is an options page?

An **options page** is a custom admin screen where you can store **site-wide settings** that are not tied to individual posts, pages, or taxonomies. These values are ideal for:

- Contact details (phone, email, address)
- Social media links
- Global theme settings (colors, toggles, logos)
- Custom footer messages
- Business hours

---

## How are these options related to themes?

Options pages let you **manage shared values** that appear across the theme — without editing code each time a change is needed.

### Example: Company Phone Number

Suppose you want to display the company’s phone number in both the header and footer:

1. Create an options page named `theme_options`.
2. Add a text field called `phone_number`.
3. Retrieve it in your classic theme like this:

```php
<?php
$phone_number = get_option('phone_number');
echo $phone_number;
?>
```

> This approach works with **Classic Themes** (non-block themes) where PHP templates are used for rendering.

## Prerequisites

- SCF (Secure Custom Fields) installed and activated
- Administrator access to WordPress
- Basic understanding of WordPress admin

You can learn more about WordPress basics here:

- [Theme Basics](https://developer.wordpress.org/themes/basics/)
- [Plugin Basics](https://developer.wordpress.org/plugins/plugin-basics/)

## 1. Access the Admin Panel

- Go to **Secure Custom Fields → Options Pages** in your WordPress admin sidebar.
- Click **"Add New"** to create your options page.

## 2. Basic Configuration

Set up the following fields to define the behavior and placement of your options page:

### Basic Settings Panel

- **Page Title**: The title displayed at the top of the options page (e.g., `Site Settings`).
- **Menu Slug**: A unique identifier for the page URL (e.g., `site-settings`).
- **Parent Page**: Choose `No Parent` to create a standalone page or select a parent if it should appear as a submenu item.
- **Advanced Configuration**: Enable to access additional options.

### Advanced Settings Panel

#### Visibility

- **Menu Title**: Label that appears in the WordPress admin menu.
- **Menu Icon**: Choose an icon from Dashicons or upload a custom SVG or URL.
- **Menu Position**: Controls the position of the item in the admin sidebar.
- **Redirect to Child Page**: If enabled, this page redirects to its first child page automatically.

#### Description

- **Description**: A short description to help explain the purpose of this options page.

#### Labels

- **Update Button Label**: Text shown on the submit button (e.g., `Update Settings`).
- **Updated Message**: Message displayed after saving the options.

#### Permissions

- **Capability**: Required capability to view/edit this page (default is `edit_posts`).

#### Data Storage

- **Storage Location**: Choose whether to store the data in the options table (default) or bind it to a specific post, user, or term.
- **Custom Storage**: Use a specific post ID (e.g., `123`) or context string (e.g., `user_1`).
- **Autoload Options**: Enable to automatically load these values when WordPress initializes — useful for performance.

Click **Save** to create the options page. Once saved, you can start adding custom fields as needed.

## 3. Adding Fields

Add fields just like you would for any post type or taxonomy.

### Common Fields to Add

- `company_name` — Company name (text)
- `support_email` — Support contact email (email)
- `emergency_alert_message` — Site-wide notice (textarea)
- `global_logo` — Logo image (image upload)

Each of these fields will be accessible from any page or template.

## For Developers

Options pages provide a clean way to centralize configuration. In more advanced implementations, you can:

- Register multiple options pages for different sections (e.g., Branding, API Keys)
- Use `get_option()` if working outside the SCF context
- Integrate with theme customizers or other plugin logic

Secure Custom Fields simplifies this process, but WordPress also offers a native way to register and manage options pages through the **Settings API**. This allows full control over settings registration, sanitization, and display via code.

👉 Learn more about the WordPress Settings API:
[https://developer.wordpress.org/plugins/settings/settings-api/](https://developer.wordpress.org/plugins/settings/settings-api/)
