/**
 * E2E tests for SCF Internal Post Type Abilities (Post Types, Taxonomies, and UI Options Pages)
 *
 * Tests the WordPress Abilities API endpoints for SCF internal post type management.
 * All entity types share the same base class, so tests are parameterized.
 *
 * HTTP Method Reference:
 * - Read-only abilities (readonly: true) → GET with bracket notation: { 'input[key]': value }
 * - Regular abilities (readonly: false, destructive: false) → POST with JSON body: { input: { key: value } }
 * - Destructive abilities (destructive: true) → DELETE with bracket notation: { 'input[key]': value }
 *
 * Note: GET/DELETE use bracket notation because PHP parses `?input[key]=val` into arrays.
 * JSON.stringify does NOT work for query params - PHP receives a string, not an object.
 */
const { test, expect } = require( './fixtures' );

const PLUGIN_SLUG = 'secure-custom-fields';
const ABILITIES_BASE = '/wp-abilities/v1/abilities';

/**
 * Entity type configurations for parameterized tests.
 */
const ENTITY_TYPES = [
	{
		name: 'Post Type',
		slug: 'post-type',
		slugPlural: 'post-types',
		identifierKey: 'post_type',
		testEntity: {
			key: 'post_type_e2e_test',
			title: 'E2E Test Type',
			post_type: 'e2e_test',
		},
	},
	{
		name: 'Taxonomy',
		slug: 'taxonomy',
		slugPlural: 'taxonomies',
		identifierKey: 'taxonomy',
		testEntity: {
			key: 'taxonomy_e2e_test',
			title: 'E2E Test Taxonomy',
			taxonomy: 'e2e_test_tax',
		},
	},
	{
		name: 'UI Options Page',
		slug: 'ui-options-page',
		slugPlural: 'ui-options-pages',
		identifierKey: 'menu_slug',
		testEntity: {
			key: 'ui_options_page_e2e_test',
			title: 'E2E Test Options Page',
			menu_slug: 'e2e-test-options',
		},
	},
];

// Shared helper functions

/**
 * Check if Abilities API exists (for older WordPress versions).
 *
 * @param {Object} requestUtils - Playwright request utilities.
 * @return {Promise<boolean>} Whether the Abilities API is available.
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
		expect( error.code ).toBe( 'not_found' );
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
		throw new Error( 'Expected invalid input error but request succeeded' );
	} catch ( error ) {
		expect( error.code ).toBe( 'ability_invalid_input' );
		expect( error.data?.status ).toBe( 400 );
	}
}

/**
 * Creates API helper functions for a given entity type.
 *
 * @param {Object} entityType - The entity type configuration.
 * @return {Object} Object containing API helper functions.
 */
function createApiHelpers( entityType ) {
	const { slug, slugPlural, testEntity } = entityType;

	return {
		list: ( requestUtils, filter = {} ) => {
			const params = { 'input[filter]': '' };
			Object.entries( filter ).forEach( ( [ key, value ] ) => {
				params[ `input[filter][${ key }]` ] = value;
			} );
			return requestUtils.rest( {
				method: 'GET',
				path: `${ ABILITIES_BASE }/scf/list-${ slugPlural }/run`,
				params,
			} );
		},

		get: ( requestUtils, identifier ) =>
			requestUtils.rest( {
				method: 'GET',
				path: `${ ABILITIES_BASE }/scf/get-${ slug }/run`,
				params: { 'input[identifier]': identifier },
			} ),

		create: ( requestUtils, data = testEntity ) =>
			requestUtils.rest( {
				method: 'POST',
				path: `${ ABILITIES_BASE }/scf/create-${ slug }/run`,
				data: { input: data },
			} ),

		update: ( requestUtils, input ) =>
			requestUtils.rest( {
				method: 'POST',
				path: `${ ABILITIES_BASE }/scf/update-${ slug }/run`,
				data: { input },
			} ),

		delete: ( requestUtils, identifier ) =>
			requestUtils.rest( {
				method: 'DELETE',
				path: `${ ABILITIES_BASE }/scf/delete-${ slug }/run`,
				params: { 'input[identifier]': identifier },
			} ),

		duplicate: ( requestUtils, identifier ) =>
			requestUtils.rest( {
				method: 'POST',
				path: `${ ABILITIES_BASE }/scf/duplicate-${ slug }/run`,
				data: { input: { identifier } },
			} ),

		export: ( requestUtils, identifier ) =>
			requestUtils.rest( {
				method: 'GET',
				path: `${ ABILITIES_BASE }/scf/export-${ slug }/run`,
				params: { 'input[identifier]': identifier },
			} ),

		import: ( requestUtils, data ) =>
			requestUtils.rest( {
				method: 'POST',
				path: `${ ABILITIES_BASE }/scf/import-${ slug }/run`,
				data: { input: data },
			} ),

		cleanup: async ( requestUtils, identifier ) => {
			try {
				await requestUtils.rest( {
					method: 'DELETE',
					path: `${ ABILITIES_BASE }/scf/delete-${ slug }/run`,
					params: { 'input[identifier]': identifier },
				} );
			} catch {
				// Ignore errors - entity may not exist
			}
		},
	};
}

// Run tests for each entity type
for ( const entityType of ENTITY_TYPES ) {
	const { name, slug, slugPlural, identifierKey, testEntity } = entityType;
	const api = createApiHelpers( entityType );

	test.describe( `${ name } Abilities`, () => {
		test.beforeAll( async ( { requestUtils } ) => {
			await requestUtils.activatePlugin( PLUGIN_SLUG );

			// Skip all tests if Abilities API is not available
			const hasAbilitiesApi = await abilitiesApiExists( requestUtils );
			test.skip(
				! hasAbilitiesApi,
				'Abilities API not available in this WordPress version'
			);
		} );

		// List entities - GET with query params (readonly)

		test.describe( `scf/list-${ slugPlural }`, () => {
			test.beforeEach( async ( { requestUtils } ) => {
				await api.cleanup( requestUtils, testEntity.key );
				await api.create( requestUtils );
			} );

			test.afterEach( async ( { requestUtils } ) => {
				await api.cleanup( requestUtils, testEntity.key );
			} );

			test( `should list all SCF ${ slugPlural }`, async ( {
				requestUtils,
			} ) => {
				const result = await api.list( requestUtils );

				expect( Array.isArray( result ) ).toBe( true );
				expect(
					result.some( ( item ) => item.key === testEntity.key )
				).toBe( true );
			} );

			test( 'should support filter parameter', async ( {
				requestUtils,
			} ) => {
				const result = await api.list( requestUtils, { active: true } );

				expect( Array.isArray( result ) ).toBe( true );
			} );
		} );

		// Get entity - GET with query params (readonly)

		test.describe( `scf/get-${ slug }`, () => {
			test.beforeEach( async ( { requestUtils } ) => {
				await api.cleanup( requestUtils, testEntity.key );
				await api.create( requestUtils );
			} );

			test.afterEach( async ( { requestUtils } ) => {
				await api.cleanup( requestUtils, testEntity.key );
			} );

			test( `should get a ${ slug } by identifier`, async ( {
				requestUtils,
			} ) => {
				const result = await api.get( requestUtils, testEntity.key );

				expect( result ).toHaveProperty(
					identifierKey,
					testEntity[ identifierKey ]
				);
				expect( result ).toHaveProperty( 'title', testEntity.title );
			} );

			test( `should return error for non-existent ${ slug }`, async ( {
				requestUtils,
			} ) => {
				await expectNotFound(
					api.get( requestUtils, 'nonexistent_entity_abc' )
				);
			} );
		} );

		// Export entity - GET with query params (readonly)

		test.describe( `scf/export-${ slug }`, () => {
			test.beforeEach( async ( { requestUtils } ) => {
				await api.cleanup( requestUtils, testEntity.key );
				await api.create( requestUtils );
			} );

			test.afterEach( async ( { requestUtils } ) => {
				await api.cleanup( requestUtils, testEntity.key );
			} );

			test( `should export a ${ slug } as JSON`, async ( {
				requestUtils,
			} ) => {
				const result = await api.export( requestUtils, testEntity.key );

				expect( result ).toHaveProperty( 'key', testEntity.key );
				expect( result ).toHaveProperty(
					identifierKey,
					testEntity[ identifierKey ]
				);
				expect( result ).toHaveProperty( 'title', testEntity.title );
			} );

			test( `should return error for non-existent ${ slug }`, async ( {
				requestUtils,
			} ) => {
				await expectNotFound(
					api.export( requestUtils, 'nonexistent_export' )
				);
			} );
		} );

		// Create entity - POST with body

		test.describe( `scf/create-${ slug }`, () => {
			test.beforeEach( async ( { requestUtils } ) => {
				await api.cleanup( requestUtils, testEntity.key );
			} );

			test.afterEach( async ( { requestUtils } ) => {
				await api.cleanup( requestUtils, testEntity.key );
			} );

			test( `should create a new ${ slug }`, async ( {
				requestUtils,
			} ) => {
				const result = await api.create( requestUtils );

				expect( result ).toHaveProperty(
					identifierKey,
					testEntity[ identifierKey ]
				);
				expect( result ).toHaveProperty( 'title', testEntity.title );
				expect( result ).toHaveProperty( 'key', testEntity.key );
			} );

			test( 'should return error when required fields are missing', async ( {
				requestUtils,
			} ) => {
				await expectInvalidInput(
					api.create( requestUtils, {
						title: 'Missing Required Fields',
					} )
				);
			} );
		} );

		// Update entity - POST with body

		test.describe( `scf/update-${ slug }`, () => {
			let testEntityId;

			test.beforeEach( async ( { requestUtils } ) => {
				await api.cleanup( requestUtils, testEntity.key );
				const result = await api.create( requestUtils );
				testEntityId = result.ID;
			} );

			test.afterEach( async ( { requestUtils } ) => {
				await api.cleanup( requestUtils, testEntity.key );
			} );

			test( `should update an existing ${ slug }`, async ( {
				requestUtils,
			} ) => {
				const result = await api.update( requestUtils, {
					ID: testEntityId,
					title: 'Updated Title',
				} );

				expect( result ).toHaveProperty( 'title', 'Updated Title' );
			} );

			test( `should return error for non-existent ${ slug } ID`, async ( {
				requestUtils,
			} ) => {
				await expectNotFound(
					api.update( requestUtils, {
						ID: 999999,
						title: 'Should Fail',
					} )
				);
			} );

			test( 'should return error when ID is missing', async ( {
				requestUtils,
			} ) => {
				await expectInvalidInput(
					api.update( requestUtils, { title: 'Missing ID' } )
				);
			} );
		} );

		// Delete entity - DELETE with query params (destructive)

		test.describe( `scf/delete-${ slug }`, () => {
			test.beforeEach( async ( { requestUtils } ) => {
				await api.cleanup( requestUtils, testEntity.key );
				await api.create( requestUtils );
			} );

			test.afterEach( async ( { requestUtils } ) => {
				await api.cleanup( requestUtils, testEntity.key );
			} );

			test( `should delete an existing ${ slug }`, async ( {
				requestUtils,
			} ) => {
				const result = await api.delete( requestUtils, testEntity.key );
				expect( result ).toBe( true );

				// Verify it's actually deleted
				await expectNotFound( api.get( requestUtils, testEntity.key ) );
			} );

			test( `should return error for non-existent ${ slug }`, async ( {
				requestUtils,
			} ) => {
				await api.cleanup( requestUtils, testEntity.key );

				await expectNotFound(
					api.delete( requestUtils, 'nonexistent_entity_xyz' )
				);
			} );

			test( 'should return error when identifier is missing', async ( {
				requestUtils,
			} ) => {
				await expectInvalidInput(
					requestUtils.rest( {
						method: 'DELETE',
						path: `${ ABILITIES_BASE }/scf/delete-${ slug }/run`,
						params: { input: '' },
					} )
				);
			} );
		} );

		// Duplicate entity - POST with body
		//
		// Note: The duplicate receives a new unique key but retains the same
		// slug. The duplicate won't register until slug is changed.

		test.describe( `scf/duplicate-${ slug }`, () => {
			let duplicatedKey;

			test.beforeEach( async ( { requestUtils } ) => {
				await api.cleanup( requestUtils, testEntity.key );
				await api.create( requestUtils );
			} );

			test.afterEach( async ( { requestUtils } ) => {
				await api.cleanup( requestUtils, testEntity.key );
				if ( duplicatedKey ) {
					await api.cleanup( requestUtils, duplicatedKey );
					duplicatedKey = null;
				}
			} );

			test( `should duplicate an existing ${ slug }`, async ( {
				requestUtils,
			} ) => {
				const result = await api.duplicate(
					requestUtils,
					testEntity.key
				);
				duplicatedKey = result.key;

				expect( result ).toHaveProperty( 'key' );
				expect( result ).toHaveProperty( identifierKey );
				expect( result.key ).not.toBe( testEntity.key );
				expect( result[ identifierKey ] ).toBe(
					testEntity[ identifierKey ]
				);
				expect( result.title ).toContain( '(copy)' );
			} );

			test( `should return error for non-existent ${ slug }`, async ( {
				requestUtils,
			} ) => {
				await expectNotFound(
					api.duplicate(
						requestUtils,
						'nonexistent_duplicate_source'
					)
				);
			} );
		} );

		// Import entity - POST with body

		test.describe( `scf/import-${ slug }`, () => {
			test.beforeEach( async ( { requestUtils } ) => {
				await api.cleanup( requestUtils, testEntity.key );
			} );

			test.afterEach( async ( { requestUtils } ) => {
				await api.cleanup( requestUtils, testEntity.key );
			} );

			test( `should import a ${ slug } from JSON`, async ( {
				requestUtils,
			} ) => {
				const result = await api.import( requestUtils, testEntity );

				expect( result ).toHaveProperty(
					identifierKey,
					testEntity[ identifierKey ]
				);
				expect( result ).toHaveProperty( 'title', testEntity.title );
			} );

			test( 'should return error when required fields are missing', async ( {
				requestUtils,
			} ) => {
				await expectInvalidInput(
					api.import( requestUtils, {
						title: 'Missing Required Fields',
					} )
				);
			} );
		} );
	} );
}
