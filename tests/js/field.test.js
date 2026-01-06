/**
 * Unit tests for the Field class (_acf-field.js)
 *
 * Tests the Field class which extends Model and provides:
 * - Field value management (val, getValue, setValue)
 * - Field DOM helpers ($input, $control, $inputWrap, $labelWrap)
 * - Field visibility (show, hide, showEnable, hideDisable)
 * - Field state (enable, disable)
 * - Error handling (showError, removeError, showNotice, removeNotice)
 * - Field type registration
 */

describe( 'Field Class', () => {
	let fieldDefinition;
	let mockJQuery;
	let mockInput;

	beforeEach( () => {
		// Create mock input element
		mockInput = {
			val: jest.fn().mockReturnValue( 'test-value' ),
			attr: jest.fn().mockReturnValue( 'field_name' ),
		};

		// Create a chainable mock jQuery element
		mockJQuery = {
			data: jest.fn().mockReturnThis(),
			find: jest.fn().mockReturnValue( mockInput ),
			on: jest.fn().mockReturnThis(),
			off: jest.fn().mockReturnThis(),
			one: jest.fn().mockReturnThis(),
			trigger: jest.fn().mockReturnThis(),
			triggerHandler: jest.fn().mockReturnThis(),
			prop: jest.fn().mockReturnValue( false ),
			addClass: jest.fn().mockReturnThis(),
			removeClass: jest.fn().mockReturnThis(),
			parents: jest.fn().mockReturnValue( { length: 0 } ),
			is: jest.fn().mockReturnValue( false ),
			closest: jest.fn().mockReturnThis(),
		};

		// Mock jQuery function
		global.jQuery = jest.fn( ( selector ) => {
			if ( typeof selector === 'string' ) {
				return mockJQuery;
			}
			return mockJQuery;
		} );
		global.jQuery.extend = jest.fn( ( deep, target, ...sources ) => {
			if ( typeof deep === 'boolean' ) {
				return Object.assign( target || {}, ...sources );
			}
			return Object.assign( deep || {}, target, ...sources );
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
			applyFilters: jest.fn( ( name, value ) => value ),
			arrayArgs: jest.fn( ( args ) => Array.from( args ) ),
			show: jest.fn().mockReturnValue( true ),
			hide: jest.fn().mockReturnValue( true ),
			enable: jest.fn().mockReturnValue( true ),
			disable: jest.fn().mockReturnValue( true ),
			val: jest.fn().mockReturnValue( true ),
			newNotice: jest.fn().mockReturnValue( {
				remove: jest.fn(),
				away: jest.fn(),
			} ),
			getFields: jest.fn().mockReturnValue( [] ),
			parseArgs: jest.fn( ( args, defaults ) => ( {
				...defaults,
				...args,
			} ) ),
			strPascalCase: jest.fn( ( str ) =>
				str
					.split( '_' )
					.map( ( s ) => s.charAt( 0 ).toUpperCase() + s.slice( 1 ) )
					.join( '' )
			),
			_e: jest.fn( ( type, string ) => string ),
			models: {},
			Model: null,
		};

		// Load model first (Field extends Model)
		jest.isolateModules( () => {
			require( '../../assets/src/js/_acf-model.js' );
		} );

		// Load field module
		jest.isolateModules( () => {
			require( '../../assets/src/js/_acf-field.js' );
		} );

		fieldDefinition = global.acf.Field;
	} );

	afterEach( () => {
		jest.clearAllMocks();
		delete global.jQuery;
		delete global.$;
		delete global.acf;
	} );

	describe( 'Field setup', () => {
		it( 'should set $el from provided jQuery element', () => {
			const instance = new fieldDefinition( mockJQuery );

			expect( instance.$el ).toBe( mockJQuery );
		} );

		it( 'should have correct type property', () => {
			expect( fieldDefinition.prototype.type ).toBe( '' );
		} );

		it( 'should have correct eventScope for nested event handling', () => {
			expect( fieldDefinition.prototype.eventScope ).toBe( '.acf-field' );
		} );

		it( 'should wait for ready action', () => {
			expect( fieldDefinition.prototype.wait ).toBe( 'ready' );
		} );
	} );

	describe( 'Value Management', () => {
		let instance;

		beforeEach( () => {
			instance = new fieldDefinition( mockJQuery );
		} );

		describe( 'val()', () => {
			it( 'should get value when no argument provided', () => {
				mockJQuery.prop.mockReturnValue( false ); // not disabled

				const result = instance.val();

				expect( result ).toBe( 'test-value' );
			} );

			it( 'should return null when field is disabled', () => {
				mockJQuery.prop.mockReturnValue( true ); // disabled

				const result = instance.val();

				expect( result ).toBeNull();
			} );

			it( 'should set value when argument provided', () => {
				instance.val( 'new-value' );

				expect( global.acf.val ).toHaveBeenCalledWith(
					mockInput,
					'new-value'
				);
			} );
		} );

		describe( 'getValue()', () => {
			it( 'should return value from input element', () => {
				const result = instance.getValue();

				expect( result ).toBe( 'test-value' );
			} );
		} );

		describe( 'setValue()', () => {
			it( 'should call acf.val with input and value', () => {
				instance.setValue( 'new-value' );

				expect( global.acf.val ).toHaveBeenCalledWith(
					mockInput,
					'new-value'
				);
			} );
		} );
	} );

	describe( 'DOM Helpers', () => {
		let instance;

		beforeEach( () => {
			instance = new fieldDefinition( mockJQuery );
		} );

		describe( '$input()', () => {
			it( 'should find first named element', () => {
				instance.$input();

				expect( mockJQuery.find ).toHaveBeenCalledWith(
					'[name]:first'
				);
			} );
		} );

		describe( '$inputWrap()', () => {
			it( 'should find first .acf-input element', () => {
				instance.$inputWrap();

				expect( mockJQuery.find ).toHaveBeenCalledWith(
					'.acf-input:first'
				);
			} );
		} );

		describe( '$labelWrap()', () => {
			it( 'should find first .acf-label element', () => {
				instance.$labelWrap();

				expect( mockJQuery.find ).toHaveBeenCalledWith(
					'.acf-label:first'
				);
			} );
		} );

		describe( '$control()', () => {
			it( 'should return false by default', () => {
				const result = instance.$control();

				expect( result ).toBe( false );
			} );
		} );

		describe( 'getInputName()', () => {
			it( 'should return input name attribute', () => {
				const result = instance.getInputName();

				expect( mockInput.attr ).toHaveBeenCalledWith( 'name' );
				expect( result ).toBe( 'field_name' );
			} );

			it( 'should return empty string when no name', () => {
				mockInput.attr.mockReturnValue( undefined );

				const result = instance.getInputName();

				expect( result ).toBe( '' );
			} );
		} );
	} );

	describe( 'Parent/Child Relationships', () => {
		let instance;

		beforeEach( () => {
			instance = new fieldDefinition( mockJQuery );
		} );

		describe( 'parent()', () => {
			it( 'should return first parent field or false', () => {
				const result = instance.parent();

				expect( result ).toBe( false );
			} );

			it( 'should return parent when parents exist', () => {
				const mockParent = { cid: 'parent1' };
				global.acf.getFields.mockReturnValue( [ mockParent ] );
				mockJQuery.parents.mockReturnValue( { length: 1 } );

				const result = instance.parent();

				expect( result ).toBe( mockParent );
			} );
		} );

		describe( 'parents()', () => {
			it( 'should find parent .acf-field elements', () => {
				instance.parents();

				expect( mockJQuery.parents ).toHaveBeenCalledWith(
					'.acf-field'
				);
			} );
		} );
	} );

	describe( 'Visibility Methods', () => {
		let instance;

		beforeEach( () => {
			instance = new fieldDefinition( mockJQuery );
		} );

		describe( 'show()', () => {
			it( 'should call acf.show with element', () => {
				instance.show( 'lockKey' );

				expect( global.acf.show ).toHaveBeenCalledWith(
					mockJQuery,
					'lockKey'
				);
			} );

			it( 'should trigger show_field action when visibility changes', () => {
				global.acf.show.mockReturnValue( true );

				instance.show( 'lockKey', 'context' );

				expect( global.acf.doAction ).toHaveBeenCalledWith(
					'show_field',
					instance,
					'context'
				);
			} );

			it( 'should set hidden prop to false when shown', () => {
				global.acf.show.mockReturnValue( true );

				instance.show( 'lockKey' );

				expect( mockJQuery.prop ).toHaveBeenCalledWith(
					'hidden',
					false
				);
			} );

			it( 'should not trigger action when visibility unchanged', () => {
				global.acf.show.mockReturnValue( false );

				instance.show( 'lockKey', 'context' );

				expect( global.acf.doAction ).not.toHaveBeenCalled();
			} );
		} );

		describe( 'hide()', () => {
			it( 'should call acf.hide with element', () => {
				instance.hide( 'lockKey' );

				expect( global.acf.hide ).toHaveBeenCalledWith(
					mockJQuery,
					'lockKey'
				);
			} );

			it( 'should trigger hide_field action when visibility changes', () => {
				global.acf.hide.mockReturnValue( true );

				instance.hide( 'lockKey', 'context' );

				expect( global.acf.doAction ).toHaveBeenCalledWith(
					'hide_field',
					instance,
					'context'
				);
			} );

			it( 'should set hidden prop to true when hidden', () => {
				global.acf.hide.mockReturnValue( true );

				instance.hide( 'lockKey' );

				expect( mockJQuery.prop ).toHaveBeenCalledWith(
					'hidden',
					true
				);
			} );
		} );
	} );

	describe( 'Enable/Disable Methods', () => {
		let instance;

		beforeEach( () => {
			instance = new fieldDefinition( mockJQuery );
		} );

		describe( 'enable()', () => {
			it( 'should call acf.enable with element', () => {
				instance.enable( 'lockKey' );

				expect( global.acf.enable ).toHaveBeenCalledWith(
					mockJQuery,
					'lockKey'
				);
			} );

			it( 'should trigger enable_field action when state changes', () => {
				global.acf.enable.mockReturnValue( true );

				instance.enable( 'lockKey', 'context' );

				expect( global.acf.doAction ).toHaveBeenCalledWith(
					'enable_field',
					instance,
					'context'
				);
			} );

			it( 'should set disabled prop to false when enabled', () => {
				global.acf.enable.mockReturnValue( true );

				instance.enable( 'lockKey' );

				expect( mockJQuery.prop ).toHaveBeenCalledWith(
					'disabled',
					false
				);
			} );
		} );

		describe( 'disable()', () => {
			it( 'should call acf.disable with element', () => {
				instance.disable( 'lockKey' );

				expect( global.acf.disable ).toHaveBeenCalledWith(
					mockJQuery,
					'lockKey'
				);
			} );

			it( 'should trigger disable_field action when state changes', () => {
				global.acf.disable.mockReturnValue( true );

				instance.disable( 'lockKey', 'context' );

				expect( global.acf.doAction ).toHaveBeenCalledWith(
					'disable_field',
					instance,
					'context'
				);
			} );

			it( 'should set disabled prop to true when disabled', () => {
				global.acf.disable.mockReturnValue( true );

				instance.disable( 'lockKey' );

				expect( mockJQuery.prop ).toHaveBeenCalledWith(
					'disabled',
					true
				);
			} );
		} );

		describe( 'showEnable()', () => {
			it( 'should call both enable and show with correct args', () => {
				const enableSpy = jest.spyOn( instance, 'enable' );
				const showSpy = jest.spyOn( instance, 'show' );

				instance.showEnable( 'lockKey', 'context' );

				expect( enableSpy ).toHaveBeenCalledWith(
					'lockKey',
					'context'
				);
				expect( showSpy ).toHaveBeenCalledWith( 'lockKey', 'context' );
			} );
		} );

		describe( 'hideDisable()', () => {
			it( 'should call both disable and hide with correct args', () => {
				const disableSpy = jest.spyOn( instance, 'disable' );
				const hideSpy = jest.spyOn( instance, 'hide' );

				instance.hideDisable( 'lockKey', 'context' );

				expect( disableSpy ).toHaveBeenCalledWith(
					'lockKey',
					'context'
				);
				expect( hideSpy ).toHaveBeenCalledWith( 'lockKey', 'context' );
			} );
		} );
	} );

	describe( 'Notice and Error Handling', () => {
		let instance;

		beforeEach( () => {
			instance = new fieldDefinition( mockJQuery );
		} );

		describe( 'showNotice()', () => {
			it( 'should create a new notice with target element', () => {
				instance.showNotice( { text: 'Test notice' } );

				// Verify notice is created with correct text and a target element
				expect( global.acf.newNotice ).toHaveBeenCalledWith(
					expect.objectContaining( {
						text: 'Test notice',
						target: expect.anything(),
					} )
				);
			} );

			it( 'should convert string to object props', () => {
				instance.showNotice( 'Test notice' );

				expect( global.acf.newNotice ).toHaveBeenCalledWith(
					expect.objectContaining( { text: 'Test notice' } )
				);
			} );

			it( 'should remove old notice before creating new one', () => {
				const oldNotice = { remove: jest.fn() };
				instance.notice = oldNotice;

				instance.showNotice( 'New notice' );

				expect( oldNotice.remove ).toHaveBeenCalled();
			} );
		} );

		describe( 'removeNotice()', () => {
			it( 'should call away on notice', () => {
				const mockNotice = { away: jest.fn() };
				instance.notice = mockNotice;

				instance.removeNotice( 500 );

				expect( mockNotice.away ).toHaveBeenCalledWith( 500 );
			} );

			it( 'should set notice to false after removal', () => {
				instance.notice = { away: jest.fn() };

				instance.removeNotice();

				expect( instance.notice ).toBe( false );
			} );

			it( 'should use 0 as default timeout', () => {
				const mockNotice = { away: jest.fn() };
				instance.notice = mockNotice;

				instance.removeNotice();

				expect( mockNotice.away ).toHaveBeenCalledWith( 0 );
			} );
		} );

		describe( 'showError()', () => {
			it( 'should add acf-error class', () => {
				instance.showError( 'Error message' );

				expect( mockJQuery.addClass ).toHaveBeenCalledWith(
					'acf-error'
				);
			} );

			it( 'should show error notice with message', () => {
				const showNoticeSpy = jest.spyOn( instance, 'showNotice' );

				instance.showError( 'Error message' );

				expect( showNoticeSpy ).toHaveBeenCalledWith(
					expect.objectContaining( {
						text: 'Error message',
						type: 'error',
						dismiss: false,
						location: 'before',
					} )
				);
			} );

			it( 'should trigger invalid_field action', () => {
				instance.showError( 'Error' );

				expect( global.acf.doAction ).toHaveBeenCalledWith(
					'invalid_field',
					instance
				);
			} );

			it( 'should attach one-time focus/change handler to clear error', () => {
				instance.showError( 'Error' );

				expect( mockJQuery.one ).toHaveBeenCalledWith(
					'focus change',
					'input, select, textarea',
					expect.any( Function )
				);
			} );

			it( 'should support custom location parameter', () => {
				const showNoticeSpy = jest.spyOn( instance, 'showNotice' );

				instance.showError( 'Error message', 'after' );

				expect( showNoticeSpy ).toHaveBeenCalledWith(
					expect.objectContaining( { location: 'after' } )
				);
			} );
		} );

		describe( 'removeError()', () => {
			it( 'should remove acf-error class', () => {
				instance.removeError();

				expect( mockJQuery.removeClass ).toHaveBeenCalledWith(
					'acf-error'
				);
			} );

			it( 'should call removeNotice', () => {
				const removeNoticeSpy = jest.spyOn( instance, 'removeNotice' );

				instance.removeError();

				expect( removeNoticeSpy ).toHaveBeenCalledWith( 250 );
			} );

			it( 'should trigger valid_field action', () => {
				instance.removeError();

				expect( global.acf.doAction ).toHaveBeenCalledWith(
					'valid_field',
					instance
				);
			} );
		} );
	} );

	describe( 'i18n Helper', () => {
		let instance;

		beforeEach( () => {
			instance = new fieldDefinition( mockJQuery );
			instance.type = 'text';
		} );

		describe( '__()', () => {
			it( 'should call acf._e with field type and string', () => {
				instance.__( 'label' );

				expect( global.acf._e ).toHaveBeenCalledWith( 'text', 'label' );
			} );
		} );
	} );

	describe( 'trigger() override', () => {
		let instance;

		beforeEach( () => {
			instance = new fieldDefinition( mockJQuery );
		} );

		it( 'should bubble invalidField events', () => {
			instance.trigger( 'invalidField', [], false );

			// Verify trigger (bubbling) is used with the invalidField event
			// The real implementation passes (event, args, bubble) to jQuery's trigger
			expect( mockJQuery.trigger ).toHaveBeenCalledWith(
				'invalidField',
				[],
				expect.anything()
			);
		} );

		it( 'should not bubble other events by default', () => {
			instance.trigger( 'customEvent', [] );

			// Verify triggerHandler (non-bubbling) is used for regular events
			expect( mockJQuery.triggerHandler ).toHaveBeenCalledWith(
				'customEvent',
				[],
				undefined // No bubble parameter for non-bubbling events
			);
			// Ensure trigger (bubbling) was NOT used
			expect( mockJQuery.trigger ).not.toHaveBeenCalled();
		} );
	} );

	describe( 'Field Type Registration', () => {
		describe( 'acf.registerFieldType()', () => {
			it( 'should store model in acf.models', () => {
				const CustomField = fieldDefinition.extend( {
					type: 'custom_type',
				} );

				global.acf.registerFieldType( CustomField );

				expect( global.acf.models.CustomTypeField ).toBe( CustomField );
			} );
		} );

		describe( 'acf.getFieldType()', () => {
			it( 'should return registered field type', () => {
				const CustomField = fieldDefinition.extend( {
					type: 'test_type',
				} );
				global.acf.models.TestTypeField = CustomField;

				const result = global.acf.getFieldType( 'test_type' );

				expect( result ).toBe( CustomField );
			} );

			it( 'should return false for unregistered type', () => {
				const result = global.acf.getFieldType( 'nonexistent' );

				expect( result ).toBe( false );
			} );
		} );

		describe( 'acf.newField()', () => {
			it( 'should create instance of registered field type', () => {
				const CustomField = fieldDefinition.extend( {
					type: 'text',
				} );
				global.acf.models.TextField = CustomField;

				mockJQuery.data.mockImplementation( ( key ) => {
					if ( key === 'type' ) {
						return 'text';
					}
					return mockJQuery;
				} );

				const instance = global.acf.newField( mockJQuery );

				// Verify instance is created from the registered field type
				expect( instance ).toBeDefined();
				// The type is stored on the model definition, not in data
				expect( instance.type ).toBe( 'text' );
			} );

			it( 'should trigger new_field action', () => {
				mockJQuery.data.mockImplementation( ( key ) => {
					if ( key === 'type' ) {
						return 'unknown';
					}
					return mockJQuery;
				} );

				global.acf.newField( mockJQuery );

				expect( global.acf.doAction ).toHaveBeenCalledWith(
					'new_field',
					expect.any( Object )
				);
			} );
		} );
	} );
} );
