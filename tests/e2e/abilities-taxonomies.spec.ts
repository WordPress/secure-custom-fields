/**
 * E2E tests for SCF Taxonomy Abilities
 *
 * Tests the WordPress Abilities API endpoints for SCF taxonomy management.
 *
 * HTTP Method Reference (per PR #152 in WordPress/abilities-api):
 * - Read-only abilities (readonly: true) → GET with query params
 * - Regular abilities (readonly: false, destructive: false) → POST with body
 * - Destructive abilities (destructive: true) → DELETE with query params
 */
const { test, expect } = require( './fixtures' );

test.describe( 'Taxonomy Abilities', () => {
	const PLUGIN_SLUG = 'secure-custom-fields';
	const ABILITIES_BASE = '/wp-abilities/v1/abilities';

	// Reusable test taxonomy - each test creates/cleans its own instance
	const TEST_TAXONOMY = {
		key: 'taxonomy_e2e_test',
		title: 'E2E Test Taxonomy',
		taxonomy: 'e2e_test_tax',
	};

	// Helper functions

	/**
	 * Check if Abilities API exists (for older WordPress versions).
	 *
	 * @param {Object} requestUtils - Playwright request utilities.
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
	 *
	 * @param {Promise} requestPromise - The REST request promise to check.
	 */
	async function expectNotFound( requestPromise ) {
		try {
			await requestPromise;
			throw new Error( 'Expected not found error but request succeeded' );
		} catch ( error ) {
			expect( error.code ).toBe( 'taxonomy_not_found' );
			expect( error.data?.status ).toBe( 404 );
		}
	}

	/**
	 * Assert that a REST request throws an "invalid input" error with 400 status.
	 *
	 * @param {Promise} requestPromise - The REST request promise to check.
	 */
	async function expectInvalidInput( requestPromise ) {
		try {
			await requestPromise;
			throw new Error(
				'Expected invalid input error but request succeeded'
			);
		} catch ( error ) {
			expect( error.code ).toBe( 'ability_invalid_input' );
			expect( error.data?.status ).toBe( 400 );
		}
	}

	// Taxonomy API helpers

	async function listTaxonomies( requestUtils, filter = {} ) {
		return await requestUtils.rest( {
			method: 'POST',
			path: `${ ABILITIES_BASE }/scf/list-taxonomies/run`,
			data: { input: { filter } },
		} );
	}

	async function getTaxonomy( requestUtils, identifier ) {
		return await requestUtils.rest( {
			method: 'POST',
			path: `${ ABILITIES_BASE }/scf/get-taxonomy/run`,
			data: { input: { identifier } },
		} );
	}

	async function createTaxonomy(
		requestUtils,
		taxonomyData = TEST_TAXONOMY
	) {
		return await requestUtils.rest( {
			method: 'POST',
			path: `${ ABILITIES_BASE }/scf/create-taxonomy/run`,
			data: { input: taxonomyData },
		} );
	}

	async function updateTaxonomy( requestUtils, input ) {
		return await requestUtils.rest( {
			method: 'POST',
			path: `${ ABILITIES_BASE }/scf/update-taxonomy/run`,
			data: { input },
		} );
	}

	async function deleteTaxonomy( requestUtils, identifier ) {
		return await requestUtils.rest( {
			method: 'DELETE',
			path: `${ ABILITIES_BASE }/scf/delete-taxonomy/run`,
			params: { 'input[identifier]': identifier },
		} );
	}

	async function duplicateTaxonomy( requestUtils, identifier ) {
		return await requestUtils.rest( {
			method: 'POST',
			path: `${ ABILITIES_BASE }/scf/duplicate-taxonomy/run`,
			data: { input: { identifier } },
		} );
	}

	async function exportTaxonomy( requestUtils, identifier ) {
		return await requestUtils.rest( {
			method: 'GET',
			path: `${ ABILITIES_BASE }/scf/export-taxonomy/run`,
			params: { 'input[identifier]': identifier },
		} );
	}

	async function importTaxonomy( requestUtils, taxonomyData ) {
		return await requestUtils.rest( {
			method: 'POST',
			path: `${ ABILITIES_BASE }/scf/import-taxonomy/run`,
			data: { input: taxonomyData },
		} );
	}

	/**
	 * Clean up a taxonomy (ignore errors if it doesn't exist).
	 *
	 * @param {Object} requestUtils - Playwright request utilities.
	 * @param {string} identifier   - The taxonomy identifier (key or ID).
	 */
	async function cleanupTaxonomy( requestUtils, identifier ) {
		try {
			await deleteTaxonomy( requestUtils, identifier );
		} catch {
			// Ignore errors - taxonomy may not exist
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

	// List taxonomies - POST with body

	test.describe( 'scf/list-taxonomies', () => {
		test.beforeEach( async ( { requestUtils } ) => {
			await cleanupTaxonomy( requestUtils, TEST_TAXONOMY.key );
			await createTaxonomy( requestUtils );
		} );

		test.afterEach( async ( { requestUtils } ) => {
			await cleanupTaxonomy( requestUtils, TEST_TAXONOMY.key );
		} );

		test( 'should list all SCF taxonomies', async ( { requestUtils } ) => {
			const result = await listTaxonomies( requestUtils );

			expect( Array.isArray( result ) ).toBe( true );
			expect(
				result.some( ( tax ) => tax.key === TEST_TAXONOMY.key )
			).toBe( true );
		} );

		test( 'should support filter parameter', async ( { requestUtils } ) => {
			const result = await listTaxonomies( requestUtils, {
				active: true,
			} );

			expect( Array.isArray( result ) ).toBe( true );
		} );
	} );

	// Get taxonomy - POST with body

	test.describe( 'scf/get-taxonomy', () => {
		test.beforeEach( async ( { requestUtils } ) => {
			await cleanupTaxonomy( requestUtils, TEST_TAXONOMY.key );
			await createTaxonomy( requestUtils );
		} );

		test.afterEach( async ( { requestUtils } ) => {
			await cleanupTaxonomy( requestUtils, TEST_TAXONOMY.key );
		} );

		test( 'should get a taxonomy by identifier', async ( {
			requestUtils,
		} ) => {
			const result = await getTaxonomy( requestUtils, TEST_TAXONOMY.key );

			expect( result ).toHaveProperty(
				'taxonomy',
				TEST_TAXONOMY.taxonomy
			);
			expect( result ).toHaveProperty( 'title', TEST_TAXONOMY.title );
		} );

		test( 'should return error for non-existent taxonomy', async ( {
			requestUtils,
		} ) => {
			await expectNotFound(
				getTaxonomy( requestUtils, 'nonexistent_taxonomy_abc' )
			);
		} );
	} );

	// Export taxonomy - GET with query params (readonly)

	test.describe( 'scf/export-taxonomy', () => {
		test.beforeEach( async ( { requestUtils } ) => {
			await cleanupTaxonomy( requestUtils, TEST_TAXONOMY.key );
			await createTaxonomy( requestUtils );
		} );

		test.afterEach( async ( { requestUtils } ) => {
			await cleanupTaxonomy( requestUtils, TEST_TAXONOMY.key );
		} );

		test( 'should export a taxonomy as JSON', async ( {
			requestUtils,
		} ) => {
			const result = await exportTaxonomy(
				requestUtils,
				TEST_TAXONOMY.key
			);

			expect( result ).toHaveProperty( 'key', TEST_TAXONOMY.key );
			expect( result ).toHaveProperty(
				'taxonomy',
				TEST_TAXONOMY.taxonomy
			);
			expect( result ).toHaveProperty( 'title', TEST_TAXONOMY.title );
		} );

		test( 'should return error for non-existent taxonomy', async ( {
			requestUtils,
		} ) => {
			await expectNotFound(
				exportTaxonomy( requestUtils, 'nonexistent_export' )
			);
		} );
	} );

	// Create taxonomy - POST with body

	test.describe( 'scf/create-taxonomy', () => {
		test.beforeEach( async ( { requestUtils } ) => {
			await cleanupTaxonomy( requestUtils, TEST_TAXONOMY.key );
		} );

		test.afterEach( async ( { requestUtils } ) => {
			await cleanupTaxonomy( requestUtils, TEST_TAXONOMY.key );
		} );

		test( 'should create a new taxonomy', async ( { requestUtils } ) => {
			const result = await createTaxonomy( requestUtils );

			expect( result ).toHaveProperty(
				'taxonomy',
				TEST_TAXONOMY.taxonomy
			);
			expect( result ).toHaveProperty( 'title', TEST_TAXONOMY.title );
			expect( result ).toHaveProperty( 'key', TEST_TAXONOMY.key );
		} );

		test( 'should return error when required fields are missing', async ( {
			requestUtils,
		} ) => {
			await expectInvalidInput(
				createTaxonomy( requestUtils, {
					title: 'Missing Key and Taxonomy',
				} )
			);
		} );
	} );

	// Update taxonomy - POST with body

	test.describe( 'scf/update-taxonomy', () => {
		let testTaxonomyId;

		test.beforeEach( async ( { requestUtils } ) => {
			await cleanupTaxonomy( requestUtils, TEST_TAXONOMY.key );
			const result = await createTaxonomy( requestUtils );
			testTaxonomyId = result.ID;
		} );

		test.afterEach( async ( { requestUtils } ) => {
			await cleanupTaxonomy( requestUtils, TEST_TAXONOMY.key );
		} );

		test( 'should update an existing taxonomy', async ( {
			requestUtils,
		} ) => {
			const result = await updateTaxonomy( requestUtils, {
				ID: testTaxonomyId,
				title: 'Updated Title',
			} );

			expect( result ).toHaveProperty( 'title', 'Updated Title' );
		} );

		test( 'should return error for non-existent taxonomy ID', async ( {
			requestUtils,
		} ) => {
			await expectNotFound(
				updateTaxonomy( requestUtils, {
					ID: 999999,
					title: 'Should Fail',
				} )
			);
		} );

		test( 'should return error when ID is missing', async ( {
			requestUtils,
		} ) => {
			await expectInvalidInput(
				updateTaxonomy( requestUtils, { title: 'Missing ID' } )
			);
		} );
	} );

	// Delete taxonomy - DELETE with query params (destructive)

	test.describe( 'scf/delete-taxonomy', () => {
		test.beforeEach( async ( { requestUtils } ) => {
			await cleanupTaxonomy( requestUtils, TEST_TAXONOMY.key );
			await createTaxonomy( requestUtils );
		} );

		test.afterEach( async ( { requestUtils } ) => {
			await cleanupTaxonomy( requestUtils, TEST_TAXONOMY.key );
		} );

		test( 'should delete an existing taxonomy', async ( {
			requestUtils,
		} ) => {
			const result = await deleteTaxonomy(
				requestUtils,
				TEST_TAXONOMY.key
			);
			expect( result ).toBe( true );

			// Verify it's actually deleted
			await expectNotFound(
				getTaxonomy( requestUtils, TEST_TAXONOMY.key )
			);
		} );

		test( 'should return error for non-existent taxonomy', async ( {
			requestUtils,
		} ) => {
			await cleanupTaxonomy( requestUtils, TEST_TAXONOMY.key );

			await expectNotFound(
				deleteTaxonomy( requestUtils, 'nonexistent_taxonomy_xyz' )
			);
		} );

		test( 'should return error when identifier is missing', async ( {
			requestUtils,
		} ) => {
			await expectInvalidInput(
				requestUtils.rest( {
					method: 'DELETE',
					path: `${ ABILITIES_BASE }/scf/delete-taxonomy/run`,
					params: { input: '' },
				} )
			);
		} );
	} );

	// Duplicate taxonomy - POST with body
	//
	// Note: The duplicate receives a new unique key but retains the same
	// taxonomy slug. The duplicate won't register until slug is changed.

	test.describe( 'scf/duplicate-taxonomy', () => {
		let duplicatedKey;

		test.beforeEach( async ( { requestUtils } ) => {
			await cleanupTaxonomy( requestUtils, TEST_TAXONOMY.key );
			await createTaxonomy( requestUtils );
		} );

		test.afterEach( async ( { requestUtils } ) => {
			await cleanupTaxonomy( requestUtils, TEST_TAXONOMY.key );
			if ( duplicatedKey ) {
				await cleanupTaxonomy( requestUtils, duplicatedKey );
				duplicatedKey = null;
			}
		} );

		test( 'should duplicate an existing taxonomy', async ( {
			requestUtils,
		} ) => {
			const result = await duplicateTaxonomy(
				requestUtils,
				TEST_TAXONOMY.key
			);
			duplicatedKey = result.key;

			expect( result ).toHaveProperty( 'key' );
			expect( result ).toHaveProperty( 'taxonomy' );
			expect( result.key ).not.toBe( TEST_TAXONOMY.key );
			expect( result.taxonomy ).toBe( TEST_TAXONOMY.taxonomy );
			expect( result.title ).toContain( '(copy)' );
		} );

		test( 'should return error for non-existent taxonomy', async ( {
			requestUtils,
		} ) => {
			await expectNotFound(
				duplicateTaxonomy(
					requestUtils,
					'nonexistent_duplicate_source'
				)
			);
		} );
	} );

	// Import taxonomy - POST with body

	test.describe( 'scf/import-taxonomy', () => {
		test.beforeEach( async ( { requestUtils } ) => {
			await cleanupTaxonomy( requestUtils, TEST_TAXONOMY.key );
		} );

		test.afterEach( async ( { requestUtils } ) => {
			await cleanupTaxonomy( requestUtils, TEST_TAXONOMY.key );
		} );

		test( 'should import a taxonomy from JSON', async ( {
			requestUtils,
		} ) => {
			const result = await importTaxonomy( requestUtils, TEST_TAXONOMY );

			expect( result ).toHaveProperty(
				'taxonomy',
				TEST_TAXONOMY.taxonomy
			);
			expect( result ).toHaveProperty( 'title', TEST_TAXONOMY.title );
		} );

		test( 'should return error when required fields are missing', async ( {
			requestUtils,
		} ) => {
			await expectInvalidInput(
				importTaxonomy( requestUtils, {
					title: 'Missing Required Fields',
				} )
			);
		} );
	} );
} );
