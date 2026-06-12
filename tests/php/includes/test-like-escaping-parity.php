<?php
/**
 * Regression tests for the remaining hand-built wp_options LIKE queries.
 *
 * Two legacy code paths build wp_options LIKE patterns from a taxonomy (and
 * term) and previously escaped only the `_` wildcard, leaving `%` and `\`
 * active. Both now run their dynamic parts through $wpdb->esc_like(). These
 * tests capture the generated SQL (the queries are no-ops under WorDBless)
 * and assert the dynamic part is escaped, comparing against patterns built
 * with the same $wpdb->prepare()/esc_like() primitives the source uses.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests that the surviving wp_options LIKE queries escape via esc_like().
 */
class Test_Like_Escaping_Parity extends BaseTestCase {

	/**
	 * Captured wp_options LIKE queries.
	 *
	 * @var array
	 */
	protected $captured = array();

	/**
	 * Start capturing queries.
	 */
	public function set_up() {
		parent::set_up();
		$this->captured = array();
		add_filter( 'wordbless_wpdb_query_results', array( $this, 'capture_query' ), 10, 2 );
	}

	/**
	 * Stop capturing queries.
	 */
	public function tear_down() {
		remove_filter( 'wordbless_wpdb_query_results', array( $this, 'capture_query' ), 10 );
		parent::tear_down();
	}

	/**
	 * Record any wp_options LIKE query.
	 *
	 * @param array  $results The (empty) results array.
	 * @param string $query   The SQL passed to wpdb::query().
	 * @return array
	 */
	public function capture_query( $results, $query ) {
		if ( false !== strpos( (string) $query, 'option_name LIKE' ) ) {
			$this->captured[] = (string) $query;
		}
		return $results;
	}

	/**
	 * The escaped fragment a correctly-escaped literal produces in the SQL.
	 *
	 * @param string $literal The literal portion of the LIKE pattern.
	 * @return string
	 */
	protected function expected_fragment( $literal ) {
		global $wpdb;
		return $wpdb->prepare( '%s', $wpdb->esc_like( $literal ) . '%' );
	}

	/**
	 * The most recent captured wp_options LIKE query.
	 *
	 * @return string
	 */
	protected function last_query() {
		return empty( $this->captured ) ? '' : end( $this->captured );
	}

	/**
	 * Ensures acf_upgrade_550_taxonomy() escapes the taxonomy in its SELECT.
	 */
	public function test_upgrade_550_taxonomy_escapes_like() {
		global $wpdb;

		// A taxonomy name carrying a % wildcard proves the escaping.
		acf_upgrade_550_taxonomy( 'ta%x' );

		$query = $this->last_query();
		$this->assertNotEmpty( $query, 'The upgrade SELECT should have been captured.' );
		$this->assertStringContainsString(
			$this->expected_fragment( 'ta%x_' ),
			$query,
			'The taxonomy should be escaped via esc_like().'
		);

		$bad = $wpdb->prepare( '%s', str_replace( '_', '\_', 'ta%x_%' ) );
		$this->assertStringNotContainsString(
			$bad,
			$query,
			'The unescaped (active-wildcard) pattern must not be generated.'
		);
	}

	/**
	 * Ensures acf_form_taxonomy::delete_term() escapes the taxonomy and term
	 * in its legacy (no-termmeta) DELETE.
	 */
	public function test_delete_term_escapes_like() {
		global $wpdb;

		// Force the legacy path: acf_isset_termmeta() returns false when the
		// stored db_version predates termmeta (WP < 4.4 / db_version 34370).
		update_option( 'db_version', 34000 );

		$form = new acf_form_taxonomy();
		$form->delete_term( 7, 0, 'ta%x', null );

		$query = $this->last_query();
		$this->assertNotEmpty( $query, 'The delete DELETE should have been captured.' );
		$this->assertStringContainsString(
			$this->expected_fragment( 'ta%x_7_' ),
			$query,
			'The taxonomy and term should be escaped via esc_like().'
		);

		$bad = $wpdb->prepare( '%s', str_replace( '_', '\_', 'ta%x_7_%' ) );
		$this->assertStringNotContainsString(
			$bad,
			$query,
			'The unescaped (active-wildcard) pattern must not be generated.'
		);
	}
}
