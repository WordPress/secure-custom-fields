/**
 * Unit tests for post-locking utilities
 * Tests the WordPress post locking functions used during block operations
 */

// wp is set up as a global in the test environment
// eslint-disable-next-line no-redeclare
/* global wp */

import {
	lockPostSaving,
	unlockPostSaving,
	isPostSavingLocked,
	lockPostSavingByName,
	unlockPostSavingByName,
	sortObjectKeys,
} from '../../../assets/src/js/pro/blocks-v3/utils/post-locking';

// Mock wp.data
const mockDispatch = {
	lockPostSaving: jest.fn(),
	unlockPostSaving: jest.fn(),
};

const mockSelect = {
	isPostSavingLocked: jest.fn(),
};

global.wp = {
	data: {
		dispatch: jest.fn( () => mockDispatch ),
		select: jest.fn( () => mockSelect ),
	},
};

describe( 'Post Locking Utilities', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	describe( 'lockPostSaving', () => {
		test( 'calls dispatch.lockPostSaving with correct lock name', () => {
			lockPostSaving( 'test-client-id' );

			expect( wp.data.dispatch ).toHaveBeenCalledWith( 'core/editor' );
			expect( mockDispatch.lockPostSaving ).toHaveBeenCalledWith(
				'acf/block/test-client-id'
			);
		} );

		test( 'handles different client IDs', () => {
			const clientIds = [ 'abc-123', 'def-456', 'ghi-789' ];

			clientIds.forEach( ( clientId ) => {
				lockPostSaving( clientId );
			} );

			expect( mockDispatch.lockPostSaving ).toHaveBeenCalledTimes( 3 );
			expect( mockDispatch.lockPostSaving ).toHaveBeenNthCalledWith(
				1,
				'acf/block/abc-123'
			);
			expect( mockDispatch.lockPostSaving ).toHaveBeenNthCalledWith(
				2,
				'acf/block/def-456'
			);
			expect( mockDispatch.lockPostSaving ).toHaveBeenNthCalledWith(
				3,
				'acf/block/ghi-789'
			);
		} );

		test( 'does not throw when dispatch returns null', () => {
			wp.data.dispatch.mockReturnValueOnce( null );

			expect( () => lockPostSaving( 'test-id' ) ).not.toThrow();
		} );
	} );

	describe( 'unlockPostSaving', () => {
		test( 'calls dispatch.unlockPostSaving with correct lock name', () => {
			unlockPostSaving( 'test-client-id' );

			expect( wp.data.dispatch ).toHaveBeenCalledWith( 'core/editor' );
			expect( mockDispatch.unlockPostSaving ).toHaveBeenCalledWith(
				'acf/block/test-client-id'
			);
		} );

		test( 'handles different client IDs', () => {
			const clientIds = [ 'block-1', 'block-2', 'block-3' ];

			clientIds.forEach( ( clientId ) => {
				unlockPostSaving( clientId );
			} );

			expect( mockDispatch.unlockPostSaving ).toHaveBeenCalledTimes( 3 );
		} );

		test( 'does not throw when dispatch returns null', () => {
			wp.data.dispatch.mockReturnValueOnce( null );

			expect( () => unlockPostSaving( 'test-id' ) ).not.toThrow();
		} );
	} );

	describe( 'isPostSavingLocked', () => {
		test( 'returns true when post saving is locked', () => {
			mockSelect.isPostSavingLocked.mockReturnValue( true );

			const result = isPostSavingLocked( 'test-client-id' );

			expect( wp.data.select ).toHaveBeenCalledWith( 'core/editor' );
			expect( mockSelect.isPostSavingLocked ).toHaveBeenCalledWith(
				'acf/block/test-client-id'
			);
			expect( result ).toBe( true );
		} );

		test( 'returns false when post saving is not locked', () => {
			mockSelect.isPostSavingLocked.mockReturnValue( false );

			const result = isPostSavingLocked( 'test-client-id' );

			expect( result ).toBe( false );
		} );

		test( 'returns false when dispatch is null', () => {
			wp.data.dispatch.mockReturnValueOnce( null );

			const result = isPostSavingLocked( 'test-id' );

			expect( result ).toBe( false );
		} );
	} );

	describe( 'lockPostSavingByName', () => {
		test( 'calls dispatch.lockPostSaving with custom lock name', () => {
			lockPostSavingByName( 'acf-fetching-block' );

			expect( mockDispatch.lockPostSaving ).toHaveBeenCalledWith(
				'acf/block/acf-fetching-block'
			);
		} );

		test( 'handles various lock names', () => {
			const lockNames = [
				'custom-operation',
				'form-validation',
				'ajax-request',
			];

			lockNames.forEach( ( name ) => {
				lockPostSavingByName( name );
			} );

			expect( mockDispatch.lockPostSaving ).toHaveBeenCalledTimes( 3 );
		} );

		test( 'does not throw when dispatch returns null', () => {
			wp.data.dispatch.mockReturnValueOnce( null );

			expect( () =>
				lockPostSavingByName( 'test-operation' )
			).not.toThrow();
		} );
	} );

	describe( 'unlockPostSavingByName', () => {
		test( 'calls dispatch.unlockPostSaving with custom lock name', () => {
			unlockPostSavingByName( 'acf-fetching-block' );

			expect( mockDispatch.unlockPostSaving ).toHaveBeenCalledWith(
				'acf/block/acf-fetching-block'
			);
		} );

		test( 'handles various lock names', () => {
			const lockNames = [
				'custom-operation',
				'form-validation',
				'ajax-request',
			];

			lockNames.forEach( ( name ) => {
				unlockPostSavingByName( name );
			} );

			expect( mockDispatch.unlockPostSaving ).toHaveBeenCalledTimes( 3 );
		} );

		test( 'does not throw when dispatch returns null', () => {
			wp.data.dispatch.mockReturnValueOnce( null );

			expect( () =>
				unlockPostSavingByName( 'test-operation' )
			).not.toThrow();
		} );
	} );

	describe( 'sortObjectKeys', () => {
		test( 'sorts object keys alphabetically', () => {
			const input = { zebra: 1, apple: 2, mango: 3 };
			const result = sortObjectKeys( input );

			const keys = Object.keys( result );
			expect( keys ).toEqual( [ 'apple', 'mango', 'zebra' ] );
		} );

		test( 'preserves values when sorting', () => {
			const input = { c: 'third', a: 'first', b: 'second' };
			const result = sortObjectKeys( input );

			expect( result.a ).toBe( 'first' );
			expect( result.b ).toBe( 'second' );
			expect( result.c ).toBe( 'third' );
		} );

		test( 'handles empty object', () => {
			const input = {};
			const result = sortObjectKeys( input );

			expect( result ).toEqual( {} );
			expect( Object.keys( result ) ).toHaveLength( 0 );
		} );

		test( 'handles single key object', () => {
			const input = { only: 'value' };
			const result = sortObjectKeys( input );

			expect( result ).toEqual( { only: 'value' } );
		} );

		test( 'handles numeric keys', () => {
			const input = { 3: 'c', 1: 'a', 2: 'b' };
			const result = sortObjectKeys( input );

			const keys = Object.keys( result );
			expect( keys ).toEqual( [ '1', '2', '3' ] );
		} );

		test( 'handles mixed key types', () => {
			const input = { name: 'John', age: 30, city: 'NYC' };
			const result = sortObjectKeys( input );

			const keys = Object.keys( result );
			expect( keys ).toEqual( [ 'age', 'city', 'name' ] );
		} );

		test( 'returns new object without modifying original', () => {
			const input = { b: 1, a: 2 };
			const result = sortObjectKeys( input );

			// Check that original is not modified
			const originalKeys = Object.keys( input );
			expect( originalKeys[ 0 ] ).toBe( 'b' );

			// Check that result is different
			expect( result ).not.toBe( input );
		} );

		test( 'produces consistent hash for same properties in different order', () => {
			const obj1 = { name: 'test', data: { a: 1 }, id: '123' };
			const obj2 = { id: '123', name: 'test', data: { a: 1 } };

			const sorted1 = sortObjectKeys( obj1 );
			const sorted2 = sortObjectKeys( obj2 );

			expect( JSON.stringify( sorted1 ) ).toBe(
				JSON.stringify( sorted2 )
			);
		} );

		test( 'handles special characters in keys', () => {
			const input = { 'a-b': 1, a_c: 2, 'a.d': 3 };
			const result = sortObjectKeys( input );

			expect( Object.keys( result ) ).toEqual( [ 'a-b', 'a.d', 'a_c' ] );
		} );
	} );
} );
