# Nested Custom Post Types

Nested Custom Post Types is a beta feature for placing entries from one custom post type under entries from another post type. It stores the cross-type relationship separately from WordPress's native `post_parent` hierarchy and builds a URL from the parent chain.

## Enable the beta feature

1. Go to **Custom Fields → Beta Features**.
2. Enable **Nested Custom Post Types** and save the setting.
3. Go to **Custom Fields → Post Types** and edit the post type that will contain nested entries.
4. In **Basic Settings**, choose a **Nested Parent Post Type** and save the post type.

The parent type must be a different registered public post type. A post type can be configured to nest under one parent type. Keep the feature enabled while creating or editing nested relationships.

## Choose a parent

When editing an entry of the configured child post type, use the **Nesting** panel to choose a parent entry or **None**. The relationship is validated when saved. The parent must have the configured post type, and a post cannot be its own ancestor or create a cycle.

The relationship is stored in the `_scf_nested_parent` post meta key. Avoid changing this key directly. Use the helper functions or the SCF REST endpoint so the relationship is validated and rewrite rules are invalidated.

## URLs

With pretty permalinks enabled, a nested entry URL includes the parent chain, for example:

```text
/services/website-development/wordpress-development/
```

The first path segment comes from the root post type's rewrite slug, followed by the root entry and each nested entry's slug. WordPress must have pretty permalinks enabled for nested URL routing. After changing permalink settings, visit **Settings → Permalinks** and save once to rebuild the site's rewrite rules.

Nested paths are registered only when they do not exactly replace an existing rewrite rule. Paths that are ambiguous between nested entries are not registered. Avoid duplicate slugs in the same parent chain and check for conflicts with existing pages, taxonomies, and other custom routes.

## REST API

The feature provides authenticated endpoints for reading and setting a post's nested parent. Requests require the current user to be able to edit the target post:

- `GET /wp-json/scf/v1/nested-parent/{id}` returns `post_id` and `parent_id`.
- `POST /wp-json/scf/v1/nested-parent/{id}` accepts an integer `parent_id` (`0` clears the relationship) and returns the updated IDs and permalink.

Use a valid WordPress REST nonce or other authenticated REST credentials. Parent type and cycle validation are applied to updates.

## Helpers

- `scf_get_nested_parent( $post_id )` returns the parent post ID, or `0`.
- `scf_get_nested_ancestors( $post_id )` returns ancestor `WP_Post` objects, nearest parent first.
- `scf_get_nested_children( $post_id )` returns direct nested child posts.
- `scf_get_nested_parent_post_type( $post_type )` returns the configured parent post type key, or an empty string.
- `scf_set_nested_parent( $post_id, $parent_id )` sets or clears a relationship and returns `true` or `WP_Error`.

## Limitations

This beta feature covers the parent relationship, editor selector, nested URLs, REST access, and helper functions. It does not add breadcrumb output, navigation management, Elementor-specific conditions, or integrations with page builders. The parent selector currently lists up to 200 candidate parent entries.
