<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'ACF_Admin' ) ) :

	class ACF_Admin {


		/**
		 * Constructor.
		 *
		 * @since ACF 5.0.0
		 */
		public function __construct() {
			add_action( 'admin_menu', array( $this, 'admin_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
			add_action( 'admin_body_class', array( $this, 'admin_body_class' ) );
			add_action( 'current_screen', array( $this, 'current_screen' ) );
			add_action( 'admin_notices', array( $this, 'maybe_show_escaped_html_notice' ) );
			add_action( 'admin_init', array( $this, 'dismiss_escaped_html_notice' ) );
			add_action( 'admin_init', array( $this, 'clear_escaped_html_log' ) );
			add_filter( 'parent_file', array( $this, 'ensure_menu_selection' ) );
			add_filter( 'submenu_file', array( $this, 'ensure_submenu_selection' ) );
		}

		/**
		 * Adds the ACF menu item.
		 *
		 * @date    28/09/13
		 * @since   ACF 5.0.0
		 */
		public function admin_menu() {

			// Bail early if SCF is hidden.
			if ( ! acf_get_setting( 'show_admin' ) ) {
				return;
			}

			// Vars.
			$cap         = acf_get_setting( 'capability' );
			$parent_slug = 'edit.php?post_type=acf-field-group';

			// Add menu items.
			add_menu_page( __( 'SCF', 'secure-custom-fields' ), __( 'SCF', 'secure-custom-fields' ), $cap, $parent_slug, false, 'dashicons-welcome-widgets-menus', 80 );
		}

		/**
		 * Enqueues global admin styling.
		 *
		 * @since   ACF 5.0.0
		 */
		public function admin_enqueue_scripts() {
			wp_enqueue_style( 'acf-global' );
			wp_enqueue_script( 'acf-escaped-html-notice' );

			wp_localize_script(
				'acf-escaped-html-notice',
				'acf_escaped_html_notice',
				array(
					'show_details' => __( 'Show&nbsp;details', 'secure-custom-fields' ),
					'hide_details' => __( 'Hide&nbsp;details', 'secure-custom-fields' ),
				)
			);
		}

		/**
		 * Appends custom admin body classes.
		 *
		 * @date    5/11/19
		 * @since   ACF 5.8.7
		 *
		 * @param   string $classes Space-separated list of CSS classes.
		 * @return  string
		 */
		public function admin_body_class( $classes ) {
			global $wp_version;

			// Determine body class version.
			$wp_minor_version = floatval( $wp_version );
			if ( $wp_minor_version >= 5.3 ) {
				$classes .= ' acf-admin-5-3';
			} else {
				$classes .= ' acf-admin-3-8';
			}

			// Add browser for specific CSS.
			$classes .= ' acf-browser-' . esc_attr( acf_get_browser() );

			// Return classes.
			return $classes;
		}

		/**
		 * Adds custom functionality to "ACF" admin pages.
		 *
		 * @date    7/4/20
		 * @since   ACF 5.9.0
		 *
		 * @return  void
		 */
		public function current_screen( $screen ) {
			// Determine if the current page being viewed is "ACF" related.
			if ( isset( $screen->post_type ) && in_array( $screen->post_type, acf_get_internal_post_types(), true ) ) {
				add_action( 'in_admin_header', array( $this, 'in_admin_header' ) );
				add_filter( 'admin_footer_text', array( $this, 'admin_footer_text' ) );
				add_filter( 'update_footer', array( $this, 'admin_footer_version_text' ) );
				$this->maybe_show_import_from_cptui_notice();
			}
		}

		/**
		 * Shows a notice to import post types and taxonomies from CPTUI if that plugin is active.
		 *
		 * @since ACF 6.1
		 */
		public function maybe_show_import_from_cptui_notice() {
			global $plugin_page;

			// Only show if CPTUI is active and post types are enabled.
			if ( ! acf_get_setting( 'enable_post_types' ) || ! is_plugin_active( 'custom-post-type-ui/custom-post-type-ui.php' ) ) {
				return;
			}

			// No need to show on the tools page.
			if ( 'acf-tools' === $plugin_page ) {
				return;
			}

			$text = sprintf(
				/* translators: %s - URL to ACF tools page. */
				__( 'Import Post Types and Taxonomies registered with Custom Post Type UI and manage them with SCF. <a href="%s">Get Started</a>.', 'secure-custom-fields' ),
				acf_get_admin_tools_url()
			);

			acf_add_admin_notice( $text, 'success', true, true );
		}

		/**
		 * Notifies the user that fields rendered via shortcode or the_field() have
		 * had HTML removed/altered due to unsafe HTML being escaped.
		 *
		 * @since ACF 6.2.5
		 */
		public function maybe_show_escaped_html_notice() {
			// Only show to editors and above.
			if ( ! current_user_can( 'edit_others_posts' ) ) {
				return;
			}

			// Allow opting-out of the notice.
			if ( apply_filters( 'acf/admin/prevent_escaped_html_notice', false ) ) {
				return;
			}

			if ( get_option( 'acf_escaped_html_notice_dismissed' ) ) {
				return;
			}

			$escaped = _acf_get_escaped_html_log();

			// Notice for when HTML has already been escaped.
			if ( ! empty( $escaped ) ) {
				acf_get_view( 'escaped-html-notice', array( 'acf_escaped' => $escaped ) );
			}
		}

		/**
		 * Dismisses the escaped unsafe HTML notice.
		 *
		 * @since ACF 6.2.5
		 */
		public function dismiss_escaped_html_notice() {
			if ( empty( $_GET['acf-dismiss-esc-html-notice'] ) ) {
				return;
			}

			$nonce = sanitize_text_field( wp_unslash( $_GET['acf-dismiss-esc-html-notice'] ) );

			if (
				! wp_verify_nonce( $nonce, 'acf/dismiss_escaped_html_notice' ) ||
				! current_user_can( acf_get_setting( 'capability' ) )
			) {
				return;
			}

			update_option( 'acf_escaped_html_notice_dismissed', true );

			_acf_delete_escaped_html_log();

			wp_safe_redirect( remove_query_arg( 'acf-dismiss-esc-html-notice' ) );
			exit;
		}

		/**
		 * Clear the escaped unsafe HTML log.
		 *
		 * @since ACF 6.2.5
		 */
		public function clear_escaped_html_log() {
			if ( empty( $_GET['acf-clear-esc-html-log'] ) ) {
				return;
			}

			$nonce = sanitize_text_field( wp_unslash( $_GET['acf-clear-esc-html-log'] ) );

			if (
				! wp_verify_nonce( $nonce, 'acf/clear_escaped_html_log' ) ||
				! current_user_can( acf_get_setting( 'capability' ) )
			) {
				return;
			}

			_acf_delete_escaped_html_log();

			wp_safe_redirect( remove_query_arg( 'acf-clear-esc-html-log' ) );
			exit;
		}

		/**
		 * Renders the admin navigation element.
		 *
		 * @date    27/3/20
		 * @since   ACF 5.9.0
		 *
		 * @return  void
		 */
		function in_admin_header() {
			acf_get_view( 'global/navigation' );

			$screen = get_current_screen();

			if ( isset( $screen->base ) && 'post' === $screen->base ) {
				acf_get_view( 'global/form-top' );
			}

			do_action( 'acf/in_admin_header' );
		}

		/**
		 * Modifies the admin footer text.
		 *
		 * @date    7/4/20
		 * @since   ACF 5.9.0
		 *
		 * @param   string $text The current admin footer text.
		 * @return  string
		 */
		public function admin_footer_text( $text ) {
			return '';
		}

		/**
		 * Modifies the admin footer version text.
		 *
		 * @since ACF 6.2
		 *
		 * @param   string $text The current admin footer version text.
		 * @return  string
		 */
		public function admin_footer_version_text( $text ) {
			return '';
		}

		/**
		 * Ensure the ACF parent menu is selected for add-new.php
		 *
		 * @since ACF 6.1
		 * @param string $parent_file The parent file checked against menu activation.
		 * @return string The modified parent file
		 */
		public function ensure_menu_selection( $parent_file ) {
			if ( ! is_string( $parent_file ) ) {
				return $parent_file;
			}
			if ( strpos( $parent_file, 'edit.php?post_type=acf-' ) === 0 ) {
				return 'edit.php?post_type=acf-field-group';
			}
			return $parent_file;
		}


		/**
		 * Ensure the correct ACF submenu item is selected when in post-new versions of edit pages
		 *
		 * @since ACF 6.1
		 * @param string $submenu_file The submenu filename.
		 * @return string The modified submenu filename
		 */
		public function ensure_submenu_selection( $submenu_file ) {
			if ( ! is_string( $submenu_file ) ) {
				return $submenu_file;
			}
			if ( strpos( $submenu_file, 'post-new.php?post_type=acf-' ) === 0 ) {
				return str_replace( 'post-new', 'edit', $submenu_file );
			}
			return $submenu_file;
		}
	}

	// Instantiate.
	acf_new_instance( 'ACF_Admin' );
endif; // class_exists check
