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

		const currentPost = getEditedEntityRecord(
			'postType',
			context.postType,
			context.postId
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

		// Format ACF data properly before saving
		const formattedAcfData = { ...currentAcfData };

		// Process all ACF fields to ensure proper types
		const allAcfFields = { ...formattedAcfData, ...fieldsToUpdate };
		const processedAcfData = {};

		for ( const [ key, value ] of Object.entries( allAcfFields ) ) {
			// Handle specific field types requiring proper type conversion
			if ( value === '' ) {
				// Convert empty strings to appropriate types based on field name
				if (
					key === 'number' ||
					key.includes( '_number' ) ||
					/number$/.test( key )
				) {
					// Number fields should be null when empty
					processedAcfData[ key ] = null;
				} else if ( key.includes( 'range' ) || key === 'range_type' ) {
					// Range fields should be null when empty
					processedAcfData[ key ] = null;
				} else if ( key.includes( '_date' ) ) {
					// Date fields should be null when empty
					processedAcfData[ key ] = null;
				} else if ( key.includes( 'email' ) || key === 'email_type' ) {
					// Handle email fields
					processedAcfData[ key ] = null;
				} else if ( key.includes( 'url' ) || key === 'url_type' ) {
					// Handle URL fields
					processedAcfData[ key ] = null;
				} else {
					// Other fields can remain as empty strings
					processedAcfData[ key ] = value;
				}
			} else if ( value === 0 || value ) {
				// Non-empty values - ensure numbers are actually numbers
				if (
					( key === 'number' ||
						key.includes( '_number' ) ||
						/number$/.test( key ) ) &&
					value !== null
				) {
					// Convert string numbers to actual numbers if needed
					const numValue = parseFloat( value );
					processedAcfData[ key ] = isNaN( numValue )
						? null
						: numValue;
				} else {
					processedAcfData[ key ] = value;
				}
			} else {
				// null, undefined, etc.
				processedAcfData[ key ] = value;
			}
		}

		dispatch( coreDataStore ).editEntityRecord(
			'postType',
			context.postType,
			context.postId,
			{
				acf: processedAcfData,
				meta: { _acf_changed: 1 },
			}
		);
	},
	canUserEditValue( { select, context, args } ) {
		// Lock editing in query loop.
		if ( context?.query || context?.queryId ) {
			return false;
		}

		// Lock editing when `postType` is not defined.
		if ( ! context?.postType ) {
			return false;
		}

		// Check that the user has the capability to edit post meta.
		const canUserEdit = select( coreDataStore ).canUser( 'update', {
			kind: 'postType',
			name: context?.postType,
			id: context?.postId,
		} );
		if ( ! canUserEdit ) {
			return false;
		}

		return true;
	},
} );
