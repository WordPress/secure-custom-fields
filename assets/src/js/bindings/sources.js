/**
 * WordPress dependencies.
 */
import { registerBlockBindingsSource } from '@wordpress/blocks';
import { store as coreDataStore } from '@wordpress/core-data';
import { format } from '@wordpress/date';

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

	switch ( fieldType ) {
		case 'number':
		case 'range':
			if ( attribute === 'content' ) {
				return fieldValue.toString() || '';
			}
			break;
		case 'date_picker':
			if ( ! fieldValue ) {
				return '';
			}
			/**
			 * On the server side, we use `date_i18n()` (PHP) to format dates, see
			 * https://developer.wordpress.org/reference/functions/date_i18n/.
			 * However, the client-side (JS) version, `dateI18n()`, seems to have a bug
			 * that gets the timezone wrong. When the WordPress install's timezone is set
			 * to UTC, and the client timezone is UTC+x, it will return the _previous_ day.
			 * This is probably because a date without a time is treated as midnight UTC,
			 * which is still the previous day in UTC+x timezones (i.e. east of Greenwich).
			 * Since we aren't interested in times and timezones for date picker fields,
			 * we can simply use the `format()` function to format the date.
			 */
			return format( fieldConfig?.display_format, fieldValue ) || '';
		case 'image':
			// fieldValue is a (numeric) image ID.
			const imageObj = getMedia( fieldValue );
			return resolveImageAttribute( imageObj, attribute );
		case 'select':
		case 'text':
		case 'textarea':
		case 'url':
		default:
			return fieldValue || '';
	}
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
