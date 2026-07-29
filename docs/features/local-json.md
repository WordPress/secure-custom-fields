# Local JSON

Local JSON saves field group, post type, and taxonomy definitions as JSON files whenever they are edited. These files can be kept in version control, code-reviewed, and synced back into the database on other environments.

## Save and load locations

By default, files are written to and loaded from an `acf-json` directory in the active theme. The locations are configurable:

- `acf/settings/save_json` — the directory new files are written to.
- `acf/json/save_paths` — the full list of candidate save directories.
- `acf/settings/load_json` / `acf/json/load_paths` — the directories files are loaded from.

```php
add_filter( 'acf/settings/save_json', function () {
    return get_stylesheet_directory() . '/my-json';
} );
```

## Multisite

On multisite installations, Local JSON files are only written for network super admins by default. Other requests — site administrators, WP-Cron, and WP-CLI commands run without a super admin `--user` — may only write to save path directories inside the current site's uploads directory, the only filesystem location WordPress isolates per site. The `sites` subdirectory of the uploads directory is always excluded, because on the main site it contains the other sites' files.

This prevents a site administrator from modifying field group definitions in locations shared across the network, such as a theme's `acf-json` directory. Reading (loading) Local JSON is unaffected.

To let a site write Local JSON without super admin involvement, point its save location at an existing directory inside that site's uploads directory:

```php
add_filter( 'acf/settings/save_json', function () {
    $uploads = wp_get_upload_dir();
    return $uploads['basedir'] . '/scf-json';
} );
```

Note that the uploads directory is publicly readable on most servers, and files there are typically not tracked in version control. Workflows that keep Local JSON in a shared, version-controlled location on multisite should perform field group changes as a network super admin.
