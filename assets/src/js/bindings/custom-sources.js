/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { registerBlockBindingsSource } from '@wordpress/blocks';
import { store as coreDataStore } from '@wordpress/core-data';

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
		case 'id':
			return imageObj.id;
		case 'url':
		case 'content':
			return imageObj.source_url;
		case 'alt':
			return imageObj.alt_text || '';
		case 'title':
			return imageObj.title?.rendered || '';
		default:
			return '';
	}
};

registerBlockBindingsSource( {
	name: 'scf/experimental-field',
	label: 'SCF Custom Fields',
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
		const result = {};

		Object.entries( bindings ).forEach(
			( [ attribute, { args } = {} ] ) => {
				const fieldName = args?.field;
				const fieldValue = getFieldValue( fields, fieldName );

				if ( typeof fieldValue === 'object' && fieldValue !== null ) {
					result[ attribute ] =
						( fieldValue[ attribute ] ??
							( attribute === 'content' && fieldValue.url ) ) ||
						'';
				} else if ( typeof fieldValue === 'number' ) {
					const imageObj = getMedia( fieldValue );
					result[ attribute ] = resolveImageAttribute(
						imageObj,
						attribute
					);
				} else {
					result[ attribute ] = fieldValue || '';
				}
			}
		);
		return result;
	},
	async setValues( { context, bindings, dispatch, select } ) {
		const { getEditedEntityRecord } = select( coreDataStore );
		if ( ! bindings || ! context?.postType || ! context?.postId ) return;

		const postType = context.postType;
		const postId = context.postId;
		const currentPost = getEditedEntityRecord(
			'postType',
			postType,
			postId
		);
		const currentAcfData = currentPost?.acf || {};
		const fieldsToUpdate = {};

		for ( const [ attribute, binding ] of Object.entries( bindings ) ) {
			const fieldName = binding?.args?.field;
			const newValue = binding?.newValue;
			if ( ! fieldName || newValue === undefined ) continue;
			if ( ! fieldsToUpdate[ fieldName ] ) {
				fieldsToUpdate[ fieldName ] = newValue;
			} else if (
				attribute === 'url' &&
				typeof fieldsToUpdate[ fieldName ] === 'object'
			) {
				fieldsToUpdate[ fieldName ] = {
					...fieldsToUpdate[ fieldName ],
					url: newValue,
				};
			} else if ( attribute === 'id' && typeof newValue === 'number' ) {
				fieldsToUpdate[ fieldName ] = newValue;
			}
		}

		dispatch( coreDataStore ).editEntityRecord(
			'postType',
			postType,
			postId,
			{
				acf: { ...currentAcfData, ...fieldsToUpdate },
				meta: { _acf_changed: 1 },
			}
		);
	},
	canUserEditValue: () => true,
} );
