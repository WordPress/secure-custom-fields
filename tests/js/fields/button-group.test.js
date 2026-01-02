/**
 * Unit tests for button_group field type
 */

describe( 'Button Group Field', () => {
	let fieldDefinition;
	let mockField;

	beforeEach( () => {
		// Reset mocks
		fieldDefinition = null;

		// Mock @wordpress/icons
		jest.mock( '@wordpress/icons', () => ( { update: 'update-icon' } ), {
			virtual: true,
		} );

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

		// Load the button_group field module
		jest.isolateModules( () => {
			require( '../../../assets/src/js/_acf-field-button-group.js' );
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
		jest.resetModules();
	} );

	describe( 'Field Definition', () => {
		it( 'should have type "button_group"', () => {
			expect( fieldDefinition.type ).toBe( 'button_group' );
		} );

		it( 'should register click and keydown events', () => {
			expect( fieldDefinition.events ).toEqual( {
				'click input[type="radio"]': 'onClick',
				'keydown label': 'onKeyDown',
			} );
		} );
	} );

	describe( '$control()', () => {
		it( 'should find .acf-button-group element', () => {
			fieldDefinition.$control.call( mockField );

			expect( mockField.$ ).toHaveBeenCalledWith( '.acf-button-group' );
		} );
	} );

	describe( '$input()', () => {
		it( 'should find checked input element', () => {
			fieldDefinition.$input.call( mockField );

			expect( mockField.$ ).toHaveBeenCalledWith( 'input:checked' );
		} );
	} );

	describe( 'initialize()', () => {
		it( 'should call updateButtonStates', () => {
			const updateSpy = jest.fn();
			mockField.updateButtonStates = updateSpy;

			fieldDefinition.initialize.call( mockField );

			expect( updateSpy ).toHaveBeenCalled();
		} );
	} );

	describe( 'setValue()', () => {
		it( 'should check the input with matching value and trigger change', () => {
			const mockInput = {
				prop: jest.fn().mockReturnThis(),
				trigger: jest.fn().mockReturnThis(),
			};
			mockField.$ = jest.fn().mockReturnValue( mockInput );
			mockField.updateButtonStates = jest.fn();

			fieldDefinition.setValue.call( mockField, 'option2' );

			expect( mockField.$ ).toHaveBeenCalledWith(
				'input[value="option2"]'
			);
			expect( mockInput.prop ).toHaveBeenCalledWith( 'checked', true );
			expect( mockInput.trigger ).toHaveBeenCalledWith( 'change' );
		} );

		it( 'should update button states after setting value', () => {
			const updateSpy = jest.fn();
			mockField.$ = jest.fn().mockReturnValue( {
				prop: jest.fn().mockReturnThis(),
				trigger: jest.fn().mockReturnThis(),
			} );
			mockField.updateButtonStates = updateSpy;

			fieldDefinition.setValue.call( mockField, 'option1' );

			expect( updateSpy ).toHaveBeenCalled();
		} );
	} );

	describe( 'updateButtonStates()', () => {
		let mockLabels;
		let mockInput;
		let mockInputLabel;

		beforeEach( () => {
			mockInputLabel = {
				addClass: jest.fn().mockReturnThis(),
				attr: jest.fn().mockReturnThis(),
			};
			mockInput = {
				length: 1,
				parent: jest.fn().mockReturnValue( mockInputLabel ),
			};
			mockLabels = {
				removeClass: jest.fn().mockReturnThis(),
				attr: jest.fn().mockReturnThis(),
				first: jest.fn().mockReturnThis(),
			};
		} );

		it( 'should remove selected class and reset aria attributes on all labels', () => {
			const mockControl = {
				find: jest.fn().mockReturnValue( mockLabels ),
			};
			mockField.$control = jest.fn().mockReturnValue( mockControl );
			mockField.$input = jest.fn().mockReturnValue( mockInput );

			fieldDefinition.updateButtonStates.call( mockField );

			expect( mockLabels.removeClass ).toHaveBeenCalledWith( 'selected' );
			expect( mockLabels.attr ).toHaveBeenCalledWith(
				'aria-checked',
				'false'
			);
			expect( mockLabels.attr ).toHaveBeenCalledWith( 'tabindex', '-1' );
		} );

		it( 'should mark selected input label with correct attributes', () => {
			const mockControl = {
				find: jest.fn().mockReturnValue( mockLabels ),
			};
			mockField.$control = jest.fn().mockReturnValue( mockControl );
			mockField.$input = jest.fn().mockReturnValue( mockInput );

			fieldDefinition.updateButtonStates.call( mockField );

			expect( mockInputLabel.addClass ).toHaveBeenCalledWith(
				'selected'
			);
			expect( mockInputLabel.attr ).toHaveBeenCalledWith(
				'aria-checked',
				'true'
			);
			expect( mockInputLabel.attr ).toHaveBeenCalledWith(
				'tabindex',
				'0'
			);
		} );

		it( 'should set tabindex on first label when no input is checked', () => {
			mockInput.length = 0;
			const mockControl = {
				find: jest.fn().mockReturnValue( mockLabels ),
			};
			mockField.$control = jest.fn().mockReturnValue( mockControl );
			mockField.$input = jest.fn().mockReturnValue( mockInput );

			fieldDefinition.updateButtonStates.call( mockField );

			expect( mockLabels.first ).toHaveBeenCalled();
			expect( mockLabels.attr ).toHaveBeenCalledWith( 'tabindex', '0' );
		} );
	} );

	describe( 'onClick()', () => {
		it( 'should call selectButton with parent label', () => {
			const selectSpy = jest.fn();
			mockField.selectButton = selectSpy;
			const mockLabel = { hasClass: jest.fn() };
			const mockEl = { parent: jest.fn().mockReturnValue( mockLabel ) };

			fieldDefinition.onClick.call( mockField, {}, mockEl );

			expect( mockEl.parent ).toHaveBeenCalledWith( 'label' );
			expect( selectSpy ).toHaveBeenCalledWith( mockLabel );
		} );
	} );

	describe( 'onKeyDown()', () => {
		let mockLabels;
		let mockEvent;
		let mockLabel;

		beforeEach( () => {
			mockLabel = {};
			mockLabels = {
				index: jest.fn().mockReturnValue( 1 ),
				length: 3,
				eq: jest.fn().mockReturnValue( {
					attr: jest.fn().mockReturnThis(),
					trigger: jest.fn().mockReturnThis(),
				} ),
				attr: jest.fn().mockReturnThis(),
			};
			const mockControl = {
				find: jest.fn().mockReturnValue( mockLabels ),
			};
			mockField.$control = jest.fn().mockReturnValue( mockControl );
			mockField.selectButton = jest.fn();
		} );

		it( 'should select button on Space key (32)', () => {
			mockEvent = { which: 32, preventDefault: jest.fn() };

			fieldDefinition.onKeyDown.call( mockField, mockEvent, mockLabel );

			expect( mockEvent.preventDefault ).toHaveBeenCalled();
			expect( mockField.selectButton ).toHaveBeenCalledWith( mockLabel );
		} );

		it( 'should select button on Enter key (13)', () => {
			mockEvent = { which: 13, preventDefault: jest.fn() };

			fieldDefinition.onKeyDown.call( mockField, mockEvent, mockLabel );

			expect( mockEvent.preventDefault ).toHaveBeenCalled();
			expect( mockField.selectButton ).toHaveBeenCalledWith( mockLabel );
		} );

		it( 'should move to previous button on left arrow (37)', () => {
			mockEvent = { which: 37, preventDefault: jest.fn() };

			fieldDefinition.onKeyDown.call( mockField, mockEvent, mockLabel );

			expect( mockEvent.preventDefault ).toHaveBeenCalled();
			expect( mockLabels.eq ).toHaveBeenCalledWith( 0 ); // Previous index
		} );

		it( 'should move to next button on right arrow (39)', () => {
			mockEvent = { which: 39, preventDefault: jest.fn() };

			fieldDefinition.onKeyDown.call( mockField, mockEvent, mockLabel );

			expect( mockEvent.preventDefault ).toHaveBeenCalled();
			expect( mockLabels.eq ).toHaveBeenCalledWith( 2 ); // Next index
		} );

		it( 'should wrap to last button when at start and pressing left', () => {
			mockLabels.index.mockReturnValue( 0 );
			mockEvent = { which: 37, preventDefault: jest.fn() };

			fieldDefinition.onKeyDown.call( mockField, mockEvent, mockLabel );

			expect( mockLabels.eq ).toHaveBeenCalledWith( 2 ); // Last index
		} );

		it( 'should wrap to first button when at end and pressing right', () => {
			mockLabels.index.mockReturnValue( 2 );
			mockEvent = { which: 39, preventDefault: jest.fn() };

			fieldDefinition.onKeyDown.call( mockField, mockEvent, mockLabel );

			expect( mockLabels.eq ).toHaveBeenCalledWith( 0 ); // First index
		} );

		it( 'should move to previous on up arrow (38)', () => {
			mockEvent = { which: 38, preventDefault: jest.fn() };

			fieldDefinition.onKeyDown.call( mockField, mockEvent, mockLabel );

			expect( mockEvent.preventDefault ).toHaveBeenCalled();
			expect( mockLabels.eq ).toHaveBeenCalledWith( 0 );
		} );

		it( 'should move to next on down arrow (40)', () => {
			mockEvent = { which: 40, preventDefault: jest.fn() };

			fieldDefinition.onKeyDown.call( mockField, mockEvent, mockLabel );

			expect( mockEvent.preventDefault ).toHaveBeenCalled();
			expect( mockLabels.eq ).toHaveBeenCalledWith( 2 );
		} );
	} );

	describe( 'selectButton()', () => {
		let mockElement;
		let mockRadio;

		beforeEach( () => {
			mockRadio = {
				prop: jest.fn().mockReturnThis(),
				trigger: jest.fn().mockReturnThis(),
			};
			mockElement = {
				find: jest.fn().mockReturnValue( mockRadio ),
				hasClass: jest.fn().mockReturnValue( false ),
			};
			mockField.updateButtonStates = jest.fn();
			mockField.get = jest.fn().mockReturnValue( false );
		} );

		it( 'should check radio and trigger change', () => {
			fieldDefinition.selectButton.call( mockField, mockElement );

			expect( mockElement.find ).toHaveBeenCalledWith(
				'input[type="radio"]'
			);
			expect( mockRadio.prop ).toHaveBeenCalledWith( 'checked', true );
			expect( mockRadio.trigger ).toHaveBeenCalledWith( 'change' );
		} );

		it( 'should update button states after selection', () => {
			fieldDefinition.selectButton.call( mockField, mockElement );

			expect( mockField.updateButtonStates ).toHaveBeenCalled();
		} );

		it( 'should deselect when allow_null is true and already selected', () => {
			mockElement.hasClass.mockReturnValue( true );
			mockField.get = jest.fn( ( key ) =>
				key === 'allow_null' ? true : false
			);

			fieldDefinition.selectButton.call( mockField, mockElement );

			expect( mockRadio.prop ).toHaveBeenCalledWith( 'checked', false );
		} );

		it( 'should not deselect when allow_null is false', () => {
			mockElement.hasClass.mockReturnValue( true );
			mockField.get = jest.fn().mockReturnValue( false );

			fieldDefinition.selectButton.call( mockField, mockElement );

			// First call is check true, should not be followed by check false
			expect( mockRadio.prop ).toHaveBeenCalledWith( 'checked', true );
			expect( mockRadio.prop ).not.toHaveBeenCalledWith(
				'checked',
				false
			);
		} );
	} );
} );
