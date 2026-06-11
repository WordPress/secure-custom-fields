<?php
/**
 * Tests for SCF version migration logic in includes/upgrades.php.
 *
 * Note on the WorDBless environment: raw $wpdb queries are no-ops, so the
 * upgrade routines that read legacy rows via direct SQL are fed through the
 * `wordbless_wpdb_query_results` filter, and writes performed via
 * $wpdb->insert() are captured through the `wordbless_wpdb_insert` filter.
 * Posts, postmeta and options all persist through the regular WordPress
 * APIs, so everything else runs as a true integration test.
 *
 * Also note that WorDBless stores post rows exactly as passed to the
 * `wp_insert_post_data` filter, i.e. still slashed. Serialized field/field
 * group settings saved to post_content must be passed through wp_unslash()
 * before unserializing when asserting against the raw stored post.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Test the upgrade routines and DB version helpers.
 */
class Test_ACF_Upgrades extends BaseTestCase {

	/**
	 * Captured rows inserted into the termmeta table via $wpdb->insert().
	 *
	 * @var array
	 */
	private $captured_termmeta = array();

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->captured_termmeta = array();
	}

	/**
	 * Finds a stored field post (post_type acf-field) by its key (post_name).
	 *
	 * @param string $key The field key.
	 * @return WP_Post|null
	 */
	private function get_field_post( $key ) {
		foreach ( \WorDBless\Posts::init()->posts as $post ) {
			if ( 'acf-field' === $post->post_type && $key === $post->post_name ) {
				return get_post( $post->ID );
			}
		}
		return null;
	}

	/**
	 * Returns the unserialized settings stored in a post's post_content.
	 *
	 * WorDBless stores post rows slashed, so the content must be unslashed
	 * before unserializing.
	 *
	 * @param WP_Post $post The post object.
	 * @return array
	 */
	private function get_stored_settings( $post ) {
		$settings = maybe_unserialize( wp_unslash( $post->post_content ) );
		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Creates a legacy (ACF4-style) field group post with its postmeta.
	 *
	 * @param array $args  Optional post overrides.
	 * @param array $rules Optional location rules to store in 'rule' meta.
	 * @return integer The old field group post ID.
	 */
	private function create_legacy_field_group( $args = array(), $rules = array() ) {
		$post_id = wp_insert_post(
			wp_parse_args(
				$args,
				array(
					'post_type'   => 'acf',
					'post_title'  => 'Legacy Group',
					'post_status' => 'publish',
					'menu_order'  => 3,
				)
			)
		);

		foreach ( $rules as $rule ) {
			add_post_meta( $post_id, 'rule', $rule );
		}

		return $post_id;
	}

	/**
	 * Registers a `wordbless_wpdb_query_results` filter that feeds legacy
	 * field rows to the raw postmeta query in acf_upgrade_500_fields().
	 *
	 * @param integer $ofg_id The old field group post ID.
	 * @param array   $fields Array of legacy field arrays keyed by meta key.
	 * @return void
	 */
	private function seed_legacy_fields( $ofg_id, $fields ) {
		add_filter(
			'wordbless_wpdb_query_results',
			function ( $results, $query ) use ( $ofg_id, $fields ) {
				global $wpdb;

				if ( false === strpos( $query, $wpdb->postmeta ) || false === strpos( $query, "post_id = {$ofg_id}" ) ) {
					return $results;
				}

				$rows    = array();
				$meta_id = 9000;
				foreach ( $fields as $meta_key => $field ) {
					// phpcs:disable WordPress.DB.SlowDBQuery -- mimicking raw postmeta rows, not building a query.
					$rows[] = (object) array(
						'meta_id'    => $meta_id++, // phpcs:ignore Squiz.Operators.IncrementDecrementUsage.Found -- simple counter.
						'post_id'    => $ofg_id,
						'meta_key'   => $meta_key,
						'meta_value' => serialize( $field ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- seeding legacy ACF4 data format.
					);
					// phpcs:enable WordPress.DB.SlowDBQuery
				}
				return $rows;
			},
			10,
			2
		);
	}

	/**
	 * Registers filters that capture $wpdb->insert() calls into termmeta and
	 * feed legacy option rows to the raw options query in
	 * acf_upgrade_550_taxonomy().
	 *
	 * @param array $option_rows Rows to return for the options query, each an
	 *                           array with option_name/option_value.
	 * @return void
	 */
	private function seed_legacy_termmeta( $option_rows ) {
		add_filter(
			'wordbless_wpdb_query_results',
			function ( $results, $query ) use ( $option_rows ) {
				global $wpdb;

				// Only intercept the options query for the 'category' taxonomy.
				if ( false === strpos( $query, $wpdb->options ) || false === strpos( $query, 'category' ) ) {
					return $results;
				}

				$rows      = array();
				$option_id = 1;
				foreach ( $option_rows as $name => $value ) {
					$rows[] = (object) array(
						'option_id'    => $option_id++, // phpcs:ignore Squiz.Operators.IncrementDecrementUsage.Found -- simple counter.
						'option_name'  => $name,
						'option_value' => $value,
					);
				}
				return $rows;
			},
			10,
			2
		);

		add_filter(
			'wordbless_wpdb_insert',
			function ( $result, $table, $data ) {
				global $wpdb;

				if ( $table === $wpdb->termmeta ) {
					$this->captured_termmeta[] = $data;
				}
				return $result;
			},
			10,
			3
		);
	}

	// =========================================================================
	// acf_get_db_version / acf_update_db_version
	// =========================================================================

	/**
	 * Test the DB version option round trip.
	 */
	public function test_db_version_round_trip() {
		delete_option( 'acf_version' );
		$this->assertFalse( acf_get_db_version() );

		acf_update_db_version( '5.5.0' );
		$this->assertSame( '5.5.0', acf_get_db_version() );

		acf_update_db_version( ACF_VERSION );
		$this->assertSame( ACF_VERSION, acf_get_db_version() );
	}

	// =========================================================================
	// acf_has_upgrade
	// =========================================================================

	/**
	 * Test that a fresh install has no upgrade and initializes the version.
	 */
	public function test_has_upgrade_fresh_install() {
		delete_option( 'acf_version' );

		$this->assertFalse( acf_has_upgrade() );

		// A fresh install gets stamped with the current version.
		$this->assertSame( ACF_VERSION, acf_get_db_version() );
	}

	/**
	 * Test that an ancient (ACF4-era) version requires an upgrade.
	 */
	public function test_has_upgrade_ancient_version() {
		acf_update_db_version( '4.4.12' );

		$this->assertTrue( acf_has_upgrade() );

		// The version is not touched until the upgrade actually runs.
		$this->assertSame( '4.4.12', acf_get_db_version() );
	}

	/**
	 * Test that a version just below the upgrade boundary requires an upgrade.
	 */
	public function test_has_upgrade_just_below_boundary() {
		acf_update_db_version( '5.4.9' );

		$this->assertTrue( acf_has_upgrade() );
		$this->assertSame( '5.4.9', acf_get_db_version() );
	}

	/**
	 * Test that a DB version equal to ACF_UPGRADE_VERSION needs no upgrade.
	 */
	public function test_has_upgrade_at_boundary() {
		acf_update_db_version( ACF_UPGRADE_VERSION );

		$this->assertFalse( acf_has_upgrade() );

		// The version is bumped to the current version as a side effect.
		$this->assertSame( ACF_VERSION, acf_get_db_version() );
	}

	/**
	 * Test that a DB version between the boundary and current needs no upgrade.
	 */
	public function test_has_upgrade_between_boundary_and_current() {
		acf_update_db_version( '6.0.0' );

		$this->assertFalse( acf_has_upgrade() );
		$this->assertSame( ACF_VERSION, acf_get_db_version() );
	}

	/**
	 * Test that the current version needs no upgrade and is left untouched.
	 */
	public function test_has_upgrade_current_version() {
		acf_update_db_version( ACF_VERSION );

		$this->assertFalse( acf_has_upgrade() );
		$this->assertSame( ACF_VERSION, acf_get_db_version() );
	}

	// =========================================================================
	// acf_upgrade_500 (field group migration)
	// =========================================================================

	/**
	 * Test that a legacy field group is migrated to the new post type with
	 * its location rules, position and hide_on_screen settings.
	 */
	public function test_upgrade_500_field_group_migrates_settings() {
		$ofg_id = $this->create_legacy_field_group(
			array(
				'post_title' => 'My Legacy Group',
				'menu_order' => 7,
			),
			array(
				array(
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => 'post',
					'order_no' => 0,
					'group_no' => 0,
				),
				array(
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => 'page',
					'order_no' => 0,
					'group_no' => 1,
				),
			)
		);
		add_post_meta( $ofg_id, 'allorany', 'any' );
		add_post_meta( $ofg_id, 'position', 'side' );
		add_post_meta( $ofg_id, 'hide_on_screen', serialize( array( 'the_content' ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- legacy data was stored serialized.

		$nfg = acf_upgrade_500_field_group( get_post( $ofg_id ) );

		$this->assertIsArray( $nfg );
		$this->assertGreaterThan( 0, $nfg['ID'] );
		$this->assertSame( 'My Legacy Group', $nfg['title'] );
		$this->assertSame( 7, $nfg['menu_order'] );

		// The new field group is saved under the new post type.
		$new_post = get_post( $nfg['ID'] );
		$this->assertSame( 'acf-field-group', $new_post->post_type );

		// Two rule groups (group_no 0 and 1) with one rule each.
		$this->assertCount( 2, $nfg['location'] );
		$this->assertSame( 'post_type', $nfg['location'][0][0]['param'] );
		$this->assertSame( 'post', $nfg['location'][0][0]['value'] );
		$this->assertSame( 'page', $nfg['location'][1][0]['value'] );

		// Settings carried over.
		$this->assertSame( 'side', $nfg['position'] );
		$this->assertSame( array( 'the_content' ), $nfg['hide_on_screen'] );

		// Settings persisted to the stored post.
		$stored = $this->get_stored_settings( $new_post );
		$this->assertSame( 'side', $stored['position'] );
		$this->assertSame( array( 'the_content' ), $stored['hide_on_screen'] );
		$this->assertSame( 'post', $stored['location'][0][0]['value'] );
	}

	/**
	 * Test that 'all' rules are merged into a single location group.
	 */
	public function test_upgrade_500_field_group_allorany_all_merges_rules() {
		$ofg_id = $this->create_legacy_field_group(
			array(),
			array(
				array(
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => 'post',
				),
				array(
					'param'    => 'post_status',
					'operator' => '==',
					'value'    => 'publish',
				),
			)
		);
		add_post_meta( $ofg_id, 'allorany', 'all' );

		$nfg = acf_upgrade_500_field_group( get_post( $ofg_id ) );

		// 'all' means a single AND group containing both rules.
		$this->assertCount( 1, $nfg['location'] );
		$this->assertCount( 2, $nfg['location'][0] );
	}

	/**
	 * Test that legacy fields stored in postmeta are migrated to acf-field posts.
	 */
	public function test_upgrade_500_migrates_fields_to_posts() {
		$ofg_id = $this->create_legacy_field_group();

		$this->seed_legacy_fields(
			$ofg_id,
			array(
				'field_legacy_text' => array(
					'key'          => 'field_legacy_text',
					'label'        => 'Legacy Text',
					'name'         => 'legacy_text',
					'type'         => 'text',
					'order_no'     => 2,
					'instructions' => 'Some instructions',
				),
			)
		);

		$nfg = acf_upgrade_500_field_group( get_post( $ofg_id ) );

		$field_post = $this->get_field_post( 'field_legacy_text' );
		$this->assertInstanceOf( 'WP_Post', $field_post );

		// Field posts store the key as post_name and the name as post_excerpt.
		$this->assertSame( 'acf-field', $field_post->post_type );
		$this->assertSame( 'legacy_text', $field_post->post_excerpt );
		$this->assertSame( 'Legacy Text', $field_post->post_title );

		// Field is parented to the new field group.
		$this->assertSame( $nfg['ID'], $field_post->post_parent );

		// order_no becomes menu_order.
		$this->assertSame( 2, $field_post->menu_order );

		// Settings persisted in post_content.
		$settings = $this->get_stored_settings( $field_post );
		$this->assertSame( 'text', $settings['type'] );
		$this->assertSame( 'Some instructions', $settings['instructions'] );
	}

	/**
	 * Test that a trashed legacy field group results in a trashed new group.
	 */
	public function test_upgrade_500_trashed_field_group_stays_trashed() {
		$ofg_id = $this->create_legacy_field_group( array( 'post_status' => 'trash' ) );

		$nfg = acf_upgrade_500_field_group( get_post( $ofg_id ) );

		$this->assertSame( 'trash', get_post( $nfg['ID'] )->post_status );
	}

	/**
	 * Test that acf_upgrade_500() bumps the DB version to 5.0.0.
	 */
	public function test_upgrade_500_updates_db_version() {
		acf_update_db_version( '4.4.12' );

		acf_upgrade_500();

		$this->assertSame( '5.0.0', acf_get_db_version() );
	}

	// =========================================================================
	// acf_upgrade_500_field
	// =========================================================================

	/**
	 * Test that very old field keys are corrected (field2 => field_2).
	 */
	public function test_upgrade_500_field_corrects_old_keys() {
		$field = acf_upgrade_500_field(
			array(
				'key'      => 'field2',
				'label'    => 'Very Old',
				'name'     => 'very_old',
				'type'     => 'text',
				'order_no' => 0,
			)
		);

		$this->assertSame( 'field_2', $field['key'] );
		$this->assertGreaterThan( 0, $field['ID'] );
	}

	/**
	 * Test that order_no is moved to menu_order and removed from the field.
	 */
	public function test_upgrade_500_field_order_no_becomes_menu_order() {
		$field = acf_upgrade_500_field(
			array(
				'key'      => 'field_order_test',
				'label'    => 'Order Test',
				'name'     => 'order_test',
				'type'     => 'text',
				'order_no' => 5,
			)
		);

		$this->assertSame( 5, $field['menu_order'] );
		$this->assertArrayNotHasKey( 'order_no', $field );
	}

	/**
	 * Test that repeater sub fields are extracted and saved as child fields.
	 */
	public function test_upgrade_500_field_repeater_sub_fields() {
		$field = acf_upgrade_500_field(
			array(
				'key'        => 'field_legacy_repeater',
				'label'      => 'Legacy Repeater',
				'name'       => 'legacy_repeater',
				'type'       => 'repeater',
				'order_no'   => 0,
				'sub_fields' => array(
					array(
						'key'      => 'field_legacy_sub',
						'label'    => 'Legacy Sub',
						'name'     => 'legacy_sub',
						'type'     => 'text',
						'order_no' => 0,
					),
				),
			)
		);

		// Sub fields are stripped from the parent settings.
		$this->assertArrayNotHasKey( 'sub_fields', $field );

		// The sub field is saved as its own post, parented to the repeater.
		$sub_post = $this->get_field_post( 'field_legacy_sub' );
		$this->assertInstanceOf( 'WP_Post', $sub_post );
		$this->assertSame( $field['ID'], $sub_post->post_parent );
	}

	/**
	 * Test that flexible content layouts get keys and sub fields are extracted.
	 */
	public function test_upgrade_500_field_flexible_content_layouts() {
		$field = acf_upgrade_500_field(
			array(
				'key'      => 'field_legacy_flex',
				'label'    => 'Legacy Flex',
				'name'     => 'legacy_flex',
				'type'     => 'flexible_content',
				'order_no' => 0,
				'layouts'  => array(
					array(
						'label'      => 'Layout One',
						'name'       => 'layout_one',
						'sub_fields' => array(
							array(
								'key'      => 'field_legacy_flex_sub',
								'label'    => 'Flex Sub',
								'name'     => 'flex_sub',
								'type'     => 'text',
								'order_no' => 0,
							),
						),
					),
				),
			)
		);

		// The layout received a generated key and lost its sub fields.
		$layouts = array_values( $field['layouts'] );
		$this->assertStringStartsWith( 'layout_', $layouts[0]['key'] );
		$this->assertArrayNotHasKey( 'sub_fields', $layouts[0] );

		// The sub field was saved, parented to the flex field, and tagged
		// with the generated parent_layout key.
		$sub_post = $this->get_field_post( 'field_legacy_flex_sub' );
		$this->assertInstanceOf( 'WP_Post', $sub_post );
		$this->assertSame( $field['ID'], $sub_post->post_parent );

		$sub_settings = $this->get_stored_settings( $sub_post );
		$this->assertSame( $layouts[0]['key'], $sub_settings['parent_layout'] );
	}

	// =========================================================================
	// acf_upgrade_550 (termmeta migration)
	// =========================================================================

	/**
	 * Test that legacy termmeta stored in wp_options is migrated to termmeta.
	 */
	public function test_upgrade_550_taxonomy_migrates_termmeta() {
		update_option( 'db_version', 58975 );

		$this->seed_legacy_termmeta(
			array(
				'category_7_color'  => 'red',
				'_category_7_color' => 'field_legacy_color',
			)
		);

		acf_upgrade_550_taxonomy( 'category' );

		$this->assertCount( 2, $this->captured_termmeta );

		// The value row.
		$this->assertEquals( 7, $this->captured_termmeta[0]['term_id'] );
		$this->assertSame( 'color', $this->captured_termmeta[0]['meta_key'] );
		$this->assertSame( 'red', $this->captured_termmeta[0]['meta_value'] );

		// The hidden field key reference row.
		$this->assertEquals( 7, $this->captured_termmeta[1]['term_id'] );
		$this->assertSame( '_color', $this->captured_termmeta[1]['meta_key'] );
		$this->assertSame( 'field_legacy_color', $this->captured_termmeta[1]['meta_value'] );
	}

	/**
	 * Test that option rows not matching the taxonomy pattern are skipped.
	 */
	public function test_upgrade_550_taxonomy_skips_non_matching_rows() {
		update_option( 'db_version', 58975 );

		$this->seed_legacy_termmeta(
			array(
				'category_settings'   => 'not termmeta',
				'my_category_7_color' => 'also not termmeta',
			)
		);

		acf_upgrade_550_taxonomy( 'category' );

		$this->assertCount( 0, $this->captured_termmeta );
	}

	/**
	 * Test that the termmeta migration bails when the termmeta table is
	 * unavailable (WP db_version below 34370).
	 */
	public function test_upgrade_550_termmeta_bails_without_termmeta_table() {
		update_option( 'db_version', 30000 );

		$this->seed_legacy_termmeta(
			array(
				'category_7_color' => 'red',
			)
		);

		acf_upgrade_550_termmeta();

		$this->assertCount( 0, $this->captured_termmeta );
	}

	/**
	 * Test that acf_upgrade_550() runs the migration over registered
	 * taxonomies and bumps the DB version to 5.5.0.
	 */
	public function test_upgrade_550_updates_db_version_and_migrates() {
		update_option( 'db_version', 58975 );
		acf_update_db_version( '5.4.0' );

		$this->seed_legacy_termmeta(
			array(
				'category_3_subtitle' => 'hello',
			)
		);

		acf_upgrade_550();

		$this->assertSame( '5.5.0', acf_get_db_version() );
		$this->assertCount( 1, $this->captured_termmeta );
		$this->assertSame( 'subtitle', $this->captured_termmeta[0]['meta_key'] );
	}

	/**
	 * Test the wp_upgrade hook handler runs the termmeta migration only when
	 * WP crosses the termmeta DB version boundary on an already-upgraded site.
	 */
	public function test_wp_upgrade_550_termmeta_hook() {
		update_option( 'db_version', 58975 );
		acf_update_db_version( '5.6.0' );

		$this->seed_legacy_termmeta(
			array(
				'category_9_color' => 'blue',
			)
		);

		// Not crossing the boundary: nothing happens.
		acf_wp_upgrade_550_termmeta( 34370, 34370 );
		$this->assertCount( 0, $this->captured_termmeta );

		// Crossing the boundary: migration runs.
		acf_wp_upgrade_550_termmeta( 34370, 30000 );
		$this->assertCount( 1, $this->captured_termmeta );
	}

	// =========================================================================
	// acf_upgrade_all
	// =========================================================================

	/**
	 * Installs a `posts_pre_query` filter that answers legacy field group
	 * queries (post_type "acf") from the WorDBless posts store, since
	 * WP_Query raw SQL is a no-op under WorDBless.
	 *
	 * @return void
	 */
	private function shim_legacy_field_group_query() {
		add_filter(
			'posts_pre_query',
			function ( $posts, $query ) {
				if ( 'acf' !== $query->get( 'post_type' ) ) {
					return $posts;
				}

				$found = array();
				foreach ( \WorDBless\Posts::init()->posts as $post ) {
					if ( 'acf' === $post->post_type ) {
						$found[] = new WP_Post( $post );
					}
				}
				return $found;
			},
			10,
			2
		);
	}

	/**
	 * Test that acf_upgrade_all() runs all pending upgrades and stamps the
	 * current version.
	 */
	public function test_upgrade_all_runs_pending_upgrades() {
		acf_update_db_version( '4.4.12' );
		update_option( 'db_version', 58975 );
		$this->shim_legacy_field_group_query();

		// Seed a legacy field group so the 5.0.0 routine has work to do.
		$ofg_id = $this->create_legacy_field_group(
			array( 'post_title' => 'Upgrade All Group' ),
			array(
				array(
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => 'post',
				),
			)
		);

		$upgraded_500 = 0;
		$upgraded_550 = 0;
		add_action(
			'acf/upgrade_500',
			function () use ( &$upgraded_500 ) {
				++$upgraded_500;
			}
		);
		add_action(
			'acf/upgrade_550',
			function () use ( &$upgraded_550 ) {
				++$upgraded_550;
			}
		);

		acf_upgrade_all();

		// Both routines ran exactly once.
		$this->assertSame( 1, $upgraded_500 );
		$this->assertSame( 1, $upgraded_550 );

		// The legacy field group was migrated.
		$migrated = null;
		foreach ( \WorDBless\Posts::init()->posts as $post ) {
			if ( 'acf-field-group' === $post->post_type && 'Upgrade All Group' === $post->post_title ) {
				$migrated = $post;
			}
		}
		$this->assertNotNull( $migrated );

		// DB version stamped with the current version, no upgrade pending.
		$this->assertSame( ACF_VERSION, acf_get_db_version() );
		$this->assertFalse( acf_has_upgrade() );

		// The legacy post itself is intentionally left in place.
		$this->assertSame( 'acf', get_post( $ofg_id )->post_type );
	}

	/**
	 * Test that acf_upgrade_all() skips routines already applied.
	 */
	public function test_upgrade_all_skips_completed_routines() {
		acf_update_db_version( '5.5.0' );

		$upgraded_500 = 0;
		$upgraded_550 = 0;
		add_action(
			'acf/upgrade_500',
			function () use ( &$upgraded_500 ) {
				++$upgraded_500;
			}
		);
		add_action(
			'acf/upgrade_550',
			function () use ( &$upgraded_550 ) {
				++$upgraded_550;
			}
		);

		acf_upgrade_all();

		$this->assertSame( 0, $upgraded_500 );
		$this->assertSame( 0, $upgraded_550 );
		$this->assertSame( ACF_VERSION, acf_get_db_version() );
	}
}
