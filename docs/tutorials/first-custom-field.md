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

### Field Group Title

Give your field group a descriptive name, like `Movie Details` or `Product Specs`.

### Location Rules

Choose where this group should appear. For example:

-   Post type is equal to `Movie`
-   Page template is `Product Page`

This ensures your fields appear only where you need them.

**Note:** This fine-grained control allows you to define SCF fields once and reuse them across different post types, templates, or components.

### Active

Make sure the field group is set to **Active** so it appears in the editor.

**Note:** Some features require the field group to be **enabled** and **exposed via the REST API**. To future-proof your configuration, we recommend setting **Show in REST** to `true` when creating your field groups.

## 3. Adding Fields

Click **"Add Field"** to create your first field, configure:

### Minimum required settings

To create a functional custom field, you only need to define the following in the **General** tab:

- **Field Type** (e.g., Text, Image, Select): Defines the kind of input
- **Field Label**: The name shown in the editor
- **Field Name**: A unique, code-friendly identifier (no spaces; dashes and underscores allowed)

For example, you could create fields like:

- **"Movie Title"** (`movie_title`) — a text field to store the name of the movie
- **"Director"** (`director`) — another text field to store the director's name
- **"Release Year"** (`release_year`) — a number field to store the release year

> **Note:** All other settings are optional and provide extra control or visual customization.

---

### 🔧 Full settings overview by tab

### General

Basic field definition and default behavior:

- **Field Type**
- **Field Label**
- **Field Name**
- **Default Value**

### Validation

Rules to control what values are allowed:

- **Required**
- **Minimum / Maximum Values**
- **Allowed Characters / Pattern**
- **Custom Validation Message**

### Presentation

Controls how the field appears in the editor:

- **Placeholder Text**
- **Instructions**
- **Wrapper Attributes**
- **Hide Label**

### Conditional Logic

Display logic to control field visibility:

- **Enable Conditions**
- **Condition Rules**

> **Note:** Not all options appear for every field type. Each field may expose different settings depending on its nature.

---

Repeat this process for as many fields as needed.

## 4. Testing

-   Go to a post of the type you've targeted with the field group.
-   You should now see your custom fields below the content editor.
-   Fill in test data and save the post.
-   Confirm the fields save and appear as expected.

## For Developers

While Secure Custom Fields makes it easier to manage and display custom fields with a user-friendly interface, WordPress also includes native support for custom fields that can be managed programmatically using functions like `get_post_meta()` and `add_post_meta()`.

You can learn more about WordPress native custom fields here:

👉 [WordPress Native Custom Fields](https://developer.wordpress.org/plugins/metadata/custom-fields/)

To access custom field values in your theme or plugin, use SCF functions you can learn more about SCF’s developer API in their official documentation.
