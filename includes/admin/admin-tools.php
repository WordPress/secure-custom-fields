<?php // phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'acf_admin_tools' ) ) :
	#[AllowDynamicProperties]
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

			add_action( 'admin_body_class', array( $this, 'admin_body_class' ) );

			// disable filters (default to raw data)
			acf_disable_filters();

			// include tools
			$this->include_tools();

			// check submit
			$this->check_submit();

			// load acf scripts
			acf_enqueue_scripts();
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
