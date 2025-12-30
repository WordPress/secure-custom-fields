/**
 * Unit tests for select field type
 */

describe( 'Select Field', () => {
	let fieldDefinition;
	let mockField;

	beforeEach( () => {
		// Reset mocks
		fieldDefinition = null;

		// Mock acf.Field.extend to capture the field definition
		global.acf = {
			Field: {
				extend: jest.fn( ( definition ) => {
					fieldDefinition = definition;
					return definition;
				} ),
			},
			registerFieldType: jest.fn(),
			newSelect2: jest.fn().mockReturnValue( {
				destroy: jest.fn(),
			} ),
		};

		// Load the select field module
		jest.isolateModules( () => {
			require( '../../../assets/src/js/_acf-field-select.js' );
		} );

		// Create a mock field instance with the captured definition
		mockField = {
			...fieldDefinition,
			$: jest.fn(),
			get: jest.fn(),
			inherit: jest.fn(),
		};
	} );

	afterEach( () => {
		delete global.acf;
	} );

	describe( 'Field Definition', () => {
		it( 'should have type "select"', () => {
			expect( fieldDefinition.type ).toBe( 'select' );
		} );

		it( 'should wait for "load" action', () => {
			expect( fieldDefinition.wait ).toBe( 'load' );
		} );

		it( 'should have select2 property initialized to false', () => {
			expect( fieldDefinition.select2 ).toBe( false );
		} );

		it( 'should register removeField and duplicateField events', () => {
			expect( fieldDefinition.events ).toEqual( {
				removeField: 'onRemove',
				duplicateField: 'onDuplicate',
			} );
		} );
	} );

	describe( '$input()', () => {
		it( 'should find select element', () => {
			fieldDefinition.$input.call( mockField );

			expect( mockField.$ ).toHaveBeenCalledWith( 'select' );
		} );
	} );

	describe( 'initialize()', () => {
		it( 'should inherit data from select element', () => {
			const mockSelect = { data: jest.fn() };
			mockField.$ = jest.fn().mockReturnValue( mockSelect );
			mockField.get = jest.fn().mockReturnValue( false );

			fieldDefinition.initialize.call( mockField );

			expect( mockField.inherit ).toHaveBeenCalledWith( mockSelect );
		} );

		it( 'should create select2 when ui is enabled', () => {
			const mockSelect = { data: jest.fn() };
			mockField.$ = jest.fn().mockReturnValue( mockSelect );
			mockField.get = jest.fn( ( key ) => {
				const values = {
					ui: true,
					ajax: false,
					multiple: false,
					placeholder: 'Select...',
					allow_null: true,
					create_options: false,
					ajax_action: null,
					type: 'select',
				};
				return values[ key ];
			} );

			fieldDefinition.initialize.call( mockField );

			expect( global.acf.newSelect2 ).toHaveBeenCalledWith( mockSelect, {
				field: mockField,
				ajax: false,
				multiple: false,
				placeholder: 'Select...',
				allowNull: true,
				tags: false,
				ajaxAction: 'acf/fields/select/query',
			} );
		} );

		it( 'should use custom ajax_action when provided', () => {
			const mockSelect = { data: jest.fn() };
			mockField.$ = jest.fn().mockReturnValue( mockSelect );
			mockField.get = jest.fn( ( key ) => {
				const values = {
					ui: true,
					ajax_action: 'custom_action',
					type: 'select',
				};
				return values[ key ];
			} );

			fieldDefinition.initialize.call( mockField );

			expect( global.acf.newSelect2 ).toHaveBeenCalledWith(
				mockSelect,
				expect.objectContaining( {
					ajaxAction: 'custom_action',
				} )
			);
		} );

		it( 'should not create select2 when ui is disabled', () => {
			const mockSelect = { data: jest.fn() };
			mockField.$ = jest.fn().mockReturnValue( mockSelect );
			mockField.get = jest.fn().mockReturnValue( false );

			fieldDefinition.initialize.call( mockField );

			expect( global.acf.newSelect2 ).not.toHaveBeenCalled();
		} );
	} );

	describe( 'onRemove()', () => {
		it( 'should destroy select2 when it exists', () => {
			const mockSelect2 = { destroy: jest.fn() };
			mockField.select2 = mockSelect2;

			fieldDefinition.onRemove.call( mockField );

			expect( mockSelect2.destroy ).toHaveBeenCalled();
		} );

		it( 'should do nothing when select2 does not exist', () => {
			mockField.select2 = null;

			expect( () => {
				fieldDefinition.onRemove.call( mockField );
			} ).not.toThrow();
		} );
	} );

	describe( 'onDuplicate()', () => {
		it( 'should remove select2 container from duplicate', () => {
			const mockSelect2Container = {
				remove: jest.fn(),
			};
			const mockSelectElement = {
				removeClass: jest.fn().mockReturnThis(),
			};
			const mockDuplicate = {
				find: jest.fn( ( selector ) => {
					if ( selector === '.select2-container' ) {
						return mockSelect2Container;
					}
					return mockSelectElement;
				} ),
			};
			mockField.select2 = { destroy: jest.fn() };

			fieldDefinition.onDuplicate.call(
				mockField,
				{},
				{},
				mockDuplicate
			);

			expect( mockDuplicate.find ).toHaveBeenCalledWith(
				'.select2-container'
			);
			expect( mockSelect2Container.remove ).toHaveBeenCalled();
		} );

		it( 'should remove select2-hidden-accessible class from select', () => {
			const mockSelect = {
				removeClass: jest.fn().mockReturnThis(),
			};
			const mockDuplicate = {
				find: jest.fn( ( selector ) => {
					if ( selector === 'select' ) {
						return mockSelect;
					}
					return { remove: jest.fn() };
				} ),
			};
			mockField.select2 = { destroy: jest.fn() };

			fieldDefinition.onDuplicate.call(
				mockField,
				{},
				{},
				mockDuplicate
			);

			expect( mockDuplicate.find ).toHaveBeenCalledWith( 'select' );
			expect( mockSelect.removeClass ).toHaveBeenCalledWith(
				'select2-hidden-accessible'
			);
		} );

		it( 'should do nothing when select2 does not exist', () => {
			mockField.select2 = null;
			const mockDuplicate = {
				find: jest.fn(),
			};

			fieldDefinition.onDuplicate.call(
				mockField,
				{},
				{},
				mockDuplicate
			);

			expect( mockDuplicate.find ).not.toHaveBeenCalled();
		} );
	} );
} );
