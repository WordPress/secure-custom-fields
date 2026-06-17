/**
 * Unit tests for radio field type
 */

describe( 'Radio Field', () => {
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

		// Load the radio field module
		jest.isolateModules( () => {
			require( '../../../assets/src/js/_acf-field-radio.js' );
		} );

		// Create a mock field instance with the captured definition
		mockField = {
			...fieldDefinition,
			$: jest.fn(),
			get: jest.fn(),
		};
	} );

	afterEach( () => {
		delete global.acf;
	} );

	describe( 'Field Definition', () => {
		it( 'should have type "radio"', () => {
			expect( fieldDefinition.type ).toBe( 'radio' );
		} );

		it( 'should register click and keydown events', () => {
			expect( fieldDefinition.events ).toEqual( {
				'click input[type="radio"]': 'onClick',
				'keydown input[type="radio"]': 'onKeyDownInput',
			} );
		} );
	} );

	describe( '$control()', () => {
		it( 'should find .acf-radio-list element', () => {
			fieldDefinition.$control.call( mockField );

			expect( mockField.$ ).toHaveBeenCalledWith( '.acf-radio-list' );
		} );
	} );

	describe( '$input()', () => {
		it( 'should find checked input element', () => {
			fieldDefinition.$input.call( mockField );

			expect( mockField.$ ).toHaveBeenCalledWith( 'input:checked' );
		} );
	} );

	describe( '$inputText()', () => {
		it( 'should find text input element', () => {
			fieldDefinition.$inputText.call( mockField );

			expect( mockField.$ ).toHaveBeenCalledWith( 'input[type="text"]' );
		} );
	} );

	describe( 'getValue()', () => {
		/**
		 * Creates a real radio input with the given value.
		 *
		 * @param {string} value The input value.
		 * @return {HTMLElement} The radio input.
		 */
		const createRadio = ( value ) => {
			const input = document.createElement( 'input' );
			input.type = 'radio';
			input.value = value;
			return input;
		};

		it( 'should return checked input value', () => {
			mockField.$input = jest
				.fn()
				.mockReturnValue( [ createRadio( 'option1' ) ] );
			mockField.get = jest.fn().mockReturnValue( false );

			const result = fieldDefinition.getValue.call( mockField );

			expect( result ).toBe( 'option1' );
		} );

		it( 'should return text input value when other_choice is enabled and other is selected', () => {
			const textInput = document.createElement( 'input' );
			textInput.type = 'text';
			textInput.value = 'custom value';
			mockField.$input = jest
				.fn()
				.mockReturnValue( [ createRadio( 'other' ) ] );
			mockField.$inputText = jest.fn().mockReturnValue( [ textInput ] );
			mockField.get = jest.fn( ( key ) =>
				key === 'other_choice' ? true : false
			);

			const result = fieldDefinition.getValue.call( mockField );

			expect( result ).toBe( 'custom value' );
		} );

		it( 'should return radio value when other_choice is disabled', () => {
			mockField.$input = jest
				.fn()
				.mockReturnValue( [ createRadio( 'other' ) ] );
			mockField.get = jest.fn().mockReturnValue( false );

			const result = fieldDefinition.getValue.call( mockField );

			expect( result ).toBe( 'other' );
		} );
	} );

	describe( 'onClick()', () => {
		let container;
		let input;
		let label;
		let otherLabel;
		let textInput;
		let onChange;

		beforeEach( () => {
			// Build a real radio list with two options.
			container = document.createElement( 'div' );

			label = document.createElement( 'label' );
			input = document.createElement( 'input' );
			input.type = 'radio';
			input.value = 'option1';
			label.appendChild( input );
			container.appendChild( label );

			otherLabel = document.createElement( 'label' );
			otherLabel.className = 'selected';
			const otherInput = document.createElement( 'input' );
			otherInput.type = 'radio';
			otherInput.value = 'option2';
			otherInput.checked = true;
			otherLabel.appendChild( otherInput );
			container.appendChild( otherLabel );

			textInput = document.createElement( 'input' );
			textInput.type = 'text';

			onChange = jest.fn();
			input.addEventListener( 'change', onChange );

			mockField.$el = [ container ];
			mockField.$inputText = jest.fn().mockReturnValue( [ textInput ] );
		} );

		it( 'should remove selected class from all labels', () => {
			mockField.get = jest.fn().mockReturnValue( false );

			fieldDefinition.onClick.call( mockField, {}, [ input ] );

			expect( otherLabel.classList.contains( 'selected' ) ).toBe( false );
		} );

		it( 'should add selected class to clicked label', () => {
			mockField.get = jest.fn().mockReturnValue( false );

			fieldDefinition.onClick.call( mockField, {}, [ input ] );

			expect( label.classList.contains( 'selected' ) ).toBe( true );
		} );

		it( 'should deselect when allow_null is true and already selected', () => {
			label.classList.add( 'selected' );
			input.checked = true;
			mockField.get = jest.fn( ( key ) =>
				key === 'allow_null' ? true : false
			);

			fieldDefinition.onClick.call( mockField, {}, [ input ] );

			expect( label.classList.contains( 'selected' ) ).toBe( false );
			expect( input.checked ).toBe( false );
			expect( onChange ).toHaveBeenCalled();
		} );

		it( 'should enable text input when other is selected', () => {
			input.value = 'other';
			textInput.disabled = true;
			mockField.get = jest.fn( ( key ) =>
				key === 'other_choice' ? true : false
			);

			fieldDefinition.onClick.call( mockField, {}, [ input ] );

			expect( textInput.disabled ).toBe( false );
		} );

		it( 'should disable text input when other is not selected', () => {
			mockField.get = jest.fn( ( key ) =>
				key === 'other_choice' ? true : false
			);

			fieldDefinition.onClick.call( mockField, {}, [ input ] );

			expect( textInput.disabled ).toBe( true );
		} );
	} );

	describe( 'onKeyDownInput()', () => {
		let input;
		let onChange;

		beforeEach( () => {
			input = document.createElement( 'input' );
			input.type = 'radio';
			onChange = jest.fn();
			input.addEventListener( 'change', onChange );
		} );

		it( 'should check input and trigger change on Enter key', () => {
			const mockEvent = {
				which: 13,
				preventDefault: jest.fn(),
			};

			fieldDefinition.onKeyDownInput.call( mockField, mockEvent, [
				input,
			] );

			expect( mockEvent.preventDefault ).toHaveBeenCalled();
			expect( input.checked ).toBe( true );
			expect( onChange ).toHaveBeenCalled();
		} );

		it( 'should not react to other keys', () => {
			const mockEvent = {
				which: 65, // 'A' key
				preventDefault: jest.fn(),
			};

			fieldDefinition.onKeyDownInput.call( mockField, mockEvent, [
				input,
			] );

			expect( mockEvent.preventDefault ).not.toHaveBeenCalled();
			expect( input.checked ).toBe( false );
			expect( onChange ).not.toHaveBeenCalled();
		} );
	} );
} );
