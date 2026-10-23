# ACF_Rest_Api

## Properties

### `$request`

* @var ACF_Rest_Request

### `$wp_request`

The WP_REST_Request object.

* @var WP_REST_Request|null

### `$per_object_schema`

Per-object schema set by maybe_apply_per_object_schema().

* @var array|null

### `$embed_links`

* @var ACF_Rest_Embed_Links

### `$core_controllers`

Cached controllers WP core does not lazy-load.

* @var array<string, WP_REST_Controller>

### `$rest_update_plan`

Fields and updates resolved during the current REST request.

* @since SCF 6.9.5
* @var array|null

## Methods

### `maybe_apply_per_object_schema`

Upgrade both OPTIONS surfaces (schema.properties.acf and
endpoints[].args.acf) from the type-level union to per-object precision
when the caller can read the target object.

* @since SCF 6.9.6
* @return void

### `build_per_object_schema`

Build the per-object ACF schema for the current request's target object.

* @since SCF 6.9.6
* @param integer|string $object_id The target object ID (may be a child id for revisions/autosaves).
* @return array

### `build_schema_from_field_groups`

Build the ACF schema scaffold and populate its `properties` from the
given field groups. Shared between the type-level get_schema() and the
per-object build_per_object_schema() so both surfaces stay in lockstep.

* @since SCF 6.9.6
* @param array               $field_groups Field groups to walk.
* @param integer|string|null $object_id    Object ID passed to get_fields() so field-level filters see the target.
* @return array

### `enrich_options_endpoint_args`

Filter callback for `rest_endpoints`. Swaps the per-object properties
into the `acf` entry of each matching endpoint's args. Only runs for
OPTIONS requests on routes matching the current URL, so write
validation is left untouched.

* @since SCF 6.9.6
* @param array $endpoints Endpoints as passed by WP_REST_Server::get_routes().
* @return array

### `current_user_can_read_request_object`

Whether the current caller can read the given object. Fails closed
(returns false) when permission cannot be conclusively verified.
Callers guarantee that $this->wp_request is a WP_REST_Request.

* @since SCF 6.9.6
* @param integer|string $object_id The effective target ID (child ID if present, otherwise the URL id).
* @param integer|string $child_id  The URL child_id, if any — signals a revision/autosave context.
* @return boolean

### `resolve_rest_controller`

Resolve the WP REST controller for the current request's object type.

* @since SCF 6.9.6
* @return WP_REST_Controller|null

### `get_core_controller`

Cache and return a WP core REST controller for 'user' or 'comment'.

* @since SCF 6.9.6
* @param string $object_type One of 'user' or 'comment'.
* @return WP_REST_Controller|null

### `register_field`

Register our custom property as a REST field.

### `get_schema`

Generate the type-level schema for the current request's object type.
Gets baked into $wp_rest_additional_fields and endpoint args at
rest_api_init. Per-object precision is layered on top in
maybe_apply_per_object_schema() and enrich_options_endpoint_args().

* @return array

### `validate_rest_arg`

Validate the request args. Mostly a wrapper for `rest_validate_request_arg()`, but also
fires off a filter, so we can add some custom validation for specific fields.

* This will likely no longer be needed once WordPress implements something like `validate_callback`
and `sanitize_callback` for nested schema properties, see:
<https://core.trac.wordpress.org/ticket/49960>
* @param mixed            $value
* @param \WP_REST_Request $request
* @param string           $param
* @return boolean|WP_Error

### `is_password_protected_content_hidden`

Checks whether a post's field values must stay out of the response because the
post is password protected and the caller has neither unlocked it nor can edit it.

* @since SCF 6.9.7
* @param integer         $post_id The post ID.
* @param WP_REST_Request $request The current request.
* @return boolean

### `load_fields`

Load field values into the requested object. This method is not a part of any public API and is only public as
it is required by WordPress.

* @param array           $object          An array representation of the post, term, or user object.
* @param string          $field_name
* @param WP_REST_Request $request
* @param string          $object_sub_type Note that this isn't the same as $this->object_type. This variable is
more specific and can be a post type or taxonomy.
* @return array

### `check_bidirectional_target_permissions`

Check bidirectional target permissions before the REST callback runs.

* @since SCF 6.9.5
* @param mixed           $dispatch_result Result from an earlier dispatch filter.
* @param WP_REST_Request $request         Current REST request.
* @return mixed|WP_Error

### `prepare_field_updates`

Resolve incoming values against fields active for the REST object.

* @since SCF 6.9.5
* @param array   $data            Incoming ACF field values.
* @param integer $object_id       Object ID, or zero for a create request.
* @param string  $object_type     ACF object type.
* @param string  $object_sub_type Post type, taxonomy, or user.
* @return array Field, value, and submitted field name tuples.

### `update_fields`

Update any incoming field values for the given object. This method is not a part of any public API and is only
public as it is required by WordPress.

* @param array                   $data
* @param WP_Post|WP_Term|WP_User $object
* @param string                  $property        'acf'
* @param WP_REST_Request         $request
* @param string                  $object_sub_type This will be the post type, the taxonomy, or 'user'.
* @return boolean|WP_Error

### `make_identifier`

Make the ACF identifier string for the given object.

* @param integer $object_id
* @param string  $object_type 'user', 'term', or 'post'
* @return string

### `object_type_has_field_group`

Gets an array of the location types that a field group is configured to use.

* @param string $object_type    'user', 'term', or 'post'
* @param array  $field_group    The field group to check.
* @param array  $location_types An array of location types.
* @return boolean

### `get_field_groups_by_object_type`

Get all field groups for the provided object type.

* @param string $object_type 'user', 'term', or 'post'
* @return array An array of field groups that display for that location type.

### `get_field_groups_by_id`

Get all field groups for a given object.

* @param integer     $object_id
* @param string      $object_type     'user', 'term', or 'post'
* @param string|null $object_sub_type The post type or taxonomy. When an $object_type of 'user' is in play, this can be ignored.
* @param array       $scope           Field group keys to limit the returned set of field groups to. This is used to scope field lookups to specific groups.
* @return array An array of matching field groups.

### `get_fields`

Get all ACF fields for a given field group and allow third party filtering.

* @param array        $field_group This could technically be other possible values supported by acf_get_fields() but in this
context, we're only using the field group arrays.
* @param null|integer $object_id   The ID of the object being prepared.
* @return array
