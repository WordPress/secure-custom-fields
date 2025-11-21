/**
 * WordPress dependencies
 */
import { registerBlockBindingsSource } from '@wordpress/blocks';
import { store as coreDataStore } from '@wordpress/core-data';
import { store as editorStore } from '@wordpress/editor';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import {
	getSCFFields,
	processFieldBinding,
	formatFieldLabel,
} from './field-processing';
import { STORE_NAME } from './store';

/**
 * Register the SCF field binding source.
 */
registerBlockBindingsSource( {
	name: 'acf/field',
	label: __( 'SCF Fields', 'secure-custom-fields' ),
	getLabel( { args, select } ) {
		const fieldKey = args?.key;

		if ( ! fieldKey ) {
			return __( 'SCF Fields', 'secure-custom-fields' );
		}

		const fieldMetadata = select( STORE_NAME ).getFieldMetadata( fieldKey );

		if ( fieldMetadata?.label ) {
			return fieldMetadata.label;
		}

		return formatFieldLabel( fieldKey );
	},
	getValues( { context, bindings, select } ) {
		const { getCurrentPostType } = select( editorStore );
		const currentPostType = getCurrentPostType();
		const isSiteEditor = currentPostType === 'wp_template';

		// In site editor, return field labels as placeholder values
		if ( isSiteEditor ) {
			const result = {};
			Object.entries( bindings ).forEach(
				( [ attribute, { args } = {} ] ) => {
					const fieldKey = args?.key;
					if ( ! fieldKey ) {
						result[ attribute ] = '';
						return;
					}

					const fieldMetadata =
						select( STORE_NAME ).getFieldMetadata( fieldKey );
					result[ attribute ] =
						fieldMetadata?.label || formatFieldLabel( fieldKey );
				}
			);
			return result;
		}

		// Regular post editor - get actual field values
		const { getEditedEntityRecord } = select( coreDataStore );

		const post =
			context?.postType && context?.postId
				? getEditedEntityRecord(
						'postType',
						context.postType,
						context.postId
				  )
				: undefined;

		const scfFields = getSCFFields( post );
		const result = {};

		Object.entries( bindings ).forEach(
			( [ attribute, { args } = {} ] ) => {
				const value = processFieldBinding( attribute, args, scfFields );
				result[ attribute ] = value;
			}
		);

		return result;
	},
	getPlaceholder( { context, bindings, select } ) {
		// Get the first binding to determine the field label
		const firstBinding = Object.values( bindings )[ 0 ];
		if ( ! firstBinding?.args?.key ) {
			return __( 'SCF Fields', 'secure-custom-fields' );
		}

		const fieldKey = firstBinding.args.key;

		// Check if we're in the site editor (editing a template)
		const { getCurrentPostType } = select( editorStore );
		const currentPostType = getCurrentPostType();
		const isSiteEditor = currentPostType === 'wp_template';

		if ( isSiteEditor ) {
			const fieldMetadata =
				select( STORE_NAME ).getFieldMetadata( fieldKey );
			return fieldMetadata?.label || formatFieldLabel( fieldKey );
		}

		// Regular post editor - get from post data
		const { getEditedEntityRecord } = select( coreDataStore );

		const post =
			context?.postType && context?.postId
				? getEditedEntityRecord(
						'postType',
						context.postType,
						context.postId
				  )
				: undefined;

		const scfFields = getSCFFields( post );
		const fieldConfig = scfFields[ fieldKey ];

		if ( fieldConfig?.label ) {
			return fieldConfig.label;
		}

		const fieldMetadata = select( STORE_NAME ).getFieldMetadata( fieldKey );
		return fieldMetadata?.label || formatFieldLabel( fieldKey );
	},
	canUserEditValue() {
		return false;
	},
} );
