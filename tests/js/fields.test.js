/**
 * Unit tests for the Fields manager (_acf-fields.js)
 *
 * Tests the field finder and manager functions:
 * - acf.findFields() - jQuery selector-based field discovery
 * - acf.findField() - Single field lookup by key
 * - acf.getField() - Field instance retrieval
 * - acf.getFields() - Multiple field instances
 * - acf.findClosestField() - DOM traversal helper
 * - acf.getClosestField() - Instance from closest element
 */

describe( 'Fields Manager', () => {
	let mockJQuery;
	let mockFields;

	/**
	 * Creates a fully chainable mock jQuery object with all required methods
	 */
	const createChainableMock = () => {
		const mock = {
			length: 2,
			on: jest.fn().mockReturnThis(),
			off: jest.fn().mockReturnThis(),
			find: jest.fn().mockReturnThis(),
			not: jest.fn().mockReturnThis(),
			siblings: jest.fn().mockReturnThis(),
			slice: jest.fn().mockReturnThis(),
			closest: jest.fn().mockReturnThis(),
			addClass: jest.fn().mockReturnThis(),
			removeClass: jest.fn().mockReturnThis(),
			hasClass: jest.fn().mockReturnValue( false ),
			css: jest.fn().mockReturnThis(),
			attr: jest.fn().mockReturnThis(),
			data: jest.fn().mockReturnValue( null ),
			html: jest.fn().mockReturnThis(),
			text: jest.fn().mockReturnThis(),
			val: jest.fn().mockReturnThis(),
			prop: jest.fn().mockReturnThis(),
			trigger: jest.fn().mockReturnThis(),
			each: jest.fn( function ( callback ) {
				callback.call( this, 0, this );
				return this;
			} ),
			eq: jest.fn().mockReturnThis(),
			first: jest.fn().mockReturnThis(),
			last: jest.fn().mockReturnThis(),
			parent: jest.fn().mockReturnThis(),
			parents: jest.fn().mockReturnThis(),
			children: jest.fn().mockReturnThis(),
			append: jest.fn().mockReturnThis(),
			prepend: jest.fn().mockReturnThis(),
			remove: jest.fn().mockReturnThis(),
			empty: jest.fn().mockReturnThis(),
			show: jest.fn().mockReturnThis(),
			hide: jest.fn().mockReturnThis(),
			is: jest.fn().mockReturnValue( false ),
			filter: jest.fn().mockReturnThis(),
		};
		return mock;
	};

	beforeEach( () => {
		// Create mock field elements with all jQuery methods needed
		mockFields = createChainableMock();

		// Create a chainable mock jQuery constructor
		mockJQuery = jest.fn( () => {
			// Return the mock fields for any selector
			return mockFields;
		} );

		// Add static jQuery methods
		mockJQuery.extend = jest.fn( ( target, ...sources ) => {
			return Object.assign( target || {}, ...sources );
		} );
		mockJQuery.proxy = jest.fn( ( fn, context ) => fn.bind( context ) );
		mockJQuery.fn = { jquery: '3.0.0' };

		// Set jQuery globals
		global.jQuery = mockJQuery;
		global.$ = mockJQuery;

		// Mock acf global with all required properties
		global.acf = {
			uniqueId: jest.fn( ( prefix ) => `${ prefix }123` ),
			didAction: jest.fn().mockReturnValue( true ),
			addAction: jest.fn(),
			removeAction: jest.fn(),
			addFilter: jest.fn(),
			applyFilters: jest.fn( ( name, value ) => value ),
			doAction: jest.fn(),
			arrayArgs: jest.fn( ( args ) => Array.from( args ) ),
			parseArgs: jest.fn( ( args, defaults ) => ( {
				...defaults,
				...args,
			} ) ),
			getInstance: jest.fn(),
			getInstances: jest.fn(),
			newField: jest.fn().mockReturnValue( { cid: 'newField' } ),
			models: {},
			Model: null,
			Field: null,
			isGutenbergPostEditor: jest.fn().mockReturnValue( false ),
			// String helper functions used by the field system
			strPascalCase: jest.fn( ( str ) => {
				return str
					.split( /[\s_-]+/ )
					.map(
						( word ) =>
							word.charAt( 0 ).toUpperCase() +
							word.slice( 1 ).toLowerCase()
					)
					.join( '' );
			} ),
		};

		// Load modules in order
		jest.isolateModules( () => {
			require( '../../assets/src/js/_acf-model.js' );
			require( '../../assets/src/js/_acf-field.js' );
			require( '../../assets/src/js/_acf-fields.js' );
		} );
	} );

	afterEach( () => {
		jest.clearAllMocks();
		delete global.jQuery;
		delete global.$;
		delete global.acf;
	} );

	describe( 'acf.findFields()', () => {
		it( 'should return jQuery collection of .acf-field elements', () => {
			global.acf.findFields();

			expect( mockJQuery ).toHaveBeenCalledWith(
				expect.stringContaining( '.acf-field' )
			);
		} );

		it( 'should filter by key when provided', () => {
			global.acf.findFields( { key: 'field_123' } );

			expect( mockJQuery ).toHaveBeenCalledWith(
				expect.stringContaining( '[data-key="field_123"]' )
			);
		} );

		it( 'should filter by type when provided', () => {
			global.acf.findFields( { type: 'text' } );

			expect( mockJQuery ).toHaveBeenCalledWith(
				expect.stringContaining( '[data-type="text"]' )
			);
		} );

		it( 'should filter by name when provided', () => {
			global.acf.findFields( { name: 'my_field' } );

			expect( mockJQuery ).toHaveBeenCalledWith(
				expect.stringContaining( '[data-name="my_field"]' )
			);
		} );

		it( 'should append custom selector with is parameter', () => {
			global.acf.findFields( { is: '.custom-class' } );

			expect( mockJQuery ).toHaveBeenCalledWith(
				expect.stringContaining( '.custom-class' )
			);
		} );

		it( 'should filter visible fields when visible is true', () => {
			global.acf.findFields( { visible: true } );

			expect( mockJQuery ).toHaveBeenCalledWith(
				expect.stringContaining( ':visible' )
			);
		} );

		it( 'should search within parent when provided', () => {
			const mockParent = createChainableMock();

			global.acf.findFields( { parent: mockParent } );

			// Should search for .acf-field within the parent
			expect( mockParent.find ).toHaveBeenCalledWith( '.acf-field' );
		} );

		it( 'should exclude sub-fields when excludeSubFields is true', () => {
			const mockParent = createChainableMock();
			const mockFoundFields = createChainableMock();
			mockParent.find.mockReturnValue( mockFoundFields );

			global.acf.findFields( {
				parent: mockParent,
				excludeSubFields: true,
			} );

			// Should call .not() to exclude sub-fields
			expect( mockFoundFields.not ).toHaveBeenCalledWith(
				expect.stringContaining( '.acf-field' )
			);
		} );

		it( 'should search siblings when sibling provided', () => {
			const mockSibling = createChainableMock();

			global.acf.findFields( { sibling: mockSibling } );

			// Should search for .acf-field siblings
			expect( mockSibling.siblings ).toHaveBeenCalledWith( '.acf-field' );
		} );

		it( 'should limit results when limit is set', () => {
			global.acf.findFields( { limit: 5 } );

			expect( mockFields.slice ).toHaveBeenCalledWith( 0, 5 );
		} );

		it( 'should exclude clone fields by default', () => {
			global.acf.findFields();

			expect( mockFields.not ).toHaveBeenCalledWith(
				'.acf-clone .acf-field'
			);
		} );

		it( 'should not apply filters when suppressFilters is true', () => {
			global.acf.findFields( { suppressFilters: true } );

			expect( global.acf.applyFilters ).not.toHaveBeenCalledWith(
				'find_fields_args',
				expect.anything()
			);
		} );

		it( 'should apply find_fields_args filter', () => {
			global.acf.findFields();

			expect( global.acf.applyFilters ).toHaveBeenCalledWith(
				'find_fields_args',
				expect.any( Object )
			);
		} );

		it( 'should apply find_fields filter to results', () => {
			global.acf.findFields();

			// Filter receives the found fields jQuery collection
			expect( global.acf.applyFilters ).toHaveBeenCalledWith(
				'find_fields',
				expect.objectContaining( { length: expect.any( Number ) } )
			);
		} );
	} );

	describe( 'acf.findField()', () => {
		it( 'should call findFields with key and limit 1', () => {
			const findFieldsSpy = jest.spyOn( global.acf, 'findFields' );

			global.acf.findField( 'field_123' );

			expect( findFieldsSpy ).toHaveBeenCalledWith( {
				key: 'field_123',
				limit: 1,
				parent: undefined,
				suppressFilters: true,
			} );
		} );

		it( 'should pass parent when provided', () => {
			const findFieldsSpy = jest.spyOn( global.acf, 'findFields' );
			const mockParent = createChainableMock();

			global.acf.findField( 'field_123', mockParent );

			expect( findFieldsSpy ).toHaveBeenCalledWith(
				expect.objectContaining( { parent: mockParent } )
			);
		} );
	} );

	describe( 'acf.getField()', () => {
		it( 'should return existing instance from element data', () => {
			const existingInstance = { cid: 'existing' };
			mockFields.data.mockReturnValue( existingInstance );

			const result = global.acf.getField( mockFields );

			expect( result ).toBe( existingInstance );
		} );

		it( 'should create new instance when none exists', () => {
			// Spy on newField after module load since it gets replaced
			const newFieldSpy = jest.spyOn( global.acf, 'newField' );
			newFieldSpy.mockReturnValue( { cid: 'newField' } );

			mockFields.data.mockReturnValue( null );

			const result = global.acf.getField( mockFields );

			expect( newFieldSpy ).toHaveBeenCalledWith( mockFields );
			expect( result ).toEqual( { cid: 'newField' } );
		} );

		it( 'should find field by key when string provided', () => {
			const findFieldSpy = jest.spyOn( global.acf, 'findField' );

			global.acf.getField( 'field_key' );

			expect( findFieldSpy ).toHaveBeenCalledWith( 'field_key' );
		} );
	} );

	describe( 'acf.getFields()', () => {
		it( 'should return array of field instances when jQuery collection provided', () => {
			// Create a mock that is recognized as instanceof jQuery
			// by giving it prototype properties matching jQuery
			const mockFieldElements = createChainableMock();
			Object.setPrototypeOf(
				mockFieldElements,
				global.jQuery.prototype || {}
			);

			const fieldInstances = [ { cid: 'field1' }, { cid: 'field2' } ];

			mockFieldElements.each = jest.fn( function ( callback ) {
				fieldInstances.forEach( ( f, i ) => {
					const fieldMock = createChainableMock();
					fieldMock.data.mockReturnValue( f );
					callback.call( fieldMock, i, fieldMock );
				} );
				return this;
			} );

			const result = global.acf.getFields( mockFieldElements );

			expect( Array.isArray( result ) ).toBe( true );
		} );

		it( 'should find fields when object args provided', () => {
			const findFieldsSpy = jest.spyOn( global.acf, 'findFields' );

			// Pass args that will trigger findFields path
			global.acf.getFields( { type: 'text' } );

			expect( findFieldsSpy ).toHaveBeenCalledWith( { type: 'text' } );
		} );
	} );

	describe( 'acf.findClosestField()', () => {
		it( 'should find closest .acf-field element', () => {
			const mockEl = createChainableMock();

			global.acf.findClosestField( mockEl );

			expect( mockEl.closest ).toHaveBeenCalledWith( '.acf-field' );
		} );
	} );

	describe( 'acf.getClosestField()', () => {
		it( 'should call findClosestField and return field instance', () => {
			const mockEl = createChainableMock();
			const mockClosestEl = createChainableMock();
			mockEl.closest.mockReturnValue( mockClosestEl );

			// Setup existing field instance on the element
			const mockInstance = { cid: 'closest', type: 'text' };
			mockClosestEl.data.mockReturnValue( mockInstance );

			const result = global.acf.getClosestField( mockEl );

			// Verify it finds the closest .acf-field element
			expect( mockEl.closest ).toHaveBeenCalledWith( '.acf-field' );
			// Verify a field instance is returned (via getField)
			expect( result ).toBeDefined();
			expect( result.cid ).toBeDefined();
		} );
	} );

	describe( 'Global Field Actions', () => {
		it( 'should register global action callbacks', () => {
			// The module registers actions on load
			const registeredActions = global.acf.addAction.mock.calls.map(
				( call ) => call[ 0 ]
			);

			// Check some expected global actions are registered
			expect( registeredActions ).toContain( 'prepare' );
			expect( registeredActions ).toContain( 'ready' );
			expect( registeredActions ).toContain( 'load' );
			expect( registeredActions ).toContain( 'append' );
		} );
	} );

	describe( 'duplicateFieldsManager', () => {
		it( 'should register duplicate action', () => {
			const registeredActions = global.acf.addAction.mock.calls.map(
				( call ) => call[ 0 ]
			);

			expect( registeredActions ).toContain( 'duplicate' );
		} );
	} );
} );
