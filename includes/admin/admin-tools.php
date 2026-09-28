<?php // phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'acf_admin_tools' ) ) :
	/**
	 * Class AdminTools
	 *
	 * This class provides various administrative tools for managing secure custom fields.
	 */
	class acf_admin_tools { // phpcs:ignore


		/**
		 * Contains an array of admin tool instance.
		 *
		 * @var array
		 */
		public $tools = array(); // @todo This should be private, but maintaining compatibility with the original code.


		/**
		 * The active tool
		 *
		 * @var string
		 */
		public $active = ''; // @todo Check to see if this should be private, but maintaining compatibility with the original code for now.


		/**
		 * This function will setup the class functionality
		 *
		 * @date    10/10/17
		 * @since   ACF 5.6.3
		 *
		 * @return  void
		 */
		public function __construct() {

			// actions
			add_action( 'admin_menu', array( $this, 'admin_menu' ), 15 );
		}

		/**
		 * This function will store a tool class instance in the tools array.
		 *
		 * @date    10/10/17
		 * @since   ACF 5.6.3
		 *
		 * @param   string $class Class name.
		 * @return  void
		 */
		public function register_tool( $class ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.classFound

			$instance                       = new $class();
			$this->tools[ $instance->name ] = $instance;
		}


		/**
		 * This function will return a tool class or null if not found.
		 *
		 * @date    10/10/17
		 * @since   ACF 5.6.3
		 *
		 * @param   string $name Name of tool.
		 * @return  mixed (ACF_Admin_Tool|null)
		 */
		public function get_tool( $name ) {

			return isset( $this->tools[ $name ] ) ? $this->tools[ $name ] : null;
		}


		/**
		 * This function will return an array of all tool instances.
		 *
		 * @date    10/10/17
		 * @since   ACF 5.6.3
		 *
		 * @return  array
		 */
		public function get_tools() {

			return $this->tools;
		}


		/**
		 * This function will add the SCF menu item to the WP admin
		 *
		 * @type    action (admin_menu)
		 * @date    28/09/13
		 * @since   ACF 5.0.0
		 *
		 * @return  void
		 */
		public function admin_menu() {

			// bail early if no show_admin
			if ( ! acf_get_setting( 'show_admin' ) ) {
				return;
			}

			// add page
			$page = add_submenu_page( 'edit.php?post_type=acf-field-group', __( 'Tools', 'secure-custom-fields' ), __( 'Tools', 'secure-custom-fields' ), acf_get_setting( 'capability' ), 'acf-tools', array( $this, 'html' ) );

			// actions
			add_action( 'load-' . $page, array( $this, 'load' ) );
		}


		/**
		 * Loads the admin tools page.
		 *
		 * @date    10/10/17
		 * @since   ACF 5.6.3
		 *
		 * @return  void
		 */
		public function load() {

			add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );

			// disable filters (default to raw data)
			acf_disable_filters();

			// include tools
			$this->include_tools();

			// check submit
			$this->check_submit();

			// load acf scripts
			acf_enqueue_scripts();

			// load the redesigned screen when the beta feature is enabled
			if ( $this->is_tools_redesign_enabled() ) {
				$this->enqueue_tools_redesign_assets();
			}
		}

		/**
		 * Checks if the tools redesign beta feature is enabled.
		 *
		 * @since SCF 6.5.0
		 *
		 * @return boolean
		 */
		public function is_tools_redesign_enabled() {
			// triggers discovery of beta features when not yet loaded
			acf()->admin_beta_features->get_beta_features();

			$feature = acf()->admin_beta_features->get_beta_feature( 'tools_redesign' );
			return $feature ? $feature->is_enabled() : false;
		}

		/**
		 * Enqueues the scripts and data for the tools redesign prototype.
		 *
		 * @since SCF 6.5.0
		 *
		 * @return  void
		 */
		public function enqueue_tools_redesign_assets() {
			$version = acf_get_setting( 'version' );
			$suffix  = defined( 'SCF_DEVELOPMENT_MODE' ) && SCF_DEVELOPMENT_MODE ? '' : '.min';
			$asset   = acf_get_path( 'assets/build/js/scf-tools-redesign.asset.php' );
			$deps    = file_exists( $asset ) ? ( require $asset ) : array();

			wp_enqueue_script(
				'scf-tools-redesign',
				acf_get_url( 'assets/build/js/scf-tools-redesign' . $suffix . '.js' ),
				isset( $deps['dependencies'] ) ? $deps['dependencies'] : array( 'wp-element', 'wp-i18n' ),
				isset( $deps['version'] ) ? $deps['version'] : $version,
				true
			);

			wp_enqueue_style( 'wp-components' );

			wp_print_inline_script_tag(
				'window.scfToolsRedesign = ' . acf_json_encode( $this->get_tools_redesign_data() ) . ';'
			);
		}

		/**
		 * Builds the data consumed by the tools redesign prototype.
		 *
		 * @since SCF 6.5.0
		 *
		 * @return array
		 */
		private function get_tools_redesign_data() {
			return array(
				'nonces'    => array(
					'import' => wp_create_nonce( 'import' ),
					'export' => wp_create_nonce( 'export' ),
				),
				'items'     => $this->get_exportable_items(),
				'cptui'     => $this->get_cptui_data(),
				'phpExport' => $this->get_php_export_data(),
			);
		}

		/**
		 * Returns field groups, post types, taxonomies, and options pages available for export.
		 *
		 * @since SCF 6.5.0
		 *
		 * @return array
		 */
		private function get_exportable_items() {
			$groups = array(
				'acf-field-group',
				'acf-post-type',
				'acf-taxonomy',
				'acf-ui-options-page',
			);

			$items = array();
			foreach ( $groups as $internal_type ) {
				$posts = array_filter(
					acf_get_internal_post_type_posts( $internal_type ),
					'acf_internal_post_object_contains_valid_key'
				);

				foreach ( $posts as $post ) {
					$items[ $internal_type ][] = array(
						'key'   => $post['key'],
						'title' => $post['title'],
					);
				}
			}

			return $items;
		}

		/**
		 * Returns the Custom Post Type UI import options when the plugin is active.
		 *
		 * @since SCF 6.5.0
		 *
		 * @return array
		 */
		private function get_cptui_data() {
			if ( ! is_plugin_active( 'custom-post-type-ui/custom-post-type-ui.php' ) || ! acf_get_setting( 'enable_post_types' ) ) {
				return array();
			}

			$cptui_post_types = get_option( 'cptui_post_types' );
			$cptui_taxonomies = get_option( 'cptui_taxonomies' );

			if ( ! is_array( $cptui_post_types ) ) {
				$cptui_post_types = array();
			}
			if ( ! is_array( $cptui_taxonomies ) ) {
				$cptui_taxonomies = array();
			}

			if ( empty( $cptui_post_types ) && empty( $cptui_taxonomies ) ) {
				return array();
			}

			$choices = array();
			if ( ! empty( $cptui_post_types ) ) {
				$choices['post_types'] = __( 'Post Types', 'secure-custom-fields' );
			}
			if ( ! empty( $cptui_taxonomies ) ) {
				$choices['taxonomies'] = __( 'Taxonomies', 'secure-custom-fields' );
			}

			return array(
				'choices'          => $choices,
				'overwriteWarning' => $this->cptui_overwrites_existing( $cptui_post_types, $cptui_taxonomies ),
			);
		}

		/**
		 * Checks whether importing from Custom Post Type UI would overwrite existing SCF items.
		 *
		 * @since SCF 6.5.0
		 *
		 * @param array $cptui_post_types CPTUI post types.
		 * @param array $cptui_taxonomies CPTUI taxonomies.
		 * @return bool
		 */
		private function cptui_overwrites_existing( $cptui_post_types, $cptui_taxonomies ) {
			foreach ( acf_get_acf_post_types() as $post_type ) {
				if ( isset( $cptui_post_types[ $post_type['post_type'] ] ) ) {
					return true;
				}
			}

			foreach ( acf_get_acf_taxonomies() as $taxonomy ) {
				if ( isset( $cptui_taxonomies[ $taxonomy['taxonomy'] ] ) ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Returns the generated PHP code when the export tool is in its keys mode.
		 *
		 * @since SCF 6.5.0
		 *
		 * @return array
		 */
		private function get_php_export_data() {
			$tool = $this->get_tool( 'export' );
			if ( ! $tool ) {
				return array();
			}

			$keys = $tool->get_selected_keys();
			if ( ! $keys ) {
				return array();
			}

			return array(
				'code' => $tool->get_php_export_code(),
			);
		}

		/**
		 * Modifies the admin body class.
		 *
		 * @since ACF 6.0.0
		 *
		 * @param string $classes Space-separated list of CSS classes.
		 * @return string
		 */
		public function admin_body_class( $classes ) {
			$classes .= ' acf-admin-page';
			return $classes;
		}

		/**
		 * Includes various tool-related files.
		 *
		 * @date    10/10/17
		 * @since   ACF 5.6.3
		 *
		 * @return  void
		 */
		public function include_tools() {

			// include
			acf_include( 'includes/admin/tools/class-acf-admin-tool.php' );
			acf_include( 'includes/admin/tools/class-acf-admin-tool-export.php' );
			acf_include( 'includes/admin/tools/class-acf-admin-tool-import.php' );

			// action
			do_action( 'acf/include_admin_tools' );
		}


		/**
		 * Verifies the nonces and submits the value if it passes.
		 *
		 * @date    10/10/17
		 * @since   ACF 5.6.3
		 *
		 * @return  void
		 */
		public function check_submit() {

			// loop
			foreach ( $this->get_tools() as $tool ) {

				// load
				$tool->load();

				// submit
				if ( acf_verify_nonce( $tool->name ) ) {
					$tool->submit();
				}
			}
		}


		/**
		 * Admin Tools html
		 *
		 * @date    10/10/17
		 * @since   ACF 5.6.3
		 *
		 * @return  void
		 */
		public function html() {

			// vars
			$screen = get_current_screen();
			$active = acf_maybe_get_GET( 'tool' );

			// the redesigned screen renders its own tool selection without metaboxes
			if ( $this->is_tools_redesign_enabled() ) {
				acf_get_view( 'tools/tools-redesign' );
				return;
			}

			// view
			$view = array(
				'screen_id' => $screen->id,
				'active'    => $active,
			);

			// register metaboxes
			foreach ( $this->get_tools() as $tool ) {

				// check active
				if ( $active && $active !== $tool->name ) {
					continue;
				}

				// add metabox
				add_meta_box( 'acf-admin-tool-' . $tool->name, acf_esc_html( $tool->title ), array( $this, 'metabox_html' ), $screen->id, 'normal', 'default', array( 'tool' => $tool->name ) );
			}

			// view
			acf_get_view( 'tools/tools', $view );
		}


		/**
		 * Output the metabox HTML for specific tools
		 *
		 * @since ACF 5.6.3
		 *
		 * @param mixed $post    The post this metabox is being displayed on, should be an empty string always for us on a tools page.
		 * @param array $metabox An array of the metabox attributes.
		 */
		public function metabox_html( $post, $metabox ) {
			$tool       = $this->get_tool( $metabox['args']['tool'] );
			$form_attrs = array( 'method' => 'post' );

			if ( 'import' === $metabox['args']['tool'] ) {
				$form_attrs['onsubmit'] = 'acf.disableForm(event)';
			}

			printf( '<form %s>', acf_esc_attrs( $form_attrs ) );
			$tool->html();
			acf_nonce_input( $tool->name );
			echo '</form>';
		}
	}

	// initialize
	acf()->admin_tools = new acf_admin_tools();
endif; // class_exists check


/**
 * Alias of acf()->admin_tools->register_tool()
 *
 * @type    function
 * @date    31/5/17
 * @since   ACF 5.6.0
 *
 * @param   ACF_Admin_Tool $class The tool class.
 * @return  void
 */
function acf_register_admin_tool( $class ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.classFound
	acf()->admin_tools->register_tool( $class );
}


/**
 *
 * This function will return the admin URL to the tools page
 *
 * @type    function
 * @date    31/5/17
 * @since   ACF 5.6.0
 *
 * @return  string The URL to the tools page.
 */
function acf_get_admin_tools_url() {

	return admin_url( 'edit.php?post_type=acf-field-group&page=acf-tools' );
}


/**
 * This function will return the admin URL to the tools page
 *
 * @type    function
 * @date    31/5/17
 * @since   ACF 5.6.0
 *
 * @param   string $tool The tool name.
 * @return  string The URL to a particular tool's page.
 */
function acf_get_admin_tool_url( $tool = '' ) {

	return acf_get_admin_tools_url() . '&tool=' . $tool;
}
