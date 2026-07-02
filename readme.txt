=== Secure Custom Fields ===
Contributors: wordpressdotorg
Tags: fields, custom fields, meta, scf
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 6.9.0
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
= 6.9.1 =
*Release Date 2nd July 2026*

*Security*

- Capped the number of user-contributed choices that can be persisted to checkbox, radio, and select field definitions at 1000 by default, with a new `acf/fields/max_appended_choices` filter for customization.
- The WooCommerce order fields save handler is now only registered on order edit screens.

*Fixes*

- Fixed PHP 8.5 deprecation notices when numeric post ID values contain floats that cannot be represented as integers.

= 6.9.0 =
*Release Date 23rd June 2026*

*Hardening*

- Improved input validation when duplicating field groups, post types, and taxonomies, including ownership and type checks on the target.
- Restricted persistence of custom choices on checkbox, radio, and select fields to authorized users.
- Constrained the field update ability input schema to known properties.
- Sanitized flexible content layout labels and cleaned up orphaned row meta on layout removal.
- Hardened REST type field-group metadata exposure and oEmbed source formatting.
- Normalized non-scalar block binding attribute values.
- Required authentication and nonce verification before saving fields on WooCommerce orders.
- Preserved localization wrappers in PHP exports and corrected nav menu field ID output.

= 6.8.9 =
*Release Date 15th June 2026*

*Security*

- Hardened the escaping of wp_options LIKE queries used when loading option-page meta and during taxonomy term cleanup, switching to esc_like() so option-name prefixes are always matched as literals rather than as patterns.

*Fixes*

- The URL, text, textarea, and select-style fields no longer raise PHP errors when a non-scalar value (such as an array) is submitted; such input is now treated as invalid.

= 6.8.8 =
*Release Date 11th June 2026*

*Security*

- AJAX field handlers now validate that the request nonce was created for the expected field type, so a nonce minted for one field type can no longer be replayed against another field type's AJAX handler. The gallery field was also aligned with the typed nonce scheme used by all other AJAX fields.
- `acf_decrypt()` now treats malformed payloads as a decrypt failure and returns `false` instead of emitting PHP 8 warnings.

*Enhancements*

- `acf_inline_toolbar_editing_attrs()` now accepts a `return_array` argument that returns the attributes as an escaped array suitable for use with `wp_get_attachment_image()`.

*Fixes*

- `acf_form()` with `'post_id' => 'new_post'` and a `fields` list of field names no longer fatal errors when `acf_form_head()` runs before WordPress's main query is built.
- Multiple `acf_form()` calls wrapped inside a single outer form tag with one submit button no longer silently drop field values, `post_title`, or `post_content` from the non-last forms. A new `acf/form/meta_ttl` filter controls how long per-form metadata remains valid.
- Duplicating a V3 block with identical attributes no longer displays corrupted preview content in the duplicate.
- Switching between tabs containing WYSIWYG fields no longer leaves the admin menu pinned against a shorter page, which could lock page scroll.

= 6.8.7 =
*Release Date 8th June 2026*

*Fixes*

- SCF's Abilities API integration for its internal post types no longer triggers PHP warnings, notices, or a fatal error (500) on block editor and REST API requests when another active plugin builds the WordPress abilities registry earlier in the request; registration is skipped cleanly in that case and normal abilities behavior is otherwise unchanged.

= 6.8.6 =
*Release Date 27th May 2026*

*Security*

- Hardened the oEmbed field's AJAX preview handling by restricting provider discovery for visitors and users without content-authoring capability while preserving previews from WordPress's registered oEmbed providers.
- Hardened front-end `acf_form()` submission processing so the `post_title` and `post_content` form options are respected on save, and the save pipeline only accepts values for fields the rendered form exposed. A new `acf/form/allowed_field_keys` filter is available for sites that legitimately extend a form at runtime.

= 6.8.5 =
*Release Date 19th May 2026*

*Features*

Backports 6.8.1 feature work into SCF.
= 6.8.4 =
*Release Date 30th April 2026*

*Features*

- Backports 6.8.0 and 6.8.0.1 feature work into SCF.
- AI integration: SCF now integrates with the WordPress Abilities API, allowing external consumers, including AI tools, to manage field groups, post types, and taxonomies when explicitly enabled via the `enable_acf_ai` feature flag.
- Structured data: SCF can now generate JSON-LD structured data fields when explicitly enabled via the `enable_schema` feature flag.
- WP-CLI: Added `wp scf json` and backward-compatible `wp acf json` commands for importing, exporting, syncing, and checking the status of SCF JSON files.
- Post types: SCF custom post types now support the WordPress 6.9+ Notes editor feature via a new Notes checkbox in the Supports settings.
- JSON Schemas: Added v1 schemas for supported field types and updated field group, post type, and taxonomy schemas.

*Enhancements*

- Blocks V3: The Open in Expanded Editor button text can now be customized via a new `acf.expandedEditorButtonText` block.json property.
- Blocks V3: Added an `acf/blocks/default_expanded_editor_button_text` PHP filter to customize the default Open in Expanded Editor button text.
- Blocks V3: The edit and Open in Expanded Editor buttons can now be hidden via a new `acf.expandedEditorButtons` block.json property.
- Blocks V3: Added a `blocks/expanded_editor_overlay_class` JavaScript filter for customizing the Expanded Editor modal overlay class.
- Blocks V3: The block form HTML is now preloaded alongside the preview, eliminating an extra AJAX call on mount.
- Blocks V3: Expanded Editor buttons are now hidden for V3 blocks that have no fields assigned.
- SCF inline script tags now use `wp_print_inline_script_tag()` for Content Security Policy (CSP) compliance and nonce support.

*Fixes*

- V3 blocks with WYSIWYG fields no longer enqueue TinyMCE editor assets on the frontend.
- V3 blocks with identical attributes and different InnerBlocks content no longer return cached output from the first block on the frontend.
- Flexible Content fields now properly clean up nested postmeta when a parent layout containing nested Flexible Content fields is deleted.
- The Expanded Editor Done button now stays disabled until the AJAX save completes, preventing data loss.
- Pressing Escape while the Expanded Editor is saving will no longer close the modal, preventing data loss.
- InnerBlocks content containing backslashes or dollar signs now renders correctly.
- Auto Inline Editing now only applies to SCF Blocks V3, resolving incorrect hover/focus borders appearing on V2 blocks.
- Auto Inline Editing blocks now receive block context variables in render templates.
- Auto Inline Editing now works with blocks using `renderCallback`.
- Validation errors in the V3 Expanded Editor no longer cause a dead-end state.
- Icon Picker selections in Repeater fields no longer disappear.
- Range field number input now syncs to the slider and correctly updates V3 block previews.
- Message field Name and Instructions settings are no longer shown in the field group editor.
- Image field no longer crashes in WordPress 7.0 release candidates.
- V3 blocks registered via PHP now correctly show the Open in Expanded Editor button.
- Flexible Content disabled layouts now work correctly in Blocks V3.

= 6.8.3 =
*Release Date 22th April 2026*

*Fixes*

- Fix command palette type error on wp-admin.
- Plugins requiring ACF are also validated for SCF.
- REST API calls now honor the user's `unfiltered_html` capability.
- Block Preview rendering now verifies the user can edit the target post.
- Paginated Repeater fields now verify the user can edit the target post.
- Flexible Content layout title AJAX requests now validate a security nonce.
- Clone field AJAX endpoints now enforce SCF admin permissions on field group listings.

= 6.8.2 =
*Release Date 24th March 2026*

*Fixes*

- AJAX Handlers: Prefix field-specific nonces to resolve an issue where third-party nonces could be treated as valid for AJAX calls.
- Block Preview: Verify that user has access to post specified via block context.
- Repeater Field: Verify that user has access to specified post.
- REST API: Apply KSES sanitization to field content saved by users without `unfiltered_html` capabilities.
- REST API: Respect `show_in_rest` setting for field groups in `/types` endpoint.

= 6.8.1 =
*Release Date 11th March 2026*

*Backports from 6.7.1*

- Security - User field AJAX queries now enforce field-configured role restrictions and validate search permissions.
- Security - Post Object, Relationship, and Page Link field AJAX queries now enforce field-configured restrictions for post status, post type, and taxonomy.
- Site Health - Track blocks using auto inline editing.

= 6.8.0 =
*Release Date 30 Dec 2025*

*Features*

- Abilities integration: addded field abilities for Field Groups.
- Abilities integration: added trash/untrash abilities for internal post types.
- All backports up to 6.7.0.2.
- JSON Schemas: Added several fields schemas.
- WooCommerce HPOS: Added support for custom fields on any WooCommerce Order Types.
- Added PHPUnit tests.

*Fixes*

- Hide duplicated Command Palette Commands on WP 6.9+.
- Fix field schema validation for WP Rest API.
- Fix checkbox toggle functionality.


= 6.7.0 =

= 6.7.1 =
*Release Date 10 Dec 2025*

*Features*

- JSON Schemas: Added Options Pages schema.

*Fixes*

- Fixed too-early validation of schemas causing a fatal error.
- Fix block validation on WordPress 6.2.

= 6.7.0 =
*Release Date 3 Dec 2025*

*Features*

- Tested compatibility up to WordPress 6.9.
- Abilities support. Taxonomy abilities.
- JSON schemas. Taxonomy schema.


= 6.6.0 =
*Release Date 19 Nov 2025*

*Features*

- Backported features up to 6.6.0.
- Abilities API integration. Post Type abilities.
- JSON schemas validation infrastructure.

*Fixes*

- Fixed Function in network.php
- SCF label in "More" menu.
- Get the formatted_value from the original field value.
- Blocks V3: Fix flexible content not working in sidebar - modal.
- Use specific entity prefixes for key generation when duplicating entities.


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
