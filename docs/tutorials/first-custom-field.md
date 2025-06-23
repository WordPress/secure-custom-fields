# Creating Your First Custom Field

A beginner-friendly guide to adding custom fields using Secure Custom Fields (SCF).

**What is a custom field?**

Custom fields let you add structured data to your content. With SCF, you can attach fields like text inputs, selects, image uploads, and more to your posts, pages, or custom post types.

## Prerequisites

-   SCF installed and activated
-   A post type (default or custom) where you'll attach your fields
-   Administrator access to WordPress

You can learn more about WordPress basics here:

-   [Theme Basics](https://developer.wordpress.org/themes/basics/)
-   [Plugin Basics](https://developer.wordpress.org/plugins/plugin-basics/)

## 1. Access the Admin Panel

-   Go to **Secure Custom Fields → Field Groups** in your WordPress admin menu.
-   Click **"Add New"** to create a new group of fields.

## 2. Basic Configuration

#### Field Group Title

Give your field group a descriptive name, like `Movie Details` or `Product Specs`.

#### Location Rules

Choose where this group should appear. For example:

-   Post type is equal to `Movie`
-   Page template is `Product Page`

This ensures your fields appear only where you need them.

#### Active

Make sure the field group is set to **Active** so it appears in the editor.

## 3. Adding Fields

Click **"Add Field"** to create your first field. For each field, configure:

-   **Label**: The visible name of the field (e.g., `Director`)
-   **Field Name**: A unique identifier used in code (e.g., `director`)
-   **Field Type**: Choose from text, textarea, number, image, checkbox, select, and more
-   **Instructions**: Optional helper text to guide users
-   **Required**: Whether this field must be filled in
-   **Default Value** and **Placeholder**: Optional presets

Repeat this process for as many fields as needed.

## 4. Testing

-   Go to a post of the type you've targeted with the field group.
-   You should now see your custom fields below the content editor.
-   Fill in test data and save the post.
-   Confirm the fields save and appear as expected.

## Next Steps

-   Display the custom field values on the frontend using SCF template functions
-   Use conditional logic or field groups to organize complex forms
-   Combine with taxonomies or other field groups for richer structures

## For Developers

While Secure Custom Fields makes it easier to manage and display custom fields with a user-friendly interface, WordPress also includes native support for custom fields that can be managed programmatically using functions like `get_post_meta()` and `add_post_meta()`.

You can learn more about WordPress native custom fields here:

👉 [WordPress Native Custom Fields](https://developer.wordpress.org/plugins/metadata/custom-fields/)

To access custom field values in your theme or plugin, use SCF functions you can learn more about SCF’s developer API in their official documentation.
