/**
 * E2E tests for SCF Post Type Abilities
 *
 * Tests the WordPress Abilities API endpoints for SCF post type management.
 *
 * HTTP Method Reference (per PR #152 in WordPress/abilities-api):
 * - Read-only abilities (readonly: true) → GET with query params
 * - Regular abilities (readonly: false, destructive: false) → POST with body
 * - Destructive abilities (destructive: true) → DELETE with query params
 */
const { test, expect } = require( './fixtures' );

test.describe( 'Post Type Abilities', () => {
	const PLUGIN_SLUG = 'secure-custom-fields';
	const ABILITIES_BASE = '/wp-abilities/v1/abilities';

	// Reusable test post type - each test creates/cleans its own instance
	const TEST_POST_TYPE = {
		key: 'post_type_e2e_test',
		title: 'E2E Test Type',
		post_type: 'e2e_test',
	};

	// Helper functions

	/**
	 * Check if Abilities API exists (for older WordPress versions).
	 */
	async function abilitiesApiExists( requestUtils ) {
		try {
			await requestUtils.rest( {
				method: 'GET',
				path: '/wp-abilities/v1',
			} );
			return true;
		} catch {
			return false;
		}
	}

	/**
	 * Assert that a REST request throws a "not found" error with 404 status.
	 */
	async function expectNotFound( requestPromise ) {
		try {
			await requestPromise;
			throw new Error( 'Expected not found error but request succeeded' );
		} catch ( error ) {
			expect( error.code ).toBe( 'post_type_not_found' );
			expect( error.data?.status ).toBe( 404 );
		}
	}

	/**
	 * Assert that a REST request throws an "invalid input" error with 400 status.
	 */
	async function expectInvalidInput( requestPromise ) {
		try {
			await requestPromise;
			throw new Error( 'Expected invalid input error but request succeeded' );
		} catch ( error ) {
			expect( error.code ).toBe( 'ability_invalid_input' );
			expect( error.data?.status ).toBe( 400 );
		}
	}

	// Post type API helpers

	async function listPostTypes( requestUtils, filter = {} ) {
		return await requestUtils.rest( {
			method: 'POST',
			path: `${ ABILITIES_BASE }/scf/list-post-types/run`,
			data: { input: { filter } },
		} );
	}

	async function getPostType( requestUtils, identifier ) {
		return await requestUtils.rest( {
			method: 'POST',
			path: `${ ABILITIES_BASE }/scf/get-post-type/run`,
			data: { input: { identifier } },
		} );
	}

	async function createPostType( requestUtils, postTypeData = TEST_POST_TYPE ) {
		return await requestUtils.rest( {
			method: 'POST',
			path: `${ ABILITIES_BASE }/scf/create-post-type/run`,
			data: { input: postTypeData },
		} );
	}

	async function updatePostType( requestUtils, input ) {
		return await requestUtils.rest( {
			method: 'POST',
			path: `${ ABILITIES_BASE }/scf/update-post-type/run`,
			data: { input },
		} );
	}

	async function deletePostType( requestUtils, identifier ) {
		return await requestUtils.rest( {
			method: 'DELETE',
			path: `${ ABILITIES_BASE }/scf/delete-post-type/run`,
			params: { 'input[identifier]': identifier },
		} );
	}

	async function duplicatePostType( requestUtils, identifier ) {
		return await requestUtils.rest( {
			method: 'POST',
			path: `${ ABILITIES_BASE }/scf/duplicate-post-type/run`,
			data: { input: { identifier } },
		} );
	}

	async function exportPostType( requestUtils, identifier ) {
		return await requestUtils.rest( {
			method: 'GET',
			path: `${ ABILITIES_BASE }/scf/export-post-type/run`,
			params: { 'input[identifier]': identifier },
		} );
	}

	async function importPostType( requestUtils, postTypeData ) {
		return await requestUtils.rest( {
			method: 'POST',
			path: `${ ABILITIES_BASE }/scf/import-post-type/run`,
			data: { input: postTypeData },
		} );
	}

	/**
	 * Clean up a post type (ignore errors if it doesn't exist).
	 */
	async function cleanupPostType( requestUtils, identifier ) {
		try {
			await deletePostType( requestUtils, identifier );
		} catch {
			// Ignore errors - post type may not exist
		}
	}

	// Test setup

	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( PLUGIN_SLUG );

		// Skip all tests if Abilities API is not available (older WordPress versions)
		const hasAbilitiesApi = await abilitiesApiExists( requestUtils );
		test.skip(
			! hasAbilitiesApi,
			'Abilities API not available in this WordPress version'
		);
	} );

	// List post types - POST with body

	test.describe( 'scf/list-post-types', () => {
		test.beforeEach( async ( { requestUtils } ) => {
			await cleanupPostType( requestUtils, TEST_POST_TYPE.key );
			await createPostType( requestUtils );
		} );

		test.afterEach( async ( { requestUtils } ) => {
			await cleanupPostType( requestUtils, TEST_POST_TYPE.key );
		} );

		test( 'should list all SCF post types', async ( { requestUtils } ) => {
			const result = await listPostTypes( requestUtils );

			expect( Array.isArray( result ) ).toBe( true );
			expect( result.some( ( pt ) => pt.key === TEST_POST_TYPE.key ) ).toBe( true );
		} );

		test( 'should support filter parameter', async ( { requestUtils } ) => {
			const result = await listPostTypes( requestUtils, { active: true } );

			expect( Array.isArray( result ) ).toBe( true );
		} );
	} );

	// Get post type - POST with body

	test.describe( 'scf/get-post-type', () => {
		test.beforeEach( async ( { requestUtils } ) => {
			await cleanupPostType( requestUtils, TEST_POST_TYPE.key );
			await createPostType( requestUtils );
		} );

		test.afterEach( async ( { requestUtils } ) => {
			await cleanupPostType( requestUtils, TEST_POST_TYPE.key );
		} );

		test( 'should get a post type by identifier', async ( { requestUtils } ) => {
			const result = await getPostType( requestUtils, TEST_POST_TYPE.key );

			expect( result ).toHaveProperty( 'post_type', TEST_POST_TYPE.post_type );
			expect( result ).toHaveProperty( 'title', TEST_POST_TYPE.title );
		} );

		test( 'should return error for non-existent post type', async ( { requestUtils } ) => {
			await expectNotFound( getPostType( requestUtils, 'nonexistent_type_abc' ) );
		} );
	} );

	// Export post type - GET with query params (readonly)

	test.describe( 'scf/export-post-type', () => {
		test.beforeEach( async ( { requestUtils } ) => {
			await cleanupPostType( requestUtils, TEST_POST_TYPE.key );
			await createPostType( requestUtils );
		} );

		test.afterEach( async ( { requestUtils } ) => {
			await cleanupPostType( requestUtils, TEST_POST_TYPE.key );
		} );

		test( 'should export a post type as JSON', async ( { requestUtils } ) => {
			const result = await exportPostType( requestUtils, TEST_POST_TYPE.key );

			expect( result ).toHaveProperty( 'key', TEST_POST_TYPE.key );
			expect( result ).toHaveProperty( 'post_type', TEST_POST_TYPE.post_type );
			expect( result ).toHaveProperty( 'title', TEST_POST_TYPE.title );
		} );

		test( 'should return error for non-existent post type', async ( { requestUtils } ) => {
			await expectNotFound( exportPostType( requestUtils, 'nonexistent_export' ) );
		} );
	} );

	// Create post type - POST with body

	test.describe( 'scf/create-post-type', () => {
		test.beforeEach( async ( { requestUtils } ) => {
			await cleanupPostType( requestUtils, TEST_POST_TYPE.key );
		} );

		test.afterEach( async ( { requestUtils } ) => {
			await cleanupPostType( requestUtils, TEST_POST_TYPE.key );
		} );

		test( 'should create a new post type', async ( { requestUtils } ) => {
			const result = await createPostType( requestUtils );

			expect( result ).toHaveProperty( 'post_type', TEST_POST_TYPE.post_type );
			expect( result ).toHaveProperty( 'title', TEST_POST_TYPE.title );
			expect( result ).toHaveProperty( 'key', TEST_POST_TYPE.key );
		} );

		test( 'should return error when required fields are missing', async ( { requestUtils } ) => {
			await expectInvalidInput( createPostType( requestUtils, { title: 'Missing Key and Post Type' } ) );
		} );
	} );

	// Update post type - POST with body

	test.describe( 'scf/update-post-type', () => {
		let testPostTypeId;

		test.beforeEach( async ( { requestUtils } ) => {
			await cleanupPostType( requestUtils, TEST_POST_TYPE.key );
			const result = await createPostType( requestUtils );
			testPostTypeId = result.ID;
		} );

		test.afterEach( async ( { requestUtils } ) => {
			await cleanupPostType( requestUtils, TEST_POST_TYPE.key );
		} );

		test( 'should update an existing post type', async ( { requestUtils } ) => {
			const result = await updatePostType( requestUtils, {
				ID: testPostTypeId,
				title: 'Updated Title',
			} );

			expect( result ).toHaveProperty( 'title', 'Updated Title' );
		} );

		test( 'should return error for non-existent post type ID', async ( { requestUtils } ) => {
			await expectNotFound( updatePostType( requestUtils, { ID: 999999, title: 'Should Fail' } ) );
		} );

		test( 'should return error when ID is missing', async ( { requestUtils } ) => {
			await expectInvalidInput( updatePostType( requestUtils, { title: 'Missing ID' } ) );
		} );
	} );

	// Delete post type - DELETE with query params (destructive)

	test.describe( 'scf/delete-post-type', () => {
		test.beforeEach( async ( { requestUtils } ) => {
			await cleanupPostType( requestUtils, TEST_POST_TYPE.key );
			await createPostType( requestUtils );
		} );

		test.afterEach( async ( { requestUtils } ) => {
			await cleanupPostType( requestUtils, TEST_POST_TYPE.key );
		} );

		test( 'should delete an existing post type', async ( { requestUtils } ) => {
			const result = await deletePostType( requestUtils, TEST_POST_TYPE.key );
			expect( result ).toBe( true );

			// Verify it's actually deleted
			await expectNotFound( getPostType( requestUtils, TEST_POST_TYPE.key ) );
		} );

		test( 'should return error for non-existent post type', async ( { requestUtils } ) => {
			await cleanupPostType( requestUtils, TEST_POST_TYPE.key );

			await expectNotFound( deletePostType( requestUtils, 'nonexistent_type_xyz' ) );
		} );

		test( 'should return error when identifier is missing', async ( { requestUtils } ) => {
			await expectInvalidInput(
				requestUtils.rest( {
					method: 'DELETE',
					path: `${ ABILITIES_BASE }/scf/delete-post-type/run`,
					params: { input: '' },
				} )
			);
		} );
	} );

	// Duplicate post type - POST with body
	//
	// Note: The duplicate receives a new unique key but retains the same
	// post_type slug. The duplicate won't register until slug is changed.

	test.describe( 'scf/duplicate-post-type', () => {
		let duplicatedKey;

		test.beforeEach( async ( { requestUtils } ) => {
			await cleanupPostType( requestUtils, TEST_POST_TYPE.key );
			await createPostType( requestUtils );
		} );

		test.afterEach( async ( { requestUtils } ) => {
			await cleanupPostType( requestUtils, TEST_POST_TYPE.key );
			if ( duplicatedKey ) {
				await cleanupPostType( requestUtils, duplicatedKey );
				duplicatedKey = null;
			}
		} );

		test( 'should duplicate an existing post type', async ( { requestUtils } ) => {
			const result = await duplicatePostType( requestUtils, TEST_POST_TYPE.key );
			duplicatedKey = result.key;

			expect( result ).toHaveProperty( 'key' );
			expect( result ).toHaveProperty( 'post_type' );
			expect( result.key ).not.toBe( TEST_POST_TYPE.key );
			expect( result.post_type ).toBe( TEST_POST_TYPE.post_type );
			expect( result.title ).toContain( '(copy)' );
		} );

		test( 'should return error for non-existent post type', async ( { requestUtils } ) => {
			await expectNotFound( duplicatePostType( requestUtils, 'nonexistent_duplicate_source' ) );
		} );
	} );

	// Import post type - POST with body

	test.describe( 'scf/import-post-type', () => {
		test.beforeEach( async ( { requestUtils } ) => {
			await cleanupPostType( requestUtils, TEST_POST_TYPE.key );
		} );

		test.afterEach( async ( { requestUtils } ) => {
			await cleanupPostType( requestUtils, TEST_POST_TYPE.key );
		} );

		test( 'should import a post type from JSON', async ( { requestUtils } ) => {
			const result = await importPostType( requestUtils, TEST_POST_TYPE );

			expect( result ).toHaveProperty( 'post_type', TEST_POST_TYPE.post_type );
			expect( result ).toHaveProperty( 'title', TEST_POST_TYPE.title );
		} );

		test( 'should return error when required fields are missing', async ( { requestUtils } ) => {
			await expectInvalidInput( importPostType( requestUtils, { title: 'Missing Required Fields' } ) );
		} );
	} );
} );
