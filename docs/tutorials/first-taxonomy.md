# Creating Your First Taxonomy

A step-by-step guide to creating a custom taxonomy using Secure Custom Fields (SCF).

**What is a taxonomy?**

A taxonomy in WordPress is a way to group content together. The most common examples are categories and tags. You can create your own custom taxonomies to organize your content in meaningful ways depending on your needs—for example, genres for movies, colors for products, or locations for rentals.

## Prerequisites

- SCF installed and activated
- Administrator access to WordPress
- Basic understanding of WordPress concepts

You can learn more about WordPress basics here:

- [Theme Basics](https://developer.wordpress.org/themes/basics/)
- [Plugin Basics](https://developer.wordpress.org/plugins/plugin-basics/)

## 1. Access the Admin Panel

To begin creating your custom taxonomy:

- Go to **Secure Custom Fields → Taxonomies** in your WordPress admin menu.
- Click the **"Add New"** button to open the creation form.

## 2. Basic Configuration

#### Plural Label \*

Name shown in admin menus and listings, e.g., `Genres`.

#### Singular Label \*

Used for individual terms. Example: `Genre`.

#### Taxonomy Key \*

A unique identifier. Use lowercase letters, underscores, or dashes only. Max 32 characters. Example: `genre`.

#### Post Types

Select the post types that will use this taxonomy. For example, Posts, Pages, or custom types like `product`, `vehicle`, etc.

#### Public

Choose **Yes** to make the taxonomy visible on your website and in the admin dashboard.

#### Hierarchical

Enable to allow parent-child terms (like categories). Disable for flat lists (like tags).

#### Advanced Configuration

Enable this to unlock advanced settings for developers or experienced users.

## 3. Advanced Settings

#### Sort Terms

Controls whether the terms are stored in the order you assign them.

#### Default Term

Creates a default term that can’t be deleted, useful for fallback categorization.

#### Description

Brief summary of what the taxonomy represents.

#### Active

Make sure this is set to **Yes** so your taxonomy is usable.

#### Labels

Customize the texts shown in the WordPress admin:

- Menu Label, Add New Item, Edit Item, View Item
- Parent Item (for hierarchical), Popular Items (for non-hierarchical)
- Messages for empty lists, instructions, tooltips, etc.

#### Visibility Options

Control where the taxonomy appears in the admin and frontend:

- Show In UI, Show In Admin Menu
- Appearance Menu Support, Quick Edit, Admin Columns

#### Meta Box

Enable and configure how the taxonomy is displayed in the post editor.
You can define custom callbacks for display and sanitization if needed.

#### Permalinks and URLs

Customize how your taxonomy URLs will be structured:

- Custom Slug, Include front prefix, Enable hierarchical URLs
- Publicly queryable, custom query variable

#### Permissions

Control who can manage, edit, assign, or delete terms.
You can set custom capabilities (e.g., `manage_categories`, `edit_posts`).

## 4. Testing

Once everything is configured:

- Click **Save** to register your taxonomy.
- Go to one of the selected post types (e.g., Posts or Products).
- Try adding terms in the new taxonomy field.
- Make sure they appear correctly in the editor and on the frontend if public.

## For Developers

If you're a developer and prefer to register custom taxonomies using code instead of the admin interface, you can refer to the official WordPress documentation:

[How to Create Custom Taxonomies with Code](https://developer.wordpress.org/plugins/taxonomies/)

This guide includes examples and explanations on using `register_taxonomy()` and other related functions.
