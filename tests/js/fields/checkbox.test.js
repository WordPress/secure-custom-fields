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
		/**
		 * Creates real checkbox inputs plus a toggle, with change listeners.
		 *
		 * @param {boolean} toggleChecked Whether the toggle is checked.
		 * @return {Object} The inputs, toggle and change spy.
		 */
		const createCheckboxes = ( toggleChecked ) => {
			const onChange = jest.fn();
			const inputs = [ 'a', 'b' ].map( ( value ) => {
				const input = document.createElement( 'input' );
				input.type = 'checkbox';
				input.value = value;
				input.checked = ! toggleChecked;
				input.addEventListener( 'change', onChange );
				return input;
			} );
			const toggle = document.createElement( 'input' );
			toggle.type = 'checkbox';
			toggle.checked = toggleChecked;
			return { inputs, toggle, onChange };
		};

		it( 'should check all inputs when toggle is checked', () => {
			const { inputs, toggle, onChange } = createCheckboxes( true );

			// Mock this.$inputs() to return the real inputs
			mockField.$inputs = jest.fn().mockReturnValue( inputs );

			// Call the method
			fieldDefinition.onClickToggle.call( mockField, {}, [ toggle ] );

			// Verify all inputs were checked and notified
			expect( inputs.every( ( input ) => input.checked ) ).toBe( true );
			expect( onChange ).toHaveBeenCalledTimes( inputs.length );
		} );

		it( 'should uncheck all inputs when toggle is unchecked', () => {
			const { inputs, toggle, onChange } = createCheckboxes( false );

			mockField.$inputs = jest.fn().mockReturnValue( inputs );

			fieldDefinition.onClickToggle.call( mockField, {}, [ toggle ] );

			expect( inputs.every( ( input ) => input.checked ) ).toBe( false );
			expect( onChange ).toHaveBeenCalledTimes( inputs.length );
		} );
	} );
} );
