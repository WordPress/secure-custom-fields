<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- This file contains procedural code for now.
// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed -- This file contains both procedural and object-oriented code for now.
/**
 * Secure Custom Fields
 *
 * Plugin Name:       Secure Custom Fields
 * Plugin URI:        https://developer.wordpress.org/secure-custom-fields/
 * Description:       Secure Custom Fields (SCF) offers an intuitive way for developers to enhance WordPress content management by adding extra fields and options without coding requirements.
 * Version:           6.4.2
 * Author:            WordPress.org
 * Author URI:        https://wordpress.org/
 * Text Domain:       secure-custom-fields
 * Domain Path:       /lang
 * Requires PHP:      7.4
 * Requires at least: 6.0
 *
 * @package wordpress/secure-custom-fields
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'ACF' ) ) {

	/**
	 * The main ACF class
	 */
	#[AllowDynamicProperties]
	class ACF {


		/**
		 * The plugin version number.
		 *
		 * @var string
		 */
		public $version = '6.4.2';

		/**
		 * The plugin settings array.
		 *
		 * @var array
		 */
		public $settings = array();

		/**
		 * The plugin data array.
		 *
		 * @var array
		 */
		public $data = array();

		/**
		 * Storage for class instances.
		 *
		 * @var array
		 */
		public $instances = array();

		/**
		 * A dummy constructor to ensure ACF is only setup once.
		 *
		 * @date    23/06/12
		 * @since   ACF 5.0.0
		 */
		public function __construct() {
			// Do nothing.
		}

		/**
		 * Sets up the ACF plugin.
		 *
		 * @date    28/09/13
		 * @since   ACF 5.0.0
		 */
		public function initialize() {

			// Define constants.
			$this->define( 'ACF', true );
			$this->define( 'ACF_PATH', plugin_dir_path( __FILE__ ) );
			$this->define( 'ACF_BASENAME', plugin_basename( __FILE__ ) );
			$this->define( 'ACF_VERSION', $this->version );
			$this->define( 'ACF_MAJOR_VERSION', 6 );
			$this->define( 'ACF_FIELD_API_VERSION', 5 );
			$this->define( 'ACF_UPGRADE_VERSION', '5.5.0' ); // Highest version with an upgrade routine. See upgrades.php.
			$this->define( 'ACF_PRO', true );

			// Register activation hook.
			register_activation_hook( __FILE__, array( $this, 'acf_plugin_activated' ) );

			// Define settings.
			$this->settings = array(
				'name'                    => 'Secure Custom Fields', // Will be updated in the init hook to i18n string.
				'slug'                    => dirname( ACF_BASENAME ),
				'version'                 => ACF_VERSION,
				'basename'                => ACF_BASENAME,
				'path'                    => ACF_PATH,
				'file'                    => __FILE__,
				'url'                     => plugin_dir_url( __FILE__ ),
				'show_admin'              => true,
				'show_updates'            => true,
				'enable_post_types'       => true,
				'enable_options_pages_ui' => true,
				'stripslashes'            => false,
				'local'                   => true,
				'json'                    => true,
				'save_json'               => '',
				'load_json'               => array(),
				'default_language'        => '',
				'current_language'        => '',
				'capability'              => 'manage_options',
				'uploader'                => 'wp',
				'autoload'                => false,
				'l10n'                    => true,
				'l10n_textdomain'         => '',
				'google_api_key'          => '',
				'google_api_client'       => '',
				'enqueue_google_maps'     => true,
				'enqueue_select2'         => true,
				'enqueue_datepicker'      => true,
				'enqueue_datetimepicker'  => true,
				'select2_version'         => 4,
				'row_index_offset'        => 1,
				'remove_wp_meta_box'      => true,
				'rest_api_enabled'        => true,
				'rest_api_format'         => 'light',
				'rest_api_embed_links'    => true,
				'preload_blocks'          => true,
				'enable_shortcode'        => true,
				'enable_bidirection'      => true,
				'enable_block_bindings'   => true,
				'pro'                     => true,
			);

			// Include utility functions.
			include_once ACF_PATH . 'includes/acf-utility-functions.php';

			// Include previous API functions.
			acf_include( 'includes/api/api-helpers.php' );
			acf_include( 'includes/api/api-template.php' );
			acf_include( 'includes/api/api-term.php' );

			// Include classes.
			acf_include( 'includes/class-acf-data.php' );
			acf_include( 'includes/class-acf-internal-post-type.php' );
			acf_include( 'includes/class-acf-options-page.php' );
			acf_include( 'includes/class-acf-site-health.php' );
			acf_include( 'includes/fields/class-acf-field.php' );
			acf_include( 'includes/locations/abstract-acf-legacy-location.php' );
			acf_include( 'includes/locations/abstract-acf-location.php' );

			// Include functions.
			acf_include( 'includes/acf-helper-functions.php' );
			acf_include( 'includes/acf-hook-functions.php' );
			acf_include( 'includes/acf-field-functions.php' );
			acf_include( 'includes/acf-bidirectional-functions.php' );
			acf_include( 'includes/acf-internal-post-type-functions.php' );
			acf_include( 'includes/acf-post-type-functions.php' );
			acf_include( 'includes/acf-taxonomy-functions.php' );
			acf_include( 'includes/acf-field-group-functions.php' );
			acf_include( 'includes/acf-form-functions.php' );
			acf_include( 'includes/acf-meta-functions.php' );
			acf_include( 'includes/acf-post-functions.php' );
			acf_include( 'includes/acf-user-functions.php' );
			acf_include( 'includes/acf-value-functions.php' );
			acf_include( 'includes/acf-input-functions.php' );
			acf_include( 'includes/acf-wp-functions.php' );
			acf_include( 'includes/scf-ui-options-page-functions.php' );

			// Override the shortcode default value based on the version when installed.
			$first_activated_version = acf_get_version_when_first_activated();

			// Only enable shortcode by default for versions prior to 6.3.
			if ( $first_activated_version && version_compare( $first_activated_version, '6.3', '>=' ) ) {
				$this->settings['enable_shortcode'] = false;
			}

			// Include core.
			acf_include( 'includes/fields.php' );
			acf_include( 'includes/locations.php' );
			acf_include( 'includes/assets.php' );
			acf_include( 'includes/compatibility.php' );
			acf_include( 'includes/deprecated.php' );
			acf_include( 'includes/l10n.php' );
			acf_include( 'includes/local-fields.php' );
			acf_include( 'includes/local-meta.php' );
			acf_include( 'includes/local-json.php' );
			acf_include( 'includes/loop.php' );
			acf_include( 'includes/media.php' );
			acf_include( 'includes/revisions.php' );
			acf_include( 'includes/upgrades.php' );
			acf_include( 'includes/validation.php' );
			acf_include( 'includes/rest-api.php' );
			acf_include( 'includes/blocks.php' );
			acf_include( 'includes/class-acf-options-page.php' );

			// Include field group class.
			acf_include( 'includes/post-types/class-acf-field-group.php' );

			// Include ajax.
			acf_include( 'includes/ajax/class-acf-ajax.php' );
			acf_include( 'includes/ajax/class-acf-ajax-check-screen.php' );
			acf_include( 'includes/ajax/class-acf-ajax-user-setting.php' );
			acf_include( 'includes/ajax/class-acf-ajax-upgrade.php' );
			acf_include( 'includes/ajax/class-acf-ajax-query.php' );
			acf_include( 'includes/ajax/class-acf-ajax-query-users.php' );
			acf_include( 'includes/ajax/class-acf-ajax-local-json-diff.php' );

			// Include admin.
			if ( is_admin() ) {
				acf_include( 'includes/admin/admin.php' );
				acf_include( 'includes/admin/admin-internal-post-type-list.php' );
				acf_include( 'includes/admin/admin-internal-post-type.php' );
				acf_include( 'includes/admin/admin-notices.php' );
				acf_include( 'includes/admin/admin-tools.php' );
				acf_include( 'includes/admin/admin-upgrade.php' );
				acf_include( 'includes/admin/class-acf-admin-options-page.php' );
			}

			// Include legacy.
			acf_include( 'includes/legacy/legacy-locations.php' );

			// Include PRO.
			acf_include( 'pro/acf-pro.php' );

			// Add actions.
			add_action( 'init', array( $this, 'register_post_status' ), 4 );
			add_action( 'init', array( $this, 'init' ), 5 );
			add_action( 'init', array( $this, 'register_post_types' ), 5 );

			// Add filters.
			add_filter( 'posts_where', array( $this, 'posts_where' ), 10, 2 );
		}

		/**
		 * Completes the setup process on "init" of earlier.
		 *
		 * @date    28/09/13
		 * @since   ACF 5.0.0
		 */
		public function init() {

			// Bail early if called directly from functions.php or plugin file.
			if ( ! did_action( 'plugins_loaded' ) ) {
				return;
			}

			// This function may be called directly from template functions. Bail early if already did this.
			if ( acf_did( 'init' ) ) {
				return;
			}

			// Update url setting. Allows other plugins to modify the URL (force SSL).
			acf_update_setting( 'url', plugin_dir_url( __FILE__ ) );

			// Load textdomain file.
			acf_load_textdomain();

			// Update the name setting now that we're in the init hook.
			acf_update_setting( 'name', __( 'Secure Custom Fields', 'secure-custom-fields' ) );

			// Include 3rd party compatiblity.
			acf_include( 'includes/third-party.php' );

			// Include wpml support.
			if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
				acf_include( 'includes/wpml.php' );
			}

			// Include forms.
			acf_include( 'includes/forms/form-attachment.php' );
			acf_include( 'includes/forms/form-comment.php' );
			acf_include( 'includes/forms/form-customizer.php' );
			acf_include( 'includes/forms/form-front.php' );
			acf_include( 'includes/forms/form-nav-menu.php' );
			acf_include( 'includes/forms/form-post.php' );
			acf_include( 'includes/forms/form-gutenberg.php' );
			acf_include( 'includes/forms/form-taxonomy.php' );
			acf_include( 'includes/forms/form-user.php' );
			acf_include( 'includes/forms/form-widget.php' );

			// Add post types and taxonomies.
			if ( acf_get_setting( 'enable_post_types' ) ) {
				acf_include( 'includes/post-types/class-acf-taxonomy.php' );
				acf_include( 'includes/post-types/class-acf-post-type.php' );
			}

			if ( acf_get_setting( 'enable_options_pages_ui' ) ) {
				acf_include( 'includes/post-types/class-acf-ui-options-page.php' );
			}
			// Add other ACF internal post types.
			do_action( 'acf/init_internal_post_types' );

			// Include fields.
			acf_include( 'includes/fields/class-acf-field-text.php' );
			acf_include( 'includes/fields/class-acf-field-textarea.php' );
			acf_include( 'includes/fields/class-acf-field-number.php' );
			acf_include( 'includes/fields/class-acf-field-range.php' );
			acf_include( 'includes/fields/class-acf-field-email.php' );
			acf_include( 'includes/fields/class-acf-field-url.php' );
			acf_include( 'includes/fields/class-acf-field-password.php' );
			acf_include( 'includes/fields/class-acf-field-image.php' );
			acf_include( 'includes/fields/class-acf-field-file.php' );
			acf_include( 'includes/fields/class-acf-field-wysiwyg.php' );
			acf_include( 'includes/fields/class-acf-field-oembed.php' );
			acf_include( 'includes/fields/class-acf-field-select.php' );
			acf_include( 'includes/fields/class-acf-field-checkbox.php' );
			acf_include( 'includes/fields/class-acf-field-radio.php' );
			acf_include( 'includes/fields/class-acf-field-button-group.php' );
			acf_include( 'includes/fields/class-acf-field-true_false.php' );
			acf_include( 'includes/fields/class-acf-field-link.php' );
			acf_include( 'includes/fields/class-acf-field-post_object.php' );
			acf_include( 'includes/fields/class-acf-field-page_link.php' );
			acf_include( 'includes/fields/class-acf-field-relationship.php' );
			acf_include( 'includes/fields/class-acf-field-taxonomy.php' );
			acf_include( 'includes/fields/class-acf-field-user.php' );
			acf_include( 'includes/fields/class-acf-field-google-map.php' );
			acf_include( 'includes/fields/class-acf-field-date_picker.php' );
			acf_include( 'includes/fields/class-acf-field-date_time_picker.php' );
			acf_include( 'includes/fields/class-acf-field-time_picker.php' );
			acf_include( 'includes/fields/class-acf-field-color_picker.php' );
			acf_include( 'includes/fields/class-acf-field-icon_picker.php' );
			acf_include( 'includes/fields/class-acf-field-message.php' );
			acf_include( 'includes/fields/class-acf-field-accordion.php' );
			acf_include( 'includes/fields/class-acf-field-tab.php' );
			acf_include( 'includes/fields/class-acf-field-group.php' );
			acf_include( 'includes/fields/class-acf-repeater-table.php' );
			acf_include( 'includes/fields/class-acf-field-repeater.php' );
			acf_include( 'includes/fields/class-acf-field-flexible-content.php' );
			acf_include( 'includes/fields/class-acf-field-gallery.php' );
			acf_include( 'includes/fields/class-acf-field-clone.php' );

			/**
			 * Fires after field types have been included.
			 *
			 * @date    28/09/13
			 * @since   ACF 5.0.0
			 *
			 * @param   int ACF_FIELD_API_VERSION The field API version.
			 */
			do_action( 'acf/include_field_types', ACF_FIELD_API_VERSION );

			// Include locations.
			acf_include( 'includes/locations/class-acf-location-post-type.php' );
			acf_include( 'includes/locations/class-acf-location-post-template.php' );
			acf_include( 'includes/locations/class-acf-location-post-status.php' );
			acf_include( 'includes/locations/class-acf-location-post-format.php' );
			acf_include( 'includes/locations/class-acf-location-post-category.php' );
			acf_include( 'includes/locations/class-acf-location-post-taxonomy.php' );
			acf_include( 'includes/locations/class-acf-location-post.php' );
			acf_include( 'includes/locations/class-acf-location-page-template.php' );
			acf_include( 'includes/locations/class-acf-location-page-type.php' );
			acf_include( 'includes/locations/class-acf-location-page-parent.php' );
			acf_include( 'includes/locations/class-acf-location-page.php' );
			acf_include( 'includes/locations/class-acf-location-current-user.php' );
			acf_include( 'includes/locations/class-acf-location-current-user-role.php' );
			acf_include( 'includes/locations/class-acf-location-user-form.php' );
			acf_include( 'includes/locations/class-acf-location-user-role.php' );
			acf_include( 'includes/locations/class-acf-location-taxonomy.php' );
			acf_include( 'includes/locations/class-acf-location-attachment.php' );
			acf_include( 'includes/locations/class-acf-location-comment.php' );
			acf_include( 'includes/locations/class-acf-location-widget.php' );
			acf_include( 'includes/locations/class-acf-location-nav-menu.php' );
			acf_include( 'includes/locations/class-acf-location-nav-menu-item.php' );
			acf_include( 'includes/locations/class-acf-location-block.php' );
			acf_include( 'includes/locations/class-acf-location-options-page.php' );

			/**
			 * Fires after location types have been included.
			 *
			 * @date    28/09/13
			 * @since   ACF 5.0.0
			 *
			 * @param   int ACF_FIELD_API_VERSION The field API version.
			 */
			do_action( 'acf/include_location_rules', ACF_FIELD_API_VERSION );

			/**
			 * Fires during initialization. Used to add local fields.
			 *
			 * @date    28/09/13
			 * @since   ACF 5.0.0
			 *
			 * @param   int ACF_FIELD_API_VERSION The field API version.
			 */
			do_action( 'acf/include_fields', ACF_FIELD_API_VERSION );

			/**
			 * Fires during initialization. Used to add local post types.
			 *
			 * @since ACF 6.1
			 *
			 * @param int ACF_MAJOR_VERSION The major version of ACF.
			 */
			do_action( 'acf/include_post_types', ACF_MAJOR_VERSION );

			/**
			 * Fires during initialization. Used to add local taxonomies.
			 *
			 * @since ACF 6.1
			 *
			 * @param int ACF_MAJOR_VERSION The major version of ACF.
			 */
			do_action( 'acf/include_taxonomies', ACF_MAJOR_VERSION );

			/**
			 * Fires during initialization. Used to add local option pages.
			 *
			 * @param int ACF_MAJOR_VERSION The major version of ACF.
			 */
			do_action( 'acf/include_options_pages', ACF_MAJOR_VERSION );

			// If we're on 6.5 or newer, load block bindings. This will move to an autoloader in 6.3.
			if ( version_compare( get_bloginfo( 'version' ), '6.5-beta1', '>=' ) ) {
				acf_include( 'includes/Blocks/Bindings.php' );
				new ACF\Blocks\Bindings();
			}

			/**
			 * Fires after ACF is completely "initialized".
			 *
			 * @date    28/09/13
			 * @since   ACF 5.0.0
			 *
			 * @param   int ACF_MAJOR_VERSION The major version of ACF.
			 */
			do_action( 'acf/init', ACF_MAJOR_VERSION );
		}

		/**
		 * Registers the ACF post types.
		 *
		 * @date    22/10/2015
		 * @since   ACF 5.3.2
		 */
		public function register_post_types() {
			$cap = acf_get_setting( 'capability' );

			// Register the Field Group post type.
			register_post_type(
				'acf-field-group',
				array(
					'labels'          => array(
						'name'               => __( 'Field Groups', 'secure-custom-fields' ),
						'singular_name'      => __( 'Field Group', 'secure-custom-fields' ),
						'add_new'            => __( 'Add New', 'secure-custom-fields' ),
						'add_new_item'       => __( 'Add New Field Group', 'secure-custom-fields' ),
						'edit_item'          => __( 'Edit Field Group', 'secure-custom-fields' ),
						'new_item'           => __( 'New Field Group', 'secure-custom-fields' ),
						'view_item'          => __( 'View Field Group', 'secure-custom-fields' ),
						'search_items'       => __( 'Search Field Groups', 'secure-custom-fields' ),
						'not_found'          => __( 'No Field Groups found', 'secure-custom-fields' ),
						'not_found_in_trash' => __( 'No Field Groups found in Trash', 'secure-custom-fields' ),
					),
					'public'          => false,
					'hierarchical'    => true,
					'show_ui'         => true,
					'show_in_menu'    => false,
					'_builtin'        => false,
					'capability_type' => 'post',
					'capabilities'    => array(
						'edit_post'    => $cap,
						'delete_post'  => $cap,
						'edit_posts'   => $cap,
						'delete_posts' => $cap,
					),
					'supports'        => false,
					'rewrite'         => false,
					'query_var'       => false,
				)
			);

			// Register the Field post type.
			register_post_type(
				'acf-field',
				array(
					'labels'          => array(
						'name'               => __( 'Fields', 'secure-custom-fields' ),
						'singular_name'      => __( 'Field', 'secure-custom-fields' ),
						'add_new'            => __( 'Add New', 'secure-custom-fields' ),
						'add_new_item'       => __( 'Add New Field', 'secure-custom-fields' ),
						'edit_item'          => __( 'Edit Field', 'secure-custom-fields' ),
						'new_item'           => __( 'New Field', 'secure-custom-fields' ),
						'view_item'          => __( 'View Field', 'secure-custom-fields' ),
						'search_items'       => __( 'Search Fields', 'secure-custom-fields' ),
						'not_found'          => __( 'No Fields found', 'secure-custom-fields' ),
						'not_found_in_trash' => __( 'No Fields found in Trash', 'secure-custom-fields' ),
					),
					'public'          => false,
					'hierarchical'    => true,
					'show_ui'         => false,
					'show_in_menu'    => false,
					'_builtin'        => false,
					'capability_type' => 'post',
					'capabilities'    => array(
						'edit_post'    => $cap,
						'delete_post'  => $cap,
						'edit_posts'   => $cap,
						'delete_posts' => $cap,
					),
					'supports'        => array( 'title' ),
					'rewrite'         => false,
					'query_var'       => false,
				)
			);
		}

		/**
		 * Registers the ACF post statuses.
		 *
		 * @date    22/10/2015
		 * @since   ACF 5.3.2
		 */
		public function register_post_status() {

			// Register the Inactive post status.
			register_post_status(
				'acf-disabled',
				array(
					'label'                     => _x( 'Inactive', 'post status', 'secure-custom-fields' ),
					'public'                    => true,
					'exclude_from_search'       => false,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					/* translators: counts for inactive field groups */
					'label_count'               => _n_noop( 'Inactive <span class="count">(%s)</span>', 'Inactive <span class="count">(%s)</span>', 'secure-custom-fields' ),
				)
			);
		}

		/**
		 * Filters the $where clause allowing for custom WP_Query args.
		 *
		 * @date    31/8/19
		 * @since   ACF 5.8.1
		 *
		 * @param  string   $where    The WHERE clause.
		 * @param  WP_Query $wp_query The query object.
		 * @return string
		 */
		public function posts_where( $where, $wp_query ) {
			global $wpdb;

			$field_key        = $wp_query->get( 'acf_field_key' );
			$field_name       = $wp_query->get( 'acf_field_name' );
			$group_key        = $wp_query->get( 'acf_group_key' );
			$options_page_key = $wp_query->get( 'acf_ui_options_page_key' );
			$post_type_key    = $wp_query->get( 'acf_post_type_key' );
			$taxonomy_key     = $wp_query->get( 'acf_taxonomy_key' );

			// Add custom "acf_field_key" arg.
			if ( $field_key ) {
				$where .= $wpdb->prepare( " AND {$wpdb->posts}.post_name = %s", $field_key );
			}

			// Add custom "acf_field_name" arg.
			if ( $field_name ) {
				$where .= $wpdb->prepare( " AND {$wpdb->posts}.post_excerpt = %s", $field_name );
			}

			// Add custom "acf_group_key" arg.
			if ( $group_key ) {
				$where .= $wpdb->prepare( " AND {$wpdb->posts}.post_name = %s", $group_key );
			}

			// Add custom "acf_post_type_key" arg.
			if ( $post_type_key ) {
				$where .= $wpdb->prepare( " AND {$wpdb->posts}.post_name = %s", $post_type_key );
			}

			// Add custom "acf_options_page_key" arg.
			if ( $options_page_key ) {
				$where .= $wpdb->prepare( " AND {$wpdb->posts}.post_name = %s", $options_page_key );
			}

			// Add custom "acf_taxonomy_key" arg.
			if ( $taxonomy_key ) {
				$where .= $wpdb->prepare( " AND {$wpdb->posts}.post_name = %s", $taxonomy_key );
			}

			return $where;
		}

		/**
		 * Defines a constant if doesnt already exist.
		 *
		 * @date    3/5/17
		 * @since   ACF 5.5.13
		 *
		 * @param   string $name  The constant name.
		 * @param   mixed  $value The constant value.
		 * @return  void
		 */
		public function define( $name, $value = true ) {
			if ( ! defined( $name ) ) {
				define( $name, $value );
			}
		}

		/**
		 * Returns true if a setting exists for this name.
		 *
		 * @date    2/2/18
		 * @since   ACF 5.6.5
		 *
		 * @param   string $name The setting name.
		 * @return  boolean
		 */
		public function has_setting( $name ) {
			return isset( $this->settings[ $name ] );
		}

		/**
		 * Returns a setting or null if doesn't exist.
		 *
		 * @date    28/09/13
		 * @since   ACF 5.0.0
		 *
		 * @param   string $name The setting name.
		 * @return  mixed
		 */
		public function get_setting( $name ) {
			return isset( $this->settings[ $name ] ) ? $this->settings[ $name ] : null;
		}

		/**
		 * Updates a setting for the given name and value.
		 *
		 * @date    28/09/13
		 * @since   ACF 5.0.0
		 *
		 * @param   string $name  The setting name.
		 * @param   mixed  $value The setting value.
		 * @return  true
		 */
		public function update_setting( $name, $value ) {
			$this->settings[ $name ] = $value;
			return true;
		}

		/**
		 * Returns data or null if doesn't exist.
		 *
		 * @date    28/09/13
		 * @since   ACF 5.0.0
		 *
		 * @param   string $name The data name.
		 * @return  mixed
		 */
		public function get_data( $name ) {
			return isset( $this->data[ $name ] ) ? $this->data[ $name ] : null;
		}

		/**
		 * Sets data for the given name and value.
		 *
		 * @date    28/09/13
		 * @since   ACF 5.0.0
		 *
		 * @param   string $name  The data name.
		 * @param   mixed  $value The data value.
		 * @return  void
		 */
		public function set_data( $name, $value ) {
			$this->data[ $name ] = $value;
		}

		/**
		 * Returns an instance or null if doesn't exist.
		 *
		 * @date    13/2/18
		 * @since   ACF 5.6.9
		 *
		 * @param   string $class The instance class name.
		 * @return  object
		 */
		public function get_instance( $class ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.classFound -- Opting not to rename due to PHP 8.0 named arugments.
			$name = strtolower( $class );
			return isset( $this->instances[ $name ] ) ? $this->instances[ $name ] : null;
		}

		/**
		 * Creates and stores an instance of the given class.
		 *
		 * @date    13/2/18
		 * @since   ACF 5.6.9
		 *
		 * @param   string $class The instance class name.
		 * @return  object
		 */
		public function new_instance( $class ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.classFound -- Opting not to rename due to PHP 8.0 named arugments.
			$instance                 = new $class();
			$name                     = strtolower( $class );
			$this->instances[ $name ] = $instance;
			return $instance;
		}

		/**
		 * Magic __isset method for backwards compatibility.
		 *
		 * @date    24/4/20
		 * @since   ACF 5.9.0
		 *
		 * @param   string $key Key name.
		 * @return  boolean
		 */
		public function __isset( $key ) {
			return in_array( $key, array( 'locations', 'json' ), true );
		}

		/**
		 * Magic __get method for backwards compatibility.
		 *
		 * @date    24/4/20
		 * @since   ACF 5.9.0
		 *
		 * @param   string $key Key name.
		 * @return  mixed
		 */
		public function __get( $key ) {
			switch ( $key ) {
				case 'locations':
					return acf_get_instance( 'ACF_Legacy_Locations' );
				case 'json':
					return acf_get_instance( 'ACF_Local_JSON' );
			}
			return null;
		}

		/**
		 * Plugin Activation Hook
		 *
		 * @since ACF 6.2.6
		 */
		public function acf_plugin_activated() {
			// Set the first activated version of ACF.
			if ( null === get_option( 'acf_first_activated_version', null ) ) {
				// If acf_version is set, this isn't the first activated version, so leave it unset so it's legacy.
				if ( null === get_option( 'acf_version', null ) ) {
					update_option( 'acf_first_activated_version', ACF_VERSION, true );

					do_action( 'acf/first_activated' );
				}
			}
		}
	}

	/**
	 * The main function responsible for returning the one true acf Instance to functions everywhere.
	 * Use this function like you would a global variable, except without needing to declare the global.
	 *
	 * Example: <?php $acf = acf(); ?>
	 *
	 * @date    4/09/13
	 * @since   ACF 4.3.0
	 *
	 * @return  ACF
	 */
	function acf() {
		global $acf;

		// Instantiate only once.
		if ( ! isset( $acf ) ) {
			$acf = new ACF();
			$acf->initialize();
		}
		return $acf;
	}

	// Instantiate.
	acf();
} // class_exists check

if ( ! function_exists( 'scf_deactivate_other_instances' ) ) {
	/**
	 * Checks if another version of ACF/ACF PRO is active and deactivates it.
	 * Hooked on `activated_plugin` so other plugin is deactivated when current plugin is activated.
	 */
	function scf_deactivate_other_instances() {

		$plugin_to_deactivate  = 'advanced-custom-fields/acf.php';
		$deactivated_notice_id = '1';

		// Check if the plugin to deactivate is installed.
		if ( is_plugin_active( 'advanced-custom-fields-pro/acf.php' ) ) {
			$plugin_to_deactivate  = 'advanced-custom-fields-pro/acf.php';
			$deactivated_notice_id = '2';
		} elseif ( is_plugin_active( 'advanced-custom-fields/acf.php' ) ) {
			// Check if the plugin to deactivate is 'advanced-custom-fields/acf.php' but the title is 'Secure Custom Fields'.
			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_to_deactivate );
			if ( 'Secure Custom Fields' === $plugin_data['Name'] ) {
				$deactivated_notice_id = '3';
			}
		}

		if ( is_multisite() && is_network_admin() ) {
			$active_plugins = (array) get_site_option( 'active_sitewide_plugins', array() );
			$active_plugins = array_keys( $active_plugins );
		} else {
			$active_plugins = (array) get_option( 'active_plugins', array() );
		}

		foreach ( $active_plugins as $plugin_basename ) {
			if ( $plugin_to_deactivate === $plugin_basename ) {
				set_transient( 'acf_deactivated_notice_id', $deactivated_notice_id, 1 * HOUR_IN_SECONDS );
				deactivate_plugins( $plugin_basename );
				return;
			}
		}
	}

	add_action( 'activated_plugin', 'scf_deactivate_other_instances' );
}

if ( ! function_exists( 'scf_plugin_deactivated_notice' ) ) {
	/**
	 * Displays a notice when either ACF or ACF PRO is automatically deactivated.
	 */
	function scf_plugin_deactivated_notice() {
		$deactivated_notice_id = (int) get_transient( 'acf_deactivated_notice_id' );
		if ( ! in_array( $deactivated_notice_id, array( 1, 2, 3 ), true ) ) {
			return;
		}

		$message = __( "Secure Custom Fields and Advanced Custom Fields should not be active at the same time. We've automatically deactivated Advanced Custom Fields.", 'secure-custom-fields' );
		if ( 2 === $deactivated_notice_id ) {
			$message = __( "Secure Custom Fields and Advanced Custom Fields PRO should not be active at the same time. We've automatically deactivated Advanced Custom Fields PRO.", 'secure-custom-fields' );
		} elseif ( 3 === $deactivated_notice_id ) {
			$message = __( "This version of Secure Custom Fields cannot work with the legacy version of Secure Custom Fields. We've automatically deactivated your previous version of Secure Custom Fields.", 'secure-custom-fields' );
		}

		?>
		<div class="updated" style="border-left: 4px solid #ffba00;">
			<p><?php echo esc_html( $message ); ?></p>
		</div>
		<?php

		delete_transient( 'acf_deactivated_notice_id' );
	}

	add_action( 'pre_current_active_plugins', 'scf_plugin_deactivated_notice' );
}
