/**
 * Unit tests for SCF Field Metadata Cache
 */

import {
	setFieldMetadata,
	addFieldMetadata,
	clearFieldMetadata,
	getFieldMetadata,
	getAllFieldMetadata,
	hasFieldMetadata,
} from '../../../assets/src/js/bindings/fieldMetadataCache';

describe( 'Field Metadata Cache', () => {
	beforeEach( () => {
		clearFieldMetadata();
	} );

	describe( 'setFieldMetadata', () => {
		it( 'should set field metadata', () => {
			const fields = {
				field_1: { label: 'Field 1', type: 'text' },
				field_2: { label: 'Field 2', type: 'image' },
			};

			setFieldMetadata( fields );

			expect( getFieldMetadata( 'field_1' ) ).toEqual( {
				label: 'Field 1',
				type: 'text',
			} );
			expect( getFieldMetadata( 'field_2' ) ).toEqual( {
				label: 'Field 2',
				type: 'image',
			} );
		} );

		it( 'should replace all existing metadata', () => {
			setFieldMetadata( {
				field_1: { label: 'Field 1', type: 'text' },
			} );

			setFieldMetadata( {
				field_2: { label: 'Field 2', type: 'image' },
			} );

			expect( getFieldMetadata( 'field_1' ) ).toBeNull();
			expect( getFieldMetadata( 'field_2' ) ).toEqual( {
				label: 'Field 2',
				type: 'image',
			} );
		} );
	} );

	describe( 'addFieldMetadata', () => {
		it( 'should add field metadata', () => {
			addFieldMetadata( {
				field_1: { label: 'Field 1', type: 'text' },
			} );

			expect( getFieldMetadata( 'field_1' ) ).toEqual( {
				label: 'Field 1',
				type: 'text',
			} );
		} );

		it( 'should merge with existing metadata', () => {
			setFieldMetadata( {
				field_1: { label: 'Field 1', type: 'text' },
			} );

			addFieldMetadata( {
				field_2: { label: 'Field 2', type: 'image' },
			} );

			expect( getFieldMetadata( 'field_1' ) ).toEqual( {
				label: 'Field 1',
				type: 'text',
			} );
			expect( getFieldMetadata( 'field_2' ) ).toEqual( {
				label: 'Field 2',
				type: 'image',
			} );
		} );

		it( 'should overwrite fields with same key', () => {
			setFieldMetadata( {
				field_1: { label: 'Old Label', type: 'text' },
			} );

			addFieldMetadata( {
				field_1: { label: 'New Label', type: 'textarea' },
			} );

			expect( getFieldMetadata( 'field_1' ) ).toEqual( {
				label: 'New Label',
				type: 'textarea',
			} );
		} );
	} );

	describe( 'clearFieldMetadata', () => {
		it( 'should clear all metadata', () => {
			setFieldMetadata( {
				field_1: { label: 'Field 1', type: 'text' },
				field_2: { label: 'Field 2', type: 'image' },
			} );

			clearFieldMetadata();

			expect( getFieldMetadata( 'field_1' ) ).toBeNull();
			expect( getFieldMetadata( 'field_2' ) ).toBeNull();
			expect( getAllFieldMetadata() ).toEqual( {} );
		} );
	} );

	describe( 'getFieldMetadata', () => {
		it( 'should return metadata for existing field', () => {
			setFieldMetadata( {
				field_1: { label: 'Field 1', type: 'text' },
			} );

			expect( getFieldMetadata( 'field_1' ) ).toEqual( {
				label: 'Field 1',
				type: 'text',
			} );
		} );

		it( 'should return null for non-existent field', () => {
			expect( getFieldMetadata( 'nonexistent' ) ).toBeNull();
		} );
	} );

	describe( 'getAllFieldMetadata', () => {
		it( 'should return all metadata', () => {
			const fields = {
				field_1: { label: 'Field 1', type: 'text' },
				field_2: { label: 'Field 2', type: 'image' },
			};

			setFieldMetadata( fields );

			expect( getAllFieldMetadata() ).toEqual( fields );
		} );

		it( 'should return empty object when no metadata', () => {
			expect( getAllFieldMetadata() ).toEqual( {} );
		} );
	} );

	describe( 'hasFieldMetadata', () => {
		it( 'should return true for existing field', () => {
			setFieldMetadata( {
				field_1: { label: 'Field 1', type: 'text' },
			} );

			expect( hasFieldMetadata( 'field_1' ) ).toBe( true );
		} );

		it( 'should return false for non-existent field', () => {
			expect( hasFieldMetadata( 'nonexistent' ) ).toBe( false );
		} );
	} );
} );
