/**
 * Unit tests for custom hooks
 */

import { renderHook } from '@testing-library/react';
import { useSelect, useDispatch } from '@wordpress/data';

// Mock the fieldMetadataCache module
jest.mock( '../../../assets/src/js/bindings/fieldMetadataCache', () => ( {
	addFieldMetadata: jest.fn(),
	getFieldMetadata: jest.fn(),
	setFieldMetadata: jest.fn(),
	clearFieldMetadata: jest.fn(),
	getAllFieldMetadata: jest.fn(),
	hasFieldMetadata: jest.fn(),
} ) );

import {
	useSiteEditorContext,
	usePostEditorFields,
	useSiteEditorFields,
	useBoundFields,
} from '../../../assets/src/js/bindings/hooks';
import * as fieldMetadataCache from '../../../assets/src/js/bindings/fieldMetadataCache';

describe( 'Custom Hooks', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	describe( 'useSiteEditorContext', () => {
		it( 'should detect site editor when post type is wp_template', () => {
			useSelect.mockImplementation( ( callback ) => {
				const select = ( store ) => {
					if ( store === 'core/editor' ) {
						return {
							getCurrentPostType: () => 'wp_template',
							getCurrentPostId: () => 123,
						};
					}
					if ( store === 'core' ) {
						return {
							getEditedEntityRecord: () => ( {
								slug: 'single-product',
							} ),
						};
					}
					return {};
				};
				return callback( select );
			} );

			const { result } = renderHook( () => useSiteEditorContext() );

			expect( result.current.isSiteEditor ).toBe( true );
			expect( result.current.templatePostType ).toBe( 'product' );
		} );

		it( 'should not detect site editor for regular post editor', () => {
			useSelect.mockImplementation( ( callback ) => {
				const select = ( store ) => {
					if ( store === 'core/editor' ) {
						return {
							getCurrentPostType: () => 'post',
							getCurrentPostId: () => 456,
						};
					}
					if ( store === 'core' ) {
						return {
							getEditedEntityRecord: () => null,
						};
					}
					return {};
				};
				return callback( select );
			} );

			const { result } = renderHook( () => useSiteEditorContext() );

			expect( result.current.isSiteEditor ).toBe( false );
			expect( result.current.templatePostType ).toBeNull();
		} );

		it( 'should extract post type from archive template', () => {
			useSelect.mockImplementation( ( callback ) => {
				const select = ( store ) => {
					if ( store === 'core/editor' ) {
						return {
							getCurrentPostType: () => 'wp_template',
							getCurrentPostId: () => 789,
						};
					}
					if ( store === 'core' ) {
						return {
							getEditedEntityRecord: () => ( {
								slug: 'archive-book',
							} ),
						};
					}
					return {};
				};
				return callback( select );
			} );

			const { result } = renderHook( () => useSiteEditorContext() );

			expect( result.current.isSiteEditor ).toBe( true );
			expect( result.current.templatePostType ).toBe( 'book' );
		} );

		it( 'should handle missing template', () => {
			useSelect.mockImplementation( ( callback ) => {
				const select = ( store ) => {
					if ( store === 'core/editor' ) {
						return {
							getCurrentPostType: () => 'wp_template',
							getCurrentPostId: () => 999,
						};
					}
					if ( store === 'core' ) {
						return {
							getEditedEntityRecord: () => null,
						};
					}
					return {};
				};
				return callback( select );
			} );

			const { result } = renderHook( () => useSiteEditorContext() );

			expect( result.current.isSiteEditor ).toBe( true );
			expect( result.current.templatePostType ).toBeNull();
		} );
	} );

	describe( 'usePostEditorFields', () => {
		it( 'should extract sourced fields from post entity', () => {
			useSelect.mockImplementation( ( callback ) => {
				const select = ( store ) => {
					if ( store === 'core/editor' ) {
						return {
							getCurrentPostType: () => 'post',
							getCurrentPostId: () => 123,
						};
					}
					if ( store === 'core' ) {
						return {
							getEditedEntityRecord: () => ( {
								acf: {
									field1: 'value1',
									field1_source: {
										type: 'text',
										formatted_value: 'value1',
									},
									field2: 'value2',
									field2_source: {
										type: 'textarea',
										formatted_value: 'value2',
									},
								},
							} ),
						};
					}
					return {};
				};
				return callback( select );
			} );

			const { result } = renderHook( () => usePostEditorFields() );

			expect( result.current ).toEqual( {
				field1: { type: 'text', formatted_value: 'value1' },
				field2: { type: 'textarea', formatted_value: 'value2' },
			} );
		} );

		it( 'should return empty object for wp_template post type', () => {
			useSelect.mockImplementation( ( callback ) => {
				const select = ( store ) => {
					if ( store === 'core/editor' ) {
						return {
							getCurrentPostType: () => 'wp_template',
							getCurrentPostId: () => 123,
						};
					}
					if ( store === 'core' ) {
						return {
							getEditedEntityRecord: () => null,
						};
					}
					return {};
				};
				return callback( select );
			} );

			const { result } = renderHook( () => usePostEditorFields() );

			expect( result.current ).toEqual( {} );
		} );

		it( 'should return empty object when no post type', () => {
			useSelect.mockImplementation( ( callback ) => {
				const select = ( store ) => {
					if ( store === 'core/editor' ) {
						return {
							getCurrentPostType: () => null,
							getCurrentPostId: () => null,
						};
					}
					if ( store === 'core' ) {
						return {
							getEditedEntityRecord: () => null,
						};
					}
					return {};
				};
				return callback( select );
			} );

			const { result } = renderHook( () => usePostEditorFields() );

			expect( result.current ).toEqual( {} );
		} );

		it( 'should only include fields with both value and source', () => {
			useSelect.mockImplementation( ( callback ) => {
				const select = ( store ) => {
					if ( store === 'core/editor' ) {
						return {
							getCurrentPostType: () => 'post',
							getCurrentPostId: () => 123,
						};
					}
					if ( store === 'core' ) {
						return {
							getEditedEntityRecord: () => ( {
								acf: {
									field1: 'value1',
									field1_source: {
										type: 'text',
										formatted_value: 'value1',
									},
									field2_source: {
										type: 'text',
										formatted_value: 'value2',
									},
									// field2 is missing
								},
							} ),
						};
					}
					return {};
				};
				return callback( select );
			} );

			const { result } = renderHook( () => usePostEditorFields() );

			expect( result.current ).toEqual( {
				field1: { type: 'text', formatted_value: 'value1' },
			} );
		} );
	} );

	describe( 'useSiteEditorFields', () => {
		const groups = [
			{
				title: 'Group',
				fields: [ { name: 'field1', label: 'Field 1', type: 'text' } ],
			},
		];
		let core, invalidateResolution;

		beforeEach( () => {
			core = {
				getPostType: jest.fn( () => ( {
					scf_field_groups: groups,
				} ) ),
				getResolutionError: jest.fn( () => null ),
				hasFinishedResolution: jest.fn( () => true ),
			};
			invalidateResolution = jest.fn();
			useDispatch.mockReturnValue( { invalidateResolution } );
			useSelect.mockImplementation( ( callback ) =>
				callback( () => core )
			);
		} );

		it( 'should fetch fields for a post type', () => {
			const { result } = renderHook( () =>
				useSiteEditorFields( 'product' )
			);
			expect( core.getPostType ).toHaveBeenCalledWith( 'product' );
			expect( result.current.fields ).toEqual( {
				field1: { label: 'Field 1', type: 'text' },
			} );
			expect( result.current.isLoading ).toBe( false );
			expect( result.current.error ).toBeNull();
			expect( fieldMetadataCache.addFieldMetadata ).toHaveBeenCalledWith(
				result.current.fields
			);
		} );

		it( 'should store field metadata in cache on successful fetch', () => {
			const { result } = renderHook( () =>
				useSiteEditorFields( 'product' )
			);
			expect( fieldMetadataCache.addFieldMetadata ).toHaveBeenCalledWith(
				result.current.fields
			);
			expect( result.current.fields ).toHaveProperty( 'field1' );
		} );

		it( 'should reset when post type is null', () => {
			const { result } = renderHook( () => useSiteEditorFields( null ) );
			result.current.refresh();
			expect( core.getPostType ).not.toHaveBeenCalled();
			expect( result.current.fields ).toEqual( {} );
			expect( result.current.isLoading ).toBe( false );
			expect( result.current.error ).toBeNull();
			expect( invalidateResolution ).not.toHaveBeenCalled();
		} );

		it( 'keeps stored fields while a resolution is pending', () => {
			core.hasFinishedResolution.mockReturnValue( false );
			const { result } = renderHook( () =>
				useSiteEditorFields( 'product' )
			);
			expect( result.current.isLoading ).toBe( true );
			expect( result.current.fields ).toEqual( {
				field1: { label: 'Field 1', type: 'text' },
			} );
			expect( result.current.error ).toBeNull();
		} );

		it( 'has no fields until a record is available', () => {
			core.getPostType.mockReturnValue( undefined );
			core.hasFinishedResolution.mockReturnValue( false );
			const { result } = renderHook( () =>
				useSiteEditorFields( 'product' )
			);
			expect( result.current.fields ).toEqual( {} );
			expect( result.current.isLoading ).toBe( true );
			expect(
				fieldMetadataCache.addFieldMetadata
			).not.toHaveBeenCalled();
		} );

		it( 'should handle API errors', () => {
			const error = new Error( 'Temporary failure' );
			core.getPostType.mockReturnValue( undefined );
			core.hasFinishedResolution.mockReturnValue( true );
			core.getResolutionError.mockReturnValue( error );
			const { result } = renderHook( () =>
				useSiteEditorFields( 'product' )
			);
			expect( result.current.isLoading ).toBe( false );
			expect( result.current.error ).toBe( error );
			expect( result.current.fields ).toEqual( {} );
			expect(
				fieldMetadataCache.addFieldMetadata
			).not.toHaveBeenCalled();
		} );

		it( 'refreshes exactly the shared resolution without automatic retries', () => {
			core.getResolutionError.mockReturnValue( new Error( 'Failure' ) );
			const { result, rerender } = renderHook( () =>
				useSiteEditorFields( 'product' )
			);
			rerender();
			expect( invalidateResolution ).not.toHaveBeenCalled();
			result.current.refresh();
			expect( invalidateResolution ).toHaveBeenCalledTimes( 1 );
			expect( invalidateResolution ).toHaveBeenCalledWith(
				'getPostType',
				[ 'product' ]
			);
		} );

		it( 'should update when post type changes', () => {
			core.getPostType.mockImplementation( ( type ) =>
				type === 'product' ? { scf_field_groups: groups } : undefined
			);
			core.hasFinishedResolution.mockImplementation(
				( selector, args ) => args[ 0 ] === 'product'
			);
			const { result, rerender } = renderHook(
				( { type } ) => useSiteEditorFields( type ),
				{ initialProps: { type: 'product' } }
			);
			expect( result.current.fields ).toHaveProperty( 'field1' );
			rerender( { type: 'book' } );
			expect( result.current.fields ).toEqual( {} );
			expect( result.current.isLoading ).toBe( true );
		} );

		it( 'should handle post type changing from value to null', () => {
			const { result, rerender } = renderHook(
				( { type } ) => useSiteEditorFields( type ),
				{ initialProps: { type: 'product' } }
			);
			expect( result.current.fields ).toHaveProperty( 'field1' );
			rerender( { type: null } );
			expect( result.current.fields ).toEqual( {} );
			expect( result.current.isLoading ).toBe( false );
			expect( result.current.error ).toBeNull();
		} );

		it( 'keeps valid fields alongside malformed groups without a fetch error', () => {
			core.getPostType.mockReturnValue( {
				scf_field_groups: [ null, groups[ 0 ] ],
			} );
			const { result } = renderHook( () =>
				useSiteEditorFields( 'product' )
			);
			expect( result.current.fields ).toEqual( {
				field1: { label: 'Field 1', type: 'text' },
			} );
			expect( result.current.error ).toBeNull();
			expect( result.current.isLoading ).toBe( false );
			expect( fieldMetadataCache.addFieldMetadata ).toHaveBeenCalledWith(
				result.current.fields
			);
		} );

		it.each( [
			[ 'empty scf_field_groups', { scf_field_groups: [] } ],
			[ 'missing scf_field_groups in response', {} ],
		] )( 'should handle %s', ( description, record ) => {
			core.getPostType.mockReturnValue( record );
			const { result } = renderHook( () =>
				useSiteEditorFields( 'product' )
			);
			expect( result.current.fields ).toEqual( {} );
			expect( result.current.isLoading ).toBe( false );
			expect( result.current.error ).toBeNull();
		} );
	} );

	describe( 'useBoundFields', () => {
		it( 'should extract bound fields from block attributes', () => {
			const blockAttributes = {
				metadata: {
					bindings: {
						content: {
							source: 'acf/field',
							args: { key: 'field1' },
						},
						url: {
							source: 'acf/field',
							args: { key: 'field2' },
						},
					},
				},
			};

			const { result } = renderHook( () =>
				useBoundFields( blockAttributes )
			);

			expect( result.current.boundFields ).toEqual( {
				content: 'field1',
				url: 'field2',
			} );
		} );

		it( 'should return empty object when no bindings', () => {
			const blockAttributes = {
				metadata: {},
			};

			const { result } = renderHook( () =>
				useBoundFields( blockAttributes )
			);

			expect( result.current.boundFields ).toEqual( {} );
		} );

		it( 'should ignore bindings without key', () => {
			const blockAttributes = {
				metadata: {
					bindings: {
						content: {
							source: 'acf/field',
							args: { key: 'field1' },
						},
						url: {
							source: 'acf/field',
							args: {}, // No key
						},
					},
				},
			};

			const { result } = renderHook( () =>
				useBoundFields( blockAttributes )
			);

			expect( result.current.boundFields ).toEqual( {
				content: 'field1',
			} );
		} );

		it( 'should update when bindings change', () => {
			const initialAttributes = {
				metadata: {
					bindings: {
						content: {
							source: 'acf/field',
							args: { key: 'field1' },
						},
					},
				},
			};

			const { result, rerender } = renderHook(
				( { attrs } ) => useBoundFields( attrs ),
				{ initialProps: { attrs: initialAttributes } }
			);

			expect( result.current.boundFields ).toEqual( {
				content: 'field1',
			} );

			const updatedAttributes = {
				metadata: {
					bindings: {
						content: {
							source: 'acf/field',
							args: { key: 'field2' },
						},
						url: {
							source: 'acf/field',
							args: { key: 'field3' },
						},
					},
				},
			};

			rerender( { attrs: updatedAttributes } );

			expect( result.current.boundFields ).toEqual( {
				content: 'field2',
				url: 'field3',
			} );
		} );

		it( 'should return empty object when metadata is undefined', () => {
			const blockAttributes = {};

			const { result } = renderHook( () =>
				useBoundFields( blockAttributes )
			);

			expect( result.current.boundFields ).toEqual( {} );
		} );

		it( 'should handle null blockAttributes', () => {
			const { result } = renderHook( () => useBoundFields( null ) );

			expect( result.current.boundFields ).toEqual( {} );
		} );

		it( 'should handle undefined blockAttributes', () => {
			const { result } = renderHook( () => useBoundFields( undefined ) );

			expect( result.current.boundFields ).toEqual( {} );
		} );

		it( 'should return setBoundFields function', () => {
			const blockAttributes = {
				metadata: {
					bindings: {},
				},
			};

			const { result } = renderHook( () =>
				useBoundFields( blockAttributes )
			);

			expect( typeof result.current.setBoundFields ).toBe( 'function' );
		} );

		it( 'should ignore bindings with null args', () => {
			const blockAttributes = {
				metadata: {
					bindings: {
						content: {
							source: 'acf/field',
							args: null,
						},
					},
				},
			};

			const { result } = renderHook( () =>
				useBoundFields( blockAttributes )
			);

			expect( result.current.boundFields ).toEqual( {} );
		} );

		it( 'should clear bound fields when bindings are removed', () => {
			const initialAttributes = {
				metadata: {
					bindings: {
						content: {
							source: 'acf/field',
							args: { key: 'field1' },
						},
					},
				},
			};

			const { result, rerender } = renderHook(
				( { attrs } ) => useBoundFields( attrs ),
				{ initialProps: { attrs: initialAttributes } }
			);

			expect( result.current.boundFields ).toEqual( {
				content: 'field1',
			} );

			// Remove bindings
			rerender( { attrs: { metadata: { bindings: {} } } } );

			expect( result.current.boundFields ).toEqual( {} );
		} );
	} );

	describe( 'usePostEditorFields - additional edge cases', () => {
		it( 'should return empty object when record has no acf property', () => {
			useSelect.mockImplementation( ( callback ) => {
				const select = ( store ) => {
					if ( store === 'core/editor' ) {
						return {
							getCurrentPostType: () => 'post',
							getCurrentPostId: () => 123,
						};
					}
					if ( store === 'core' ) {
						return {
							getEditedEntityRecord: () => ( {
								title: 'Test Post',
								content: 'Content',
							} ),
						};
					}
					return {};
				};
				return callback( select );
			} );

			const { result } = renderHook( () => usePostEditorFields() );

			expect( result.current ).toEqual( {} );
		} );

		it( 'should return empty object when record is null', () => {
			useSelect.mockImplementation( ( callback ) => {
				const select = ( store ) => {
					if ( store === 'core/editor' ) {
						return {
							getCurrentPostType: () => 'post',
							getCurrentPostId: () => 123,
						};
					}
					if ( store === 'core' ) {
						return {
							getEditedEntityRecord: () => null,
						};
					}
					return {};
				};
				return callback( select );
			} );

			const { result } = renderHook( () => usePostEditorFields() );

			expect( result.current ).toEqual( {} );
		} );

		it( 'should return empty object when postId is null', () => {
			useSelect.mockImplementation( ( callback ) => {
				const select = ( store ) => {
					if ( store === 'core/editor' ) {
						return {
							getCurrentPostType: () => 'post',
							getCurrentPostId: () => null,
						};
					}
					if ( store === 'core' ) {
						return {
							getEditedEntityRecord: () => null,
						};
					}
					return {};
				};
				return callback( select );
			} );

			const { result } = renderHook( () => usePostEditorFields() );

			expect( result.current ).toEqual( {} );
		} );

		it( 'should handle acf with null values', () => {
			useSelect.mockImplementation( ( callback ) => {
				const select = ( store ) => {
					if ( store === 'core/editor' ) {
						return {
							getCurrentPostType: () => 'post',
							getCurrentPostId: () => 123,
						};
					}
					if ( store === 'core' ) {
						return {
							getEditedEntityRecord: () => ( {
								acf: null,
							} ),
						};
					}
					return {};
				};
				return callback( select );
			} );

			const { result } = renderHook( () => usePostEditorFields() );

			expect( result.current ).toEqual( {} );
		} );
	} );

	describe( 'useSiteEditorContext - additional edge cases', () => {
		it( 'should handle wp_template_part post type', () => {
			useSelect.mockImplementation( ( callback ) => {
				const select = ( store ) => {
					if ( store === 'core/editor' ) {
						return {
							getCurrentPostType: () => 'wp_template_part',
							getCurrentPostId: () => 123,
						};
					}
					if ( store === 'core' ) {
						return {
							getEditedEntityRecord: () => null,
						};
					}
					return {};
				};
				return callback( select );
			} );

			const { result } = renderHook( () => useSiteEditorContext() );

			// wp_template_part is not considered site editor
			expect( result.current.isSiteEditor ).toBe( false );
		} );

		it( 'should handle template with empty slug', () => {
			useSelect.mockImplementation( ( callback ) => {
				const select = ( store ) => {
					if ( store === 'core/editor' ) {
						return {
							getCurrentPostType: () => 'wp_template',
							getCurrentPostId: () => 123,
						};
					}
					if ( store === 'core' ) {
						return {
							getEditedEntityRecord: () => ( {
								slug: '',
							} ),
						};
					}
					return {};
				};
				return callback( select );
			} );

			const { result } = renderHook( () => useSiteEditorContext() );

			expect( result.current.isSiteEditor ).toBe( true );
			expect( result.current.templatePostType ).toBeNull();
		} );

		it( 'should handle default single template', () => {
			useSelect.mockImplementation( ( callback ) => {
				const select = ( store ) => {
					if ( store === 'core/editor' ) {
						return {
							getCurrentPostType: () => 'wp_template',
							getCurrentPostId: () => 123,
						};
					}
					if ( store === 'core' ) {
						return {
							getEditedEntityRecord: () => ( {
								slug: 'single',
							} ),
						};
					}
					return {};
				};
				return callback( select );
			} );

			const { result } = renderHook( () => useSiteEditorContext() );

			expect( result.current.isSiteEditor ).toBe( true );
			expect( result.current.templatePostType ).toBe( 'post' );
		} );
	} );
} );
