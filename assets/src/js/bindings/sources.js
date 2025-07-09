/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { registerBlockBindingsSource } from '@wordpress/blocks';
import { store as coreDataStore } from '@wordpress/core-data';
import { dateI18n } from '@wordpress/date';

/**
 * Get the value of a specific field from the ACF fields.
 *
 * @param {Object} fields The ACF fields object.
 * @param {string} fieldName The name of the field to retrieve.
 * @returns {string} The value of the specified field, or undefined if not found.
 */
const getFieldValue = ( fields, fieldName ) => fields?.acf?.[ fieldName ];

const resolveImageAttribute = ( imageObj, attribute ) => {
	if ( ! imageObj ) return '';
	switch ( attribute ) {
		case 'url':
		case 'content':
			return imageObj.source_url;
		case 'alt':
			return imageObj.alt_text || '';
		case 'title':
			return imageObj.title?.rendered || '';
		case 'id':
			return imageObj.id;
		default:
			return '';
	}
};

registerBlockBindingsSource( {
	name: 'acf/field',
	label: 'SCF Fields',
	getValues( { context, bindings, select } ) {
		const { getEditedEntityRecord, getMedia } = select( coreDataStore );
		let fields =
			context?.postType && context?.postId
				? getEditedEntityRecord(
						'postType',
						context.postType,
						context.postId
				  )
				: undefined;
		const mergedFields = fields?.scf_field_groups
			? Object.fromEntries(
					fields.scf_field_groups
						.flatMap( ( group ) => group.fields || [] )
						.map( ( field ) => [ field.name, field ] )
			  )
			: {};

		const result = {};
		Object.entries( bindings ).forEach(
			( [ attribute, { args } = {} ] ) => {
				const fieldName = args?.key;
				const fieldValue = getFieldValue( fields, fieldName );
				const fieldType = mergedFields[ fieldName ]?.type;

				if ( typeof fieldValue === 'object' && fieldValue !== null ) {
					let value = '';

					if ( fieldValue[ attribute ] ) {
						value = fieldValue[ attribute ];
					} else if ( attribute === 'content' && fieldValue.url ) {
						value = fieldValue.url;
					}

					result[ attribute ] = value;
				} else if ( 'number' === fieldType ) {
					if ( attribute === 'content' ) {
						result[ attribute ] = fieldValue.toString() || '';
					} else {
						const imageObj = getMedia( fieldValue );
						result[ attribute ] = resolveImageAttribute(
							imageObj,
							attribute
						);
					}
				} else if ( 'date_picker' === fieldType && fieldValue ) {
					result[ attribute ] =
						dateI18n(
							mergedFields[ fieldName ]?.display_format,
							fieldValue
						) || '';
				} else {
					result[ attribute ] = fieldValue || '';
				}
			}
		);

		return result;
	},
	canUserEditValue() {
		return false;
	},
} );
