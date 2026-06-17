/**
 * Unit tests for true_false field type
 */

describe( 'True/False Field', () => {
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

		// Load the true_false field module
		jest.isolateModules( () => {
			require( '../../../assets/src/js/_acf-field-true-false.js' );
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

	describe( 'Field Definition', () => {
		it( 'should have type "true_false"', () => {
			expect( fieldDefinition.type ).toBe( 'true_false' );
		} );

		it( 'should register switch-related events', () => {
			expect( fieldDefinition.events ).toEqual( {
				'change .acf-switch-input': 'onChange',
				'focus .acf-switch-input': 'onFocus',
				'blur .acf-switch-input': 'onBlur',
				'keypress .acf-switch-input': 'onKeypress',
			} );
		} );
	} );

	describe( '$input()', () => {
		it( 'should find checkbox input element', () => {
			fieldDefinition.$input.call( mockField );

			expect( mockField.$ ).toHaveBeenCalledWith(
				'input[type="checkbox"]'
			);
		} );
	} );

	describe( '$switch()', () => {
		it( 'should find .acf-switch element', () => {
			fieldDefinition.$switch.call( mockField );

			expect( mockField.$ ).toHaveBeenCalledWith( '.acf-switch' );
		} );
	} );

	describe( 'getValue()', () => {
		/**
		 * Creates a real checkbox input.
		 *
		 * @param {boolean} checked Whether the input is checked.
		 * @return {HTMLElement} The checkbox input.
		 */
		const createCheckbox = ( checked ) => {
			const input = document.createElement( 'input' );
			input.type = 'checkbox';
			input.checked = checked;
			return input;
		};

		it( 'should return 1 when checked', () => {
			mockField.$input = jest
				.fn()
				.mockReturnValue( [ createCheckbox( true ) ] );

			const result = fieldDefinition.getValue.call( mockField );

			expect( result ).toBe( 1 );
		} );

		it( 'should return 0 when unchecked', () => {
			mockField.$input = jest
				.fn()
				.mockReturnValue( [ createCheckbox( false ) ] );

			const result = fieldDefinition.getValue.call( mockField );

			expect( result ).toBe( 0 );
		} );
	} );

	describe( 'initialize()', () => {
		it( 'should call render method', () => {
			const renderSpy = jest.fn();
			mockField.render = renderSpy;

			fieldDefinition.initialize.call( mockField );

			expect( renderSpy ).toHaveBeenCalled();
		} );
	} );

	describe( 'render()', () => {
		let mockSwitch;
		let mockOn;
		let mockOff;
		let onEl;
		let offEl;

		beforeEach( () => {
			// jQuery .width() is still used for measurement, so the on/off
			// wrappers mock it; style updates land on the real elements.
			onEl = document.createElement( 'span' );
			offEl = document.createElement( 'span' );
			mockOn = [ onEl ];
			mockOn.width = jest.fn().mockReturnValue( 50 );
			mockOff = [ offEl ];
			mockOff.width = jest.fn().mockReturnValue( 40 );
			mockSwitch = {
				length: 1,
				children: jest.fn( ( selector ) => {
					if ( selector === '.acf-switch-on' ) {
						return mockOn;
					}
					if ( selector === '.acf-switch-off' ) {
						return mockOff;
					}
					return { width: jest.fn() };
				} ),
			};
			mockField.$switch = jest.fn().mockReturnValue( mockSwitch );
		} );

		it( 'should set min-width based on max of on/off widths', () => {
			fieldDefinition.render.call( mockField );

			// Max width is 50 (from mockOn)
			expect( onEl.style.minWidth ).toBe( '50px' );
			expect( offEl.style.minWidth ).toBe( '50px' );
		} );

		it( 'should bail early if no switch element', () => {
			mockSwitch.length = 0;
			mockField.$switch = jest.fn().mockReturnValue( mockSwitch );

			fieldDefinition.render.call( mockField );

			expect( mockSwitch.children ).not.toHaveBeenCalled();
		} );

		it( 'should bail early if width is 0', () => {
			mockOn.width.mockReturnValue( 0 );
			mockOff.width.mockReturnValue( 0 );

			fieldDefinition.render.call( mockField );

			expect( onEl.style.minWidth ).toBe( '' );
			expect( offEl.style.minWidth ).toBe( '' );
		} );
	} );

	describe( 'switchOn()', () => {
		it( 'should check the input and add -on class', () => {
			const input = document.createElement( 'input' );
			input.type = 'checkbox';
			const switchEl = document.createElement( 'div' );
			mockField.$input = jest.fn().mockReturnValue( [ input ] );
			mockField.$switch = jest.fn().mockReturnValue( [ switchEl ] );

			fieldDefinition.switchOn.call( mockField );

			expect( input.checked ).toBe( true );
			expect( switchEl.classList.contains( '-on' ) ).toBe( true );
		} );
	} );

	describe( 'switchOff()', () => {
		it( 'should uncheck the input and remove -on class', () => {
			const input = document.createElement( 'input' );
			input.type = 'checkbox';
			input.checked = true;
			const switchEl = document.createElement( 'div' );
			switchEl.className = '-on';
			mockField.$input = jest.fn().mockReturnValue( [ input ] );
			mockField.$switch = jest.fn().mockReturnValue( [ switchEl ] );

			fieldDefinition.switchOff.call( mockField );

			expect( input.checked ).toBe( false );
			expect( switchEl.classList.contains( '-on' ) ).toBe( false );
		} );
	} );

	describe( 'onChange()', () => {
		const createCheckbox = ( checked ) => {
			const input = document.createElement( 'input' );
			input.type = 'checkbox';
			input.checked = checked;
			return input;
		};

		it( 'should call switchOn when input is checked', () => {
			const switchOnSpy = jest.fn();
			mockField.switchOn = switchOnSpy;
			mockField.switchOff = jest.fn();

			fieldDefinition.onChange.call( mockField, {}, [
				createCheckbox( true ),
			] );

			expect( switchOnSpy ).toHaveBeenCalled();
		} );

		it( 'should call switchOff when input is unchecked', () => {
			const switchOffSpy = jest.fn();
			mockField.switchOn = jest.fn();
			mockField.switchOff = switchOffSpy;

			fieldDefinition.onChange.call( mockField, {}, [
				createCheckbox( false ),
			] );

			expect( switchOffSpy ).toHaveBeenCalled();
		} );
	} );

	describe( 'onFocus()', () => {
		it( 'should add -focus class to switch', () => {
			const switchEl = document.createElement( 'div' );
			mockField.$switch = jest.fn().mockReturnValue( [ switchEl ] );

			fieldDefinition.onFocus.call( mockField, {}, {} );

			expect( switchEl.classList.contains( '-focus' ) ).toBe( true );
		} );
	} );

	describe( 'onBlur()', () => {
		it( 'should remove -focus class from switch', () => {
			const switchEl = document.createElement( 'div' );
			switchEl.className = '-focus';
			mockField.$switch = jest.fn().mockReturnValue( [ switchEl ] );

			fieldDefinition.onBlur.call( mockField, {}, {} );

			expect( switchEl.classList.contains( '-focus' ) ).toBe( false );
		} );
	} );

	describe( 'onKeypress()', () => {
		it( 'should call switchOff on left arrow key (37)', () => {
			const switchOffSpy = jest.fn();
			mockField.switchOff = switchOffSpy;

			fieldDefinition.onKeypress.call( mockField, { keyCode: 37 }, {} );

			expect( switchOffSpy ).toHaveBeenCalled();
		} );

		it( 'should call switchOn on right arrow key (39)', () => {
			const switchOnSpy = jest.fn();
			mockField.switchOn = switchOnSpy;

			fieldDefinition.onKeypress.call( mockField, { keyCode: 39 }, {} );

			expect( switchOnSpy ).toHaveBeenCalled();
		} );

		it( 'should not react to other keys', () => {
			mockField.switchOn = jest.fn();
			mockField.switchOff = jest.fn();

			fieldDefinition.onKeypress.call( mockField, { keyCode: 13 }, {} );

			expect( mockField.switchOn ).not.toHaveBeenCalled();
			expect( mockField.switchOff ).not.toHaveBeenCalled();
		} );
	} );
} );
