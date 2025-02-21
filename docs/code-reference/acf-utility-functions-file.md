# Acf Utility Functions Global Functions

## `acf_new_instance()`

acf_new_instance

* Creates a new instance of the given class and stores it in the instances data store.
* @date    9/1/19
* @since ACF 5.7.10
* @param   string $class The class name.
* @return object The instance.

## `acf_get_instance()`

Returns an instance for the given class.

* @date  9/1/19
* @since ACF 5.7.10
* @param string $class The class name.
* @return object The instance.

## `acf_register_store()`

acf_register_store

* Registers a data store.
* @date    9/1/19
* @since ACF 5.7.10
* @param   string $name The store name.
* @param array  $data Array of data to start the store with.
* @return ACF_Data

## `acf_get_store()`

acf_get_store

* Returns a data store.
* @date    9/1/19
* @since ACF 5.7.10
* @param   string $name The store name.
* @return ACF_Data

## `acf_switch_stores()`

acf_switch_stores

* Triggered when switching between sites on a multisite installation.
* @date    13/2/19
* @since ACF 5.7.11
* @param   integer                       $site_id New blog ID.
* @param int prev_blog_id Prev blog ID.
* @return void

## `acf_get_path()`

acf_get_path

* Returns the plugin path to a specified file.
* @date    28/9/13
* @since ACF 5.0.0
* @param   string $filename The specified file.
* @return string

## `acf_get_url()`

acf_get_url

* Returns the plugin url to a specified file.
This function also defines the ACF_URL constant.
* @date    12/12/17
* @since ACF 5.6.8
* @param   string $filename The specified file.
* @return string

## `acf_include()`

Includes a file within the ACF plugin.

* @date    10/3/14
* @since ACF 5.0.0
* @param   string $filename The specified file.
* @return void

---
