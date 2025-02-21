# Global Functions

## `acf_filter_attrs()`

acf_filter_attrs

* Filters out empty attrs from the provided array.
* @date    11/6/19
* @since ACF 5.8.1
* @param   array $attrs The array of attrs.
* @return array

## `acf_esc_attrs()`

acf_esc_attrs

* Generated valid HTML from an array of attrs.
* @date    11/6/19
* @since ACF 5.8.1
* @param   array $attrs The array of attrs.
* @return string

## `acf_esc_html()`

Sanitizes text content and strips out disallowed HTML.

* This function emulates `wp_kses_post()` with a context of "acf" for extensibility.
* @since  ACF 5.9.6
* @param  string $string The string to be escaped
* @return string|false

## `_acf_kses_allowed_html()`

Private callback for the "wp_kses_allowed_html" filter used to return allowed HTML for "acf" context.

* @since   ACF 5.9.6
* @param  array  $tags    An array of allowed tags.
* @param string $context The context name.
* @return array

## `acf_hidden_input()`

acf_hidden_input

* Renders the HTML of a hidden input.
* @date    3/02/2014
* @since ACF 5.0.0
* @param   array $attrs The array of attrs.
* @return string

## `acf_get_hidden_input()`

acf_get_hidden_input

* Returns the HTML of a hidden input.
* @date    3/02/2014
* @since ACF 5.0.0
* @param   array $attrs The array of attrs.
* @return string

## `acf_text_input()`

acf_text_input

* Renders the HTML of a text input.
* @date    3/02/2014
* @since ACF 5.0.0
* @param   array $attrs The array of attrs.
* @return string

## `acf_get_text_input()`

acf_get_text_input

* Returns the HTML of a text input.
* @date    3/02/2014
* @since ACF 5.0.0
* @param   array $attrs The array of attrs.
* @return string

## `acf_file_input()`

acf_file_input

* Renders the HTML of a file input.
* @date    3/02/2014
* @since ACF 5.0.0
* @param   array $attrs The array of attrs.
* @return string

## `acf_get_file_input()`

acf_get_file_input

* Returns the HTML of a file input.
* @date    3/02/2014
* @since ACF 5.0.0
* @param   array $attrs The array of attrs.
* @return string

## `acf_textarea_input()`

acf_textarea_input

* Renders the HTML of a textarea input.
* @date    3/02/2014
* @since ACF 5.0.0
* @param   array $attrs The array of attrs.
* @return string

## `acf_get_textarea_input()`

acf_get_textarea_input

* Returns the HTML of a textarea input.
* @date    3/02/2014
* @since ACF 5.0.0
* @param   array $attrs The array of attrs.
* @return string

## `acf_checkbox_input()`

acf_checkbox_input

* Renders the HTML of a checkbox input.
* @date    3/02/2014
* @since ACF 5.0.0
* @param   array $attrs The array of attrs.
* @return string

## `acf_get_checkbox_input()`

acf_get_checkbox_input

* Returns the HTML of a checkbox input.
* @date    3/02/2014
* @since ACF 5.0.0
* @param   array $attrs The array of attrs.
* @return string

## `acf_radio_input()`

acf_radio_input

* Renders the HTML of a radio input.
* @date    3/02/2014
* @since ACF 5.0.0
* @param   array $attrs The array of attrs.
* @return string

## `acf_get_radio_input()`

acf_get_radio_input

* Returns the HTML of a radio input.
* @date    3/02/2014
* @since ACF 5.0.0
* @param   array $attrs The array of attrs.
* @return string

## `acf_select_input()`

acf_select_input

* Renders the HTML of a select input.
* @date    3/02/2014
* @since ACF 5.0.0
* @param   array $attrs The array of attrs.
* @return string

## `acf_get_select_input()`

acf_select_input

* Returns the HTML of a select input.
* @date    3/02/2014
* @since ACF 5.0.0
* @param   array $attrs The array of attrs.
* @return string

## `acf_walk_select_input()`

acf_walk_select_input

* Returns the HTML of a select input's choices.
* @date    27/6/17
* @since ACF 5.6.0
* @param   array $choices The choices to walk through.
* @param array $values  The selected choices.
* @param array $depth   The current walk depth.
* @return string

## `acf_clean_atts()`

acf_clean_atts

* See acf_filter_attrs().
* @date    3/10/17
* @since ACF 5.6.3
* @param   array $attrs The array of attrs.
* @return string

## `acf_esc_atts()`

acf_esc_atts

* See acf_esc_attrs().
* @date    27/6/17
* @since ACF 5.6.0
* @param   array $attrs The array of attrs.
* @return string

## `acf_esc_attr()`

acf_esc_attr

* @date    13/6/19
* @since ACF 5.8.1
* @deprecated 5.6.0
@see acf_esc_attrs().
* @param   array $attrs The array of attrs.
* @return string

## `acf_esc_attr_e()`

acf_esc_attr_e

* See acf_esc_attrs().
* @date    13/6/19
* @since ACF 5.8.1
* @deprecated 5.6.0
* @param   array $attrs The array of attrs.

## `acf_esc_atts_e()`

acf_esc_atts_e

* See acf_esc_attrs().
* @date    13/6/19
* @since ACF 5.8.1
* @deprecated 5.6.0
* @param   array $attrs The array of attrs.

---
