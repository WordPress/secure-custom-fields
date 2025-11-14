/**
 * WordPress dependencies
 */
const { test, expect } = require( './fixtures' );

test.describe( 'REST API Types Endpoint', () => {
	const PLUGIN_SLUG = 'secure-custom-fields';
	const SCF_TEST_POST_TYPE = 'scf-e2e-test-type';
	const OTHER_TEST_POST_TYPE = 'other-e2e-test-type';
	const TEST_POST_TYPE_SLUG = SCF_TEST_POST_TYPE; // Define for backwards compatibility with existing tests

	test.beforeAll( async ( { requestUtils } ) => {
		// Make sure the SCF plugin is active
		await requestUtils.activatePlugin( PLUGIN_SLUG );

		// Activate our test helper plugin that registers post types for testing
		await requestUtils.activatePlugin( 'scf-test-setup-post-types' );

		// Wait a moment for the post types to be registered
		await new Promise( ( resolve ) => setTimeout( resolve, 2000 ) );
	} );

	// Basic tests that don't require creating a post type

	test( 'should return types with correct structure', async ( {
		requestUtils,
	} ) => {
		try {
			const types = await requestUtils.rest( {
				path: '/wp/v2/types',
			} );

			// At minimum, should include these types
			expect( types ).toHaveProperty( 'post' );
			expect( types ).toHaveProperty( 'page' );

			// Verify structure of a post type object
			expect( types.post ).toHaveProperty( 'name' );
			expect( types.post ).toHaveProperty( 'slug' );
			expect( types.post ).toHaveProperty( 'rest_base' );
		} catch ( error ) {
			throw error;
		}
	} );

	test( 'should support source parameter with core value', async ( {
		requestUtils,
	} ) => {
		try {
			const types = await requestUtils.rest( {
				path: '/wp/v2/types',
				params: { source: 'core' },
			} );

			// Should at least have post and page
			expect( types ).toHaveProperty( 'post' );
			expect( types ).toHaveProperty( 'page' );

			// Core post types shouldn't have _source=scf
			for ( const key in types ) {
				if ( types[ key ]._source ) {
					expect( types[ key ]._source ).not.toBe( 'scf' );
				}
			}
		} catch ( error ) {
			throw error;
		}
	} );

	test( 'should support source parameter with scf value', async ( {
		requestUtils,
	} ) => {
		try {
			// Get all source collections for comparison
			const allTypes = await requestUtils.rest( {
				path: '/wp/v2/types',
			} );

			const scfTypes = await requestUtils.rest( {
				path: '/wp/v2/types',
				params: { source: 'scf' },
			} );

			const coreTypes = await requestUtils.rest( {
				path: '/wp/v2/types',
				params: { source: 'core' },
			} );

			const otherTypes = await requestUtils.rest( {
				path: '/wp/v2/types',
				params: { source: 'other' },
			} );

			// Each post type should only be in one source collection
			const allPostTypes = Object.keys( allTypes );
			for ( const postType of allPostTypes ) {
				// Count how many source collections contain this post type
				let sourceCount = 0;
				if ( postType in coreTypes ) sourceCount++;
				if ( postType in scfTypes ) sourceCount++;
				if ( postType in otherTypes ) sourceCount++;

				// Should be in exactly one source collection
				expect( sourceCount ).toBe( 1 );
			}
		} catch ( error ) {
			throw error;
		}
	} );

	test( 'should filter requests for single post types by source', async ( {
		requestUtils,
	} ) => {
		// Test that core post type with core source should succeed
		const postWithCore = await requestUtils.rest( {
			path: '/wp/v2/types/post',
			params: { source: 'core' },
		} );
		expect( postWithCore ).toHaveProperty( 'slug', 'post' );

		// Test that core post type with scf source should fail
		try {
			await requestUtils.rest( {
				path: '/wp/v2/types/post',
				params: { source: 'scf' },
			} );
			// Should not reach here
			throw new Error(
				'Core post type should not be available with SCF source'
			);
		} catch ( error ) {
			// Should fail with a 404 error
			expect( error.data ).toHaveProperty( 'status', 404 );
		}

		// We look for the test post type which should be categorized as SCF
		const customTestType = SCF_TEST_POST_TYPE; // 'scf-e2e-test-type'

		// Get all types and verify our test type exists
		const allTypes = await requestUtils.rest( {
			path: '/wp/v2/types',
		} );

		// Test post type should exist - fail fast if it doesn't
		expect( allTypes ).toHaveProperty( customTestType );

		// Try to access it with the SCF source parameter
		const typeWithScfSource = await requestUtils.rest( {
			path: `/wp/v2/types/${ customTestType }`,
			params: { source: 'scf' },
		} );

		// Should succeed if our test post type is properly registered with SCF
		expect( typeWithScfSource ).toHaveProperty( 'slug', customTestType );
	} );

	test( 'should validate source parameter values', async ( {
		requestUtils,
	} ) => {
		// Test with an invalid source parameter
		try {
			await requestUtils.rest( {
				path: '/wp/v2/types',
				params: { source: 'invalid' },
			} );
			// Should not reach here
			throw new Error( 'Should have failed with invalid parameter' );
		} catch ( error ) {
			// Should be a REST API error
			expect( error ).toHaveProperty( 'code', 'rest_invalid_param' );
		}
	} );

	// Tests for SCF post types
	test.describe( 'With SCF post type', () => {
		// Test that post types are correctly identified by their source
		test( 'should correctly categorize post types by their source', async ( {
			requestUtils,
		} ) => {
			// Get all post types
			const allTypes = await requestUtils.rest( {
				path: '/wp/v2/types',
			} );

			// Get types with source=scf
			const scfTypes = await requestUtils.rest( {
				path: '/wp/v2/types',
				params: { source: 'scf' },
			} );

			// Get types with source=core
			const coreTypes = await requestUtils.rest( {
				path: '/wp/v2/types',
				params: { source: 'core' },
			} );

			// Get types with source=other
			const otherTypes = await requestUtils.rest( {
				path: '/wp/v2/types',
				params: { source: 'other' },
			} );

			// Verify core post types are in the core source
			expect( coreTypes ).toHaveProperty( 'post' );
			expect( coreTypes ).toHaveProperty( 'page' );

			// Test post types should exist - fail fast if they don't
			expect( allTypes ).toHaveProperty( SCF_TEST_POST_TYPE );
			expect( allTypes ).toHaveProperty( OTHER_TEST_POST_TYPE );

			// Test that our other test post type is in Other source
			expect( otherTypes ).toHaveProperty( OTHER_TEST_POST_TYPE );
			expect( scfTypes ).not.toHaveProperty( OTHER_TEST_POST_TYPE );
			expect( coreTypes ).not.toHaveProperty( OTHER_TEST_POST_TYPE );

			// Test that SCF post type is in SCF source
			expect( scfTypes ).toHaveProperty( SCF_TEST_POST_TYPE );
			expect( coreTypes ).not.toHaveProperty( SCF_TEST_POST_TYPE );
			expect( otherTypes ).not.toHaveProperty( SCF_TEST_POST_TYPE );

			// Verify post type properties
			const scfPostTypeInfo = allTypes[ SCF_TEST_POST_TYPE ];
			expect( scfPostTypeInfo ).toHaveProperty( 'name' );
			expect( scfPostTypeInfo ).toHaveProperty( 'slug' );
			expect( scfPostTypeInfo ).toHaveProperty( 'rest_base' );
			expect( scfPostTypeInfo.slug ).toBe( SCF_TEST_POST_TYPE );

			const otherPostTypeInfo = allTypes[ OTHER_TEST_POST_TYPE ];
			expect( otherPostTypeInfo ).toHaveProperty( 'name' );
			expect( otherPostTypeInfo ).toHaveProperty( 'slug' );
			expect( otherPostTypeInfo ).toHaveProperty( 'rest_base' );
			expect( otherPostTypeInfo.slug ).toBe( OTHER_TEST_POST_TYPE );
		} );

		// Instead, test that the source parameter works for filtering
		test( 'should filter post types by source parameter', async ( {
			requestUtils,
		} ) => {
			// Get all post types
			const allTypes = await requestUtils.rest( {
				path: '/wp/v2/types',
			} );

			// Get each source type
			const scfTypes = await requestUtils.rest( {
				path: '/wp/v2/types',
				params: { source: 'scf' },
			} );

			const coreTypes = await requestUtils.rest( {
				path: '/wp/v2/types',
				params: { source: 'core' },
			} );

			const otherTypes = await requestUtils.rest( {
				path: '/wp/v2/types',
				params: { source: 'other' },
			} );

			// Test that core source should include post and page
			expect( coreTypes ).toHaveProperty( 'post' );
			expect( coreTypes ).toHaveProperty( 'page' );

			// Test that core types should not be in the other sources
			expect( scfTypes ).not.toHaveProperty( 'post' );
			expect( scfTypes ).not.toHaveProperty( 'page' );
			expect( otherTypes ).not.toHaveProperty( 'post' );
			expect( otherTypes ).not.toHaveProperty( 'page' );

			// Test that our custom post type should appear in only one source collection
			const customTestType = TEST_POST_TYPE_SLUG; // 'scf-e2e-test-type'

			// Test post type should exist - fail fast if it doesn't
			expect( allTypes ).toHaveProperty( customTestType );

			// Find where our test post type is categorized
			const inScf = customTestType in scfTypes;
			const inCore = customTestType in coreTypes;
			const inOther = customTestType in otherTypes;

			// Test that it only appears in one source
			const sourceCount =
				( inScf ? 1 : 0 ) + ( inCore ? 1 : 0 ) + ( inOther ? 1 : 0 );
			expect( sourceCount ).toBe( 1 );

			// It should never be in core source
			expect( inCore ).toBe( false );

			// It should be in either SCF (preferred) or other source
			expect( inScf || inOther ).toBe( true );

			// Test that each post type only appears in ONE source collection
			const allPostTypes = Object.keys( allTypes );
			for ( const postType of allPostTypes ) {
				// Count how many source collections contain this post type
				let sourceCount = 0;
				if ( postType in coreTypes ) sourceCount++;
				if ( postType in scfTypes ) sourceCount++;
				if ( postType in otherTypes ) sourceCount++;

				// Should be in exactly one source collection
				expect( sourceCount ).toBe( 1 );
			}
		} );

		// Test single post type endpoint with source parameter
		test( 'single post type endpoint should respect source parameter', async ( {
			requestUtils,
		} ) => {
			const customTestType = TEST_POST_TYPE_SLUG; // 'scf-e2e-test-type'
			const coreType = 'post';

			// First verify the post types exist
			const allTypes = await requestUtils.rest( {
				path: '/wp/v2/types',
			} );

			// Test post type should exist - fail fast if it doesn't
			expect( allTypes ).toHaveProperty( customTestType );

			// Test that core type should be accessible with no source parameter
			const postWithNoSource = await requestUtils.rest( {
				path: `/wp/v2/types/${ coreType }`,
			} );
			expect( postWithNoSource ).toHaveProperty( 'slug', coreType );

			// Test that core type should be accessible with source=core
			const postWithCore = await requestUtils.rest( {
				path: `/wp/v2/types/${ coreType }`,
				params: { source: 'core' },
			} );
			expect( postWithCore ).toHaveProperty( 'slug', coreType );

			// Test that core type should NOT be accessible with source=other
			try {
				await requestUtils.rest( {
					path: `/wp/v2/types/${ coreType }`,
					params: { source: 'other' },
				} );
				throw new Error(
					'Core post type was incorrectly found with other source'
				);
			} catch ( error ) {
				// Should fail with a 404 error
				expect( error.data ).toHaveProperty( 'status', 404 );
			}

			// SCF test type should be accessible with SCF source
			const customTypeWithScf = await requestUtils.rest( {
				path: `/wp/v2/types/${ customTestType }`,
				params: { source: 'scf' },
			} );
			expect( customTypeWithScf ).toHaveProperty(
				'slug',
				customTestType
			);

			// SCF test type should NOT be accessible with other source
			try {
				await requestUtils.rest( {
					path: `/wp/v2/types/${ customTestType }`,
					params: { source: 'other' },
				} );
				throw new Error(
					'SCF test type incorrectly found with other source'
				);
			} catch ( error ) {
				expect( error.data ).toHaveProperty( 'status', 404 );
			}

			// SCF test type should NOT be accessible with core source
			try {
				await requestUtils.rest( {
					path: `/wp/v2/types/${ customTestType }`,
					params: { source: 'core' },
				} );
				throw new Error(
					'SCF test type incorrectly found with core source'
				);
			} catch ( error ) {
				expect( error.data ).toHaveProperty( 'status', 404 );
			}
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deactivatePlugin( 'scf-test-setup-post-types' );
		await requestUtils.deactivatePlugin( PLUGIN_SLUG );
	} );
} );
