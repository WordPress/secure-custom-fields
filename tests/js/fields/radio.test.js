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
		it( 'should return checked input value', () => {
			const mockInput = { val: jest.fn().mockReturnValue( 'option1' ) };
			mockField.$input = jest.fn().mockReturnValue( mockInput );
			mockField.get = jest.fn().mockReturnValue( false );

			const result = fieldDefinition.getValue.call( mockField );

			expect( result ).toBe( 'option1' );
		} );

		it( 'should return text input value when other_choice is enabled and other is selected', () => {
			const mockInput = { val: jest.fn().mockReturnValue( 'other' ) };
			const mockTextInput = {
				val: jest.fn().mockReturnValue( 'custom value' ),
			};
			mockField.$input = jest.fn().mockReturnValue( mockInput );
			mockField.$inputText = jest.fn().mockReturnValue( mockTextInput );
			mockField.get = jest.fn( ( key ) =>
				key === 'other_choice' ? true : false
			);

			const result = fieldDefinition.getValue.call( mockField );

			expect( result ).toBe( 'custom value' );
		} );

		it( 'should return radio value when other_choice is disabled', () => {
			const mockInput = { val: jest.fn().mockReturnValue( 'other' ) };
			mockField.$input = jest.fn().mockReturnValue( mockInput );
			mockField.get = jest.fn().mockReturnValue( false );

			const result = fieldDefinition.getValue.call( mockField );

			expect( result ).toBe( 'other' );
		} );
	} );

	describe( 'onClick()', () => {
		let mockEl;
		let mockLabel;

		beforeEach( () => {
			mockLabel = {
				hasClass: jest.fn().mockReturnValue( false ),
				addClass: jest.fn().mockReturnThis(),
				removeClass: jest.fn().mockReturnThis(),
			};
			mockEl = {
				parent: jest.fn().mockReturnValue( mockLabel ),
				val: jest.fn().mockReturnValue( 'option1' ),
				prop: jest.fn().mockReturnThis(),
				trigger: jest.fn().mockReturnThis(),
			};
			mockField.$ = jest.fn().mockReturnValue( {
				removeClass: jest.fn().mockReturnThis(),
			} );
			mockField.$inputText = jest.fn().mockReturnValue( {
				prop: jest.fn().mockReturnThis(),
			} );
		} );

		it( 'should remove selected class from all labels', () => {
			const mockSelected = { removeClass: jest.fn() };
			mockField.$ = jest.fn().mockReturnValue( mockSelected );
			mockField.get = jest.fn().mockReturnValue( false );

			fieldDefinition.onClick.call( mockField, {}, mockEl );

			expect( mockField.$ ).toHaveBeenCalledWith( '.selected' );
			expect( mockSelected.removeClass ).toHaveBeenCalledWith(
				'selected'
			);
		} );

		it( 'should add selected class to clicked label', () => {
			mockField.get = jest.fn().mockReturnValue( false );

			fieldDefinition.onClick.call( mockField, {}, mockEl );

			expect( mockLabel.addClass ).toHaveBeenCalledWith( 'selected' );
		} );

		it( 'should deselect when allow_null is true and already selected', () => {
			mockLabel.hasClass.mockReturnValue( true );
			mockField.get = jest.fn( ( key ) =>
				key === 'allow_null' ? true : false
			);

			fieldDefinition.onClick.call( mockField, {}, mockEl );

			expect( mockLabel.removeClass ).toHaveBeenCalledWith( 'selected' );
			expect( mockEl.prop ).toHaveBeenCalledWith( 'checked', false );
			expect( mockEl.trigger ).toHaveBeenCalledWith( 'change' );
		} );

		it( 'should enable text input when other is selected', () => {
			const mockTextInput = { prop: jest.fn().mockReturnThis() };
			mockField.$inputText = jest.fn().mockReturnValue( mockTextInput );
			mockEl.val.mockReturnValue( 'other' );
			mockField.get = jest.fn( ( key ) =>
				key === 'other_choice' ? true : false
			);

			fieldDefinition.onClick.call( mockField, {}, mockEl );

			expect( mockTextInput.prop ).toHaveBeenCalledWith(
				'disabled',
				false
			);
		} );

		it( 'should disable text input when other is not selected', () => {
			const mockTextInput = { prop: jest.fn().mockReturnThis() };
			mockField.$inputText = jest.fn().mockReturnValue( mockTextInput );
			mockEl.val.mockReturnValue( 'option1' );
			mockField.get = jest.fn( ( key ) =>
				key === 'other_choice' ? true : false
			);

			fieldDefinition.onClick.call( mockField, {}, mockEl );

			expect( mockTextInput.prop ).toHaveBeenCalledWith(
				'disabled',
				true
			);
		} );
	} );

	describe( 'onKeyDownInput()', () => {
		it( 'should check input and trigger change on Enter key', () => {
			const mockEvent = {
				which: 13,
				preventDefault: jest.fn(),
			};
			const mockInput = {
				prop: jest.fn().mockReturnThis(),
				trigger: jest.fn().mockReturnThis(),
			};

			fieldDefinition.onKeyDownInput.call(
				mockField,
				mockEvent,
				mockInput
			);

			expect( mockEvent.preventDefault ).toHaveBeenCalled();
			expect( mockInput.prop ).toHaveBeenCalledWith( 'checked', true );
			expect( mockInput.trigger ).toHaveBeenCalledWith( 'change' );
		} );

		it( 'should not react to other keys', () => {
			const mockEvent = {
				which: 65, // 'A' key
				preventDefault: jest.fn(),
			};
			const mockInput = {
				prop: jest.fn().mockReturnThis(),
				trigger: jest.fn().mockReturnThis(),
			};

			fieldDefinition.onKeyDownInput.call(
				mockField,
				mockEvent,
				mockInput
			);

			expect( mockEvent.preventDefault ).not.toHaveBeenCalled();
			expect( mockInput.prop ).not.toHaveBeenCalled();
		} );
	} );
} );
