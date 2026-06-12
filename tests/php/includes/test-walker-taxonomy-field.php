<?php
/**
 * Tests for the ACF_Taxonomy_Field_Walker class.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// Load the walker class (normally loaded on demand by the taxonomy field).
acf_include( 'includes/walkers/class-acf-walker-taxonomy-field.php' );

/**
 * Class Test_ACF_Walker_Taxonomy_Field
 *
 * Tests the HTML produced by the taxonomy field walker for hierarchical
 * term checklists (checkbox/radio inputs, selection state and nesting).
 *
 * @group walkers
 */
class Test_ACF_Walker_Taxonomy_Field extends BaseTestCase {

	/**
	 * Builds a taxonomy field array for the walker.
	 *
	 * @param array $overrides Field overrides.
	 * @return array
	 */
	private function get_field( array $overrides = array() ): array {
		return array_merge(
			array(
				'value'      => array(),
				'field_type' => 'checkbox',
				'name'       => 'acf[field_test_walker_tax][]',
			),
			$overrides
		);
	}

	/**
	 * Builds a term-like object for the walker.
	 *
	 * @param int    $term_id   The term ID.
	 * @param string $name      The term name.
	 * @param int    $parent_id The parent term ID.
	 * @return stdClass
	 */
	private function make_term( int $term_id, string $name, int $parent_id = 0 ): stdClass {
		return (object) array(
			'term_id' => $term_id,
			'name'    => $name,
			'parent'  => $parent_id,
		);
	}

	/**
	 * Test that start_el renders an unchecked checkbox item.
	 */
	public function test_start_el_renders_unchecked_checkbox() {
		$walker = new ACF_Taxonomy_Field_Walker( $this->get_field() );

		$output = '';
		$walker->start_el( $output, $this->make_term( 10, 'News' ) );

		$this->assertStringContainsString( '<li data-id="10">', $output );
		$this->assertStringContainsString( 'type="checkbox"', $output );
		$this->assertStringContainsString( 'name="acf[field_test_walker_tax][]"', $output );
		$this->assertStringContainsString( 'value="10"', $output );
		$this->assertStringContainsString( '<span>News</span>', $output );
		$this->assertStringNotContainsString( 'checked', $output );
		$this->assertStringNotContainsString( 'class="selected"', $output );
	}

	/**
	 * Test that start_el marks selected terms as checked.
	 */
	public function test_start_el_renders_checked_selected_term() {
		$walker = new ACF_Taxonomy_Field_Walker( $this->get_field( array( 'value' => array( 10 ) ) ) );

		$output = '';
		$walker->start_el( $output, $this->make_term( 10, 'News' ) );

		$this->assertStringContainsString( '<label class="selected">', $output );
		$this->assertStringContainsString( 'checked="1"', $output );
	}

	/**
	 * Test that the field_type controls the input type.
	 */
	public function test_start_el_renders_radio_input_type() {
		$walker = new ACF_Taxonomy_Field_Walker( $this->get_field( array( 'field_type' => 'radio' ) ) );

		$output = '';
		$walker->start_el( $output, $this->make_term( 10, 'News' ) );

		$this->assertStringContainsString( 'type="radio"', $output );
	}

	/**
	 * Test that term names are sanitized through acf_esc_html.
	 */
	public function test_start_el_escapes_term_name() {
		$walker = new ACF_Taxonomy_Field_Walker( $this->get_field() );

		$output = '';
		$walker->start_el( $output, $this->make_term( 10, 'News <script>alert(1)</script>' ) );

		$this->assertStringNotContainsString( '<script>', $output, 'Script tags should be stripped from term names' );
	}

	/**
	 * Test that end_el closes the list item.
	 */
	public function test_end_el_closes_list_item() {
		$walker = new ACF_Taxonomy_Field_Walker( $this->get_field() );

		$output = '';
		$walker->end_el( $output, $this->make_term( 10, 'News' ) );

		$this->assertSame( "</li>\n", $output );
	}

	/**
	 * Test that start_lvl and end_lvl wrap children in an indented list.
	 */
	public function test_lvl_methods_render_children_list() {
		$walker = new ACF_Taxonomy_Field_Walker( $this->get_field() );

		$output = '';
		$walker->start_lvl( $output, 1 );
		$walker->end_lvl( $output, 1 );

		$this->assertSame( "\t<ul class='children acf-bl'>\n\t</ul>\n", $output );
	}

	/**
	 * Test that walk() nests child terms beneath their parents.
	 */
	public function test_walk_nests_hierarchical_terms() {
		$walker = new ACF_Taxonomy_Field_Walker( $this->get_field( array( 'value' => array( 20 ) ) ) );

		$terms = array(
			$this->make_term( 10, 'Parent Term' ),
			$this->make_term( 20, 'Child Term', 10 ),
			$this->make_term( 30, 'Other Root Term' ),
		);

		$html = $walker->walk( $terms, 0, array() );

		// All three terms render.
		$this->assertStringContainsString( '<li data-id="10">', $html );
		$this->assertStringContainsString( '<li data-id="20">', $html );
		$this->assertStringContainsString( '<li data-id="30">', $html );

		// The child is nested inside a children list within the parent item.
		$parent_pos   = strpos( $html, '<li data-id="10">' );
		$children_pos = strpos( $html, "<ul class='children acf-bl'>" );
		$child_pos    = strpos( $html, '<li data-id="20">' );
		$close_pos    = strpos( $html, '</ul>' );

		$this->assertNotFalse( $children_pos );
		$this->assertGreaterThan( $parent_pos, $children_pos, 'Children list should open after the parent item' );
		$this->assertGreaterThan( $children_pos, $child_pos, 'Child item should be inside the children list' );
		$this->assertGreaterThan( $child_pos, $close_pos, 'Children list should close after the child item' );

		// Only the selected child is checked.
		$this->assertSame( 1, substr_count( $html, 'checked="1"' ) );
		$this->assertSame( 1, substr_count( $html, 'class="selected"' ) );
	}

	/**
	 * Test that walk() limited to depth keeps all terms but flattens output.
	 */
	public function test_walk_with_max_depth_minus_one_flattens_terms() {
		$walker = new ACF_Taxonomy_Field_Walker( $this->get_field() );

		$terms = array(
			$this->make_term( 10, 'Parent Term' ),
			$this->make_term( 20, 'Child Term', 10 ),
		);

		$html = $walker->walk( $terms, -1, array() );

		$this->assertStringContainsString( '<li data-id="10">', $html );
		$this->assertStringContainsString( '<li data-id="20">', $html );
		$this->assertStringNotContainsString( "<ul class='children acf-bl'>", $html, 'Flat walks should not nest children' );
	}
}
