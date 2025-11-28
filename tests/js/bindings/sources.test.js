/**
 * Unit tests for block binding sources
 */

import { registerBlockBindingsSource } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import * as fieldMetadataCache from '../../../assets/src/js/bindings/fieldMetadataCache';

// Mock field processing module
jest.mock( '../../../assets/src/js/bindings/field-processing', () => ( {
	getSCFFields: jest.fn( ( post ) => {
		if ( ! post?.acf ) {
			return {};
		}
		const sourceFields = {};
		Object.entries( post.acf ).forEach( ( [ key, value ] ) => {
			if ( key.endsWith( '_source' ) ) {
				const fieldName = key.replace( '_source', '' );
				sourceFields[ fieldName ] = value;
			}
		} );
		return sourceFields;
	} ),
	processFieldBinding: jest.fn( ( attribute, args, scfFields ) => {
		const fieldName = args?.key;
		const fieldConfig = scfFields[ fieldName ];
		if ( ! fieldConfig ) {
			return '';
		}
		return fieldConfig.formatted_value || '';
	} ),
	formatFieldLabel: jest.fn( ( fieldKey ) => {
		if ( ! fieldKey ) {
			return '';
		}
		return fieldKey
			.split( '_' )
			.map( ( word ) => word.charAt( 0 ).toUpperCase() + word.slice( 1 ) )
			.join( ' ' );
	} ),
} ) );

// Mock the field metadata cache
jest.mock( '../../../assets/src/js/bindings/fieldMetadataCache' );

describe( 'Block Binding Sources', () => {
	let registeredConfig;

	beforeEach( () => {
		jest.clearAllMocks();
		registeredConfig = null;

		// Capture the configuration passed to registerBlockBindingsSource
		registerBlockBindingsSource.mockImplementation( ( config ) => {
			registeredConfig = config;
		} );

		// Re-require the module to trigger registration
		jest.isolateModules( () => {
			require( '../../../assets/src/js/bindings/sources' );
		} );
	} );

	describe( 'Registration', () => {
		it( 'should register the SCF field binding source', () => {
			expect( registerBlockBindingsSource ).toHaveBeenCalled();
			expect( registeredConfig ).not.toBeNull();
			expect( registeredConfig.name ).toBe( 'acf/field' );
		} );
	} );

	describe( 'getLabel', () => {
		it( 'should return default label when no field key', () => {
			const label = registeredConfig.getLabel( {
				args: {},
				select: jest.fn(),
			} );

			expect( label ).toBe( 'SCF Fields' );
		} );

		it( 'should return field metadata label when available', () => {
			fieldMetadataCache.getFieldMetadata.mockReturnValue( {
				label: 'My Custom Field',
				type: 'text',
			} );

			const label = registeredConfig.getLabel( {
				args: { key: 'my_field' },
				select: jest.fn(),
			} );

			expect( label ).toBe( 'My Custom Field' );
		} );

		it( 'should use formatFieldLabel when no metadata available', () => {
			fieldMetadataCache.getFieldMetadata.mockReturnValue( null );

			const {
				formatFieldLabel,
			} = require( '../../../assets/src/js/bindings/field-processing' );
			formatFieldLabel.mockReturnValue( 'My Field' );

			const label = registeredConfig.getLabel( {
				args: { key: 'my_field' },
				select: jest.fn(),
			} );

			expect( label ).toBe( 'My Field' );
		} );
	} );

	describe( 'getValues', () => {
		it( 'should return field labels in site editor', () => {
			fieldMetadataCache.getFieldMetadata.mockImplementation( ( key ) => {
				const metadata = {
					product_name: { label: 'Product Name', type: 'text' },
					product_image: { label: 'Product Image', type: 'image' },
				};
				return metadata[ key ] || null;
			} );

			const mockSelect = jest.fn( ( storeName ) => {
				if ( storeName === 'core/editor' ) {
					return {
						getCurrentPostType: () => 'wp_template',
					};
				}
				return {};
			} );

			const values = registeredConfig.getValues( {
				select: mockSelect,
				context: {},
				bindings: {
					content: { args: { key: 'product_name' } },
					url: { args: { key: 'product_image' } },
				},
			} );

			expect( values ).toEqual( {
				content: 'Product Name',
				url: 'Product Image',
			} );
		} );

		it( 'should use formatFieldLabel for fields without metadata in site editor', () => {
			fieldMetadataCache.getFieldMetadata.mockReturnValue( null );

			const {
				formatFieldLabel,
			} = require( '../../../assets/src/js/bindings/field-processing' );
			formatFieldLabel.mockImplementation( ( key ) => {
				return key
					.split( '_' )
					.map(
						( word ) =>
							word.charAt( 0 ).toUpperCase() + word.slice( 1 )
					)
					.join( ' ' );
			} );

			const mockSelect = jest.fn( ( storeName ) => {
				if ( storeName === 'core/editor' ) {
					return {
						getCurrentPostType: () => 'wp_template',
					};
				}
				return {};
			} );

			const values = registeredConfig.getValues( {
				select: mockSelect,
				context: {},
				bindings: {
					content: { args: { key: 'unknown_field' } },
				},
			} );

			expect( values ).toEqual( {
				content: 'Unknown Field',
			} );
		} );

		it( 'should return processed field values in post editor', () => {
			const mockSelect = jest.fn( ( storeName ) => {
				if ( storeName === 'core/editor' ) {
					return {
						getCurrentPostType: () => 'post',
					};
				}
				if ( storeName === 'core' ) {
					return {
						getEditedEntityRecord: () => ( {
							acf: {
								title_source: {
									formatted_value: 'Test Title',
								},
							},
						} ),
					};
				}
				return {};
			} );

			const {
				processFieldBinding,
			} = require( '../../../assets/src/js/bindings/field-processing' );
			processFieldBinding.mockReturnValue( 'Test Title' );

			const values = registeredConfig.getValues( {
				select: mockSelect,
				context: { postType: 'post', postId: 123 },
				bindings: {
					content: { args: { key: 'title' } },
				},
			} );

			expect( values ).toEqual( {
				content: 'Test Title',
			} );
		} );
	} );
} );
