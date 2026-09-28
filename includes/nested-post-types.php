<?php // phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed -- Class and helper functions for one feature live together.
/**
 * Nested post types.
 *
 * Allows items of one post type to nest under items of another post type
 * through the nested_parent_post_type setting. The relationship is stored in
 * post meta so WordPress core hierarchy stays untouched. This file only wires
 * behavior while the nested_post_types beta feature is enabled.
 *
 * @package    Secure Custom Fields
 * @since      SCF {NEXT_MAJOR_VERSION}
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'SCF_Nested_Post_Types' ) ) :
	/**
	 * Class SCF_Nested_Post_Types
	 *
	 * Handles the cross-post-type parent relationship, nested permalinks and
	 * request resolution for post types configured with a nested parent.
	 *
	 * @since SCF {NEXT_MAJOR_VERSION}
	 */
	class SCF_Nested_Post_Types {

		/**
		 * Post meta key holding the nested parent post ID.
		 */
		const META_KEY = '_scf_nested_parent';

		/**
		 * Query var used to resolve nested URLs.
		 */
		const QUERY_VAR = 'scf_nested_uri';

		/**
		 * Nonce action for the nesting meta box.
		 */
		const NONCE_ACTION = 'scf_nested_parent_save';

		/**
		 * Nonce field name for the nesting meta box.
		 */
		const NONCE_NAME = 'scf_nested_parent_nonce';

		/**
		 * Maximum nesting depth to guard against runaway chains.
		 *
		 * @var int
		 */
		const MAX_DEPTH = 10;

		/**
		 * Singleton instance.
		 *
		 * @var SCF_Nested_Post_Types|null
		 */
		private static $instance = null;

		/**
		 * Cache of parent post types per child post type.
		 *
		 * @var array
		 */
		private $parent_type_cache = array();

		/**
		 * Bootstraps the feature when the beta flag is enabled.
		 *
		 * @return SCF_Nested_Post_Types|null
		 */
		public static function instance() {
			if ( null === self::$instance ) {
				if ( ! scf_nested_post_types_enabled() ) {
					return null;
				}
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Resets the singleton, used by the beta feature toggle.
		 *
		 * @return void
		 */
		public static function reset_instance() {
			self::$instance = null;
		}

		/**
		 * Constructor. Registers all hooks.
		 */
		private function __construct() {
			add_filter( 'post_type_link', array( $this, 'filter_post_type_link' ), 10, 2 );
			add_filter( 'rewrite_rules_array', array( $this, 'filter_rewrite_rules_array' ) );
			add_filter( 'query_vars', array( $this, 'filter_query_vars' ) );
			add_action( 'parse_request', array( $this, 'resolve_request' ) );
			add_action( 'admin_notices', array( $this, 'nested_parent_error_notice' ) );
			add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 10, 1 );
			add_action( 'save_post', array( $this, 'save_meta_box' ) );
			add_action( 'post_updated', array( $this, 'invalidate_after_post_updated' ), 10, 3 );
			add_action( 'before_delete_post', array( $this, 'invalidate_for_post' ), 10, 1 );

			$this->register_meta();
		}

		/**
		 * Returns true when the nested post types beta feature is enabled.
		 *
		 * @return boolean
		 */
		public static function enabled() {
			return (bool) get_option( 'scf_beta_feature_nested_post_types_enabled', false );
		}

		/**
		 * Gets the allowed nested parent post type for a post type.
		 *
		 * @param string $post_type The child post type key.
		 * @return string The parent post type key, or an empty string.
		 */
		public function get_parent_post_type( $post_type ) {
			if ( isset( $this->parent_type_cache[ $post_type ] ) ) {
				return $this->parent_type_cache[ $post_type ];
			}

			$parent_type   = '';
			$acf_post_type = acf_get_post_type( $post_type );
			if ( $acf_post_type && ! empty( $acf_post_type['nested_parent_post_type'] ) ) {
				$parent_type = (string) $acf_post_type['nested_parent_post_type'];
			}

			/**
			 * Filters the post type that items of a post type may nest under.
			 *
			 * @since SCF {NEXT_MAJOR_VERSION}
			 *
			 * @param string $parent_type The allowed parent post type key, or empty string.
			 * @param string $post_type   The child post type key.
			 */
			$parent_type = apply_filters( 'scf/nested_parent_post_type', $parent_type, $post_type );

			if ( $parent_type && ! post_type_exists( $parent_type ) ) {
				return '';
			}

			$this->parent_type_cache[ $post_type ] = $parent_type;
			return $parent_type;
		}

		/**
		 * Returns all post types which can nest under another post type.
		 *
		 * @return array Array of child post type keys.
		 */
		public function get_nested_post_types() {
			$nested = array();

			foreach ( get_post_types( array( 'public' => true ), 'names' ) as $post_type ) {
				if ( $this->get_parent_post_type( $post_type ) ) {
					$nested[] = $post_type;
				}
			}

			return $nested;
		}

		/**
		 * Gets the nested parent post ID for a post.
		 *
		 * @param int $post_id The post ID.
		 * @return int The parent post ID, or 0.
		 */
		public function get_parent_id( $post_id ) {
			return (int) get_post_meta( $post_id, self::META_KEY, true );
		}

		/**
		 * Walks the nested parent chain for a post.
		 *
		 * @param int $post_id The child post ID.
		 * @return WP_Post[] Parent posts ordered from nearest ancestor to root.
		 */
		public function get_ancestor_posts( $post_id ) {
			$ancestors = array();
			$visited   = array( $post_id => true );
			$current   = $post_id;
			$depth     = 0;

			while ( $depth < self::MAX_DEPTH ) {
				$parent_id = $this->get_parent_id( $current );

				if ( ! $parent_id || isset( $visited[ $parent_id ] ) ) {
					break;
				}

				$parent = get_post( $parent_id );
				if ( ! $parent ) {
					break;
				}

				$ancestors[]           = $parent;
				$visited[ $parent_id ] = true;
				$current               = $parent_id;
				++$depth;
			}

			return $ancestors;
		}

		/**
		 * Validates a proposed nested parent relationship.
		 *
		 * @param int $post_id    The child post ID.
		 * @param int $parent_id  The proposed parent post ID, 0 clears the relationship.
		 * @return true|WP_Error
		 */
		public function validate_parent( $post_id, $parent_id ) {
			$post_id   = absint( $post_id );
			$parent_id = absint( $parent_id );

			if ( ! $parent_id ) {
				return true;
			}

			if ( $parent_id === $post_id ) {
				return new WP_Error( 'scf_nested_parent_self', __( 'A post cannot nest under itself.', 'secure-custom-fields' ) );
			}

			$post = get_post( $post_id );
			if ( ! $post ) {
				return new WP_Error( 'scf_nested_parent_invalid', __( 'The post could not be found.', 'secure-custom-fields' ) );
			}

			$allowed = $this->get_parent_post_type( $post->post_type );
			if ( ! $allowed ) {
				return new WP_Error( 'scf_nested_parent_unsupported', __( 'This post type does not support nesting.', 'secure-custom-fields' ) );
			}

			$parent = get_post( $parent_id );
			if ( ! $parent || 'trash' === $parent->post_status ) {
				return new WP_Error( 'scf_nested_parent_invalid', __( 'The parent post could not be found.', 'secure-custom-fields' ) );
			}

			if ( $parent->post_type !== $allowed ) {
				return new WP_Error(
					'scf_nested_parent_type',
					/* translators: %s post type name */
					sprintf( __( 'The parent post must be of the %s post type.', 'secure-custom-fields' ), $allowed )
				);
			}

			$ancestors = $this->get_ancestor_posts( $parent_id );
			foreach ( $ancestors as $ancestor ) {
				if ( $ancestor->ID === $post_id ) {
					return new WP_Error( 'scf_nested_parent_cycle', __( 'This parent would create a circular hierarchy.', 'secure-custom-fields' ) );
				}
			}

			if ( count( $ancestors ) + 1 > self::MAX_DEPTH ) {
				return new WP_Error( 'scf_nested_parent_depth', __( 'The hierarchy is too deep.', 'secure-custom-fields' ) );
			}

			return true;
		}

		/**
		 * Sets or clears the nested parent of a post after validation.
		 *
		 * @param int $post_id   The child post ID.
		 * @param int $parent_id The parent post ID, 0 clears the relationship.
		 * @return true|WP_Error
		 */
		public function set_parent( $post_id, $parent_id ) {
			$valid = $this->validate_parent( $post_id, $parent_id );
			if ( is_wp_error( $valid ) ) {
				return $valid;
			}

			$parent_id = absint( $parent_id );

			if ( ! $parent_id ) {
				delete_post_meta( $post_id, self::META_KEY );
			} else {
				update_post_meta( $post_id, self::META_KEY, $parent_id );
			}

			$this->invalidate_rewrites();

			return true;
		}

		/**
		 * Returns the URL path for a nested post, relative to the site root.
		 *
		 * The path starts with the root ancestor's post type base and continues
		 * with each slug down to the post. Pages have no base, so a page root
		 * yields its native ancestors followed by the child slugs. Returns an
		 * empty string when the post is not nested.
		 *
		 * @param WP_Post|int $post The post or post ID.
		 * @return string The relative path with leading and trailing slash, or empty string.
		 */
		public function get_nested_path( $post ) {
			$post = get_post( $post );
			if ( ! $post ) {
				return '';
			}

			$chain = array_reverse( $this->get_ancestor_posts( $post->ID ) );
			if ( empty( $chain ) ) {
				return '';
			}

			$root     = $chain[0];
			$segments = array();

			// The root post type's rewrite slug forms the base segment. Pages are
			// served without a base, so they contribute none.
			$root_object = get_post_type_object( $root->post_type );
			if ( $root_object && 'page' !== $root->post_type ) {
				if ( is_array( $root_object->rewrite ) && ! empty( $root_object->rewrite['slug'] ) ) {
					$segments[] = $root_object->rewrite['slug'];
				} elseif ( ! empty( $root_object->rewrite ) ) {
					// Rewrite enabled with the default slug, which is the post type key.
					$segments[] = $root->post_type;
				}
			}

			// Include the root's own same-type ancestors (e.g. a nested page tree).
			if ( $root_object && ! empty( $root_object->hierarchical ) ) {
				foreach ( array_reverse( get_post_ancestors( $root->ID ) ) as $native_id ) {
					$native = get_post( $native_id );
					if ( $native ) {
						$segments[] = $native->post_name;
					}
				}
			}

			foreach ( $chain as $ancestor ) {
				if ( '' !== $ancestor->post_name ) {
					$segments[] = $ancestor->post_name;
				}
			}

			if ( '' !== $post->post_name ) {
				$segments[] = $post->post_name;
			}

			$segments = array_values( array_filter( $segments, 'strlen' ) );
			if ( empty( $segments ) ) {
				return '';
			}

			return '/' . implode( '/', $segments ) . '/';
		}

		/**
		 * Builds the nested permalink for a post when it has a valid parent.
		 *
		 * @filter post_type_link
		 *
		 * @param string  $url  The default permalink.
		 * @param WP_Post $post The post.
		 * @return string
		 */
		public function filter_post_type_link( $url, $post ) {
			if ( is_wp_error( $url ) || ! $post instanceof WP_Post ) {
				return $url;
			}

			// The nested path only resolves when the site uses pretty permalinks.
			global $wp_rewrite;
			if ( ! $wp_rewrite || ! $wp_rewrite->using_permalinks() ) {
				return $url;
			}

			$path = $this->get_nested_path( $post );
			if ( '' === $path ) {
				return $url;
			}

			return home_url( user_trailingslashit( $path ) );
		}

		/**
		 * Adds rewrite rules for every nested post so the combined URLs resolve.
		 *
		 * @filter rewrite_rules_array
		 *
		 * @param array $rules The registered rewrite rules.
		 * @return array
		 */
		public function filter_rewrite_rules_array( $rules ) {
			// Rules are meaningless without a permalink structure.
			global $wp_rewrite;
			if ( ! $wp_rewrite || ! $wp_rewrite->using_permalinks() ) {
				return $rules;
			}

			$nested_posts = $this->get_nested_posts();

			return $this->add_rules_for_posts( $nested_posts, $rules );
		}

		/**
		 * Builds rewrite rules for the given nested post IDs.
		 *
		 * Kept separate from the enumeration so the URL-to-rule mapping can be
		 * exercised without a meta query, which some test environments lack.
		 *
		 * @param int[] $post_ids Nested post IDs.
		 * @param array $rules    Existing rewrite rules.
		 * @return array
		 */
		public function add_rules_for_posts( $post_ids, $rules ) {
			$paths      = array();
			$path_count = array();

			foreach ( $post_ids as $post_id ) {
				$path = trim( $this->get_nested_path( $post_id ), '/' );
				if ( '' === $path ) {
					continue;
				}

				$paths[ $post_id ] = $path;
				if ( ! isset( $path_count[ $path ] ) ) {
					$path_count[ $path ] = 0;
				}
				++$path_count[ $path ];
			}

			$nested_rules = array();
			foreach ( $paths as $path ) {
				$regex = '^' . $path . '/?$';

				// Leave ambiguous paths and rules already owned by WordPress or another plugin untouched.
				if ( 1 !== $path_count[ $path ] || isset( $rules[ $regex ] ) ) {
					continue;
				}

				$nested_rules[ $regex ] = 'index.php?' . self::QUERY_VAR . '=' . $path;
			}

			// Put specific nested paths before WordPress's broad page and post rules.
			return $nested_rules + $rules;
		}

		/**
		 * Returns the IDs of all published posts that have a nested parent.
		 *
		 * @return int[]
		 */
		public function get_nested_posts() {
			$nested_post_types = $this->get_nested_post_types();
			$ids               = array();

			if ( ! empty( $nested_post_types ) ) {
				$ids = get_posts(
					array(
						'post_type'        => $nested_post_types,
						'post_status'      => 'publish',
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- The parent link only exists as this meta key, so it is the query.
						'meta_key'         => self::META_KEY,
						'numberposts'      => -1,
						'fields'           => 'ids',
						'suppress_filters' => false,
					)
				);
			}

			/**
			 * Filters the post IDs used to build nested rewrite rules.
			 *
			 * Allows large sites to supply the nested post IDs from their own
			 * index instead of a meta query.
			 *
			 * @since SCF {NEXT_MAJOR_VERSION}
			 *
			 * @param int[]  $ids               Nested post IDs.
			 * @param string[] $nested_types    The nested child post type keys.
			 * @param SCF_Nested_Post_Types $service The service instance.
			 */
			return apply_filters( 'scf/nested_post_ids', $ids, $nested_post_types, $this );
		}

		/**
		 * Registers the nested URI query var.
		 *
		 * @filter query_vars
		 *
		 * @param array $vars Public query vars.
		 * @return array
		 */
		public function filter_query_vars( $vars ) {
			$vars[] = self::QUERY_VAR;
			return $vars;
		}

		/**
		 * Resolves a nested request to its post.
		 *
		 * @action parse_request
		 *
		 * @param WP $wp The WP instance handling the request.
		 * @return void
		 */
		public function resolve_request( $wp ) {
			if ( empty( $wp->query_vars[ self::QUERY_VAR ] ) ) {
				return;
			}

			$path  = trim( urldecode( $wp->query_vars[ self::QUERY_VAR ] ), '/' );
			$slugs = array_map( 'sanitize_title', array_filter( explode( '/', $path ) ) );
			$path  = implode( '/', $slugs );
			if ( empty( $slugs ) ) {
				return;
			}

			$last_slug = (string) end( $slugs );
			$found     = null;

			foreach ( $this->find_candidates( $last_slug ) as $candidate ) {
				if ( 'trash' === $candidate->post_status || ( 'publish' !== $candidate->post_status && ! current_user_can( 'read_post', $candidate->ID ) ) ) {
					continue;
				}

				$candidate_path = trim( $this->get_nested_path( $candidate->ID ), '/' );
				if ( '' !== $candidate_path && $candidate_path === $path ) {
					$found = $candidate;
					break;
				}
			}

			if ( ! $found ) {
				$wp->set_404();
				return;
			}

			unset(
				$wp->query_vars[ self::QUERY_VAR ],
				$wp->query_vars['name'],
				$wp->query_vars['pagename'],
				$wp->query_vars['page_id']
			);
			$wp->query_vars['post_type'] = $found->post_type;
			$wp->query_vars['p']         = $found->ID;

			/**
			 * Fires after a nested post request has been resolved.
			 *
			 * @since SCF {NEXT_MAJOR_VERSION}
			 *
			 * @param WP_Post $found The resolved post.
			 * @param string  $path  The requested relative path.
			 */
			do_action( 'scf/nested_post/resolved', $found, $path );
		}

		/**
		 * Returns the posts directly nested under a post.
		 *
		 * @param int $post_id The parent post ID.
		 * @return WP_Post[]
		 */
		public function get_nested_children( $post_id ) {
			$post_id    = absint( $post_id );
			$children   = array();
			$post_types = $this->get_nested_post_types();

			if ( $post_id && ! empty( $post_types ) ) {
				// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- The parent link only exists as this meta key and value.
				$children = get_posts(
					array(
						'post_type'        => $post_types,
						'post_status'      => 'publish',
						'meta_key'         => self::META_KEY,
						'meta_value'       => $post_id,
						'numberposts'      => -1,
						'suppress_filters' => false,
					)
				);
				// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			}

			/**
			 * Filters the direct children of a nested post.
			 *
			 * @since SCF {NEXT_MAJOR_VERSION}
			 *
			 * @param WP_Post[] $children The child posts.
				 * @param int       $post_id  The parent post ID.
				 */
			return apply_filters( 'scf/nested_children', $children, $post_id );
		}

		/**
		 * Finds candidate child posts for the last slug of a nested request.
		 *
		 * @param string $slug The final slug in the requested path.
		 * @return WP_Post[]
		 */
		public function find_candidates( $slug ) {
			$candidates = array();

			foreach ( $this->get_nested_post_types() as $post_type ) {
				$found = get_page_by_path( $slug, OBJECT, $post_type );
				if ( $found ) {
					$candidates[] = $found;
					continue;
				}

				$posts = get_posts(
					array(
						'post_type'   => $post_type,
						'name'        => $slug,
						'post_status' => 'publish',
						'numberposts' => 5,
					)
				);

				if ( ! empty( $posts ) ) {
					$candidates = array_merge( $candidates, $posts );
				}
			}

			/**
			 * Filters the candidate posts considered when resolving a nested URL.
			 *
			 * Integrations that resolve slugs differently, or that index nested
			 * posts, can supply candidates here. The path is then verified against
			 * each candidate before it is accepted.
			 *
			 * @since SCF {NEXT_MAJOR_VERSION}
			 *
			 * @param WP_Post[] $candidates Candidate posts.
			 * @param string    $slug       The final slug in the requested path.
			 * @param SCF_Nested_Post_Types $service The service instance.
			 */
			return apply_filters( 'scf/nested_resolve_candidates', $candidates, $slug, $this );
		}

		/**
		 * Registers the relationship meta while blocking unvalidated core REST writes.
		 *
		 * @return void
		 */
		public function register_meta() {
			foreach ( $this->get_nested_post_types() as $post_type ) {
				register_post_meta(
					$post_type,
					self::META_KEY,
					array(
						'type'          => 'integer',
						'single'        => true,
						'show_in_rest'  => true,
						'auth_callback' => '__return_false',
					)
				);
			}
		}

		/**
		 * Adds the nesting meta box to editors of nested post types.
		 *
		 * @action add_meta_boxes
		 *
		 * @param string $post_type The post type being edited.
		 * @return void
		 */
		public function add_meta_box( $post_type ) {
			if ( ! $this->should_show_meta_box( $post_type ) || ! function_exists( 'add_meta_box' ) ) {
				return;
			}

			add_meta_box(
				'scf-nested-parent',
				__( 'Nesting', 'secure-custom-fields' ),
				array( $this, 'render_meta_box' ),
				$post_type,
				'side',
				'default'
			);
		}

		/**
		 * Returns true when the nesting meta box should appear for a post type.
		 *
		 * @param string $post_type The post type key.
		 * @return boolean
		 */
		public function should_show_meta_box( $post_type ) {
			return (bool) $this->get_parent_post_type( $post_type );
		}

		/**
		 * Renders the nested parent select for the meta box.
		 *
		 * @param WP_Post $post The post being edited.
		 * @return void
		 */
		public function render_meta_box( $post ) {
			wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

			$parent_type    = $this->get_parent_post_type( $post->post_type );
			$current_parent = $this->get_parent_id( $post->ID );

			$parents = get_posts(
				array(
					'post_type'        => $parent_type,
					'post_status'      => array( 'publish', 'pending', 'draft', 'future', 'private' ),
					// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_numberposts -- A bounded candidate list for the select; search UI is a known follow-up.
					'numberposts'      => 200,
					'orderby'          => array( 'title' => 'ASC' ),
					'exclude'          => array( $post->ID ),
					'suppress_filters' => false,
				)
			);

			$selected = array();
			foreach ( $parents as $parent ) {
				$selected[ $parent->ID ] = $parent->post_title;
			}

			// Keep the current parent selectable even if it is not in the list above.
			if ( $current_parent && ! isset( $selected[ $current_parent ] ) ) {
				$parent = get_post( $current_parent );
				if ( $parent ) {
					$selected[ $parent->ID ] = $parent->post_title;
				}
			}
			?>
			<p>
				<label for="scf_nested_parent"><?php esc_html_e( 'Nested parent', 'secure-custom-fields' ); ?></label>
				<select id="scf_nested_parent" name="scf_nested_parent" style="width:100%;">
					<option value="0"><?php esc_html_e( 'None', 'secure-custom-fields' ); ?></option>
					<?php foreach ( $selected as $parent_id => $parent_title ) : ?>
						<option value="<?php echo esc_attr( $parent_id ); ?>" <?php selected( $current_parent, $parent_id ); ?>>
							<?php echo esc_html( $parent_title ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>
			<?php
		}

		/**
		 * Saves the nested parent selection from the meta box.
		 *
		 * @action save_post
		 *
		 * @param int $post_id The post being saved.
		 * @return void
		 */
		public function save_meta_box( $post_id ) {
			if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
				return;
			}

			$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) );
			if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
				return;
			}

			if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
				return;
			}

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}

			if ( ! isset( $_POST['scf_nested_parent'] ) ) {
				return;
			}

			$parent_id = absint( wp_unslash( $_POST['scf_nested_parent'] ) );
			$current   = $this->get_parent_id( $post_id );

			if ( $parent_id === $current ) {
				return;
			}

			$result = $this->set_parent( $post_id, $parent_id );

			if ( is_wp_error( $result ) && ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) ) {
				set_transient( 'scf_nested_parent_error_' . $post_id, $result->get_error_message(), 60 );
			}
		}

		/**
		 * Shows an admin notice when a nested parent update failed validation.
		 *
		 * @return void
		 */
		public function nested_parent_error_notice() {
			$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The ID only selects a transient notice for the current editor screen.
			if ( ! $post_id ) {
				return;
			}

			$key   = 'scf_nested_parent_error_' . $post_id;
			$error = get_transient( $key );
			if ( ! $error ) {
				return;
			}

			delete_transient( $key );
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $error ) );
		}

		/**
		 * Drops cached rewrite rules when a nested post is saved or deleted.
		 *
		 * Slug and relationship changes affect the generated URLs, so rules are
		 * rebuilt on the next request.
		 *
		 * @return void
		 */
		public function invalidate_rewrites() {
			delete_option( 'rewrite_rules' );
		}

		/**
		 * Invalidates rewrites when a post slug or status changes.
		 *
		 * @action post_updated
		 *
		 * @param int     $post_id      The post ID.
		 * @param WP_Post $post_after   The post after the update.
		 * @param WP_Post $post_before  The post before the update.
		 * @return void
		 */
		public function invalidate_after_post_updated( $post_id, $post_after, $post_before ) {
			if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
				return;
			}

			if ( $post_after->post_name === $post_before->post_name && $post_after->post_status === $post_before->post_status ) {
				return;
			}

			$this->invalidate_for_post( $post_id );
		}

		/**
		 * Invalidates rewrites when a nested post or its ancestor is deleted.
		 *
		 * @action before_delete_post
		 *
		 * @param int $post_id The post ID.
		 * @return void
		 */
		public function invalidate_for_post( $post_id ) {
			$is_nested_child = (bool) $this->get_parent_id( $post_id );
			if ( $is_nested_child ) {
				$this->invalidate_rewrites();
				return;
			}

			// The post may be the root of a chain whose slug just changed.
			// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Both meta fields are required to find direct children.
			$children = get_posts(
				array(
					'post_type'   => $this->get_nested_post_types(),
					'meta_key'    => self::META_KEY,
					'meta_value'  => $post_id,
					'numberposts' => 1,
					'fields'      => 'ids',
				)
			);
			// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value

			if ( ! empty( $children ) ) {
				$this->invalidate_rewrites();
			}
		}
	}
endif;


if ( ! function_exists( 'scf_nested_post_types_enabled' ) ) {
	/**
	 * Returns true when the nested post types beta feature is enabled.
	 *
	 * @since SCF {NEXT_MAJOR_VERSION}
	 *
	 * @return boolean
	 */
	function scf_nested_post_types_enabled() {
		return SCF_Nested_Post_Types::enabled();
	}
}

if ( ! function_exists( 'scf_get_nested_parent_post_type' ) ) {
	/**
	 * Gets the post type items of the given post type may nest under.
	 *
	 * @since SCF {NEXT_MAJOR_VERSION}
	 *
	 * @param string $post_type The child post type key.
	 * @return string The parent post type key, or empty string.
	 */
	function scf_get_nested_parent_post_type( $post_type ) {
		$nested = SCF_Nested_Post_Types::instance();
		if ( ! $nested ) {
			return '';
		}

		return $nested->get_parent_post_type( $post_type );
	}
}

if ( ! function_exists( 'scf_get_nested_parent' ) ) {
	/**
	 * Gets the nested parent post ID for a post.
	 *
	 * @since SCF {NEXT_MAJOR_VERSION}
	 *
	 * @param int $post_id The post ID.
	 * @return int The parent post ID, or 0.
	 */
	function scf_get_nested_parent( $post_id ) {
		return (int) get_post_meta( $post_id, SCF_Nested_Post_Types::META_KEY, true );
	}
}

if ( ! function_exists( 'scf_set_nested_parent' ) ) {
	/**
	 * Sets or clears the nested parent of a post after validation.
	 *
	 * @since SCF {NEXT_MAJOR_VERSION}
	 *
	 * @param int $post_id   The child post ID.
	 * @param int $parent_id The parent post ID, 0 clears the relationship.
	 * @return true|WP_Error
	 */
	function scf_set_nested_parent( $post_id, $parent_id ) {
		$nested = SCF_Nested_Post_Types::instance();
		if ( ! $nested ) {
			return new WP_Error( 'scf_nested_parent_disabled', __( 'Nested post types are not enabled.', 'secure-custom-fields' ) );
		}

		return $nested->set_parent( $post_id, $parent_id );
	}
}

if ( ! function_exists( 'scf_get_nested_ancestors' ) ) {
	/**
	 * Gets the nested ancestors of a post, nearest parent first.
	 *
	 * @since SCF {NEXT_MAJOR_VERSION}
	 *
	 * @param int $post_id The post ID.
	 * @return WP_Post[]
	 */
	function scf_get_nested_ancestors( $post_id ) {
		$nested = SCF_Nested_Post_Types::instance();
		if ( ! $nested ) {
			return array();
		}

		return $nested->get_ancestor_posts( $post_id );
	}
}

if ( ! function_exists( 'scf_get_nested_children' ) ) {
	/**
	 * Gets the posts directly nested under a post.
	 *
	 * @since SCF {NEXT_MAJOR_VERSION}
	 *
	 * @param int $post_id The parent post ID.
	 * @return WP_Post[]
	 */
	function scf_get_nested_children( $post_id ) {
		$nested = SCF_Nested_Post_Types::instance();
		if ( ! $nested ) {
			return array();
		}

		return $nested->get_nested_children( $post_id );
	}
}

/**
 * Bootstraps nested post types support once post types are registered.
 *
 * @since SCF {NEXT_MAJOR_VERSION}
 *
 * @return void
 */
function scf_bootstrap_nested_post_types() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;

	// The endpoint's register_routes() checks the beta flag, so it is safe to
	// create it even while the feature is disabled.
	acf_include( 'includes/rest-api/class-scf-nested-post-types-endpoint.php' );
	new SCF_Rest_Nested_Post_Types_Endpoint();

	SCF_Nested_Post_Types::instance();
}
add_action( 'acf/init', 'scf_bootstrap_nested_post_types', 20 );
