# API Helpers Global Functions

## `acf_is_array()`

This function will return true for a non empty array

* @since   ACF 5.4.0
* @param   mixed $array The variable to test.
* @return boolean

## `acf_has_setting()`

Alias of acf()->has_setting()

* @since   ACF 5.6.5
* @param   string $name Name of the setting to check for.
* @return boolean

## `acf_raw_setting()`

acf_raw_setting

* alias of acf()->get_setting()
* @since   ACF 5.6.5
* @param   n/a
* @return n/a

## `acf_update_setting()`

acf_update_setting

* alias of acf()->update_setting()
* @since   ACF 5.0.0
* @param   $name (string)
* @param $value (mixed)
* @return n/a

## `acf_validate_setting()`

acf_validate_setting

* Returns the changed setting name if available.
* @since   ACF 5.6.5
* @param   n/a
* @return n/a

## `acf_get_setting()`

Alias of acf()->get_setting()

* @since   ACF 5.0.0
* @param   string $name  The name of the setting to test.
* @param string $value An optional default value for the setting if it doesn't exist.
* @return n/a

## `acf_get_internal_post_types()`

Return an array of ACF's internal post type names

* @since ACF 6.1
* @return array An array of ACF's internal post type names

## `acf_append_setting()`

acf_append_setting

* This function will add a value into the settings array found in the acf object
* @since   ACF 5.0.0
* @param   $name (string)
* @param $value (mixed)
* @return n/a

## `acf_get_data()`

acf_get_data

* Returns data.
* @since   ACF 5.0.0
* @param   string $name
* @return mixed

## `acf_set_data()`

acf_set_data

* Sets data.
* @since   ACF 5.0.0
* @param   string $name
* @param mixed  $value
* @return n/a

## `acf_append_data()`

Appends data to an existing key.

* @since   ACF 5.9.0
* @param string $name The data name.
* @param mixed  $data The data to append to name.

## `acf_init()`

Alias of acf()->init() - the core ACF init function.

* @since   ACF 5.0.0

## `acf_has_done()`

acf_has_done

* This function will return true if this action has already been done
* @since   ACF 5.3.2
* @param   $name (string)
* @return (boolean)

## `acf_get_external_path()`

This function will return the path to a file within an external folder

* @since   ACF 5.5.8
* @param   string $file Directory path.
* @param string $path Optional file path.
* @return string File path.

## `acf_get_external_dir()`

This function will return the url to a file within an internal ACF folder

* @since   ACF 5.5.8
* @param   string $file Directory path.
* @param string $path Optional file path.
* @return string File path.

## `acf_plugin_dir_url()`

This function will calculate the url to a plugin folder.
Different to the WP plugin_dir_url(), this function can calculate for urls outside of the plugins folder (theme include).

* @since   ACF 5.6.8
* @param   string $file A file path inside the ACF plugin to get the plugin directory path from.
* @return string The plugin directory path.

## `acf_parse_args()`

This function will merge together 2 arrays and also convert any numeric values to ints

* @since   ACF 5.0.0
* @param   array $args     The configured arguments array.
* @param array $defaults The default properties for the passed args to inherit.
* @return array $args Parsed arguments with defaults applied.

## `acf_parse_types()`

acf_parse_types

* This function will convert any numeric values to int and trim strings
* @since   ACF 5.0.0
* @param   $var (mixed)
* @return $var (mixed)

## `acf_parse_type()`

acf_parse_type

* description
* @since   ACF 5.0.9
* @param   $post_id (int)
* @return $post_id (int)

## `acf_get_view()`

This function will load in a file from the 'admin/views' folder and allow variables to be passed through

* @since   ACF 5.0.0
* @param string $view_path
* @param array  $view_args

## `acf_merge_atts()`

acf_merge_atts

* description
* @since   ACF 5.0.9
* @param   $post_id (int)
* @return $post_id (int)

## `acf_nonce_input()`

This function will create and echo a basic nonce input

* @since   ACF 5.6.0
* @param string $nonce The nonce parameter string.

## `acf_extract_var()`

This function will remove the var from the array, and return the var

* @since   ACF 5.0.0
* @param array  $extract_array an array passed as reference to be extracted.
* @param string $key           The key to extract from the array.
* @param mixed  $default_value The default value if it doesn't exist in the extract array.
* @return mixed Extracted var or default.

## `acf_extract_vars()`

This function will remove the vars from the array, and return the vars

* @since   ACF 5.0.0
* @param array $extract_array an array passed as reference to be extracted.
* @param array $keys          An array of keys to extract from the original array.
* @return array An array of extracted values.

## `acf_get_sub_array()`

acf_get_sub_array

* This function will return a sub array of data
* @since   ACF 5.3.2
* @param   $post_id (int)
* @return $post_id (int)

## `acf_get_post_types()`

Returns an array of post type names.

* @since   ACF 5.0.0
* @param array $args Optional. An array of key => value arguments to match against the post type objects. Default empty array.
* @return array A list of post type names.

## `acf_get_post_stati()`

Function acf_get_post_stati()

* Returns an array of post status names.
* @since   ACF 6.1.0
* @param   array $args Optional. An array of key => value arguments to match against the post status objects. Default empty array.
* @return array A list of post status names.

## `acf_get_pretty_post_statuses()`

Function acf_get_pretty_post_statuses()

* Returns a clean array of post status names.
* @since   ACF 6.1.0
* @param   array $post_statuses Optional. An array of post status objects. Default empty array.
* @return array An array of post status names.

## `acf_get_post_type_label()`

acf_get_post_type_label

* This function will return a pretty label for a specific post_type
* @since   ACF 5.4.0
* @param   $post_type (string)
* @return (string)

## `acf_get_post_status_label()`

Function acf_get_post_status_label()

* This function will return a pretty label for a specific post_status
* @since   ACF 6.1.0
* @param   string $post_status The post status.
* @return string The post status label.

## `acf_verify_nonce()`

acf_verify_nonce

* This function will look at the $_POST['_acf_nonce'] value and return true or false
* @since   ACF 5.0.0
* @param   $nonce (string)
* @return (boolean)

## `acf_verify_ajax()`

Returns true if the current AJAX request is valid.
It's action will also allow WPML to set the lang and avoid AJAX get_posts issues

* @since   ACF 5.2.3
* @param string $nonce  The nonce to check.
* @param string $action The action of the nonce.
* @return boolean

## `acf_get_image_sizes()`

acf_get_image_sizes

* This function will return an array of available image sizes
* @since   ACF 5.0.0
* @param   n/a
* @return (array)

## `acf_version_compare()`

acf_version_compare

* Similar to the version_compare() function but with extra functionality.
* @since   ACF 5.5.0
* @param   string $left    The left version number.
* @param string $compare The compare operator.
* @param string $right   The right version number.
* @return boolean

## `acf_get_full_version()`

acf_get_full_version

* This function will remove any '-beta1' or '-RC1' strings from a version
* @since   ACF 5.5.0
* @param   $version (string)
* @return (string)

## `acf_get_terms()`

acf_get_terms

* This function is a wrapper for the get_terms() function
* @since   ACF 5.4.0
* @param   $args (array)
* @return (array)

## `acf_get_taxonomy_terms()`

acf_get_taxonomy_terms

* This function will return an array of available taxonomy terms
* @since   ACF 5.0.0
* @param   $taxonomies (array)
* @return (array)

## `acf_decode_taxonomy_terms()`

acf_decode_taxonomy_terms

* This function decodes the $taxonomy:$term strings into a nested array
* @since   ACF 5.0.0
* @param   $terms (array)
* @return (array)

## `acf_decode_taxonomy_term()`

acf_decode_taxonomy_term

* This function will return the taxonomy and term slug for a given value
* @since   ACF 5.0.0
* @param   $string (string)
* @return (array)

## `acf_array()`

acf_array

* Casts the value into an array.
* @since   ACF 5.7.10
* @param   mixed $val The value to cast.
* @return array

## `acf_unarray()`

Returns a non-array value.

* @since   ACF 5.8.10
* @param   mixed $val The value to review.
* @return mixed

## `acf_get_array()`

acf_get_array

* This function will force a variable to become an array
* @since   ACF 5.0.0
* @param   $var (mixed)
* @return (array)

## `acf_get_numeric()`

acf_get_numeric

* This function will return numeric values
* @since   ACF 5.4.0
* @param   $value (mixed)
* @return (mixed)

## `acf_get_posts()`

acf_get_posts

* Similar to the get_posts() function but with extra functionality.
* @since   ACF 5.1.5
* @param   array $args The query args.
* @return array

## `_acf_query_remove_post_type()`

_acf_query_remove_post_type

* This function will remove the 'wp_posts.post_type' WHERE clause completely
When using 'post__in', this clause is unneccessary and slow.
* @since   ACF 5.1.5
* @param   $sql (string)
* @return $sql

## `acf_get_grouped_posts()`

acf_get_grouped_posts

* This function will return all posts grouped by post_type
This is handy for select settings
* @since   ACF 5.0.0
* @param   $args (array)
* @return (array)

## `_acf_orderby_post_type()`

The internal ACF function to add order by post types for use in `acf_get_grouped_posts`

* @param string $orderby  The current orderby value for a query.
* @param object $wp_query The WP_Query.
* @return string The potentially modified orderby string.

## `acf_get_pretty_user_roles()`

acf_get_pretty_user_roles

* description
* @since   ACF 5.3.2
* @param   $post_id (int)
* @return $post_id (int)

## `acf_get_grouped_users()`

acf_get_grouped_users

* This function will return all users grouped by role
This is handy for select settings
* @since   ACF 5.0.0
* @param   $args (array)
* @return (array)

## `acf_json_encode()`

acf_json_encode

* Returns json_encode() ready for file / database use.
* @since   ACF 5.0.0
* @param   array $json The array of data to encode.
* @return string

## `acf_str_exists()`

acf_str_exists

* This function will return true if a sub string is found
* @since   ACF 5.0.0
* @param   $needle (string)
* @param $haystack (string)
* @return (boolean)

## `acf_debug()`

A legacy function designed for developer debugging.

* @deprecated 6.2.6 Removed for security, but keeping the definition in case third party devs have it in their code.
* @since ACF 5.0.0
* @return false

## `acf_debug_start()`

A legacy function designed for developer debugging.

* @deprecated 6.2.6 Removed for security, but keeping the definition in case third party devs have it in their code.
* @since ACF 5.0.0
* @return false

## `acf_debug_end()`

A legacy function designed for developer debugging.

* @deprecated 6.2.6 Removed for security, but keeping the definition in case third party devs have it in their code.
* @since ACF 5.0.0
* @return false

## `acf_encode_choices()`

acf_encode_choices

* description
* @since   ACF 5.0.0
* @param   $post_id (int)
* @return $post_id (int)

## `acf_str_replace()`

acf_str_replace

* This function will replace an array of strings much like str_replace
The difference is the extra logic to avoid replacing a string that has alread been replaced
This is very useful for replacing date characters as they overlap with eachother
* @since   ACF 5.3.8
* @param   $post_id (int)
* @return $post_id (int)

## `acf_split_date_time()`

acf_split_date_time

* This function will split a format string into seperate date and time
* @since   ACF 5.3.8
* @param   $date_time (string)
* @return $formats (array)

## `acf_convert_date_to_php()`

acf_convert_date_to_php

* This fucntion converts a date format string from JS to PHP
* @since   ACF 5.0.0
* @param   $date (string)
* @return (string)

## `acf_convert_date_to_js()`

acf_convert_date_to_js

* This fucntion converts a date format string from PHP to JS
* @since   ACF 5.0.0
* @param   $date (string)
* @return (string)

## `acf_convert_time_to_php()`

acf_convert_time_to_php

* This fucntion converts a time format string from JS to PHP
* @since   ACF 5.0.0
* @param   $time (string)
* @return (string)

## `acf_convert_time_to_js()`

acf_convert_time_to_js

* This fucntion converts a date format string from PHP to JS
* @since   ACF 5.0.0
* @param   $time (string)
* @return (string)

## `acf_update_user_setting()`

acf_update_user_setting

* description
* @since   ACF 5.0.0
* @param   $post_id (int)
* @return $post_id (int)

## `acf_get_user_setting()`

acf_get_user_setting

* description
* @since   ACF 5.0.0
* @param   $post_id (int)
* @return $post_id (int)

## `acf_in_array()`

acf_in_array

* description
* @since   ACF 5.0.0
* @param   $post_id (int)
* @return $post_id (int)

## `acf_get_valid_post_id()`

acf_get_valid_post_id

* This function will return a valid post_id based on the current screen / parameter
* @since   ACF 5.0.0
* @param   $post_id (mixed)
* @return $post_id (mixed)

## `acf_get_post_id_info()`

acf_get_post_id_info

* This function will return the type and id for a given $post_id string
* @since   ACF 5.4.0
* @param   $post_id (mixed)
* @return $info (array)

## `acf_isset_termmeta()`

acf_isset_termmeta

* This function will return true if the termmeta table exists
<https://developer.wordpress.org/reference/functions/get_term_meta/>
* @since   ACF 5.4.0
* @param   $post_id (int)
* @return $post_id (int)

## `acf_upload_files()`

This function will walk through the $_FILES data and upload each found.

* @since   ACF 5.0.9
* @param array $ancestors An internal parameter, not required.

## `acf_upload_file()`

acf_upload_file

* This function will uploade a $_FILE
* @since   ACF 5.0.9
* @param   $uploaded_file (array) array found from $_FILE data
* @return $id (int) new attachment ID

## `acf_update_nested_array()`

acf_update_nested_array

* This function will update a nested array value. Useful for modifying the $_POST array
* @since   ACF 5.0.9
* @param   $array (array) target array to be updated
* @param $ancestors (array) array of keys to navigate through to find the child
* @param $value (mixed) The new value
* @return (boolean)

## `acf_is_screen()`

acf_is_screen

* This function will return true if all args are matched for the current screen
* @since   ACF 5.1.5
* @param   $post_id (int)
* @return $post_id (int)

## `acf_is_acf_admin_screen()`

Check if we're in an ACF admin screen

* @since  ACF 6.2.2
* @return boolean Returns true if the current screen is an ACF admin screen.

## `acf_maybe_get()`

acf_maybe_get

* This function will return a var if it exists in an array
* @since   ACF 5.1.5
* @param   $array (array) the array to look within
* @param $key (key) the array key to look for. Nested values may be found using '/'
* @param $default (mixed) the value returned if not found
* @return $post_id (int)

## `acf_get_attachment()`

Returns an array of attachment data.

* @since   ACF 5.1.5
* @param   integer|WP_Post The attachment ID or object
* @return array|false

## `acf_get_truncated()`

This function will truncate and return a string

* @since   ACF 5.0.0
* @param string  $text   The text to truncate.
* @param integer $length The number of characters to allow in the string.
* @return  string

## `acf_current_user_can_admin()`

acf_current_user_can_admin

* This function will return true if the current user can administrate the ACF field groups
* @since   ACF 5.1.5
* @param   $post_id (int)
* @return $post_id (int)

## `acf_current_user_can_edit_post()`

Wrapper function for current_user_can( 'edit_post', $post_id ).

* @since ACF 6.3.4
* @param integer $post_id The post ID to check.
* @return boolean

## `acf_get_filesize()`

acf_get_filesize

* This function will return a numeric value of bytes for a given filesize string
* @since   ACF 5.1.5
* @param   $size (mixed)
* @return (int)

## `acf_format_filesize()`

acf_format_filesize

* This function will return a formatted string containing the filesize and unit
* @since   ACF 5.1.5
* @param   $size (mixed)
* @return (int)

## `acf_get_valid_terms()`

acf_get_valid_terms

* This function will replace old terms with new split term ids
* @since   ACF 5.1.5
* @param   $terms (int|array)
* @param $taxonomy (string)
* @return $terms

## `acf_validate_attachment()`

acf_validate_attachment

* This function will validate an attachment based on a field's restrictions and return an array of errors
* @since   ACF 5.2.3
* @param   $attachment (array) attachment data. Changes based on context
* @param $field (array) field settings containing restrictions
* @param context (string)                                     $file is different when uploading / preparing
* @return $errors (array)

## `acf_translate()`

acf_translate

* This function will translate a string using the new 'l10n_textdomain' setting
Also works for arrays which is great for fields - select -> choices
* @since   ACF 5.3.2
* @param   $string (mixed) string or array containins strings to be translated
* @return $string

## `acf_maybe_add_action()`

acf_maybe_add_action

* This function will determine if the action has already run before adding / calling the function
* @since   ACF 5.3.2
* @param   $post_id (int)
* @return $post_id (int)

## `acf_is_row_collapsed()`

acf_is_row_collapsed

* This function will return true if the field's row is collapsed
* @since   ACF 5.3.2
* @param   $post_id (int)
* @return $post_id (int)

## `acf_get_attachment_image()`

Return an image tag for the provided attachment ID

* @since ACF 5.5.0
* @deprecated 6.3.2
* @param integer $attachment_id The attachment ID
* @param string  $size          The image size to use in the image tag.
* @return false

## `acf_get_post_thumbnail()`

acf_get_post_thumbnail

* This function will return a thumbail image url for a given post
* @since   ACF 5.3.8
* @param   $post (obj)
* @param $size (mixed)
* @return (string)

## `acf_get_browser()`

acf_get_browser

* Returns the name of the current browser.
* @since   ACF 5.0.0
* @return  string

## `acf_is_ajax()`

acf_is_ajax

* This function will reutrn true if performing a wp ajax call
* @since   ACF 5.3.8
* @param   n/a
* @return (boolean)

## `acf_format_date()`

Returns a date value in a formatted string.

* @since ACF 5.3.8
* @param string $value  The date value to format.
* @param string $format The format to use.
* @return string

## `acf_clear_log()`

Previously, deletes the debug.log file.

* @since      ACF 5.7.10
* @deprecated 6.2.7

## `acf_log()`

acf_log

* description
* @since   ACF 5.3.8
* @param   $post_id (int)
* @return $post_id (int)

## `acf_dev_log()`

acf_dev_log

* Used to log variables only if ACF_DEV is defined
* @since   ACF 5.7.4
* @param   mixed
* @return void

## `acf_doing()`

acf_doing

* This function will tell ACF what task it is doing
* @since   ACF 5.3.8
* @param   $event (string)
* @param context (string)
* @return n/a

## `acf_is_doing()`

acf_is_doing

* This function can be used to state what ACF is doing, or to check
* @since   ACF 5.3.8
* @param   $event (string)
* @param context (string)
* @return (boolean)

## `acf_is_plugin_active()`

acf_is_plugin_active

* This function will return true if the ACF plugin is active
* May be included within a theme or other plugin

* @since   ACF 5.4.0
* @param   $basename (int)
* @return $post_id (int)

## `acf_send_ajax_results()`

acf_send_ajax_results

* This function will print JSON data for a Select2 AJAX query
* @since   ACF 5.4.0
* @param   $response (array)
* @return n/a

## `acf_is_sequential_array()`

acf_is_sequential_array

* This function will return true if the array contains only numeric keys
* @source  <http://stackoverflow.com/questions/173400/how-to-check-if-php-array-is-associative-or-sequential>
* @since   ACF 5.4.0
* @param   $array (array)
* @return (boolean)

## `acf_is_associative_array()`

acf_is_associative_array

* This function will return true if the array contains one or more string keys
* @source  <http://stackoverflow.com/questions/173400/how-to-check-if-php-array-is-associative-or-sequential>
* @since   ACF 5.4.0
* @param   $array (array)
* @return (boolean)

## `acf_add_array_key_prefix()`

acf_add_array_key_prefix

* This function will add a prefix to all array keys
Useful to preserve numeric keys when performing array_multisort
* @since   ACF 5.4.0
* @param   $array (array)
* @param $prefix (string)
* @return (array)

## `acf_remove_array_key_prefix()`

acf_remove_array_key_prefix

* This function will remove a prefix to all array keys
Useful to preserve numeric keys when performing array_multisort
* @since   ACF 5.4.0
* @param   $array (array)
* @param $prefix (string)
* @return (array)

## `acf_connect_attachment_to_post()`

This function will connect an attacment (image etc) to the post
Used to connect attachements uploaded directly to media that have not been attaced to a post

* @since   ACF 5.8.0 Added filter to prevent connection.
* @since ACF 5.5.4
* @param   integer $attachment_id The attachment ID.
* @param integer $post_id       The post ID.
* @return boolean True if attachment was connected.

## `acf_encrypt()`

acf_encrypt

* This function will encrypt a string using PHP
<https://bhoover.com/using-php-openssl_encrypt-openssl_decrypt-encrypt-decrypt-data/>
* @since   ACF 5.5.8
* @param   $data (string)
* @return (string)

## `acf_decrypt()`

acf_decrypt

* This function will decrypt an encrypted string using PHP
<https://bhoover.com/using-php-openssl_encrypt-openssl_decrypt-encrypt-decrypt-data/>
* @since   ACF 5.5.8
* @param   $data (string)
* @return (string)

## `acf_parse_markdown()`

acf_parse_markdown

* A very basic regex-based Markdown parser function based off [slimdown](https://gist.github.com/jbroadway/2836900).
* @since   ACF 5.7.2
* @param   string $text The string to parse.
* @return string

## `acf_get_sites()`

acf_get_sites

* Returns an array of sites for a network.
* @since   ACF 5.4.0
* @return  array

## `acf_convert_rules_to_groups()`

acf_convert_rules_to_groups

* Converts an array of rules from ACF4 to an array of groups for ACF5
* @since   ACF 5.7.4
* @param   array  $rules    An array of rules.
* @param string $anyorall The anyorall setting used in ACF4. Defaults to 'any'.
* @return array

## `acf_register_ajax()`

acf_register_ajax

* Regsiters an ajax callback.
* @since   ACF 5.7.7
* @param   string  $name     The ajax action name.
* @param array   $callback The callback function or array.
* @param boolean $public   Whether to allow access to non logged in users.
* @return void

## `acf_str_camel_case()`

acf_str_camel_case

* Converts a string into camelCase.
Thanks to <https://stackoverflow.com/questions/31274782/convert-array-keys-from-underscore-case-to-camelcase-recursively>
* @since   ACF 5.8.0
* @param   string $string The string ot convert.
* @return string

## `acf_array_camel_case()`

acf_array_camel_case

* Converts all aray keys to camelCase.
* @since   ACF 5.8.0
* @param   array $array The array to convert.
* @return array

## `acf_is_block_editor()`

Returns true if the current screen is using the block editor.

* @since ACF 5.8.0
* @return boolean

## `acf_get_wp_reserved_terms()`

Return an array of the WordPress reserved terms

* @since ACF 6.1
* @return array The WordPress reserved terms list.

## `acf_is_multisite_sub_site()`

Detect if we're on a multisite subsite.

* @since ACF 6.2.4
* @return boolean true if we're in a multisite install and not on the main site

## `acf_is_multisite_main_site()`

Detect if we're on a multisite main site.

* @since ACF 6.2.4
* @return boolean true if we're in a multisite install and on the main site

---
