# API Term Global Functions

## `acf_get_taxonomies()`

Returns an array of taxonomy names.

* @date    7/10/13
* @since ACF 5.0.0
* @param   array $args An array of args used in the get_taxonomies() function.
* @return array An array of taxonomy names.

## `acf_get_taxonomies_for_post_type()`

acf_get_taxonomies_for_post_type

* Returns an array of taxonomies for a given post type(s)
* @date    7/9/18
* @since ACF 5.7.5
* @param   string|array $post_types The post types to compare against.
* @return array

## `acf_get_taxonomy_labels()`

Returns an array of taxonomies in the format "name => label" for use in a select field.

* @date    3/8/18
* @since ACF 5.7.2
* @param   array $taxonomies Optional. An array of specific taxonomies to return.
* @return array

## `acf_get_term_title()`

acf_get_term_title

* Returns the title for this term object.
* @date    10/9/18
* @since ACF 5.0.0
* @param   object $term The WP_Term object.
* @return string

## `acf_get_grouped_terms()`

acf_get_grouped_terms

* Returns an array of terms for the given query $args and groups by taxonomy name.
* @date    2/8/18
* @since ACF 5.7.2
* @param   array $args An array of args used in the get_terms() function.
* @return array

## `_acf_terms_clauses()`

_acf_terms_clauses

* Used in the 'terms_clauses' filter to order terms by taxonomy name.
* @date    2/8/18
* @since ACF 5.7.2
* @param   array $pieces     Terms query SQL clauses.
* @param array $taxonomies An array of taxonomies.
* @param array $args       An array of terms query arguments.
* @return array $pieces

## `acf_get_pretty_taxonomies()`

acf_get_pretty_taxonomies

* Deprecated in favor of acf_get_taxonomy_labels() function.
* @date        7/10/13
* @since ACF 5.0.0
* @deprecated 5.7.2

## `acf_get_term()`

acf_get_term

* Similar to get_term() but with some extra functionality.
* @date    19/8/18
* @since ACF 5.7.3
* @param   mixed  $term_id  The term ID or a string of "taxonomy:slug".
* @param string $taxonomy The taxonomyname.
* @return WP_Term

## `acf_encode_term()`

acf_encode_term

* Returns a "taxonomy:slug" string for a given WP_Term.
* @date    27/8/18
* @since ACF 5.7.4
* @param   WP_Term $term The term object.
* @return string

## `acf_decode_term()`

acf_decode_term

* Decodes a "taxonomy:slug" string into an array of taxonomy and slug.
* @date    27/8/18
* @since ACF 5.7.4
* @param   WP_Term $term The term object.
* @return string

## `acf_get_encoded_terms()`

acf_get_encoded_terms

* Returns an array of WP_Term objects from an array of encoded strings
* @date    9/9/18
* @since ACF 5.7.5
* @param   array $values The array of encoded strings.
* @return array

## `acf_get_choices_from_terms()`

acf_get_choices_from_terms

* Returns an array of choices from the terms provided.
* @date    8/9/18
* @since ACF 5.7.5
* @param   array  $values and array of WP_Terms objects or encoded strings.
* @param string $format The value format (term_id, slug).
* @return array

## `acf_get_choices_from_grouped_terms()`

acf_get_choices_from_grouped_terms

* Returns an array of choices from the grouped terms provided.
* @date    8/9/18
* @since ACF 5.7.5
* @param   array  $value  A grouped array of WP_Terms objects.
* @param string $format The value format (term_id, slug).
* @return array

## `acf_get_choice_from_term()`

acf_get_choice_from_term

* Returns an array containing the id and text for this item.
* @date    10/9/18
* @since ACF 5.7.6
* @param   object $item   The item object such as WP_Post or WP_Term.
* @param string $format The value format (term_id, slug)
* @return array

## `acf_get_term_post_id()`

Returns a valid post_id string for a given term and taxonomy.
No longer needed since WP introduced the termmeta table in WP 4.4.

* @date    6/2/17
* @since ACF 5.5.6
* @deprecated 5.9.2
* @param   $taxonomy (string) The taxonomy type.
* @param $term_id (int) The term ID.
* @return (string)

---
