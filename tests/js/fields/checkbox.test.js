/**
 * Unit tests for checkbox field type
 */

describe( 'Checkbox Field', () => {
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
		};

		// Load the checkbox field module (this will call acf.Field.extend)
		jest.isolateModules( () => {
			require( '../../../assets/src/js/_acf-field-checkbox.js' );
		} );

		// Create a mock field instance with the captured definition
		mockField = {
			...fieldDefinition,
			$: jest.fn(),
		};
	} );

	afterEach( () => {
		delete global.acf;
	} );

	describe( 'onClickToggle', () => {
		it( 'should check all inputs when toggle is checked', () => {
			// Mock $inputs with chainable jQuery methods
			const mockInputs = {
				prop: jest.fn().mockReturnThis(),
				trigger: jest.fn().mockReturnThis(),
			};

			// Mock $el (the toggle checkbox) as checked
			const mockToggle = {
				prop: jest.fn().mockReturnValue( true ),
			};

			// Mock this.$inputs() to return our mock
			mockField.$inputs = jest.fn().mockReturnValue( mockInputs );

			// Call the method
			fieldDefinition.onClickToggle.call( mockField, {}, mockToggle );

			// Verify all inputs were checked
			expect( mockInputs.prop ).toHaveBeenCalledWith( 'checked', true );
			expect( mockInputs.trigger ).toHaveBeenCalledWith( 'change' );
		} );

		it( 'should uncheck all inputs when toggle is unchecked', () => {
			const mockInputs = {
				prop: jest.fn().mockReturnThis(),
				trigger: jest.fn().mockReturnThis(),
			};

			const mockToggle = {
				prop: jest.fn().mockReturnValue( false ),
			};

			mockField.$inputs = jest.fn().mockReturnValue( mockInputs );

			fieldDefinition.onClickToggle.call( mockField, {}, mockToggle );

			expect( mockInputs.prop ).toHaveBeenCalledWith( 'checked', false );
			expect( mockInputs.trigger ).toHaveBeenCalledWith( 'change' );
		} );
	} );
} );
