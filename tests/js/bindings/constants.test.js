/**
 * Unit tests for block binding constants
 *
 * These tests validate the configuration structure that determines
 * which SCF field types can bind to which block attributes.
 */

import {
	BLOCK_BINDINGS_CONFIG,
	BLOCK_BINDINGS_CONFIG_FILTER,
	BINDING_SOURCE,
	getBlockBindingsConfig,
} from '../../../assets/src/js/bindings/constants';

import { applyFilters } from '@wordpress/hooks';

describe( 'Block Binding Constants', () => {
	afterEach( () => {
		applyFilters.mockImplementation( ( hook, value ) => value );
	} );

	describe( 'BINDING_SOURCE', () => {
		it( 'should be acf/field following WordPress binding source convention', () => {
			expect( BINDING_SOURCE ).toBe( 'acf/field' );
			expect( BINDING_SOURCE ).toMatch( /^[a-z]+\/[a-z]+$/ );
		} );
	} );

	describe( 'BLOCK_BINDINGS_CONFIG', () => {
		it( 'should define bindings for core blocks', () => {
			expect( Object.keys( BLOCK_BINDINGS_CONFIG ) ).toEqual( [
				'core/paragraph',
				'core/heading',
				'core/image',
				'core/button',
			] );
		} );

		it( 'should map paragraph content to text-like field types', () => {
			expect( BLOCK_BINDINGS_CONFIG[ 'core/paragraph' ].content ).toEqual(
				[ 'text', 'textarea', 'date_picker', 'number', 'range' ]
			);
		} );

		it( 'should map heading content same as paragraph', () => {
			expect( BLOCK_BINDINGS_CONFIG[ 'core/heading' ].content ).toEqual(
				BLOCK_BINDINGS_CONFIG[ 'core/paragraph' ].content
			);
		} );

		it( 'should map all image attributes to image field type only', () => {
			const imageConfig = BLOCK_BINDINGS_CONFIG[ 'core/image' ];
			expect( imageConfig ).toEqual( {
				id: [ 'image' ],
				url: [ 'image' ],
				title: [ 'image' ],
				alt: [ 'image' ],
			} );
		} );

		it( 'should map button attributes to appropriate field types', () => {
			const buttonConfig = BLOCK_BINDINGS_CONFIG[ 'core/button' ];
			expect( buttonConfig.url ).toEqual( [ 'url' ] );
			expect( buttonConfig.text ).toEqual( [
				'text',
				'checkbox',
				'select',
				'date_picker',
			] );
			expect( buttonConfig.linkTarget ).toEqual( buttonConfig.rel );
		} );

		it( 'should have valid structure with non-empty field type arrays', () => {
			Object.entries( BLOCK_BINDINGS_CONFIG ).forEach(
				( [ blockName, blockConfig ] ) => {
					expect( blockName ).toMatch( /^core\// );
					Object.entries( blockConfig ).forEach(
						( [ , fieldTypes ] ) => {
							expect( Array.isArray( fieldTypes ) ).toBe( true );
							expect( fieldTypes.length ).toBeGreaterThan( 0 );
							fieldTypes.forEach( ( type ) => {
								expect( typeof type ).toBe( 'string' );
								expect( type ).toMatch( /^[a-z_]+$/ );
							} );
						}
					);
				}
			);
		} );
	} );

	describe( 'getBlockBindingsConfig', () => {
		it( 'should return the default configuration unchanged without filters', () => {
			expect( getBlockBindingsConfig() ).toBe( BLOCK_BINDINGS_CONFIG );
		} );

		it( 'should apply the block bindings config filter', () => {
			const extended = {
				...BLOCK_BINDINGS_CONFIG,
				'core/post-title': { content: [ 'text' ] },
			};

			applyFilters.mockImplementation( ( hook, value ) => {
				if ( hook === BLOCK_BINDINGS_CONFIG_FILTER ) {
					return extended;
				}
				return value;
			} );

			expect( getBlockBindingsConfig() ).toBe( extended );
			expect( getBlockBindingsConfig()[ 'core/post-title' ] ).toEqual( {
				content: [ 'text' ],
			} );
		} );

		it( 'should read the filter on every call rather than caching it', () => {
			applyFilters.mockImplementation( ( hook, value ) => {
				if ( hook === BLOCK_BINDINGS_CONFIG_FILTER ) {
					return { ...value, 'core/quote': { content: [ 'text' ] } };
				}
				return value;
			} );

			expect( getBlockBindingsConfig()[ 'core/quote' ] ).toEqual( {
				content: [ 'text' ],
			} );

			applyFilters.mockImplementation( ( hook, value ) => value );

			expect( getBlockBindingsConfig()[ 'core/quote' ] ).toBeUndefined();
		} );

		it( 'should use the documented filter name', () => {
			expect( BLOCK_BINDINGS_CONFIG_FILTER ).toBe(
				'scf/block-bindings-config'
			);
		} );
	} );
} );
