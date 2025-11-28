/**
 * Unit tests for custom hooks
 */

import { renderHook, waitFor } from '@testing-library/react';
import { useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

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
		const mockFieldGroups = [
			{
				title: 'Group 1',
				fields: [
					{ name: 'field1', label: 'Field 1', type: 'text' },
					{ name: 'field2', label: 'Field 2', type: 'textarea' },
				],
			},
		];

		it( 'should fetch fields for a post type', async () => {
			apiFetch.mockResolvedValue( {
				scf_field_groups: mockFieldGroups,
			} );

			const { result } = renderHook( () =>
				useSiteEditorFields( 'product' )
			);

			expect( result.current.isLoading ).toBe( true );

			await waitFor( () => {
				expect( result.current.isLoading ).toBe( false );
			} );

			expect( result.current.fields ).toEqual( {
				field1: { label: 'Field 1', type: 'text' },
				field2: { label: 'Field 2', type: 'textarea' },
			} );

			expect( result.current.error ).toBeNull();
			expect( fieldMetadataCache.addFieldMetadata ).toHaveBeenCalled();
		} );

		it( 'should handle API errors', async () => {
			const error = new Error( 'API Error' );
			apiFetch.mockRejectedValue( error );

			const { result } = renderHook( () =>
				useSiteEditorFields( 'product' )
			);

			await waitFor( () => {
				expect( result.current.isLoading ).toBe( false );
			} );

			expect( result.current.fields ).toEqual( {} );
			expect( result.current.error ).toBe( error );
		} );

		it( 'should reset when post type is null', () => {
			const { result } = renderHook( () => useSiteEditorFields( null ) );

			expect( result.current.fields ).toEqual( {} );
			expect( result.current.isLoading ).toBe( false );
			expect( result.current.error ).toBeNull();
		} );

		it( 'should call apiFetch with correct parameters', async () => {
			apiFetch.mockResolvedValue( {
				scf_field_groups: mockFieldGroups,
			} );

			renderHook( () => useSiteEditorFields( 'product' ) );

			await waitFor( () => {
				expect( apiFetch ).toHaveBeenCalledWith( {
					path: '/wp/v2/types/product?context=edit',
				} );
			} );
		} );

		it( 'should update when post type changes', async () => {
			apiFetch.mockResolvedValue( {
				scf_field_groups: mockFieldGroups,
			} );

			const { rerender } = renderHook(
				( { postType } ) => useSiteEditorFields( postType ),
				{ initialProps: { postType: 'product' } }
			);

			await waitFor( () => {
				expect( apiFetch ).toHaveBeenCalledWith( {
					path: '/wp/v2/types/product?context=edit',
				} );
			} );

			apiFetch.mockClear();

			rerender( { postType: 'book' } );

			await waitFor( () => {
				expect( apiFetch ).toHaveBeenCalledWith( {
					path: '/wp/v2/types/book?context=edit',
				} );
			} );
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
	} );
} );
