/**
 * Unit tests for the SCF internal hook/filter system (_acf-hooks.js)
 *
 * Tests the EventManager which powers acf.addAction / acf.addFilter etc.
 * This is pure logic with no DOM dependency, and a frequent backport
 * surface, so it gets thorough coverage:
 * - action registration and execution order by priority
 * - filter value chaining
 * - callback/context-specific removal
 * - argument passing and context binding
 */

describe( 'SCF Hooks (EventManager)', () => {
	let hooks;

	beforeEach( () => {
		global.acf = {};
		jest.isolateModules( () => {
			require( '../../assets/src/js/_acf-hooks.js' );
		} );
		hooks = global.acf.hooks;
	} );

	afterEach( () => {
		delete global.acf;
	} );

	describe( 'addAction() / doAction()', () => {
		it( 'should execute a registered action callback', () => {
			const callback = jest.fn();

			hooks.addAction( 'test.action', callback );
			hooks.doAction( 'test.action' );

			expect( callback ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'should pass all arguments to the callback', () => {
			const callback = jest.fn();

			hooks.addAction( 'test.action', callback );
			hooks.doAction( 'test.action', 'one', 2, { three: true } );

			expect( callback ).toHaveBeenCalledWith( 'one', 2, {
				three: true,
			} );
		} );

		it( 'should execute callbacks in priority order (lowest first)', () => {
			const order = [];

			hooks.addAction( 'test.action', () => order.push( 'late' ), 20 );
			hooks.addAction( 'test.action', () => order.push( 'early' ), 5 );
			hooks.addAction( 'test.action', () => order.push( 'default' ) );
			hooks.doAction( 'test.action' );

			expect( order ).toEqual( [ 'early', 'default', 'late' ] );
		} );

		it( 'should preserve registration order for equal priorities', () => {
			const order = [];

			hooks.addAction( 'test.action', () => order.push( 'first' ), 10 );
			hooks.addAction( 'test.action', () => order.push( 'second' ), 10 );
			hooks.addAction( 'test.action', () => order.push( 'third' ), 10 );
			hooks.doAction( 'test.action' );

			expect( order ).toEqual( [ 'first', 'second', 'third' ] );
		} );

		it( 'should default the priority to 10', () => {
			const order = [];

			hooks.addAction( 'test.action', () => order.push( 'default' ) );
			hooks.addAction( 'test.action', () => order.push( 'nine' ), 9 );
			hooks.addAction( 'test.action', () => order.push( 'eleven' ), 11 );
			hooks.doAction( 'test.action' );

			expect( order ).toEqual( [ 'nine', 'default', 'eleven' ] );
		} );

		it( 'should parse string priorities as integers', () => {
			const order = [];

			hooks.addAction( 'test.action', () => order.push( 'b' ), '20' );
			hooks.addAction( 'test.action', () => order.push( 'a' ), '5' );
			hooks.doAction( 'test.action' );

			expect( order ).toEqual( [ 'a', 'b' ] );
		} );

		it( 'should bind the supplied context as `this`', () => {
			const context = { name: 'scf' };
			let receivedThis = null;

			hooks.addAction(
				'test.action',
				function () {
					receivedThis = this;
				},
				10,
				context
			);
			hooks.doAction( 'test.action' );

			expect( receivedThis ).toBe( context );
		} );

		it( 'should ignore registration when callback is not a function', () => {
			hooks.addAction( 'test.action', 'not-a-function' );

			expect( hooks.storage().actions[ 'test.action' ] ).toBeUndefined();
		} );

		it( 'should ignore registration when action name is not a string', () => {
			hooks.addAction( 123, jest.fn() );

			expect( Object.keys( hooks.storage().actions ) ).toHaveLength( 0 );
		} );

		it( 'should do nothing when running an unregistered action', () => {
			expect( () => hooks.doAction( 'never.registered' ) ).not.toThrow();
		} );

		it( 'should ignore doAction calls with a non-string action', () => {
			const callback = jest.fn();
			hooks.addAction( 'test.action', callback );

			hooks.doAction( 42 );

			expect( callback ).not.toHaveBeenCalled();
		} );

		it( 'should support chaining', () => {
			const result = hooks
				.addAction( 'test.action', jest.fn() )
				.doAction( 'test.action' );

			expect( result ).toBe( hooks );
		} );
	} );

	describe( 'removeAction()', () => {
		it( 'should remove all callbacks when no callback is given', () => {
			const callback1 = jest.fn();
			const callback2 = jest.fn();

			hooks.addAction( 'test.action', callback1 );
			hooks.addAction( 'test.action', callback2 );
			hooks.removeAction( 'test.action' );
			hooks.doAction( 'test.action' );

			expect( callback1 ).not.toHaveBeenCalled();
			expect( callback2 ).not.toHaveBeenCalled();
		} );

		it( 'should remove only the given callback', () => {
			const removed = jest.fn();
			const kept = jest.fn();

			hooks.addAction( 'test.action', removed );
			hooks.addAction( 'test.action', kept );
			hooks.removeAction( 'test.action', removed );
			hooks.doAction( 'test.action' );

			expect( removed ).not.toHaveBeenCalled();
			expect( kept ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'should remove every registration of the same callback', () => {
			const callback = jest.fn();

			hooks.addAction( 'test.action', callback, 5 );
			hooks.addAction( 'test.action', callback, 20 );
			hooks.removeAction( 'test.action', callback );
			hooks.doAction( 'test.action' );

			expect( callback ).not.toHaveBeenCalled();
		} );

		it( 'should not throw when removing an unknown action', () => {
			expect( () => hooks.removeAction( 'unknown' ) ).not.toThrow();
		} );
	} );

	describe( 'addFilter() / applyFilters()', () => {
		it( 'should return the first argument unmodified when no filters exist', () => {
			expect( hooks.applyFilters( 'unknown.filter', 'value' ) ).toBe(
				'value'
			);
		} );

		it( 'should apply a filter to the value', () => {
			hooks.addFilter( 'test.filter', ( value ) => value + '-filtered' );

			expect( hooks.applyFilters( 'test.filter', 'value' ) ).toBe(
				'value-filtered'
			);
		} );

		it( 'should chain filter results through each callback', () => {
			hooks.addFilter( 'test.filter', ( value ) => value * 2 );
			hooks.addFilter( 'test.filter', ( value ) => value + 1 );

			expect( hooks.applyFilters( 'test.filter', 10 ) ).toBe( 21 );
		} );

		it( 'should apply filters in priority order', () => {
			hooks.addFilter( 'test.filter', ( value ) => value + 'b', 20 );
			hooks.addFilter( 'test.filter', ( value ) => value + 'a', 5 );

			expect( hooks.applyFilters( 'test.filter', '' ) ).toBe( 'ab' );
		} );

		it( 'should pass extra arguments to every callback', () => {
			const callback = jest.fn( ( value ) => value );

			hooks.addFilter( 'test.filter', callback );
			hooks.applyFilters( 'test.filter', 'value', 'extra1', 'extra2' );

			expect( callback ).toHaveBeenCalledWith(
				'value',
				'extra1',
				'extra2'
			);
		} );

		it( 'should bind the supplied context as `this`', () => {
			const context = { multiplier: 3 };

			hooks.addFilter(
				'test.filter',
				function ( value ) {
					return value * this.multiplier;
				},
				10,
				context
			);

			expect( hooks.applyFilters( 'test.filter', 2 ) ).toBe( 6 );
		} );

		it( 'should return the methods object when the filter name is not a string', () => {
			// NOTE: documents current behavior — a non-string filter name
			// returns the methods object rather than the value.
			const result = hooks.applyFilters( 42, 'value' );

			expect( result ).toBe( hooks );
		} );
	} );

	describe( 'removeFilter()', () => {
		it( 'should remove all callbacks when no callback is given', () => {
			hooks.addFilter( 'test.filter', ( value ) => value + '-changed' );
			hooks.removeFilter( 'test.filter' );

			expect( hooks.applyFilters( 'test.filter', 'value' ) ).toBe(
				'value'
			);
		} );

		it( 'should remove only the given callback', () => {
			const removed = ( value ) => value + '-removed';
			const kept = ( value ) => value + '-kept';

			hooks.addFilter( 'test.filter', removed );
			hooks.addFilter( 'test.filter', kept );
			hooks.removeFilter( 'test.filter', removed );

			expect( hooks.applyFilters( 'test.filter', 'value' ) ).toBe(
				'value-kept'
			);
		} );
	} );

	describe( 'storage()', () => {
		it( 'should expose separate action and filter containers', () => {
			hooks.addAction( 'my.action', jest.fn() );
			hooks.addFilter( 'my.filter', jest.fn() );

			const storage = hooks.storage();

			expect( storage.actions[ 'my.action' ] ).toHaveLength( 1 );
			expect( storage.filters[ 'my.filter' ] ).toHaveLength( 1 );
			expect( storage.actions[ 'my.filter' ] ).toBeUndefined();
		} );

		it( 'should store callback, priority and context for each hook', () => {
			const callback = jest.fn();
			const context = {};

			hooks.addAction( 'my.action', callback, 15, context );

			expect( hooks.storage().actions[ 'my.action' ][ 0 ] ).toEqual( {
				callback,
				priority: 15,
				context,
			} );
		} );
	} );
} );
