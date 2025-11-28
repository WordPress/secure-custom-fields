/**
 * Unit tests for block bindings utility functions
 */

import {
	getBindableAttributes,
	getAllowedFieldTypes,
	getFilteredFieldOptions,
	canUseUnifiedBinding,
	extractPostTypeFromTemplate,
	formatFieldGroupsData,
	fieldsToOptions,
} from '../../../assets/src/js/bindings/utils';

describe( 'Block Bindings Utils', () => {
	describe( 'getBindableAttributes', () => {
		it( 'should return bindable attributes for core/paragraph', () => {
			const attributes = getBindableAttributes( 'core/paragraph' );
			expect( attributes ).toEqual( [ 'content' ] );
		} );

		it( 'should return bindable attributes for core/heading', () => {
			const attributes = getBindableAttributes( 'core/heading' );
			expect( attributes ).toEqual( [ 'content' ] );
		} );

		it( 'should return multiple bindable attributes for core/image', () => {
			const attributes = getBindableAttributes( 'core/image' );
			expect( attributes ).toEqual( [ 'id', 'url', 'title', 'alt' ] );
		} );

		it( 'should return multiple bindable attributes for core/button', () => {
			const attributes = getBindableAttributes( 'core/button' );
			expect( attributes ).toEqual( [
				'url',
				'text',
				'linkTarget',
				'rel',
			] );
		} );

		it( 'should return empty array for unsupported block', () => {
			const attributes = getBindableAttributes( 'core/unknown' );
			expect( attributes ).toEqual( [] );
		} );

		it( 'should return empty array for null block name', () => {
			const attributes = getBindableAttributes( null );
			expect( attributes ).toEqual( [] );
		} );
	} );

	describe( 'getAllowedFieldTypes', () => {
		it( 'should return allowed field types for paragraph content', () => {
			const types = getAllowedFieldTypes( 'core/paragraph', 'content' );
			expect( types ).toEqual( [
				'text',
				'textarea',
				'date_picker',
				'number',
				'range',
			] );
		} );

		it( 'should return allowed field types for image url', () => {
			const types = getAllowedFieldTypes( 'core/image', 'url' );
			expect( types ).toEqual( [ 'image' ] );
		} );

		it( 'should return all unique field types when attribute is null', () => {
			const types = getAllowedFieldTypes( 'core/paragraph', null );
			expect( types ).toEqual( [
				'text',
				'textarea',
				'date_picker',
				'number',
				'range',
			] );
		} );

		it( 'should return all unique field types for image block', () => {
			const types = getAllowedFieldTypes( 'core/image', null );
			expect( types ).toEqual( [ 'image' ] );
		} );

		it( 'should return null for unsupported block', () => {
			const types = getAllowedFieldTypes( 'core/unknown', 'content' );
			expect( types ).toBeNull();
		} );

		it( 'should return null for unsupported attribute', () => {
			const types = getAllowedFieldTypes(
				'core/paragraph',
				'unknownAttr'
			);
			expect( types ).toBeNull();
		} );
	} );

	describe( 'getFilteredFieldOptions', () => {
		const fieldOptions = [
			{ value: 'field1', label: 'Field 1', type: 'text' },
			{ value: 'field2', label: 'Field 2', type: 'textarea' },
			{ value: 'field3', label: 'Field 3', type: 'image' },
			{ value: 'field4', label: 'Field 4', type: 'number' },
			{ value: 'field5', label: 'Field 5', type: 'url' },
		];

		it( 'should filter options for paragraph content', () => {
			const filtered = getFilteredFieldOptions(
				fieldOptions,
				'core/paragraph',
				'content'
			);
			expect( filtered ).toHaveLength( 3 );
			expect( filtered.map( ( o ) => o.value ) ).toEqual( [
				'field1',
				'field2',
				'field4',
			] );
		} );

		it( 'should filter options for image attributes', () => {
			const filtered = getFilteredFieldOptions(
				fieldOptions,
				'core/image',
				'url'
			);
			expect( filtered ).toHaveLength( 1 );
			expect( filtered[ 0 ].value ).toBe( 'field3' );
		} );

		it( 'should return all options when no restrictions', () => {
			const filtered = getFilteredFieldOptions(
				fieldOptions,
				'core/unknown',
				'content'
			);
			expect( filtered ).toEqual( fieldOptions );
		} );

		it( 'should return empty array for empty input', () => {
			const filtered = getFilteredFieldOptions(
				[],
				'core/paragraph',
				'content'
			);
			expect( filtered ).toEqual( [] );
		} );

		it( 'should return empty array for null input', () => {
			const filtered = getFilteredFieldOptions(
				null,
				'core/paragraph',
				'content'
			);
			expect( filtered ).toEqual( [] );
		} );

		it( 'should handle attribute parameter as null', () => {
			const filtered = getFilteredFieldOptions(
				fieldOptions,
				'core/paragraph',
				null
			);
			expect( filtered ).toHaveLength( 3 );
		} );
	} );

	describe( 'canUseUnifiedBinding', () => {
		it( 'should return true when all attributes support same types', () => {
			const result = canUseUnifiedBinding( 'core/image', [
				'url',
				'alt',
				'title',
			] );
			expect( result ).toBe( true );
		} );

		it( 'should return false for single attribute', () => {
			const result = canUseUnifiedBinding( 'core/paragraph', [
				'content',
			] );
			expect( result ).toBe( false );
		} );

		it( 'should return false for empty attributes array', () => {
			const result = canUseUnifiedBinding( 'core/paragraph', [] );
			expect( result ).toBe( false );
		} );

		it( 'should return false for null attributes', () => {
			const result = canUseUnifiedBinding( 'core/paragraph', null );
			expect( result ).toBe( false );
		} );

		it( 'should return false for unsupported block', () => {
			const result = canUseUnifiedBinding( 'core/unknown', [
				'attr1',
				'attr2',
			] );
			expect( result ).toBe( false );
		} );

		it( 'should return false for button with different attribute types', () => {
			// Button has url:[url] and text:[text, checkbox, select, date_picker]
			const result = canUseUnifiedBinding( 'core/button', [
				'url',
				'text',
			] );
			expect( result ).toBe( false );
		} );
	} );

	describe( 'extractPostTypeFromTemplate', () => {
		it( 'should extract post type from single template', () => {
			expect( extractPostTypeFromTemplate( 'single-product' ) ).toBe(
				'product'
			);
		} );

		it( 'should extract post type from archive template', () => {
			expect( extractPostTypeFromTemplate( 'archive-product' ) ).toBe(
				'product'
			);
		} );

		it( 'should return "post" for default single template', () => {
			expect( extractPostTypeFromTemplate( 'single' ) ).toBe( 'post' );
		} );

		it( 'should return null for non-matching template', () => {
			expect( extractPostTypeFromTemplate( 'page' ) ).toBeNull();
		} );

		it( 'should return null for empty string', () => {
			expect( extractPostTypeFromTemplate( '' ) ).toBeNull();
		} );

		it( 'should return null for null input', () => {
			expect( extractPostTypeFromTemplate( null ) ).toBeNull();
		} );

		it( 'should handle complex post type names', () => {
			expect(
				extractPostTypeFromTemplate( 'single-custom-post-type' )
			).toBe( 'custom-post-type' );
		} );
	} );

	describe( 'formatFieldGroupsData', () => {
		const mockFieldGroups = [
			{
				title: 'Group 1',
				fields: [
					{ name: 'field1', label: 'Field 1', type: 'text' },
					{ name: 'field2', label: 'Field 2', type: 'textarea' },
				],
			},
			{
				title: 'Group 2',
				fields: [ { name: 'field3', label: 'Field 3', type: 'image' } ],
			},
		];

		it( 'should format field groups data correctly', () => {
			const result = formatFieldGroupsData( mockFieldGroups );
			expect( result ).toEqual( {
				field1: { label: 'Field 1', type: 'text' },
				field2: { label: 'Field 2', type: 'textarea' },
				field3: { label: 'Field 3', type: 'image' },
			} );
		} );

		it( 'should return empty object for empty array', () => {
			const result = formatFieldGroupsData( [] );
			expect( result ).toEqual( {} );
		} );

		it( 'should return empty object for non-array input', () => {
			const result = formatFieldGroupsData( null );
			expect( result ).toEqual( {} );
		} );

		it( 'should handle field groups without fields', () => {
			const result = formatFieldGroupsData( [
				{ title: 'Empty Group' },
			] );
			expect( result ).toEqual( {} );
		} );

		it( 'should handle field groups with null fields', () => {
			const result = formatFieldGroupsData( [
				{ title: 'Group', fields: null },
			] );
			expect( result ).toEqual( {} );
		} );
	} );

	describe( 'fieldsToOptions', () => {
		const mockFieldsMap = {
			field1: { label: 'Field 1', type: 'text' },
			field2: { label: 'Field 2', type: 'textarea' },
			field3: { label: 'Field 3', type: 'image' },
		};

		it( 'should convert fields map to options array', () => {
			const result = fieldsToOptions( mockFieldsMap );
			expect( result ).toEqual( [
				{ value: 'field1', label: 'Field 1', type: 'text' },
				{ value: 'field2', label: 'Field 2', type: 'textarea' },
				{ value: 'field3', label: 'Field 3', type: 'image' },
			] );
		} );

		it( 'should return empty array for empty object', () => {
			const result = fieldsToOptions( {} );
			expect( result ).toEqual( [] );
		} );

		it( 'should return empty array for null input', () => {
			const result = fieldsToOptions( null );
			expect( result ).toEqual( [] );
		} );

		it( 'should handle single field', () => {
			const result = fieldsToOptions( {
				field1: { label: 'Field 1', type: 'text' },
			} );
			expect( result ).toEqual( [
				{ value: 'field1', label: 'Field 1', type: 'text' },
			] );
		} );
	} );
} );
