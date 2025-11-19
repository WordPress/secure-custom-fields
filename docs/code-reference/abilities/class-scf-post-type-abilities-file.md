# SCF_Post_Type_Abilities

SCF Post Type Abilities class.

* Registers and handles all post type management abilities for the
WordPress Abilities API integration. Provides programmatic access
to SCF post type operations.
* @since 6.6.0

## Properties

### `$post_type_schema`

Post type schema to reuse across ability registrations.

* @var object|null

### `$scf_identifier_schema`

SCF identifier schema to reuse across ability registrations.

* @var object|null

## Methods

### `__construct`

Constructor.

* @since 6.6.0

### `get_post_type_schema`

Get the SCF post type schema, loading it once and caching for reuse.

* @since 6.6.0
* @return array The post type schema definition.

### `get_scf_identifier_schema`

Get the SCF identifier schema, loading it once and caching for reuse.

* @since 6.6.0
* @return array The SCF identifier schema definition.

### `get_internal_fields_schema`

Get the internal fields schema (ID, _valid, local).

* @since 6.6.0
* @return array The internal fields schema.

### `get_post_type_with_internal_fields_schema`

Get the post type schema extended with internal fields for GET/LIST/CREATE/UPDATE/IMPORT/DUPLICATE operations.

* @since 6.6.0
* @return array The extended post type schema with internal fields.

### `register_categories`

Register SCF ability categories.

* @since 6.6.0

### `register_abilities`

Register all post type abilities.

* @since 6.6.0

### `register_list_post_types_ability`

Register the list post types ability.

* @since 6.6.0

### `register_get_post_type_ability`

Register the get post type ability.

* @since 6.6.0

### `register_create_post_type_ability`

Register the create post type ability.

* @since 6.6.0

### `register_update_post_type_ability`

Register the update post type ability.

* @since 6.6.0

### `register_delete_post_type_ability`

Register the delete post type ability.

* @since 6.6.0

### `register_duplicate_post_type_ability`

Register the duplicate post type ability.

* @since 6.6.0

### `register_export_post_type_ability`

Register the export post type ability.

* @since 6.6.0

### `register_import_post_type_ability`

Register the import post type ability.

* @since 6.6.0

### `list_post_types_callback`

Callback for the list post types ability.

* @since 6.6.0
* @param array $input The input parameters.
* @return array The response data.

### `get_post_type_callback`

Callback for the get post type ability.

* @since 6.6.0
* @param array $input The input parameters.
* @return array The response data.

### `create_post_type_callback`

Callback for the create post type ability.

* @since 6.6.0
* @param array $input The input parameters.
* @return array The response data.

### `update_post_type_callback`

Callback for the update post type ability.

* @since 6.6.0
* @param array $input The input parameters.
* @return array|WP_Error The post type data on success, WP_Error on failure.

### `delete_post_type_callback`

Callback for the delete post type ability.

* @since 6.6.0
* @param array $input The input parameters.
* @return bool|WP_Error True on success, WP_Error on failure.

### `duplicate_post_type_callback`

Callback for the duplicate post type ability.

* @since 6.6.0
* @param array $input The input parameters.
* @return array|WP_Error The duplicated post type data on success, WP_Error on failure.

### `export_post_type_callback`

Callback for the export post type ability.

* @since 6.6.0
* @param array $input The input parameters.
* @return array|WP_Error The export data on success, WP_Error on failure.

### `import_post_type_callback`

Callback for the import post type ability.

* @since 6.6.0
* @param array|object $input The input parameters.
* @return array|WP_Error The imported post type data on success, WP_Error on failure.
