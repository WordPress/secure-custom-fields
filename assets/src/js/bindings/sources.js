/**
 * WordPress dependencies.
 */
import { registerBlockBindingsSource } from '@wordpress/blocks';
import { store as coreDataStore } from '@wordpress/core-data';
import { dateI18n } from '@wordpress/date';

/**
 * Get the value of a specific field from the SCF fields.
 *
 * @param {Object} fields The SCF fields object.
 * @param {string} fieldName The name of the field to retrieve.
 * @returns {*} The value of the specified field, or undefined if not found.
 */
const getFieldValue = ( fields, fieldName ) => fields?.acf?.[ fieldName ];

/**
 * Create a lookup map of field names to field objects from field groups.
 *
 * @param {Object} fields The fields object containing scf_field_groups.
 * @returns {Object} A map of field names to field objects.
 */
const createFieldsLookupMap = ( fields ) => {
	if ( ! fields?.scf_field_groups ) {
		return {};
	}

	return Object.fromEntries(
		fields.scf_field_groups
			.flatMap( ( group ) => group.fields || [] )
			.map( ( field ) => [ field.name, field ] )
	);
};

/**
 * Resolve image attribute values from an image object.
 *
 * @param {Object} imageObj The image object from WordPress media.
 * @param {string} attribute The attribute to resolve.
 * @returns {string} The resolved attribute value.
 */
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

/**
 * Handle object-type field values (like complex field objects).
 *
 * @param {Object} fieldValue The field value object.
 * @param {string} attribute The attribute being processed.
 * @returns {string} The resolved value.
 */
const handleObjectFieldValue = ( fieldValue, attribute ) => {
	// Check if the field value has the exact attribute property
	if ( fieldValue.hasOwnProperty( attribute ) && fieldValue[ attribute ] ) {
		return fieldValue[ attribute ];
	}

	// Special fallback: if we're looking for 'content' and no content property exists (or is falsy),
	// but there's a 'url' property, use that instead
	if ( attribute === 'content' && fieldValue.url ) {
		return fieldValue.url;
	}

	return '';
};

/**
 * Handle numeric field values (typically image IDs).
 *
 * @param {number} fieldValue The numeric field value.
 * @param {string} attribute The attribute being processed.
 * @param {Function} getMedia Function to get media object by ID.
 * @returns {string} The resolved value.
 */
const handleNumericFieldValue = ( fieldValue, attribute, getMedia ) => {
	if ( attribute === 'content' ) {
		return fieldValue.toString() || '';
	}

	// For image fields or numeric values, try to resolve as media
	const imageObj = getMedia( fieldValue );
	return resolveImageAttribute( imageObj, attribute );
};

/**
 * Handle date picker field values.
 *
 * @param {string} fieldValue The date field value.
 * @param {Object} fieldConfig The field configuration object.
 * @returns {string} The formatted date string.
 */
const handleDateFieldValue = ( fieldValue, fieldConfig ) => {
	if ( ! fieldValue ) {
		return '';
	}

	return dateI18n( fieldConfig?.display_format, fieldValue ) || '';
};

/**
 * Process a single field binding and return its resolved value.
 *
 * @param {string} attribute The attribute being bound.
 * @param {Object} args The binding arguments.
 * @param {Object} fields The post fields object.
 * @param {Object} fieldsLookupMap The fields lookup map.
 * @param {Function} getMedia Function to get media object by ID.
 * @returns {string} The resolved field value.
 */
const processFieldBinding = (
	attribute,
	args,
	fields,
	fieldsLookupMap,
	getMedia
) => {
	const fieldName = args?.key;
	const fieldValue = getFieldValue( fields, fieldName );
	const fieldConfig = fieldsLookupMap[ fieldName ];
	const fieldType = fieldConfig?.type;

	if ( typeof fieldValue === 'object' && fieldValue !== null ) {
		return handleObjectFieldValue( fieldValue, attribute );
	}

	if ( typeof fieldValue === 'number' ) {
		return handleNumericFieldValue( fieldValue, attribute, getMedia );
	}

	if ( fieldType === 'date_picker' ) {
		return handleDateFieldValue( fieldValue, fieldConfig );
	}

	return fieldValue || '';
};

registerBlockBindingsSource( {
	name: 'acf/field',
	label: 'SCF Fields',
	getValues( { context, bindings, select } ) {
		const { getEditedEntityRecord, getMedia } = select( coreDataStore );

		const fields =
			context?.postType && context?.postId
				? getEditedEntityRecord(
						'postType',
						context.postType,
						context.postId
				  )
				: undefined;

		const fieldsLookupMap = createFieldsLookupMap( fields );

		const result = {};

		Object.entries( bindings ).forEach(
			( [ attribute, { args } = {} ] ) => {
				result[ attribute ] = processFieldBinding(
					attribute,
					args,
					fields,
					fieldsLookupMap,
					getMedia
				);
			}
		);

		return result;
	},
	canUserEditValue() {
		return false;
	},
} );
