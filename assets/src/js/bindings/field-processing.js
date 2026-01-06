/**
 * Field processing utilities for block bindings
 *
 * @since 6.5.0
 */

/**
 * Gets the SCF fields from the post entity.
 *
 * @since 6.5.0
 *
 * @param {Object} post The post entity object.
 * @return {Object} The SCF fields object with source data.
 */
export function getSCFFields( post ) {
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
}

/**
 * Resolves image attribute values from an image object.
 *
 * @since 6.5.0
 *
 * @param {Object} imageObj  The image object from SCF field data.
 * @param {string} attribute The attribute to resolve (url, alt, title, id).
 * @return {string|number} The resolved attribute value.
 */
export function resolveImageAttribute( imageObj, attribute ) {
	if ( ! imageObj ) {
		return '';
	}

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
}

/**
 * Processes a single field binding and returns its resolved value.
 *
 * @since 6.7.0
 *
 * @param {string} attribute The attribute being bound.
 * @param {Object} args      The binding arguments.
 * @param {Object} scfFields The SCF fields object.
 * @return {string} The resolved field value.
 */
export function processFieldBinding( attribute, args, scfFields ) {
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
			return fieldValue ? String( fieldValue ) : '';

		case 'number':
		case 'range':
			return fieldValue ? String( fieldValue ) : '';

		case 'date_picker':
		case 'text':
		case 'textarea':
		case 'url':
		case 'email':
		case 'select':
		default:
			return fieldValue ? String( fieldValue ) : '';
	}
}

/**
 * Formats a field key into a human-readable label.
 *
 * @since 6.7.0
 *
 * @param {string} fieldKey The field key (e.g., 'my_field_name').
 * @return {string} Formatted label (e.g., 'My Field Name').
 */
export function formatFieldLabel( fieldKey ) {
	if ( ! fieldKey ) {
		return '';
	}

	return fieldKey
		.split( '_' )
		.map( ( word ) => word.charAt( 0 ).toUpperCase() + word.slice( 1 ) )
		.join( ' ' );
}

/**
 * Gets the field label from metadata or formats the field key.
 *
 * @since 6.7.0
 * @param {string} fieldKey        The field key.
 * @param {Object} fieldMetadata   Optional field metadata object.
 * @param {string} defaultLabel    Optional default label to use.
 * @return {string} The field label.
 */
export function getFieldLabel(
	fieldKey,
	fieldMetadata = null,
	defaultLabel = ''
) {
	if ( ! fieldKey ) {
		return defaultLabel;
	}

	// Try to get the label from the provided metadata
	if ( fieldMetadata && fieldMetadata[ fieldKey ]?.label ) {
		return fieldMetadata[ fieldKey ].label;
	}

	// Fallback: format field key as a label
	return formatFieldLabel( fieldKey );
}
