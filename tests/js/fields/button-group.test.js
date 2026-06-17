/**
 * Unit tests for button_group field type
 */

describe( 'Button Group Field', () => {
	let fieldDefinition;
	let mockField;

	/**
	 * Builds a real button group DOM fixture with three options.
	 *
	 * @param {number} checkedIndex Index of the checked option, or -1.
	 * @return {Object} The container, control, labels and inputs.
	 */
	const createButtonGroup = ( checkedIndex = -1 ) => {
		const container = document.createElement( 'div' );
		const control = document.createElement( 'div' );
		control.className = 'acf-button-group';
		container.appendChild( control );

		const labels = [];
		const inputs = [];
		[ 'option1', 'option2', 'option3' ].forEach( ( value, index ) => {
			const label = document.createElement( 'label' );
			const input = document.createElement( 'input' );
			input.type = 'radio';
			input.name = 'group';
			input.value = value;
			if ( index === checkedIndex ) {
				input.checked = true;
				label.className = 'selected';
			}
			label.appendChild( input );
			control.appendChild( label );
			labels.push( label );
			inputs.push( input );
		} );

		return { container, control, labels, inputs };
	};

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
			const { container, inputs } = createButtonGroup();
			const onChange = jest.fn();
			inputs[ 1 ].addEventListener( 'change', onChange );
			mockField.$el = [ container ];
			mockField.updateButtonStates = jest.fn();

			fieldDefinition.setValue.call( mockField, 'option2' );

			expect( inputs[ 1 ].checked ).toBe( true );
			expect( onChange ).toHaveBeenCalled();
		} );

		it( 'should update button states after setting value', () => {
			const { container } = createButtonGroup();
			const updateSpy = jest.fn();
			mockField.$el = [ container ];
			mockField.updateButtonStates = updateSpy;

			fieldDefinition.setValue.call( mockField, 'option1' );

			expect( updateSpy ).toHaveBeenCalled();
		} );
	} );

	describe( 'updateButtonStates()', () => {
		it( 'should remove selected class and reset aria attributes on all labels', () => {
			const { control, labels } = createButtonGroup();
			labels.forEach( ( label ) => {
				label.classList.add( 'selected' );
				label.setAttribute( 'aria-checked', 'true' );
				label.setAttribute( 'tabindex', '0' );
			} );
			mockField.$control = jest.fn().mockReturnValue( [ control ] );
			mockField.$input = jest.fn().mockReturnValue( [] );

			fieldDefinition.updateButtonStates.call( mockField );

			labels.forEach( ( label ) => {
				expect( label.classList.contains( 'selected' ) ).toBe( false );
				expect( label.getAttribute( 'aria-checked' ) ).toBe( 'false' );
			} );
			expect( labels[ 1 ].getAttribute( 'tabindex' ) ).toBe( '-1' );
		} );

		it( 'should mark selected input label with correct attributes', () => {
			const { control, labels, inputs } = createButtonGroup( 1 );
			mockField.$control = jest.fn().mockReturnValue( [ control ] );
			mockField.$input = jest.fn().mockReturnValue( [ inputs[ 1 ] ] );

			fieldDefinition.updateButtonStates.call( mockField );

			expect( labels[ 1 ].classList.contains( 'selected' ) ).toBe( true );
			expect( labels[ 1 ].getAttribute( 'aria-checked' ) ).toBe( 'true' );
			expect( labels[ 1 ].getAttribute( 'tabindex' ) ).toBe( '0' );
		} );

		it( 'should set tabindex on first label when no input is checked', () => {
			const { control, labels } = createButtonGroup();
			mockField.$control = jest.fn().mockReturnValue( [ control ] );
			mockField.$input = jest.fn().mockReturnValue( [] );

			fieldDefinition.updateButtonStates.call( mockField );

			expect( labels[ 0 ].getAttribute( 'tabindex' ) ).toBe( '0' );
		} );
	} );

	describe( 'onClick()', () => {
		it( 'should call selectButton with parent label', () => {
			const { labels, inputs } = createButtonGroup();
			const selectSpy = jest.fn();
			mockField.selectButton = selectSpy;

			fieldDefinition.onClick.call( mockField, {}, [ inputs[ 1 ] ] );

			expect( selectSpy ).toHaveBeenCalledWith( labels[ 1 ] );
		} );
	} );

	describe( 'onKeyDown()', () => {
		let control;
		let labels;
		let mockEvent;

		beforeEach( () => {
			( { control, labels } = createButtonGroup() );
			mockField.$control = jest.fn().mockReturnValue( [ control ] );
			mockField.selectButton = jest.fn();
		} );

		it( 'should select button on Space key (32)', () => {
			mockEvent = { which: 32, preventDefault: jest.fn() };

			fieldDefinition.onKeyDown.call( mockField, mockEvent, [
				labels[ 1 ],
			] );

			expect( mockEvent.preventDefault ).toHaveBeenCalled();
			expect( mockField.selectButton ).toHaveBeenCalledWith(
				labels[ 1 ]
			);
		} );

		it( 'should select button on Enter key (13)', () => {
			mockEvent = { which: 13, preventDefault: jest.fn() };

			fieldDefinition.onKeyDown.call( mockField, mockEvent, [
				labels[ 1 ],
			] );

			expect( mockEvent.preventDefault ).toHaveBeenCalled();
			expect( mockField.selectButton ).toHaveBeenCalledWith(
				labels[ 1 ]
			);
		} );

		it( 'should move to previous button on left arrow (37)', () => {
			mockEvent = { which: 37, preventDefault: jest.fn() };

			fieldDefinition.onKeyDown.call( mockField, mockEvent, [
				labels[ 1 ],
			] );

			expect( mockEvent.preventDefault ).toHaveBeenCalled();
			expect( labels[ 0 ].getAttribute( 'tabindex' ) ).toBe( '0' ); // Previous index
		} );

		it( 'should move to next button on right arrow (39)', () => {
			mockEvent = { which: 39, preventDefault: jest.fn() };

			fieldDefinition.onKeyDown.call( mockField, mockEvent, [
				labels[ 1 ],
			] );

			expect( mockEvent.preventDefault ).toHaveBeenCalled();
			expect( labels[ 2 ].getAttribute( 'tabindex' ) ).toBe( '0' ); // Next index
		} );

		it( 'should wrap to last button when at start and pressing left', () => {
			mockEvent = { which: 37, preventDefault: jest.fn() };

			fieldDefinition.onKeyDown.call( mockField, mockEvent, [
				labels[ 0 ],
			] );

			expect( labels[ 2 ].getAttribute( 'tabindex' ) ).toBe( '0' ); // Last index
		} );

		it( 'should wrap to first button when at end and pressing right', () => {
			mockEvent = { which: 39, preventDefault: jest.fn() };

			fieldDefinition.onKeyDown.call( mockField, mockEvent, [
				labels[ 2 ],
			] );

			expect( labels[ 0 ].getAttribute( 'tabindex' ) ).toBe( '0' ); // First index
		} );

		it( 'should move to previous on up arrow (38)', () => {
			mockEvent = { which: 38, preventDefault: jest.fn() };

			fieldDefinition.onKeyDown.call( mockField, mockEvent, [
				labels[ 1 ],
			] );

			expect( mockEvent.preventDefault ).toHaveBeenCalled();
			expect( labels[ 0 ].getAttribute( 'tabindex' ) ).toBe( '0' );
		} );

		it( 'should move to next on down arrow (40)', () => {
			mockEvent = { which: 40, preventDefault: jest.fn() };

			fieldDefinition.onKeyDown.call( mockField, mockEvent, [
				labels[ 1 ],
			] );

			expect( mockEvent.preventDefault ).toHaveBeenCalled();
			expect( labels[ 2 ].getAttribute( 'tabindex' ) ).toBe( '0' );
		} );
	} );

	describe( 'selectButton()', () => {
		let label;
		let radio;
		let onChange;

		beforeEach( () => {
			const { labels, inputs } = createButtonGroup();
			label = labels[ 1 ];
			radio = inputs[ 1 ];
			onChange = jest.fn();
			radio.addEventListener( 'change', onChange );
			mockField.updateButtonStates = jest.fn();
			mockField.get = jest.fn().mockReturnValue( false );
		} );

		it( 'should check radio and trigger change', () => {
			fieldDefinition.selectButton.call( mockField, label );

			expect( radio.checked ).toBe( true );
			expect( onChange ).toHaveBeenCalled();
		} );

		it( 'should update button states after selection', () => {
			fieldDefinition.selectButton.call( mockField, label );

			expect( mockField.updateButtonStates ).toHaveBeenCalled();
		} );

		it( 'should deselect when allow_null is true and already selected', () => {
			label.classList.add( 'selected' );
			mockField.get = jest.fn( ( key ) =>
				key === 'allow_null' ? true : false
			);

			fieldDefinition.selectButton.call( mockField, label );

			expect( radio.checked ).toBe( false );
		} );

		it( 'should not deselect when allow_null is false', () => {
			label.classList.add( 'selected' );
			mockField.get = jest.fn().mockReturnValue( false );

			fieldDefinition.selectButton.call( mockField, label );

			// Selecting must leave the radio checked
			expect( radio.checked ).toBe( true );
		} );
	} );
} );
