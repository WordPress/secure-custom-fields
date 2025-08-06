# Creating Your First Post Type

A step-by-step guide to creating a custom post type using Secure Custom Fields (SCF).

**What is a custom post type?**

In WordPress, a custom post type (CPT) is a content type like posts and pages, but customized to suit your specific needs. You can use CPTs to manage products, portfolios, testimonials, events, vehicles, and more—essentially any type of structured content that needs its own menu and fields.

## Prerequisites

- SCF installed and activated
- Administrator access to WordPress
- Basic understanding of WordPress concepts

You can learn more about WordPress basics here:

- [Theme Basics](https://developer.wordpress.org/themes/basics/)
- [Plugin Basics](https://developer.wordpress.org/plugins/plugin-basics/)

## 1. Access the Admin Panel

To begin creating your custom post type:

- Go to **Secure Custom Fields → Post Types** in your WordPress admin menu.
- Click the **"Add New"** button to open the creation form.

## 2. Basic Configuration

These fields help you define how your post type will appear and behave:

### Plural Label \*

This is the name that will appear in the admin sidebar. For example, `Movies`.

### Singular Label \*

Used for individual entries. Example: `Movie`.

### Post Type Key \*

A technical identifier for WordPress to recognize this post type. Use lowercase letters and underscores only. Max 20 characters. Example: `movie`.

### Taxonomies

Choose existing taxonomies like **category** or **tags** if you want to group or filter your content. You can also select custom ones like **Brand** or **Color**.

### Public

Choose **Yes** to make your post type visible on the website and admin area. Choose **No** to keep it private.

### Hierarchical

Set to **Yes** if you want your items to be nested (like pages). Set to **No** for a flat list (like blog posts).

### Advanced Configuration

Enable this to unlock more advanced options, useful when you need more control over how your post type works.

## 3. Advanced Settings

These options let you customize deeper behaviors and labels.

### Supports

Select which features to enable when editing a post, such as:

- **Title**: Adds a title field
- **Editor**: Main content box
- **Featured Image**: Allow image upload
- **Comments**, **Author**, **Excerpt**, etc.

### Description

A short explanation of what this post type is about. Helpful for organization.

### Active

Make sure this is set to **Yes** so your post type is registered and usable.

### Labels

These are the texts that WordPress shows throughout the dashboard. For example:

- **Menu Name**: What appears in the sidebar
- **Add New Item**: Button to create a new entry
- **Edit Item**, **View Item**, **Search Items**, etc.
    You can keep the default labels or customize them to your liking.

### Visibility Options

Control where your post type appears in the admin and site:

- Show in dashboard menu
- Show in admin bar
- Show in appearance menus

### Menu Icon and Position

Choose an icon for your post type from the WordPress Dashicons set. Optionally, set its position in the sidebar.

### Permalinks and URLs

You can define how URLs will look for your post type:

- **Slug**: A custom word for your URL (e.g., `/movie/`)
- Enable archive and pagination
- Optionally include RSS feeds

### Permissions

If needed, you can assign custom capabilities like `edit_movie` or `delete_movies`. Useful for advanced role control.

### REST API Settings

Enable this if you plan to use the post type with the block editor or external tools.

- You can customize the API route, namespace, or controller class if needed.

## 4. Testing

Once everything is set up:

- Click **Save** to register your post type.
- Go to the WordPress dashboard menu where your new post type now appears.
- Click **Add New** and create a test entry.
- Visit your website and check that it appears correctly on the frontend.

## Next Steps

- Add custom fields to your post type via Secure Custom Fields
- Configure how archives and single templates are displayed in your theme
- Set up or register your own taxonomies for more organization

## For Developers

If you're a developer and prefer to register custom post types using code instead of the admin interface, you can refer to the official WordPress documentation:

[How to Create Custom Post Types with Code](https://developer.wordpress.org/plugins/post-types/)

This guide includes examples and explanations on using `register_post_type()` and other related functions.
