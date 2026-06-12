/**
 * E2E tests for SCF Field Abilities
 *
 * Tests the WordPress Abilities API endpoints for SCF field management.
 * Fields require a parent field group, so we create/cleanup field groups in beforeAll/afterAll.
 */
const { test, expect } = require( './fixtures' );
const { purgeScfInternalPosts } = require( './field-helpers' );

const PLUGIN_SLUG = 'secure-custom-fields';
const ABILITIES_BASE = '/wp-abilities/v1/abilities';

const TEST_FIELD_GROUP = {
	key: 'group_e2e_field_test',
	title: 'E2E Field Test Group',
	fields: [],
};

const TEST_FIELD = {
	key: 'field_e2e_test',
	label: 'E2E Test Field',
	name: 'e2e_test_field',
	type: 'text',
};

// Helper functions

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

// Field Group API helpers (for setup/cleanup)
const fieldGroupApi = {
	create: ( requestUtils, data = TEST_FIELD_GROUP ) =>
		requestUtils.rest( {
			method: 'POST',
			path: `${ ABILITIES_BASE }/scf/create-field-group/run`,
			data: { input: data },
		} ),

	delete: ( requestUtils, identifier ) =>
		requestUtils.rest( {
			method: 'DELETE',
			path: `${ ABILITIES_BASE }/scf/delete-field-group/run`,
			params: { 'input[identifier]': identifier },
		} ),

	cleanup: async ( requestUtils, identifier ) => {
		try {
			await requestUtils.rest( {
				method: 'DELETE',
				path: `${ ABILITIES_BASE }/scf/delete-field-group/run`,
				params: { 'input[identifier]': identifier },
			} );
		} catch {
			// Ignore errors - entity may not exist
		}
	},
};

// Field API helpers
const fieldApi = {
	list: ( requestUtils, filter = {} ) => {
		const params = { 'input[filter]': '' };
		Object.entries( filter ).forEach( ( [ key, value ] ) => {
			params[ `input[filter][${ key }]` ] = value;
		} );
		return requestUtils.rest( {
			method: 'GET',
			path: `${ ABILITIES_BASE }/scf/list-fields/run`,
			params,
		} );
	},

	get: ( requestUtils, identifier ) =>
		requestUtils.rest( {
			method: 'GET',
			path: `${ ABILITIES_BASE }/scf/get-field/run`,
			params: { 'input[identifier]': identifier },
		} ),

	create: ( requestUtils, data ) =>
		requestUtils.rest( {
			method: 'POST',
			path: `${ ABILITIES_BASE }/scf/create-field/run`,
			data: { input: data },
		} ),

	update: ( requestUtils, input ) =>
		requestUtils.rest( {
			method: 'POST',
			path: `${ ABILITIES_BASE }/scf/update-field/run`,
			data: { input },
		} ),

	delete: ( requestUtils, identifier ) =>
		requestUtils.rest( {
			method: 'DELETE',
			path: `${ ABILITIES_BASE }/scf/delete-field/run`,
			params: { 'input[identifier]': identifier },
		} ),

	duplicate: ( requestUtils, identifier, newParentId = null ) => {
		const input = { identifier };
		if ( newParentId ) {
			input.new_parent_id = newParentId;
		}
		return requestUtils.rest( {
			method: 'POST',
			path: `${ ABILITIES_BASE }/scf/duplicate-field/run`,
			data: { input },
		} );
	},

	export: ( requestUtils, identifier ) =>
		requestUtils.rest( {
			method: 'GET',
			path: `${ ABILITIES_BASE }/scf/export-field/run`,
			params: { 'input[identifier]': identifier },
		} ),

	import: ( requestUtils, data ) =>
		requestUtils.rest( {
			method: 'POST',
			path: `${ ABILITIES_BASE }/scf/import-field/run`,
			data: { input: data },
		} ),

	cleanup: async ( requestUtils, identifier ) => {
		try {
			await requestUtils.rest( {
				method: 'DELETE',
				path: `${ ABILITIES_BASE }/scf/delete-field/run`,
				params: { 'input[identifier]': identifier },
			} );
		} catch {
			// Ignore errors - entity may not exist
		}
	},
};

test.describe( 'Field Abilities', () => {
	let fieldGroupId;

	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( PLUGIN_SLUG );

		// Skip all tests if Abilities API is not available
		const hasAbilitiesApi = await abilitiesApiExists( requestUtils );
		test.skip(
			! hasAbilitiesApi,
			'Abilities API not available in this WordPress version'
		);

		// Purge any field groups/fields left behind by other specs or
		// aborted runs: list-fields validates every stored field against
		// its output schema, so a single stray field fails the listing.
		await requestUtils.activatePlugin( 'scf-test-utilities' );
		await purgeScfInternalPosts( requestUtils );

		// Create parent field group
		await fieldGroupApi.cleanup( requestUtils, TEST_FIELD_GROUP.key );
		const fieldGroup = await fieldGroupApi.create( requestUtils );
		fieldGroupId = fieldGroup.ID;
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await fieldGroupApi.cleanup( requestUtils, TEST_FIELD_GROUP.key );
	} );

	// List fields - GET with query params (readonly)

	test.describe( 'scf/list-fields', () => {
		test.beforeEach( async ( { requestUtils } ) => {
			await fieldApi.cleanup( requestUtils, TEST_FIELD.key );
			await fieldApi.create( requestUtils, {
				...TEST_FIELD,
				parent: fieldGroupId,
			} );
		} );

		test.afterEach( async ( { requestUtils } ) => {
			await fieldApi.cleanup( requestUtils, TEST_FIELD.key );
		} );

		test( 'should list all SCF fields', async ( { requestUtils } ) => {
			const result = await fieldApi.list( requestUtils );

			expect( Array.isArray( result ) ).toBe( true );
			expect(
				result.some( ( item ) => item.key === TEST_FIELD.key )
			).toBe( true );
		} );

		test( 'should support filter by type', async ( { requestUtils } ) => {
			const result = await fieldApi.list( requestUtils, {
				type: 'text',
			} );

			expect( Array.isArray( result ) ).toBe( true );
			expect( result.every( ( item ) => item.type === 'text' ) ).toBe(
				true
			);
		} );

		test( 'should support filter by parent', async ( { requestUtils } ) => {
			const result = await fieldApi.list( requestUtils, {
				parent: fieldGroupId,
			} );

			expect( Array.isArray( result ) ).toBe( true );
			expect(
				result.some( ( item ) => item.key === TEST_FIELD.key )
			).toBe( true );
		} );
	} );

	// Get field - GET with query params (readonly)

	test.describe( 'scf/get-field', () => {
		test.beforeEach( async ( { requestUtils } ) => {
			await fieldApi.cleanup( requestUtils, TEST_FIELD.key );
			await fieldApi.create( requestUtils, {
				...TEST_FIELD,
				parent: fieldGroupId,
			} );
		} );

		test.afterEach( async ( { requestUtils } ) => {
			await fieldApi.cleanup( requestUtils, TEST_FIELD.key );
		} );

		test( 'should get a field by key', async ( { requestUtils } ) => {
			const result = await fieldApi.get( requestUtils, TEST_FIELD.key );

			expect( result ).toHaveProperty( 'key', TEST_FIELD.key );
			expect( result ).toHaveProperty( 'label', TEST_FIELD.label );
			expect( result ).toHaveProperty( 'name', TEST_FIELD.name );
			expect( result ).toHaveProperty( 'type', TEST_FIELD.type );
		} );

		test( 'should return error for non-existent field', async ( {
			requestUtils,
		} ) => {
			await expectNotFound(
				fieldApi.get( requestUtils, 'field_nonexistent_abc' )
			);
		} );
	} );

	// Export field - GET with query params (readonly)

	test.describe( 'scf/export-field', () => {
		test.beforeEach( async ( { requestUtils } ) => {
			await fieldApi.cleanup( requestUtils, TEST_FIELD.key );
			await fieldApi.create( requestUtils, {
				...TEST_FIELD,
				parent: fieldGroupId,
			} );
		} );

		test.afterEach( async ( { requestUtils } ) => {
			await fieldApi.cleanup( requestUtils, TEST_FIELD.key );
		} );

		test( 'should export a field as JSON', async ( { requestUtils } ) => {
			const result = await fieldApi.export(
				requestUtils,
				TEST_FIELD.key
			);

			expect( result ).toHaveProperty( 'key', TEST_FIELD.key );
			expect( result ).toHaveProperty( 'label', TEST_FIELD.label );
			expect( result ).toHaveProperty( 'type', TEST_FIELD.type );
			// Internal fields should be stripped
			expect( result ).not.toHaveProperty( 'ID' );
		} );

		test( 'should return error for non-existent field', async ( {
			requestUtils,
		} ) => {
			await expectNotFound(
				fieldApi.export( requestUtils, 'field_nonexistent_export' )
			);
		} );
	} );

	// Create field - POST with body

	test.describe( 'scf/create-field', () => {
		test.beforeEach( async ( { requestUtils } ) => {
			await fieldApi.cleanup( requestUtils, TEST_FIELD.key );
		} );

		test.afterEach( async ( { requestUtils } ) => {
			await fieldApi.cleanup( requestUtils, TEST_FIELD.key );
		} );

		test( 'should create a new field', async ( { requestUtils } ) => {
			const result = await fieldApi.create( requestUtils, {
				...TEST_FIELD,
				parent: fieldGroupId,
			} );

			expect( result ).toHaveProperty( 'key', TEST_FIELD.key );
			expect( result ).toHaveProperty( 'label', TEST_FIELD.label );
			expect( result ).toHaveProperty( 'name', TEST_FIELD.name );
			expect( result ).toHaveProperty( 'type', TEST_FIELD.type );
			expect( result ).toHaveProperty( 'ID' );
		} );

		test( 'should return error when required fields are missing', async ( {
			requestUtils,
		} ) => {
			await expectInvalidInput(
				fieldApi.create( requestUtils, {
					label: 'Missing Required Fields',
				} )
			);
		} );
	} );

	// Update field - POST with body

	test.describe( 'scf/update-field', () => {
		let testFieldId;

		test.beforeEach( async ( { requestUtils } ) => {
			await fieldApi.cleanup( requestUtils, TEST_FIELD.key );
			const result = await fieldApi.create( requestUtils, {
				...TEST_FIELD,
				parent: fieldGroupId,
			} );
			testFieldId = result.ID;
		} );

		test.afterEach( async ( { requestUtils } ) => {
			await fieldApi.cleanup( requestUtils, TEST_FIELD.key );
		} );

		test( 'should update an existing field', async ( { requestUtils } ) => {
			const result = await fieldApi.update( requestUtils, {
				ID: testFieldId,
				label: 'Updated Label',
			} );

			expect( result ).toHaveProperty( 'label', 'Updated Label' );
		} );

		test( 'should return error for non-existent field ID', async ( {
			requestUtils,
		} ) => {
			await expectNotFound(
				fieldApi.update( requestUtils, {
					ID: 999999,
					label: 'Should Fail',
				} )
			);
		} );

		test( 'should return error when ID is missing', async ( {
			requestUtils,
		} ) => {
			await expectInvalidInput(
				fieldApi.update( requestUtils, { label: 'Missing ID' } )
			);
		} );
	} );

	// Delete field - DELETE with query params (destructive)

	test.describe( 'scf/delete-field', () => {
		test.beforeEach( async ( { requestUtils } ) => {
			await fieldApi.cleanup( requestUtils, TEST_FIELD.key );
			await fieldApi.create( requestUtils, {
				...TEST_FIELD,
				parent: fieldGroupId,
			} );
		} );

		test.afterEach( async ( { requestUtils } ) => {
			await fieldApi.cleanup( requestUtils, TEST_FIELD.key );
		} );

		test( 'should delete an existing field', async ( { requestUtils } ) => {
			const result = await fieldApi.delete(
				requestUtils,
				TEST_FIELD.key
			);
			expect( result ).toBe( true );

			// Verify it's actually deleted
			await expectNotFound(
				fieldApi.get( requestUtils, TEST_FIELD.key )
			);
		} );

		test( 'should return error for non-existent field', async ( {
			requestUtils,
		} ) => {
			await fieldApi.cleanup( requestUtils, TEST_FIELD.key );

			await expectNotFound(
				fieldApi.delete( requestUtils, 'field_nonexistent_xyz' )
			);
		} );
	} );

	// Duplicate field - POST with body

	test.describe( 'scf/duplicate-field', () => {
		let duplicatedKey;

		test.beforeEach( async ( { requestUtils } ) => {
			await fieldApi.cleanup( requestUtils, TEST_FIELD.key );
			await fieldApi.create( requestUtils, {
				...TEST_FIELD,
				parent: fieldGroupId,
			} );
		} );

		test.afterEach( async ( { requestUtils } ) => {
			await fieldApi.cleanup( requestUtils, TEST_FIELD.key );
			if ( duplicatedKey ) {
				await fieldApi.cleanup( requestUtils, duplicatedKey );
				duplicatedKey = null;
			}
		} );

		test( 'should duplicate an existing field', async ( {
			requestUtils,
		} ) => {
			const result = await fieldApi.duplicate(
				requestUtils,
				TEST_FIELD.key
			);
			duplicatedKey = result.key;

			expect( result ).toHaveProperty( 'key' );
			expect( result.key ).not.toBe( TEST_FIELD.key );
			expect( result ).toHaveProperty( 'type', TEST_FIELD.type );
		} );

		test( 'should return error for non-existent field', async ( {
			requestUtils,
		} ) => {
			await expectNotFound(
				fieldApi.duplicate( requestUtils, 'field_nonexistent_dup' )
			);
		} );
	} );

	// Import field - POST with body

	test.describe( 'scf/import-field', () => {
		test.beforeEach( async ( { requestUtils } ) => {
			await fieldApi.cleanup( requestUtils, TEST_FIELD.key );
		} );

		test.afterEach( async ( { requestUtils } ) => {
			await fieldApi.cleanup( requestUtils, TEST_FIELD.key );
		} );

		test( 'should import a field from JSON', async ( { requestUtils } ) => {
			const result = await fieldApi.import( requestUtils, {
				...TEST_FIELD,
				parent: fieldGroupId,
			} );

			expect( result ).toHaveProperty( 'key', TEST_FIELD.key );
			expect( result ).toHaveProperty( 'label', TEST_FIELD.label );
			expect( result ).toHaveProperty( 'type', TEST_FIELD.type );
		} );

		test( 'should return error when required fields are missing', async ( {
			requestUtils,
		} ) => {
			await expectInvalidInput(
				fieldApi.import( requestUtils, {
					label: 'Missing Required Fields',
				} )
			);
		} );
	} );
} );
