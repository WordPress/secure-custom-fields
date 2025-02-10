<?php
/**
 * Test Secure Custom Fields main functionality
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Class Test_Secure_Custom_Fields
 */
class Test_Secure_Custom_Fields extends BaseTestCase {

	/**
	 * Test if ACF class exists and is loaded
	 */
	public function test_acf_class_exists() {
		$this->assertTrue( class_exists( 'ACF' ), 'ACF class should exist' );
	}

	/**
	 * Test if ACF instance is available
	 */
	public function test_acf_instance_available() {
		$acf = acf();
		$this->assertInstanceOf( 'ACF', $acf, 'ACF instance should be available via acf() function' );
	}

	/**
	 * Test if ACF version is defined
	 */
	public function test_acf_version_defined() {
		$acf = acf();
		$this->assertNotEmpty( $acf->version, 'ACF version should be defined' );
	}
}
