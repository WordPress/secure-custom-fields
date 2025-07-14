/**
 * WordPress dependencies.
 */
import { registerBlockBindingsSource } from '@wordpress/blocks';
import { store as coreDataStore } from '@wordpress/core-data';

/**
 * Get the SCF fields from the post entity.
 *
 * @param {Object} post The post entity object.
 * @returns {Object} The SCF fields object with source data.
 */
const getSCFFields = ( post ) => {
	if ( ! post?.acf ) {
		return {};
	}

	// Extract only the _source fields which contain the formatted data
	const sourceFields = {};
	Object.entries( post.acf ).forEach( ( [ key, value ] ) => {
		if ( key.endsWith( '_source' ) ) {
			// Remove the _source suffix to get the field name
			const fieldName = key.replace( '_source', '' );
			sourceFields[ fieldName ] = value;
		}
	} );

	return sourceFields;
};

/**
 * Resolve image attribute values from an image object.
 *
 * @param {Object} imageObj The image object from SCF field data.
 * @param {string} attribute The attribute to resolve.
 * @returns {string} The resolved attribute value.
 */
const resolveImageAttribute = ( imageObj, attribute ) => {
	if ( ! imageObj ) return '';
	switch ( attribute ) {
		case 'url':
			return imageObj.url || '';
		case 'alt':
			return imageObj.alt || '';
		case 'title':
			return imageObj.title || '';
		case 'id':
			return imageObj.id || imageObj.ID || '';
		default:
			return '';
	}
};

/**
 * Process a single field binding and return its resolved value.
 *
 * @param {string} attribute The attribute being bound.
 * @param {Object} args The binding arguments.
 * @param {Object} scfFields The SCF fields object.
 * @returns {string} The resolved field value.
 */
const processFieldBinding = ( attribute, args, scfFields ) => {
	const fieldName = args?.key;
	const fieldConfig = scfFields[ fieldName ];

	if ( ! fieldConfig ) {
		return '';
	}

	const fieldType = fieldConfig.type;
	const fieldValue = fieldConfig.formatted_value;

	switch ( fieldType ) {
		case 'image':
			return resolveImageAttribute( fieldValue, attribute );
		case 'checkbox':
			// For checkbox fields, join array values or return as string
			if ( Array.isArray( fieldValue ) ) {
				return fieldValue.join( ', ' );
			}
			return fieldValue ? fieldValue.toString() : '';
		case 'number':
		case 'range':
			return fieldValue ? fieldValue.toString() : '';
		case 'date_picker':
		case 'text':
		case 'textarea':
		case 'url':
		case 'email':
		case 'select':
		default:
			return fieldValue ? fieldValue.toString() : '';
	}
};

registerBlockBindingsSource( {
	name: 'acf/field',
	label: 'SCF Fields',
	getValues( { context, bindings, select } ) {
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
