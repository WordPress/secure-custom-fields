# Acf Form Functions Global Functions

## `acf_set_form_data()`

acf_set_form_data

* Sets data about the current form.
* @date    6/10/13
* @since ACF 5.0.0
* @param   string $name The store name.
* @param array  $data Array of data to start the store with.
* @return ACF_Data

## `acf_get_form_data()`

acf_get_form_data

* Gets data about the current form.
* @date    6/10/13
* @since ACF 5.0.0
* @param   string $name The store name.
* @return mixed

## `acf_form_data()`

acf_form_data

* Called within a form to set important information and render hidden inputs.
* @date    15/10/13
* @since ACF 5.0.0
* @return  void

## `acf_save_post()`

acf_save_post

* Saves the $_POST data.
* @date    15/10/13
* @since ACF 5.0.0
* @param   integer|string $post_id The post id.
* @param array          $values  An array of values to override $_POST.
* @return boolean True if save was successful.

## `_acf_do_save_post()`

_acf_do_save_post

* Private function hooked into 'acf/save_post' to actually save the $_POST data.
This allows developers to hook in before and after ACF has actually saved the data.
* @date    11/1/19
* @since ACF 5.7.10
* @param   integer|string $post_id The post id.
* @return void

---
