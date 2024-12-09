=== Secure Custom Fields (Preview) ===
Contributors: wordpressdotorg
Tags: fields, custom fields, meta, scf
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 6.3.9
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Secure Custom Fields (SCF) offers an intuitive way for developers to enhance WordPress content management by adding extra fields and options without coding requirements.

== Description ==

SCF extends WordPress’s capabilities, transforming it into a flexible content management tool. With SCF, managing custom data becomes straightforward and efficient.

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

= Features =
* Clear and easy-to-use setup
* Robust functions for content management
* Over 30 Field Types
* Millions of Users

== Screenshots ==

1. Add groups of custom fields.
2. Easy to add custom content while writing.
3. Need a new post type? Just add it!
4. Navigate the various field types with ease.


== Changelog ==
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