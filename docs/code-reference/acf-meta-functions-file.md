# Global Functions

## `acf_get_meta()`

Returns an array of "ACF only" meta for the given post_id.

* @date    9/10/18
* @since ACF 5.8.0
* @param mixed $post_id The post_id for this data.
* @return array

## `acf_get_option_meta()`

acf_get_option_meta

* Returns an array of meta for the given wp_option name prefix in the same format as get_post_meta().
* @date    9/10/18
* @since ACF 5.8.0
* @param   string $prefix The wp_option name prefix.
* @return array

## `acf_get_metadata()`

Retrieves specific metadata from the database.

* @date    16/10/2015
* @since ACF 5.2.3
* @param   integer|string $post_id The post id.
* @param string         $name    The meta name.
* @param boolean        $hidden  If the meta is hidden (starts with an underscore).
* @return  mixed

## `acf_update_metadata()`

Updates metadata in the database.

* @date    16/10/2015
* @since ACF 5.2.3
* @param   integer|string $post_id The post id.
* @param string         $name    The meta name.
* @param mixed          $value   The meta value.
* @param boolean        $hidden  If the meta is hidden (starts with an underscore).
* @return  integer|boolean Meta ID if the key didn't exist, true on successful update, false on failure.

## `acf_delete_metadata()`

Deletes metadata from the database.

* @date    16/10/2015
* @since ACF 5.2.3
* @param   integer|string $post_id The post id.
* @param string         $name    The meta name.
* @param boolean        $hidden  If the meta is hidden (starts with an underscore).
* @return  boolean

## `acf_copy_metadata()`

acf_copy_postmeta

* Copies meta from one post to another. Useful for saving and restoring revisions.
* @date    25/06/2016
* @since ACF 5.3.8
* @param   (int|string) $from_post_id The post id to copy from.
* @param (int|string) $to_post_id   The post id to paste to.
* @return void

## `acf_copy_postmeta()`

acf_copy_postmeta

* Copies meta from one post to another. Useful for saving and restoring revisions.
* @date    25/06/2016
* @since ACF 5.3.8
* @deprecated 5.7.11
* @param   integer $from_post_id The post id to copy from.
* @param integer $to_post_id   The post id to paste to.
* @return void

## `acf_get_meta_field()`

acf_get_meta_field

* Returns a field using the provided $id and $post_id parameters.
Looks for a reference to help loading the correct field via name.
* @date    21/1/19
* @since ACF 5.7.10
* @param   string       $key     The meta name (field name).
* @param (int|string) $post_id The post_id where this field's value is saved.
* @return (array|false) The field array.

## `acf_get_metaref()`

acf_get_metaref

* Retrieves reference metadata from the database.
* @date    16/10/2015
* @since ACF 5.2.3
* @param   (int|string)                                   $post_id The post id.
* @param string type The reference type (fields|groups).
* @param string                                         $name    An optional specific name
* @return mixed

## `acf_update_metaref()`

acf_update_metaref

* Updates reference metadata in the database.
* @date    16/10/2015
* @since ACF 5.2.3
* @param   (int|string)                                   $post_id    The post id.
* @param string type The reference type (fields|groups).
* @param array                                          $references An array of references.
* @return (int|bool) Meta ID if the key didn't exist, true on successful update, false on failure.

---
