=== Secure Custom Fields ===
Contributors: wordpressdotorg
Tags: fields, custom fields, meta, scf
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 6.5.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Secure Custom Fields boosts content management with custom fields and options. It deactivates Advanced Custom Fields to prevent duplicate code errors.

== Description ==

Secure Custom Fields (SCF) extends WordPress’s capabilities, transforming it into a flexible content management tool. With SCF, managing custom data becomes straightforward and efficient.

**Easily create fields on demand.**
The SCF builder makes it easy to add fields to WordPress edit screens, whether you’re adding a new “ingredients” field to a recipe or designing complex metadata for a specialized site.

**Flexibility in placement.**
Fields can be applied throughout WordPress—posts, pages, users, taxonomy terms, media, comments, and even custom options pages—organizing your data how you want.

**Display seamlessly.**
Using SCF functions, you can display custom field data in your templates, making content integration easy for all levels of developers.

**A comprehensive content management solution.**
Beyond custom fields, SCF allows you to register new post types and taxonomies directly from the SCF interface, providing more control without needing additional plugins or custom code.

**Accessible and user-friendly design.**
The field interface aligns with WordPress’s native design, creating an experience that’s both accessible and easy for content creators to use.

Installing this plugin will deactivate plugins with matching function names/functionality, specifically Advanced Custom Fields, Advanced Custom Fields Pro, and the legacy Secure Custom Fields plugins, to avoid code errors (this is the same behavior as ACF Pro).

Read more about Secure Custom Fields at [developer.wordpress.org/secure-custom-fields](https://developer.wordpress.org/secure-custom-fields/).

= Features =
* Clear and easy-to-use setup
* Robust functions for content management
* Over 30 Field Types

== Screenshots ==

1. Add groups of custom fields.
2. Easy to add custom content while writing.
3. Need a new post type? Just add it!
4. Navigate the various field types with ease.

= Acknowledgement =

This plugin builds upon and is a fork of the previous work done by the contributors of Advanced Custom Fields. Please see the plugin's license.txt for the full license and acknowledgements.


== Changelog ==

= 6.5.7 =
*Release Date 28 Aug 2025*

*Features*

- Flexible Content layouts can now be renamed in the post editor, giving content editors better clarity when managing layouts.
- Flexible Content layouts can now be disabled, preventing them from rendering on the frontend without needing to delete their data.
- Flexible Content layouts can now be collapsed and expanded in bulk for faster content editing.
- Editing a Flexible Content layout now highlights the layout being edited, making it easier to identify.
- The Date and Date Time Picker fields can now be configured to default to the current date.
- Custom Icon Picker tabs now work correctly when used inside an ACF Block.
- Duplicating a Field Group no longer causes a fatal error when using Russian translations.
- ACF classes no longer use dynamic class properties, improving compatibility with PHP 8.2+.
- Field group metabox collapse and expand buttons are no longer misaligned in the post editor.
- HTML is now escaped from field validation errors and tooltips.
- Added a new source parameter to the /wp/v2/types REST API endpoint that allows filtering post types by their origin: core (WordPress built-in), scf (for SCF managed types), or other for the rest of CPTs.

*Security*

– Unsafe HTML in field group labels is now correctly escaped for conditionally loaded field groups, resolving a JS execution vulnerability in the classic editor.
– HTML is now escaped from field group labels when output in the ACF admin.
– Bidirectional and Conditional Logic Select2 elements no longer render HTML in field labels or post titles.
– The acf.escHtml function now uses the third party DOMPurify library to ensure all unsafe HTML is removed. A new esc_html_dompurify_config JS filter can be used to modify the default behaviour.
– Post titles are now correctly escaped whenever they are output by ACF code. Thanks to Shogo Kumamaru of LAC Co., Ltd. for the responsible disclosure.
– An admin notice is now displayed when version 3 of the Select2 library is used, as it has now been deprecated in favor of version 4.

= 6.5.6 =

Release discarded due to SVN errors.

= 6.5.5 =
*Release Date 31 Jul 2025*

*Features*

- Connect block attributes with custom fields via UI.
- Remove the word 'New' from default `add-new*` label values.

*Bug Fixes*

- Bug fix: Prevent fatal if class does not exist on Beta Features.


= 6.5.4 =
*Release Date 30 Jul 2025*

Revert from 6.5.2.


= 6.5.2 =
*Release Date 30 Jul 2025*

*Features*

- Connect block attributes with custom fields via UI.
- Remove the word 'New' from default `add-new*` label values.


= 6.5.1 =
*Release Date 2 Jul 2025*

*Bug Fixes*

- Command Palette: Use `@wordpress\icons` instead of Dashicons.


= 6.5.0 =
*Release Date 23 Jun 2025*

*Enhancements & Features*

- Added Command Palette support.
- Added editor preview to acf-field source.
- Added an endpoint to retrieve the custom fields of a post type.
- Added nav menu as field type.
- Added compatibility with Woo HPOS for order fields and subscriptions. ( Ported from ACF )
- Create new options when editing a fields value on Selector. ( Ported from ACF )
- The “Escaped HTML” warning notice is now disabled by default. ( Ported from ACF )
- Added new `acf/fields/icon_picker/{tab_name}/icons` filter ( Ported from ACF )

*Bug Fixes*

- Update initialization of the acfL10n object to ensure it's available globally.
- SCF Blocks are now forced into preview mode when editing a synced pattern. ( Ported from ACF )
- SCF no longer causes an infinite loop in bbPress when editing replies. ( Ported from ACF )
- Changing a field type no longer enables the “Allow Access to Value in Editor UI” setting. ( Ported from ACF )
- Blocks registered via acf_register_block_type() with a `parent` value of `null` no longer fail to register. ( Ported from ACF )
- Fix AJAX repeater pagination. ( Ported from ACF )
- Paginated Repeater fields no longer save duplicate values when saving to a WooCommerce Order with HPOS disabled ( Ported from ACF )

*Testing*

- Added an initial batch of e2e tests.

= 6.4.2 =
*Release Date 14 Apr 2025*

* Resolved issue with shortcode translation not parsing correctly.
* Improve validation for an URL on field admin.

= 6.4.1 =
*Release Date 7 Mar 2025*

* Forked from Advanced Custom Fields®
* Various updates to coding standards.
* Updated to rely on the WordPress.org translation packs for all strings.

= 6.3.9 =
*Release Date 22nd October 2024*

* Version update release

= 6.3.6.3 =
*Release Date 15th October 2024*

* Security - Editing a Field in the Field Group editor can no longer execute a stored XSS vulnerability. Thanks to Duc Luong Tran (janlele91) from Viettel Cyber Security for the responsible disclosure
* Security - Post Type and Taxonomy metabox callbacks no longer have access to any superglobal values, hardening the original fix from 6.3.6.2 even further
* Fix - SCF Fields now correctly validate when used in the block editor and attached to the sidebar

= 6.3.6.2 =
*Release Date 12th October 2024*

* Security - Harden fix in 6.3.6.1 to cover $_REQUEST as well.
* Fork - Change name of plugin to Secure Custom Fields.

= 6.3.6.1 =
*Release Date 7th October 2024*

* Security - SCF defined Post Type and Taxonomy metabox callbacks no longer have access to $_POST data. (Thanks to the Automattic Security Team for the disclosure)

== Upgrade Notice ==

= 6.4.2 =
Security: improves validation of an URL in an admin field.
