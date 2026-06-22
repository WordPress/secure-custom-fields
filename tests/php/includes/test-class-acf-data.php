<?php
/**
 * Tests for the ACF_Data store class.
 *
 * Covers includes/class-acf-data.php along with the store and instance
 * helpers in includes/acf-utility-functions.php.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

require_once __DIR__ . '/class-scf-test-dummy-instance.php';

/**
 * Test the ACF_Data class.
 */
class Test_Class_ACF_Data extends BaseTestCase {

	/**
	 * Clean up test data.
	 */
	public function tearDown(): void {
		// Remove anything the tests registered in the global stores.
		unset( $GLOBALS['acf_stores']['scf-test-store'] );
		unset( $GLOBALS['acf_instances']['SCF_Test_Dummy_Instance'] );
		parent::tearDown();
	}

	// =========================================================================
	// Construction.
	// =========================================================================

	/**
	 * Test constructing without data.
	 */
	public function test_constructor_without_data() {
		$store = new ACF_Data();

		$this->assertSame( array(), $store->get_data() );
		$this->assertSame( 0, $store->count() );
	}

	/**
	 * Test constructing with initial data.
	 */
	public function test_constructor_with_data() {
		$store = new ACF_Data(
			array(
				'one' => 1,
				'two' => 2,
			)
		);

		$this->assertSame( 1, $store->get( 'one' ) );
		$this->assertSame( 2, $store->get( 'two' ) );
		$this->assertSame( 2, $store->count() );
	}

	/**
	 * Test that every instance gets a unique cid.
	 */
	public function test_cid_is_unique() {
		$store_a = new ACF_Data();
		$store_b = new ACF_Data();

		$this->assertNotEmpty( $store_a->cid );
		$this->assertNotEmpty( $store_b->cid );
		$this->assertNotSame( $store_a->cid, $store_b->cid );
		$this->assertStringStartsWith( 'acf-', $store_a->cid );
	}

	// =========================================================================
	// prop()
	// =========================================================================

	/**
	 * Test that prop() sets a property and chains.
	 */
	public function test_prop_sets_property_and_chains() {
		$store = new ACF_Data();

		$result = $store->prop( 'multisite', true );

		$this->assertSame( $store, $result );
		$this->assertTrue( $store->multisite );

		// Arbitrary (dynamic) property names are supported too.
		$store->prop( 'cid', 'custom-cid' );
		$this->assertSame( 'custom-cid', $store->cid );
	}

	// =========================================================================
	// set() / get() / get_data()
	// =========================================================================

	/**
	 * Test setting and getting a single value.
	 */
	public function test_set_get_single() {
		$store = new ACF_Data();

		$result = $store->set( 'key1', 'value1' );

		$this->assertSame( $store, $result );
		$this->assertSame( 'value1', $store->get( 'key1' ) );

		// Overwriting works.
		$store->set( 'key1', 'value2' );
		$this->assertSame( 'value2', $store->get( 'key1' ) );
	}

	/**
	 * Test that get() returns null for unknown names.
	 */
	public function test_get_unknown_returns_null() {
		$store = new ACF_Data();

		$this->assertNull( $store->get( 'missing' ) );
	}

	/**
	 * Test setting multiple values merges them into existing data.
	 */
	public function test_set_multiple_merges() {
		$store = new ACF_Data( array( 'existing' => 'kept' ) );

		$store->set(
			array(
				'new_one' => 1,
				'new_two' => 2,
			)
		);

		$this->assertSame(
			array(
				'existing' => 'kept',
				'new_one'  => 1,
				'new_two'  => 2,
			),
			$store->get_data()
		);

		// Merging overwrites existing keys.
		$store->set( array( 'existing' => 'replaced' ) );
		$this->assertSame( 'replaced', $store->get( 'existing' ) );
	}

	/**
	 * Test that get( false ) and get_data() both return all data.
	 */
	public function test_get_all_data() {
		$data  = array(
			'a' => 1,
			'b' => 2,
		);
		$store = new ACF_Data( $data );

		$this->assertSame( $data, $store->get( false ) );
		$this->assertSame( $data, $store->get_data() );
	}

	// =========================================================================
	// has() / is() / aliases
	// =========================================================================

	/**
	 * Test has(), is() and get() with aliases.
	 */
	public function test_aliases() {
		$store = new ACF_Data();
		$store->set( 'original', 'value' );

		$result = $store->alias( 'original', 'alias_one', 'alias_two' );
		$this->assertSame( $store, $result );

		// _key() resolves aliases to the canonical name.
		$this->assertSame( 'original', $store->_key( 'alias_one' ) );
		$this->assertSame( 'original', $store->_key( 'alias_two' ) );
		$this->assertSame( 'unaliased', $store->_key( 'unaliased' ) );

		// has() resolves aliases, is() does not.
		$this->assertTrue( $store->has( 'original' ) );
		$this->assertTrue( $store->has( 'alias_one' ) );
		$this->assertTrue( $store->has( 'alias_two' ) );
		$this->assertFalse( $store->is( 'alias_one' ) );
		$this->assertTrue( $store->is( 'original' ) );

		// get() resolves aliases.
		$this->assertSame( 'value', $store->get( 'alias_one' ) );
		$this->assertSame( 'value', $store->get( 'alias_two' ) );
	}

	/**
	 * Test that has() uses isset() semantics for null values.
	 */
	public function test_has_with_null_value() {
		$store = new ACF_Data();
		$store->set( 'null_key', null );

		// NOTE: documents current behavior — has() uses isset(), so a key
		// explicitly stored with a null value is reported as missing.
		$this->assertFalse( $store->has( 'null_key' ) );
		$this->assertFalse( $store->is( 'null_key' ) );
		$this->assertArrayHasKey( 'null_key', $store->get_data() );
	}

	// =========================================================================
	// append() / remove() / reset() / count()
	// =========================================================================

	/**
	 * Test appending values.
	 */
	public function test_append() {
		$store = new ACF_Data();

		$result = $store->append( 'first' )->append( 'second' );

		$this->assertSame( $store, $result );
		$this->assertSame( array( 'first', 'second' ), $store->get_data() );
		$this->assertSame( 2, $store->count() );
	}

	/**
	 * Test removing values.
	 */
	public function test_remove() {
		$store = new ACF_Data(
			array(
				'keep'   => 1,
				'remove' => 2,
			)
		);

		$result = $store->remove( 'remove' );

		$this->assertSame( $store, $result );
		$this->assertFalse( $store->has( 'remove' ) );
		$this->assertTrue( $store->has( 'keep' ) );

		// Removing an unknown key is a no-op.
		$store->remove( 'missing' );
		$this->assertSame( 1, $store->count() );
	}

	/**
	 * Test that remove() does not resolve aliases.
	 */
	public function test_remove_does_not_resolve_aliases() {
		$store = new ACF_Data( array( 'original' => 'value' ) );
		$store->alias( 'original', 'alias_one' );

		// NOTE: documents current behavior — unlike get()/has(), remove()
		// operates on the raw key, so removing by alias leaves the data.
		$store->remove( 'alias_one' );
		$this->assertTrue( $store->has( 'original' ) );

		$store->remove( 'original' );
		$this->assertFalse( $store->has( 'original' ) );
	}

	/**
	 * Test that reset() clears data and aliases.
	 */
	public function test_reset() {
		$store = new ACF_Data( array( 'key1' => 'value1' ) );
		$store->alias( 'key1', 'alias_one' );

		$store->reset();

		$this->assertSame( array(), $store->get_data() );
		$this->assertSame( 0, $store->count() );

		// Aliases are cleared too.
		$this->assertSame( 'alias_one', $store->_key( 'alias_one' ) );
	}

	/**
	 * Test count().
	 */
	public function test_count() {
		$store = new ACF_Data();
		$this->assertSame( 0, $store->count() );

		$store->set( 'a', 1 )->set( 'b', 2 )->set( 'c', 3 );
		$this->assertSame( 3, $store->count() );

		$store->remove( 'a' );
		$this->assertSame( 2, $store->count() );
	}

	// =========================================================================
	// query()
	// =========================================================================

	/**
	 * Test query() filtering with AND and OR operators.
	 */
	public function test_query() {
		$store = new ACF_Data(
			array(
				'item1' => array(
					'type'   => 'text',
					'parent' => 10,
				),
				'item2' => array(
					'type'   => 'text',
					'parent' => 20,
				),
				'item3' => array(
					'type'   => 'number',
					'parent' => 10,
				),
			)
		);

		// Single condition.
		$results = $store->query( array( 'type' => 'text' ) );
		$this->assertSame( array( 'item1', 'item2' ), array_keys( $results ) );

		// AND requires all conditions to match.
		$results = $store->query(
			array(
				'type'   => 'text',
				'parent' => 10,
			)
		);
		$this->assertSame( array( 'item1' ), array_keys( $results ) );

		// OR matches any condition.
		$results = $store->query(
			array(
				'type'   => 'number',
				'parent' => 20,
			),
			'OR'
		);
		$this->assertSame( array( 'item2', 'item3' ), array_keys( $results ) );

		// No matches returns an empty array.
		$this->assertSame( array(), $store->query( array( 'type' => 'missing' ) ) );
	}

	// =========================================================================
	// switch_site()
	// =========================================================================

	/**
	 * Test that switch_site() is a no-op for non-multisite stores.
	 */
	public function test_switch_site_not_multisite() {
		$store = new ACF_Data( array( 'key1' => 'value1' ) );

		$store->switch_site( 2, 1 );

		$this->assertSame( 'value1', $store->get( 'key1' ) );
		$this->assertSame( array(), $store->site_data );
	}

	/**
	 * Test that switch_site() is a no-op when the site does not change.
	 */
	public function test_switch_site_same_site() {
		$store = new ACF_Data( array( 'key1' => 'value1' ) );
		$store->prop( 'multisite', true );

		$store->switch_site( 1, 1 );

		$this->assertSame( 'value1', $store->get( 'key1' ) );
		$this->assertSame( array(), $store->site_data );
	}

	/**
	 * Test that switch_site() saves and restores per-site data and aliases.
	 */
	public function test_switch_site_saves_and_restores_data() {
		$store = new ACF_Data();
		$store->prop( 'multisite', true );

		// Populate data for site 1.
		$store->set( 'site_one_key', 'site_one_value' );
		$store->alias( 'site_one_key', 'site_one_alias' );

		// Switch to site 2: data is stashed and the store is emptied.
		$store->switch_site( 2, 1 );
		$this->assertFalse( $store->has( 'site_one_key' ) );
		$this->assertSame( 0, $store->count() );
		$this->assertSame( 'site_one_alias', $store->_key( 'site_one_alias' ) );

		// Populate data for site 2.
		$store->set( 'site_two_key', 'site_two_value' );

		// Switch back to site 1: original data and aliases are restored.
		$store->switch_site( 1, 2 );
		$this->assertSame( 'site_one_value', $store->get( 'site_one_key' ) );
		$this->assertSame( 'site_one_value', $store->get( 'site_one_alias' ) );
		$this->assertFalse( $store->has( 'site_two_key' ) );

		// And switching to site 2 again restores its data.
		$store->switch_site( 2, 1 );
		$this->assertSame( 'site_two_value', $store->get( 'site_two_key' ) );
		$this->assertFalse( $store->has( 'site_one_key' ) );
	}

	// =========================================================================
	// Store helper functions.
	// =========================================================================

	/**
	 * Test registering and retrieving a store.
	 */
	public function test_register_and_get_store() {
		$store = acf_register_store( 'scf-test-store', array( 'seed' => 'data' ) );

		$this->assertInstanceOf( 'ACF_Data', $store );
		$this->assertSame( 'data', $store->get( 'seed' ) );

		// acf_get_store() returns the same instance.
		$this->assertSame( $store, acf_get_store( 'scf-test-store' ) );

		// Unknown stores return false.
		$this->assertFalse( acf_get_store( 'scf-missing-store' ) );
	}

	/**
	 * Test that acf_switch_stores() only switches multisite stores.
	 */
	public function test_acf_switch_stores() {
		$multisite_store = acf_register_store( 'scf-test-store' )->prop( 'multisite', true );
		$multisite_store->set( 'ms_key', 'ms_value' );

		try {
			acf_switch_stores( 2, 1 );

			// The multisite store was emptied for the new site.
			$this->assertFalse( $multisite_store->has( 'ms_key' ) );

			// Non-multisite stores are untouched.
			$locations = acf_get_store( 'acf-meta-locations' );
			$this->assertTrue( $locations->has( 'post' ) );
		} finally {
			// Switch all stores back to restore global state.
			acf_switch_stores( 1, 2 );
		}

		$this->assertSame( 'ms_value', $multisite_store->get( 'ms_key' ) );
	}

	// =========================================================================
	// Instance helper functions.
	// =========================================================================

	/**
	 * Test acf_new_instance() and acf_get_instance().
	 */
	public function test_instance_functions() {
		$first = acf_new_instance( 'SCF_Test_Dummy_Instance' );
		$this->assertInstanceOf( 'SCF_Test_Dummy_Instance', $first );

		// acf_get_instance() returns the stored instance.
		$this->assertSame( $first, acf_get_instance( 'SCF_Test_Dummy_Instance' ) );
		$this->assertSame( $first, acf_get_instance( 'SCF_Test_Dummy_Instance' ) );

		// acf_new_instance() replaces the stored instance.
		$second = acf_new_instance( 'SCF_Test_Dummy_Instance' );
		$this->assertNotSame( $first, $second );
		$this->assertSame( $second, acf_get_instance( 'SCF_Test_Dummy_Instance' ) );
	}

	/**
	 * Test that acf_get_instance() lazily instantiates unknown classes.
	 */
	public function test_get_instance_creates_when_missing() {
		unset( $GLOBALS['acf_instances']['SCF_Test_Dummy_Instance'] );

		$instance = acf_get_instance( 'SCF_Test_Dummy_Instance' );

		$this->assertInstanceOf( 'SCF_Test_Dummy_Instance', $instance );
		$this->assertSame( $instance, acf_get_instance( 'SCF_Test_Dummy_Instance' ) );
	}
}
