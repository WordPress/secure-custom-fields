/**
 * Unit tests for url field type
 */

describe( 'URL Field', () => {
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

		// Load the url field module
		jest.isolateModules( () => {
			require( '../../../assets/src/js/_acf-field-url.js' );
		} );

		// Create a mock field instance with the captured definition
		mockField = {
			...fieldDefinition,
			$: jest.fn(),
			val: jest.fn(),
		};
	} );

	afterEach( () => {
		delete global.acf;
	} );

	describe( 'Field Definition', () => {
		it( 'should have type "url"', () => {
			expect( fieldDefinition.type ).toBe( 'url' );
		} );

		it( 'should register keyup event on url input', () => {
			expect( fieldDefinition.events ).toEqual( {
				'keyup input[type="url"]': 'onkeyup',
			} );
		} );
	} );

	describe( '$control()', () => {
		it( 'should find .acf-input-wrap element', () => {
			fieldDefinition.$control.call( mockField );

			expect( mockField.$ ).toHaveBeenCalledWith( '.acf-input-wrap' );
		} );
	} );

	describe( '$input()', () => {
		it( 'should find url input element', () => {
			fieldDefinition.$input.call( mockField );

			expect( mockField.$ ).toHaveBeenCalledWith( 'input[type="url"]' );
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

	describe( 'isValid()', () => {
		it( 'should return false for empty value', () => {
			mockField.val = jest.fn().mockReturnValue( '' );

			const result = fieldDefinition.isValid.call( mockField );

			expect( result ).toBe( false );
		} );

		it( 'should return false for null value', () => {
			mockField.val = jest.fn().mockReturnValue( null );

			const result = fieldDefinition.isValid.call( mockField );

			expect( result ).toBe( false );
		} );

		it( 'should return true for URL with protocol', () => {
			mockField.val = jest.fn().mockReturnValue( 'https://example.com' );

			const result = fieldDefinition.isValid.call( mockField );

			expect( result ).toBe( true );
		} );

		it( 'should return true for http URL', () => {
			mockField.val = jest.fn().mockReturnValue( 'http://example.com' );

			const result = fieldDefinition.isValid.call( mockField );

			expect( result ).toBe( true );
		} );

		it( 'should return true for ftp URL', () => {
			mockField.val = jest
				.fn()
				.mockReturnValue( 'ftp://files.example.com' );

			const result = fieldDefinition.isValid.call( mockField );

			expect( result ).toBe( true );
		} );

		it( 'should return true for protocol-relative URL', () => {
			mockField.val = jest.fn().mockReturnValue( '//example.com/path' );

			const result = fieldDefinition.isValid.call( mockField );

			expect( result ).toBe( true );
		} );

		it( 'should return false for URL without protocol', () => {
			mockField.val = jest.fn().mockReturnValue( 'example.com' );

			const result = fieldDefinition.isValid.call( mockField );

			expect( result ).toBe( false );
		} );

		it( 'should return false for relative path', () => {
			mockField.val = jest.fn().mockReturnValue( '/path/to/page' );

			const result = fieldDefinition.isValid.call( mockField );

			expect( result ).toBe( false );
		} );

		it( 'should return false for malformed URL', () => {
			mockField.val = jest.fn().mockReturnValue( 'not a url' );

			const result = fieldDefinition.isValid.call( mockField );

			expect( result ).toBe( false );
		} );

		it( 'should return true for URL with port', () => {
			mockField.val = jest
				.fn()
				.mockReturnValue( 'http://localhost:8080/path' );

			const result = fieldDefinition.isValid.call( mockField );

			expect( result ).toBe( true );
		} );

		it( 'should return true for URL with query string', () => {
			mockField.val = jest
				.fn()
				.mockReturnValue( 'https://example.com?foo=bar' );

			const result = fieldDefinition.isValid.call( mockField );

			expect( result ).toBe( true );
		} );

		it( 'should return true for URL with fragment', () => {
			mockField.val = jest
				.fn()
				.mockReturnValue( 'https://example.com#section' );

			const result = fieldDefinition.isValid.call( mockField );

			expect( result ).toBe( true );
		} );
	} );

	describe( 'render()', () => {
		let controlEl;

		beforeEach( () => {
			controlEl = document.createElement( 'div' );
			controlEl.className = 'acf-input-wrap';
			mockField.$control = jest.fn().mockReturnValue( [ controlEl ] );
		} );

		it( 'should add -valid class when URL is valid', () => {
			mockField.isValid = jest.fn().mockReturnValue( true );

			fieldDefinition.render.call( mockField );

			expect( controlEl.classList.contains( '-valid' ) ).toBe( true );
		} );

		it( 'should remove -valid class when URL is invalid', () => {
			controlEl.classList.add( '-valid' );
			mockField.isValid = jest.fn().mockReturnValue( false );

			fieldDefinition.render.call( mockField );

			expect( controlEl.classList.contains( '-valid' ) ).toBe( false );
		} );
	} );

	describe( 'onkeyup()', () => {
		it( 'should call render method', () => {
			const renderSpy = jest.fn();
			mockField.render = renderSpy;

			fieldDefinition.onkeyup.call( mockField, {}, {} );

			expect( renderSpy ).toHaveBeenCalled();
		} );
	} );

	describe( 'Integration scenarios', () => {
		it( 'should update validation state as user types', () => {
			const controlEl = document.createElement( 'div' );
			controlEl.className = 'acf-input-wrap';
			mockField.$control = jest.fn().mockReturnValue( [ controlEl ] );

			// Initially empty - invalid
			mockField.val = jest.fn().mockReturnValue( '' );
			fieldDefinition.render.call( mockField );
			expect( controlEl.classList.contains( '-valid' ) ).toBe( false );

			// User types partial URL - still invalid
			mockField.val = jest.fn().mockReturnValue( 'https:/' );
			fieldDefinition.render.call( mockField );
			expect( controlEl.classList.contains( '-valid' ) ).toBe( false );

			// User completes URL - valid
			mockField.val = jest.fn().mockReturnValue( 'https://example.com' );
			fieldDefinition.render.call( mockField );
			expect( controlEl.classList.contains( '-valid' ) ).toBe( true );
		} );
	} );
} );
