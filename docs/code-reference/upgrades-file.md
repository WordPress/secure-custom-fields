# Upgrades Global Functions

## `acf_has_upgrade()`

acf_has_upgrade

* Returns true if this site has an upgrade available.
* @date    24/8/18
* @since ACF 5.7.4
* @return  boolean

## `acf_upgrade_all()`

Runs upgrade routines if this site has an upgrade available.

* @date  24/8/18
* @since ACF 5.7.4

## `acf_get_db_version()`

acf_get_db_version

* Returns the ACF DB version.
* @date    10/09/2016
* @since ACF 5.4.0
* @return  string

## `acf_update_db_version()`

Updates the ACF DB version.

* @date    10/09/2016
* @since ACF 5.4.0
* @param   string $version The new version.
* @return void

## `acf_upgrade_500()`

acf_upgrade_500

* Version 5 introduces new post types for field groups and fields.
* @date    23/8/18
* @since ACF 5.7.4
* @return  void

## `acf_upgrade_500_field_groups()`

acf_upgrade_500_field_groups

* Upgrades all ACF4 field groups to ACF5
* @date    23/8/18
* @since ACF 5.7.4
* @return  void

## `acf_upgrade_500_field_group()`

acf_upgrade_500_field_group

* Upgrades a ACF4 field group to ACF5
* @date    23/8/18
* @since ACF 5.7.4
* @param   object $ofg The old field group post object.
* @return array $nfg  The new field group array.

## `acf_upgrade_500_fields()`

acf_upgrade_500_fields

* Upgrades all ACF4 fields to ACF5 from a specific field group
* @date    23/8/18
* @since ACF 5.7.4
* @param   object $ofg The old field group post object.
* @param array  $nfg The new field group array.
* @return void

## `acf_upgrade_500_field()`

acf_upgrade_500_field

* Upgrades a ACF4 field to ACF5
* @date    23/8/18
* @since ACF 5.7.4
* @param   array $field The old field.
* @return array $field The new field.

## `acf_upgrade_550()`

acf_upgrade_550

* Version 5.5 adds support for the wp_termmeta table added in WP 4.4.
* @date    23/8/18
* @since ACF 5.7.4
* @return  void

## `acf_upgrade_550_termmeta()`

acf_upgrade_550_termmeta

* Upgrades all ACF4 termmeta saved in wp_options to the wp_termmeta table.
* @date    23/8/18
* @since ACF 5.7.4
* @return  void

## `acf_wp_upgrade_550_termmeta()`

When the database is updated to support term meta, migrate ACF term meta data across.

* @date    23/8/18
* @since ACF 5.7.4
* @param   string $wp_db_version         The new $wp_db_version.
* @param string $wp_current_db_version The old (current) $wp_db_version.
* @return void

## `acf_upgrade_550_taxonomy()`

acf_upgrade_550_taxonomy

* Upgrades all ACF4 termmeta for a specific taxonomy.
* @date    24/8/18
* @since ACF 5.7.4
* @param   string $taxonomy The taxonomy name.
* @return void

---
