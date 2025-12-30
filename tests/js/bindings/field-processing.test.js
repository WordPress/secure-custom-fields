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

		it.each( [
			[ 'null post', null ],
			[ 'undefined post', undefined ],
			[ 'post without acf', { id: 1 } ],
			[ 'post with null acf', { acf: null } ],
			[ 'post with empty acf', { acf: {} } ],
		] )( 'should return empty object for %s', ( _desc, post ) => {
			expect( getSCFFields( post ) ).toEqual( {} );
		} );

		it( 'should only include fields with _source counterparts', () => {
			const post = {
				acf: {
					field1: 'value1',
					field1_source: { type: 'text', formatted_value: 'value1' },
					field2: 'value2', // No _source
				},
			};
			expect( getSCFFields( post ) ).toEqual( {
				field1: { type: 'text', formatted_value: 'value1' },
			} );
		} );

		it( 'should handle complex image field values', () => {
			const post = {
				acf: {
					img_source: {
						type: 'image',
						formatted_value: {
							id: 123,
							url: 'https://example.com/image.jpg',
						},
					},
				},
			};
			expect( getSCFFields( post ).img.formatted_value.id ).toBe( 123 );
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

		it.each( [
			[ 'url', 'https://example.com/image.jpg' ],
			[ 'alt', 'Test image' ],
			[ 'title', 'Test Title' ],
			[ 'id', 123 ],
		] )( 'should resolve %s attribute', ( attr, expected ) => {
			expect( resolveImageAttribute( imageObj, attr ) ).toBe( expected );
		} );

		it( 'should fallback to ID property for id attribute', () => {
			expect( resolveImageAttribute( { ID: 456 }, 'id' ) ).toBe( 456 );
		} );

		it.each( [
			[ 'missing attribute', imageObj, 'unknown' ],
			[ 'null imageObj', null, 'url' ],
			[ 'empty imageObj', {}, 'url' ],
		] )( 'should return empty string for %s', ( _desc, img, attr ) => {
			expect( resolveImageAttribute( img, attr ) ).toBe( '' );
		} );

		// Test the || '' fallback branches for each attribute
		it.each( [
			[ 'url', { url: '' } ],
			[ 'alt', { alt: '' } ],
			[ 'title', { title: '' } ],
		] )(
			'should return empty string when %s is empty string',
			( attr, img ) => {
				expect( resolveImageAttribute( img, attr ) ).toBe( '' );
			}
		);

		it( 'should return empty string when both id and ID are missing', () => {
			expect( resolveImageAttribute( { url: 'test' }, 'id' ) ).toBe( '' );
		} );
	} );

	describe( 'processFieldBinding', () => {
		const scfFields = {
			text_field: { type: 'text', formatted_value: 'Hello World' },
			textarea_field: {
				type: 'textarea',
				formatted_value: 'Long content',
			},
			image_field: {
				type: 'image',
				formatted_value: {
					url: 'https://example.com/image.jpg',
					alt: 'Alt text',
					title: 'Title',
					id: 123,
				},
			},
			checkbox_field: {
				type: 'checkbox',
				formatted_value: [ 'opt1', 'opt2' ],
			},
			number_field: { type: 'number', formatted_value: 42 },
		};

		// Test all simple field types that return formatted_value directly
		it.each( [
			[ 'text', 'text_field', 'Hello World' ],
			[ 'textarea', 'textarea_field', 'Long content' ],
			[ 'date_picker', 'date_field', '2024-01-15' ],
			[ 'url', 'url_field', 'https://example.com' ],
			[ 'email', 'email_field', 'test@example.com' ],
			[ 'select', 'select_field', 'option_one' ],
			[ 'unknown custom type', 'custom_field', 'custom value' ],
		] )( 'should process %s field', ( type, key, value ) => {
			const fields = {
				[ key ]: {
					type: type === 'unknown custom type' ? 'custom' : type,
					formatted_value: value,
				},
			};
			expect( processFieldBinding( 'content', { key }, fields ) ).toBe(
				value
			);
		} );

		// Test numeric fields that convert to string
		it.each( [
			[ 'number', 42, '42' ],
			[ 'range', 75, '75' ],
		] )( 'should convert %s field to string', ( type, value, expected ) => {
			const fields = { field: { type, formatted_value: value } };
			expect(
				processFieldBinding( 'content', { key: 'field' }, fields )
			).toBe( expected );
		} );

		it( 'should process checkbox array as joined string', () => {
			expect(
				processFieldBinding(
					'content',
					{ key: 'checkbox_field' },
					scfFields
				)
			).toBe( 'opt1, opt2' );
		} );

		it( 'should process checkbox with string value', () => {
			const fields = {
				cb: { type: 'checkbox', formatted_value: 'single' },
			};
			expect(
				processFieldBinding( 'content', { key: 'cb' }, fields )
			).toBe( 'single' );
		} );

		// Test image attribute resolution
		it.each( [
			[ 'url', 'https://example.com/image.jpg' ],
			[ 'alt', 'Alt text' ],
			[ 'title', 'Title' ],
			[ 'id', 123 ],
		] )( 'should resolve image %s attribute', ( attr, expected ) => {
			expect(
				processFieldBinding( attr, { key: 'image_field' }, scfFields )
			).toBe( expected );
		} );

		// Test empty/missing returns
		it.each( [
			[ 'missing field', { key: 'nonexistent' }, scfFields ],
			[ 'null args', null, scfFields ],
			[ 'missing key in args', {}, scfFields ],
			[
				'null formatted_value',
				{ key: 'f' },
				{ f: { type: 'text', formatted_value: null } },
			],
			[
				'undefined formatted_value',
				{ key: 'f' },
				{ f: { type: 'text', formatted_value: undefined } },
			],
			[
				'empty checkbox array',
				{ key: 'f' },
				{ f: { type: 'checkbox', formatted_value: [] } },
			],
			[
				'false checkbox',
				{ key: 'f' },
				{ f: { type: 'checkbox', formatted_value: false } },
			],
			[
				'zero number',
				{ key: 'f' },
				{ f: { type: 'number', formatted_value: 0 } },
			],
			[
				'zero range',
				{ key: 'f' },
				{ f: { type: 'range', formatted_value: 0 } },
			],
		] )( 'should return empty string for %s', ( _desc, args, fields ) => {
			expect( processFieldBinding( 'content', args, fields ) ).toBe( '' );
		} );
	} );

	describe( 'formatFieldLabel', () => {
		it.each( [
			[ 'my_field_name', 'My Field Name' ],
			[ 'field', 'Field' ],
			[ 'My_Field', 'My Field' ],
			[ 'my__field', 'My  Field' ],
		] )( 'should format "%s" to "%s"', ( input, expected ) => {
			expect( formatFieldLabel( input ) ).toBe( expected );
		} );

		it.each( [ '', null ] )(
			'should return empty string for %s',
			( input ) => {
				expect( formatFieldLabel( input ) ).toBe( '' );
			}
		);
	} );

	describe( 'getFieldLabel', () => {
		const metadata = {
			field1: { label: 'Custom Label', type: 'text' },
			field_no_label: { type: 'text' }, // Field exists but has no label property
		};

		it( 'should return label from metadata when available', () => {
			expect( getFieldLabel( 'field1', metadata ) ).toBe(
				'Custom Label'
			);
		} );

		it( 'should format key when field exists in metadata but has no label', () => {
			// Tests the ?.label optional chaining branch
			expect( getFieldLabel( 'field_no_label', metadata ) ).toBe(
				'Field No Label'
			);
		} );

		it.each( [
			[ 'no metadata', 'my_field', null, undefined, 'My Field' ],
			[ 'field not in metadata', 'other', metadata, undefined, 'Other' ],
			[ 'empty key with default', '', metadata, 'Default', 'Default' ],
			[ 'null key with default', null, metadata, 'Default', 'Default' ],
		] )(
			'should handle %s',
			( _desc, key, meta, defaultLabel, expected ) => {
				expect( getFieldLabel( key, meta, defaultLabel ) ).toBe(
					expected
				);
			}
		);

		it( 'should use default parameters when called with only fieldKey', () => {
			// Tests the default parameter branches (fieldMetadata = null, defaultLabel = '')
			expect( getFieldLabel( 'my_field' ) ).toBe( 'My Field' );
		} );
	} );
} );
