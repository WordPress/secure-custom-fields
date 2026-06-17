/**
 * Unit tests for range field type
 */

describe( 'Range Field', () => {
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
			val: jest.fn(),
		};

		// Load the range field module
		jest.isolateModules( () => {
			require( '../../../assets/src/js/_acf-field-range.js' );
		} );

		// Create a mock field instance with the captured definition
		mockField = {
			...fieldDefinition,
			$: jest.fn(),
			busy: false,
		};
	} );

	afterEach( () => {
		delete global.acf;
	} );

	describe( 'Field Definition', () => {
		it( 'should have type "range"', () => {
			expect( fieldDefinition.type ).toBe( 'range' );
		} );

		it( 'should register input and change events', () => {
			expect( fieldDefinition.events ).toEqual( {
				'input input[type="range"]': 'onChange',
				'change input': 'onChange',
			} );
		} );
	} );

	describe( '$input()', () => {
		it( 'should find range input element', () => {
			fieldDefinition.$input.call( mockField );

			expect( mockField.$ ).toHaveBeenCalledWith( 'input[type="range"]' );
		} );
	} );

	describe( '$inputAlt()', () => {
		it( 'should find number input element', () => {
			fieldDefinition.$inputAlt.call( mockField );

			expect( mockField.$ ).toHaveBeenCalledWith(
				'input[type="number"]'
			);
		} );
	} );

	describe( 'setValue()', () => {
		let rangeInput;
		let $rangeInput;
		let $numberInput;

		beforeEach( () => {
			rangeInput = document.createElement( 'input' );
			rangeInput.type = 'range';
			rangeInput.value = '50';
			const numberInput = document.createElement( 'input' );
			numberInput.type = 'number';
			$rangeInput = [ rangeInput ];
			$numberInput = [ numberInput ];
			mockField.$input = jest.fn().mockReturnValue( $rangeInput );
			mockField.$inputAlt = jest.fn().mockReturnValue( $numberInput );
		} );

		it( 'should set busy flag during value update', () => {
			fieldDefinition.setValue.call( mockField, 75 );

			// After setValue completes, busy should be false
			expect( mockField.busy ).toBe( false );
		} );

		it( 'should update range input with change event', () => {
			fieldDefinition.setValue.call( mockField, 75 );

			expect( global.acf.val ).toHaveBeenCalledWith( $rangeInput, 75 );
		} );

		it( 'should update alt input without change event', () => {
			fieldDefinition.setValue.call( mockField, 75 );

			// Second call should be for alt input with silent flag
			expect( global.acf.val ).toHaveBeenCalledWith(
				$numberInput,
				'50',
				true
			);
		} );

		it( 'should read validated value from range input', () => {
			rangeInput.value = '100'; // Validated by browser

			fieldDefinition.setValue.call( mockField, 150 ); // Above max

			// Should use the validated value from the range input
			expect( global.acf.val ).toHaveBeenCalledWith(
				$numberInput,
				'100',
				true
			);
		} );
	} );

	describe( 'onChange()', () => {
		const createRangeInput = ( value ) => {
			const input = document.createElement( 'input' );
			input.type = 'range';
			input.value = value;
			return [ input ];
		};

		it( 'should call setValue with input value when not busy', () => {
			const setValueSpy = jest.fn();
			mockField.setValue = setValueSpy;
			mockField.busy = false;

			fieldDefinition.onChange.call(
				mockField,
				{},
				createRangeInput( '42' )
			);

			expect( setValueSpy ).toHaveBeenCalledWith( '42' );
		} );

		it( 'should not call setValue when busy', () => {
			const setValueSpy = jest.fn();
			mockField.setValue = setValueSpy;
			mockField.busy = true;

			fieldDefinition.onChange.call(
				mockField,
				{},
				createRangeInput( '42' )
			);

			expect( setValueSpy ).not.toHaveBeenCalled();
		} );
	} );

	describe( 'Integration scenarios', () => {
		it( 'should synchronize range and number inputs', () => {
			const rangeInput = document.createElement( 'input' );
			rangeInput.type = 'range';
			rangeInput.value = '75';
			const numberInput = document.createElement( 'input' );
			numberInput.type = 'number';
			const $rangeInput = [ rangeInput ];
			const $numberInput = [ numberInput ];
			mockField.$input = jest.fn().mockReturnValue( $rangeInput );
			mockField.$inputAlt = jest.fn().mockReturnValue( $numberInput );

			// Simulate user dragging range slider
			fieldDefinition.setValue.call( mockField, '75' );

			expect( global.acf.val ).toHaveBeenNthCalledWith(
				1,
				$rangeInput,
				'75'
			);
			expect( global.acf.val ).toHaveBeenNthCalledWith(
				2,
				$numberInput,
				'75',
				true
			);
		} );
	} );
} );
