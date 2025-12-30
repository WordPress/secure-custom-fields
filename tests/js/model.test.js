/**
 * Unit tests for the core Model class (_acf-model.js)
 *
 * Tests the Model base class which provides:
 * - Data management (get, set, has, inherit)
 * - Event handling (on, off, trigger)
 * - Action/filter integration
 * - DOM element management
 */

describe( 'Model Class', () => {
	let modelDefinition;
	let mockJQuery;

	beforeEach( () => {
		// Create a chainable mock jQuery element
		mockJQuery = {
			data: jest.fn().mockReturnThis(),
			find: jest.fn().mockReturnThis(),
			on: jest.fn().mockReturnThis(),
			off: jest.fn().mockReturnThis(),
			trigger: jest.fn().mockReturnThis(),
			triggerHandler: jest.fn().mockReturnThis(),
			prop: jest.fn().mockReturnThis(),
			remove: jest.fn().mockReturnThis(),
		};

		// Mock jQuery function
		global.jQuery = jest.fn( () => mockJQuery );
		global.jQuery.extend = jest.fn( ( target, ...sources ) => {
			return Object.assign( target || {}, ...sources );
		} );
		global.jQuery.proxy = jest.fn( ( fn, context ) => fn.bind( context ) );
		global.$ = global.jQuery;

		// Mock acf global
		global.acf = {
			uniqueId: jest.fn( ( prefix ) => `${ prefix }123` ),
			didAction: jest.fn().mockReturnValue( true ),
			addAction: jest.fn(),
			removeAction: jest.fn(),
			addFilter: jest.fn(),
			removeFilter: jest.fn(),
			doAction: jest.fn(),
			arrayArgs: jest.fn( ( args ) => Array.from( args ) ),
			show: jest.fn(),
			hide: jest.fn(),
			strPascalCase: jest.fn( ( str ) =>
				str
					.split( '_' )
					.map( ( s ) => s.charAt( 0 ).toUpperCase() + s.slice( 1 ) )
					.join( '' )
			),
			models: {},
		};

		// Load the model module
		jest.isolateModules( () => {
			require( '../../assets/src/js/_acf-model.js' );
		} );

		// Capture the Model class
		modelDefinition = global.acf.Model;
	} );

	afterEach( () => {
		jest.clearAllMocks();
		delete global.jQuery;
		delete global.$;
		delete global.acf;
	} );

	describe( 'Model constructor', () => {
		it( 'should generate a unique client ID', () => {
			const instance = new modelDefinition();

			expect( global.acf.uniqueId ).toHaveBeenCalledWith( 'acf' );
			expect( instance.cid ).toBe( 'acf123' );
		} );

		it( 'should clone data to avoid prototype modification', () => {
			const instance1 = new modelDefinition();
			const instance2 = new modelDefinition();

			instance1.data.testValue = 'instance1';
			instance2.data.testValue = 'instance2';

			expect( instance1.data.testValue ).not.toBe(
				instance2.data.testValue
			);
		} );

		it( 'should call setup with provided arguments', () => {
			const setupSpy = jest.fn();
			const CustomModel = modelDefinition.extend( {
				setup: setupSpy,
			} );

			const props = { test: 'value' };
			new CustomModel( props );

			expect( setupSpy ).toHaveBeenCalledWith( props );
		} );
	} );

	describe( 'Data Management', () => {
		let instance;

		beforeEach( () => {
			instance = new modelDefinition();
		} );

		describe( 'get()', () => {
			it( 'should return the value for a given key', () => {
				instance.data.testKey = 'testValue';

				expect( instance.get( 'testKey' ) ).toBe( 'testValue' );
			} );

			it( 'should return undefined for non-existent keys', () => {
				expect( instance.get( 'nonExistent' ) ).toBeUndefined();
			} );
		} );

		describe( 'has()', () => {
			it( 'should return true when value exists and is not null', () => {
				instance.data.testKey = 'testValue';

				expect( instance.has( 'testKey' ) ).toBe( true );
			} );

			it( 'should return false when value is null', () => {
				instance.data.testKey = null;

				expect( instance.has( 'testKey' ) ).toBe( false );
			} );

			it( 'should return false when value is undefined', () => {
				expect( instance.has( 'nonExistent' ) ).toBe( false );
			} );

			it( 'should return true for falsy non-null values', () => {
				instance.data.zeroValue = 0;
				instance.data.emptyString = '';
				instance.data.falseValue = false;

				expect( instance.has( 'zeroValue' ) ).toBe( true );
				expect( instance.has( 'emptyString' ) ).toBe( true );
				expect( instance.has( 'falseValue' ) ).toBe( true );
			} );
		} );

		describe( 'set()', () => {
			it( 'should set a data value', () => {
				instance.set( 'newKey', 'newValue' );

				expect( instance.data.newKey ).toBe( 'newValue' );
			} );

			it( 'should return this for chaining', () => {
				const result = instance.set( 'key', 'value' );

				expect( result ).toBe( instance );
			} );

			it( 'should not trigger events when value is unchanged', () => {
				instance.data.key = 'value';
				const triggerSpy = jest.spyOn( instance, 'trigger' );

				instance.set( 'key', 'value' );

				expect( triggerSpy ).not.toHaveBeenCalled();
			} );

			it( 'should trigger changed events when value changes', () => {
				instance.$el = mockJQuery;
				instance.data.key = 'oldValue';
				const triggerSpy = jest.spyOn( instance, 'trigger' );

				instance.set( 'key', 'newValue' );

				expect( triggerSpy ).toHaveBeenCalledWith( 'changed:key', [
					'newValue',
					'oldValue',
				] );
				expect( triggerSpy ).toHaveBeenCalledWith( 'changed', [
					'key',
					'newValue',
					'oldValue',
				] );
			} );

			it( 'should set changed flag when value changes', () => {
				instance.$el = mockJQuery;
				instance.data.key = 'oldValue';

				instance.set( 'key', 'newValue' );

				expect( instance.changed ).toBe( true );
			} );

			it( 'should not trigger events when silent is true', () => {
				instance.data.key = 'oldValue';
				const triggerSpy = jest.spyOn( instance, 'trigger' );

				instance.set( 'key', 'newValue', true );

				expect( triggerSpy ).not.toHaveBeenCalled();
			} );
		} );

		describe( 'inherit()', () => {
			it( 'should extend data from a plain object', () => {
				instance.inherit( { key1: 'value1', key2: 'value2' } );

				expect( instance.data.key1 ).toBe( 'value1' );
				expect( instance.data.key2 ).toBe( 'value2' );
			} );

			it( 'should return this for chaining', () => {
				const result = instance.inherit( { key: 'value' } );

				expect( result ).toBe( instance );
			} );
		} );
	} );

	describe( 'Element Helpers', () => {
		let instance;

		beforeEach( () => {
			instance = new modelDefinition();
			instance.$el = mockJQuery;
		} );

		describe( '$()', () => {
			it( 'should find elements within $el', () => {
				instance.$( '.test-selector' );

				expect( mockJQuery.find ).toHaveBeenCalledWith(
					'.test-selector'
				);
			} );
		} );

		describe( 'prop()', () => {
			it( 'should call prop on $el', () => {
				instance.prop( 'checked', true );

				expect( mockJQuery.prop ).toHaveBeenCalledWith(
					'checked',
					true
				);
			} );
		} );

		describe( 'addElement()', () => {
			it( 'should add element reference with $ prefix', () => {
				instance.addElement( 'button', '.btn' );

				expect( instance.$button ).toBeDefined();
				expect( mockJQuery.find ).toHaveBeenCalledWith( '.btn' );
			} );
		} );

		describe( 'addElements()', () => {
			it( 'should add multiple element references', () => {
				instance.elements = {
					button: '.btn',
					input: 'input[type="text"]',
				};

				instance.addElements();

				expect( mockJQuery.find ).toHaveBeenCalledWith( '.btn' );
				expect( mockJQuery.find ).toHaveBeenCalledWith(
					'input[type="text"]'
				);
			} );

			it( 'should return false when no elements defined', () => {
				const result = instance.addElements();

				expect( result ).toBe( false );
			} );
		} );
	} );

	describe( 'Event Handling', () => {
		let instance;

		beforeEach( () => {
			instance = new modelDefinition();
			instance.$el = mockJQuery;
		} );

		describe( 'getEventTarget()', () => {
			it( 'should return provided element', () => {
				const customEl = { custom: true };

				const result = instance.getEventTarget( customEl );

				expect( result ).toBe( customEl );
			} );

			it( 'should return $el when no element provided', () => {
				const result = instance.getEventTarget();

				expect( result ).toBe( mockJQuery );
			} );
		} );

		describe( 'trigger()', () => {
			it( 'should use triggerHandler for non-bubbling events', () => {
				instance.trigger( 'customEvent', [ 'arg1' ] );

				// Verify triggerHandler is used with correct event name
				expect( mockJQuery.triggerHandler ).toHaveBeenCalledWith(
					'customEvent',
					expect.anything()
				);
			} );

			it( 'should use trigger for bubbling events', () => {
				instance.trigger( 'customEvent', [ 'arg1' ], true );

				// Verify trigger is used with correct event name and args
				expect( mockJQuery.trigger ).toHaveBeenCalledWith(
					'customEvent',
					[ 'arg1' ],
					true
				);
			} );

			it( 'should return this for chaining', () => {
				const result = instance.trigger( 'event' );

				expect( result ).toBe( instance );
			} );
		} );

		describe( 'validateEvent()', () => {
			it( 'should return true when no eventScope is set', () => {
				const mockEvent = { target: document.createElement( 'div' ) };

				expect( instance.validateEvent( mockEvent ) ).toBe( true );
			} );
		} );
	} );

	describe( 'Action/Filter Integration', () => {
		let instance;

		beforeEach( () => {
			instance = new modelDefinition();
			instance.testCallback = jest.fn();
		} );

		describe( 'addAction()', () => {
			it( 'should call acf.addAction with correct parameters', () => {
				instance.addAction( 'testAction', instance.testCallback );

				expect( global.acf.addAction ).toHaveBeenCalledWith(
					'testAction',
					instance.testCallback,
					10,
					instance
				);
			} );

			it( 'should use custom priority', () => {
				instance.addAction( 'testAction', instance.testCallback, 5 );

				expect( global.acf.addAction ).toHaveBeenCalledWith(
					'testAction',
					instance.testCallback,
					5,
					instance
				);
			} );

			it( 'should resolve string callback names', () => {
				instance.addAction( 'testAction', 'testCallback' );

				expect( global.acf.addAction ).toHaveBeenCalledWith(
					'testAction',
					instance.testCallback,
					10,
					instance
				);
			} );
		} );

		describe( 'addFilter()', () => {
			it( 'should call acf.addFilter with correct parameters', () => {
				instance.addFilter( 'testFilter', instance.testCallback );

				expect( global.acf.addFilter ).toHaveBeenCalledWith(
					'testFilter',
					instance.testCallback,
					10,
					instance
				);
			} );

			it( 'should resolve string callback names', () => {
				instance.addFilter( 'testFilter', 'testCallback' );

				expect( global.acf.addFilter ).toHaveBeenCalledWith(
					'testFilter',
					instance.testCallback,
					10,
					instance
				);
			} );
		} );

		describe( 'addActions()', () => {
			it( 'should add multiple actions from object', () => {
				instance.actions = {
					action1: 'testCallback',
					action2: 'testCallback',
				};

				instance.addActions();

				expect( global.acf.addAction ).toHaveBeenCalledTimes( 2 );
			} );

			it( 'should return falsy when no actions defined', () => {
				const result = instance.addActions();

				expect( result ).toBeFalsy();
			} );
		} );

		describe( 'addFilters()', () => {
			it( 'should add multiple filters from object', () => {
				instance.filters = {
					filter1: 'testCallback',
					filter2: 'testCallback',
				};

				instance.addFilters();

				expect( global.acf.addFilter ).toHaveBeenCalledTimes( 2 );
			} );

			it( 'should return falsy when no filters defined', () => {
				const result = instance.addFilters();

				expect( result ).toBeFalsy();
			} );
		} );
	} );

	describe( 'Visibility Methods', () => {
		let instance;

		beforeEach( () => {
			instance = new modelDefinition();
			instance.$el = mockJQuery;
		} );

		describe( 'show()', () => {
			it( 'should call acf.show with $el', () => {
				instance.show();

				expect( global.acf.show ).toHaveBeenCalledWith( mockJQuery );
			} );
		} );

		describe( 'hide()', () => {
			it( 'should call acf.hide with $el', () => {
				instance.hide();

				expect( global.acf.hide ).toHaveBeenCalledWith( mockJQuery );
			} );
		} );
	} );

	describe( 'Cleanup', () => {
		let instance;

		beforeEach( () => {
			instance = new modelDefinition();
			instance.$el = mockJQuery;
		} );

		describe( 'remove()', () => {
			it( 'should remove element and cleanup listeners', () => {
				instance.remove();

				expect( mockJQuery.remove ).toHaveBeenCalled();
			} );
		} );
	} );

	describe( 'Utility Methods', () => {
		let instance;

		beforeEach( () => {
			instance = new modelDefinition();
		} );

		describe( 'proxy()', () => {
			it( 'should bind callback to instance context', () => {
				const callback = jest.fn( function () {
					return this;
				} );

				const proxied = instance.proxy( callback );
				const result = proxied();

				expect( result ).toBe( instance );
			} );
		} );

		describe( 'setTimeout()', () => {
			it( 'should call setTimeout with proxied callback', () => {
				jest.useFakeTimers();
				const callback = jest.fn();

				instance.setTimeout( callback, 100 );

				// Callback should not be called before timeout
				expect( callback ).not.toHaveBeenCalled();

				jest.advanceTimersByTime( 100 );

				// Callback should be called after timeout elapses
				expect( callback ).toHaveBeenCalledTimes( 1 );

				jest.useRealTimers();
			} );
		} );
	} );

	describe( 'Model.extend()', () => {
		it( 'should create a subclass with extended prototype', () => {
			const CustomModel = modelDefinition.extend( {
				customMethod: jest.fn(),
			} );

			const instance = new CustomModel();

			expect( typeof instance.customMethod ).toBe( 'function' );
		} );

		it( 'should inherit parent methods', () => {
			const CustomModel = modelDefinition.extend( {} );
			const instance = new CustomModel();

			expect( typeof instance.get ).toBe( 'function' );
			expect( typeof instance.set ).toBe( 'function' );
		} );

		it( 'should allow custom setup method', () => {
			const customSetup = jest.fn();
			const CustomModel = modelDefinition.extend( {
				setup( ...args ) {
					customSetup( ...args );
				},
			} );

			new CustomModel( 'arg1', 'arg2' );

			// setup should be called from constructor with the passed args
			expect( customSetup ).toHaveBeenCalledWith( 'arg1', 'arg2' );
		} );
	} );

	describe( 'acf.getInstance()', () => {
		it( 'should return instance from element data', () => {
			const mockInstance = { cid: 'test' };
			const mockEl = {
				data: jest.fn().mockReturnValue( mockInstance ),
			};

			const result = global.acf.getInstance( mockEl );

			expect( mockEl.data ).toHaveBeenCalledWith( 'acf' );
			expect( result ).toBe( mockInstance );
		} );
	} );

	describe( 'acf.getInstances()', () => {
		it( 'should return array of instances from multiple elements', () => {
			const instances = [
				{ cid: 'test1' },
				{ cid: 'test2' },
				{ cid: 'test3' },
			];

			// Mock jQuery with each method
			const mockElements = {
				each: jest.fn( ( callback ) => {
					instances.forEach( ( inst ) => {
						const mockEl = {
							data: jest.fn().mockReturnValue( inst ),
						};
						global.jQuery.mockReturnValue( mockEl );
						callback.call( mockEl );
					} );
				} ),
			};

			const result = global.acf.getInstances( mockElements );

			expect( result ).toHaveLength( 3 );
		} );
	} );
} );
