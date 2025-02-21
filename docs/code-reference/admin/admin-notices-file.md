# Admin Notices Global Functions

## `acf_new_admin_notice()`

Instantiates and returns a new model.

* @date    23/12/18
* @since ACF 5.8.0
* @param   array $data Optional data to set.
* @return ACF_Admin_Notice

## `acf_render_admin_notices()`

Renders all admin notices HTML.

* @date    10/1/19
* @since ACF 5.7.10
* @return  void

## `acf_add_admin_notice()`

Creates and returns a new notice.

* @date        17/10/13
* @since ACF 5.0.0
* @param   string  $text        The admin notice text.
* @param string  $type        The type of notice (warning, error, success, info).
* @param boolean $dismissible Is this notification dismissible (default true) (since 5.11.0).
* @param boolean $persisted   Store once a notice has been dismissed per user and prevent showing it again. (since 6.1.0).
* @return ACF_Admin_Notice

---
