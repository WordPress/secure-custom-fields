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
import { getFieldMetadata } from './fieldMetadataCache';

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

		const fieldMetadata = getFieldMetadata( fieldKey );

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

					const fieldMetadata = getFieldMetadata( fieldKey );
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
	canUserEditValue() {
		return false;
	},
} );
