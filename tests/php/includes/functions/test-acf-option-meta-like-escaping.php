<?php
/**
 * Tests that acf_get_option_meta() escapes its LIKE patterns with esc_like().
 *
 * The function builds wp_options LIKE patterns from a caller supplied
 * prefix. The previous str_replace() approach escaped only `_`, leaving
 * other LIKE metacharacters in the prefix unescaped; these tests assert
 * the prefix is run through $wpdb->esc_like(), which covers `%`, `_` and
 * `\`, so the prefix matches only as a literal.
 *
 * The query itself is a no-op under WorDBless, so the generated SQL is
 * captured via the wordbless_wpdb_query_results filter and the LIKE
 * fragments are compared against patterns built with the same
 * $wpdb->prepare()/esc_like() primitives the function uses (avoiding any
 * dependency on prepare()'s internal escaping representation).
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests that acf_get_option_meta() escapes LIKE metacharacters in its prefix.
 */
class Test_ACF_Option_Meta_Like_Escaping extends BaseTestCase {

	/**
	 * The most recent SQL string passed to $wpdb->query().
	 *
	 * @var string
	 */
	protected $captured_sql = '';

	/**
	 * Capture every query the dbless wpdb would run.
	 */
	public function set_up() {
		parent::set_up();
		$this->captured_sql = '';
		add_filter( 'wordbless_wpdb_query_results', array( $this, 'capture_query' ), 10, 2 );
	}

	/**
	 * Remove the capture filter.
	 */
	public function tear_down() {
		remove_filter( 'wordbless_wpdb_query_results', array( $this, 'capture_query' ), 10 );
		parent::tear_down();
	}

	/**
	 * Filter callback: record the SQL and return an empty result set.
	 *
	 * @param array  $results The (empty) results array.
	 * @param string $query   The SQL passed to wpdb::query().
	 * @return array
	 */
	public function capture_query( $results, $query ) {
		// Only retain the acf_get_option_meta lookup, not unrelated queries.
		if ( false !== strpos( (string) $query, 'option_name LIKE' ) ) {
			$this->captured_sql = (string) $query;
		}
		return $results;
	}

	/**
	 * The escaped fragment a correctly-escaped prefix produces in the SQL.
	 *
	 * @param string $literal The literal portion of the LIKE pattern.
	 * @return string
	 */
	protected function expected_like_fragment( $literal ) {
		global $wpdb;
		// Mirror acf_get_option_meta(): esc_like() the literal, append the
		// single intended trailing wildcard, then quote it as prepare() does.
		return $wpdb->prepare( '%s', $wpdb->esc_like( $literal ) . '%' );
	}

	/**
	 * A prefix containing the % wildcard must be escaped, not left active.
	 */
	public function test_percent_prefix_is_escaped() {
		global $wpdb;

		acf_get_option_meta( '%' );

		$this->assertNotEmpty( $this->captured_sql, 'The option-meta query should have been captured.' );

		// The fixed function escapes the whole literal "%_" then appends "%".
		$good = $this->expected_like_fragment( '%_' );
		$this->assertStringContainsString(
			$good,
			$this->captured_sql,
			'The prefix should be escaped via esc_like().'
		);

		// The previous behaviour escaped only "_", leaving the leading %
		// active. That exact fragment must no longer appear.
		$bad = $wpdb->prepare( '%s', str_replace( '_', '\_', '%_%' ) );
		$this->assertStringNotContainsString(
			$bad,
			$this->captured_sql,
			'The unescaped (leading-wildcard) pattern must not be generated.'
		);
	}

	/**
	 * A prefix containing an _ wildcard must be escaped as a literal.
	 */
	public function test_underscore_prefix_is_escaped() {
		acf_get_option_meta( 'a_b' );

		$good = $this->expected_like_fragment( 'a_b_' );
		$this->assertStringContainsString(
			$good,
			$this->captured_sql,
			'Underscores in the prefix should be escaped as literals.'
		);
	}

	/**
	 * A prefix containing a backslash must be escaped.
	 */
	public function test_backslash_prefix_is_escaped() {
		global $wpdb;

		// The prefix is the three characters a \ b.
		acf_get_option_meta( 'a\\b' );

		$good = $this->expected_like_fragment( 'a\\b_' );
		$this->assertStringContainsString(
			$good,
			$this->captured_sql,
			'A backslash in the prefix should be escaped via esc_like().'
		);

		// The previous str_replace() approach escaped only `_`, leaving the
		// backslash unescaped. That exact fragment must not appear.
		$bad = $wpdb->prepare( '%s', str_replace( '_', '\_', 'a\\b_%' ) );
		$this->assertStringNotContainsString(
			$bad,
			$this->captured_sql,
			'The pattern with an unescaped backslash must not be generated.'
		);
	}

	/**
	 * A benign prefix still produces the expected (unchanged) pattern.
	 */
	public function test_benign_prefix_unchanged() {
		acf_get_option_meta( 'options' );

		$good = $this->expected_like_fragment( 'options_' );
		$this->assertStringContainsString(
			$good,
			$this->captured_sql,
			'A benign prefix should match option names beginning with it.'
		);
	}
}
