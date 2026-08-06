<?php
/**
 * Tests for the row loop template API in includes/api/api-template.php.
 *
 * Covers have_rows(), the_row(), get_row(), get_row_index(), get_row_layout(),
 * get_sub_field(), get_sub_field_object(), the_sub_field(), has_sub_field(),
 * reset_rows(), add_row(), update_row(), delete_row(), add_sub_row(),
 * update_sub_row(), delete_sub_row(), update_sub_field() and delete_sub_field()
 * for repeater, flexible content and group fields.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Test the row loop template API functions.
 */
class Test_API_Template_Rows extends BaseTestCase {

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();

		$this->ensure_field_type_filters();

		$this->post_id = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Rows API Test Post',
				'post_status' => 'publish',
			)
		);

		acf_add_local_field_group(
			array(
				'key'      => 'group_rows_api',
				'title'    => 'Rows API Test Fields',
				'fields'   => array(
					array(
						'key'        => 'field_rows_rep',
						'name'       => 'rows_rep',
						'label'      => 'Parent Repeater',
						'type'       => 'repeater',
						'sub_fields' => array(
							array(
								'key'   => 'field_rows_rep_title',
								'name'  => 'title',
								'label' => 'Title',
								'type'  => 'text',
							),
							array(
								'key'        => 'field_rows_rep_child',
								'name'       => 'child_rep',
								'label'      => 'Child Repeater',
								'type'       => 'repeater',
								'sub_fields' => array(
									array(
										'key'   => 'field_rows_rep_child_item',
										'name'  => 'item',
										'label' => 'Item',
										'type'  => 'text',
									),
								),
							),
						),
					),
					array(
						'key'     => 'field_rows_flex',
						'name'    => 'rows_flex',
						'label'   => 'Flexible Content',
						'type'    => 'flexible_content',
						'layouts' => array(
							'layout_rows_hero'  => array(
								'key'        => 'layout_rows_hero',
								'name'       => 'hero',
								'label'      => 'Hero',
								'sub_fields' => array(
									array(
										'key'   => 'field_rows_flex_heading',
										'name'  => 'heading',
										'label' => 'Heading',
										'type'  => 'text',
									),
								),
							),
							'layout_rows_quote' => array(
								'key'        => 'layout_rows_quote',
								'name'       => 'quote',
								'label'      => 'Quote',
								'sub_fields' => array(
									array(
										'key'   => 'field_rows_flex_quote_text',
										'name'  => 'quote_text',
										'label' => 'Quote Text',
										'type'  => 'text',
									),
								),
							),
						),
					),
					array(
						'key'        => 'field_rows_grp',
						'name'       => 'rows_grp',
						'label'      => 'Group',
						'type'       => 'group',
						'sub_fields' => array(
							array(
								'key'   => 'field_rows_grp_first',
								'name'  => 'first',
								'label' => 'First',
								'type'  => 'text',
							),
							array(
								'key'   => 'field_rows_grp_second',
								'name'  => 'second',
								'label' => 'Second',
								'type'  => 'text',
							),
						),
					),
				),
				'location' => array(),
			)
		);

		acf_get_store( 'values' )->reset();
		acf()->loop->loops = array();
	}

	/**
	 * Clean up test data.
	 */
	public function tear_down() {
		acf_get_store( 'values' )->reset();
		acf()->loop->loops = array();
		parent::tear_down();
	}

	/**
	 * SCF registers field type hooks lazily via acf_init(), but the WorDBless
	 * base test case restores all hooks after each test to a snapshot taken
	 * before acf_init() ever ran. Since acf_init() only runs once per process,
	 * the field type filters (e.g. acf/update_value/type=repeater) would be
	 * missing for every test after the first. Re-instantiate the field types
	 * used by these tests to restore their hooks.
	 */
	private function ensure_field_type_filters() {
		acf_init();

		if ( has_filter( 'acf/format_value/type=repeater' ) ) {
			return;
		}

		foreach ( array( 'textarea', 'select', 'group', 'repeater', 'flexible_content' ) as $type ) {
			$instance = acf_get_field_type( $type );
			if ( $instance instanceof acf_field ) {
				acf_register_field_type( get_class( $instance ) );
			}
		}
	}

	/**
	 * Saves two parent repeater rows, the first with two child rows.
	 */
	private function save_nested_repeater_value() {
		update_field(
			'field_rows_rep',
			array(
				array(
					'title'     => 'Row A',
					'child_rep' => array(
						array( 'item' => 'a1' ),
						array( 'item' => 'a2' ),
					),
				),
				array(
					'title'     => 'Row B',
					'child_rep' => array(
						array( 'item' => 'b1' ),
					),
				),
			),
			$this->post_id
		);
		acf_get_store( 'values' )->reset();
	}

	// =========================================================================
	// have_rows() / the_row() basics
	// =========================================================================

	/**
	 * Test have_rows returns false when the repeater has no rows.
	 */
	public function test_have_rows_returns_false_when_no_rows() {
		$this->assertFalse( have_rows( 'rows_rep', $this->post_id ) );
		$this->assertSame( array(), acf()->loop->loops );
	}

	/**
	 * Test have_rows loops through all repeater rows in order.
	 */
	public function test_have_rows_loops_repeater_rows() {
		$this->save_nested_repeater_value();

		$titles = array();
		while ( have_rows( 'rows_rep', $this->post_id ) ) {
			the_row();
			$titles[] = get_sub_field( 'title' );
		}

		$this->assertSame( array( 'Row A', 'Row B' ), $titles );
	}

	/**
	 * Test have_rows works with a field key selector.
	 */
	public function test_have_rows_accepts_field_key_selector() {
		$this->save_nested_repeater_value();

		$titles = array();
		while ( have_rows( 'field_rows_rep', $this->post_id ) ) {
			the_row();
			$titles[] = get_sub_field( 'title' );
		}

		$this->assertSame( array( 'Row A', 'Row B' ), $titles );
	}

	/**
	 * Test nested have_rows loops iterate a repeater inside a repeater.
	 */
	public function test_have_rows_nested_repeater_loops() {
		$this->save_nested_repeater_value();

		$result = array();
		while ( have_rows( 'rows_rep', $this->post_id ) ) {
			the_row();
			$row = array(
				'title' => get_sub_field( 'title' ),
				'items' => array(),
			);
			while ( have_rows( 'child_rep' ) ) {
				the_row();
				$row['items'][] = get_sub_field( 'item' );
			}
			$result[] = $row;
		}

		$this->assertSame(
			array(
				array(
					'title' => 'Row A',
					'items' => array( 'a1', 'a2' ),
				),
				array(
					'title' => 'Row B',
					'items' => array( 'b1' ),
				),
			),
			$result
		);
		$this->assertSame( array(), acf()->loop->loops );
	}

	/**
	 * Test the loop state is fully reset after a completed have_rows loop.
	 */
	public function test_loop_state_resets_between_have_rows_calls() {
		$this->save_nested_repeater_value();

		$first_pass = array();
		while ( have_rows( 'rows_rep', $this->post_id ) ) {
			the_row();
			$first_pass[] = get_sub_field( 'title' );
		}

		$this->assertSame( array(), acf()->loop->loops );

		// A second loop over the same field starts from the first row again.
		$second_pass = array();
		while ( have_rows( 'rows_rep', $this->post_id ) ) {
			the_row();
			$second_pass[] = get_sub_field( 'title' );
		}

		$this->assertSame( $first_pass, $second_pass );
	}

	/**
	 * Test sequential have_rows loops over different posts are independent.
	 */
	public function test_have_rows_sequential_loops_on_different_posts() {
		$this->save_nested_repeater_value();

		$post_id_2 = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Second Rows Post',
				'post_status' => 'publish',
			)
		);
		update_field( 'field_rows_rep', array( array( 'title' => 'Other Post Row' ) ), $post_id_2 );
		acf_get_store( 'values' )->reset();

		$titles = array();
		while ( have_rows( 'rows_rep', $this->post_id ) ) {
			the_row();
			$titles[] = get_sub_field( 'title' );
		}
		while ( have_rows( 'rows_rep', $post_id_2 ) ) {
			the_row();
			$titles[] = get_sub_field( 'title' );
		}

		$this->assertSame( array( 'Row A', 'Row B', 'Other Post Row' ), $titles );
	}

	/**
	 * Test reset_rows clears the active loop so it can be restarted.
	 */
	public function test_reset_rows_breaks_out_of_loop() {
		$this->save_nested_repeater_value();

		// Read only the first row, then break out.
		$titles = array();
		while ( have_rows( 'rows_rep', $this->post_id ) ) {
			the_row();
			$titles[] = get_sub_field( 'title' );
			$this->assertTrue( reset_rows() );
			break;
		}

		$this->assertSame( array( 'Row A' ), $titles );
		$this->assertSame( array(), acf()->loop->loops );

		// The next loop starts from the beginning.
		$this->assertTrue( have_rows( 'rows_rep', $this->post_id ) );
		the_row();
		$this->assertSame( 'Row A', get_sub_field( 'title' ) );
		reset_rows();
	}

	/**
	 * Test has_sub_field progresses the row pointer on each call.
	 */
	public function test_has_sub_field_progresses_rows() {
		$this->save_nested_repeater_value();

		$titles = array();
		while ( has_sub_field( 'rows_rep', $this->post_id ) ) {
			$titles[] = get_sub_field( 'title' );
		}

		$this->assertSame( array( 'Row A', 'Row B' ), $titles );
	}

	// =========================================================================
	// get_row() / get_row_index() / row sub value access
	// =========================================================================

	/**
	 * Test get_row returns the current row keyed by sub field keys when unformatted.
	 */
	public function test_get_row_unformatted_uses_field_keys() {
		$this->save_nested_repeater_value();

		have_rows( 'rows_rep', $this->post_id );
		$row = the_row();

		$this->assertIsArray( $row );
		$this->assertArrayHasKey( 'field_rows_rep_title', $row );
		$this->assertSame( 'Row A', $row['field_rows_rep_title'] );

		reset_rows();
	}

	/**
	 * Test get_row with format true returns the row keyed by sub field names.
	 */
	public function test_get_row_formatted_uses_field_names() {
		$this->save_nested_repeater_value();

		have_rows( 'rows_rep', $this->post_id );
		the_row();
		$row = get_row( true );

		$this->assertIsArray( $row );
		$this->assertArrayHasKey( 'title', $row );
		$this->assertSame( 'Row A', $row['title'] );

		reset_rows();
	}

	/**
	 * Test get_row returns false when called outside of a loop.
	 */
	public function test_get_row_returns_false_outside_loop() {
		$this->assertFalse( get_row() );
	}

	/**
	 * Test get_row_index returns the row number using the row_index_offset setting.
	 */
	public function test_get_row_index_uses_offset() {
		$this->save_nested_repeater_value();

		$indexes = array();
		while ( have_rows( 'rows_rep', $this->post_id ) ) {
			the_row();
			$indexes[] = get_row_index();
		}

		// Default row_index_offset is 1.
		$this->assertSame( array( 1, 2 ), $indexes );
	}

	/**
	 * Test the_row_index echoes the current row index.
	 */
	public function test_the_row_index_echoes_index() {
		$this->save_nested_repeater_value();

		have_rows( 'rows_rep', $this->post_id );
		the_row();

		ob_start();
		the_row_index();
		$output = ob_get_clean();

		$this->assertSame( '1', $output );

		reset_rows();
	}

	// =========================================================================
	// get_sub_field() / get_sub_field_object() / the_sub_field()
	// =========================================================================

	/**
	 * Test get_sub_field returns false outside of a have_rows loop.
	 */
	public function test_get_sub_field_returns_false_outside_loop() {
		$this->assertFalse( get_sub_field( 'title' ) );
	}

	/**
	 * Test get_sub_field returns false for an unknown sub field name.
	 */
	public function test_get_sub_field_returns_false_for_unknown_sub_field() {
		$this->save_nested_repeater_value();

		have_rows( 'rows_rep', $this->post_id );
		the_row();

		$this->assertFalse( get_sub_field( 'does_not_exist' ) );

		reset_rows();
	}

	/**
	 * Test get_sub_field_object returns the sub field array with a row-based name.
	 */
	public function test_get_sub_field_object_returns_field_with_row_name() {
		$this->save_nested_repeater_value();

		have_rows( 'rows_rep', $this->post_id );
		the_row();
		$sub_field = get_sub_field_object( 'title' );

		$this->assertIsArray( $sub_field );
		$this->assertSame( 'field_rows_rep_title', $sub_field['key'] );
		$this->assertSame( 'rows_rep_0_title', $sub_field['name'] );
		$this->assertSame( 'Row A', $sub_field['value'] );

		reset_rows();
	}

	/**
	 * Test get_sub_field_object accepts a sub field key selector.
	 */
	public function test_get_sub_field_object_accepts_field_key() {
		$this->save_nested_repeater_value();

		have_rows( 'rows_rep', $this->post_id );
		the_row();
		$sub_field = get_sub_field_object( 'field_rows_rep_title' );

		$this->assertIsArray( $sub_field );
		$this->assertSame( 'Row A', $sub_field['value'] );

		reset_rows();
	}

	/**
	 * Test the_sub_field echoes the sub field value.
	 */
	public function test_the_sub_field_echoes_value() {
		$this->save_nested_repeater_value();

		have_rows( 'rows_rep', $this->post_id );
		the_row();

		ob_start();
		the_sub_field( 'title' );
		$output = ob_get_clean();

		$this->assertSame( 'Row A', $output );

		reset_rows();
	}

	/**
	 * Test the_sub_field strips unsafe HTML from output.
	 */
	public function test_the_sub_field_escapes_unsafe_html() {
		update_field(
			'field_rows_rep',
			array( array( 'title' => '<script>bad()</script>good' ) ),
			$this->post_id
		);
		acf_get_store( 'values' )->reset();

		have_rows( 'rows_rep', $this->post_id );
		the_row();

		ob_start();
		the_sub_field( 'title' );
		$output = ob_get_clean();

		$this->assertStringNotContainsString( '<script>', $output );
		$this->assertStringContainsString( 'good', $output );

		reset_rows();
	}

	// =========================================================================
	// Flexible content
	// =========================================================================

	/**
	 * Test have_rows iterates flexible content layouts with get_row_layout.
	 */
	public function test_have_rows_flexible_content_layouts() {
		update_field(
			'field_rows_flex',
			array(
				array(
					'acf_fc_layout' => 'hero',
					'heading'       => 'Big Heading',
				),
				array(
					'acf_fc_layout' => 'quote',
					'quote_text'    => 'Famous words',
				),
			),
			$this->post_id
		);
		acf_get_store( 'values' )->reset();

		$rendered = array();
		while ( have_rows( 'rows_flex', $this->post_id ) ) {
			the_row();
			if ( get_row_layout() === 'hero' ) {
				$rendered[] = 'hero:' . get_sub_field( 'heading' );
			} elseif ( get_row_layout() === 'quote' ) {
				$rendered[] = 'quote:' . get_sub_field( 'quote_text' );
			}
		}

		$this->assertSame( array( 'hero:Big Heading', 'quote:Famous words' ), $rendered );
	}

	/**
	 * Test get_row_layout returns false outside of a loop.
	 */
	public function test_get_row_layout_returns_false_outside_loop() {
		$this->assertFalse( get_row_layout() );
	}

	/**
	 * Test get_row_layout returns false inside a repeater loop (no layouts).
	 */
	public function test_get_row_layout_returns_false_in_repeater_loop() {
		$this->save_nested_repeater_value();

		have_rows( 'rows_rep', $this->post_id );
		the_row();

		$this->assertFalse( get_row_layout() );

		reset_rows();
	}

	// =========================================================================
	// Group fields
	// =========================================================================

	/**
	 * Test have_rows treats a group field as a single row.
	 */
	public function test_have_rows_group_field_single_row() {
		update_field(
			'field_rows_grp',
			array(
				'first'  => 'first value',
				'second' => 'second value',
			),
			$this->post_id
		);
		acf_get_store( 'values' )->reset();

		$rows = array();
		while ( have_rows( 'rows_grp', $this->post_id ) ) {
			the_row();
			$rows[] = get_sub_field( 'first' ) . '|' . get_sub_field( 'second' );
		}

		$this->assertSame( array( 'first value|second value' ), $rows );
	}

	/**
	 * Test a group value is returned by get_field as a name-keyed array.
	 */
	public function test_get_field_group_returns_name_keyed_array() {
		update_field(
			'field_rows_grp',
			array(
				'first'  => 'one',
				'second' => 'two',
			),
			$this->post_id
		);
		acf_get_store( 'values' )->reset();

		$this->assertSame(
			array(
				'first'  => 'one',
				'second' => 'two',
			),
			get_field( 'rows_grp', $this->post_id )
		);
	}

	// =========================================================================
	// add_row() / update_row() / delete_row()
	// =========================================================================

	/**
	 * Test add_row appends rows and returns the new row count.
	 */
	public function test_add_row_appends_rows_and_returns_count() {
		$this->assertSame( 1, add_row( 'field_rows_rep', array( 'title' => 'first' ), $this->post_id ) );
		acf_get_store( 'values' )->reset();
		$this->assertSame( 2, add_row( 'field_rows_rep', array( 'title' => 'second' ), $this->post_id ) );

		acf_get_store( 'values' )->reset();
		$value = get_field( 'rows_rep', $this->post_id );

		$this->assertCount( 2, $value );
		$this->assertSame( 'first', $value[0]['title'] );
		$this->assertSame( 'second', $value[1]['title'] );
	}

	/**
	 * Test add_row accepts rows keyed by sub field keys.
	 */
	public function test_add_row_accepts_sub_field_keys() {
		add_row( 'field_rows_rep', array( 'field_rows_rep_title' => 'keyed row' ), $this->post_id );

		acf_get_store( 'values' )->reset();
		$value = get_field( 'rows_rep', $this->post_id );

		$this->assertSame( 'keyed row', $value[0]['title'] );
	}

	/**
	 * Test add_row returns false for an unknown field.
	 */
	public function test_add_row_returns_false_for_unknown_field() {
		$this->assertFalse( add_row( 'completely_unknown_repeater', array( 'title' => 'x' ), $this->post_id ) );
	}

	/**
	 * Test update_row replaces the row at a 1-based index.
	 */
	public function test_update_row_replaces_row_at_index() {
		add_row( 'field_rows_rep', array( 'title' => 'original 1' ), $this->post_id );
		acf_get_store( 'values' )->reset();
		add_row( 'field_rows_rep', array( 'title' => 'original 2' ), $this->post_id );
		acf_get_store( 'values' )->reset();

		$this->assertTrue( update_row( 'rows_rep', 2, array( 'title' => 'replaced 2' ), $this->post_id ) );

		acf_get_store( 'values' )->reset();
		$value = get_field( 'rows_rep', $this->post_id );

		$this->assertSame( 'original 1', $value[0]['title'] );
		$this->assertSame( 'replaced 2', $value[1]['title'] );
	}

	/**
	 * Test delete_row removes a row and re-indexes the remaining rows.
	 */
	public function test_delete_row_removes_and_reindexes_rows() {
		$this->save_nested_repeater_value();

		$this->assertTrue( delete_row( 'rows_rep', 1, $this->post_id ) );

		acf_get_store( 'values' )->reset();
		$value = get_field( 'rows_rep', $this->post_id );

		$this->assertCount( 1, $value );
		$this->assertSame( 'Row B', $value[0]['title'] );
	}

	/**
	 * Test delete_row returns false when the index does not exist.
	 */
	public function test_delete_row_returns_false_for_missing_index() {
		$this->save_nested_repeater_value();

		$this->assertFalse( delete_row( 'rows_rep', 99, $this->post_id ) );
	}

	/**
	 * Test delete_row returns false for an unknown field.
	 */
	public function test_delete_row_returns_false_for_unknown_field() {
		$this->assertFalse( delete_row( 'completely_unknown_repeater', 1, $this->post_id ) );
	}

	// =========================================================================
	// update_sub_field() / delete_sub_field()
	// =========================================================================

	/**
	 * Test update_sub_field with an ancestor selector array updates a nested value.
	 */
	public function test_update_sub_field_with_ancestor_selector() {
		$this->save_nested_repeater_value();

		$this->assertNotFalse( update_sub_field( array( 'rows_rep', 1, 'title' ), 'Row A Updated', $this->post_id ) );

		acf_get_store( 'values' )->reset();
		$value = get_field( 'rows_rep', $this->post_id );

		$this->assertSame( 'Row A Updated', $value[0]['title'] );
		$this->assertSame( 'Row B', $value[1]['title'] );
	}

	/**
	 * Test update_sub_field reaches into nested repeaters via a long ancestor selector.
	 */
	public function test_update_sub_field_with_nested_ancestor_selector() {
		$this->save_nested_repeater_value();

		$result = update_sub_field( array( 'rows_rep', 1, 'child_rep', 2, 'item' ), 'a2-updated', $this->post_id );
		$this->assertNotFalse( $result );

		acf_get_store( 'values' )->reset();
		$value = get_field( 'rows_rep', $this->post_id );

		$this->assertSame( 'a2-updated', $value[0]['child_rep'][1]['item'] );
	}

	/**
	 * Test update_sub_field by name inside an active have_rows loop.
	 */
	public function test_update_sub_field_inside_loop() {
		$this->save_nested_repeater_value();

		while ( have_rows( 'rows_rep', $this->post_id ) ) {
			the_row();
			if ( 1 === get_row_index() ) {
				$this->assertNotFalse( update_sub_field( 'title', 'Loop Updated', $this->post_id ) );
			}
		}

		acf_get_store( 'values' )->reset();
		$value = get_field( 'rows_rep', $this->post_id );

		$this->assertSame( 'Loop Updated', $value[0]['title'] );
	}

	/**
	 * Test update_sub_field returns false outside a loop with a plain selector.
	 */
	public function test_update_sub_field_returns_false_outside_loop() {
		$this->assertFalse( update_sub_field( 'title', 'nope', $this->post_id ) );
	}

	/**
	 * Test delete_sub_field removes the nested value.
	 */
	public function test_delete_sub_field_removes_value() {
		$this->save_nested_repeater_value();

		delete_sub_field( array( 'rows_rep', 1, 'title' ), $this->post_id );

		acf_get_store( 'values' )->reset();
		$value = get_field( 'rows_rep', $this->post_id );

		// The row remains but the deleted sub value is null.
		$this->assertCount( 2, $value );
		$this->assertNull( $value[0]['title'] );
		$this->assertSame( 'Row B', $value[1]['title'] );
	}

	// =========================================================================
	// add_sub_row() / update_sub_row() / delete_sub_row()
	// =========================================================================

	/**
	 * Test add_sub_row appends a row to a nested repeater.
	 */
	public function test_add_sub_row_appends_to_nested_repeater() {
		$this->save_nested_repeater_value();

		$count = add_sub_row( array( 'rows_rep', 1, 'child_rep' ), array( 'item' => 'a3' ), $this->post_id );
		$this->assertSame( 3, $count );

		acf_get_store( 'values' )->reset();
		$value = get_field( 'rows_rep', $this->post_id );

		$this->assertSame( array( 'a1', 'a2', 'a3' ), array_column( $value[0]['child_rep'], 'item' ) );
	}

	/**
	 * Test update_sub_row replaces a nested repeater row at a 1-based index.
	 */
	public function test_update_sub_row_replaces_nested_row() {
		$this->save_nested_repeater_value();

		$this->assertTrue( update_sub_row( array( 'rows_rep', 1, 'child_rep' ), 2, array( 'item' => 'a2-new' ), $this->post_id ) );

		acf_get_store( 'values' )->reset();
		$value = get_field( 'rows_rep', $this->post_id );

		$this->assertSame( array( 'a1', 'a2-new' ), array_column( $value[0]['child_rep'], 'item' ) );
	}

	/**
	 * Test delete_sub_row removes a nested repeater row.
	 */
	public function test_delete_sub_row_removes_nested_row() {
		$this->save_nested_repeater_value();

		$this->assertTrue( delete_sub_row( array( 'rows_rep', 1, 'child_rep' ), 1, $this->post_id ) );

		acf_get_store( 'values' )->reset();
		$value = get_field( 'rows_rep', $this->post_id );

		$this->assertSame( array( 'a2' ), array_column( $value[0]['child_rep'], 'item' ) );
	}

	/**
	 * Test delete_sub_row returns false for a missing index.
	 */
	public function test_delete_sub_row_returns_false_for_missing_index() {
		$this->save_nested_repeater_value();

		$this->assertFalse( delete_sub_row( array( 'rows_rep', 1, 'child_rep' ), 99, $this->post_id ) );
	}

	/**
	 * Test add_sub_row returns false for an invalid ancestor selector.
	 */
	public function test_add_sub_row_returns_false_for_invalid_selector() {
		$this->assertFalse( add_sub_row( array( 'rows_rep' ), array( 'item' => 'x' ), $this->post_id ) );
		$this->assertFalse( add_sub_row( array( 'unknown_field', 1, 'item' ), array( 'item' => 'x' ), $this->post_id ) );
	}

	// =========================================================================
	// Repeater values via get_field
	// =========================================================================

	/**
	 * Test a formatted repeater value is keyed by sub field names.
	 */
	public function test_get_field_repeater_formatted_uses_names() {
		$this->save_nested_repeater_value();

		$value = get_field( 'rows_rep', $this->post_id );

		$this->assertIsArray( $value );
		$this->assertArrayHasKey( 'title', $value[0] );
		$this->assertSame( 'Row A', $value[0]['title'] );
	}

	/**
	 * Test an unformatted repeater value is keyed by sub field keys.
	 */
	public function test_get_field_repeater_unformatted_uses_keys() {
		$this->save_nested_repeater_value();

		$value = get_field( 'rows_rep', $this->post_id, false );

		$this->assertIsArray( $value );
		$this->assertArrayHasKey( 'field_rows_rep_title', $value[0] );
		$this->assertSame( 'Row A', $value[0]['field_rows_rep_title'] );
	}

	/**
	 * Test repeater rows are stored as a row count plus per-row meta keys.
	 */
	public function test_repeater_storage_layout_in_meta() {
		$this->save_nested_repeater_value();

		// The repeater meta value is the row count (an integer under WorDBless,
		// a numeric string under a real database).
		$this->assertEquals( 2, get_post_meta( $this->post_id, 'rows_rep', true ) );
		$this->assertSame( 'Row A', get_post_meta( $this->post_id, 'rows_rep_0_title', true ) );
		$this->assertSame( 'Row B', get_post_meta( $this->post_id, 'rows_rep_1_title', true ) );
		$this->assertEquals( 2, get_post_meta( $this->post_id, 'rows_rep_0_child_rep', true ) );
		$this->assertSame( 'a2', get_post_meta( $this->post_id, 'rows_rep_0_child_rep_1_item', true ) );
	}

	/**
	 * Test row functions work against the options context.
	 */
	public function test_rows_round_trip_options_context() {
		update_field(
			'field_rows_rep',
			array( array( 'title' => 'Option Row' ) ),
			'options'
		);
		acf_get_store( 'values' )->reset();

		$titles = array();
		while ( have_rows( 'rows_rep', 'options' ) ) {
			the_row();
			$titles[] = get_sub_field( 'title' );
		}

		$this->assertSame( array( 'Option Row' ), $titles );

		$this->assertSame( 2, add_row( 'field_rows_rep', array( 'title' => 'Second Option Row' ), 'options' ) );
	}
}
