/**
 * Unit tests for field processing utilities
 */

import {
	getSCFFields,
	resolveImageAttribute,
	processFieldBinding,
	formatFieldLabel,
	getFieldLabel,
} from '../../../assets/src/js/bindings/field-processing';

describe( 'Field Processing Utils', () => {
	describe( 'getSCFFields', () => {
		it( 'should extract source fields from post entity', () => {
			const post = {
				acf: {
					field1: 'value1',
					field1_source: { type: 'text', formatted_value: 'value1' },
					field2: 'value2',
					field2_source: {
						type: 'textarea',
						formatted_value: 'value2',
					},
				},
			};

			const result = getSCFFields( post );
			expect( result ).toEqual( {
				field1: { type: 'text', formatted_value: 'value1' },
				field2: { type: 'textarea', formatted_value: 'value2' },
			} );
		} );

		it( 'should return empty object when post has no acf property', () => {
			const post = { id: 1, title: 'Test' };
			const result = getSCFFields( post );
			expect( result ).toEqual( {} );
		} );

		it( 'should return empty object when post is null', () => {
			const result = getSCFFields( null );
			expect( result ).toEqual( {} );
		} );

		it( 'should return empty object when acf is null', () => {
			const post = { acf: null };
			const result = getSCFFields( post );
			expect( result ).toEqual( {} );
		} );

		it( 'should only include fields with _source counterparts', () => {
			const post = {
				acf: {
					field1: 'value1',
					field1_source: { type: 'text', formatted_value: 'value1' },
					field2: 'value2',
					// No field2_source
				},
			};

			const result = getSCFFields( post );
			expect( result ).toEqual( {
				field1: { type: 'text', formatted_value: 'value1' },
			} );
		} );
	} );

	describe( 'resolveImageAttribute', () => {
		const imageObj = {
			id: 123,
			ID: 123,
			url: 'https://example.com/image.jpg',
			alt: 'Test image',
			title: 'Test Title',
		};

		it( 'should resolve url attribute', () => {
			expect( resolveImageAttribute( imageObj, 'url' ) ).toBe(
				'https://example.com/image.jpg'
			);
		} );

		it( 'should resolve alt attribute', () => {
			expect( resolveImageAttribute( imageObj, 'alt' ) ).toBe(
				'Test image'
			);
		} );

		it( 'should resolve title attribute', () => {
			expect( resolveImageAttribute( imageObj, 'title' ) ).toBe(
				'Test Title'
			);
		} );

		it( 'should resolve id attribute', () => {
			expect( resolveImageAttribute( imageObj, 'id' ) ).toBe( 123 );
		} );

		it( 'should fallback to ID property for id attribute', () => {
			const img = { ID: 456 };
			expect( resolveImageAttribute( img, 'id' ) ).toBe( 456 );
		} );

		it( 'should return empty string for missing attributes', () => {
			const img = {};
			expect( resolveImageAttribute( img, 'url' ) ).toBe( '' );
			expect( resolveImageAttribute( img, 'alt' ) ).toBe( '' );
			expect( resolveImageAttribute( img, 'title' ) ).toBe( '' );
		} );

		it( 'should return empty string for unknown attribute', () => {
			expect( resolveImageAttribute( imageObj, 'unknown' ) ).toBe( '' );
		} );

		it( 'should return empty string for null imageObj', () => {
			expect( resolveImageAttribute( null, 'url' ) ).toBe( '' );
		} );
	} );

	describe( 'processFieldBinding', () => {
		const scfFields = {
			text_field: {
				type: 'text',
				formatted_value: 'Hello World',
			},
			image_field: {
				type: 'image',
				formatted_value: {
					url: 'https://example.com/image.jpg',
					alt: 'Test image',
					title: 'Test Title',
					id: 123,
				},
			},
			checkbox_field: {
				type: 'checkbox',
				formatted_value: [ 'option1', 'option2' ],
			},
			number_field: {
				type: 'number',
				formatted_value: 42,
			},
			textarea_field: {
				type: 'textarea',
				formatted_value: 'Long text content',
			},
		};

		it( 'should process text field', () => {
			const result = processFieldBinding(
				'content',
				{ key: 'text_field' },
				scfFields
			);
			expect( result ).toBe( 'Hello World' );
		} );

		it( 'should process image field url attribute', () => {
			const result = processFieldBinding(
				'url',
				{ key: 'image_field' },
				scfFields
			);
			expect( result ).toBe( 'https://example.com/image.jpg' );
		} );

		it( 'should process image field alt attribute', () => {
			const result = processFieldBinding(
				'alt',
				{ key: 'image_field' },
				scfFields
			);
			expect( result ).toBe( 'Test image' );
		} );

		it( 'should process checkbox field as joined string', () => {
			const result = processFieldBinding(
				'content',
				{ key: 'checkbox_field' },
				scfFields
			);
			expect( result ).toBe( 'option1, option2' );
		} );

		it( 'should process number field as string', () => {
			const result = processFieldBinding(
				'content',
				{ key: 'number_field' },
				scfFields
			);
			expect( result ).toBe( '42' );
		} );

		it( 'should return empty string for missing field', () => {
			const result = processFieldBinding(
				'content',
				{ key: 'nonexistent_field' },
				scfFields
			);
			expect( result ).toBe( '' );
		} );

		it( 'should return empty string when args is null', () => {
			const result = processFieldBinding( 'content', null, scfFields );
			expect( result ).toBe( '' );
		} );

		it( 'should return empty string when key is missing', () => {
			const result = processFieldBinding( 'content', {}, scfFields );
			expect( result ).toBe( '' );
		} );

		it( 'should handle empty field value', () => {
			const fields = {
				empty_field: {
					type: 'text',
					formatted_value: '',
				},
			};
			const result = processFieldBinding(
				'content',
				{ key: 'empty_field' },
				fields
			);
			expect( result ).toBe( '' );
		} );

		it( 'should handle checkbox with string value', () => {
			const fields = {
				checkbox_string: {
					type: 'checkbox',
					formatted_value: 'single-value',
				},
			};
			const result = processFieldBinding(
				'content',
				{ key: 'checkbox_string' },
				fields
			);
			expect( result ).toBe( 'single-value' );
		} );
	} );

	describe( 'formatFieldLabel', () => {
		it( 'should format field key with underscores', () => {
			expect( formatFieldLabel( 'my_field_name' ) ).toBe(
				'My Field Name'
			);
		} );

		it( 'should format single word', () => {
			expect( formatFieldLabel( 'field' ) ).toBe( 'Field' );
		} );

		it( 'should handle already capitalized words', () => {
			expect( formatFieldLabel( 'My_Field' ) ).toBe( 'My Field' );
		} );

		it( 'should return empty string for empty input', () => {
			expect( formatFieldLabel( '' ) ).toBe( '' );
		} );

		it( 'should return empty string for null input', () => {
			expect( formatFieldLabel( null ) ).toBe( '' );
		} );

		it( 'should handle multiple consecutive underscores', () => {
			expect( formatFieldLabel( 'my__field' ) ).toBe( 'My  Field' );
		} );
	} );

	describe( 'getFieldLabel', () => {
		const fieldMetadata = {
			field1: { label: 'Custom Label 1', type: 'text' },
			field2: { label: 'Custom Label 2', type: 'textarea' },
		};

		it( 'should return label from metadata', () => {
			const result = getFieldLabel( 'field1', fieldMetadata );
			expect( result ).toBe( 'Custom Label 1' );
		} );

		it( 'should format field key when metadata not provided', () => {
			const result = getFieldLabel( 'my_field', null );
			expect( result ).toBe( 'My Field' );
		} );

		it( 'should format field key when field not in metadata', () => {
			const result = getFieldLabel( 'unknown_field', fieldMetadata );
			expect( result ).toBe( 'Unknown Field' );
		} );

		it( 'should return default label when field key is empty', () => {
			const result = getFieldLabel( '', fieldMetadata, 'Default' );
			expect( result ).toBe( 'Default' );
		} );

		it( 'should return default label when field key is null', () => {
			const result = getFieldLabel( null, fieldMetadata, 'Default' );
			expect( result ).toBe( 'Default' );
		} );

		it( 'should format field key when no default provided', () => {
			const result = getFieldLabel( 'my_field' );
			expect( result ).toBe( 'My Field' );
		} );
	} );
} );
